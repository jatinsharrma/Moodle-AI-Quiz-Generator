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
 * Tests for interpreting Gemini API responses.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ai_quiz\quiz_generator::parse_api_response
 */
final class quiz_generator_test extends \advanced_testcase {

    /**
     * Assert that parsing a response fails with a particular error code.
     *
     * @param array $response Decoded Gemini response
     * @param string $expectedcode Expected moodle_exception errorcode
     */
    private function assert_fails_with(array $response, string $expectedcode): void {
        try {
            quiz_generator::parse_api_response($response);
            $this->fail("Expected {$expectedcode} but parsing succeeded.");
        } catch (\moodle_exception $e) {
            $this->assertEquals($expectedcode, $e->errorcode);
        }
    }

    /**
     * Gemini 2.5 Flash reasons before answering. When that consumes the output
     * budget the candidate comes back with no parts at all - which the old code
     * reported only as "Invalid API response format", giving nothing to act on.
     */
    public function test_thinking_exhausting_budget_is_reported_as_truncation(): void {
        $this->assert_fails_with([
            'candidates' => [[
                'content' => ['role' => 'model'],
                'finishReason' => 'MAX_TOKENS',
                'index' => 0,
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 18450,
                'totalTokenCount' => 26834,
                'thoughtsTokenCount' => 8384,
            ],
        ], 'error:response_truncated');
    }

    /**
     * JSON cut off mid-structure is truncation, not a malformed response.
     */
    public function test_truncated_json_is_reported_as_truncation(): void {
        $this->assert_fails_with([
            'candidates' => [[
                'content' => ['parts' => [['text' => '{"questions":[{"id":1,"question":"What is ZT']]],
                'finishReason' => 'MAX_TOKENS',
            ]],
        ], 'error:response_truncated');
    }

    /**
     * A prompt rejected before generation names the block reason.
     */
    public function test_blocked_prompt_is_distinguished(): void {
        $this->assert_fails_with(
            ['promptFeedback' => ['blockReason' => 'SAFETY']],
            'error:prompt_blocked'
        );
    }

    /**
     * Generation halted by a content filter is distinguished from truncation.
     */
    public function test_filtered_generation_is_distinguished(): void {
        foreach (['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST'] as $reason) {
            $this->assert_fails_with([
                'candidates' => [['content' => ['role' => 'model'], 'finishReason' => $reason]],
            ], 'error:response_blocked');
        }
    }

    /**
     * No candidates at all.
     */
    public function test_missing_candidates_is_distinguished(): void {
        $this->assert_fails_with(['candidates' => []], 'error:no_candidates');
        $this->assert_fails_with([], 'error:no_candidates');
    }

    /**
     * Output that finished normally but isn't the expected JSON is a genuine
     * format problem, and the detail must include what actually came back.
     */
    public function test_non_json_output_reports_the_content(): void {
        try {
            quiz_generator::parse_api_response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Sorry, I cannot do that.']]],
                    'finishReason' => 'STOP',
                ]],
            ]);
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:invalid_api_response', $e->errorcode);
            $this->assertStringContainsString('Sorry, I cannot do that.', $e->debuginfo);
        }
    }

    /**
     * A well-formed response parses through unchanged.
     */
    public function test_successful_response_is_returned(): void {
        $payload = quiz_generator::parse_api_response([
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => '{"questions":[{"id":1,"question":"Q?","source_quote":"a quote here"}],'
                        . '"metadata":{"total_questions":1}}',
                ]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 18450, 'candidatesTokenCount' => 4210],
        ]);

        $this->assertCount(1, $payload['questions']);
        $this->assertEquals('Q?', $payload['questions'][0]['question']);
    }
}
