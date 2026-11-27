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

namespace quizaccess_webcamproctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use quizaccess_webcamproctor\local\image_validator;

/**
 * Privacy provider for quizaccess_webcamproctor.
 *
 * Students own their proctoring records and webcam photos. Teachers who reviewed an attempt are
 * recorded as the reviewer.
 *
 * @package    quizaccess_webcamproctor
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string SQL joining proctoring records to their quiz module context. */
    const CONTEXT_JOIN = "JOIN {course_modules} cm ON cm.instance = pa.quizid
                          JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                          JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel";

    /**
     * Describe the personal data this plugin stores and sends.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_webcamproctor_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'attemptid' => 'privacy:metadata:attempts:attemptid',
            'status' => 'privacy:metadata:attempts:status',
            'flaggedreason' => 'privacy:metadata:attempts:flaggedreason',
            'lowestconfidence' => 'privacy:metadata:attempts:lowestconfidence',
            'snapshotcount' => 'privacy:metadata:attempts:snapshotcount',
            'reviewedby' => 'privacy:metadata:attempts:reviewedby',
            'reviewedtime' => 'privacy:metadata:attempts:reviewedtime',
            'timecreated' => 'privacy:metadata:attempts:timecreated',
        ], 'privacy:metadata:attempts');

        $collection->add_database_table('quizaccess_webcamproctor_images', [
            'imagetype' => 'privacy:metadata:images:imagetype',
            'imagedata' => 'privacy:metadata:images:imagedata',
            'confidence' => 'privacy:metadata:images:confidence',
            'processstatus' => 'privacy:metadata:images:processstatus',
            'timecreated' => 'privacy:metadata:images:timecreated',
        ], 'privacy:metadata:images');

        $collection->add_external_location_link('facecompare', [
            'baseline' => 'privacy:metadata:facecompare:baseline',
            'snapshot' => 'privacy:metadata:facecompare:snapshot',
        ], 'privacy:metadata:facecompare');

        return $collection;
    }

    /**
     * Get the contexts holding data for a user, as student or reviewer.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {quizaccess_webcamproctor_attempts} pa
                  " . self::CONTEXT_JOIN . "
                 WHERE pa.userid = :userid OR pa.reviewedby = :reviewer";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid, 'reviewer' => $userid]);
        return $contextlist;
    }

    /**
     * Get the users with data in a context.
     *
     * @param userlist $userlist The userlist to fill.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT pa.userid, pa.reviewedby
                  FROM {quizaccess_webcamproctor_attempts} pa
                  " . self::CONTEXT_JOIN . "
                 WHERE ctx.id = :contextid";
        $params = ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id];
        $userlist->add_from_sql('userid', $sql, $params);
        $userlist->add_from_sql('reviewedby', $sql, $params);
    }

    /**
     * Export a user's proctoring records and photos.
     *
     * @param approved_contextlist $contextlist The approved contexts to export data for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $pluginname = get_string('pluginname', 'quizaccess_webcamproctor');

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $sql = "SELECT pa.*
                      FROM {quizaccess_webcamproctor_attempts} pa
                      " . self::CONTEXT_JOIN . "
                     WHERE ctx.id = :contextid AND (pa.userid = :userid OR pa.reviewedby = :reviewer)";
            $attempts = $DB->get_records_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id,
                'userid' => $userid, 'reviewer' => $userid]);

            foreach ($attempts as $attempt) {
                $subcontext = [$pluginname, get_string('privacy:attempt', 'quizaccess_webcamproctor', $attempt->attemptid)];
                if ((int) $attempt->userid !== (int) $userid) {
                    // The user reviewed someone else's attempt: export only the review.
                    writer::with_context($context)->export_data($subcontext, (object) [
                        'reviewedtime' => transform::datetime($attempt->reviewedtime),
                        'status' => $attempt->status,
                    ]);
                    continue;
                }

                $images = $DB->get_records('quizaccess_webcamproctor_images', ['attemptid' => $attempt->id], 'timecreated ASC');
                $imagedata = [];
                foreach ($images as $image) {
                    $filename = null;
                    $decoded = image_validator::decode((string) $image->imagedata);
                    if ($decoded) {
                        [$mimetype, $binary] = $decoded;
                        $extension = ['image/png' => 'png', 'image/webp' => 'webp'][$mimetype] ?? 'jpg';
                        $filename = $image->imagetype . '-' . $image->id . '.' . $extension;
                        writer::with_context($context)->export_custom_file($subcontext, $filename, $binary);
                    }
                    $imagedata[] = [
                        'type' => $image->imagetype,
                        'file' => $filename,
                        'timecreated' => transform::datetime($image->timecreated),
                        'confidence' => $image->confidence,
                        'processstatus' => $image->processstatus,
                    ];
                }

                writer::with_context($context)->export_data($subcontext, (object) [
                    'attemptid' => $attempt->attemptid,
                    'status' => $attempt->status,
                    'flaggedreason' => $attempt->flaggedreason,
                    'lowestconfidence' => $attempt->lowestconfidence,
                    'snapshotcount' => $attempt->snapshotcount,
                    'submissionheld' => transform::yesno($attempt->submissionblocked),
                    'reviewedtime' => $attempt->reviewedtime ? transform::datetime($attempt->reviewedtime) : null,
                    'timecreated' => transform::datetime($attempt->timecreated),
                    'images' => $imagedata,
                ]);
            }
        }
    }

    /**
     * Delete all proctoring data in a context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_module) {
            return;
        }
        self::delete_where($context, '', []);
    }

    /**
     * Delete a user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                self::delete_where($context, 'AND pa.userid = :userid', ['userid' => $userid]);
                self::forget_reviewer($context, [$userid]);
            }
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist The approved context and users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$context instanceof \context_module || empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        self::delete_where($context, "AND pa.userid $insql", $params);
        self::forget_reviewer($context, $userids);
    }

    /**
     * Delete proctoring records and their images in a context.
     *
     * @param \context_module $context The quiz context.
     * @param string $where Extra SQL condition on pa.
     * @param array $params Parameters for the condition.
     */
    protected static function delete_where(\context_module $context, string $where, array $params): void {
        global $DB;
        $sql = "SELECT pa.id
                  FROM {quizaccess_webcamproctor_attempts} pa
                  " . self::CONTEXT_JOIN . "
                 WHERE ctx.id = :contextid $where";
        $ids = $DB->get_fieldset_sql($sql, $params + ['contextlevel' => CONTEXT_MODULE, 'contextid' => $context->id]);
        if ($ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('quizaccess_webcamproctor_images', "attemptid $insql", $inparams);
            $DB->delete_records_select('quizaccess_webcamproctor_attempts', "id $insql", $inparams);
        }
    }

    /**
     * Remove users as reviewers of attempts in a context, keeping the attempts.
     *
     * @param \context_module $context The quiz context.
     * @param int[] $userids The reviewer user ids.
     */
    protected static function forget_reviewer(\context_module $context, array $userids): void {
        global $DB;
        $quizid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid]);
        if (!$quizid) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'r');
        $DB->set_field_select(
            'quizaccess_webcamproctor_attempts',
            'reviewedby',
            null,
            "quizid = :quizid AND reviewedby $insql",
            $params + ['quizid' => $quizid]
        );
    }
}
