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

defined('MOODLE_INTERNAL') || die();

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
        global $DB, $CFG;

        $data = $this->get_custom_data();

        if (!isset($data->proctorattemptid) || !isset($data->imageid)) {
            mtrace('Missing required data for process_image task');
            return;
        }

        // Get the proctoring attempt.
        $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts',
            ['id' => $data->proctorattemptid]);

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
        $baseline = $DB->get_record('quizaccess_webcamproctor_images',
            ['id' => $proctorattempt->baselineimageid]);

        if (!$baseline) {
            mtrace('Baseline image not found for attempt: ' . $data->proctorattemptid);
            $this->mark_image_error($image->id, 'No baseline image');
            return;
        }

        // Get quiz settings for threshold.
        $settings = $DB->get_record('quizaccess_webcamproctor',
            ['quizid' => $proctorattempt->quizid]);

        $threshold = $settings ? $settings->sensitivitythreshold : 70;

        // Perform face matching.
        $result = $this->match_faces($baseline->imagedata, $image->imagedata, $threshold);

        // Update image with results.
        $image->confidence = $result['confidence'];
        $image->processstatus = 'processed';
        $image->processresult = json_encode($result);
        $image->facedetected = $result['face_detected'] ? 1 : 0;
        $DB->update_record('quizaccess_webcamproctor_images', $image);

        // Update attempt if flagged.
        if (!$result['match'] || !$result['face_detected']) {
            $this->flag_attempt($proctorattempt, $image, $result, $settings);
        }

        // Update lowest confidence score.
        if ($result['confidence'] < ($proctorattempt->lowestconfidence ?? 100)) {
            $DB->set_field('quizaccess_webcamproctor_attempts', 'lowestconfidence',
                $result['confidence'], ['id' => $proctorattempt->id]);
        }

        mtrace('Processed image ' . $image->id . ' with confidence ' . $result['confidence'] . '%');
    }

    /**
     * Match faces between baseline and snapshot images.
     *
     * @param string $baselinedata Base64 baseline image.
     * @param string $snapshotdata Base64 snapshot image.
     * @param int $threshold Match threshold percentage.
     * @return array Match result with confidence score.
     */
    private function match_faces(string $baselinedata, string $snapshotdata, int $threshold): array {
        global $CFG;

        // Get API client configuration.
        $apiurl = get_config('quizaccess_webcamproctor', 'apiurl');
        $apikey = get_config('quizaccess_webcamproctor', 'apikey');

        if (empty($apiurl) || empty($apikey)) {
            // Use default Essay Grader AI API.
            $apiurl = get_config('local_moodlesupport', 'apiurl');
            $apikey = get_config('local_moodlesupport', 'apikey');
        }

        if (empty($apiurl) || empty($apikey)) {
            mtrace('No API configured for face matching — marking as unverified (fail closed)');
            return [
                'match' => false,
                'confidence' => 0,
                'face_detected' => true,
                'error' => 'No face-matching API configured',
                'unverified' => true,
            ];
        }

        try {
            $endpoint = rtrim($apiurl, '/') . '/api/face-compare';

            $postdata = json_encode([
                'baseline' => $baselinedata,
                'snapshot' => $snapshotdata,
                'threshold' => $threshold,
            ]);

            // Use Moodle's curl wrapper which respects proxy settings and SSL configuration.
            $curl = new \curl();
            $curl->setHeader([
                'Content-Type: application/json',
                'X-API-Key: ' . $apikey,
            ]);
            $response = $curl->post($endpoint, $postdata, [
                'CURLOPT_TIMEOUT' => 30,
            ]);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;
            $error = $curl->error;

            if ($error) {
                throw new \Exception('cURL error: ' . $error);
            }

            if ($httpcode !== 200) {
                throw new \Exception('API returned HTTP ' . $httpcode);
            }

            $result = json_decode($response, true);

            if (!$result || !isset($result['confidence'])) {
                throw new \Exception('Invalid API response');
            }

            return [
                'match' => ($result['confidence'] >= $threshold),
                'confidence' => (int) $result['confidence'],
                'face_detected' => $result['face_detected'] ?? true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            mtrace('Face matching API error: ' . $e->getMessage() . ' — marking as unverified (fail closed)');
            return [
                'match' => false,
                'confidence' => 0,
                'face_detected' => true,
                'error' => $e->getMessage(),
                'unverified' => true,
            ];
        }
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
            $reason = 'No face detected in webcam image';
        } else {
            $reason = 'Face match confidence (' . $result['confidence'] . '%) below threshold';
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
            $DB->set_field('quizaccess_webcamproctor_attempts', 'submissionblocked', 1,
                ['id' => $attempt->id]);
        }

        // Send notification if not already sent.
        if (!$attempt->notificationsent) {
            $this->send_notification($attempt, $image, $result, $settings);
        }
    }

    /**
     * Send notification about flagged attempt.
     *
     * @param object $attempt The proctoring attempt.
     * @param object $image The triggering image.
     * @param array $result The match result.
     * @param object $settings Quiz proctoring settings.
     */
    private function send_notification($attempt, $image, array $result, $settings): void {
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
            $a->student = fullname($user);
            $a->quiz = $quiz->name;
            $a->course = $course->fullname;
            $a->confidence = $result['confidence'];
            $a->reason = $result['face_detected'] ? 'Low confidence match' : 'No face detected';
            $a->reporturl = (new \moodle_url('/mod/quiz/accessrule/webcamproctor/report.php', [
                'id' => $cm->id,
                'attemptid' => $attempt->attemptid,
            ]))->out(false);

            $subject = get_string('notification_subject', 'quizaccess_webcamproctor', $a);
            $messagehtml = get_string('notification_body', 'quizaccess_webcamproctor', $a);

            // Use custom template if provided.
            if (!empty($settings->emailtemplate)) {
                $messagehtml = str_replace(
                    ['{student}', '{quiz}', '{course}', '{confidence}', '{reason}', '{reporturl}'],
                    [$a->student, $a->quiz, $a->course, $a->confidence, $a->reason, $a->reporturl],
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
                $eventdata->fullmessage = strip_tags($messagehtml);
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
            $DB->set_field('quizaccess_webcamproctor_attempts', 'notificationsent', 1,
                ['id' => $attempt->id]);
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

            email_to_user($user, \core_user::get_noreply_user(), $subject, strip_tags($body), $body);
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

        $DB->set_field('quizaccess_webcamproctor_images', 'processstatus', 'error',
            ['id' => $imageid]);
        $DB->set_field('quizaccess_webcamproctor_images', 'processresult',
            json_encode(['error' => $error]), ['id' => $imageid]);
    }
}
