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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use stdClass;

/**
 * External API for saving webcam snapshots.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_snapshot extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The quiz attempt ID'),
            'imagedata' => new external_value(PARAM_RAW, 'Base64 encoded image data'),
            'imagesize' => new external_value(PARAM_INT, 'Image size in bytes', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save a webcam snapshot.
     *
     * @param int $attemptid The quiz attempt ID.
     * @param string $imagedata Base64 encoded image data.
     * @param int $imagesize Image size in bytes.
     * @return array Result with success status.
     */
    public static function execute(int $attemptid, string $imagedata, int $imagesize = 0): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'imagedata' => $imagedata,
            'imagesize' => $imagesize,
        ]);

        // Get the proctoring attempt record.
        $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', [
            'attemptid' => $params['attemptid'],
            'userid' => $USER->id,
        ]);

        if (!$proctorattempt) {
            return [
                'success' => false,
                'imageid' => 0,
                'flagged' => false,
                'message' => 'Proctoring session not found',
            ];
        }

        // Validate context.
        $quizattempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']]);
        if (!$quizattempt) {
            return [
                'success' => false,
                'imageid' => 0,
                'flagged' => false,
                'message' => 'Quiz attempt not found',
            ];
        }

        $cm = get_coursemodule_from_instance('quiz', $quizattempt->quiz);
        if (!$cm) {
            return [
                'success' => false,
                'imageid' => 0,
                'flagged' => false,
                'message' => 'Quiz module not found',
            ];
        }
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Rate limit: max 1 snapshot per 10 seconds per attempt.
        $recentimages = $DB->get_records_sql(
            'SELECT id FROM {quizaccess_webcamproctor_images}
             WHERE attemptid = :attemptid AND timecreated > :cutoff
             ORDER BY timecreated DESC',
            [
                'attemptid' => $proctorattempt->id,
                'cutoff' => time() - 10,
            ],
            0,
            1
        );
        $recentimage = !empty($recentimages);
        if ($recentimage) {
            return [
                'success' => false,
                'imageid' => 0,
                'flagged' => false,
                'message' => 'Rate limit: please wait before sending another snapshot',
            ];
        }

        // Enforce max captures if configured.
        $settings = $DB->get_record('quizaccess_webcamproctor',
            ['quizid' => $proctorattempt->quizid]);
        if ($settings && $settings->maxcaptures > 0
                && $proctorattempt->snapshotcount >= $settings->maxcaptures) {
            return [
                'success' => false,
                'imageid' => 0,
                'flagged' => false,
                'message' => 'Maximum snapshot limit reached',
            ];
        }

        // Validate base64 image data format.
        $rawimage = $params['imagedata'];
        if (!empty($rawimage) && strpos($rawimage, 'data:image') !== 0 && !preg_match('/^[A-Za-z0-9+\/=]+$/', substr($rawimage, 0, 100))) {
            return [
                'success' => false,
                'imageid' => 0,
                'flagged' => false,
                'message' => 'Invalid image data format',
            ];
        }

        // Enforce image size limit (500KB decoded).
        if (!empty($rawimage)) {
            $b64only = $rawimage;
            if (strpos($b64only, 'data:image') === 0) {
                $b64parts = explode(',', $b64only, 2);
                if (count($b64parts) === 2) {
                    $b64only = $b64parts[1];
                }
            }
            $estimatedsize = (int) (strlen($b64only) * 3 / 4);
            if ($estimatedsize > 512000) {
                return [
                    'success' => false,
                    'imageid' => 0,
                    'flagged' => false,
                    'message' => 'Image too large',
                ];
            }
        }

        // Detect face presence (basic check - actual matching done async).
        $facedetected = self::detect_face_presence($params['imagedata']);

        // Save the snapshot image.
        $image = new stdClass();
        $image->attemptid = $proctorattempt->id;
        $image->imagetype = 'snapshot';
        $image->imagedata = $params['imagedata'];
        $image->imagesize = $params['imagesize'];
        $image->facedetected = $facedetected ? 1 : 0;
        $image->processstatus = 'pending';
        $image->timecreated = time();
        $imageid = $DB->insert_record('quizaccess_webcamproctor_images', $image);

        // Update snapshot count atomically to avoid race conditions.
        $DB->execute(
            "UPDATE {quizaccess_webcamproctor_attempts}
                SET snapshotcount = snapshotcount + 1, timemodified = :now
              WHERE id = :id",
            ['now' => time(), 'id' => $proctorattempt->id]
        );

        // Check if flagged (no face detected).
        $flagged = false;
        if (!$facedetected) {
            $flagged = true;
            // Update attempt status if not already flagged.
            if ($proctorattempt->status !== 'flagged' && $proctorattempt->status !== 'blocked') {
                $DB->set_field('quizaccess_webcamproctor_attempts', 'status', 'flagged',
                    ['id' => $proctorattempt->id]);
                $DB->set_field('quizaccess_webcamproctor_attempts', 'flaggedreason',
                    'No face detected in snapshot', ['id' => $proctorattempt->id]);
                $DB->set_field('quizaccess_webcamproctor_attempts', 'flaggedsnapshotid',
                    $imageid, ['id' => $proctorattempt->id]);
            }
        }

        // Queue face matching task if enabled.
        self::queue_face_matching($proctorattempt->id, $imageid);

        return [
            'success' => true,
            'imageid' => $imageid,
            'flagged' => $flagged,
            'message' => $flagged ? 'No face detected' : 'Snapshot saved',
        ];
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
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }

    /**
     * Basic face detection check using image data analysis.
     *
     * @param string $imagedata Base64 image data.
     * @return bool Whether a face appears to be present.
     */
    private static function detect_face_presence(string $imagedata): bool {
        // Remove data URL prefix if present.
        if (strpos($imagedata, 'data:image') === 0) {
            $parts = explode(',', $imagedata, 2);
            if (count($parts) == 2) {
                $imagedata = $parts[1];
            }
        }

        // Decode and check image validity.
        $decoded = base64_decode($imagedata);
        if (!$decoded || strlen($decoded) < 1000) {
            return false;
        }

        // For now, assume face is present if image is valid.
        // Actual face detection will be done by GPT-4o Vision in async task.
        return true;
    }

    /**
     * Queue an adhoc task for face matching.
     *
     * @param int $proctorattemptid The proctoring attempt ID.
     * @param int $imageid The image ID to process.
     */
    private static function queue_face_matching(int $proctorattemptid, int $imageid): void {
        // Queue adhoc task for face matching.
        $task = new \quizaccess_webcamproctor\task\process_image();
        $task->set_custom_data([
            'proctorattemptid' => $proctorattemptid,
            'imageid' => $imageid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
