<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use local_ai_quiz\question_bank_helper;

require_login();

$sessionkey = required_param('sessionkey', PARAM_ALPHANUM);
$action = optional_param('action', 'preview', PARAM_ALPHA);

// Set up the page
$PAGE->set_url('/local/ai_quiz/preview.php', ['sessionkey' => $sessionkey]);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('previewquestions', 'local_ai_quiz'));
$PAGE->set_heading(get_string('previewquestions', 'local_ai_quiz'));
// Load AMD module for preview functionality (delete, select all, inline editing)
$PAGE->requires->js_call_amd('local_ai_quiz/preview', 'init');

// Get stored question data
global $DB, $USER;

$record = $DB->get_record('local_ai_quiz_temp', [
    'sessionkey' => $sessionkey,
    'userid' => $USER->id
], '*', MUST_EXIST);

$quizdata = json_decode($record->questiondata, true);
$courseid = $record->courseid;
$categoryid = $record->categoryid;

// Handle import action
if ($action === 'import' && confirm_sesskey()) {
    $selectedids = optional_param_array('selected', [], PARAM_INT);

    if (empty($selectedids)) {
        redirect($PAGE->url, get_string('error:noselection', 'local_ai_quiz'), null, 'error');
    }

    // Filter selected questions
    $selectedquestions = array_filter($quizdata['questions'], function($q) use ($selectedids) {
        return in_array($q['id'], $selectedids);
    });

    $filteredquizdata = [
        'questions' => array_values($selectedquestions),
        'metadata' => $quizdata['metadata']
    ];

    // Import to question bank
    $coursecontext = context_course::instance($courseid);
    $results = question_bank_helper::import_questions(
        $filteredquizdata,
        $categoryid,
        $coursecontext->id
    );

    // Delete temp record
    $DB->delete_records('local_ai_quiz_temp', ['id' => $record->id]);

    // Redirect to question bank with detailed results
    $continueurl = new moodle_url('/question/edit.php', ['courseid' => $courseid]);

    $message = get_string('questionsimported', 'local_ai_quiz', $results['success']);
    $messagetype = 'success';

    if ($results['failed'] > 0) {
        $message .= ' | ' . $results['failed'] . ' failed';
        if (!empty($results['errors'])) {
            $message .= ': ' . implode(', ', array_slice($results['errors'], 0, 3));
        }
        $messagetype = 'warning';
    }

    redirect($continueurl, $message, null, $messagetype);
}

// Handle edit action
if ($action === 'edit' && confirm_sesskey()) {
    $questionid = required_param('qid', PARAM_INT);
    $field = required_param('field', PARAM_ALPHA);
    $value = required_param('value', PARAM_RAW);

    // Update question in memory
    foreach ($quizdata['questions'] as &$q) {
        if ($q['id'] == $questionid) {
            if ($field === 'question' || $field === 'explanation' || $field === 'difficulty' || $field === 'topic') {
                $q[$field] = $value;
            } else if (in_array($field, ['A', 'B', 'C', 'D'])) {
                $q['options'][$field] = $value;
            } else if ($field === 'correct_answer') {
                $q['correct_answer'] = $value;
            }
            break;
        }
    }

    // Save back to database
    $record->questiondata = json_encode($quizdata);
    $DB->update_record('local_ai_quiz_temp', $record);

    echo json_encode(['success' => true]);
    exit;
}

// Handle delete action
if ($action === 'delete' && confirm_sesskey()) {
    $questionid = required_param('qid', PARAM_INT);

    // Remove question
    $quizdata['questions'] = array_filter($quizdata['questions'], function($q) use ($questionid) {
        return $q['id'] != $questionid;
    });
    $quizdata['questions'] = array_values($quizdata['questions']); // Re-index

    // Save back to database
    $record->questiondata = json_encode($quizdata);
    $DB->update_record('local_ai_quiz_temp', $record);

    // Return JSON for AJAX requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => get_string('questiondeleted', 'local_ai_quiz'),
        'remaining' => count($quizdata['questions'])
    ]);
    exit;
}

// Display preview
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('previewquestions', 'local_ai_quiz'));

// Info box
echo html_writer::start_div('alert alert-info');
echo html_writer::tag('strong', get_string('reviewinstructions', 'local_ai_quiz'));
echo html_writer::empty_tag('br');
echo get_string('reviewinstructions_help', 'local_ai_quiz');
echo html_writer::end_div();

// Stats
echo html_writer::start_div('mb-3');
echo html_writer::tag('p',
    get_string('totalgenerated', 'local_ai_quiz', count($quizdata['questions'])),
    ['class' => 'lead']
);
echo html_writer::end_div();

// Form
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'id' => 'preview-form'
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'action',
    'value' => 'import'
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey()
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sessionkey',
    'value' => $sessionkey
]);

// Bulk actions
echo html_writer::start_div('mb-3');
echo html_writer::tag('button',
    get_string('selectall', 'local_ai_quiz'),
    ['type' => 'button', 'class' => 'btn btn-secondary', 'id' => 'select-all']
);
echo ' ';
echo html_writer::tag('button',
    get_string('deselectall', 'local_ai_quiz'),
    ['type' => 'button', 'class' => 'btn btn-secondary', 'id' => 'deselect-all']
);
echo html_writer::end_div();

