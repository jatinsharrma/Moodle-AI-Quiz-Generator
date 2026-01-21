<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ai_quiz';
$plugin->version = 2026012012;  // YYYYMMDDXX - Fix Create Category button validation issue
$plugin->requires = 2022041900; // Moodle 4.0+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.5.3';
