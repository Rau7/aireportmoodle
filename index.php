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
 * Main page for AI Report plugin
 *
 * @package    local_aireport
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Require login and check capabilities.
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Setup page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aireport/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_aireport'));
$PAGE->set_heading(get_string('pluginname', 'local_aireport'));
$PAGE->set_pagelayout('admin');

// Form include.
require_once($CFG->dirroot.'/local/aireport/classes/form/prompt_form.php');
require_once($CFG->libdir.'/tablelib.php');

// Initialize variables.
// Geçmiş promptları çek
$historyoptions = array('' => get_string('choosehistory', 'local_aireport'));
$historyprompts = $DB->get_records('local_aireport_history', array('userid' => $USER->id), 'timecreated DESC');
foreach ($historyprompts as $h) {
    $shortprompt = mb_strimwidth($h->prompt, 0, 60, '...');
    $historyoptions[$h->id] = $shortprompt . ' [' . userdate($h->timecreated, '%d %b %Y %H:%M') . ']';
}
$mform = new local_aireport\form\prompt_form(null, array('historyoptions' => $historyoptions));
$sqlresult = '';
$error = '';

// Process form submission.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/search.php'));
} else if ($data = $mform->get_data()) {
    global $DB, $USER;
    $historygo = isset($_POST['historygo']);
    $historyprompt = $_POST['historyprompt'] ?? '';
    $submitprompt = $_POST['submitbutton'] ?? '';
    // Go butonuna basıldıysa:
    if ($historygo && !empty($historyprompt)) {
        $historyid = $historyprompt;
        $historyrec = $DB->get_record('local_aireport_history', array('id' => $historyid, 'userid' => $USER->id));
        if ($historyrec && !empty($historyrec->sqlquery)) {
            $sqlresult = $historyrec->sqlquery;
        } else {
            $error = 'Selected history prompt not found.';
        }
    } else if (!empty($submitprompt) && !empty($data->prompt)) {
        // Get API key from config or use default for testing
        $apikey = get_config('local_aireport', 'openrouter_apikey');
    if (empty($apikey)) {
        // For testing purposes, use the provided API key
        $apikey = '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';
        // Uncomment this line when going to production
        // $error = get_string('error_apikey', 'local_aireport');
    }
    
    if (!empty($apikey)) {
        // Call OpenRouter API.
        $prompt = $data->prompt;
        $apiurl = 'https://openrouter.ai/api/v1/chat/completions';
        
        // Prepare system prompt to guide the AI.
        $systemprompt  = 'You are a strict SQL generator for Moodle reporting purposes. ';
        $systemprompt .= 'Your task is to: ';
        $systemprompt .= '- Only generate valid, safe MySQL SELECT statements compatible with Moodle\'s database structure. ';
        $systemprompt .= '- Never use DELETE, UPDATE, INSERT, DROP, ALTER or any unsafe operation. ';
        $systemprompt .= '- Use standard Moodle table names such as cbd_user, cbd_course, cbd_user_enrolments, cbd_enrol, cbd_logstore_standard_log, ';
        $systemprompt .= 'cbd_grade_items, cbd_grade_grades, cbd_role_assignments, cbd_context, cbd_role, etc. ';
        $systemprompt .= '- When referencing roles, prefer using \'shortname\' (like \'student\') instead of hardcoded role IDs. ';
        $systemprompt .= '- If the prompt is unclear, make reasonable assumptions but never explain them. ';
        $systemprompt .= '- Return ONLY the raw SQL query with NO markdown formatting, NO code blocks, NO backticks, NO comments, NO explanations. Just the pure SQL query.';
        
        $postdata = [
            'model' => 'meta-llama/llama-4-maverick:free',
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        
        // Make API request.
        $curl = new curl();
        $headers = [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json',
            'HTTP-Referer: https://moodle.org' // Good practice to identify your app
        ];
        
        $options = [
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30
        ];
        
        $response = $curl->post($apiurl, json_encode($postdata), $options);
        $json = json_decode($response);
        
        if (isset($json->choices[0]->message->content)) {
            // Clean markdown formatting from SQL response
            $content = $json->choices[0]->message->content;
            
            // Remove markdown code blocks (```sql, ```, sql, etc.)
            $content = preg_replace('/```sql\s*|```\s*|`{1,3}|sql\s*/', '', $content);
            
            // Remove any explanatory text before or after the SQL (try to extract just the SQL)
            if (stripos($content, 'SELECT') !== false) {
                // Find the position of SELECT
                $selectPos = stripos($content, 'SELECT');
                // Find the last semicolon or the end of the string
                $endPos = strrpos($content, ';');
                if ($endPos === false) {
                    $endPos = strlen($content);
                } else {
                    $endPos++; // Include the semicolon
                }
                
                // Extract just the SQL part
                $sqlresult = trim(substr($content, $selectPos, $endPos - $selectPos));
            } else {
                $sqlresult = trim($content);
            }

            // Başarılı prompt+sql'i history tablosuna kaydet
            $record = new stdClass();
            $record->userid = $USER->id;
            $record->prompt = $prompt;
            $record->sqlquery = $sqlresult;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('local_aireport_history', $record);
        } else {
            $error = get_string('error_openrouter', 'local_aireport');
            if (isset($json->error)) {
                $error .= ' ' . s($json->error->message);
            }
        }
    }

    }
}

// Output page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_aireport'));
// Select2 CDN ekle
// (jQuery zaten DataTables için eklenmişti)
echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';
$mform->display();
// Select2 başlatıcı kod
// (Sayfa sonunda çalışacak şekilde)
echo '<script>$(function() { if (typeof $ !== "undefined" && $("#historyprompt").length) { $("#historyprompt").select2({width: "resolve"}); } });</script>';

// Display results or error.
if (!empty($sqlresult)) {
    echo html_writer::tag('h3', get_string('resultlabel', 'local_aireport'));
    echo html_writer::tag('pre', $sqlresult, ['class' => 'border p-3 bg-light']);
    
    // Execute the SQL query and show results
    try {
        global $DB;
        // Sanitize the SQL query - only allow SELECT statements
        $sql = trim($sqlresult);
        
        if (stripos($sql, 'select') === 0) {
            

            $records = $DB->get_records_sql($sql, array());
            
            if (count($records) > 0) {
                echo html_writer::tag('h3', 'Query Results' . ' (' . count($records) . ' records)');
                
                // Start table
                $table = new html_table();
                
                // Convert stdClass to array
                $firstrecord = reset($records);
                $table->head = array_keys((array)$firstrecord);
                $table->data = [];
                
                // Add data rows
                foreach ($records as $record) {
                    $table->data[] = array_values((array)$record);
                }
                
                // Add DataTables id
                $table->id = 'aireport-table';
                echo html_writer::table($table);
                
                // DataTables ve Buttons CDN ile export özellikleri
                echo '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">';
                echo '<link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.1/css/buttons.dataTables.min.css">';
                echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
                echo '<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>';
                echo '<script src="https://cdn.datatables.net/buttons/3.0.1/js/dataTables.buttons.min.js"></script>';
                echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>';
                echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>';
                echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>';
                echo '<script src="https://cdn.datatables.net/buttons/3.0.1/js/buttons.html5.min.js"></script>';
                echo '<script src="https://cdn.datatables.net/buttons/3.0.1/js/buttons.print.min.js"></script>';
                echo '<script>$(document).ready(function() { $("#aireport-table").DataTable({ dom: "Bfrtip", buttons: ["copy", "csv", "excel", "pdf", "print"] }); });</script>';

            } else {
                echo $OUTPUT->notification(get_string('norecords', 'local_aireport'), 'info');
            }
        } else {
            echo $OUTPUT->notification(get_string('onlyselect', 'local_aireport'), 'warning');
        }
    } catch (Exception $e) {
        echo $OUTPUT->notification(get_string('sqlerror', 'local_aireport') . ' ' . $e->getMessage(), 'error');
    }
}

if (!empty($error)) {
    echo $OUTPUT->notification($error, 'notifyproblem');
}

echo $OUTPUT->footer();
