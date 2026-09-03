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
 * Tests for the bounded generation repair loop.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ai_quiz\quiz_generator::generate_mcqs
 */
final class repair_loop_test extends \advanced_testcase {

    /**
     * A well-formed question payload.
     *
     * @param int $count Number of questions
     * @return array Payload
     */
    private function good_payload(int $count = 2): array {
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $questions[] = [
                'id' => $i,
                'question' => "Question {$i}?",
                'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
                'correct_answer' => 'B',
                'difficulty' => 'medium',
                'source_quote' => 'a verbatim quote taken from the source',
            ];
        }
        return ['questions' => $questions, 'metadata' => []];
    }

    /**
     * Source descriptor for generate_mcqs().
     *
     * @return array Sources
     */
    private function sources(): array {
        // The quote used by good_payload() must genuinely appear here, otherwise
        // grounding fails and the replacement pass fires, changing the call counts
        // these tests assert on.
        $text = 'Some genuine document text. a verbatim quote taken from the source '
            . 'appears right here, followed by further material so that the extract '
            . 'is long enough to be treated as usable content.';

        return [
            'context' => 'PRIMARY SOURCE MATERIALS: ' . $text,
            'primarytext' => $text,
            'files' => [],
        ];
    }

    /**
     * A usable reply on the first try must cost exactly one API call.
     */
    public function test_success_makes_a_single_call(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [$this->good_payload()];

        $gen->generate_mcqs($this->sources(), 20);

        $this->assertCount(1, $gen->calls);
        $this->assertEmpty($gen->get_warnings());
    }

    /**
     * Malformed JSON is handed back to the model with the specific complaint.
     */
    public function test_malformed_json_is_repaired_with_feedback(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            new response_format_exception('error:invalid_api_response',
                'The response was not valid JSON.', '{"questions":[bro'),
            $this->good_payload(),
        ];

        $result = $gen->generate_mcqs($this->sources(), 20);

        $this->assertCount(2, $gen->calls);
        $this->assertEquals(2, $gen->calls[1]['priorturns']);
        $this->assertStringContainsString('not valid JSON', $gen->calls[1]['feedback']);
        $this->assertArrayHasKey('questions', $result);
    }

    /**
     * The repair instruction must not become a licence to fabricate content.
     */
    public function test_repair_prompt_forbids_inventing_content(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            new response_format_exception('error:invalid_api_response', 'bad', '{'),
            $this->good_payload(),
        ];

        $gen->generate_mcqs($this->sources(), 20);

        $this->assertStringContainsString('Do NOT invent', $gen->calls[1]['feedback']);
        $this->assertStringContainsString('source_quote', $gen->calls[1]['feedback']);
    }

    /**
     * A question with the wrong number of options is a repairable defect.
     */
    public function test_structural_defect_is_repaired(): void {
        $bad = $this->good_payload();
        unset($bad['questions'][0]['options']['D']);

        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [$bad, $this->good_payload()];

        $gen->generate_mcqs($this->sources(), 20);

        $this->assertCount(2, $gen->calls);
        $this->assertStringContainsString('exactly 4 options', $gen->calls[1]['feedback']);
    }

    /**
     * Truncation cannot be fixed by feedback. The retry must ask for fewer
     * questions and must NOT resend the failed output.
     */
    public function test_truncation_reduces_the_request_instead_of_arguing(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [
            new \moodle_exception('error:response_truncated', 'local_ai_quiz'),
            $this->good_payload(),
        ];

        $gen->generate_mcqs($this->sources(), 20);

        $this->assertEquals(20, $gen->calls[0]['requested']);
        $this->assertEquals(10, $gen->calls[1]['requested']);
        $this->assertEquals(0, $gen->calls[1]['priorturns']);
    }

    /**
     * Deterministic failures must fail immediately: retrying wastes the user's
     * time and their API quota to reach the identical outcome.
     *
     * @dataProvider deterministic_error_provider
     * @param string $errorcode The error that must not be retried
     */
    public function test_deterministic_failures_are_not_retried(string $errorcode): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [new \moodle_exception($errorcode, 'local_ai_quiz'), $this->good_payload()];

        try {
            $gen->generate_mcqs($this->sources(), 20);
            $this->fail('Expected the failure to propagate.');
        } catch (\moodle_exception $e) {
            $this->assertCount(1, $gen->calls, 'Must not retry a deterministic failure.');
            $this->assertEquals($errorcode, $e->errorcode, 'Must preserve the original error.');
        }
    }

    /**
     * Errors that can never succeed on retry.
     *
     * @return array[]
     */
    public static function deterministic_error_provider(): array {
        return [
            'content filter on output' => ['error:response_blocked'],
            'content filter on prompt' => ['error:prompt_blocked'],
            'bad credentials' => ['error:api_auth_failed'],
            'quota exhausted' => ['error:quota_exceeded'],
            'unreadable source' => ['error:binary_content'],
        ];
    }

    /**
     * Transient failures retry, but never more than MAX_ATTEMPTS in total.
     */
    public function test_transient_failures_are_capped(): void {
        $gen = new scripted_quiz_generator('testkey');
        $gen->script = array_fill(0, 5, new \moodle_exception('error:no_candidates', 'local_ai_quiz'));

        try {
            $gen->generate_mcqs($this->sources(), 20);
            $this->fail('Expected the failure to propagate.');
        } catch (\moodle_exception $e) {
            $this->assertCount(quiz_generator::MAX_ATTEMPTS, $gen->calls);
            $this->assertEquals('error:no_candidates', $e->errorcode);
        }
    }

    /**
     * On the last attempt, keep the questions that are usable rather than
     * discarding a whole set over one malformed entry.
     */
    public function test_final_attempt_salvages_valid_questions(): void {
        $mixed = $this->good_payload(3);
        unset($mixed['questions'][1]['options']['D']);

        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [$mixed, $mixed, $mixed];

        $result = $gen->generate_mcqs($this->sources(), 20);

        $this->assertCount(quiz_generator::MAX_ATTEMPTS, $gen->calls);
        $this->assertCount(2, $result['questions']);
        $this->assertNotEmpty($gen->get_warnings());
    }

    /**
     * If nothing is salvageable the generation fails rather than importing junk.
     */
    public function test_wholly_malformed_output_fails(): void {
        $allbad = $this->good_payload(2);
        unset($allbad['questions'][0]['options']['D'], $allbad['questions'][1]['options']['D']);

        $gen = new scripted_quiz_generator('testkey');
        $gen->script = [$allbad, $allbad, $allbad];

        $this->expectException(\moodle_exception::class);
        $gen->generate_mcqs($this->sources(), 20);
    }
}
