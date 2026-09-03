<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Report whether this server can extract text from PDFs locally.
 *
 * Usage:
 *   sudo -u www-data php local/ai_quiz/cli/diagnose.php
 *   sudo -u www-data php local/ai_quiz/cli/diagnose.php --file=/path/to/lecture.pdf
 *
 * Run it as the *web server user*, not as root: exec() and PATH frequently
 * differ between the two, and the web server user is what actually matters.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'file' => ''],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Check whether local/ai_quiz can read PDF text on this server.\n");
    cli_writeln("Options:");
    cli_writeln("  --file=PATH   Also try extracting text from a specific PDF");
    cli_writeln("  -h, --help    Print this help\n");
    exit(0);
}

cli_heading('AI Quiz Generator - PDF extraction diagnostics');

$problems = [];

// 1. Is exec() usable at all?
$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$execexists = function_exists('exec');
$execdisabled = in_array('exec', $disabled, true);

cli_writeln('PHP version:            ' . PHP_VERSION);
cli_writeln('exec() defined:         ' . ($execexists ? 'yes' : 'NO'));
cli_writeln('exec() in disable_functions: ' . ($execdisabled ? 'YES - blocked' : 'no'));

if (!$execexists || $execdisabled) {
    $problems[] = 'exec() is unavailable, so pdftotext cannot be run. '
        . 'Remove "exec" from disable_functions in php.ini, or accept that every PDF '
        . 'will be uploaded to the AI provider to be read directly.';
}

// 2. Can we find pdftotext?
$available = \local_ai_quiz\pdf_extractor::is_text_extraction_available();
cli_writeln('pdftotext usable:       ' . ($available ? 'yes' : 'NO'));

if (!$available && $execexists && !$execdisabled) {
    $problems[] = 'pdftotext was not found. Install it with: sudo apt-get install poppler-utils '
        . '(Debian/Ubuntu) or sudo dnf install poppler-utils (RHEL/Fedora).';
}

// 3. Settings that affect grounding.
$temperature = get_config('local_ai_quiz', 'temperature');
cli_writeln('temperature setting:    ' . ($temperature === false || $temperature === ''
    ? '(unset, using 0.2)' : $temperature));
if (is_numeric($temperature) && (float)$temperature > 0.4) {
    $problems[] = 'Temperature is ' . $temperature . '. Values above ~0.4 make the AI more '
        . 'likely to drift away from the source document. 0.2 is recommended.';
}

$apikey = get_config('local_ai_quiz', 'gemini_api_key');
cli_writeln('Gemini API key:         ' . (empty($apikey) ? 'NOT SET' : 'set'));
if (empty($apikey)) {
    $problems[] = 'No Gemini API key is configured.';
}

// 4. Optionally try a real file.
if (!empty($options['file'])) {
    cli_writeln('');
    cli_heading('Extraction test: ' . $options['file']);

    if (!file_exists($options['file'])) {
        cli_writeln('File not found.');
        $problems[] = 'The file passed to --file does not exist.';
    } else {
        try {
            $text = \local_ai_quiz\pdf_extractor::extract_pages($options['file']);
            $words = str_word_count($text);
            cli_writeln('Extracted OK: ' . strlen($text) . " bytes, {$words} words.");
            cli_writeln('');
            cli_writeln('First 300 characters:');
            cli_writeln('---');
            cli_writeln(substr($text, 0, 300));
            cli_writeln('---');
            cli_writeln('');
            cli_writeln('If the text above is readable, questions will be generated from it '
                . 'and checked against it automatically.');
        } catch (\local_ai_quiz\extraction_unavailable $e) {
            cli_writeln('No text could be extracted (' . $e->reason . ').');
            cli_writeln($e->debuginfo ?? '');
            cli_writeln('');
            cli_writeln('This PDF will be uploaded to the AI provider to be read directly. '
                . 'Questions from it CANNOT be automatically checked against the source.');
        } catch (\Exception $e) {
            cli_writeln('Extraction failed: ' . $e->getMessage());
            $problems[] = 'The test file could not be processed: ' . $e->getMessage();
        }
    }
}

cli_writeln('');
cli_heading('Summary');

if (empty($problems)) {
    cli_writeln('No problems found. PDFs will be extracted locally and every generated '
        . 'question will be automatically verified against the source document.');
    exit(0);
}

foreach ($problems as $i => $problem) {
    cli_writeln(($i + 1) . '. ' . $problem);
}

cli_writeln('');
cli_writeln('Until these are resolved the plugin still refuses to invent questions - it '
    . 'sends the PDF to the AI to read instead - but it cannot automatically verify that '
    . 'the questions came from your document.');

exit(1);
