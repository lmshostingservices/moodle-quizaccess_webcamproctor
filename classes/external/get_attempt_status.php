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

        $params = self::validate_parameters(self::execute_parameters(), ['attemptid' => $attemptid]);
        $unknown = [
            'success' => false,
            'status' => 'unknown',
            'snapshotcount' => 0,
            'flagged' => false,
            'blocked' => false,
            'message' => '',
        ];

        $quizattempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']]);
        if (!$quizattempt) {
            return $unknown;
        }
        $cm = get_coursemodule_from_instance('quiz', $quizattempt->quiz, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Students can only see their own attempts; staff with the report capability can see all.
        if (
            (int) $quizattempt->userid !== (int) $USER->id &&
                !has_capability('quizaccess/webcamproctor:viewreport', $context)
        ) {
            return $unknown;
        }

        $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', ['attemptid' => $params['attemptid']]);
        if (!$proctorattempt) {
            return $unknown;
        }

        return [
            'success' => true,
            'status' => $proctorattempt->status,
            'snapshotcount' => (int) $proctorattempt->snapshotcount,
            'flagged' => ($proctorattempt->status === 'flagged'),
            'blocked' => ((int) $proctorattempt->submissionblocked === 1),
            'message' => (string) ($proctorattempt->flaggedreason ?? ''),
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
