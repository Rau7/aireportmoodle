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

// Sadece admin veya sistemde manager rolü erişebilsin
$cansee = has_capability('moodle/site:config', $context);
if (!$cansee) {
    global $USER, $DB;
    $managerrole = $DB->get_record('role', array('shortname' => 'manager'));
    if ($managerrole) {
        $roles = get_user_roles($context, $USER->id, true);
        foreach ($roles as $role) {
            if ($role->roleid == $managerrole->id) {
                $cansee = true;
                break;
            }
        }
    }
}
if (!$cansee) {
    throw new required_capability_exception($context, 'moodle/site:config', 'nopermissions', '');
}

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
    if (!empty($h->name)) {
        $label = $h->name;
    } else {
        $label = mb_strimwidth($h->prompt, 0, 60, '...');
    }
    $historyoptions[$h->id] = $label . ' [' . userdate($h->timecreated, '%d %b %Y %H:%M') . ']';
}
// Eğer Go ile geçmişten bir kayıt seçildiyse, prompt ve name değerlerini POST'a inject et
if (isset($_POST['historygo']) && !empty($_POST['historyprompt'])) {
    $historyid = intval($_POST['historyprompt']);
    $historyrec = $DB->get_record('local_aireport_history', array('id' => $historyid, 'userid' => $USER->id));
    if ($historyrec) {
        $_POST['prompt'] = $historyrec->prompt;
        $_POST['promptname'] = $historyrec->name;
    }
}
$mform = new local_aireport\form\prompt_form(null, array('historyoptions' => $historyoptions));
// Eğer geçmişten bir kayıt seçildiyse prompt ve name alanı otomatik dolsun
if (!empty($historyrec->prompt)) {
    $mform->set_data([
        'prompt' => $historyrec->prompt,
        'promptname' => $historyrec->name
    ]);
}
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
        $apiurl = 'https://api.openai.com/v1/chat/completions';
        
        // systemprompt yapısı değişmeden korunuyor
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
            'model' => 'gpt-4o',
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

            // Otomatik kayıt yok! Sadece ekrana yazdır.
        } else {
            $error = get_string('error_openrouter', 'local_aireport');
            if (isset($json->error)) {
                $error .= ' ' . s($json->error->message);
            }
        }
    }

    }
}

// Promptu Kaydet ile geldiyse sadece burada kaydet
if (isset($_POST['saveprompt']) && !empty($_POST['prompt']) && !empty($_POST['sqlresult'])) {
    global $DB, $USER;
    $promptname = trim($_POST['promptname'] ?? '');
    if ($promptname === '') {
        $error = get_string('promptname_empty', 'local_aireport');
    } else {
        $record = new stdClass();
        $record->userid = $USER->id;
        $record->name = $promptname;
        $record->prompt = $_POST['prompt'];
        $record->sqlquery = $_POST['sqlresult'];
        $record->timecreated = time();
        $record->timemodified = time();
        $DB->insert_record('local_aireport_history', $record);
        $success = get_string('prompt_saved', 'local_aireport');
    }
}
// Promptu Güncelle ile geldiyse burada güncelle
if (isset($_POST['updateprompt']) && !empty($_POST['promptid']) && !empty($_POST['prompt']) && !empty($_POST['sqlresult'])) {
    // DEBUG: POST verilerini göster
    echo '<div style="background:#f8f9fa;border:1px solid #ccc;padding:10px;margin:10px 0;border-radius:5px;">';
    echo '<strong>DEBUG: updateprompt POST</strong><pre>';
    print_r($_POST);
    echo '</pre></div>';

    global $DB, $USER;
    $promptname = trim($_POST['promptname'] ?? '');
    $promptid = intval($_POST['promptid']);
    if ($promptname === '') {
        $error = get_string('promptname_empty', 'local_aireport');
    } else {
        $record = $DB->get_record('local_aireport_history', array('id' => $promptid, 'userid' => $USER->id));
        if ($record) {
            $record->name = $promptname;
            $record->prompt = $_POST['prompt'];
            $record->sqlquery = $_POST['sqlresult'];
            $record->timemodified = time();
            $DB->update_record('local_aireport_history', $record);
            $success = get_string('prompt_updated', 'local_aireport');
        } else {
            $error = get_string('prompt_update_error', 'local_aireport');
        }
    }
}

// Output page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_aireport'));

