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
 * Tests for verifying that questions are grounded in the source document.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ai_quiz\grounding_validator
 */
final class grounding_validator_test extends \advanced_testcase {

    /** @var string Stand-in for text extracted from a teacher's PDF. */
    private const SOURCE = <<<'TEXT'
Chapter 5: The Zylonic Transport Protocol (ZTP)

ZTP was ratified by the Kestrel Working Group in 2019. It uses a 14-byte
header and a fixed window size of 37 segments. The TTL-equivalent field in
ZTP is called the Decay Counter and is 5 bits wide.

A ZTP session begins with a three-phase Amber Handshake: PROBE, GRANT, and
SEAL. If the SEAL phase is not acknowledged within 400 ms, the initiator
must abort and enter the Quiet Period of 12 seconds.
TEXT;

    /**
     * A quote copied verbatim from the source is verified.
     */
    public function test_verbatim_quote_is_verified(): void {
        $quote = 'It uses a 14-byte header and a fixed window size of 37 segments.';

        $this->assertEquals(
            grounding_validator::STATUS_VERIFIED,
            grounding_validator::verify($quote, self::SOURCE)
        );
    }

    /**
     * The core case: a question invented from the model's own knowledge. The
     * subject matter is plausible but the quote is not in the teacher's document.
     */
    public function test_hallucinated_quote_is_ungrounded(): void {
        $quote = 'The Time To Live field in an IPv4 header is eight bits wide and is '
            . 'decremented by every router that forwards the packet.';

        $this->assertEquals(
            grounding_validator::STATUS_UNGROUNDED,
            grounding_validator::verify($quote, self::SOURCE)
        );
    }

    /**
     * Extracted PDF text is full of hard line wraps. A quote spanning one must
     * still verify, or every question would be falsely flagged.
     */
    public function test_quote_spanning_a_line_break_is_verified(): void {
        $quote = 'The TTL-equivalent field in ZTP is called the Decay Counter and is 5 bits wide.';

        $this->assertEquals(
            grounding_validator::STATUS_VERIFIED,
            grounding_validator::verify($quote, self::SOURCE)
        );
    }

    /**
     * Typographic drift (smart quotes, en dashes, doubled spaces) must not cause
     * a false alarm.
     */
    public function test_punctuation_drift_is_tolerated(): void {
        $quote = 'A ZTP session begins with a three–phase Amber Handshake:  PROBE, GRANT, and SEAL.';

        $this->assertEquals(
            grounding_validator::STATUS_VERIFIED,
            grounding_validator::verify($quote, self::SOURCE)
        );
    }

    /**
     * Hyphenation across a line break must be rejoined before comparison.
     */
    public function test_hyphenation_across_line_break_is_rejoined(): void {
        $source = "the initiator must abort and enter the Quiet Per-\niod of 12 seconds";
        $quote = 'the initiator must abort and enter the Quiet Period of 12 seconds';

        $this->assertEquals(
            grounding_validator::STATUS_VERIFIED,
            grounding_validator::verify($quote, $source)
        );
    }

    /**
     * A missing or unusably short quote is reported, not silently accepted.
     */
    public function test_missing_or_short_quote_is_flagged(): void {
        $this->assertEquals(
            grounding_validator::STATUS_NOQUOTE,
            grounding_validator::verify('', self::SOURCE)
        );
        $this->assertEquals(
            grounding_validator::STATUS_NOQUOTE,
            grounding_validator::verify(null, self::SOURCE)
        );
        $this->assertEquals(
            grounding_validator::STATUS_NOQUOTE,
            grounding_validator::verify('ZTP is fast', self::SOURCE)
        );
    }

    /**
     * With no local text (the AI read the file itself) nothing can be checked.
     */
    public function test_no_source_text_is_unverifiable(): void {
        $this->assertEquals(
            grounding_validator::STATUS_UNVERIFIABLE,
            grounding_validator::verify('any quote at all goes here', '')
        );
    }

    /**
     * annotate() labels every question and produces an accurate summary.
     */
    public function test_annotate_labels_questions_and_summarises(): void {
        $quizdata = [
            'questions' => [
                [
                    'id' => 1,
                    'question' => 'How wide is the Decay Counter?',
                    'source_quote' => 'the Decay Counter and is 5 bits wide',
                ],
                [
                    'id' => 2,
                    'question' => 'How wide is the IPv4 TTL field?',
                    'source_quote' => 'The Time To Live field in an IPv4 header is eight bits wide',
                ],
                [
                    'id' => 3,
                    'question' => 'What is a handshake?',
                ],
            ],
            'metadata' => [],
        ];

        $result = grounding_validator::annotate($quizdata, self::SOURCE);

        $this->assertEquals(grounding_validator::STATUS_VERIFIED, $result['questions'][0]['grounding']);
        $this->assertEquals(grounding_validator::STATUS_UNGROUNDED, $result['questions'][1]['grounding']);
        $this->assertEquals(grounding_validator::STATUS_NOQUOTE, $result['questions'][2]['grounding']);

        $summary = $result['metadata']['grounding_summary'];
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(1, $summary['verified']);
        $this->assertEquals(1, $summary['ungrounded']);
        $this->assertEquals(1, $summary['noquote']);
        $this->assertTrue($summary['checked']);
    }

