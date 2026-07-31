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
 * Privacy Subsystem implementation for quizaccess_webcamproctor.
 *
 * @package    quizaccess_webcamproctor
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_webcamproctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for quizaccess_webcamproctor.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about this plugin's data storage.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'quizaccess_webcamproctor_attempts',
            [
                'userid' => 'privacy:metadata:quizaccess_webcamproctor_attempts:userid',
                'timecreated' => 'privacy:metadata:quizaccess_webcamproctor_attempts:timecreated',
            ],
            'privacy:metadata:quizaccess_webcamproctor_attempts'
        );

        $collection->add_database_table(
            'quizaccess_webcamproctor_images',
            [
                'attemptid' => 'privacy:metadata:quizaccess_webcamproctor_images:attemptid',
                'imagedata' => 'privacy:metadata:quizaccess_webcamproctor_images:imagedata',
                'timecreated' => 'privacy:metadata:quizaccess_webcamproctor_images:timecreated',
                'confidence' => 'privacy:metadata:quizaccess_webcamproctor_images:confidence',
                'processstatus' => 'privacy:metadata:quizaccess_webcamproctor_images:processstatus',
            ],
            'privacy:metadata:quizaccess_webcamproctor_images'
        );

        $collection->add_external_location_link(
            'openai',
            [
                'webcamimage' => 'privacy:metadata:openai:webcamimage',
            ],
            'privacy:metadata:openai'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz_attempts} qa ON qa.quiz = cm.instance
                  JOIN {quizaccess_webcamproctor_attempts} pa ON pa.attemptid = qa.id
                 WHERE pa.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT pa.userid
                  FROM {quizaccess_webcamproctor_attempts} pa
                  JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                 WHERE cm.id = :cmid";

        $params = ['cmid' => $context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $sql = "SELECT pa.*, img.imagetype, img.confidence, img.processstatus, img.timecreated as imagetime
                      FROM {quizaccess_webcamproctor_attempts} pa
                      LEFT JOIN {quizaccess_webcamproctor_images} img ON img.attemptid = pa.id
                      JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                      JOIN {quiz} q ON q.id = qa.quiz
                      JOIN {course_modules} cm ON cm.instance = q.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                     WHERE cm.id = :cmid AND pa.userid = :userid";

            $params = [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ];

            $records = $DB->get_records_sql($sql, $params);

            if (!empty($records)) {
                $data = [];
                foreach ($records as $record) {
                    $data[] = [
                        'attemptid' => $record->attemptid,
                        'imagetype' => $record->imagetype ?? 'N/A',
                        'timecreated' => transform::datetime($record->imagetime ?? $record->timecreated),
                        'isbaseline' => ($record->imagetype === 'baseline') ? 'Yes' : 'No',
                        'matchscore' => $record->confidence ?? 'N/A',
                        'matchstatus' => $record->processstatus ?? $record->status,
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'quizaccess_webcamproctor')],
                    (object) ['snapshots' => $data]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT pa.id
                  FROM {quizaccess_webcamproctor_attempts} pa
                  JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                 WHERE cm.id = :cmid";

        $ids = $DB->get_fieldset_sql($sql, ['cmid' => $context->instanceid]);

        if (!empty($ids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('quizaccess_webcamproctor_images', "attemptid $insql", $inparams);
            $DB->delete_records_select('quizaccess_webcamproctor_attempts', "id $insql", $inparams);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user data to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $sql = "SELECT pa.id
                      FROM {quizaccess_webcamproctor_attempts} pa
                      JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                      JOIN {quiz} q ON q.id = qa.quiz
                      JOIN {course_modules} cm ON cm.instance = q.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                     WHERE cm.id = :cmid AND pa.userid = :userid";

            $params = [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ];

            $ids = $DB->get_fieldset_sql($sql, $params);

            if (!empty($ids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('quizaccess_webcamproctor_images', "attemptid $insql", $inparams);
                $DB->delete_records_select('quizaccess_webcamproctor_attempts', "id $insql", $inparams);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $sql = "SELECT pa.id
                  FROM {quizaccess_webcamproctor_attempts} pa
                  JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                 WHERE cm.id = :cmid AND pa.userid {$usersql}";

        $params = array_merge(['cmid' => $context->instanceid], $userparams);
        $ids = $DB->get_fieldset_sql($sql, $params);

        if (!empty($ids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('quizaccess_webcamproctor_images', "attemptid $insql", $inparams);
            $DB->delete_records_select('quizaccess_webcamproctor_attempts', "id $insql", $inparams);
        }
    }
}
