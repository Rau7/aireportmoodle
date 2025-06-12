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
 * Prompt form class for AI Report
 *
 * @package    local_aireport
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aireport\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * Form for entering prompts to generate SQL reports
 */
class prompt_form extends \moodleform {
    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        
        $mform->addElement('textarea', 'prompt', get_string('promptlabel', 'local_aireport'), 
                           ['rows' => 5, 'cols' => 60]);
        $mform->setType('prompt', PARAM_TEXT);
        $mform->addRule('prompt', null, 'required', null, 'client');
        
        $this->add_action_buttons(false, get_string('submitprompt', 'local_aireport'));
    }
}