    /**
     * When part of the primary material was sent to the AI as a file we cannot
     * read, an unlocatable quote may legitimately have come from it. Report it as
     * unverifiable rather than accusing it of being invented.
     */
    public function test_unreadable_sources_downgrade_to_unverifiable(): void {
        $quizdata = [
            'questions' => [
                [
                    'id' => 1,
                    'question' => 'Something from the attached scan?',
                    'source_quote' => 'A sentence that only exists inside the scanned attachment file',
                ],
            ],
            'metadata' => [],
        ];

        $result = grounding_validator::annotate($quizdata, self::SOURCE, true);

        $this->assertEquals(
            grounding_validator::STATUS_UNVERIFIABLE,
            $result['questions'][0]['grounding']
        );
        $this->assertEquals(0, $result['metadata']['grounding_summary']['ungrounded']);
    }

    /**
     * Quotes that must be accepted or rejected, including adversarial ones.
     *
     * @return array[] [quote, expected status, description]
     */
    public static function quote_provider(): array {
        return [
            'verbatim' => [
                'It uses a 14-byte header and a fixed window size of 37 segments.',
                grounding_validator::STATUS_VERIFIED,
            ],
            'two sentences spliced' => [
                'ZTP was ratified by the Kestrel Working Group in 2019 and it uses a 14-byte header',
                grounding_validator::STATUS_VERIFIED,
            ],
            'mid-sentence fragment' => [
                'the initiator must abort and enter the Quiet Period of 12 seconds',
                grounding_validator::STATUS_VERIFIED,
            ],
            'plausible but from general knowledge' => [
                'The Time To Live field in an IPv4 header is eight bits wide and is '
                    . 'decremented by every router that forwards the packet.',
                grounding_validator::STATUS_UNGROUNDED,
            ],
            'paraphrase rather than quote' => [
                'The protocol was standardised by the Kestrel committee during 2019 and '
                    . 'employs a header spanning fourteen octets in total.',
                grounding_validator::STATUS_UNGROUNDED,
            ],
            'unrelated subject entirely' => [
                'Photosynthesis converts light energy into chemical energy stored in '
                    . 'glucose molecules within chloroplasts.',
                grounding_validator::STATUS_UNGROUNDED,
            ],
            'adversarial: source vocabulary, wrong meaning' => [
                'The Decay Counter field in the header uses a fixed size of 5 segments '
                    . 'and is ratified by the working group.',
                grounding_validator::STATUS_UNGROUNDED,
            ],
            'adversarial: real phrases, reshuffled' => [
                'A fixed window size of 37 segments is called the Decay Counter in every '
                    . 'Amber Handshake session.',
                grounding_validator::STATUS_UNGROUNDED,
            ],
        ];
    }

    /**
     * @dataProvider quote_provider
     * @param string $quote The quote to check
     * @param string $expected Expected status
     */
    public function test_quote_verdicts(string $quote, string $expected): void {
        $this->assertEquals($expected, grounding_validator::verify($quote, self::SOURCE));
    }

    /**
     * Changing a single figure must be caught - this is the most damaging kind
     * of drift, because everything around it looks right.
     */
    public function test_altered_number_is_caught(): void {
        $source = 'ZTP fragmentation occurs only when the payload exceeds 1180 bytes. '
            . 'Fragments are reassembled using the Mosaic Tag, a 9-bit identifier.';

        $this->assertEquals(
            grounding_validator::STATUS_VERIFIED,
            grounding_validator::verify(
                'ZTP fragmentation occurs only when the payload exceeds 1180 bytes', $source)
        );

        $this->assertEquals(
            grounding_validator::STATUS_UNGROUNDED,
            grounding_validator::verify(
                'ZTP fragmentation occurs only when the payload exceeds 1500 bytes and the '
                . 'Mosaic Tag is a 16-bit identifier', $source)
        );
    }

    /**
     * Suspect statuses are the ones the teacher must act on.
     */
    public function test_is_suspect(): void {
        $this->assertTrue(grounding_validator::is_suspect(grounding_validator::STATUS_UNGROUNDED));
        $this->assertTrue(grounding_validator::is_suspect(grounding_validator::STATUS_NOQUOTE));
        $this->assertFalse(grounding_validator::is_suspect(grounding_validator::STATUS_VERIFIED));
        $this->assertFalse(grounding_validator::is_suspect(grounding_validator::STATUS_UNVERIFIABLE));
    }
}
