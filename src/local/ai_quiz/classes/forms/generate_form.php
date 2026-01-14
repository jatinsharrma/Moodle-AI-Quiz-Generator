<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_ai_quiz\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for generating AI quiz questions
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_form extends \moodleform {

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        // Header
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Course selection
        $mform->addElement('select', 'courseid', get_string('course'), $this->_customdata['courses']);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');

        // Question category
        $mform->addElement('select', 'categoryid', get_string('category', 'question'),
            $this->_customdata['categories']);
        $mform->addRule('categoryid', get_string('required'), 'required', null, 'client');

        // Primary Documents (Required)
        $mform->addElement('header', 'primaryfiles', get_string('primarydocuments', 'local_ai_quiz'));

        $mform->addElement('static', 'primaryinfo', '', get_string('primarydocuments_info', 'local_ai_quiz'));

        $primaryfilesoptions = [
            'subdirs' => 0,
            'maxbytes' => 10485760, // 10MB
            'maxfiles' => 10,
            'accepted_types' => ['.pdf', '.docx', '.pptx']
        ];

        $mform->addElement('filemanager', 'primarydocuments', get_string('primarydocuments_upload', 'local_ai_quiz'),
            null, $primaryfilesoptions);
        $mform->addHelpButton('primarydocuments', 'primarydocuments', 'local_ai_quiz');
        $mform->addRule('primarydocuments', get_string('required'), 'required', null, 'client');

        // Page ranges for primary documents
        $mform->addElement('textarea', 'primarypageranges', get_string('pageranges', 'local_ai_quiz'),
            'wrap="virtual" rows="5" cols="50"');
        $mform->setType('primarypageranges', PARAM_TEXT);
        $mform->addHelpButton('primarypageranges', 'pageranges', 'local_ai_quiz');

        // Supporting Documents (Optional)
        $mform->addElement('header', 'supportingfiles', get_string('supportingdocuments', 'local_ai_quiz'));

        $mform->addElement('static', 'supportinginfo', '', get_string('supportingdocuments_info', 'local_ai_quiz'));

        $supportingfilesoptions = [
            'subdirs' => 0,
            'maxbytes' => 10485760, // 10MB
            'maxfiles' => 10,
            'accepted_types' => ['.pdf', '.docx', '.pptx']
        ];

        $mform->addElement('filemanager', 'supportingdocuments', get_string('supportingdocuments_upload', 'local_ai_quiz'),
            null, $supportingfilesoptions);
        $mform->addHelpButton('supportingdocuments', 'supportingdocuments', 'local_ai_quiz');

        // Page ranges for supporting documents
        $mform->addElement('textarea', 'supportingpageranges', get_string('pageranges', 'local_ai_quiz'),
            'wrap="virtual" rows="5" cols="50"');
        $mform->setType('supportingpageranges', PARAM_TEXT);
        $mform->addHelpButton('supportingpageranges', 'pageranges', 'local_ai_quiz');

        // Website URLs
        $mform->addElement('header', 'websites_header', get_string('websites', 'local_ai_quiz'));

        $mform->addElement('textarea', 'websites', get_string('websites_supporting', 'local_ai_quiz'),
            'wrap="virtual" rows="5" cols="50"');
        $mform->setType('websites', PARAM_TEXT);
        $mform->addHelpButton('websites', 'websites', 'local_ai_quiz');

        // Quiz settings
        $mform->addElement('header', 'settings', get_string('quizsettings', 'local_ai_quiz'));

        $mform->addElement('text', 'numquestions', get_string('numquestions', 'local_ai_quiz'),
            ['size' => 10]);
        $mform->setType('numquestions', PARAM_INT);
        $mform->setDefault('numquestions', 20);
        $mform->addRule('numquestions', get_string('required'), 'required', null, 'client');
        $mform->addRule('numquestions', get_string('numeric', 'local_ai_quiz'), 'numeric', null, 'client');

        // Difficulty distribution
        $mform->addElement('text', 'easy', get_string('easy', 'local_ai_quiz'), ['size' => 5]);
        $mform->setType('easy', PARAM_INT);
        $mform->setDefault('easy', 5);

        $mform->addElement('text', 'medium', get_string('medium', 'local_ai_quiz'), ['size' => 5]);
        $mform->setType('medium', PARAM_INT);
        $mform->setDefault('medium', 10);

        $mform->addElement('text', 'hard', get_string('hard', 'local_ai_quiz'), ['size' => 5]);
        $mform->setType('hard', PARAM_INT);
        $mform->setDefault('hard', 5);

        // Action buttons
        $this->add_action_buttons(true, get_string('generate', 'local_ai_quiz'));
    }

    /**
     * After data is set, add JavaScript to handle course selection
     */
    public function definition_after_data() {
        global $PAGE;

        // Add JavaScript to reload page when course is selected
        $PAGE->requires->js_init_code("
            require(['jquery'], function($) {
                $('#id_courseid').change(function() {
                    var courseid = $(this).val();
                    if (courseid) {
                        window.location.href = '" . new \moodle_url('/local/ai_quiz/generate.php') . "' + '?courseid=' + courseid;
                    }
                });
            });
        ");
    }

    /**
     * Validation
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Primary documents are required
        if (empty($data['primarydocuments'])) {
            $errors['primarydocuments'] = get_string('error:noprimarydocs', 'local_ai_quiz');
        }

        // Validate difficulty distribution matches total questions
        $total = ($data['easy'] ?? 0) + ($data['medium'] ?? 0) + ($data['hard'] ?? 0);
        if ($total != $data['numquestions']) {
            $errors['numquestions'] = get_string('error:difficultymismatch', 'local_ai_quiz');
        }

        return $errors;
    }
}
