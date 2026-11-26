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
 * Adhoc task for processing webcam images with face matching.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_image extends \core\task\adhoc_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprocessimage', 'quizaccess_webcamproctor');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();

        if (!isset($data->proctorattemptid) || !isset($data->imageid)) {
            mtrace('Missing required data for process_image task');
            return;
        }

        // Get the proctoring attempt.
        $proctorattempt = $DB->get_record(
            'quizaccess_webcamproctor_attempts',
            ['id' => $data->proctorattemptid]
        );

        if (!$proctorattempt) {
            mtrace('Proctoring attempt not found: ' . $data->proctorattemptid);
            return;
        }

        // Get the image to process.
        $image = $DB->get_record('quizaccess_webcamproctor_images', ['id' => $data->imageid]);

        if (!$image) {
            mtrace('Image not found: ' . $data->imageid);
            return;
        }

        // Get the baseline image.
        $baseline = $DB->get_record(
            'quizaccess_webcamproctor_images',
            ['id' => $proctorattempt->baselineimageid]
        );

        if (!$baseline) {
            mtrace('Baseline image not found for attempt: ' . $data->proctorattemptid);
            $this->mark_image_error($image->id, 'No baseline image');
            return;
        }

        $settings = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $proctorattempt->quizid]);
        $threshold = $settings ? (int) $settings->sensitivitythreshold : 70;

        // Paused by an administrator, or no service configured: keep the image for people to
        // review, but do not send it anywhere and do not flag anyone.
        $api = manager::get_api();
        if (manager::is_paused() || $api === null) {
            $reason = manager::is_paused() ? 'paused' : 'noservice';
            $DB->update_record('quizaccess_webcamproctor_images', (object) [
                'id' => $image->id,
                'processstatus' => 'unverified',
                'processresult' => json_encode(['unverified' => $reason, 'time' => time()]),
            ]);
            mtrace('Image ' . $image->id . ' left unverified (' . $reason . ')');
            return;
        }

        $result = $this->match_faces($api, $baseline->imagedata, $image->imagedata, $threshold);
        if (!empty($result['error'])) {
            // A service failure is not evidence against the student: record it and move on.
            $this->mark_image_error($image->id, $result['error']);
            mtrace('Face matching failed for image ' . $image->id . ': ' . $result['error']);
            return;
        }

        $DB->update_record('quizaccess_webcamproctor_images', (object) [
            'id' => $image->id,
            'confidence' => $result['confidence'],
            'processstatus' => 'processed',
            'processresult' => json_encode($result),
            'facedetected' => $result['face_detected'] ? 1 : 0,
        ]);

        if ($proctorattempt->lowestconfidence === null || $result['confidence'] < $proctorattempt->lowestconfidence) {
            $DB->set_field(
                'quizaccess_webcamproctor_attempts',
                'lowestconfidence',
                $result['confidence'],
                ['id' => $proctorattempt->id]
            );
        }

        if (!$result['match'] || !$result['face_detected']) {
            $this->flag_attempt($proctorattempt, $image, $result, $settings);
        }

        mtrace('Processed image ' . $image->id . ' with confidence ' . $result['confidence'] . '%');
    }

    /**
     * Compare a snapshot with the baseline using the configured face-matching service.
     *
     * @param array $api [url, key] of the service.
     * @param string $baselinedata Baseline image data URI.
     * @param string $snapshotdata Snapshot image data URI.
     * @param int $threshold Match threshold percentage.
     * @return array Result with match, confidence, face_detected, error and service details.
     */
    private function match_faces(array $api, string $baselinedata, string $snapshotdata, int $threshold): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        [$apiurl, $apikey] = $api;
        $endpoint = rtrim($apiurl, '/') . '/api/face-compare';

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'X-API-Key: ' . $apikey]);
        $response = $curl->post($endpoint, json_encode([
            'baseline' => $baselinedata,
            'snapshot' => $snapshotdata,
            'threshold' => $threshold,
        ]), ['CURLOPT_TIMEOUT' => 30]);
        $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);

        if ($curl->get_errno()) {
            return ['error' => 'cURL error: ' . $curl->error];
        }
        if ($httpcode !== 200) {
            return ['error' => 'HTTP ' . $httpcode];
        }
        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['confidence']) || !is_numeric($data['confidence'])) {
            return ['error' => 'Invalid response'];
        }

        $confidence = max(0, min(100, (int) $data['confidence']));
        return [
            'match' => $confidence >= $threshold,
            'confidence' => $confidence,
            'face_detected' => (bool) ($data['face_detected'] ?? true),
            'threshold' => $threshold,
            'service' => parse_url($endpoint, PHP_URL_HOST),
            'model' => isset($data['model']) ? clean_param($data['model'], PARAM_TEXT) : null,
            'requestid' => isset($data['request_id']) ? clean_param($data['request_id'], PARAM_TEXT) : null,
            'time' => time(),
            'error' => null,
        ];
    }

    /**
     * Flag an attempt as suspicious.
     *
     * @param object $attempt The proctoring attempt.
     * @param object $image The triggering image.
     * @param array $result The match result.
     * @param object $settings Quiz proctoring settings.
     */
    private function flag_attempt($attempt, $image, array $result, $settings): void {
        global $DB;

        // Determine flag reason.
        if (!$result['face_detected']) {
            $reason = get_string('reason_no_face', 'quizaccess_webcamproctor');
        } else {
            $reason = get_string('reason_low_confidence_value', 'quizaccess_webcamproctor', $result['confidence']);
        }

        // Update attempt status.
        $update = new \stdClass();
        $update->id = $attempt->id;
        $update->status = 'flagged';
        $update->flaggedreason = $reason;
        $update->flaggedsnapshotid = $image->id;
        $update->timemodified = time();
        $DB->update_record('quizaccess_webcamproctor_attempts', $update);

        // Check if we should block submission.
        if ($settings && $settings->blocksubmission) {
            $DB->set_field(
                'quizaccess_webcamproctor_attempts',
                'submissionblocked',
                1,
                ['id' => $attempt->id]
            );
        }

        // Send notification if not already sent.
        if (!$attempt->notificationsent) {
            $this->send_notification($attempt, $result, $settings);
        }
    }

    /**
     * Send notification about flagged attempt.
     *
     * @param object $attempt The proctoring attempt.
     * @param array $result The match result.
     * @param object $settings Quiz proctoring settings.
     */
    private function send_notification($attempt, array $result, $settings): void {
        global $DB;

        if (!$settings) {
            return;
        }

        // Get user and quiz info.
        $user = $DB->get_record('user', ['id' => $attempt->userid]);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quizid]);

        if (!$user || !$quiz) {
            return;
        }

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        if (!$cm) {
            mtrace('Course module not found for quiz: ' . $quiz->id);
            return;
        }
        $course = $DB->get_record('course', ['id' => $quiz->course]);
        if (!$course) {
            mtrace('Course not found: ' . $quiz->course);
            return;
        }
        $context = \context_module::instance($cm->id);

        // Build recipient list.
        $recipients = [];

        if ($settings->notifyteacher) {
            // Get teachers.
            $teachers = get_enrolled_users($context, 'mod/quiz:grade');
            foreach ($teachers as $teacher) {
                $recipients[$teacher->id] = $teacher;
            }
        }

        if ($settings->notifyadmin) {
            // Add site admins.
            $admins = get_admins();
            foreach ($admins as $admin) {
                $recipients[$admin->id] = $admin;
            }
        }

        if (!empty($recipients)) {
            // Prepare message content.
            $a = new \stdClass();
            $a->student = s(fullname($user));
            $a->quiz = s(format_string($quiz->name, true, ['context' => $context]));
            $a->course = s(format_string($course->fullname, true, ['context' => $context]));
            $a->confidence = (int) $result['confidence'];
            $a->reason = s($result['face_detected'] ? get_string('reason_low_confidence', 'quizaccess_webcamproctor') :
                get_string('reason_no_face', 'quizaccess_webcamproctor'));
            $a->reporturl = (new \moodle_url('/mod/quiz/accessrule/webcamproctor/report.php', [
                'id' => $cm->id,
                'attemptid' => $attempt->attemptid,
            ]))->out(false);

            $subject = get_string(
                'notification_subject',
                'quizaccess_webcamproctor',
                (object) ['quiz' => format_string($quiz->name, true, ['context' => $context])]
            );
            $messagehtml = get_string('notification_body', 'quizaccess_webcamproctor', $a);

            // Use custom template if provided.
            if (!empty($settings->emailtemplate)) {
                $messagehtml = str_replace(
                    ['{student}', '{quiz}', '{course}', '{confidence}', '{reason}', '{reporturl}'],
                    [$a->student, $a->quiz, $a->course, $a->confidence, $a->reason, s($a->reporturl)],
                    $settings->emailtemplate
                );
            }

            // Send to each recipient.
            foreach ($recipients as $recipient) {
                $eventdata = new \core\message\message();
                $eventdata->component = 'quizaccess_webcamproctor';
                $eventdata->name = 'flaggedattempt';
                $eventdata->userfrom = \core_user::get_noreply_user();
                $eventdata->userto = $recipient;
                $eventdata->subject = $subject;
                $eventdata->fullmessage = html_to_text($messagehtml);
                $eventdata->fullmessageformat = FORMAT_HTML;
                $eventdata->fullmessagehtml = $messagehtml;
                $eventdata->smallmessage = $subject;
                $eventdata->notification = 1;
                $eventdata->contexturl = $a->reporturl;
                $eventdata->contexturlname = get_string('viewreport', 'quizaccess_webcamproctor');

                message_send($eventdata);
            }

            // Handle CC emails.
            if (!empty($settings->ccemails)) {
                $this->send_cc_emails($settings->ccemails, $subject, $messagehtml);
            }

            // Mark notification as sent.
            $DB->set_field(
                'quizaccess_webcamproctor_attempts',
                'notificationsent',
                1,
                ['id' => $attempt->id]
            );
        }
    }

    /**
     * Send CC emails.
     *
     * @param string $ccemails Comma-separated email addresses.
     * @param string $subject Email subject.
     * @param string $body Email body HTML.
     */
    private function send_cc_emails(string $ccemails, string $subject, string $body): void {
        $emails = array_map('trim', explode(',', $ccemails));

        foreach ($emails as $email) {
            if (!validate_email($email)) {
                continue;
            }

            // Create a user-like object with all required fields for email_to_user.
            $user = new \stdClass();
            $user->id = -99;
            $user->email = $email;
            $user->mailformat = 1;
            $user->firstname = '';
            $user->lastname = '';
            $user->username = 'ccrecipient';
            $user->auth = 'manual';
            $user->maildisplay = 1;
            $user->emailstop = 0;
            $user->deleted = 0;
            $user->suspended = 0;
            $user->firstnamephonetic = '';
            $user->lastnamephonetic = '';
            $user->middlename = '';
            $user->alternatename = '';

            email_to_user($user, \core_user::get_noreply_user(), $subject, html_to_text($body), $body);
        }
    }

    /**
     * Mark image as having an error.
     *
     * @param int $imageid The image ID.
     * @param string $error The error message.
     */
    private function mark_image_error(int $imageid, string $error): void {
        global $DB;

        $DB->set_field(
            'quizaccess_webcamproctor_images',
            'processstatus',
            'error',
            ['id' => $imageid]
        );
        $DB->set_field(
            'quizaccess_webcamproctor_images',
            'processresult',
            json_encode(['error' => $error]),
            ['id' => $imageid]
        );
    }
}
