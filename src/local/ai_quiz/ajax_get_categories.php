<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_ai_quiz\question_bank_helper;

require_login();

$courseid = required_param('courseid', PARAM_INT);

// Check capability
if ($courseid) {
    require_capability('moodle/question:add', context_course::instance($courseid));
} else {
    require_capability('moodle/question:add', context_system::instance());
}

$categories = [];
if ($courseid) {
    $coursecontext = context_course::instance($courseid);
    $cats = question_bank_helper::get_question_categories($coursecontext->id);
    foreach ($cats as $cat) {
        $categories[] = [
            'id' => $cat->id,
            'name' => $cat->name
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['categories' => $categories]);
