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
 * AI Report settings.
 *
 * @package    local_aireport
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aireport_settings', 
        new lang_string('pluginname', 'local_aireport'));
    $ADMIN->add('localplugins', $settings);

    // Add OpenRouter API Key setting.
    $settings->add(new admin_setting_configtext(
        'local_aireport/openrouter_apikey',
        get_string('openrouter_apikey', 'local_aireport'),
        get_string('openrouter_apikey_desc', 'local_aireport'),
        '',
        PARAM_RAW
    ));

    // Add show link to only admins setting
    $settings->add(new admin_setting_configcheckbox(
        'local_aireport/showlink_adminonly',
        get_string('showlink_adminonly', 'local_aireport'),
        get_string('showlink_adminonly_desc', 'local_aireport'),
        1 // default checked
    ));
}
