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

/**
 * External API for getting proctoring attempt status.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_attempt_status extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The quiz attempt ID'),
        ]);
    }

    /**
     * Get proctoring status for an attempt.
     *
     * @param int $attemptid The quiz attempt ID.
     * @return array Status information.
     */
    public static function execute(int $attemptid): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
        ]);

        // Get the proctoring attempt record.
        $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', [
            'attemptid' => $params['attemptid'],
        ]);

        if (!$proctorattempt) {
            return [
                'success' => false,
                'status' => 'unknown',
                'snapshotcount' => 0,
                'flagged' => false,
                'blocked' => false,
                'message' => 'Proctoring session not found',
            ];
        }

        // Check permissions - users can only see their own, teachers can see all.
        $quizattempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']]);
        if (!$quizattempt) {
            return [
                'success' => false,
                'status' => 'unknown',
                'snapshotcount' => 0,
                'flagged' => false,
                'blocked' => false,
                'message' => 'Quiz attempt not found',
            ];
        }

        $cm = get_coursemodule_from_instance('quiz', $quizattempt->quiz);
        if (!$cm) {
            return [
                'success' => false,
                'status' => 'unknown',
                'snapshotcount' => 0,
                'flagged' => false,
                'blocked' => false,
                'message' => 'Quiz module not found',
            ];
        }
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user has permission.
        $canview = has_capability('quizaccess/webcamproctor:viewreport', $context) ||
                   $proctorattempt->userid == $USER->id;

        if (!$canview) {
            return [
                'success' => false,
                'status' => 'unknown',
                'snapshotcount' => 0,
                'flagged' => false,
                'blocked' => false,
                'message' => 'Permission denied',
            ];
        }

        return [
            'success' => true,
            'status' => $proctorattempt->status,
            'snapshotcount' => (int) $proctorattempt->snapshotcount,
            'flagged' => ($proctorattempt->status === 'flagged'),
            'blocked' => ($proctorattempt->submissionblocked == 1),
            'message' => $proctorattempt->flaggedreason ?? '',
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
            'status' => new external_value(PARAM_TEXT, 'Proctoring status'),
            'snapshotcount' => new external_value(PARAM_INT, 'Number of snapshots'),
            'flagged' => new external_value(PARAM_BOOL, 'Whether flagged'),
            'blocked' => new external_value(PARAM_BOOL, 'Whether submission blocked'),
            'message' => new external_value(PARAM_TEXT, 'Status message or flag reason'),
        ]);
    }
}
