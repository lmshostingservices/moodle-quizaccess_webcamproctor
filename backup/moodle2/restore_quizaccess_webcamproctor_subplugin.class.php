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

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/**
 * Restores the webcam proctoring settings of a quiz.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizaccess_webcamproctor_subplugin extends restore_mod_quiz_access_subplugin {
    /**
     * Paths to process for the quiz.
     *
     * @return restore_path_element[]
     */
    protected function define_quiz_subplugin_structure() {
        $path = $this->get_pathfor('/quizaccess_webcamproctor');
        return [new restore_path_element('quizaccess_webcamproctor', $path)];
    }

    /**
     * Restore the quiz settings.
     *
     * @param array $data The settings from the backup.
     */
    public function process_quizaccess_webcamproctor($data) {
        global $DB;
        $data = (object) $data;
        unset($data->id);
        $data->quizid = $this->get_new_parentid('quiz');
        $data->timemodified = time();
        if (!$DB->record_exists('quizaccess_webcamproctor', ['quizid' => $data->quizid])) {
            $DB->insert_record('quizaccess_webcamproctor', $data);
        }
    }
}
