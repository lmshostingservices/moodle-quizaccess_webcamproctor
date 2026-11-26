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

namespace quizaccess_webcamproctor\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../base_testcase.php');

/**
 * Privacy provider tests.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quizaccess_webcamproctor\privacy\provider
 */
final class provider_test extends \quizaccess_webcamproctor\base_testcase {
    /** @var \context_module Quiz context. */
    private $context;
    /** @var \stdClass Student's proctoring record. */
    private $record;
    /** @var \stdClass Second student's proctoring record. */
    private $record2;

    /**
     * Two students with baselines; the teacher reviewed the first.
     */
    private function setup_data(): void {
        global $DB;
        $this->create_proctored_quiz();
        $this->context = \context_module::instance($this->cm->id);
        $this->record = $this->add_baseline($this->start_attempt($this->student));
        $this->record2 = $this->add_baseline($this->start_attempt($this->student2));
        $DB->update_record('quizaccess_webcamproctor_attempts', (object) ['id' => $this->record->id,
            'reviewedby' => $this->teacher->id, 'reviewedtime' => time(), 'status' => 'approved']);
    }

    public function test_contexts_and_users(): void {
        $this->setup_data();
        foreach ([$this->student, $this->teacher] as $user) {
            $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        }
        $userlist = new userlist($this->context, 'quizaccess_webcamproctor');
        provider::get_users_in_context($userlist);
        $expected = [$this->student->id, $this->student2->id, $this->teacher->id];
        sort($expected);
        $actual = $userlist->get_userids();
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function test_export_includes_photos(): void {
        $this->setup_data();
        provider::export_user_data(new approved_contextlist($this->student, 'quizaccess_webcamproctor', [$this->context->id]));
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        $subcontext = [get_string('pluginname', 'quizaccess_webcamproctor'),
            get_string('privacy:attempt', 'quizaccess_webcamproctor', $this->record->attemptid)];
        $data = $writer->get_data($subcontext);
        $this->assertEquals('approved', $data->status);
        $this->assertCount(1, $data->images);
        $this->assertNotEmpty($writer->get_custom_file($subcontext, $data->images[0]['file']));
    }

    public function test_delete_for_user_keeps_others_and_forgets_reviewer(): void {
        global $DB;
        $this->setup_data();
        provider::delete_data_for_user(new approved_contextlist($this->teacher, 'quizaccess_webcamproctor', [$this->context->id]));
        $this->assertNull($DB->get_field('quizaccess_webcamproctor_attempts', 'reviewedby', ['id' => $this->record->id]));

        provider::delete_data_for_user(new approved_contextlist($this->student, 'quizaccess_webcamproctor', [$this->context->id]));
        $this->assertFalse($DB->record_exists('quizaccess_webcamproctor_attempts', ['id' => $this->record->id]));
        $this->assertFalse($DB->record_exists('quizaccess_webcamproctor_images', ['attemptid' => $this->record->id]));
        $this->assertTrue($DB->record_exists('quizaccess_webcamproctor_attempts', ['id' => $this->record2->id]));
    }

    public function test_delete_for_users_and_context(): void {
        global $DB;
        $this->setup_data();
        provider::delete_data_for_users(new approved_userlist($this->context, 'quizaccess_webcamproctor', [$this->student2->id]));
        $this->assertEquals(1, $DB->count_records('quizaccess_webcamproctor_attempts'));
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('quizaccess_webcamproctor_attempts'));
        $this->assertEquals(0, $DB->count_records('quizaccess_webcamproctor_images'));
    }
}
