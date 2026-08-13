<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_quizaccess_webcamproctor_upgrade($oldversion) {
    if ($oldversion < 2026060400) {
        upgrade_plugin_savepoint(true, 2026060400, 'quizaccess', 'webcamproctor');
    }
    return true;
}
