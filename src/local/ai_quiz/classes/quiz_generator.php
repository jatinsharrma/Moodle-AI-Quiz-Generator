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

    /** @var array Non-fatal warnings raised while preparing source material. */
    private $warnings = [];

    /**
     * Maximum raw bytes of source files that may be attached for native reading.
     * Gemini caps an inline request at 20MB and base64 inflates by ~4/3, so this
     * leaves headroom for the prompt itself.
     */
    const MAX_INLINE_BYTES = 12582912; // 12 MB.

    /**
     * Warnings raised during the last create_quiz() call.
     *
     * @return array List of human-readable warning strings
     */
    public function get_warnings() {
        return $this->warnings;
    }

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
        debugging('Processing PDF: ' . $filepath, DEBUG_DEVELOPER);
        debugging('PDF file exists: ' . (file_exists($filepath) ? 'YES' : 'NO'), DEBUG_DEVELOPER);
        debugging('PDF file size: ' . (file_exists($filepath) ? filesize($filepath) . ' bytes' : 'N/A'), DEBUG_DEVELOPER);
        debugging('exec() available: ' . (function_exists('exec') ? 'YES' : 'NO'), DEBUG_DEVELOPER);

        // Throws extraction_unavailable if no real text can be extracted, so that
        // callers can attach the original PDF instead of guessing at its content.
        return pdf_extractor::extract_pages($filepath);
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

            // PHP 8.1 compatibility: ensure $xml is string before processing
            if ($xml !== false && is_string($xml)) {
                return self::xml_to_text($xml);
            }
        }

        throw new \moodle_exception('error:docx_processing_failed', 'local_ai_quiz');
    }

    /**
     * Convert Office XML markup to plain text.
     *
     * strip_tags() would concatenate adjacent text runs, turning "the quick brown"
     * into "thequickbrown" wherever formatting splits a sentence into runs. Tags
     * are replaced with a space instead so word boundaries survive.
     *
     * @param string $xml Raw Office XML
     * @return string Plain text
     */
    private static function xml_to_text($xml) {
        // Drop elements whose text content is not document prose.
        $xml = preg_replace('#<(w|a):instrText\b[^>]*>.*?</\1:instrText>#s', ' ', $xml);

        // Paragraph and line breaks become real newlines.
        $xml = preg_replace('#<(w:p|a:p|w:br|a:br)\b[^>]*/?>#', "\n", $xml);

        // Every other tag becomes a space so runs don't fuse together.
        $text = preg_replace('/<[^>]*>/', ' ', $xml);

        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Collapse runs of spaces/tabs but keep paragraph structure.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);

        return trim($text);
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
                // PHP 8.1 compatibility: ensure $slidexml is string before processing
                if ($slidexml !== false && is_string($slidexml)) {
                    $text[] = self::xml_to_text($slidexml);
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
    public function generate_mcqs($sources, $numquestions = 20, $difficultymix = null, $primaryonly = false, $multipleanswerconfig = null) {
        // Accept a plain context string for backwards compatibility.
        if (!is_array($sources)) {
            $sources = ['context' => (string)$sources, 'primarytext' => (string)$sources, 'files' => []];
        }

        $context = $sources['context'] ?? '';
        $primarytext = $sources['primarytext'] ?? '';
        $inlinefiles = $sources['files'] ?? [];

        if ($difficultymix === null) {
            $difficultymix = [
                'easy' => (int)($numquestions / 4),
                'medium' => (int)($numquestions / 2),
                'hard' => (int)($numquestions / 4)
            ];
        }

        // Calculate safe context size (Gemini 2.5 Flash has a 1M token context).
        $maxinputtokens = 900000; // Leave room for prompt and response.
        $maxcontextchars = $maxinputtokens * 4;

        $originallength = strlen($context);

        if (strlen($context) > $maxcontextchars) {
            debugging("Context size: {$originallength} chars, truncating...", DEBUG_DEVELOPER);

            // Smart truncation: take beginning and end.
            $takefromstart = (int)($maxcontextchars * 0.7);
            $takefromend = $maxcontextchars - $takefromstart;

            $context = substr($context, 0, $takefromstart) .
                       "\n\n[... content truncated ...]\n\n" .
                       substr($context, -$takefromend);
        }

        // Debug context before building prompt.
        debugging("Context length before prompt: " . strlen($context) . " chars", DEBUG_DEVELOPER);
        debugging("Primary text length: " . strlen($primarytext) . " chars", DEBUG_DEVELOPER);
        debugging("Inline files attached: " . count($inlinefiles), DEBUG_DEVELOPER);

        // CRITICAL: check the actual source *content*, not the assembled context.
        // The assembled context always begins with a fixed header, so testing it
        // for emptiness can never fail and previously let empty extractions through.
        if (trim($primarytext) === '' && empty($inlinefiles)) {
            throw new \moodle_exception('error:empty_prompt', 'local_ai_quiz', '',
                'No readable content could be obtained from the primary documents.');
        }

        $prompt = $this->build_mcq_prompt($context, $numquestions, $difficultymix, $primaryonly,
            $multipleanswerconfig, !empty($inlinefiles));

        debugging('Prompt built, length: ' . strlen($prompt) . ' chars', DEBUG_DEVELOPER);
        debugging('Generating ' . $numquestions . ' MCQs...', DEBUG_DEVELOPER);

        $response = $this->call_gemini_api($prompt, $inlinefiles);

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
     * @param bool $hasinlinefiles True if the source files are attached for native reading
     * @return string Formatted prompt
     */
    private function build_mcq_prompt($context, $numquestions, $difficultymix, $primaryonly = false,
            $multipleanswerconfig = null, $hasinlinefiles = false) {
        $timestamp = date('c');

        // Where the model should be looking for source material.
        if ($hasinlinefiles) {
            $sourcelocation = 'The primary source material is the attached file(s). '
                . 'Read them directly. Any text below is supporting context only.';
        } else {
            $sourcelocation = 'The primary source material is the text under '
                . '"PRIMARY SOURCE MATERIALS" below.';
        }

        $scopeinstruction = <<<SCOPE

CRITICAL SCOPE RESTRICTION - READ THIS FIRST:
{$sourcelocation}

- Every question MUST be answerable using ONLY the primary source material.
- Every fact, number, name, definition and relationship you use MUST appear in
  the primary source material. Do NOT use your own knowledge of the subject to
  add, correct, complete or embellish anything.
- If the primary source material contradicts what you believe to be true, follow
  the source material. It is the authority, not your training data.
- Supporting materials and websites are background context ONLY. Never build a
  question from them.
- If you cannot find enough material in the source to write the requested number
  of questions, WRITE FEWER QUESTIONS. Returning 8 well-grounded questions is a
  success. Inventing 20 questions from general subject knowledge is a FAILURE and
  is worse than returning nothing.
- If the source material is unreadable, empty, or appears to be corrupted/binary
  data, return {"questions": [], "error": "unreadable source"} and nothing else.
  Do NOT attempt to infer the topic from a filename, title or metadata and write
  questions about that topic from memory.

SCOPE;

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
   - Distractors are plausible but clearly wrong to someone who studied the source
   - Distractors must also be drawn from the source material where possible
     (e.g. other terms, values or concepts that genuinely appear in it)
   - Questions are standalone and clear
   - Cover the ENTIRE PRIMARY content proportionally, not just one section
   - Use varied question stems

5. QUESTION WORDING RULES (these govern PHRASING ONLY):
   These rules are about how a question is worded. They do NOT relax the scope
   restriction above: the content still comes entirely from the source material.
   - Do not make the question refer to the act of reading a document
   - NEVER ask "According to the document/text/passage..."
   - NEVER ask "What does the author say/mention/state..."
   - NEVER ask "What is mentioned on page X..."
   - NEVER ask "What is the title/heading/section of..."
   - NEVER ask "As described in the material..."
   - Ask directly about the subject matter that the source material teaches
   - BAD:  "According to the RFC, what does the TTL field specify?"
   - GOOD: "What is the purpose of the TTL field in an IP packet header?"
   - BAD:  "What does the document say about fragmentation?"
   - GOOD: "Which condition triggers IP packet fragmentation?"
   In both GOOD examples the fact being tested still comes from the source. You
   are changing the wording, never the origin of the information.

6. MANDATORY EVIDENCE (source_quote):
   Every question MUST include a "source_quote" field containing a span of text
   copied EXACTLY, WORD FOR WORD, from the primary source material, which proves
   the correct answer.
   - Copy and paste it verbatim. Do not paraphrase, summarise, reformat, fix
     typos, or translate it.
   - It must be at least 8 words long.
   - It must be text that genuinely appears in the primary source material.
   - This quote is automatically checked against the source. Questions whose
     quote cannot be found in the source are flagged to the teacher as
     unreliable, so a fabricated quote will be detected.
   - If you cannot produce a genuine verbatim quote for a question, do not write
     that question at all.

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
      "explanation": "Why B is correct",
      "source_quote": "A span of at least 8 words copied word-for-word from the primary source material that proves the correct answer"
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
      "explanation": "Why A and C are correct",
      "source_quote": "A span of at least 8 words copied word-for-word from the primary source material that proves the correct answers"
    }
  ],
  "metadata": {
    "total_questions": "the number of questions you actually produced, which may be fewer than {$numquestions} if the source material did not support that many",
    "generated_at": "{$timestamp}"
  }
}

