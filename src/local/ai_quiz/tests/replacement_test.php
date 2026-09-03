<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_ai_quiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/ai_quiz/tests/fixtures/scripted_quiz_generator.php');

/**
 * Tests for replacing questions that cannot be traced to the source document.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ai_quiz\quiz_generator::generate_mcqs
 */
final class replacement_test extends \advanced_testcase {

    /** @var string Stand-in for text extracted from a teacher's document. */
    private const SOURCE = 'ZTP was ratified by the Kestrel Working Group in 2019. It uses a '
        . '14-byte header and a fixed window size of 37 segments. The TTL-equivalent field in '
        . 'ZTP is called the Decay Counter and is 5 bits wide. A ZTP session begins with a '
        . 'three-phase Amber Handshake: PROBE, GRANT, and SEAL. If the SEAL phase is not '
        . 'acknowledged within 400 ms, the initiator must abort and enter the Quiet Period of '
        . '12 seconds. ZTP fragmentation occurs only when the payload exceeds 1180 bytes. '
        . 'Fragments are reassembled using the Mosaic Tag, a 9-bit identifier.';

    /** @var string A quote that really is in the source. */
    private const GROUNDED = 'It uses a 14-byte header and a fixed window size of 37 segments';

    /** @var string Another real quote. */
    private const GROUNDED_ALT = 'the initiator must abort and enter the Quiet Period of 12 seconds';

    /** @var string A third real quote. */
    private const GROUNDED_THIRD = 'Fragments are reassembled using the Mosaic Tag, a 9-bit identifier';

    /** @var string Plausible, on-topic, and nowhere in the source. */
    private const INVENTED = 'The Time To Live field in an IPv4 header is eight bits wide and is '
        . 'decremented by every router that forwards it';

    /**
     * Build a well-formed question.
     *
     * @param int $id Question id
     * @param string $text Question text
     * @param string $quote The source_quote to attach
     * @return array The question
     */
    private function question(int $id, string $text, string $quote): array {
        return [
            'id' => $id,
            'question' => $text,
            'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'correct_answer' => 'B',
            'difficulty' => 'medium',
            'source_quote' => $quote,
        ];
    }

    /**
     * Sources with locally extracted text, so grounding can be checked.
     *
     * @return array Sources
     */
    private function sources(): array {
        return [
            'context' => 'PRIMARY SOURCE MATERIALS: ' . self::SOURCE,
            'primarytext' => self::SOURCE,
            'files' => [],
        ];
    }

    /**
     * When everything is grounded, no extra API call is made.
     */
    public function test_fully_grounded_output_costs_no_extra_call(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [[
            'questions' => [$this->question(1, 'Header size?', self::GROUNDED)],
            'metadata' => [],
        ]];

        $gen->generate_mcqs($this->sources(), 20);

        $this->assertCount(1, $gen->calls);
    }

    /**
     * A question that cannot be traced to the document is swapped out for one
     * that can, rather than being handed to the teacher with a warning label.
     */
    public function test_ungrounded_question_is_replaced(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            ['questions' => [
                $this->question(1, 'Header size?', self::GROUNDED),
                $this->question(2, 'How wide is the IPv4 TTL field?', self::INVENTED),
            ], 'metadata' => []],
            ['questions' => [
                $this->question(9, 'What identifier reassembles fragments?', self::GROUNDED_THIRD),
            ], 'metadata' => []],
        ];

        $result = $gen->generate_mcqs($this->sources(), 20);
        $texts = array_column($result['questions'], 'question');

