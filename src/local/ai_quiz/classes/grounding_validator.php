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
     * Share of content words two questions must have in common to count as duplicates.
     *
     * Deliberately conservative. Measured separation between reworded duplicates
     * and genuinely different questions on the same topic is narrow (duplicates
     * from ~0.75, distinct questions up to ~0.67), and token overlap cannot
     * reliably tell "How wide is X?" from "What is X equivalent to?". The costs
     * are asymmetric: rejecting a good question discards verified work, whereas a
     * missed near-duplicate is visible to the teacher in the preview. So this
     * sits well above the distinct-question ceiling and accepts that the weakest
     * duplicates slip through. The prompt instruction is the primary defence;
     * this is the backstop.
     */
    const DUPLICATE_THRESHOLD = 0.8;

    /** Questions with fewer content words than this are not judged for duplication. */
    const MIN_DUPLICATE_WORDS = 3;

    /**
     * Words carrying no topical meaning, ignored when comparing questions.
     *
     * Question stems are formulaic ("which of the following describes..."), so
     * without this two questions about entirely different topics can look alike.
     */
    const STOPWORDS = [
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'had', 'her',
        'was', 'one', 'our', 'out', 'has', 'him', 'his', 'how', 'its', 'may', 'new', 'now',
        'old', 'see', 'two', 'who', 'did', 'get', 'let', 'put', 'say', 'she', 'too', 'use',
        'what', 'which', 'when', 'where', 'that', 'this', 'with', 'from', 'they', 'them',
        'have', 'been', 'were', 'does', 'their', 'there', 'these', 'those', 'would', 'about',
        'following', 'best', 'most', 'true', 'false', 'statement', 'statements', 'describes',
        'correct', 'incorrect', 'select', 'apply', 'according', 'given', 'below', 'above',
    ];

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

    /**
     * Proportion of the shorter question's content words shared with the longer.
     *
     * Overlap coefficient rather than Jaccard: "What is the purpose of the TTL
     * field?" and "What does the TTL field do?" are the same question, but
     * Jaccard scores them low because the differing filler words inflate the
     * union. Only content words are compared, so wording changes that carry no
     * meaning do not disguise a repeat.
     *
     * @param string $a First question text
     * @param string $b Second question text
     * @return float Between 0.0 and 1.0
     */
    public static function question_overlap($a, $b) {
        $atokens = self::content_words($a);
        $btokens = self::content_words($b);

        $shorter = min(count($atokens), count($btokens));
        if ($shorter === 0) {
            return 0.0;
        }

        $shared = count(array_intersect($atokens, $btokens));

        return $shared / $shorter;
    }

    /**
     * Whether a question repeats something already in a set of questions.
     *
     * @param array $question The candidate question
     * @param array $existing Questions already accepted
     * @return bool True if the candidate duplicates one of them
     */
    public static function is_duplicate_question($question, array $existing) {
        $text = $question['question'] ?? '';
        if (trim($text) === '') {
            return false;
        }

        // Too few distinctive words to judge; treat as not a duplicate.
        if (count(self::content_words($text)) < self::MIN_DUPLICATE_WORDS) {
            return false;
        }

        foreach ($existing as $other) {
            if (self::question_overlap($text, $other['question'] ?? '') >= self::DUPLICATE_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * Distinct, meaning-bearing words of a string.
     *
     * @param string $text Input text
     * @return array Unique content words
     */
    private static function content_words($text) {
        $normalised = self::normalize($text);
        preg_match_all('/[\p{L}\p{N}]+/u', $normalised, $matches);

        $words = [];
        foreach ($matches[0] as $word) {
            if (\core_text::strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $words[self::stem($word)] = true;
        }

        return array_keys($words);
    }

    /**
     * Crudest possible stemming: fold a trailing plural "s".
     *
     * Enough to stop "packet" and "packets" being treated as unrelated words,
     * which otherwise hides genuine duplicates. Deliberately minimal - anything
     * more aggressive starts merging words that mean different things.
     *
     * @param string $word A lowercase word
     * @return string The stemmed word
     */
    private static function stem($word) {
        $length = strlen($word);

        // Leave "address", "class" and friends alone.
        if ($length > 3 && substr($word, -1) === 's' && substr($word, -2, 1) !== 's') {
            return substr($word, 0, $length - 1);
        }

        return $word;
    }
}
