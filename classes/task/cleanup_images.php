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

use quizaccess_webcamproctor\local\manager;

/**
 * Deletes webcam images older than the retention period set by the administrator.
 *
 * The proctoring records (status, flags, reviewer) are kept, so the review history survives.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_images extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcleanupimages', 'quizaccess_webcamproctor');
    }

    /**
     * Delete expired images.
     */
    public function execute(): void {
        global $DB;

        $days = manager::get_retention_days();
        if ($days <= 0) {
            mtrace('Image retention is off: nothing deleted.');
            return;
        }
        $cutoff = time() - $days * DAYSECS;

        foreach (['baselineimageid', 'flaggedsnapshotid'] as $field) {
            $DB->execute("UPDATE {quizaccess_webcamproctor_attempts}
                             SET {$field} = NULL
                           WHERE {$field} IN (SELECT id
                                                FROM {quizaccess_webcamproctor_images}
                                               WHERE timecreated < :cutoff)", ['cutoff' => $cutoff]);
        }
        $count = $DB->count_records_select('quizaccess_webcamproctor_images', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('quizaccess_webcamproctor_images', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        mtrace("Deleted {$count} webcam images older than {$days} days.");
    }
}
