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
 * Plugin version and other meta-data.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_webcamproctor';
$plugin->version   = 2026060400015;
$plugin->requires  = 2023042400;   // Moodle 4.2+
$plugin->supported  = [402, 500];  // Moodle 4.2 to 5.x
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.2.2'; // SAVEPOINT-BUMP v2.2.1: no-op savepoint marker for clean upgrade path. No DB schema changes.; // VERIFY-ID-INTEGRATION: Report now shows full Verify ID comparison (selfie, gov ID doc, scores, extracted name, doc type) alongside proctoring snapshots. Fixed wrong table name (local_verifyid_verifications → verifyid_attempts) and wrong column names (selfiedata → selfie, status approved → verified). Summary table has Verify ID status badge + score column. CSV export includes Verify ID Status, Score, Face Match, Name Match, Extracted Name, Document Type. No DB schema changes.
