<?php
// This file is part of local_aireport Moodle plugin

/**
 * Navigation extension for AI Report plugin
 *
 * @package    local_aireport
 */

function local_aireport_extend_navigation(global_navigation $navigation) {
    global $CFG;
    $showadminonly = get_config('local_aireport', 'showlink_adminonly');
    $cansee = true;
    if ($showadminonly) {
        $cansee = has_capability('moodle/site:config', \context_system::instance());
    }
    if (isloggedin() && !isguestuser() && $cansee) {
        // Add to custom menu items
        if (stripos($CFG->custommenuitems, "/local/aireport/") === false) {
            $nodes = explode("\n", $CFG->custommenuitems);
            $node = get_string('aireport', 'local_aireport');
            $node .= "|";
            $node .= "/local/aireport/index.php";
            array_push($nodes, $node);
            $CFG->custommenuitems = implode("\n", $nodes);
        }
    }
}
