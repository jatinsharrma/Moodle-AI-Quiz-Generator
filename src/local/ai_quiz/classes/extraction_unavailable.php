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
 * Thrown when usable text cannot be extracted from a document.
 *
 * This is a *recoverable* signal, not a hard failure: for PDFs the caller may
 * respond by sending the original file to the AI provider for native reading.
 * It is deliberately distinct from a generic moodle_exception so that callers
 * can tell "no text available from this file" apart from "this file is broken".
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extraction_unavailable extends \moodle_exception {

    /** @var string Machine-readable reason: 'notoolchain' or 'notext'. */
    public $reason;

    /**
     * Constructor.
     *
     * @param string $reason One of 'notoolchain' (pdftotext/exec unavailable)
     *                       or 'notext' (extraction ran but produced no usable text).
     * @param string $detail Human-readable detail for debugging.
     */
    public function __construct($reason, $detail = '') {
        $this->reason = $reason;
        parent::__construct('error:extraction_unavailable', 'local_ai_quiz', '', $detail);
    }
}
