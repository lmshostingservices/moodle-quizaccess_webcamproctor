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

namespace quizaccess_webcamproctor\external;

use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../base_testcase.php');

/**
 * Tests for the snapshot and status web services.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quizaccess_webcamproctor\external\save_snapshot
 * @covers    \quizaccess_webcamproctor\external\get_attempt_status
 */
final class save_snapshot_test extends \quizaccess_webcamproctor\base_testcase {
    /**
     * Call save_snapshot as the current user.
     *
     * @param int $attemptid Quiz attempt id.
     * @param string $image Data URI.
     * @param string $event Event name.
     * @return array
     */
    private function save(int $attemptid, string $image, string $event = ''): array {
        $result = save_snapshot::execute($attemptid, $image, strlen($image), $event);
        return external_api::clean_returnvalue(save_snapshot::execute_returns(), $result);
    }

    public function test_save_snapshot(): void {
        global $DB;
        $this->create_proctored_quiz();
        $attempt = $this->start_attempt($this->student);
        $record = $this->add_baseline($attempt);

        // First snapshot right after the baseline is accepted.
        $result = $this->save($attempt->id, $this->jpeg());
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $DB->get_field('quizaccess_webcamproctor_attempts', 'snapshotcount', ['id' => $record->id]));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks('\\quizaccess_webcamproctor\\task\\process_image'));

        // Rate limited.
        $this->assertEquals('ratelimited', $this->save($attempt->id, $this->jpeg())['message']);
    }

    public function test_save_snapshot_rejects_bad_requests(): void {
        global $DB;
        $this->create_proctored_quiz(['webcamproctor_maxcaptures' => 5]);
        $attempt = $this->start_attempt($this->student);
        $record = $this->add_baseline($attempt);
        $other = $this->start_attempt($this->student2);
        $this->add_baseline($other);

        $this->setUser($this->student);
        $this->assertEquals('notfound', $this->save($other->id, $this->jpeg())['message']);
        $svg = 'data:image/svg+xml;base64,' . base64_encode(str_repeat('<svg/>', 400));
        $this->assertEquals('invalidimage', $this->save($attempt->id, $svg)['message']);

        $DB->set_field('quizaccess_webcamproctor_attempts', 'snapshotcount', 5, ['id' => $record->id]);
        $this->assertEquals('limitreached', $this->save($attempt->id, $this->jpeg())['message']);

        $DB->set_field('quiz_attempts', 'state', 'finished', ['id' => $attempt->id]);
        $this->assertEquals('attemptclosed', $this->save($attempt->id, $this->jpeg())['message']);
    }

    public function test_camera_lost_flags_without_image(): void {
        global $DB;
        $this->create_proctored_quiz();
        $attempt = $this->start_attempt($this->student);
        $record = $this->add_baseline($attempt);
        $result = $this->save($attempt->id, '', 'cameralost');
        $this->assertTrue($result['flagged']);
        $record = $DB->get_record('quizaccess_webcamproctor_attempts', ['id' => $record->id]);
        $this->assertEquals('flagged', $record->status);
        $this->assertEquals(1, $DB->count_records('quizaccess_webcamproctor_images', ['attemptid' => $record->id]));
    }

    public function test_get_attempt_status_permissions(): void {
        $this->create_proctored_quiz();
        $attempt = $this->start_attempt($this->student);
        $this->add_baseline($attempt);

        $this->setUser($this->student);
        $this->assertTrue(get_attempt_status::execute($attempt->id)['success']);
        $this->setUser($this->student2);
        $this->assertFalse(get_attempt_status::execute($attempt->id)['success']);
        $this->setUser($this->teacher);
        $this->assertTrue(get_attempt_status::execute($attempt->id)['success']);
    }
}
