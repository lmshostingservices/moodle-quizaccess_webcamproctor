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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Backs up the webcam proctoring settings of a quiz.
 *
 * Only the quiz settings are included. Webcam photos are biometric data, so they are never copied
 * into backups.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizaccess_webcamproctor_subplugin extends backup_mod_quiz_access_subplugin {
    /**
     * Define the XML structure for the quiz settings.
     *
     * @return backup_subplugin_element
     */
    protected function define_quiz_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('quizaccess_webcamproctor', null, [
            'enabled', 'snapshotinterval', 'sensitivitythreshold', 'blocksubmission', 'verifyidintegration',
            'notifyteacher', 'notifyadmin', 'ccemails', 'emailtemplate', 'maxcaptures',
        ]);
        $subplugin->add_child($wrapper);
        $wrapper->add_child($settings);
        $settings->set_source_table('quizaccess_webcamproctor', ['quizid' => backup::VAR_ACTIVITYID]);
        return $subplugin;
    }
}
