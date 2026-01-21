<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use local_ai_quiz\question_bank_helper;

require_login();

$courseid = required_param('courseid', PARAM_INT);
$categoryname = required_param('name', PARAM_TEXT);
$categoryinfo = optional_param('info', '', PARAM_TEXT);

// Check capability
if ($courseid) {
    $context = context_course::instance($courseid);
    require_capability('moodle/question:managecategory', $context);
} else {
    $context = context_system::instance();
    require_capability('moodle/question:managecategory', $context);
}

try {
    // Find the top category for this context
    // In Moodle, each context has a "top" category (name = 'top')
    $topcategory = $DB->get_record('question_categories', [
        'contextid' => $context->id,
        'parent' => 0
    ]);

    // If no top category exists, create it (shouldn't happen in normal Moodle)
    if (!$topcategory) {
        $topcategory = new stdClass();
        $topcategory->name = 'top';
        $topcategory->contextid = $context->id;
        $topcategory->info = '';
        $topcategory->infoformat = FORMAT_HTML;
        $topcategory->parent = 0;
        $topcategory->sortorder = 0;
        $topcategory->stamp = make_unique_id_code();
        $topcategory->id = $DB->insert_record('question_categories', $topcategory);
    }

    // Create category as child of top category
    $category = new stdClass();
    $category->name = trim($categoryname);
    $category->contextid = $context->id;
    $category->info = trim($categoryinfo);
    $category->infoformat = FORMAT_HTML;
    $category->parent = $topcategory->id; // Child of top category, not orphan
    $category->sortorder = 999; // At the end
    $category->stamp = make_unique_id_code();

    // Insert into database
    $categoryid = $DB->insert_record('question_categories', $category);

    if ($categoryid) {
        // Get all categories to return updated list
        $categories = [];
        $cats = question_bank_helper::get_question_categories($context->id);
        foreach ($cats as $cat) {
            $categories[] = [
                'id' => $cat->id,
                'name' => $cat->name
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'categoryid' => $categoryid,
            'categoryname' => $category->name,
            'categories' => $categories
        ]);
    } else {
        throw new moodle_exception('error:category_creation_failed', 'local_ai_quiz');
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
