<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Add link to generate page in navigation
 *
 * @param global_navigation $navigation
 */
function local_ai_quiz_extend_navigation(global_navigation $navigation) {
    global $PAGE;

    if (has_capability('local/ai_quiz:generate', $PAGE->context)) {
        $node = $navigation->add(
            get_string('pluginname', 'local_ai_quiz'),
            new moodle_url('/local/ai_quiz/generate.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_ai_quiz',
            new pix_icon('i/course', '')
        );
        $node->showinflatnavigation = true;
    }
}

/**
 * Add link to course navigation
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_ai_quiz_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/ai_quiz:generate', $context)) {
        $url = new moodle_url('/local/ai_quiz/generate.php', ['courseid' => $course->id]);
        $node = $navigation->add(
            get_string('pluginname', 'local_ai_quiz'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_ai_quiz',
            new pix_icon('i/course', '')
        );
    }
}
