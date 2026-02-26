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
 * PDF page extraction utility
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_extractor {

    /**
     * Extract specific pages from PDF
     *
     * @param string $filepath Path to PDF file
     * @param int $frompage Start page (1-indexed)
     * @param int $topage End page (1-indexed)
     * @return string Extracted text content
     */
    public static function extract_pages($filepath, $frompage = null, $topage = null) {
        if (!file_exists($filepath)) {
            throw new \moodle_exception('error:pdf_not_found', 'local_ai_quiz', '', $filepath);
        }

        // If no page range specified, extract all pages
        if ($frompage === null || $topage === null) {
            return self::extract_all_pages($filepath);
        }

        // Validate page range
        if ($frompage < 1 || $topage < $frompage) {
            throw new \moodle_exception('error:invalid_page_range', 'local_ai_quiz',
                '', "Pages {$frompage}-{$topage}");
        }

        debugging("Extracting PDF pages {$frompage}-{$topage} from: {$filepath}", DEBUG_DEVELOPER);

        // Try pdftotext with page range
        if (self::is_pdftotext_available()) {
            return self::extract_with_pdftotext($filepath, $frompage, $topage);
        }

        // Fallback: Extract all and filter (not ideal but works)
        debugging("pdftotext not available, falling back to full extraction", DEBUG_DEVELOPER);
        $alltext = self::extract_all_pages($filepath);

        // Simple heuristic: assume each page has ~50 lines of text
        // This is approximate and not perfect
        $lines = explode("\n", $alltext);
        $linesperpage = 50;
        $startline = ($frompage - 1) * $linesperpage;
        $endline = $topage * $linesperpage;

        $pagetext = array_slice($lines, $startline, $endline - $startline);
        return implode("\n", $pagetext);
    }

    /**
     * Extract all pages from PDF
     *
     * @param string $filepath Path to PDF file
     * @return string Extracted text content
     */
    private static function extract_all_pages($filepath) {
        if (self::is_pdftotext_available()) {
            return self::extract_with_pdftotext($filepath);
        }

        // Fallback: basic extraction
        debugging("pdftotext not available, using fallback method", DEBUG_DEVELOPER);

        // Try to read PDF as text (very basic, won't work for complex PDFs)
        $content = file_get_contents($filepath);

        // Simple text extraction (NOT reliable for all PDFs)
        // This is a very basic fallback and should not be relied upon
        $text = '';
        if (preg_match_all('/\((.*?)\)/s', $content, $matches)) {
            $text = implode(' ', $matches[1]);
        }

        if (empty($text)) {
            throw new \moodle_exception('error:pdf_extraction_failed', 'local_ai_quiz',
                '', 'pdftotext not available and fallback failed');
        }

        return $text;
    }

    /**
     * Extract text using pdftotext command
     *
     * @param string $filepath Path to PDF file
     * @param int|null $frompage Start page (optional)
     * @param int|null $topage End page (optional)
     * @return string Extracted text
     */
    private static function extract_with_pdftotext($filepath, $frompage = null, $topage = null) {
        $command = 'pdftotext';

        // Add page range arguments if specified
        if ($frompage !== null && $topage !== null) {
            // Cast to int for safety (already validated as positive integers)
            // Don't use escapeshellarg on numbers - some pdftotext versions don't accept quoted numbers
            $command .= ' -f ' . (int)$frompage;
            $command .= ' -t ' . (int)$topage;
        }

        $command .= ' ' . escapeshellarg($filepath) . ' -';

        // Debug: log the actual command
        debugging("Executing pdftotext command: {$command}", DEBUG_DEVELOPER);

        $output = [];
        $returnvar = 0;
        exec($command, $output, $returnvar);

        if ($returnvar !== 0) {
            $errormsg = "Exit code: {$returnvar}";

            // Provide helpful error messages based on exit code
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
                $errormsg .= " - Error related to PDF permissions.";
            }

            throw new \moodle_exception('error:pdftotext_failed', 'local_ai_quiz',
                '', $errormsg);
        }

        $text = implode("\n", $output);

        // PHP 8.1 compatibility: ensure $text is not null
        if ($text === null || trim($text) === '') {
            throw new \moodle_exception('error:pdf_empty', 'local_ai_quiz');
        }

        return $text;
    }

    /**
     * Check if pdftotext is available
     *
     * @return bool True if pdftotext command is available
     */
    private static function is_pdftotext_available() {
        static $available = null;

        if ($available === null) {
            // First check if exec() is enabled
            if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
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
            } else {
                // Try alternative locations
                foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $path) {
                    if (file_exists($path)) {
                        $available = true;
                        debugging('pdftotext found at: ' . $path, DEBUG_DEVELOPER);
                        break;
                    }
                }
                if (!$available) {
                    debugging('pdftotext NOT found. Run: sudo apt-get install poppler-utils', DEBUG_DEVELOPER);
                }
            }
        }

        return $available;
    }

    /**
     * Parse page range string
     *
     * @param string|null $rangestr Page range string like "10-20" or "5-15"
     * @return array|null Array with 'from' and 'to' keys, or null if invalid
     */
    public static function parse_page_range($rangestr) {
        // PHP 8.1 compatibility: check null before trim
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
