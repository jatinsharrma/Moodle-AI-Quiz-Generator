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
 * AI Quiz Generator using Gemini 2.5 Flash
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_generator {

    /** @var string API key for Gemini */
    private $apikey;

    /** @var string API endpoint */
    private $apiendpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    /** @var array Usage statistics */
    private $usagestats;

    /**
     * Constructor
     *
     * @param string $apikey Gemini API key
     */
    public function __construct($apikey = null) {
        $this->apikey = $apikey ?? get_config('local_ai_quiz', 'gemini_api_key');
        $this->usagestats = [
            'total_requests' => 0,
            'total_cost_estimate' => 0.0
        ];

        if (empty($this->apikey)) {
            throw new \moodle_exception('error:no_api_key', 'local_ai_quiz');
        }
    }

    /**
     * Process PDF file using docling
     *
     * @param string $filepath Path to PDF file
     * @return string Extracted text content
     */
    public function process_pdf($filepath) {
        // For now, use simple PDF text extraction
        // TODO: Integrate docling or pdftotext
        debugging('Processing PDF: ' . $filepath, DEBUG_DEVELOPER);

        // Simple extraction using pdftotext if available
        $output = [];
        $returnvar = 0;
        exec("pdftotext " . escapeshellarg($filepath) . " -", $output, $returnvar);

        if ($returnvar === 0) {
            return implode("\n", $output);
        }

        throw new \moodle_exception('error:pdf_processing_failed', 'local_ai_quiz');
    }

    /**
     * Process DOCX file
     *
     * @param string $filepath Path to DOCX file
     * @return string Extracted text content
     */
    public function process_docx($filepath) {
        debugging('Processing DOCX: ' . $filepath, DEBUG_DEVELOPER);

        // Simple text extraction from DOCX
        // TODO: Implement proper DOCX parsing
        $zip = new \ZipArchive();
        if ($zip->open($filepath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            // PHP 8.1 compatibility: ensure $xml is string before strip_tags
            if ($xml !== false && is_string($xml)) {
                // Remove XML tags to get plain text
                $text = strip_tags($xml);
                return $text;
            }
        }

        throw new \moodle_exception('error:docx_processing_failed', 'local_ai_quiz');
    }

    /**
     * Process PPTX file
     *
     * @param string $filepath Path to PPTX file
     * @return string Extracted text content
     */
    public function process_pptx($filepath) {
        debugging('Processing PPTX: ' . $filepath, DEBUG_DEVELOPER);

        // Simple text extraction from PPTX
        // TODO: Implement proper PPTX parsing
        $zip = new \ZipArchive();
        $text = [];

        if ($zip->open($filepath) === true) {
            for ($i = 1; $i < 50; $i++) { // Try up to 50 slides
                $slidexml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                // PHP 8.1 compatibility: ensure $slidexml is string before strip_tags
                if ($slidexml !== false && is_string($slidexml)) {
                    $text[] = strip_tags($slidexml);
                }
            }
            $zip->close();
        }

        if (!empty($text)) {
            return implode("\n", $text);
        }

        throw new \moodle_exception('error:pptx_processing_failed', 'local_ai_quiz');
    }

    /**
     * Process website URL
     *
     * @param string $url Website URL
     * @return string Extracted content
     */
    public function process_website($url) {
        debugging('Processing website: ' . $url, DEBUG_DEVELOPER);

        // Simple HTML fetching and text extraction
        $content = file_get_contents($url);

        // PHP 8.1 compatibility: ensure $content is string
        if ($content === false || !is_string($content)) {
            throw new \moodle_exception('error:website_fetch_failed', 'local_ai_quiz');
        }

        // Basic HTML to text conversion
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace

        return trim($text);
    }

    /**
     * Generate MCQ questions using Gemini API
     *
     * @param string $context Learning content
     * @param int $numquestions Number of questions to generate
     * @param array $difficultymix Distribution of difficulty levels
     * @param bool $primaryonly If true, questions must come from primary documents only
     * @return array Generated MCQs
     */
    public function generate_mcqs($context, $numquestions = 20, $difficultymix = null, $primaryonly = false, $multipleanswerconfig = null) {
        if ($difficultymix === null) {
            $difficultymix = [
                'easy' => (int)($numquestions / 4),
                'medium' => (int)($numquestions / 2),
                'hard' => (int)($numquestions / 4)
            ];
        }

        // Calculate safe context size (Gemini 2.5 Pro has 2M token context)
        $maxinputtokens = 1900000; // Leave room for prompt and response
        $maxcontextchars = $maxinputtokens * 4;

        $originallength = strlen($context);

        if (strlen($context) > $maxcontextchars) {
            debugging("Context size: {$originallength} chars, truncating...", DEBUG_DEVELOPER);

            // Smart truncation: take beginning and end
            $takefromstart = (int)($maxcontextchars * 0.7);
            $takefromend = $maxcontextchars - $takefromstart;

            $context = substr($context, 0, $takefromstart) .
                       "\n\n[... content truncated ...]\n\n" .
                       substr($context, -$takefromend);
        }

        $prompt = $this->build_mcq_prompt($context, $numquestions, $difficultymix, $primaryonly, $multipleanswerconfig);

        debugging('Generating ' . $numquestions . ' MCQs...', DEBUG_DEVELOPER);

        $response = $this->call_gemini_api($prompt);

        $this->usagestats['total_requests']++;

        return $response;
    }

    /**
     * Build the MCQ generation prompt
     *
     * @param string $context Learning content
     * @param int $numquestions Number of questions
     * @param array $difficultymix Difficulty distribution
     * @param bool $primaryonly If true, emphasize primary document boundary
     * @param array $multipleanswerconfig Multiple answer configuration
     * @return string Formatted prompt
     */
    private function build_mcq_prompt($context, $numquestions, $difficultymix, $primaryonly = false, $multipleanswerconfig = null) {
        $timestamp = date('c');

        // Add primary document instruction if needed
        $scopeinstruction = '';
        if ($primaryonly) {
            $scopeinstruction = <<<SCOPE

CRITICAL SCOPE RESTRICTION:
- Generate questions ONLY from PRIMARY SOURCE MATERIALS
- Supporting materials are for context/reference only
- Do NOT create questions from supporting documents or websites
- All questions must be answerable from primary materials alone
- Primary materials set the scope and boundary for quiz content

SCOPE;
        }

        // Build answer type instruction based on configuration
        $answertypeinstruction = '';
        if ($multipleanswerconfig && $multipleanswerconfig['count'] > 0) {
            $macount = $multipleanswerconfig['count'];
            $singlecount = $numquestions - $macount;
            $maeasy = $multipleanswerconfig['difficulty']['easy'];
            $mamedium = $multipleanswerconfig['difficulty']['medium'];
            $mahard = $multipleanswerconfig['difficulty']['hard'];

            $answertypeinstruction = <<<ANSWERTYPE

3. ANSWER TYPE VARIETY:
   - Single Answer: {$singlecount} questions (only ONE correct option)
   - Multiple Answer: {$macount} questions (TWO or more correct options, marked with "answer_type": "multiple")
     * Multiple Answer Difficulty: {$maeasy} easy, {$mamedium} medium, {$mahard} hard
   - For multiple answer questions:
     * Clearly indicate which options are correct
     * Use "Select all that apply" or similar phrasing in question text
     * correct_answer field should be an array like ["A", "C"]

ANSWERTYPE;
        } else {
            // No multiple answer config = ALL single answer questions
            $answertypeinstruction = <<<ANSWERTYPE

3. ANSWER TYPE:
   - ALL questions must be Single Answer ONLY (only ONE correct option)
   - Do NOT generate any multiple answer questions
   - ALL questions should have "answer_type": "single"
   - correct_answer field should be a single letter like "B", NOT an array

ANSWERTYPE;
        }

        return <<<PROMPT
You are an expert educator creating assessment questions for students.

LEARNING CONTENT:
{$context}
{$scopeinstruction}

TASK: Generate {$numquestions} multiple-choice questions.

REQUIREMENTS:

1. DIFFICULTY DISTRIBUTION:
   - Easy: {$difficultymix['easy']} questions (recall, definitions)
   - Medium: {$difficultymix['medium']} questions (understanding, application)
   - Hard: {$difficultymix['hard']} questions (analysis, synthesis)

2. QUESTION TYPES:
   - Conceptual understanding: 40%
   - Application/problem-solving: 30%
   - Factual recall: 20%
   - Analysis/evaluation: 10%

{$answertypeinstruction}

4. QUALITY STANDARDS:
   - Each question has EXACTLY 4 options (A, B, C, D)
   - Distractors are plausible but clearly wrong if you know the material
   - Questions are standalone and clear
   - Cover the ENTIRE PRIMARY content proportionally, not just one section
   - Use varied question stems
   - Only use information explicitly stated in primary materials
   - Avoid questions like "According to the document..."

OUTPUT JSON SCHEMA:
{
  "questions": [
    {
      "id": 1,
      "question": "Question text here?",
      "options": {
        "A": "Option A",
        "B": "Option B",
        "C": "Option C",
        "D": "Option D"
      },
      "correct_answer": "B",
      "answer_type": "single",
      "difficulty": "medium",
      "topic": "Topic being tested",
      "question_type": "application",
      "explanation": "Why B is correct"
    },
    {
      "id": 2,
      "question": "Select all that apply...",
      "options": {
        "A": "Correct option 1",
        "B": "Incorrect option",
        "C": "Correct option 2",
        "D": "Incorrect option"
      },
      "correct_answer": ["A", "C"],
      "answer_type": "multiple",
      "difficulty": "hard",
      "topic": "Topic being tested",
      "question_type": "analysis",
      "explanation": "Why A and C are correct"
    }
  ],
  "metadata": {
    "total_questions": {$numquestions},
    "generated_at": "{$timestamp}"
  }
}
PROMPT;
    }

    /**
     * Call Gemini API
     *
     * @param string $prompt The prompt to send
     * @return array Decoded JSON response
     */
    private function call_gemini_api($prompt) {
        $url = $this->apiendpoint . '?key=' . $this->apikey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            // Try to get actual error message from API response
            $errordetail = '';
            $errordata = json_decode($response, true);
            if (isset($errordata['error']['message'])) {
                $errordetail = $errordata['error']['message'];
            } else if (isset($errordata['error'])) {
                $errordetail = json_encode($errordata['error']);
            }

            // Log full error for debugging
            debugging("Gemini API Error (HTTP {$httpcode}): " . $response, DEBUG_DEVELOPER);

            // Handle specific error codes
            if ($httpcode === 429) {
                throw new \moodle_exception('error:quota_exceeded', 'local_ai_quiz', '',
                    'Gemini API quota exceeded. ' . ($errordetail ? $errordetail : 'Please wait and try again later.'));
            } else if ($httpcode === 401 || $httpcode === 403) {
                throw new \moodle_exception('error:api_auth_failed', 'local_ai_quiz', '',
                    'API authentication failed. ' . ($errordetail ? $errordetail : 'Please check your API key.'));
            } else if ($httpcode === 400) {
                throw new \moodle_exception('error:api_bad_request', 'local_ai_quiz', '',
                    'Bad request to API. ' . ($errordetail ? $errordetail : 'HTTP code: ' . $httpcode));
            } else if ($httpcode === 404) {
                throw new \moodle_exception('error:api_not_found', 'local_ai_quiz', '',
                    'API endpoint or model not found. ' . ($errordetail ? $errordetail : 'Check model name: gemini-2.5-flash'));
            } else {
                throw new \moodle_exception('error:api_request_failed', 'local_ai_quiz', '',
                    'HTTP ' . $httpcode . ': ' . ($errordetail ? $errordetail : 'Unknown error'));
            }
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('error:json_decode_failed', 'local_ai_quiz');
        }

        // Extract text from Gemini response format
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $mcqsjson = $result['candidates'][0]['content']['parts'][0]['text'];
            $mcqs = json_decode($mcqsjson, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $mcqs;
            }
        }

        throw new \moodle_exception('error:invalid_api_response', 'local_ai_quiz');
    }

    /**
     * Validate generated MCQs
     *
     * @param array $mcqs MCQ data
     * @return array List of validation issues
     */
    public function validate_mcqs($mcqs) {
        $issues = [];

        if (!isset($mcqs['questions'])) {
            return ["Missing 'questions' key"];
        }

        foreach ($mcqs['questions'] as $i => $q) {
            $qnum = $i + 1;

            // Check required fields
            $required = ['question', 'options', 'correct_answer', 'difficulty'];
            foreach ($required as $field) {
                if (!isset($q[$field])) {
                    $issues[] = "Q{$qnum}: Missing '{$field}'";
                }
            }

            // Check options
            if (isset($q['options'])) {
                if (count($q['options']) !== 4) {
                    $issues[] = "Q{$qnum}: Must have 4 options";
                }

                $optionkeys = array_keys($q['options']);
                sort($optionkeys);
                if ($optionkeys !== ['A', 'B', 'C', 'D']) {
                    $issues[] = "Q{$qnum}: Options must be A,B,C,D";
                }
            }

            // Check correct answer (handle both single and multiple answer types)
            if (isset($q['correct_answer']) && isset($q['options'])) {
                $correctanswer = $q['correct_answer'];

                // Handle both single answer (string) and multiple answer (array)
                if (is_array($correctanswer)) {
                    // Multiple answer - check each answer is valid
                    foreach ($correctanswer as $answer) {
                        if (!isset($q['options'][$answer])) {
                            $issues[] = "Q{$qnum}: Correct answer '{$answer}' not in options";
                        }
                    }
                } else {
                    // Single answer - check it exists
                    if (!isset($q['options'][$correctanswer])) {
                        $issues[] = "Q{$qnum}: Correct answer not in options";
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Create quiz from uploaded files
     *
     * @param array $primarydocs Array of ['path' => string, 'pagerange' => ['from' => int, 'to' => int]]
     * @param array $supportingdocs Array of ['path' => string, 'pagerange' => ['from' => int, 'to' => int]]
     * @param array $websiteurls Array of website URLs
     * @param int $numquestions Number of questions to generate
     * @param array $difficultymix Difficulty distribution ['easy' => int, 'medium' => int, 'hard' => int]
     * @param array $multipleanswerconfig Multiple answer config ['count' => int, 'difficulty' => ['easy' => int, 'medium' => int, 'hard' => int]]
     * @return array Generated quiz data
     */
    public function create_quiz($primarydocs = null, $supportingdocs = null, $websiteurls = null, $numquestions = 20, $difficultymix = null, $multipleanswerconfig = null) {
        $primaryparts = [];
        $supportingparts = [];

        // Process PRIMARY documents (required - questions come from here)
        if ($primarydocs) {
            foreach ($primarydocs as $doc) {
                $docpath = $doc['path'];
                $pagerange = $doc['pagerange'] ?? null;
                $ext = strtolower(pathinfo($docpath, PATHINFO_EXTENSION));
                $filename = basename($docpath);

                try {
                    $rangestr = '';
                    if ($pagerange && isset($pagerange['from']) && isset($pagerange['to'])) {
                        $rangestr = " (pages {$pagerange['from']}-{$pagerange['to']})";
                    }

                    switch ($ext) {
                        case 'pdf':
                            if ($pagerange) {
                                $content = pdf_extractor::extract_pages(
                                    $docpath,
                                    $pagerange['from'],
                                    $pagerange['to']
                                );
                            } else {
                                $content = $this->process_pdf($docpath);
                            }
                            break;
                        case 'docx':
                            $content = $this->process_docx($docpath);
                            break;
                        case 'pptx':
                            $content = $this->process_pptx($docpath);
                            break;
                        default:
                            debugging("Skipping unsupported file: {$docpath}", DEBUG_DEVELOPER);
                            continue 2;
                    }

                    $primaryparts[] = "=== PRIMARY DOCUMENT: {$filename}{$rangestr} ===\n{$content}\n";
                } catch (\Exception $e) {
                    debugging("Error processing primary doc {$docpath}: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        // Process SUPPORTING documents (optional - for context only)
        if ($supportingdocs) {
            foreach ($supportingdocs as $doc) {
                $docpath = $doc['path'];
                $pagerange = $doc['pagerange'] ?? null;
                $ext = strtolower(pathinfo($docpath, PATHINFO_EXTENSION));
                $filename = basename($docpath);

                try {
                    $rangestr = '';
                    if ($pagerange && isset($pagerange['from']) && isset($pagerange['to'])) {
                        $rangestr = " (pages {$pagerange['from']}-{$pagerange['to']})";
                    }

                    switch ($ext) {
                        case 'pdf':
                            if ($pagerange) {
                                $content = pdf_extractor::extract_pages(
                                    $docpath,
                                    $pagerange['from'],
                                    $pagerange['to']
                                );
                            } else {
                                $content = $this->process_pdf($docpath);
                            }
                            break;
                        case 'docx':
                            $content = $this->process_docx($docpath);
                            break;
                        case 'pptx':
                            $content = $this->process_pptx($docpath);
                            break;
                        default:
                            debugging("Skipping unsupported file: {$docpath}", DEBUG_DEVELOPER);
                            continue 2;
                    }

                    $supportingparts[] = "=== SUPPORTING DOCUMENT: {$filename}{$rangestr} ===\n{$content}\n";
                } catch (\Exception $e) {
                    debugging("Error processing supporting doc {$docpath}: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        // Process websites (treated as supporting material)
        if ($websiteurls) {
            foreach ($websiteurls as $url) {
                try {
                    $webcontent = $this->process_website($url);
                    $supportingparts[] = "=== SUPPORTING WEBSITE: {$url} ===\n{$webcontent}\n";
                } catch (\Exception $e) {
                    debugging("Error processing {$url}: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        // Ensure we have primary documents
        if (empty($primaryparts)) {
            throw new \moodle_exception('error:no_primary_docs', 'local_ai_quiz');
        }

        // Assemble context with clear PRIMARY vs SUPPORTING distinction
        $fullcontext = "PRIMARY SOURCE MATERIALS (questions must come from these):\n\n";
        $fullcontext .= implode("\n\n", $primaryparts);

        if (!empty($supportingparts)) {
            $fullcontext .= "\n\n" . str_repeat("=", 80) . "\n\n";
            $fullcontext .= "SUPPORTING MATERIALS (for context/reference only):\n\n";
            $fullcontext .= implode("\n\n", $supportingparts);
        }

        debugging("Context assembled: ~" . str_word_count($fullcontext) . " words", DEBUG_DEVELOPER);

        // Set default difficulty mix if not provided
        if ($difficultymix === null) {
            $easycount = round(0.25 * $numquestions);
            $mediumcount = round(0.50 * $numquestions);
            $hardcount = $numquestions - $easycount - $mediumcount;
            $difficultymix = [
                'easy' => $easycount,
                'medium' => $mediumcount,
                'hard' => $hardcount
            ];
        }

        // Generate MCQs with primary document emphasis
        $mcqs = $this->generate_mcqs($fullcontext, $numquestions, $difficultymix, true, $multipleanswerconfig);

        // Add source information to metadata
        $mcqs['metadata']['source_type'] = 'primary_documents';
        $mcqs['metadata']['primary_count'] = count($primaryparts);
        $mcqs['metadata']['supporting_count'] = count($supportingparts);

        // Validate
        $issues = $this->validate_mcqs($mcqs);
        if (!empty($issues)) {
            debugging("Validation issues: " . implode(', ', $issues), DEBUG_DEVELOPER);
        }

        return $mcqs;
    }

    /**
     * Get usage statistics
     *
     * @return array Usage stats
     */
    public function get_usage_stats() {
        return $this->usagestats;
    }
}
