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
 * Test double returning scripted results instead of calling Gemini.
 *
 * Each entry in $script is either a decoded response payload to return, or a
 * Throwable to throw. Every call is recorded in $calls so tests can assert on
 * what the generator actually asked the API for.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scripted_quiz_generator extends quiz_generator {

    /** @var array Queue of results; a Throwable is thrown, anything else returned. */
    public $script = [];

    /** @var array One record per API call made. */
    public $calls = [];

    /**
     * Record the request and return the next scripted result.
     *
     * @param string $prompt The generation prompt
     * @param array $inlinefiles Natively attached files
     * @param array $priorturns Extra conversation turns
     * @return array Scripted response payload
     */
    protected function call_gemini_api($prompt, $inlinefiles = [], $priorturns = []) {
        preg_match('/Generate (\d+) multiple-choice questions/', $prompt, $matches);

        $this->calls[] = [
            'requested' => isset($matches[1]) ? (int)$matches[1] : null,
            'priorturns' => count($priorturns),
            'isfollowup' => count($priorturns) > 0,
            // The instruction we sent back (repair complaint or replacement request).
            'feedback' => $priorturns ? ($priorturns[1]['parts'][0]['text'] ?? '') : '',
            // The output we echoed back to the model.
            'echoed' => $priorturns ? ($priorturns[0]['parts'][0]['text'] ?? '') : '',
        ];

        $next = array_shift($this->script);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        return $next;
    }
}
