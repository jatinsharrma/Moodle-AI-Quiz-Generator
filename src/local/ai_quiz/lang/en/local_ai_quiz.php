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
$string['error:bad_api_request'] = 'The request to the AI could not be built: {$a}';
$string['error:api_not_found'] = 'API endpoint not found: {$a}';
$string['error:json_decode_failed'] = 'Failed to decode API response';
$string['error:invalid_api_response'] = 'The AI returned a response that could not be understood. Technical detail: {$a}';
$string['error:prompt_blocked'] = 'The AI provider rejected the request before generating anything (reason: {$a}). This usually means the source document triggered a content filter.';
$string['error:no_candidates'] = 'The AI returned no result at all. Technical detail: {$a}';
$string['error:response_truncated'] = 'The AI ran out of output space before it finished writing the questions, so the response was cut off. Try generating fewer questions at a time (for example 10 instead of 20), then run it again for the rest. Technical detail: {$a}';
$string['error:response_blocked'] = 'The AI stopped generating because of a content filter (reason: {$a}). Try a different section of the document.';
$string['error:invalid_question_structure'] = 'The AI returned questions in the wrong structure: {$a}';

// Repair loop.
$string['warning:repaired'] = 'The AI\'s first reply was not usable, so it was asked to correct it. The questions below came from attempt {$a}. Review them as usual.';
$string['warning:reduced_questions'] = 'The response did not fit within the AI\'s output limit, so the request was retried asking for {$a} questions instead. Run the generator again to produce more.';
$string['warning:attempts_exhausted'] = 'Generation was attempted {$a} times and did not succeed.';
$string['warning:replaced_ungrounded'] = '{$a->replaced} of {$a->total} question(s) could not be traced back to your document and were automatically replaced with new questions drawn from the source. The replacements were checked against your document and against the other questions to avoid repetition.';
$string['warning:unreplaced_ungrounded'] = '{$a} question(s) could not be traced back to your document, and the AI could not produce grounded replacements for them. They are shown below marked "Not found in source" and are unselected by default - review them against your material before importing.';
$string['warning:dropped_malformed'] = '{$a->dropped} question(s) came back malformed after all retries and were discarded; the {$a->kept} valid question(s) were kept.';
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

// Source grounding errors.
$string['error:extraction_unavailable'] = 'Text could not be extracted from this document: {$a}';
$string['error:binary_content'] = 'The document content could not be read as text: {$a}';
$string['error:primary_doc_failed'] = 'Quiz generation was stopped because a primary document could not be read: {$a}<br><br>No questions were generated. Generating from only part of your material would produce a quiz that is not based on your document.';
$string['error:no_usable_text'] = 'No readable text could be extracted from this file.';
$string['error:unsupported_filetype'] = 'Unsupported file type: {$a}';
$string['error:inline_too_large'] = 'The file is too large to send to the AI for reading. Install poppler-utils on the server for local text extraction, or use a page range to reduce the size.';
$string['error:unreadable_source'] = 'The AI reported that the source material was unreadable, so no questions were generated: {$a}';
$string['error:no_questions_generated'] = 'No questions could be generated from the source material. This usually means the document did not contain enough readable content.';

// Source grounding warnings.
$string['generationwarnings'] = 'Please review before importing';
$string['warning:nativepdf_notoolchain'] = '<strong>{$a}</strong>: the server cannot extract PDF text locally (pdftotext is unavailable or exec() is disabled), so the PDF was sent to the AI to read directly. Questions cannot be automatically checked against the source. Ask your administrator to install poppler-utils to enable automatic checking.';
$string['warning:nativepdf_notext'] = '<strong>{$a}</strong>: this PDF has no selectable text (it is most likely scanned), so it was sent to the AI to read directly. Questions cannot be automatically checked against the source.';
$string['warning:nativepdf_pagerange'] = '<strong>{$a}</strong>: the page range was passed to the AI as an instruction rather than applied before sending, because the whole file had to be sent. The AI may not honour it exactly.';
$string['warning:supporting_skipped'] = 'Supporting material <strong>{$a->file}</strong> was skipped: {$a->reason}';
$string['warning:ungrounded_questions'] = '<strong>{$a->count} of {$a->total} questions could not be matched to your document.</strong> They are flagged below and are unselected by default. Check them carefully before importing - they may have been written from the AI\'s general knowledge rather than from your material.';
$string['warning:fewer_questions'] = 'The AI produced {$a->got} questions instead of the {$a->asked} requested, because the source material did not support more. This is expected behaviour and is preferable to inventing questions.';

// Grounding status shown in preview.
$string['grounding:verified'] = 'Verified in source';
$string['grounding:verified_help'] = 'A verbatim quote supporting this question was found in your document.';
$string['grounding:ungrounded'] = 'Not found in source';
$string['grounding:ungrounded_help'] = 'The supporting quote for this question could not be found in your document. It may have been written from general knowledge rather than from your material.';
$string['grounding:unverifiable'] = 'Not checked';
$string['grounding:unverifiable_help'] = 'This question could not be checked automatically because the source was read directly by the AI rather than extracted as text on the server.';
$string['grounding:noquote'] = 'No source quote';
$string['grounding:noquote_help'] = 'The AI did not supply a usable supporting quote for this question, so it could not be checked against your document.';
$string['sourcequote'] = 'Source quote';
$string['groundingsummary'] = '{$a->verified} of {$a->total} questions were verified against your document.';
$string['groundingsummary_unchecked'] = 'Questions could not be automatically checked against your document. Review them against your material before importing.';

// Privacy
$string['privacy:metadata'] = 'The AI Quiz Generator plugin does not store any personal data. Files uploaded for quiz generation are processed temporarily and deleted immediately after.';