FINAL REMINDER: every question must come from the primary source material and
must carry a genuine verbatim source_quote. Fewer grounded questions is the
correct outcome when the source is thin. Never fill the gap from memory.
PROMPT;
    }

    /**
     * Call Gemini API
     *
     * @param string $prompt The prompt to send
     * @return array Decoded JSON response
     */
    private function call_gemini_api($prompt, $inlinefiles = []) {
        // Guard against empty prompt
        if (empty(trim($prompt))) {
            throw new \moodle_exception('error:empty_prompt', 'local_ai_quiz', '',
                'Prompt is empty - PDF text extraction likely returned no content. ' .
                'The PDF may be scanned/image-based (no selectable text). ' .
                'Please use a PDF with selectable text, or copy-paste text content directly.');
        }

        debugging("Prompt length: " . strlen($prompt) . " chars", DEBUG_DEVELOPER);

        // Strip control characters. This is legitimate cleanup: pdftotext emits
        // form feeds between pages. Tabs and newlines are preserved.
        $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $prompt);

        // Invalid UTF-8 is NOT cleanup - it means something upstream handed us
        // binary rather than text. Previously this was silently substituted and
        // sent anyway, which is how unreadable PDFs ended up producing questions
        // invented from the model's own knowledge. Refuse instead.
        if (!mb_check_encoding($prompt, 'UTF-8')) {
            throw new \moodle_exception('error:binary_content', 'local_ai_quiz', '',
                'Extracted content is not valid text (binary data detected). '
                . 'Refusing to generate questions from unreadable source material.');
        }

        $url = $this->apiendpoint . '?key=' . $this->apikey;

        // Attached source files are sent before the instructions so the model
        // treats them as the material to read.
        $parts = [];
        foreach ($inlinefiles as $file) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $file['mime'],
                    'data' => $file['data'],
                ]
            ];
        }
        $parts[] = ['text' => $prompt];

        // Low temperature: this is a grounded extraction task, not a creative one.
        // Higher values measurably increase drift away from the source material.
        $temperature = get_config('local_ai_quiz', 'temperature');
        if ($temperature === false || $temperature === '' || !is_numeric($temperature)) {
            $temperature = 0.2;
        }
        $temperature = max(0.0, min(1.0, (float)$temperature));

        $data = [
            'contents' => [
                ['parts' => $parts]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => 'application/json'
            ]
        ];

        $jsonpayload = json_encode($data);

        if ($jsonpayload === false) {
            throw new \moodle_exception('error:bad_api_request', 'local_ai_quiz', '',
                'Failed to encode request: ' . json_last_error_msg());
        }

        debugging("JSON payload size: " . strlen($jsonpayload) . " bytes", DEBUG_DEVELOPER);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonpayload);
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
     * Prepare one source document for the AI.
     *
     * Returns either extracted text or, when text extraction is impossible for a
     * PDF, the original file so the AI can read it natively. Never returns
     * approximated or scraped content.
     *
     * @param array $doc ['path' => string, 'pagerange' => array|null]
     * @return array ['type' => 'text'|'file', ...]
     * @throws \moodle_exception If the document cannot be used at all
     */
    private function prepare_document($doc) {
        $docpath = $doc['path'];
        $pagerange = $doc['pagerange'] ?? null;
        $ext = strtolower(pathinfo($docpath, PATHINFO_EXTENSION));
        $filename = basename($docpath);

        $rangestr = '';
        if ($pagerange && isset($pagerange['from']) && isset($pagerange['to'])) {
            $rangestr = " (pages {$pagerange['from']}-{$pagerange['to']})";
        }

        switch ($ext) {
            case 'pdf':
                try {
                    if ($pagerange) {
                        $content = pdf_extractor::extract_pages($docpath,
                            $pagerange['from'], $pagerange['to']);
                    } else {
                        $content = pdf_extractor::extract_pages($docpath);
                    }
                    return [
                        'type' => 'text',
                        'label' => $filename . $rangestr,
                        'content' => $content,
                    ];
                } catch (extraction_unavailable $e) {
                    // Text extraction is impossible here. Rather than guessing at
                    // the content, hand the original PDF to the AI to read.
                    return $this->prepare_native_pdf($docpath, $filename, $pagerange, $e->reason);
                }

            case 'docx':
                $content = $this->process_docx($docpath);
                if (!pdf_extractor::looks_like_text($content)) {
                    throw new \moodle_exception('error:no_usable_text', 'local_ai_quiz', '',
                        $filename . ' - no readable text could be extracted from this DOCX.');
                }
                return ['type' => 'text', 'label' => $filename, 'content' => $content];

            case 'pptx':
                $content = $this->process_pptx($docpath);
                if (!pdf_extractor::looks_like_text($content)) {
                    throw new \moodle_exception('error:no_usable_text', 'local_ai_quiz', '',
                        $filename . ' - no readable text could be extracted from this PPTX.');
                }
                return ['type' => 'text', 'label' => $filename, 'content' => $content];

            default:
                throw new \moodle_exception('error:unsupported_filetype', 'local_ai_quiz', '',
                    $filename . ' - unsupported file type ".' . $ext . '".');
        }
    }

    /**
     * Package a PDF for native reading by the AI provider.
     *
     * @param string $docpath Path to the PDF
     * @param string $filename Display name
     * @param array|null $pagerange Requested page range, if any
     * @param string $reason Why local extraction was not possible
     * @return array File part descriptor
     * @throws \moodle_exception If the file cannot be read
     */
    private function prepare_native_pdf($docpath, $filename, $pagerange, $reason) {
        $bytes = @file_get_contents($docpath);

        if ($bytes === false || $bytes === '') {
            throw new \moodle_exception('error:no_usable_text', 'local_ai_quiz', '',
                $filename . ' - the file could not be read.');
        }

        if ($reason === 'notoolchain') {
            $this->warnings[] = get_string('warning:nativepdf_notoolchain', 'local_ai_quiz', $filename);
        } else {
            $this->warnings[] = get_string('warning:nativepdf_notext', 'local_ai_quiz', $filename);
        }

        if ($pagerange) {
            $this->warnings[] = get_string('warning:nativepdf_pagerange', 'local_ai_quiz', $filename);
        }

        return [
            'type' => 'file',
            'label' => $filename,
            'mime' => 'application/pdf',
            'data' => base64_encode($bytes),
            'bytes' => strlen($bytes),
            'pagerange' => $pagerange,
        ];
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
    public function create_quiz($primarydocs = null, $supportingdocs = null, $websiteurls = null,
            $numquestions = 20, $difficultymix = null, $multipleanswerconfig = null) {

        $this->warnings = [];

        $primaryblocks = [];     // Labelled blocks for the prompt.
        $primarytextparts = [];  // Raw content only, used to verify grounding.
        $supportingblocks = [];
        $inlinefiles = [];
        $primaryerrors = [];
        $inlinebytes = 0;

        // --- PRIMARY documents. Questions come from these, so any failure here
        // --- is fatal: generating from a subset silently produces a quiz that is
        // --- not from the teacher's material.
        foreach ((array)$primarydocs as $doc) {
            $filename = basename($doc['path']);

            try {
                $prepared = $this->prepare_document($doc);
            } catch (\Exception $e) {
                $primaryerrors[] = $filename . ': ' . $e->getMessage();
                debugging("Primary doc failed: {$filename} - " . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }

            if ($prepared['type'] === 'text') {
                $primaryblocks[] = "=== PRIMARY DOCUMENT: {$prepared['label']} ===\n{$prepared['content']}\n";
                $primarytextparts[] = $prepared['content'];
                continue;
            }

            // Native file attachment.
            if ($inlinebytes + $prepared['bytes'] > self::MAX_INLINE_BYTES) {
                $primaryerrors[] = $filename . ': ' .
                    get_string('error:inline_too_large', 'local_ai_quiz');
                continue;
            }
            $inlinebytes += $prepared['bytes'];
            $inlinefiles[] = ['mime' => $prepared['mime'], 'data' => $prepared['data']];

            $block = "=== PRIMARY DOCUMENT: {$prepared['label']} "
                . "(attached to this request - read the attached file directly) ===";
            if (!empty($prepared['pagerange'])) {
                $block .= "\nUse ONLY pages {$prepared['pagerange']['from']}-"
                    . "{$prepared['pagerange']['to']} of this attached file. Ignore all other pages.";
            }
            $primaryblocks[] = $block . "\n";
        }

        // Fail loudly rather than generating from whatever happened to work.
        if (!empty($primaryerrors)) {
            throw new \moodle_exception('error:primary_doc_failed', 'local_ai_quiz', '',
                implode(' | ', $primaryerrors));
        }

        if (empty($primaryblocks)) {
            throw new \moodle_exception('error:no_primary_docs', 'local_ai_quiz');
        }

        // --- SUPPORTING documents. Context only, so failures are non-fatal but
        // --- must still be reported rather than silently swallowed.
        foreach ((array)$supportingdocs as $doc) {
            $filename = basename($doc['path']);

            try {
                $prepared = $this->prepare_document($doc);
            } catch (\Exception $e) {
                $this->warnings[] = get_string('warning:supporting_skipped', 'local_ai_quiz',
                    (object)['file' => $filename, 'reason' => $e->getMessage()]);
                debugging("Supporting doc skipped: {$filename} - " . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }

            if ($prepared['type'] === 'text') {
                $supportingblocks[] = "=== SUPPORTING DOCUMENT: {$prepared['label']} ===\n{$prepared['content']}\n";
            } else {
                // Don't spend the inline budget on background material.
                $this->warnings[] = get_string('warning:supporting_skipped', 'local_ai_quiz',
                    (object)['file' => $filename, 'reason' => get_string('error:no_usable_text', 'local_ai_quiz')]);
            }
        }

        // --- Websites (supporting material only).
        foreach ((array)$websiteurls as $url) {
            try {
                $webcontent = $this->process_website($url);
                $supportingblocks[] = "=== SUPPORTING WEBSITE: {$url} ===\n{$webcontent}\n";
            } catch (\Exception $e) {
                $this->warnings[] = get_string('warning:supporting_skipped', 'local_ai_quiz',
                    (object)['file' => $url, 'reason' => $e->getMessage()]);
                debugging("Error processing {$url}: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Assemble context with a clear PRIMARY vs SUPPORTING distinction.
        $fullcontext = "PRIMARY SOURCE MATERIALS (questions must come from these):\n\n";
        $fullcontext .= implode("\n\n", $primaryblocks);

        if (!empty($supportingblocks)) {
            $fullcontext .= "\n\n" . str_repeat("=", 80) . "\n\n";
            $fullcontext .= "SUPPORTING MATERIALS (background context only - never generate questions from these):\n\n";
            $fullcontext .= implode("\n\n", $supportingblocks);
        }

        $primarytext = implode("\n\n", $primarytextparts);

        debugging("Context assembled: ~" . str_word_count($fullcontext) . " words, "
            . count($inlinefiles) . " attached file(s)", DEBUG_DEVELOPER);

        // Set default difficulty mix if not provided.
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

        // Generate MCQs with primary document emphasis.
        $mcqs = $this->generate_mcqs([
            'context' => $fullcontext,
            'primarytext' => $primarytext,
            'files' => $inlinefiles,
        ], $numquestions, $difficultymix, true, $multipleanswerconfig);

        if (!is_array($mcqs)) {
            throw new \moodle_exception('error:invalid_api_response', 'local_ai_quiz');
        }

        // The model is instructed to report an unreadable source rather than
        // inventing questions. Honour that instead of importing nothing silently.
        if (!empty($mcqs['error'])) {
            throw new \moodle_exception('error:unreadable_source', 'local_ai_quiz', '',
                (string)$mcqs['error']);
        }

        if (empty($mcqs['questions']) || !is_array($mcqs['questions'])) {
            throw new \moodle_exception('error:no_questions_generated', 'local_ai_quiz');
        }

        // Add source information to metadata.
        $mcqs['metadata']['source_type'] = 'primary_documents';
        $mcqs['metadata']['primary_count'] = count($primaryblocks);
        $mcqs['metadata']['supporting_count'] = count($supportingblocks);
        $mcqs['metadata']['requested_questions'] = $numquestions;

        // Verify each question is actually grounded in the source material.
        $mcqs = grounding_validator::annotate($mcqs, $primarytext, !empty($inlinefiles));

        $summary = $mcqs['metadata']['grounding_summary'];
        debugging("Grounding: {$summary['verified']} verified, {$summary['ungrounded']} ungrounded, "
            . "{$summary['unverifiable']} unverifiable, {$summary['noquote']} without a quote",
            DEBUG_DEVELOPER);

        if ($summary['ungrounded'] > 0 || $summary['noquote'] > 0) {
            $this->warnings[] = get_string('warning:ungrounded_questions', 'local_ai_quiz',
                (object)['count' => $summary['ungrounded'] + $summary['noquote'], 'total' => $summary['total']]);
        }

        if (count($mcqs['questions']) < $numquestions) {
            $this->warnings[] = get_string('warning:fewer_questions', 'local_ai_quiz',
                (object)['got' => count($mcqs['questions']), 'asked' => $numquestions]);
        }

        // Structural validation.
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
