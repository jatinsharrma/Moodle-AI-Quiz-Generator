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
 * Thrown when the AI's reply is understandable as a response but not in the
 * shape we need - malformed JSON, missing fields, wrong number of options.
 *
 * Carries the raw model output so the repair loop can hand it back to the model
 * along with the specific complaint. This is the one failure class that asking
 * again can genuinely fix; truncation, content filters and auth errors cannot be
 * repaired by feedback and are deliberately NOT represented by this class.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_format_exception extends \moodle_exception {

    /** @var string The raw text the model produced. */
    public $rawoutput;

    /** @var string What was wrong with it, in plain language for the model. */
    public $complaint;

    /**
     * Constructor.
     *
     * @param string $errorcode Language string identifier
     * @param string $complaint What was wrong, phrased so the model can act on it
     * @param string $rawoutput The raw model output that failed
     */
    public function __construct($errorcode, $complaint = '', $rawoutput = '') {
        $this->complaint = $complaint;
        $this->rawoutput = $rawoutput;
        parent::__construct($errorcode, 'local_ai_quiz', '', $complaint);
    }
}
