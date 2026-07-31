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

/**
 * External services definition for webcam proctoring.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_webcamproctor_save_snapshot' => [
        'classname' => 'quizaccess_webcamproctor\external\save_snapshot',
        'methodname' => 'execute',
        'description' => 'Save a webcam snapshot during quiz attempt',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'quizaccess_webcamproctor_get_attempt_status' => [
        'classname' => 'quizaccess_webcamproctor\external\get_attempt_status',
        'methodname' => 'execute',
        'description' => 'Get proctoring status for a quiz attempt',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
