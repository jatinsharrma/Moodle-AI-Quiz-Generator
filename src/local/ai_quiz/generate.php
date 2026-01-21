<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use local_ai_quiz\quiz_generator;
use local_ai_quiz\question_bank_helper;
use local_ai_quiz\forms\generate_form;

require_login();

$contextid = optional_param('contextid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Set page context early to avoid warnings
$PAGE->set_context(context_system::instance());

// Try to detect course context from current page context if accessing from course
if (!$courseid && isset($PAGE->context) && $PAGE->context->contextlevel == CONTEXT_COURSE) {
    $courseid = $PAGE->context->instanceid;
}

// If still no course, try to get from context parameter
if (!$courseid && $contextid) {
    $context = context::instance_by_id($contextid);
    if ($context->contextlevel == CONTEXT_COURSE) {
        $courseid = $context->instanceid;
    }
}

// Set up the page context
if ($courseid) {
    $coursecontext = context_course::instance($courseid);
    $PAGE->set_context($coursecontext);
    $course = get_course($courseid);
    $PAGE->set_course($course);
} else {
    $PAGE->set_context(context_system::instance());
}

$PAGE->set_url('/local/ai_quiz/generate.php', ['courseid' => $courseid]);
$PAGE->set_title(get_string('pluginname', 'local_ai_quiz'));
$PAGE->set_heading(get_string('generatequiz', 'local_ai_quiz'));

// Check capability - check in current context
if ($courseid) {
    require_capability('moodle/question:add', context_course::instance($courseid));
} else {
    require_capability('moodle/question:add', context_system::instance());
}

// Get courses user has access to
$courses = enrol_get_my_courses(['id', 'fullname']);
$courseoptions = [];
foreach ($courses as $course) {
    $courseoptions[$course->id] = $course->fullname;
}

// Get categories for selected course
$categories = [];
if ($courseid) {
    $coursecontext = context_course::instance($courseid);
    $cats = question_bank_helper::get_question_categories($coursecontext->id);
    foreach ($cats as $cat) {
        $categories[$cat->id] = $cat->name;
    }
}

// Initialize form
$customdata = [
    'courses' => $courseoptions,
    'categories' => $categories
];
$mform = new generate_form(null, $customdata);

// Set default course if one is selected
if ($courseid) {
    $mform->set_data(['courseid' => $courseid]);
}

// Handle form submission
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/'));
} else if ($data = $mform->get_data()) {

    try {
        // Get API key
        $apikey = get_config('local_ai_quiz', 'gemini_api_key');

        if (empty($apikey)) {
            throw new moodle_exception('error:no_api_key', 'local_ai_quiz');
        }

        // Initialize generator
        $generator = new quiz_generator($apikey);

        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);
        $tempfiles = []; // Track all temp files for cleanup

        // Parse page ranges
        $primaryranges = parse_page_ranges_input($data->primarypageranges ?? '');
        $supportingranges = parse_page_ranges_input($data->supportingpageranges ?? '');

        // Process PRIMARY documents (required)
        $primarydocs = [];
        if (!empty($data->primarydocuments)) {
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->primarydocuments,
                'filename', false);

            foreach ($files as $file) {
                $filename = $file->get_filename();
                $tempfile = $CFG->tempdir . '/' . $filename;
                $file->copy_content_to($tempfile);
                $tempfiles[] = $tempfile;

                // Check if there's a page range for this file
                $pagerange = $primaryranges[$filename] ?? null;

                $primarydocs[] = [
                    'path' => $tempfile,
                    'pagerange' => $pagerange
                ];
            }
        }

        // Process SUPPORTING documents (optional)
        $supportingdocs = [];
        if (!empty($data->supportingdocuments)) {
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->supportingdocuments,
                'filename', false);

            foreach ($files as $file) {
                $filename = $file->get_filename();
                $tempfile = $CFG->tempdir . '/' . $filename;
                $file->copy_content_to($tempfile);
                $tempfiles[] = $tempfile;

                // Check if there's a page range for this file
                $pagerange = $supportingranges[$filename] ?? null;

                $supportingdocs[] = [
                    'path' => $tempfile,
                    'pagerange' => $pagerange
                ];
            }
        }

        // Process website URLs (treated as supporting)
        $websiteurls = [];
        if (!empty($data->websites)) {
            $urls = explode("\n", $data->websites);
            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $websiteurls[] = $url;
                }
            }
        }

        // Generate quiz with primary/supporting distinction
        // Convert percentages to actual numbers
        $totalquestions = $data->numquestions;
        $easycount = round(($data->easy_pct / 100) * $totalquestions);
        $mediumcount = round(($data->medium_pct / 100) * $totalquestions);
        $hardcount = $totalquestions - $easycount - $mediumcount; // Ensure exact total

        $difficultymix = [
            'easy' => $easycount,
            'medium' => $mediumcount,
            'hard' => $hardcount
        ];

        $quizdata = $generator->create_quiz(
            $primarydocs,
            $supportingdocs,
            $websiteurls,
            $data->numquestions
        );

        // Clean up temp files
        foreach ($tempfiles as $tempfile) {
            @unlink($tempfile);
        }

        // Store questions temporarily for preview
        $sessionkey = md5(uniqid($USER->id, true));
        $temprecord = new stdClass();
        $temprecord->userid = $USER->id;
        $temprecord->courseid = $data->courseid;
        $temprecord->categoryid = $data->categoryid;
        $temprecord->sessionkey = $sessionkey;
        $temprecord->questiondata = json_encode($quizdata);
        $temprecord->timecreated = time();

        $DB->insert_record('local_ai_quiz_temp', $temprecord);

        // Cleanup old temp records (older than 24 hours)
        $DB->delete_records_select('local_ai_quiz_temp',
            'timecreated < :cutoff',
            ['cutoff' => time() - 86400]
        );

        // Redirect to preview page
        $previewurl = new moodle_url('/local/ai_quiz/preview.php', [
            'sessionkey' => $sessionkey
        ]);
        redirect($previewurl);

    } catch (Exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        echo $OUTPUT->footer();
        exit;
    }
}

/**
 * Parse page ranges input text
 *
 * @param string $input Page ranges text input
 * @return array Array mapping filename => ['from' => int, 'to' => int]
 */
function parse_page_ranges_input($input) {
    $ranges = [];

    if (empty(trim($input))) {
        return $ranges;
    }

    $lines = explode("\n", $input);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // Expected format: "filename.pdf: 10-20" or "filename.pdf:10-20"
        if (preg_match('/^(.+?):\s*(\d+)\s*-\s*(\d+)$/i', $line, $matches)) {
            $filename = trim($matches[1]);
            $from = (int)$matches[2];
            $to = (int)$matches[3];

            if ($from >= 1 && $to >= $from) {
                $ranges[$filename] = ['from' => $from, 'to' => $to];
            }
        }
    }

    return $ranges;
}

// Display form
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generatequiz', 'local_ai_quiz'));

// Display info
echo html_writer::start_div('alert alert-info');
echo get_string('generatequiz_help', 'local_ai_quiz');
echo html_writer::end_div();

// Display notice if no course selected
if (!$courseid) {
    echo html_writer::start_div('alert alert-warning');
    echo html_writer::tag('strong', 'Please select a course');
    echo ' - Select a course from the dropdown below to load question categories.';
    echo html_writer::end_div();
}

$mform->display();

echo $OUTPUT->footer();
