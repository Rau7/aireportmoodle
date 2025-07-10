<?php
function xmldb_local_aireport_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025061901) {
        // Add 'name' field to history table.
        $table = new xmldb_table('local_aireport_history');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025061901, 'local', 'aireport');
    }
    if ($oldversion < 2025071001) {
        // Varsayılan olarak sadece adminler görebilsin (1)
        set_config('showlink_adminonly', 1, 'local_aireport');
        upgrade_plugin_savepoint(true, 2025071001, 'local', 'aireport');
    }
    return true;
}