        $this->assertCount(2, $gen->calls);
        $this->assertTrue($gen->calls[1]['isfollowup']);
        $this->assertCount(2, $result['questions']);
        $this->assertNotContains('How wide is the IPv4 TTL field?', $texts);
        $this->assertContains('What identifier reassembles fragments?', $texts);
        $this->assertEquals([1, 2], array_column($result['questions'], 'id'));
    }

    /**
     * The replacement request must carry the questions being kept, or the model
     * reliably produces near-copies of them.
     */
    public function test_replacement_request_includes_kept_questions(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            ['questions' => [
                $this->question(1, 'Header size?', self::GROUNDED),
                $this->question(2, 'How wide is the IPv4 TTL field?', self::INVENTED),
            ], 'metadata' => []],
            ['questions' => [
                $this->question(9, 'What identifier reassembles fragments?', self::GROUNDED_THIRD),
            ], 'metadata' => []],
        ];

        $gen->generate_mcqs($this->sources(), 20);
        $instruction = $gen->calls[1]['feedback'];

        $this->assertStringContainsString('How wide is the IPv4 TTL field?', $instruction,
            'Must name the question being removed.');
        $this->assertStringContainsString('Header size?', $instruction,
            'Must list the kept question so it is not duplicated.');
        $this->assertStringContainsString('must NOT duplicate', $instruction);
        $this->assertStringContainsString('Time To Live', $instruction,
            'Must show the quote that could not be found.');

        // The whole set is echoed back to the model.
        $this->assertEquals(2, substr_count($gen->calls[1]['echoed'], '"question"'));
    }

    /**
     * A replacement that is itself ungrounded must be refused, not accepted just
     * because it arrived in response to the complaint.
     */
    public function test_ungrounded_replacement_is_refused(): void {
        $invented = ['questions' => [
            $this->question(9, 'Another invented question?', self::INVENTED),
        ], 'metadata' => []];

        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            ['questions' => [
                $this->question(1, 'Header size?', self::GROUNDED),
                $this->question(2, 'IPv4 TTL width?', self::INVENTED),
            ], 'metadata' => []],
            $invented,
            $invented,
        ];

        $result = $gen->generate_mcqs($this->sources(), 20);
        $texts = array_column($result['questions'], 'question');

        $this->assertNotContains('Another invented question?', $texts);
        $this->assertCount(2, $result['questions'], 'Unreplaceable question is kept and flagged.');
        $this->assertNotEmpty($gen->get_warnings());
    }

    /**
     * A replacement that merely rewords a kept question must be refused.
     */
    public function test_duplicate_replacement_is_refused(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            ['questions' => [
                $this->question(1, 'What is the fixed window size used by ZTP?', self::GROUNDED),
                $this->question(2, 'IPv4 TTL?', self::INVENTED),
            ], 'metadata' => []],
            // Same question, reworded.
            ['questions' => [
                $this->question(9, 'ZTP uses what fixed window size?', self::GROUNDED),
            ], 'metadata' => []],
            ['questions' => [
                $this->question(9, 'What must the initiator do after a failed SEAL?',
                    self::GROUNDED_ALT),
            ], 'metadata' => []],
        ];

        $result = $gen->generate_mcqs($this->sources(), 20);
        $texts = array_column($result['questions'], 'question');

        $this->assertNotContains('ZTP uses what fixed window size?', $texts);
        $this->assertContains('What must the initiator do after a failed SEAL?', $texts);
    }

    /**
     * With no locally extracted text there is nothing to verify against, so no
     * replacement should be attempted.
     */
    public function test_natively_read_files_skip_replacement(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [[
            'questions' => [$this->question(1, 'Anything?', self::INVENTED)],
            'metadata' => [],
        ]];

        $gen->generate_mcqs([
            'context' => 'PRIMARY SOURCE MATERIALS: attached file',
            'primarytext' => '',
            'files' => [['mime' => 'application/pdf', 'data' => 'x']],
        ], 20);

        $this->assertCount(1, $gen->calls);
    }

    /**
     * If the replacement request itself fails, keep what we have rather than
     * losing the whole generation.
     */
    public function test_replacement_failure_is_not_fatal(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            ['questions' => [
                $this->question(1, 'Header size?', self::GROUNDED),
                $this->question(2, 'IPv4 TTL?', self::INVENTED),
            ], 'metadata' => []],
            new \moodle_exception('error:quota_exceeded', 'local_ai_quiz'),
        ];

        $result = $gen->generate_mcqs($this->sources(), 20);

        $this->assertCount(2, $result['questions']);
    }
}
