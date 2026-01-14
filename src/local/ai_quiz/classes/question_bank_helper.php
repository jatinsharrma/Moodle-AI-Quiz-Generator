<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_ai_quiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

/**
 * Helper class for adding AI-generated questions to Moodle question bank
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_bank_helper {

    /**
     * Import AI-generated quiz questions into Moodle question bank
     *
     * @param array $quizdata Quiz data from AI generator
     * @param int $categoryid Question category ID
     * @param int $contextid Context ID
     * @return array Results with success/failure counts
     */
    public static function import_questions($quizdata, $categoryid, $contextid) {
        global $DB, $USER;

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        if (!isset($quizdata['questions']) || empty($quizdata['questions'])) {
            $results['errors'][] = 'No questions found in quiz data';
            return $results;
        }

        // Verify category exists
        $category = $DB->get_record('question_categories', ['id' => $categoryid]);
        if (!$category) {
            $results['errors'][] = "Invalid category ID: {$categoryid}";
            return $results;
        }

        // Get question type
        $qtype = \question_bank::get_qtype('multichoice');

        foreach ($quizdata['questions'] as $qdata) {
            try {
                $question = self::create_question_object($qdata, $categoryid, $contextid);

                // Simulate form data - this is what save_question expects
                $formdata = clone $question;

                // Save the question using the question type's save method
                $savedquestion = $qtype->save_question($question, $formdata);

                if ($savedquestion && isset($savedquestion->id)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to save: " . self::truncate_text($qdata['question'], 50);
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
                debugging("Question import error: " . $e->getMessage() . " | Question: " . json_encode($qdata), DEBUG_DEVELOPER);
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
                debugging("Question import error: " . $e->getMessage() . " | Question: " . json_encode($qdata), DEBUG_DEVELOPER);
            }
        }

        return $results;
    }

    /**
     * Create a Moodle question object from AI-generated data
     *
     * @param array $qdata Question data from AI
     * @param int $categoryid Category ID
     * @param int $contextid Context ID
     * @return \stdClass Question object
     */
    private static function create_question_object($qdata, $categoryid, $contextid) {
        global $USER;

        $question = new \stdClass();

        // Core question fields
        $question->id = 0; // New question
        $question->category = $categoryid;
        $question->contextid = $contextid;
        $question->parent = 0;
        $question->name = self::truncate_text($qdata['question'], 100);
        $question->questiontext = $qdata['question'];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $qdata['explanation'] ?? '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1.0;
        $question->penalty = 0.3333333;
        $question->qtype = 'multichoice';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->hidden = 0;
        $question->idnumber = '';
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        // Multichoice specific fields
        $question->single = 1; // Single answer
        $question->shuffleanswers = 1; // Shuffle the choices
        $question->answernumbering = 'abc'; // Use a, b, c, d numbering
        $question->showstandardinstruction = 1; // Show standard instructions

        // Combined feedback (for correct/partial/incorrect)
        $question->correctfeedback = [
            'text' => 'Your answer is correct.',
            'format' => FORMAT_HTML,
            'itemid' => 0
        ];
        $question->correctfeedbackformat = FORMAT_HTML;

        $question->partiallycorrectfeedback = [
            'text' => 'Your answer is partially correct.',
            'format' => FORMAT_HTML,
            'itemid' => 0
        ];
        $question->partiallycorrectfeedbackformat = FORMAT_HTML;

        $question->incorrectfeedback = [
            'text' => 'Your answer is incorrect.',
            'format' => FORMAT_HTML,
            'itemid' => 0
        ];
        $question->incorrectfeedbackformat = FORMAT_HTML;

        // Process answers - Moodle expects simple text arrays
        $question->answer = [];
        $question->fraction = [];
        $question->feedback = [];

        $correctanswer = $qdata['correct_answer'];
        $answeroptions = ['A', 'B', 'C', 'D'];

        $answerindex = 0;
        foreach ($answeroptions as $optionkey) {
            if (isset($qdata['options'][$optionkey])) {
                // Answer text - must be array with text and format keys
                $question->answer[$answerindex] = [
                    'text' => $qdata['options'][$optionkey],
                    'format' => FORMAT_HTML,
                    'itemid' => 0
                ];

                // Set fraction (1.0 for correct, 0.0 for incorrect)
                $question->fraction[$answerindex] = ($optionkey === $correctanswer) ? 1.0 : 0.0;

                // Feedback for this answer
                $question->feedback[$answerindex] = [
                    'text' => '',
                    'format' => FORMAT_HTML,
                    'itemid' => 0
                ];

                $answerindex++;
            }
        }

        // Ensure we have exactly 4 answers
        if (count($question->answer) !== 4) {
            throw new \moodle_exception('error:invalid_question_format', 'local_ai_quiz',
                '', 'Question must have exactly 4 options');
        }

        // Add tags if available
        $question->tags = [];
        if (isset($qdata['topic']) && !empty($qdata['topic'])) {
            $question->tags[] = $qdata['topic'];
        }
        if (isset($qdata['difficulty']) && !empty($qdata['difficulty'])) {
            $question->tags[] = $qdata['difficulty'];
        }

        return $question;
    }

    /**
     * Truncate text to specified length
     *
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @return string Truncated text
     */
    private static function truncate_text($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }

    /**
     * Get available question categories for a course context
     *
     * @param int $contextid Context ID
     * @return array Array of categories
     */
    public static function get_question_categories($contextid) {
        global $DB;

        $categories = $DB->get_records('question_categories',
            ['contextid' => $contextid],
            'name ASC',
            'id, name, contextid, parent'
        );

        return $categories;
    }

    /**
     * Create a new question category
     *
     * @param string $name Category name
     * @param int $contextid Context ID
     * @param int $parent Parent category ID (0 for top level)
     * @return int New category ID
     */
    public static function create_question_category($name, $contextid, $parent = 0) {
        global $DB;

        $category = new \stdClass();
        $category->name = $name;
        $category->contextid = $contextid;
        $category->info = '';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = $parent;
        $category->sortorder = 999;
        $category->idnumber = null;

        return $DB->insert_record('question_categories', $category);
    }
}