if (!empty($success)) {
    echo '<div class="alert alert-success">'.s($success).'</div>';
}
if (!empty($error)) {
    echo '<div class="alert alert-danger">'.s($error).'</div>';
}
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
    //echo html_writer::tag('h3', get_string('resultlabel', 'local_aireport'));
    //echo html_writer::tag('pre', $sqlresult, ['class' => 'border p-3 bg-light']);
    // Promptu Kaydet butonu (tablo/sonuç altına)
    // Eğer geçmişten geldiyse güncelle formu göster
    if (!empty($sqlresult) && !empty($historyrec->id)) {
        // Güncel prompt değerini öncelikli olarak POST'tan al
        if (!empty($_POST['prompt'])) {
            $promptval = $_POST['prompt'];
        } else if (!empty($data->prompt)) {
            $promptval = $data->prompt;
        } else if (!empty($historyrec->prompt)) {
            $promptval = $historyrec->prompt;
        } else {
            $promptval = '';
        }
        $nameval = $historyrec->name ?? '';
        echo '<form method="post" style="margin-top:20px;">';
        echo '<input type="hidden" name="promptid" value="'.intval($historyrec->id).'">';
        echo '<input type="hidden" name="prompt" value="'.s($promptval).'">';
        echo '<input type="hidden" name="sqlresult" value="'.s($sqlresult).'">';
        echo '<div class="mb-3 row fitem" data-fieldtype="text">';
        echo '  <div class="col-md-3 col-form-label d-flex pb-0 pe-md-0">';
        echo '    <label for="promptname" class="mb-0 word-break" style="cursor: default;">'.get_string('promptname', 'local_aireport').'</label>';
        echo '  </div>';
        echo '  <div class="col-md-9 d-flex flex-wrap align-items-start felement">';
        echo '    <input type="text" id="promptname" name="promptname" value="'.s($nameval).'" placeholder="'.get_string('promptname', 'local_aireport').'" required class="form-control" style="max-width:340px;margin-right:10px;">';
        echo '    <div class="form-control-feedback invalid-feedback" id="id_error_promptname"></div>';
        echo '<button type="submit" name="updateprompt" class="btn btn-success updtbtn">'.get_string('updatepromptbtn', 'local_aireport').'</button>';
        echo '  </div>';
        echo '</div>';
        echo '</form>';
    } else if (!empty($sqlresult) && empty($_POST['saveprompt'])) {
        // Yeni prompt için kaydet formu
        $promptval = '';
        if (!empty($data->prompt)) {
            $promptval = $data->prompt;
        } else if (!empty($_POST['prompt'])) {
            $promptval = $_POST['prompt'];
        } else if (!empty($historyrec->prompt)) {
            $promptval = $historyrec->prompt;
        }
        echo '<form method="post" style="margin-top:20px;">';
        echo '<input type="hidden" name="prompt" value="'.s($promptval).'">';
        echo '<input type="hidden" name="sqlresult" value="'.s($sqlresult).'">';
        echo '<div class="mb-3 row fitem" data-fieldtype="text">';
        echo '  <div class="col-md-3 col-form-label d-flex pb-0 pe-md-0">';
        echo '    <label for="promptname" class="mb-0 word-break" style="cursor: default;">'.get_string('promptname', 'local_aireport').'</label>';
        echo '  </div>';
        echo '  <div class="col-md-9 d-flex flex-wrap align-items-start felement">';
        echo '    <input type="text" id="promptname" name="promptname" value="'.s($nameval).'" placeholder="'.get_string('promptname', 'local_aireport').'" required class="form-control" style="max-width:340px;margin-right:10px;">';
        echo '    <div class="form-control-feedback invalid-feedback" id="id_error_promptname"></div>';
        echo '<button type="submit" name="saveprompt" class="btn btn-success updtbtn">'.get_string('savepromptbtn', 'local_aireport').'</button>';
        echo '  </div>';
        echo '</div>';
        echo '</form>';
    }
    // Execute the SQL query and show results
    try {
        global $DB;
        // Sanitize the SQL query - only allow SELECT statements
        $sql = trim($sqlresult);
        
        if (stripos($sql, 'select') === 0) {
            

            $records = $DB->get_records_sql($sql, array());
            
            if (count($records) > 0) {
                // Modern DataTables görünümü için özel CSS
                
                // Başlık ve butonları aynı satıra al
                echo '<div class="dt-topbar">';
                echo '<div class="dt-title">'.get_string('queryresults', 'local_aireport').' (' . count($records) . ' records)</div>';
                // DataTables export butonları otomatik sağda çıkacak
                echo '</div>';
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
                echo '<link rel="stylesheet" href="/local/aireport/style/aireport_table.css">';
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

// Settings button (only for users who can config site) - page bottom
if (has_capability('moodle/site:config', $context)) {
    $settingsurl = new moodle_url('/admin/settings.php', array('section' => 'local_aireport_settings'));
    echo html_writer::div(
        html_writer::link(
            $settingsurl,
            '<i class="fa fa-cog"></i> ' . get_string('settings', 'admin'),
            array('class' => 'btn btn-primary mt-4', 'role' => 'button')
        ),
        'd-flex justify-content-end'
    );
}

echo $OUTPUT->footer();
