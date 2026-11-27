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
 * Database upgrade steps for quizaccess_webcamproctor.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database schema.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_quizaccess_webcamproctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade from v1.x to v2.0.0.
    if ($oldversion < 2025121301) {
        $table = new xmldb_table('quizaccess_webcamproctor');
        $fields = [
            new xmldb_field('blocksubmission', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'sensitivitythreshold'),
            new xmldb_field('verifyidintegration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'blocksubmission'),
            new xmldb_field('notifyteacher', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'verifyidintegration'),
            new xmldb_field('notifyadmin', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notifyteacher'),
            new xmldb_field('ccemails', XMLDB_TYPE_TEXT, null, null, null, null, null, 'notifyadmin'),
            new xmldb_field('emailtemplate', XMLDB_TYPE_TEXT, null, null, null, null, null, 'ccemails'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('quizaccess_webcamproctor_attempts');
        $fields = [
            new xmldb_field('verifyidstatus', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'snapshotcount'),
            new xmldb_field('verifyidmatch', XMLDB_TYPE_INTEGER, '3', null, null, null, null, 'verifyidstatus'),
            new xmldb_field('submissionblocked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notificationsent'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('quizaccess_webcamproctor_images');
        $fields = [
            new xmldb_field('imagesize', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'imagedata'),
            new xmldb_field('facedetected', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'processresult'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2025121301, 'quizaccess', 'webcamproctor');
    }

    // Upgrade to v2.0.1: add the maxcaptures field.
    if ($oldversion < 2025121601) {
        $table = new xmldb_table('quizaccess_webcamproctor');
        $field = new xmldb_field('maxcaptures', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'emailtemplate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025121601, 'quizaccess', 'webcamproctor');
    }

    // Upgrade to v2.3.0: no schema change. New capabilities, settings and tasks are installed by core.
    if ($oldversion < 2026112700) {
        upgrade_plugin_savepoint(true, 2026112700, 'quizaccess', 'webcamproctor');
    }

    return true;
}
