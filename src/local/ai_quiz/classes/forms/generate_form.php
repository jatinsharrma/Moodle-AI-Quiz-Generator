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

        // Add "Create new category" button
        $createcategoryhtml = <<<'HTML'
<button type="button" id="btn-create-category" class="btn btn-secondary btn-sm">
    <i class="fa fa-plus"></i> Create New Category
</button>
<div id="create-category-form" style="display:none; margin-top:10px; padding:15px; border:1px solid #ddd; border-radius:5px; background:#f9f9f9;">
    <h5>Create New Category</h5>
    <div class="form-group">
        <label for="new_category_name">Category Name *</label>
        <input type="text" id="new_category_name" class="form-control" placeholder="e.g. Biology Quiz 1" />
    </div>
    <div class="form-group">
        <label for="new_category_info">Description (optional)</label>
        <textarea id="new_category_info" class="form-control" rows="2" placeholder="Optional description"></textarea>
    </div>
    <button type="button" id="btn-save-category" class="btn btn-primary btn-sm">
        <i class="fa fa-save"></i> Save Category
    </button>
    <button type="button" id="btn-cancel-category" class="btn btn-secondary btn-sm">
        Cancel
    </button>
    <div id="category-message" style="margin-top:10px;"></div>
</div>
HTML;
        $mform->addElement('static', 'create_category_btn', '', $createcategoryhtml);

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

        // Difficulty distribution (percentages)
        $mform->addElement('static', 'difficulty_label', '',
            get_string('difficulty_distribution_help', 'local_ai_quiz'));

        $difficultygroup = [];
        $difficultygroup[] = $mform->createElement('text', 'easy_pct', '', ['size' => 5]);
        $difficultygroup[] = $mform->createElement('static', 'easy_label', '', '% ' . get_string('easy', 'local_ai_quiz'));
        $difficultygroup[] = $mform->createElement('text', 'medium_pct', '', ['size' => 5]);
        $difficultygroup[] = $mform->createElement('static', 'medium_label', '', '% ' . get_string('medium', 'local_ai_quiz'));
        $difficultygroup[] = $mform->createElement('text', 'hard_pct', '', ['size' => 5]);
        $difficultygroup[] = $mform->createElement('static', 'hard_label', '', '% ' . get_string('hard', 'local_ai_quiz'));

        $mform->addGroup($difficultygroup, 'difficulty_group',
            get_string('difficulty_distribution', 'local_ai_quiz'), ' ', false);

        $mform->setType('easy_pct', PARAM_INT);
        $mform->setType('medium_pct', PARAM_INT);
        $mform->setType('hard_pct', PARAM_INT);

        $mform->setDefault('easy_pct', 25);
        $mform->setDefault('medium_pct', 50);
        $mform->setDefault('hard_pct', 25);

        // Multiple Answer Questions Configuration
        $mform->addElement('header', 'multipleanswer_header', 'Multiple Answer Questions');

        $mform->addElement('advcheckbox', 'include_multiple_answer',
            'Include Multiple Answer Questions',
            'Generate questions with multiple correct answers (with negative marking)');
        $mform->setDefault('include_multiple_answer', 0);

        // Number of multiple answer questions
        $mform->addElement('text', 'multiple_answer_count',
            'Number of Multiple Answer Questions', ['size' => 10]);
        $mform->setType('multiple_answer_count', PARAM_INT);
        $mform->setDefault('multiple_answer_count', 5);
        $mform->addHelpButton('multiple_answer_count', 'multiple_answer_count', 'local_ai_quiz');
        $mform->hideIf('multiple_answer_count', 'include_multiple_answer');

        // Difficulty distribution for multiple answer questions
        $mform->addElement('static', 'ma_difficulty_label', '',
            '<strong>Difficulty Distribution for Multiple Answer Questions</strong><br>Specify percentages (must total 100%)');
        $mform->hideIf('ma_difficulty_label', 'include_multiple_answer');

        $madifficultygroup = [];
        $madifficultygroup[] = $mform->createElement('text', 'ma_easy_pct', '', ['size' => 5]);
        $madifficultygroup[] = $mform->createElement('static', 'ma_easy_label', '', '% Easy');
        $madifficultygroup[] = $mform->createElement('text', 'ma_medium_pct', '', ['size' => 5]);
        $madifficultygroup[] = $mform->createElement('static', 'ma_medium_label', '', '% Medium');
        $madifficultygroup[] = $mform->createElement('text', 'ma_hard_pct', '', ['size' => 5]);
        $madifficultygroup[] = $mform->createElement('static', 'ma_hard_label', '', '% Hard');

        $mform->addGroup($madifficultygroup, 'ma_difficulty_group',
            'Multiple Answer Difficulty', ' ', false);
        $mform->hideIf('ma_difficulty_group', 'include_multiple_answer');

        $mform->setType('ma_easy_pct', PARAM_INT);
        $mform->setType('ma_medium_pct', PARAM_INT);
        $mform->setType('ma_hard_pct', PARAM_INT);

        $mform->setDefault('ma_easy_pct', 25);
        $mform->setDefault('ma_medium_pct', 50);
        $mform->setDefault('ma_hard_pct', 25);

        // Action buttons
        $this->add_action_buttons(true, get_string('generate', 'local_ai_quiz'));
    }

    /**
     * After data is set, add JavaScript to handle course selection
     */
    public function definition_after_data() {
        global $PAGE;

        // Add JavaScript to load categories via AJAX when course is selected
        $ajaxurl = new \moodle_url('/local/ai_quiz/ajax_get_categories.php');
        $ajaxcreatecategoryurl = new \moodle_url('/local/ai_quiz/ajax_create_category.php');
        $PAGE->requires->js_init_code("
            require(['jquery'], function($) {
                function loadCategories(courseid, selectedCategoryId) {
                    var categorySelect = $('#id_categoryid');

                    if (courseid) {
                        // Show loading state
                        categorySelect.prop('disabled', true);
                        categorySelect.html('<option value=\"\">Loading...</option>');

                        // Load categories via AJAX
                        $.ajax({
                            url: '" . $ajaxurl . "',
                            type: 'GET',
                            data: { courseid: courseid, sesskey: M.cfg.sesskey },
                            dataType: 'json',
                            success: function(response) {
                                // Clear and populate category dropdown
                                categorySelect.html('');

                                if (response.categories && response.categories.length > 0) {
                                    categorySelect.append('<option value=\"\">Choose...</option>');
                                    $.each(response.categories, function(index, category) {
                                        var option = $('<option></option>')
                                            .val(category.id)
                                            .text(category.name);

                                        // Re-select previously selected category if provided
                                        if (selectedCategoryId && category.id == selectedCategoryId) {
                                            option.prop('selected', true);
                                        }

                                        categorySelect.append(option);
                                    });
                                } else {
                                    categorySelect.append('<option value=\"\">No categories available</option>');
                                }

                                categorySelect.prop('disabled', false);
                            },
                            error: function() {
                                categorySelect.html('<option value=\"\">Error loading categories</option>');
                                categorySelect.prop('disabled', false);
                            }
                        });
                    } else {
                        categorySelect.html('<option value=\"\">Select a course first</option>');
                        categorySelect.prop('disabled', true);
                    }
                }

                // Handle course selection change
                $('#id_courseid').change(function() {
                    var courseid = $(this).val();
                    loadCategories(courseid, null);
                });

                // On page load, if course is selected but no categories loaded, load them
                $(document).ready(function() {
                    var courseid = $('#id_courseid').val();
                    var categorySelect = $('#id_categoryid');

                    // Check if course is selected but categories are empty (only default option)
                    if (courseid && categorySelect.find('option').length <= 1) {
                        loadCategories(courseid, null);
                    }
                });

                // Handle Create New Category button
                $('#btn-create-category').click(function(e) {
                    e.preventDefault(); // Prevent form validation
                    e.stopPropagation(); // Stop event bubbling

                    var courseid = $('#id_courseid').val();
                    if (!courseid) {
                        alert('Please select a course first');
                        return false;
                    }
                    $('#create-category-form').slideDown();
                    $('#new_category_name').focus();
                    return false; // Prevent any default action
                });

                // Handle Cancel button
                $('#btn-cancel-category').click(function(e) {
                    e.preventDefault();
                    $('#create-category-form').slideUp();
                    $('#new_category_name').val('');
                    $('#new_category_info').val('');
                    $('#category-message').html('');
                    return false;
                });

                // Handle Save Category button
                $('#btn-save-category').click(function(e) {
                    e.preventDefault();
                    var courseid = $('#id_courseid').val();
                    var categoryName = $('#new_category_name').val().trim();
                    var categoryInfo = $('#new_category_info').val().trim();
                    var messageDiv = $('#category-message');

                    // Validate
                    if (!categoryName) {
                        messageDiv.html('<div class=\"alert alert-danger\">Category name is required</div>');
                        return false;
                    }

                    // Show loading
                    messageDiv.html('<div class=\"alert alert-info\">Creating category...</div>');
                    $('#btn-save-category').prop('disabled', true);

                    // Create category via AJAX
                    $.ajax({
                        url: '" . $ajaxcreatecategoryurl . "',
                        type: 'POST',
                        data: {
                            courseid: courseid,
                            name: categoryName,
                            info: categoryInfo,
                            sesskey: M.cfg.sesskey
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                messageDiv.html('<div class=\"alert alert-success\">Category created successfully!</div>');

                                // Refresh category dropdown with new list
                                loadCategories(courseid, response.categoryid);

                                // Clear form and hide after 1 second
                                setTimeout(function() {
                                    $('#create-category-form').slideUp();
                                    $('#new_category_name').val('');
                                    $('#new_category_info').val('');
                                    messageDiv.html('');
                                    $('#btn-save-category').prop('disabled', false);
                                }, 1000);
                            } else {
                                messageDiv.html('<div class=\"alert alert-danger\">Error: ' + response.error + '</div>');
                                $('#btn-save-category').prop('disabled', false);
                            }
                        },
                        error: function() {
                            messageDiv.html('<div class=\"alert alert-danger\">Failed to create category. Please try again.</div>');
                            $('#btn-save-category').prop('disabled', false);
                        }
                    });
                    return false; // Prevent form submission
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
        global $USER;
        $errors = parent::validation($data, $files);

        // Primary documents are required - check if files actually exist
        $draftitemid = $data['primarydocuments'] ?? 0;
        if ($draftitemid) {
            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);
            if (empty($draftfiles)) {
                $errors['primarydocuments'] = get_string('error:noprimarydocs', 'local_ai_quiz');
            }
        } else {
            $errors['primarydocuments'] = get_string('error:noprimarydocs', 'local_ai_quiz');
        }

        // Validate difficulty percentages add up to 100
        $easypct = $data['easy_pct'] ?? 0;
        $mediumpct = $data['medium_pct'] ?? 0;
        $hardpct = $data['hard_pct'] ?? 0;
        $totalpct = $easypct + $mediumpct + $hardpct;

        if ($totalpct != 100) {
            $errors['difficulty_group'] = get_string('error:percentagemismatch', 'local_ai_quiz', $totalpct);
        }

        // Validate each percentage is between 0 and 100
        if ($easypct < 0 || $easypct > 100) {
            $errors['difficulty_group'] = get_string('error:invalidpercentage', 'local_ai_quiz');
        }
        if ($mediumpct < 0 || $mediumpct > 100) {
            $errors['difficulty_group'] = get_string('error:invalidpercentage', 'local_ai_quiz');
        }
        if ($hardpct < 0 || $hardpct > 100) {
            $errors['difficulty_group'] = get_string('error:invalidpercentage', 'local_ai_quiz');
        }

        // Validate multiple answer questions if enabled
        if (!empty($data['include_multiple_answer'])) {
            $macount = $data['multiple_answer_count'] ?? 0;
            $totalquestions = $data['numquestions'] ?? 0;

            // Validate MA count doesn't exceed total questions
            if ($macount <= 0) {
                $errors['multiple_answer_count'] = 'Multiple answer count must be at least 1';
            } else if ($macount > $totalquestions) {
                $errors['multiple_answer_count'] = "Cannot exceed total questions ({$totalquestions})";
            }

            // Validate MA difficulty percentages
            $maeasypct = $data['ma_easy_pct'] ?? 0;
            $mamediumpct = $data['ma_medium_pct'] ?? 0;
            $mahardpct = $data['ma_hard_pct'] ?? 0;
            $matotalpct = $maeasypct + $mamediumpct + $mahardpct;

            if ($matotalpct != 100) {
                $errors['ma_difficulty_group'] = "Multiple answer difficulty must total 100% (currently {$matotalpct}%)";
            }

            // Validate each MA percentage is between 0 and 100
            if ($maeasypct < 0 || $maeasypct > 100) {
                $errors['ma_difficulty_group'] = 'Each percentage must be between 0 and 100';
            }
            if ($mamediumpct < 0 || $mamediumpct > 100) {
                $errors['ma_difficulty_group'] = 'Each percentage must be between 0 and 100';
            }
            if ($mahardpct < 0 || $mahardpct > 100) {
                $errors['ma_difficulty_group'] = 'Each percentage must be between 0 and 100';
            }
        }

        return $errors;
    }
}