// Questions
foreach ($quizdata['questions'] as $i => $q) {
    $qnum = $i + 1;

    echo html_writer::start_div('card mb-3 question-card', ['data-qid' => $q['id']]);
    echo html_writer::start_div('card-header bg-light');

    // Checkbox
    echo html_writer::start_tag('label', ['class' => 'd-flex align-items-start']);
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'name' => 'selected[]',
        'value' => $q['id'],
        'checked' => 'checked',
        'class' => 'mr-2 mt-1 question-checkbox'
    ]);

    echo html_writer::start_div('flex-grow-1');
    echo html_writer::tag('strong', get_string('questionnum', 'local_ai_quiz', $qnum));
    echo ' ';

    // Show answer type badge
    $qanswertype = $q['answer_type'] ?? 'single';
    if ($qanswertype === 'multiple') {
        echo html_writer::tag('span', '[MA]', ['class' => 'badge badge-primary', 'title' => 'Multiple Answer']);
        echo ' ';
    }

    echo html_writer::tag('span',
        $q['difficulty'],
        ['class' => 'badge badge-' . ($q['difficulty'] === 'easy' ? 'success' : ($q['difficulty'] === 'medium' ? 'warning' : 'danger'))]
    );
    if (isset($q['topic'])) {
        echo ' ';
        echo html_writer::tag('span', $q['topic'], ['class' => 'badge badge-info']);
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('label');

    echo html_writer::end_div();

    echo html_writer::start_div('card-body');

    // Question text (editable)
    echo html_writer::start_div('mb-3');
    echo html_writer::tag('div',
        $q['question'],
        [
            'class' => 'question-text editable p-2 border rounded',
            'contenteditable' => 'true',
            'data-field' => 'question'
        ]
    );
    echo html_writer::end_div();

    // Determine if single or multiple answer
    $answertype = $q['answer_type'] ?? 'single';
    $ismultiple = ($answertype === 'multiple');
    $correctanswer = $q['correct_answer'];
    $correctanswers = is_array($correctanswer) ? $correctanswer : [$correctanswer];

    // Calculate fraction for display
    $numcorrect = count($correctanswers);
    $fractionpercorrect = round((1.0 / $numcorrect) * 100, 1); // As percentage

    // Show answer type info
    if ($ismultiple) {
        echo html_writer::start_div('alert alert-warning mb-3');
        echo html_writer::tag('strong', '[Multiple Answer] ');
        echo "Select all that apply. Each correct answer worth {$fractionpercorrect}% (partial credit available).";
        echo html_writer::end_div();
    }

    // Options
    echo html_writer::start_tag('ol', ['type' => 'A', 'class' => 'options-list']);
    foreach (['A', 'B', 'C', 'D'] as $opt) {
        // Check if this option is correct (handle both single and multiple)
        $isCorrect = in_array($opt, $correctanswers);
        $class = $isCorrect ? 'list-group-item-success' : '';

        echo html_writer::start_tag('li', ['class' => 'mb-2']);
        echo html_writer::start_div('d-flex align-items-center');

        // Use checkbox for multiple answer, radio for single answer
        if ($ismultiple) {
            // Checkbox for multiple answer
            echo html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'correct_' . $q['id'] . '[]',
                'value' => $opt,
                'checked' => $isCorrect ? 'checked' : null,
                'class' => 'mr-2 correct-answer-checkbox',
                'data-qid' => $q['id'],
                'disabled' => 'disabled' // Preview only
            ]);
        } else {
            // Radio for single answer
            echo html_writer::empty_tag('input', [
                'type' => 'radio',
                'name' => 'correct_' . $q['id'],
                'value' => $opt,
                'checked' => $isCorrect ? 'checked' : null,
                'class' => 'mr-2 correct-answer-radio',
                'data-qid' => $q['id'],
                'disabled' => 'disabled' // Preview only
            ]);
        }

        // Option text (editable)
        $optiontext = $q['options'][$opt];
        if ($ismultiple && $isCorrect) {
            $optiontext .= " <span class='badge badge-success ml-2'>{$fractionpercorrect}%</span>";
        }

        echo html_writer::tag('div',
            $optiontext,
            [
                'class' => 'flex-grow-1 editable p-2 border rounded ' . $class,
                'contenteditable' => 'true',
                'data-field' => $opt
            ]
        );

        echo html_writer::end_div();
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ol');

    // Explanation
    if (!empty($q['explanation'])) {
        echo html_writer::start_div('mt-3 p-2 bg-light rounded');
        echo html_writer::tag('strong', get_string('explanation', 'local_ai_quiz') . ': ');
        echo html_writer::tag('span', $q['explanation'], ['class' => 'explanation-text']);
        echo html_writer::end_div();
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Submit button
echo html_writer::start_div('mt-4 mb-4');
echo html_writer::tag('button',
    get_string('importselected', 'local_ai_quiz'),
    ['type' => 'submit', 'class' => 'btn btn-lg btn-primary']
);
echo ' ';
echo html_writer::tag('a',
    get_string('cancel'),
    [
        'href' => new moodle_url('/course/view.php', ['id' => $courseid]),
        'class' => 'btn btn-lg btn-secondary'
    ]
);
echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
