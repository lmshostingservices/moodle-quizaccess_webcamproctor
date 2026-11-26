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
 * Language strings for the Webcam Proctoring quiz access rule plugin.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['ai_noservice_notice'] = 'No face-matching service is set up, so snapshots are kept for you to review but are not checked by AI and no attempt is flagged automatically.';
$string['ai_paused_notice'] = 'AI face matching is paused by the site administrator. Snapshots are kept for you to review but are not checked by AI.';
$string['attempt_blocked'] = 'On hold';
$string['attempt_clean'] = 'Verified';
$string['attempt_details'] = 'Attempt details';
$string['attempt_flagged'] = 'Flagged for review';
$string['attempt_held'] = 'On hold';
$string['attempt_pending'] = 'Not yet reviewed';
$string['back_to_attempts'] = 'Back to all attempts';
$string['baseline_photo'] = 'Baseline photo';
$string['baseline_required'] = 'Take your verification photo with the camera before starting the quiz.';
$string['blocksubmission'] = 'Put flagged attempts on hold';
$string['blocksubmission_help'] = 'If enabled, when the face-matching service flags an attempt, the student cannot continue or submit it until a teacher reviews the photos and releases it in the proctoring report. If disabled, flagged attempts carry on normally and are marked for review.

No attempt is put on hold when AI face matching is paused or no service is set up.';
$string['camera'] = 'Camera';
$string['camera_dark'] = 'The picture is too dark or blank, or the camera is covered. Check your lighting and camera.';
$string['camera_lost'] = 'Camera lost: proctoring interrupted';
$string['camera_off'] = 'The camera is off. Select "Turn on camera" to begin.';
$string['camera_on'] = 'Camera on. Checking the picture...';
$string['camera_preview'] = 'Camera preview';
$string['camera_ready'] = 'Ready. Look at the camera and take your photo.';
$string['camera_reconnecting'] = 'Reconnecting camera (try {$a->attempt} of {$a->max})';
$string['camera_start'] = 'Turn on camera';
$string['ccemails'] = 'CC email addresses';
$string['ccemails_help'] = 'Comma-separated list of additional email addresses to notify when an attempt is flagged.';
$string['col_confidence'] = 'Lowest match';
$string['col_finished'] = 'Attempt finished';
$string['col_held'] = 'On hold';
$string['col_latest'] = 'Latest snapshot';
$string['col_reason'] = 'Flag reason';
$string['col_snapshots'] = 'Snapshots';
$string['col_started'] = 'Proctoring started';
$string['col_status'] = 'Proctoring status';
$string['col_verifyid'] = 'Verify ID';
$string['col_verifyidscore'] = 'Verify ID score';
$string['confidence_score'] = 'Match confidence: {$a}%';
$string['consent_required'] = 'You must agree to webcam proctoring to take this quiz.';
$string['emailtemplate'] = 'Custom email template';
$string['emailtemplate_help'] = 'Leave empty to use the standard message. Available placeholders: {student}, {quiz}, {course}, {confidence}, {reason}, {reporturl}';
$string['enlarge'] = 'Enlarge: {$a}';
$string['error_no_camera'] = 'The camera could not be started. Check that no other app is using it, then try again.';
$string['exportcsv'] = 'Export to CSV';
$string['filter_all'] = 'All attempts';
$string['filter_clean'] = 'Verified';
$string['filter_flagged'] = 'Flagged';
$string['filter_pending'] = 'Not yet reviewed';
$string['image_error'] = 'could not be checked';
$string['image_unverified'] = 'not checked by AI';
$string['mark_clean'] = 'Mark as verified';
$string['mark_flagged'] = 'Mark as flagged';
$string['maxcaptures'] = 'Maximum snapshots';
$string['maxcaptures_help'] = 'The maximum number of snapshots to capture per attempt. Set to unlimited for continuous monitoring throughout the quiz.';
$string['messageprovider:flaggedattempt'] = 'Quiz attempt flagged by webcam proctoring';
$string['no_attempts'] = 'No proctored attempts yet.';
$string['notice_ai'] = 'Each snapshot is sent to {$a->processor} and compared with your baseline photo by AI face matching. A low match flags the attempt for your teacher to review.';
$string['notice_ai_paused'] = 'AI face matching is currently paused on this site. Your photos are only seen by staff.';
$string['notice_baseline'] = 'A baseline photo is taken now, before you start.';
$string['notice_retention'] = 'Photos are kept for {$a->retention} and then deleted automatically.';
$string['notice_review'] = 'Only staff who can view the proctoring report see your photos. A teacher makes any decision about your attempt, not the AI.';
$string['notice_rights'] = 'You can ask your institution for a copy of your photos or for them to be deleted.';
$string['notice_snapshots'] = 'Snapshots are taken at random times, about every {$a->interval}, while your attempt is open.';
$string['notification_body'] = '<p>A quiz attempt has been flagged for review.</p>
<p><strong>Student:</strong> {$a->student}<br>
<strong>Quiz:</strong> {$a->quiz}<br>
<strong>Course:</strong> {$a->course}<br>
<strong>Match confidence:</strong> {$a->confidence}%<br>
<strong>Reason:</strong> {$a->reason}</p>
<p><a href="{$a->reporturl}">Review the attempt</a></p>';
$string['notification_subject'] = 'Proctoring alert: flagged attempt in {$a->quiz}';
$string['notifyadmin'] = 'Notify site administrators';
$string['notifyadmin_help'] = 'Send a notification to site administrators when an attempt is flagged.';
$string['notifyteacher'] = 'Notify course teachers';
$string['notifyteacher_help'] = 'Send a notification to course teachers when an attempt is flagged.';
$string['photo_taken'] = 'Photo taken. You can retake it or start the quiz.';
$string['plugindescription'] = 'This quiz uses webcam proctoring. You will be asked to allow camera access and take a photo before you start.';
$string['pluginname'] = 'Webcam Proctoring';
$string['preflight_button'] = 'Take photo';
$string['preflight_consent'] = 'I have read the above and agree to webcam proctoring for this quiz.';
$string['preflight_message'] = 'This quiz uses webcam proctoring. Please read how your photos are used before you agree.';
$string['preflight_retake'] = 'Retake photo';
$string['preflight_title'] = 'Webcam proctoring';
$string['privacy:attempt'] = 'Attempt {$a}';
$string['privacy:metadata:attempts'] = 'Proctoring record for each quiz attempt.';
$string['privacy:metadata:attempts:attemptid'] = 'The quiz attempt being proctored.';
$string['privacy:metadata:attempts:flaggedreason'] = 'Why the attempt was flagged.';
$string['privacy:metadata:attempts:lowestconfidence'] = 'The lowest face-match score in the attempt.';
$string['privacy:metadata:attempts:reviewedby'] = 'The staff member who reviewed the attempt.';
$string['privacy:metadata:attempts:reviewedtime'] = 'When the attempt was reviewed.';
$string['privacy:metadata:attempts:snapshotcount'] = 'How many snapshots were taken.';
$string['privacy:metadata:attempts:status'] = 'The review status of the attempt.';
$string['privacy:metadata:attempts:timecreated'] = 'When proctoring started.';
$string['privacy:metadata:attempts:userid'] = 'The student whose attempt is proctored.';
$string['privacy:metadata:facecompare'] = 'Webcam photos are sent to the face-matching service set up by the site administrator, which compares each snapshot with the baseline photo.';
$string['privacy:metadata:facecompare:baseline'] = 'The baseline photo of the student.';
$string['privacy:metadata:facecompare:snapshot'] = 'A webcam snapshot taken during the attempt.';
$string['privacy:metadata:images'] = 'Webcam photos taken for identity verification.';
$string['privacy:metadata:images:confidence'] = 'The face-match score for the photo.';
$string['privacy:metadata:images:imagedata'] = 'The photo itself.';
$string['privacy:metadata:images:imagetype'] = 'Whether the photo is the baseline or a snapshot.';
$string['privacy:metadata:images:processstatus'] = 'Whether the photo was checked.';
$string['privacy:metadata:images:timecreated'] = 'When the photo was taken.';
$string['processor_default'] = 'the face-matching service used by this site';
$string['proctoring_active'] = 'Webcam proctoring is on';
$string['proctoring_active_count'] = 'Webcam proctoring is on ({$a} snapshots)';
$string['proctoring_complete'] = 'Webcam proctoring complete ({$a} snapshots)';
$string['reason_camera_lost'] = 'Camera stopped working during the attempt';
$string['reason_low_confidence'] = 'Low face-match confidence';
$string['reason_low_confidence_value'] = 'Face-match confidence ({$a}%) below the threshold';
$string['reason_no_face'] = 'No face detected';
$string['report_description'] = 'Review flagged attempts and compare captured images. AI scores are a guide only: check the photos before marking an attempt.';
$string['report_heading'] = 'Webcam proctoring report';
$string['report_title'] = 'Proctoring report';
$string['retention_never'] = 'Keep until deleted by hand';
$string['retention_never_short'] = 'until your institution deletes them';
$string['reviewed_by'] = 'Reviewed by {$a->user} on {$a->time}';
$string['sensitivitythreshold'] = 'Face match sensitivity';
$string['sensitivitythreshold_help'] = 'The minimum face-match score for a snapshot to pass. Higher values flag more attempts for review.';
$string['setting_apikey'] = 'API key';
$string['setting_apikey_desc'] = 'Key sent to the face-matching service in the X-API-Key header.';
$string['setting_apiurl'] = 'Face-matching service URL';
$string['setting_apiurl_desc'] = 'Base URL of the face-matching service. Photos are posted to {URL}/api/face-compare. Leave empty to keep photos for human review only.';
$string['setting_paused'] = 'Pause AI face matching';
$string['setting_paused_desc'] = 'Stop sending photos to the face-matching service on the whole site. Photos are still taken and kept for staff review, but no attempt is flagged or put on hold automatically. Use this if the AI is not working as intended (EU AI Act Article 26(5)).';
$string['setting_processorname'] = 'Service name shown to students';
$string['setting_processorname_desc'] = 'Name of the company or service that compares the photos, as shown in the student notice. Leave empty for a generic description.';
$string['setting_responsibleaiheading'] = 'Responsible AI';
$string['setting_responsibleaiheading_desc'] = 'Webcam proctoring is a high-risk AI use under the EU AI Act (Annex III, point 3(d)). These settings help deployers keep human oversight and limit how long photos are kept.';
$string['setting_retentiondays'] = 'Keep photos for';
$string['setting_retentiondays_desc'] = 'Webcam photos older than this are deleted by a nightly task. Review results are kept. Students are told this period before they agree.';
$string['setting_serviceheading'] = 'Face-matching service';
$string['setting_serviceheading_desc'] = 'Where webcam photos are sent for AI comparison. If no service is set, photos are kept for staff to review and no attempt is flagged automatically.';
$string['snapshot_count'] = '{$a} snapshots';
$string['snapshot_failed'] = 'Snapshot could not be sent. Proctoring continues.';
$string['snapshot_image'] = 'Snapshot';
$string['snapshotinterval'] = 'Snapshot interval';
$string['snapshotinterval_help'] = 'About how often to take a snapshot during the attempt. The exact time varies so it cannot be predicted.';
$string['student'] = 'Student';
$string['submission_blocked'] = 'This attempt is on hold while your teacher reviews the webcam photos. Please contact your teacher.';
$string['submission_unblocked'] = 'Attempt released from hold';
$string['taskcleanupimages'] = 'Delete expired webcam proctoring photos';
$string['taskprocessimage'] = 'Process webcam proctoring images';
$string['unblock_submission'] = 'Release hold';
$string['unlimited'] = 'Unlimited';
$string['verifyid_document'] = 'Verify ID document';
$string['verifyid_face'] = 'Face match {$a}%';
$string['verifyid_name'] = 'Name match {$a}%';
$string['verifyid_not_completed'] = 'Not verified';
$string['verifyid_overall'] = 'Overall {$a}%';
$string['verifyid_photo'] = 'Verify ID selfie';
$string['verifyid_section'] = 'AI Verify ID comparison';
$string['verifyidintegration'] = 'Show AI Verify ID results';
$string['verifyidintegration_help'] = 'If enabled, staff with the "View Verify ID photos" capability see the student\'s AI Verify ID result next to the proctoring photos.';
$string['view_all'] = 'View all';
$string['viewreport'] = 'View proctoring report';
$string['webcam_not_available'] = 'No camera found. Connect a webcam and try again.';
$string['webcam_permission_denied'] = 'Camera access was blocked. Allow camera access in your browser settings, then try again.';
$string['webcamproctor:manageattempts'] = 'Review and release proctored attempts';
$string['webcamproctor:viewreport'] = 'View webcam proctoring reports';
$string['webcamproctor:viewverifyid'] = 'View Verify ID photos in proctoring reports';
$string['webcamproctoring'] = 'Enable webcam proctoring';
$string['webcamproctoring_help'] = 'If enabled, students agree to proctoring and take a baseline photo before starting. Snapshots are taken during the attempt and, if a face-matching service is set up, compared with the baseline by AI. Attempts with a low match are flagged for a teacher to review in the proctoring report.';
