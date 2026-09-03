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
 * Verifies that generated questions are actually grounded in the source material.
 *
 * The AI is required to return a verbatim `source_quote` for every question. This
 * class checks that the quote really does appear in the extracted primary text.
 * A question whose quote cannot be located is almost certainly drawn from the
 * model's own knowledge rather than from the teacher's document.
 *
 * Matching is deliberately tolerant: extracted PDF text is full of hard line
 * wraps, hyphenation and typographic punctuation, and models routinely
 * "quote" with tiny whitespace or punctuation drift. We normalise aggressively
 * and then fall back to a longest-contiguous-run comparison so that trivial
 * drift does not produce false alarms.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grounding_validator {

    /** Question quote was located in the source material. */
    const STATUS_VERIFIED = 'verified';

    /** Question quote could NOT be located - likely not from the document. */
    const STATUS_UNGROUNDED = 'ungrounded';

    /** No extracted text to check against (e.g. PDF was read natively by the AI). */
    const STATUS_UNVERIFIABLE = 'unverifiable';

    /** The AI did not supply a quote at all. */
    const STATUS_NOQUOTE = 'noquote';

    /**
     * Shortest contiguous token run that counts as a genuine fragment of the
     * source. Runs of one or two words match by coincidence constantly.
     */
    const MIN_RUN_TOKENS = 3;

    /**
     * Fraction of a quote that must be covered by genuine fragments.
     *
     * Chosen from measured separation between real and invented quotes: real
     * quotes (including ones spliced across two sentences, or with punctuation
     * and line-wrap drift) score >= 0.94, while invented ones - including a
     * quote that reuses the document's own phrases in a different order, and one
     * that changes only a single number - score <= 0.71.
     */
    const MIN_COVERAGE_RATIO = 0.8;

    /**
     * Secondary guard: at least one fragment must be a decent proportion of the
     * quote. Stops a quote stitched together from many tiny fragments passing on
     * coverage alone.
     */
    const MIN_RUN_RATIO = 0.35;

    /** Quotes shorter than this (in words) are too weak to verify meaningfully. */
    const MIN_QUOTE_WORDS = 4;

    /**
     * Normalise text for comparison.
     *
     * Joins words broken across lines by hyphenation, folds all typographic
     * punctuation to ASCII, collapses whitespace and lowercases.
     *
     * @param string $text Raw text
     * @return string Normalised text
     */
    public static function normalize($text) {
        if ($text === null) {
            return '';
        }

        // Rejoin words hyphenated across a line break: "frag-\nmentation" => "fragmentation".
        $text = preg_replace('/(\p{L})[\x{00AD}\-]\s*\R\s*(\p{L})/u', '$1$2', $text);

        // Fold typographic punctuation to ASCII equivalents.
        $replacements = [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{201B}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{201F}" => '"',
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-',
            "\u{2014}" => '-', "\u{2015}" => '-', "\u{2212}" => '-',
            "\u{00A0}" => ' ', "\u{2007}" => ' ', "\u{202F}" => ' ',
            "\u{2026}" => '...', "\u{FB01}" => 'fi', "\u{FB02}" => 'fl',
        ];
        $text = strtr($text, $replacements);

        $text = \core_text::strtolower($text);

        // Collapse every run of whitespace to a single space.
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Split normalised text into comparable word tokens.
     *
     * @param string $normalised Already-normalised text
     * @return array List of tokens
     */
    private static function tokenize($normalised) {
        preg_match_all('/[\p{L}\p{N}]+/u', $normalised, $matches);
        return $matches[0];
    }

    /**
     * Check whether a quote appears in the source text.
     *
     * @param string $quote The verbatim quote claimed by the AI
     * @param string $sourcetext The extracted primary source text
     * @return string One of the STATUS_* constants
     */
    public static function verify($quote, $sourcetext) {
        if ($quote === null || trim($quote) === '') {
            return self::STATUS_NOQUOTE;
        }
        if ($sourcetext === null || trim($sourcetext) === '') {
            return self::STATUS_UNVERIFIABLE;
        }

        $nquote = self::normalize($quote);
        $nsource = self::normalize($sourcetext);

        // Fast path: the quote is genuinely verbatim.
        if ($nquote !== '' && strpos($nsource, $nquote) !== false) {
            return self::STATUS_VERIFIED;
        }

        $qtokens = self::tokenize($nquote);
        $stokens = self::tokenize($nsource);

        if (count($qtokens) < self::MIN_QUOTE_WORDS) {
            // Too short to distinguish a real quote from a coincidence.
            return self::STATUS_NOQUOTE;
        }
        if (empty($stokens)) {
            return self::STATUS_UNVERIFIABLE;
        }

        $scores = self::match_scores($qtokens, $stokens);

        $grounded = ($scores['coverage'] >= self::MIN_COVERAGE_RATIO
                && $scores['longest'] >= self::MIN_RUN_RATIO);

        return $grounded ? self::STATUS_VERIFIED : self::STATUS_UNGROUNDED;
    }

    /**
     * Score how much of a quote genuinely appears in the source.
     *
     * Returns two figures:
     *  - coverage: fraction of the quote's tokens that sit inside a contiguous
     *    run of at least MIN_RUN_TOKENS that also appears in the source. This
     *    tolerates a quote spliced across two sentences while still rejecting one
     *    assembled from the source's vocabulary but not its actual wording.
     *  - longest: the longest single such run, as a fraction of the quote.
     *
     * @param array $qtokens Quote tokens
     * @param array $stokens Source tokens
     * @return array ['coverage' => float, 'longest' => float]
     */
    private static function match_scores(array $qtokens, array $stokens) {
        $qlen = count($qtokens);
        if ($qlen === 0) {
            return ['coverage' => 0.0, 'longest' => 0.0];
        }

        // Index source token positions so we can jump straight to candidates.
        $positions = [];
        foreach ($stokens as $i => $tok) {
            $positions[$tok][] = $i;
        }

        // Guard against pathological cost on very large documents.
        $maxcandidates = 500;
        $slen = count($stokens);
        $longest = 0;
        $covered = array_fill(0, $qlen, false);

        for ($i = 0; $i < $qlen; $i++) {
            if (!isset($positions[$qtokens[$i]])) {
                continue;
            }

            $candidates = $positions[$qtokens[$i]];
            if (count($candidates) > $maxcandidates) {
                $candidates = array_slice($candidates, 0, $maxcandidates);
            }

            $bestrun = 0;
            foreach ($candidates as $start) {
                $run = 0;
                while ($i + $run < $qlen
                        && $start + $run < $slen
                        && $qtokens[$i + $run] === $stokens[$start + $run]) {
                    $run++;
                }
                if ($run > $bestrun) {
                    $bestrun = $run;
                    if ($i + $bestrun >= $qlen) {
                        break; // Cannot extend further from this position.
                    }
                }
            }

            if ($bestrun > $longest) {
                $longest = $bestrun;
            }

            if ($bestrun >= self::MIN_RUN_TOKENS) {
                for ($d = 0; $d < $bestrun; $d++) {
                    $covered[$i + $d] = true;
                }
            }
        }

        return [
            'coverage' => count(array_filter($covered)) / $qlen,
            'longest' => $longest / $qlen,
        ];
    }

    /**
     * Annotate every question in a quiz payload with its grounding status.
     *
     * Adds a `grounding` key to each question and a `grounding_summary` block to
     * the payload metadata. Never removes questions - the teacher decides.
     *
     * @param array $quizdata Quiz payload from the AI (modified in place)
     * @param string $sourcetext Extracted primary text, or '' if none available
     * @param bool $hasunreadablesources True if some primary material was sent to
     *        the AI as a file we cannot read locally. A quote we fail to locate
     *        may legitimately have come from that file, so it is reported as
     *        unverifiable rather than as ungrounded.
     * @return array The annotated quiz payload
     */
    public static function annotate(array $quizdata, $sourcetext, $hasunreadablesources = false) {
        if (!isset($quizdata['questions']) || !is_array($quizdata['questions'])) {
            return $quizdata;
        }

        $counts = [
            self::STATUS_VERIFIED => 0,
            self::STATUS_UNGROUNDED => 0,
            self::STATUS_UNVERIFIABLE => 0,
            self::STATUS_NOQUOTE => 0,
        ];

        $havesource = ($sourcetext !== null && trim($sourcetext) !== '');

        foreach ($quizdata['questions'] as &$q) {
            $quote = $q['source_quote'] ?? '';

            if (!$havesource) {
                // The AI read the file natively; we have no local text to check against.
                $status = self::STATUS_UNVERIFIABLE;
            } else {
                $status = self::verify($quote, $sourcetext);

                // Part of the primary material was unreadable locally, so a quote
                // we cannot find may have come from there. Don't cry wolf.
                if ($hasunreadablesources && self::is_suspect($status)) {
                    $status = self::STATUS_UNVERIFIABLE;
                }
            }

            $q['grounding'] = $status;
            $counts[$status]++;
        }
        unset($q);

        $total = count($quizdata['questions']);
        $quizdata['metadata']['grounding_summary'] = [
            'total' => $total,
            'verified' => $counts[self::STATUS_VERIFIED],
            'ungrounded' => $counts[self::STATUS_UNGROUNDED],
            'unverifiable' => $counts[self::STATUS_UNVERIFIABLE],
            'noquote' => $counts[self::STATUS_NOQUOTE],
            'checked' => $havesource,
        ];

        return $quizdata;
    }

    /**
     * Whether a status should be treated as a warning the teacher must act on.
     *
     * @param string $status A STATUS_* value
     * @return bool True if the question is not confirmed to come from the source
     */
    public static function is_suspect($status) {
        return in_array($status, [self::STATUS_UNGROUNDED, self::STATUS_NOQUOTE], true);
    }
}
