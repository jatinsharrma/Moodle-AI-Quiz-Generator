<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_ai_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PDF text extraction utility.
 *
 * IMPORTANT: this class never guesses. If it cannot extract real text it throws
 * {@see extraction_unavailable} so the caller can fall back to having the AI
 * read the original file. It must NOT return approximate or scraped content:
 * doing so previously caused the AI to be handed binary noise plus the PDF's
 * (uncompressed) title metadata, from which it generated plausible-looking
 * questions out of its own training knowledge rather than the teacher's
 * document.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_extractor {

    /** Minimum characters of extracted text before we trust it. */
    const MIN_TEXT_CHARS = 200;

    /** Minimum word count before we trust extracted text. */
    const MIN_TEXT_WORDS = 40;

    /** Minimum proportion of plausible text characters before we trust it. */
    const MIN_TEXT_RATIO = 0.85;

    /**
     * Extract text from a PDF, optionally limited to a page range.
     *
     * @param string $filepath Path to PDF file
     * @param int|null $frompage Start page (1-indexed)
     * @param int|null $topage End page (1-indexed)
     * @return string Extracted text content
     * @throws extraction_unavailable If no usable text could be extracted
     * @throws \moodle_exception If the file is missing or the range is invalid
     */
    public static function extract_pages($filepath, $frompage = null, $topage = null) {
        if (!file_exists($filepath)) {
            throw new \moodle_exception('error:pdf_not_found', 'local_ai_quiz', '', $filepath);
        }

        if ($frompage !== null && $topage !== null) {
            if ($frompage < 1 || $topage < $frompage) {
                throw new \moodle_exception('error:invalid_page_range', 'local_ai_quiz',
                    '', "Pages {$frompage}-{$topage}");
            }
            debugging("Extracting PDF pages {$frompage}-{$topage} from: {$filepath}", DEBUG_DEVELOPER);
        }

        if (!self::is_text_extraction_available()) {
            // No toolchain. The caller decides what to do - typically hand the
            // original PDF to the AI provider to read natively.
            throw new extraction_unavailable('notoolchain',
                'pdftotext is not available (not installed, or exec() is disabled in PHP).');
        }

        $text = self::extract_with_pdftotext($filepath, $frompage, $topage);

        if (!self::looks_like_text($text)) {
            // Ran fine but produced nothing usable - typically a scanned or
            // image-only PDF with no embedded text layer.
            throw new extraction_unavailable('notext',
                'pdftotext produced no usable text (' . strlen($text) . ' bytes). '
                . 'The PDF is most likely scanned or image-only.');
        }

        return $text;
    }

    /**
     * Whether extracted output is plausibly real prose rather than noise.
     *
     * @param string $text Candidate extracted text
     * @return bool True if the text looks like usable content
     */
    public static function looks_like_text($text) {
        if ($text === null) {
            return false;
        }

        $trimmed = trim($text);
        if ($trimmed === '' || \core_text::strlen($trimmed) < self::MIN_TEXT_CHARS) {
            return false;
        }

        // Must contain a reasonable number of actual words.
        $wordcount = preg_match_all('/[\p{L}]{2,}/u', $trimmed);
        if ($wordcount < self::MIN_TEXT_WORDS) {
            return false;
        }

        // Must be overwhelmingly composed of plausible text characters. This is a
        // backstop against any path that manages to hand us binary.
        $total = strlen($trimmed);
        $texty = preg_match_all('/[\p{L}\p{N}\p{P}\p{Zs}\s]/u', $trimmed);
        if ($total > 0 && ($texty / $total) < self::MIN_TEXT_RATIO) {
            return false;
        }

        return true;
    }

    /**
     * Extract text using the pdftotext command.
     *
     * @param string $filepath Path to PDF file
     * @param int|null $frompage Start page (optional)
     * @param int|null $topage End page (optional)
     * @return string Extracted text
     * @throws \moodle_exception If pdftotext reports a failure
     */
    private static function extract_with_pdftotext($filepath, $frompage = null, $topage = null) {
        $command = 'pdftotext';

        // Add page range arguments if specified.
        if ($frompage !== null && $topage !== null) {
            // Cast to int for safety (already validated as positive integers).
            // Don't use escapeshellarg on numbers - some pdftotext versions don't accept quoted numbers.
            $command .= ' -f ' . (int)$frompage;
            $command .= ' -t ' . (int)$topage;
        }

        $command .= ' ' . escapeshellarg($filepath) . ' - 2>/dev/null';

        debugging("Executing pdftotext command: {$command}", DEBUG_DEVELOPER);

        $output = [];
        $returnvar = 0;
        exec($command, $output, $returnvar);

        if ($returnvar !== 0) {
            $errormsg = "Exit code: {$returnvar}";

            // Provide helpful error messages based on exit code.
            if ($returnvar == 99) {
                if ($frompage !== null && $topage !== null) {
                    $errormsg .= " - Invalid page range ({$frompage}-{$topage}) or PDF has issues with page extraction. Try without page range first.";
                } else {
                    $errormsg .= " - PDF file is corrupted, encrypted, or not a valid PDF. Please check the file.";
                }
            } else if ($returnvar == 1) {
                $errormsg .= " - Error opening PDF file.";
            } else if ($returnvar == 2) {
                $errormsg .= " - Error opening output file.";
            } else if ($returnvar == 3) {
                $errormsg .= " - Error related to PDF permissions (the PDF may be password protected).";
            }

            throw new \moodle_exception('error:pdftotext_failed', 'local_ai_quiz', '', $errormsg);
        }

        return implode("\n", $output);
    }

    /**
     * Check if pdftotext can actually be run from PHP.
     *
     * @return bool True if the pdftotext command is usable
     */
    public static function is_text_extraction_available() {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        // exec() is disabled on many hardened/shared hosts.
        if (!function_exists('exec')
                || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))))) {
            debugging('exec() function is disabled in PHP. Cannot use pdftotext.', DEBUG_DEVELOPER);
            $available = false;
            return $available;
        }

        $output = [];
        $returnvar = 0;
        exec('which pdftotext 2>/dev/null', $output, $returnvar);
        $available = ($returnvar === 0 && !empty($output));

        if ($available) {
            debugging('pdftotext found at: ' . $output[0], DEBUG_DEVELOPER);
            return $available;
        }

        // Try alternative locations for hosts with a restricted PATH.
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext'] as $path) {
            if (@is_executable($path)) {
                $available = true;
                debugging('pdftotext found at: ' . $path, DEBUG_DEVELOPER);
                return $available;
            }
        }

        debugging('pdftotext NOT found. Install poppler-utils for local text extraction.', DEBUG_DEVELOPER);
        return $available;
    }

    /**
     * Parse a page range string.
     *
     * @param string|null $rangestr Page range string like "10-20" or "5 - 15"
     * @return array|null Array with 'from' and 'to' keys, or null if invalid
     */
    public static function parse_page_range($rangestr) {
        // PHP 8.1 compatibility: check null before trim.
        if ($rangestr === null || $rangestr === '') {
            return null;
        }

        $rangestr = trim($rangestr);

        if ($rangestr === '') {
            return null;
        }

        // Match patterns like "10-20", "5 - 15", etc.
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $rangestr, $matches)) {
            $from = (int)$matches[1];
            $to = (int)$matches[2];

            if ($from >= 1 && $to >= $from) {
                return ['from' => $from, 'to' => $to];
            }
        }

        return null;
    }
}
