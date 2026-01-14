<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ai_quiz', get_string('pluginname', 'local_ai_quiz'));

    // Header
    $settings->add(new admin_setting_heading(
        'local_ai_quiz/apikeys',
        get_string('apikeys', 'local_ai_quiz'),
        get_string('apikeys_desc', 'local_ai_quiz')
    ));

    // Gemini API Key
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_quiz/gemini_api_key',
        get_string('gemini_api_key', 'local_ai_quiz'),
        get_string('gemini_api_key_desc', 'local_ai_quiz'),
        ''
    ));

    // OpenAI API Key (for future use)
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_quiz/openai_api_key',
        get_string('openai_api_key', 'local_ai_quiz'),
        get_string('openai_api_key_desc', 'local_ai_quiz'),
        ''
    ));

    // Claude API Key (for future use)
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_quiz/claude_api_key',
        get_string('claude_api_key', 'local_ai_quiz'),
        get_string('claude_api_key_desc', 'local_ai_quiz'),
        ''
    ));

    // Default AI Provider
    $settings->add(new admin_setting_configselect(
        'local_ai_quiz/default_provider',
        get_string('default_provider', 'local_ai_quiz'),
        get_string('default_provider_desc', 'local_ai_quiz'),
        'gemini',
        [
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI (Coming Soon)',
            'claude' => 'Claude (Coming Soon)'
        ]
    ));

    // Advanced settings
    $settings->add(new admin_setting_heading(
        'local_ai_quiz/advanced',
        get_string('advanced'),
        ''
    ));

    // Default number of questions
    $settings->add(new admin_setting_configtext(
        'local_ai_quiz/default_questions',
        get_string('default_questions', 'local_ai_quiz'),
        get_string('default_questions_desc', 'local_ai_quiz'),
        20,
        PARAM_INT
    ));

    // Temperature setting
    $settings->add(new admin_setting_configtext(
        'local_ai_quiz/temperature',
        get_string('temperature', 'local_ai_quiz'),
        get_string('temperature_desc', 'local_ai_quiz'),
        '0.7',
        PARAM_FLOAT
    ));

    $ADMIN->add('localplugins', $settings);
}
