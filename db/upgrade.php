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

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin database schema.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_quizaccess_webcamproctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Upgrade from v1.0.0 to v2.0.0.
    if ($oldversion < 2025121301) {
        // Add new fields to quizaccess_webcamproctor table.
        $table = new xmldb_table('quizaccess_webcamproctor');

        // blocksubmission field.
        $field = new xmldb_field('blocksubmission', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'sensitivitythreshold');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // verifyidintegration field.
        $field = new xmldb_field('verifyidintegration', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'blocksubmission');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // notifyteacher field.
        $field = new xmldb_field('notifyteacher', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '1', 'verifyidintegration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // notifyadmin field.
        $field = new xmldb_field('notifyadmin', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'notifyteacher');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ccemails field.
        $field = new xmldb_field('ccemails', XMLDB_TYPE_TEXT, null, null,
            null, null, null, 'notifyadmin');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // emailtemplate field.
        $field = new xmldb_field('emailtemplate', XMLDB_TYPE_TEXT, null, null,
            null, null, null, 'ccemails');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add new fields to quizaccess_webcamproctor_attempts table.
        $table = new xmldb_table('quizaccess_webcamproctor_attempts');

        // verifyidstatus field.
        $field = new xmldb_field('verifyidstatus', XMLDB_TYPE_CHAR, '20', null,
            null, null, null, 'snapshotcount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // verifyidmatch field.
        $field = new xmldb_field('verifyidmatch', XMLDB_TYPE_INTEGER, '3', null,
            null, null, null, 'verifyidstatus');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // submissionblocked field.
        $field = new xmldb_field('submissionblocked', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'notificationsent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add new fields to quizaccess_webcamproctor_images table.
        $table = new xmldb_table('quizaccess_webcamproctor_images');

        // imagesize field.
        $field = new xmldb_field('imagesize', XMLDB_TYPE_INTEGER, '10', null,
            null, null, null, 'imagedata');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // facedetected field.
        $field = new xmldb_field('facedetected', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '1', 'processresult');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025121301, 'quizaccess', 'webcamproctor');
    }

    // Upgrade to v2.0.1 - add maxcaptures field.
    if ($oldversion < 2025121601) {
        $table = new xmldb_table('quizaccess_webcamproctor');

        // maxcaptures field.
        $field = new xmldb_field('maxcaptures', XMLDB_TYPE_INTEGER, '5', null,
            XMLDB_NOTNULL, null, '0', 'emailtemplate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025121601, 'quizaccess', 'webcamproctor');
    }
    // v2.1.2: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200012) {
        upgrade_plugin_savepoint(true, 2026042200012, 'quizaccess', 'webcamproctor');
    }

    // v2.2.1: SAVEPOINT-BUMP — no-op marker for clean upgrade path. No DB schema changes.
    if ($oldversion < 2026060400014) {
        upgrade_plugin_savepoint(true, 2026060400014, 'quizaccess', 'webcamproctor');
    }

    if ($oldversion < 2026060400015) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026060400015, 'quizaccess', 'webcamproctor');
    }

    return true;
}