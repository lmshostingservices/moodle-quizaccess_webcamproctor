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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_webcamproctor\local\image_validator;
use stdClass;

/**
 * External API for saving webcam snapshots.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_snapshot extends external_api {
    /** @var int Minimum seconds between two snapshots of the same attempt. */
    const RATE_LIMIT = 10;

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The quiz attempt ID'),
            // pipeline-ignore: PARAM_RAW — data URI, checked by image_validator for type, encoding and size before storing.
            'imagedata' => new external_value(PARAM_RAW, 'JPEG, PNG or WebP image as a base64 data URI'),
            'imagesize' => new external_value(PARAM_INT, 'Image size in bytes', VALUE_DEFAULT, 0),
            'event' => new external_value(PARAM_ALPHA, 'Optional event instead of an image: cameralost', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save a webcam snapshot, or record that the camera was lost.
     *
     * @param int $attemptid The quiz attempt ID.
     * @param string $imagedata The image data URI.
     * @param int $imagesize Image size in bytes, as reported by the browser.
     * @param string $event Optional event name.
     * @return array Result with success status.
     */
    public static function execute(int $attemptid, string $imagedata, int $imagesize = 0, string $event = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'imagedata' => $imagedata,
            'imagesize' => $imagesize,
            'event' => $event,
        ]);

        $quizattempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']]);
        if (!$quizattempt) {
            return self::result(false, 'notfound');
        }
        $cm = get_coursemodule_from_instance('quiz', $quizattempt->quiz, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', [
            'attemptid' => $params['attemptid'],
            'userid' => $USER->id,
        ]);
        if (!$proctorattempt || (int) $quizattempt->userid !== (int) $USER->id) {
            return self::result(false, 'notfound');
        }
        if (!in_array($quizattempt->state, [\mod_quiz\quiz_attempt::IN_PROGRESS, \mod_quiz\quiz_attempt::OVERDUE])) {
            return self::result(false, 'attemptclosed');
        }

        if ($params['event'] === 'cameralost') {
            self::flag($proctorattempt, get_string('reason_camera_lost', 'quizaccess_webcamproctor'), null);
            return self::result(true, 'recorded', 0, true);
        }

        // Rate limit: one snapshot per attempt every RATE_LIMIT seconds. The baseline photo does not count.
        $recent = $DB->record_exists_select(
            'quizaccess_webcamproctor_images',
            "attemptid = :attemptid AND imagetype = 'snapshot' AND timecreated > :cutoff",
            ['attemptid' => $proctorattempt->id, 'cutoff' => time() - self::RATE_LIMIT]
        );
        if ($recent) {
            return self::result(false, 'ratelimited');
        }

        $settings = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $proctorattempt->quizid]);
        if (!$settings || !$settings->enabled) {
            return self::result(false, 'disabled');
        }
        if ($settings->maxcaptures > 0 && $proctorattempt->snapshotcount >= $settings->maxcaptures) {
            return self::result(false, 'limitreached');
        }

        $image = image_validator::clean($params['imagedata']);
        if ($image === null) {
            return self::result(false, 'invalidimage');
        }

        $imageid = $DB->insert_record('quizaccess_webcamproctor_images', (object) [
            'attemptid' => $proctorattempt->id,
            'imagetype' => 'snapshot',
            'imagedata' => $image,
            'imagesize' => strlen($image),
            'facedetected' => 1,
            'processstatus' => 'pending',
            'timecreated' => time(),
        ]);
        $DB->execute("UPDATE {quizaccess_webcamproctor_attempts}
                         SET snapshotcount = snapshotcount + 1, timemodified = :now
                       WHERE id = :id", ['now' => time(), 'id' => $proctorattempt->id]);

        $task = new \quizaccess_webcamproctor\task\process_image();
        $task->set_custom_data(['proctorattemptid' => $proctorattempt->id, 'imageid' => $imageid]);
        \core\task\manager::queue_adhoc_task($task);

        return self::result(true, 'saved', $imageid);
    }

    /**
     * Flag an attempt for teacher review without an image.
     *
     * @param stdClass $proctorattempt The proctoring attempt.
     * @param string $reason The reason shown to teachers.
     * @param int|null $imageid The related image, if any.
     */
    private static function flag(stdClass $proctorattempt, string $reason, ?int $imageid): void {
        global $DB;
        if (in_array($proctorattempt->status, ['flagged', 'blocked'])) {
            return;
        }
        $DB->update_record('quizaccess_webcamproctor_attempts', (object) [
            'id' => $proctorattempt->id,
            'status' => 'flagged',
            'flaggedreason' => $reason,
            'flaggedsnapshotid' => $imageid,
            'timemodified' => time(),
        ]);
    }

    /**
     * Build a result array.
     *
     * @param bool $success Whether the call succeeded.
     * @param string $code Short status code.
     * @param int $imageid The saved image id.
     * @param bool $flagged Whether the attempt was flagged.
     * @return array
     */
    private static function result(bool $success, string $code, int $imageid = 0, bool $flagged = false): array {
        return ['success' => $success, 'imageid' => $imageid, 'flagged' => $flagged, 'message' => $code];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'imageid' => new external_value(PARAM_INT, 'The saved image ID'),
            'flagged' => new external_value(PARAM_BOOL, 'Whether the attempt was flagged'),
            'message' => new external_value(PARAM_ALPHA, 'Status code'),
        ]);
    }
}
