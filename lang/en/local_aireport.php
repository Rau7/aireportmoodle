<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for local_aireport
 *
 * @package    local_aireport
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Language strings for local_aireport
$string['pluginname'] = 'AI Report';
$string['openrouter_apikey'] = 'OpenRouter API Key';
$string['openrouter_apikey_desc'] = 'Enter your OpenRouter API key to enable AI-powered SQL generation.';
$string['promptlabel'] = 'Describe the report you want (in English or Turkish)';
$string['submitprompt'] = 'Generate SQL';
$string['resultlabel'] = 'Generated SQL:';
$string['queryresults'] = 'Query Results';
$string['norecords'] = 'No records found for this query';
$string['onlyselect'] = 'Only SELECT queries are allowed for security reasons';
$string['sqlerror'] = 'Error executing SQL:';
$string['error_apikey'] = 'OpenRouter API key is not configured.';
$string['error_openrouter'] = 'Error communicating with OpenRouter API.';
