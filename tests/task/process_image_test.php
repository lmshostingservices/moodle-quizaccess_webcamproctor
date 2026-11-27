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

namespace quizaccess_webcamproctor\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../base_testcase.php');

/**
 * Tests for the image processing and cleanup tasks.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quizaccess_webcamproctor\task\process_image
 * @covers    \quizaccess_webcamproctor\task\cleanup_images
 */
final class process_image_test extends \quizaccess_webcamproctor\base_testcase {
    /**
     * Create an attempt with one pending snapshot and return the task for it.
     *
     * @return array [proctor record, image id, task]
     */
    private function attempt_with_snapshot(): array {
        global $DB;
        $attempt = $this->start_attempt($this->student);
        $record = $this->add_baseline($attempt);
        $imageid = $DB->insert_record('quizaccess_webcamproctor_images', (object) [
            'attemptid' => $record->id, 'imagetype' => 'snapshot', 'imagedata' => $this->jpeg(),
            'processstatus' => 'pending', 'facedetected' => 1, 'timecreated' => time(),
        ]);
        $task = new process_image();
        $task->set_custom_data(['proctorattemptid' => $record->id, 'imageid' => $imageid]);
        return [$record, $imageid, $task];
    }

    public function test_no_service_leaves_image_unverified_and_does_not_flag(): void {
        global $DB;
        $this->create_proctored_quiz(['webcamproctor_blocksubmission' => 1]);
        [$record, $imageid, $task] = $this->attempt_with_snapshot();
        $sink = $this->redirectMessages();
        $this->expectOutputRegex('/unverified/');
        $task->execute();

        $this->assertEquals('unverified', $DB->get_field('quizaccess_webcamproctor_images', 'processstatus', ['id' => $imageid]));
        $record = $DB->get_record('quizaccess_webcamproctor_attempts', ['id' => $record->id]);
        $this->assertEquals('pending', $record->status);
        $this->assertEquals(0, $record->submissionblocked);
        $this->assertEquals(0, $sink->count());
    }

    public function test_paused_does_not_call_service(): void {
        global $DB;
        $this->create_proctored_quiz();
        set_config('apiurl', 'https://facecompare.invalid', 'quizaccess_webcamproctor');
        set_config('apikey', 'secret', 'quizaccess_webcamproctor');
        set_config('paused', 1, 'quizaccess_webcamproctor');
        [, $imageid, $task] = $this->attempt_with_snapshot();
        $this->expectOutputRegex('/paused/');
        $task->execute();
        $this->assertEquals('unverified', $DB->get_field('quizaccess_webcamproctor_images', 'processstatus', ['id' => $imageid]));
    }

    public function test_low_match_flags_holds_and_notifies(): void {
        global $DB;
        $this->create_proctored_quiz(['webcamproctor_blocksubmission' => 1, 'webcamproctor_notifyteacher' => 1]);
        set_config('apiurl', 'https://facecompare.example', 'quizaccess_webcamproctor');
        set_config('apikey', 'secret', 'quizaccess_webcamproctor');
        [$record, $imageid, $task] = $this->attempt_with_snapshot();
        \curl::mock_response(json_encode(['confidence' => 40, 'face_detected' => true, 'model' => 'test-model']));
        $sink = $this->redirectMessages();
        $this->expectOutputRegex('/confidence 40%/');
        $task->execute();

        $record = $DB->get_record('quizaccess_webcamproctor_attempts', ['id' => $record->id]);
        $this->assertEquals('flagged', $record->status);
        $this->assertEquals(1, $record->submissionblocked);
        $this->assertEquals(40, $record->lowestconfidence);
        $result = json_decode($DB->get_field('quizaccess_webcamproctor_images', 'processresult', ['id' => $imageid]));
        $this->assertEquals('test-model', $result->model);
        $this->assertGreaterThanOrEqual(1, $sink->count());
        $this->assertStringNotContainsString('<script', $sink->get_messages()[0]->fullmessagehtml);
    }

    public function test_cleanup_deletes_old_images_only(): void {
        global $DB;
        $this->create_proctored_quiz();
        [$record, $imageid] = $this->attempt_with_snapshot();
        $DB->set_field('quizaccess_webcamproctor_images', 'timecreated', time() - 100 * DAYSECS, ['id' => $imageid]);
        set_config('retentiondays', 90, 'quizaccess_webcamproctor');
        $this->expectOutputRegex('/Deleted 1 webcam images/');
        (new cleanup_images())->execute();
        $this->assertFalse($DB->record_exists('quizaccess_webcamproctor_images', ['id' => $imageid]));
        $this->assertEquals(1, $DB->count_records('quizaccess_webcamproctor_images', ['attemptid' => $record->id]));
        $this->assertTrue($DB->record_exists('quizaccess_webcamproctor_attempts', ['id' => $record->id]));
    }
}
