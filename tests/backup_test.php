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

namespace quizaccess_webcamproctor;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_testcase.php');

/**
 * Backup and restore tests.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_quizaccess_webcamproctor_subplugin
 * @covers    \restore_quizaccess_webcamproctor_subplugin
 */
final class backup_test extends base_testcase {
    public function test_duplicate_keeps_settings(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->create_proctored_quiz(['webcamproctor_threshold' => 85, 'webcamproctor_blocksubmission' => 1,
            'webcamproctor_maxcaptures' => 12]);
        $this->setAdminUser();
        if (
            class_exists(\core_courseformat\local\cmactions::class) &&
                method_exists(\core_courseformat\local\cmactions::class, 'duplicate')
        ) {
            // Moodle 5.2 and later.
            $newcm = (new \core_courseformat\local\cmactions($this->course))->duplicate($this->cm->id);
        } else {
            $newcm = duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($this->cm->id));
        }
        $settings = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $newcm->instance], '*', MUST_EXIST);
        $this->assertEquals(1, $settings->enabled);
        $this->assertEquals(85, $settings->sensitivitythreshold);
        $this->assertEquals(1, $settings->blocksubmission);
        $this->assertEquals(12, $settings->maxcaptures);
    }
}
