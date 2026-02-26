<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Plugin
$string['pluginname'] = 'AI Quiz Generator';

// Capabilities
$string['ai_quiz:generate'] = 'Generate AI quiz questions';
$string['ai_quiz:manage'] = 'Manage AI quiz settings';

// Settings
$string['apikeys'] = 'AI Provider API Keys';
$string['apikeys_desc'] = 'Enter your API keys for the AI providers you want to use.';
$string['gemini_api_key'] = 'Google Gemini API Key';
$string['gemini_api_key_desc'] = 'Enter your Google Gemini API key. Get one at https://makersuite.google.com/app/apikey';
$string['openai_api_key'] = 'OpenAI API Key';
$string['openai_api_key_desc'] = 'Enter your OpenAI API key (Coming soon)';
$string['claude_api_key'] = 'Anthropic Claude API Key';
$string['claude_api_key_desc'] = 'Enter your Anthropic Claude API key (Coming soon)';
$string['default_provider'] = 'Default AI Provider';
$string['default_provider_desc'] = 'Select the default AI provider to use for quiz generation';
$string['default_questions'] = 'Default Number of Questions';
$string['default_questions_desc'] = 'Default number of questions to generate';
$string['temperature'] = 'Temperature';
$string['temperature_desc'] = 'AI temperature setting (0.0-1.0). Higher values make output more random.';

// Form
$string['generatequiz'] = 'Generate AI Quiz';
$string['generatequiz_help'] = 'Upload documents (PDF, DOCX, PPTX) or provide website URLs to generate quiz questions using AI.';
$string['quizsettings'] = 'Quiz Settings';

// Primary Documents
$string['primarydocuments'] = 'Primary Documents (Required)';
$string['primarydocuments_info'] = '<strong>Primary documents set the scope and boundary for quiz questions.</strong> All questions will be generated from these materials.';
$string['primarydocuments_upload'] = 'Upload Primary Documents';
$string['primarydocuments_help'] = 'Upload PDF, DOCX, or PPTX files that contain the main content for quiz generation. Questions will be based on these documents.';

// Supporting Documents
$string['supportingdocuments'] = 'Supporting Documents (Optional)';
$string['supportingdocuments_info'] = '<strong>Supporting documents provide additional context</strong> but questions will still come from primary documents. Use for reference materials, definitions, or background information.';
$string['supportingdocuments_upload'] = 'Upload Supporting Documents';
$string['supportingdocuments_help'] = 'Upload optional reference documents that provide context. Questions will NOT be generated from these - they are for AI context only.';

// Page Ranges
$string['pageranges'] = 'Page Ranges (Optional)';
$string['pageranges_help'] = 'Specify page ranges for PDF files. Format: filename.pdf: 5-20 (one per line). Example:<br>
lecture1.pdf: 10-25<br>
textbook.pdf: 100-150<br>
If not specified, entire document will be used.';

// Websites
$string['websites'] = 'Website URLs (Optional)';
$string['websites_supporting'] = 'Supporting Website URLs';
$string['websites_help'] = 'Enter website URLs (one per line) to extract content from. These are treated as supporting materials.';

// Quiz Settings
$string['numquestions'] = 'Number of Questions';
$string['difficulty_distribution'] = 'Difficulty Distribution';
$string['difficulty_distribution_help'] = 'Enter percentages for each difficulty level (must total 100%)';
$string['easy'] = 'Easy';
$string['medium'] = 'Medium';
$string['hard'] = 'Hard';
$string['generate'] = 'Generate Quiz';
$string['numeric'] = 'Must be a number';
$string['multiple_answer_count'] = 'Number of Multiple Answer Questions';
$string['multiple_answer_count_help'] = 'Specify how many questions should have multiple correct answers. These questions use negative marking to prevent "select all" exploitation. Cannot exceed total question count.';

// Results
$string['generationresults'] = 'Quiz Generation Results';
$string['questionsimported'] = '{$a} questions successfully imported to question bank';
$string['questionsfailed'] = '{$a} questions failed to import';

// Preview
$string['previewquestions'] = 'Preview Generated Questions';
$string['reviewinstructions'] = 'Review and Edit Questions';
$string['reviewinstructions_help'] = 'Review each question below. You can: (1) Edit question text by clicking on it, (2) Change the correct answer by selecting a different radio button, (3) Delete unwanted questions, (4) Select/deselect questions to import using checkboxes.';
$string['totalgenerated'] = 'Total questions generated: {$a}';
$string['selectall'] = 'Select All';
$string['deselectall'] = 'Deselect All';
$string['questionnum'] = 'Question {$a}';
$string['explanation'] = 'Explanation';
$string['deletequestion'] = 'Delete This Question';
$string['importselected'] = 'Import Selected Questions to Question Bank';
$string['questiondeleted'] = 'Question deleted successfully';
$string['error:noselection'] = 'Please select at least one question to import';

// Errors
$string['error:no_api_key'] = 'No API key configured. Please configure an API key in plugin settings.';
$string['error:noprimarydocs'] = 'Primary documents are required. Please upload at least one primary document.';
$string['error:noinput'] = 'Please provide at least one document or website URL';
$string['error:percentagemismatch'] = 'Difficulty percentages must total 100% (currently: {$a}%)';
$string['error:invalidpercentage'] = 'Each difficulty percentage must be between 0 and 100';
$string['error:invalidpagerange'] = 'Invalid page range format for {$a}. Use format: filename.pdf: 10-20';
$string['error:pdf_processing_failed'] = 'Failed to process PDF file';
$string['error:docx_processing_failed'] = 'Failed to process DOCX file';
$string['error:pptx_processing_failed'] = 'Failed to process PPTX file';
$string['error:website_fetch_failed'] = 'Failed to fetch website content';
$string['error:api_request_failed'] = 'API request failed: {$a}';
$string['error:quota_exceeded'] = 'Gemini API quota exceeded: {$a}';
$string['error:api_auth_failed'] = 'API authentication failed: {$a}';
$string['error:api_bad_request'] = 'Bad API request: {$a}';
$string['error:api_not_found'] = 'API endpoint not found: {$a}';
$string['error:json_decode_failed'] = 'Failed to decode API response';
$string['error:invalid_api_response'] = 'Invalid API response format';
$string['error:no_input'] = 'No input provided for quiz generation';
$string['error:no_primary_docs'] = 'No valid primary documents could be processed. Please check that your PDF files are not corrupted, encrypted, or password-protected. If you have a .txt file, upload it as .txt (not renamed to .pdf).';
$string['error:pdf_not_found'] = 'PDF file not found: {$a}';
$string['error:invalid_page_range'] = 'Invalid page range: {$a}';
$string['error:pdftotext_failed'] = 'PDF text extraction failed: {$a}';
$string['error:pdf_empty'] = 'PDF extraction returned empty content. The PDF is likely scanned/image-based with no selectable text. Please use a PDF where you can select and copy text.';
$string['error:empty_prompt'] = 'No text could be extracted from the uploaded documents. Please ensure your PDFs have selectable text (not scanned images).';
$string['error:pdf_extraction_failed'] = 'PDF extraction failed: {$a}';
$string['error:invalid_question_format'] = 'Invalid question format: {$a}';
$string['error:category_creation_failed'] = 'Failed to create question category';

// Privacy
$string['privacy:metadata'] = 'The AI Quiz Generator plugin does not store any personal data. Files uploaded for quiz generation are processed temporarily and deleted immediately after.';
