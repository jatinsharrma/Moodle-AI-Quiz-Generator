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
            'errors' => [],
            'question_ids' => [] // Track successfully imported question IDs
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

        debugging("=== IMPORTING TO CATEGORY ===", DEBUG_DEVELOPER);
        debugging("Category ID: {$categoryid}", DEBUG_DEVELOPER);
        debugging("Category Name: {$category->name}", DEBUG_DEVELOPER);
        debugging("Category ContextID: {$category->contextid}", DEBUG_DEVELOPER);
        debugging("Category Parent: {$category->parent}", DEBUG_DEVELOPER);

        // CRITICAL: Check what columns actually exist in question table
        $columns = $DB->get_columns('question');
        debugging("Question table columns: " . implode(', ', array_keys($columns)), DEBUG_DEVELOPER);

        // Get question type
        $qtype = \question_bank::get_qtype('multichoice');

        foreach ($quizdata['questions'] as $qdata) {
            try {
                $question = self::create_question_object($qdata, $categoryid, $contextid);

                // Prepare for database insert - convert array fields to text
                $dbquestion = clone $question;
                $dbquestion->questiontext = is_array($question->questiontext) ? $question->questiontext['text'] : $question->questiontext;
                $dbquestion->generalfeedback = is_array($question->generalfeedback) ? $question->generalfeedback['text'] : $question->generalfeedback;

                // Remove multichoice-specific fields before base insert
                // CRITICAL: Also save category (not a DB column, but needed for save_question_options)
                $category = $question->category;
                $correctfeedback = $question->correctfeedback;
                $partiallycorrectfeedback = $question->partiallycorrectfeedback;
                $incorrectfeedback = $question->incorrectfeedback;
                $answer = $question->answer;
                $fraction = $question->fraction;
                $feedback = $question->feedback;
                $single = $question->single;
                $shuffleanswers = $question->shuffleanswers;
                $answernumbering = $question->answernumbering;
                $showstandardinstruction = $question->showstandardinstruction;

                unset($dbquestion->category); // Remove category - not a DB column in Moodle 4.0+
                unset($dbquestion->correctfeedback);
                unset($dbquestion->partiallycorrectfeedback);
                unset($dbquestion->incorrectfeedback);
                unset($dbquestion->answer);
                unset($dbquestion->fraction);
                unset($dbquestion->feedback);
                unset($dbquestion->single);
                unset($dbquestion->shuffleanswers);
                unset($dbquestion->answernumbering);
                unset($dbquestion->showstandardinstruction);

                // Insert the base question record
                $dbquestion->id = $DB->insert_record('question', $dbquestion);

                if (!$dbquestion->id) {
                    throw new \Exception("Failed to insert base question record");
                }

                // Restore multichoice fields for save_question_options - KEEP AS ARRAYS
                $question->id = $dbquestion->id;

                // CRITICAL: Restore category (needed for save_question_options to link to category)
                $question->category = $category;

                // CRITICAL: Add context object (not just contextid)
                $question->context = \context::instance_by_id($contextid);

                // Keep combined feedback AS IS (arrays)
                $question->correctfeedback = $correctfeedback;
                $question->partiallycorrectfeedback = $partiallycorrectfeedback;
                $question->incorrectfeedback = $incorrectfeedback;

                // Keep answer and feedback AS IS (arrays)
                $question->answer = $answer;
                $question->fraction = $fraction;
                $question->feedback = $feedback;

                $question->single = $single;
                $question->shuffleanswers = $shuffleanswers;
                $question->answernumbering = $answernumbering;
                $question->showstandardinstruction = $showstandardinstruction;

                // Debug: Log what we're about to save
                debugging("=== SAVING QUESTION ID {$question->id} ===", DEBUG_DEVELOPER);
                debugging("Category: {$question->category}", DEBUG_DEVELOPER);
                debugging("Name: {$question->name}", DEBUG_DEVELOPER);
                debugging("Hidden: {$question->hidden}", DEBUG_DEVELOPER);
                debugging("Answer count: " . count($question->answer), DEBUG_DEVELOPER);

                // Now save the question type-specific options
                $result = $qtype->save_question_options($question);

                // Check if save was successful
                if ($result === false || (is_object($result) && isset($result->error))) {
                    $error = is_object($result) && isset($result->error) ? $result->error : "Unknown error";
                    throw new \Exception("Failed to save question options: " . $error);
                }

                // Verify the question was saved by checking if it has answers
                $savedanswers = $DB->count_records('question_answers', ['question' => $question->id]);
                debugging("Saved answers count: {$savedanswers}", DEBUG_DEVELOPER);

                if ($savedanswers == 0) {
                    throw new \Exception("Question saved but no answers found - options save failed");
                }

                // CRITICAL: Create question_bank_entry to link question to category (Moodle 4.0+)
                // In Moodle 4.0+, questions are linked to categories via question_bank_entries table
                $entry = new \stdClass();
                $entry->questioncategoryid = $categoryid;
                $entry->idnumber = null;
                $entry->ownerid = $USER->id;

                $entryid = $DB->insert_record('question_bank_entries', $entry);
                debugging("Created question_bank_entry ID: {$entryid}", DEBUG_DEVELOPER);

                // Create question_version to link entry to question
                $version = new \stdClass();
                $version->questionbankentryid = $entryid;
                $version->version = 1;
                $version->questionid = $question->id;
                $version->status = 'ready'; // Status: ready, draft, or hidden

                $versionid = $DB->insert_record('question_versions', $version);
                debugging("Created question_version ID: {$versionid}", DEBUG_DEVELOPER);

                // Verify question is in database - DON'T specify fields yet
                $checkquestion = $DB->get_record('question', ['id' => $question->id]);
                debugging("Question in DB: " . ($checkquestion ? 'YES' : 'NO'), DEBUG_DEVELOPER);
                if ($checkquestion) {
                    debugging("Question fields: " . implode(', ', array_keys((array)$checkquestion)), DEBUG_DEVELOPER);
                }

                debugging("Question ID {$question->id} saved successfully with bank entry {$entryid}", DEBUG_DEVELOPER);
                $results['success']++;
                $results['question_ids'][] = $question->id; // Track IDs for verification
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

        // Final verification: Check what's actually in the database
        if (!empty($results['question_ids'])) {
            debugging("=== FINAL VERIFICATION ===", DEBUG_DEVELOPER);
            debugging("Imported question IDs: " . implode(', ', $results['question_ids']), DEBUG_DEVELOPER);

            // Query database directly to see what's there (no field list to avoid column errors)
            list($insql, $inparams) = $DB->get_in_or_equal($results['question_ids']);
            $dbquestions = $DB->get_records_select('question', "id $insql", $inparams);
            debugging("Found " . count($dbquestions) . " questions in database", DEBUG_DEVELOPER);

            if ($dbquestions) {
                $first = reset($dbquestions);
                debugging("First question fields: " . implode(', ', array_keys((array)$first)), DEBUG_DEVELOPER);
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

        // Generate a short, descriptive name (NOT shown to students)
        $question->name = self::generate_question_name($qdata);

        // The actual question text (shown to students)
        $question->questiontext = [
            'text' => $qdata['question'],
            'format' => FORMAT_HTML,
            'itemid' => 0
        ];
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = [
            'text' => $qdata['explanation'] ?? '',
            'format' => FORMAT_HTML,
            'itemid' => 0
        ];
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

        // Determine if single or multiple answer question
        $answertype = $qdata['answer_type'] ?? 'single';
        $ismultiple = ($answertype === 'multiple');

        // Multichoice specific fields
        $question->single = $ismultiple ? 0 : 1; // 0 = multiple answers, 1 = single answer
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

        // Handle both single answer (string) and multiple answers (array)
        $correctanswer = $qdata['correct_answer'];
        $correctanswers = is_array($correctanswer) ? $correctanswer : [$correctanswer];
        $answeroptions = ['A', 'B', 'C', 'D'];

        // Calculate fraction for each correct answer
        // For multiple correct answers, distribute 1.0 across all correct answers
        $numcorrect = count($correctanswers);
        $fractionpercorrect = 1.0 / $numcorrect;

        $answerindex = 0;
        foreach ($answeroptions as $optionkey) {
            if (isset($qdata['options'][$optionkey])) {
                // Answer text - must be array with text and format keys
                $question->answer[$answerindex] = [
                    'text' => $qdata['options'][$optionkey],
                    'format' => FORMAT_HTML,
                    'itemid' => 0
                ];

                // Set fraction (distribute 1.0 across correct answers, 0.0 for incorrect)
                $iscorrect = in_array($optionkey, $correctanswers);
                $question->fraction[$answerindex] = $iscorrect ? $fractionpercorrect : 0.0;

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
     * Generate a short, descriptive question name for organization
     * Format: "Q{id} - {type} - {difficulty} - {topic/keywords}"
     *
     * @param array $qdata Question data from AI
     * @return string Short question name (max 60 chars)
     */
    private static function generate_question_name($qdata) {
        $parts = [];

        // Start with Q{id} if available
        if (isset($qdata['id'])) {
            $parts[] = 'Q' . $qdata['id'];
        }

        // Add answer type indicator (MA = Multiple Answer, SA = Single Answer)
        $answertype = $qdata['answer_type'] ?? 'single';
        if ($answertype === 'multiple') {
            $parts[] = '[MA]'; // Multiple Answer
        }

        // Add difficulty if available
        if (isset($qdata['difficulty']) && !empty($qdata['difficulty'])) {
            $parts[] = ucfirst($qdata['difficulty']);
        }

        // Add topic or extract keywords from question
        if (isset($qdata['topic']) && !empty($qdata['topic'])) {
            // Use topic if available
            $parts[] = self::truncate_text($qdata['topic'], 30);
        } else if (isset($qdata['question'])) {
            // Extract first few meaningful words from question
            $keywords = self::extract_keywords($qdata['question']);
            if ($keywords) {
                $parts[] = $keywords;
            }
        }

        // Combine parts with separator
        $name = implode(' - ', $parts);

        // Ensure it's not too long (max 60 chars for question name)
        return self::truncate_text($name, 60);
    }

    /**
     * Extract meaningful keywords from question text
     *
     * @param string $text Question text
     * @return string Keywords (max 25 chars)
     */
    private static function extract_keywords($text) {
        // Remove question marks and extra spaces
        $text = trim(str_replace('?', '', $text));

        // Remove common stop words
        $stopwords = ['what', 'which', 'when', 'where', 'why', 'how', 'is', 'are', 'the', 'a', 'an', 'in', 'on', 'at', 'to', 'of'];

        // Split into words
        $words = preg_split('/\s+/', strtolower($text));

        // Filter out stop words and keep meaningful ones
        $keywords = [];
        foreach ($words as $word) {
            if (strlen($word) >= 4 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
                if (count($keywords) >= 3) {
                    break; // Keep first 3 meaningful words
                }
            }
        }

        if (empty($keywords)) {
            // Fallback: use first few words
            $words = array_slice($words, 0, 3);
            return ucfirst(implode(' ', $words));
        }

        return ucfirst(implode(' ', $keywords));
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
