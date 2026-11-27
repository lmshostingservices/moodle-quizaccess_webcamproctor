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

namespace quizaccess_webcamproctor\local;

use stdClass;

/**
 * Shared proctoring logic: baselines, site settings and attempt holds.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Session key holding baselines waiting for their attempt to be created. */
    const SESSION_KEY = 'quizaccess_webcamproctor_baselines';

    /** @var int Seconds a pending baseline stays valid. */
    const BASELINE_TTL = 1800;

    /**
     * Whether an administrator has paused AI face matching site-wide.
     *
     * @return bool
     */
    public static function is_paused(): bool {
        return (bool) get_config('quizaccess_webcamproctor', 'paused');
    }

    /**
     * The face-matching service URL and key, or null when none is configured.
     *
     * Falls back to the local_moodlesupport settings used by earlier releases.
     *
     * @return array|null [url, key]
     */
    public static function get_api(): ?array {
        $url = get_config('quizaccess_webcamproctor', 'apiurl');
        $key = get_config('quizaccess_webcamproctor', 'apikey');
        if (empty($url) || empty($key)) {
            $url = get_config('local_moodlesupport', 'apiurl');
            $key = get_config('local_moodlesupport', 'apikey');
        }
        if (empty($url) || empty($key)) {
            return null;
        }
        return [$url, $key];
    }

    /**
     * Name of the face-matching provider, as shown to students.
     *
     * @return string
     */
    public static function get_processor_name(): string {
        $name = trim((string) get_config('quizaccess_webcamproctor', 'processorname'));
        return $name !== '' ? $name : get_string('processor_default', 'quizaccess_webcamproctor');
    }

    /**
     * Number of days images are kept, 0 meaning they are kept until deleted by hand.
     *
     * @return int
     */
    public static function get_retention_days(): int {
        $days = get_config('quizaccess_webcamproctor', 'retentiondays');
        return $days === false ? 90 : (int) $days;
    }

    /**
     * Keep a validated baseline in the session until the attempt it belongs to exists.
     *
     * @param int $quizid The quiz id.
     * @param string $image The validated data URI.
     */
    public static function store_pending_baseline(int $quizid, string $image): void {
        global $SESSION;
        if (empty($SESSION->{self::SESSION_KEY}) || !is_array($SESSION->{self::SESSION_KEY})) {
            $SESSION->{self::SESSION_KEY} = [];
        }
        $SESSION->{self::SESSION_KEY}[$quizid] = ['image' => $image, 'time' => time()];
    }

    /**
     * Take the pending baseline for a quiz out of the session.
     *
     * @param int $quizid The quiz id.
     * @return string|null The data URI, or null if there is none or it has expired.
     */
    public static function take_pending_baseline(int $quizid): ?string {
        global $SESSION;
        if (empty($SESSION->{self::SESSION_KEY}[$quizid])) {
            return null;
        }
        $pending = $SESSION->{self::SESSION_KEY}[$quizid];
        unset($SESSION->{self::SESSION_KEY}[$quizid]);
        if ($pending['time'] < time() - self::BASELINE_TTL) {
            return null;
        }
        return $pending['image'];
    }

    /**
     * Get the proctoring record for a quiz attempt.
     *
     * @param int $attemptid The quiz_attempts id.
     * @return stdClass|null
     */
    public static function get_proctor_attempt(int $attemptid): ?stdClass {
        global $DB;
        $record = $DB->get_record('quizaccess_webcamproctor_attempts', ['attemptid' => $attemptid]);
        return $record ?: null;
    }

    /**
     * Whether this attempt already has a baseline photo.
     *
     * @param int $attemptid The quiz_attempts id.
     * @return bool
     */
    public static function has_baseline(int $attemptid): bool {
        $record = self::get_proctor_attempt($attemptid);
        return $record && !empty($record->baselineimageid);
    }

    /**
     * Save the pending baseline from the session against a quiz attempt owned by the current user.
     *
     * @param stdClass $quizattempt The quiz_attempts record.
     * @return bool True if a baseline was saved.
     */
    public static function attach_pending_baseline(stdClass $quizattempt): bool {
        global $DB, $USER;

        if ((int) $quizattempt->userid !== (int) $USER->id || self::has_baseline($quizattempt->id)) {
            return false;
        }
        $image = self::take_pending_baseline((int) $quizattempt->quiz);
        if ($image === null) {
            return false;
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $record = self::get_proctor_attempt($quizattempt->id);
        if (!$record) {
            $record = (object) [
                'quizid' => $quizattempt->quiz,
                'attemptid' => $quizattempt->id,
                'userid' => $quizattempt->userid,
                'status' => 'pending',
                'snapshotcount' => 0,
                'notificationsent' => 0,
                'submissionblocked' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('quizaccess_webcamproctor_attempts', $record);
        }
        $imageid = $DB->insert_record('quizaccess_webcamproctor_images', (object) [
            'attemptid' => $record->id,
            'imagetype' => 'baseline',
            'imagedata' => $image,
            'imagesize' => strlen($image),
            'processstatus' => 'processed',
            'facedetected' => 1,
            'timecreated' => $now,
        ]);
        $DB->update_record('quizaccess_webcamproctor_attempts', (object) [
            'id' => $record->id,
            'baselineimageid' => $imageid,
            'timemodified' => $now,
        ]);
        $transaction->allow_commit();
        return true;
    }

    /**
     * Whether the current user's open attempt at this quiz is on hold for teacher review.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function is_attempt_held(int $quizid, int $userid): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {quizaccess_webcamproctor_attempts} pa
                  JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                 WHERE pa.quizid = :quizid
                   AND pa.userid = :userid
                   AND pa.submissionblocked = 1
                   AND qa.state IN ('inprogress', 'overdue')";
        return $DB->record_exists_sql($sql, ['quizid' => $quizid, 'userid' => $userid]);
    }

    /**
     * Bootstrap 5 attribute prefix for the running Moodle version (5.0 and later use data-bs-*).
     *
     * @return string Either 'data-bs-' or 'data-'.
     */
    public static function bs_prefix(): string {
        global $CFG;
        return ($CFG->version >= 2025041400) ? 'data-bs-' : 'data-';
    }
}
