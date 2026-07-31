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

$string['pluginname'] = 'Webcam Proctoring';
$string['webcamproctoring'] = 'Enable webcam proctoring';
$string['webcamproctoring_help'] = 'If enabled, students must grant webcam access before the quiz begins. A baseline photo is captured at the start of the attempt and all subsequent periodic snapshots are compared against this baseline using AI face-matching to verify the same person remains in front of the camera throughout.

Snapshot interval controls how frequently images are captured (e.g., every 30, 60, or 120 seconds). Face match sensitivity sets how strict the comparison is — higher values flag more attempts for review. Maximum snapshots caps the total images per attempt, or can be set to unlimited for continuous monitoring.

Teachers choose whether a failed verification blocks quiz submission outright, or allows submission but flags the attempt for review. Email notifications can be sent automatically to course teachers, site administrators, and additional CC addresses when an attempt is flagged. A customisable email template is provided with placeholders for student name, quiz, course, confidence score, flag reason, and a direct link to the proctoring report.

The proctoring report shows all captured snapshots with their AI confidence scores and processing status (pass, flag, or error), a summary of flagged events, and a CSV export of the full session. Integration with AI Verify ID allows snapshots to be compared against the student\'s pre-verified government ID photo for higher-confidence identity assurance.';

// Quiz settings.
$string['snapshotinterval'] = 'Snapshot interval';
$string['snapshotinterval_help'] = 'How often to capture webcam snapshots during the quiz. Lower values provide more security but use more storage.';
$string['sensitivitythreshold'] = 'Face match sensitivity';
$string['sensitivitythreshold_help'] = 'How strict the face matching should be. Higher values are more strict and may flag more attempts for review.';
$string['blocksubmission'] = 'Block submission on verification failure';
$string['blocksubmission_help'] = 'If enabled, students cannot submit the quiz if webcam verification fails. If disabled, the quiz can be submitted but flagged for teacher review.';
$string['verifyidintegration'] = 'Compare with AI Verify ID';
$string['verifyidintegration_help'] = 'If enabled, webcam snapshots will be compared against the student\'s verified ID photo from the AI Verify ID plugin to ensure the same person is taking the quiz.';
$string['notificationsettings'] = 'Notification Settings';
$string['notifyteacher'] = 'Notify course teachers';
$string['notifyteacher_help'] = 'Send email notification to course teachers when an attempt is flagged.';
$string['notifyadmin'] = 'Notify site administrators';
$string['notifyadmin_help'] = 'Send email notification to site administrators when an attempt is flagged.';
$string['ccemails'] = 'CC email addresses';
$string['ccemails_help'] = 'Comma-separated list of additional email addresses to notify when an attempt is flagged.';
$string['emailtemplate'] = 'Custom email template';
$string['emailtemplate_help'] = 'Customise the notification email. Available placeholders: {student}, {quiz}, {course}, {confidence}, {reason}, {reporturl}';
$string['emailtemplate_default'] = '<h2>Proctoring Alert: Suspicious Activity Detected</h2>

<p>Dear Instructor,</p>

<p>A quiz attempt has been flagged for review due to potential identity verification issues.</p>

<table style="border-collapse: collapse; width: 100%; max-width: 500px; margin: 20px 0;">
<tr style="background: #f8fafc;">
<td style="padding: 12px; border: 1px solid #e2e8f0; font-weight: bold;">Student</td>
<td style="padding: 12px; border: 1px solid #e2e8f0;">{student}</td>
</tr>
<tr>
<td style="padding: 12px; border: 1px solid #e2e8f0; font-weight: bold;">Quiz</td>
<td style="padding: 12px; border: 1px solid #e2e8f0;">{quiz}</td>
</tr>
<tr style="background: #f8fafc;">
<td style="padding: 12px; border: 1px solid #e2e8f0; font-weight: bold;">Course</td>
<td style="padding: 12px; border: 1px solid #e2e8f0;">{course}</td>
</tr>
<tr>
<td style="padding: 12px; border: 1px solid #e2e8f0; font-weight: bold;">Reason</td>
<td style="padding: 12px; border: 1px solid #e2e8f0; color: #dc2626;">{reason}</td>
</tr>
<tr style="background: #f8fafc;">
<td style="padding: 12px; border: 1px solid #e2e8f0; font-weight: bold;">Confidence</td>
<td style="padding: 12px; border: 1px solid #e2e8f0;">{confidence}%</td>
</tr>
</table>

<p><a href="{reporturl}" style="display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Review Attempt</a></p>

<p style="color: #64748b; font-size: 14px;">Please review the captured images and compare them with the student\'s baseline photo to determine if further action is required.</p>

<p>Regards,<br>
Webcam Proctoring System</p>';
$string['notifyemails'] = 'Notification emails';
$string['notifyemails_help'] = 'Comma-separated list of email addresses to notify when an attempt is flagged. Leave blank to only notify course teachers.';

// Preflight check.
$string['preflight_title'] = 'Webcam Verification Required';
$string['preflight_message'] = 'This quiz requires webcam proctoring. Please allow camera access and position your face within the green circle to verify your identity.';
$string['preflight_consent'] = 'I understand that my webcam will be used to verify my identity during this quiz';
$string['preflight_button'] = 'Capture Photo & Start Quiz';
$string['preflight_retake'] = 'Retake Photo';
$string['webcam_permission_denied'] = 'Camera access was denied. Please allow camera access to proceed with this quiz.';
$string['webcam_not_available'] = 'No camera detected. Please connect a webcam to take this quiz.';
$string['baseline_required'] = 'You must capture a verification photo before starting this quiz.';
$string['consent_required'] = 'You must agree to webcam proctoring to take this quiz.';
$string['face_detected'] = 'Face detected - you can capture your photo now';
$string['no_face_detected'] = 'No face detected - please position your face in the frame';
$string['multiple_faces'] = 'Multiple faces detected - please ensure only you are in the frame';

// Status messages.
$string['proctoring_active'] = 'Webcam proctoring is active. Your identity is being verified.';
$string['snapshot_captured'] = 'Identity verification snapshot captured.';
$string['attempt_clean'] = 'Verified - No issues detected';
$string['attempt_flagged'] = 'Flagged for review';
$string['attempt_pending'] = 'Processing...';
$string['attempt_processing'] = 'Verifying identity...';
$string['attempt_blocked'] = 'Blocked - Verification failed';
$string['submission_blocked'] = 'Quiz submission is blocked because webcam verification failed. Please contact your teacher.';

// Teacher report.
$string['report_title'] = 'Proctoring Report';
$string['report_heading'] = 'Webcam Proctoring Report';
$string['report_description'] = 'Review flagged attempts and compare captured images.';
$string['baseline_image'] = 'Baseline Photo';
$string['flagged_snapshot'] = 'Flagged Snapshot';
$string['all_snapshots'] = 'All Snapshots';
$string['no_flagged_attempts'] = 'No flagged attempts to review.';
$string['no_attempts'] = 'No proctoring attempts recorded yet.';
$string['view_images'] = 'View Images';
$string['view_all'] = 'View All';
$string['mark_clean'] = 'Mark as Verified';
$string['mark_flagged'] = 'Mark as Flagged';
$string['filter_all'] = 'All attempts';
$string['filter_flagged'] = 'Flagged only';
$string['filter_clean'] = 'Verified only';
$string['filter_pending'] = 'Pending review';
$string['confidence_score'] = 'Match confidence: {$a}%';
$string['flagged_reason'] = 'Reason: {$a}';
$string['reviewed_by'] = 'Reviewed by {$a->user} on {$a->time}';
$string['snapshot_count'] = '{$a} snapshots';
$string['verifyid_status'] = 'Verify ID: {$a}';
$string['verifyid_match'] = 'ID Match: {$a}%';
$string['verifyid_section'] = 'AI Verify ID Comparison';
$string['verifyid_photo'] = 'Verified ID Photo';
$string['verifyid_not_completed'] = 'Student has not completed ID verification';

// Report actions.
$string['submission_unblocked'] = 'Submission unblocked';
$string['unblock_submission'] = 'Unblock Submission';
$string['attempt_details'] = 'Attempt Details';
$string['back_to_attempts'] = 'Back to all attempts';
$string['baseline_photo'] = 'Baseline Photo';
$string['snapshot_image'] = 'Snapshot';

// Flagged reasons.
$string['reason_face_mismatch'] = 'Face does not match baseline photo';
$string['reason_no_face'] = 'No face detected in snapshot';
$string['reason_multiple_faces'] = 'Multiple faces detected';
$string['reason_low_confidence'] = 'Low confidence face match';
$string['reason_verifyid_mismatch'] = 'Face does not match verified ID photo';

// Notifications.
$string['notification_subject'] = 'Proctoring Alert: Flagged Attempt in {$a->quiz}';
$string['notification_body'] = '<p>A quiz attempt has been flagged for review.</p>
<p><strong>Student:</strong> {$a->student}<br>
<strong>Quiz:</strong> {$a->quiz}<br>
<strong>Course:</strong> {$a->course}<br>
<strong>Match Confidence:</strong> {$a->confidence}%<br>
<strong>Reason:</strong> {$a->reason}</p>
<p><a href="{$a->reporturl}">Click here to review the attempt</a></p>';
$string['messageprovider:flaggedattempt'] = 'Notification when a quiz attempt is flagged by webcam proctoring';

// Tasks.
$string['taskprocessimage'] = 'Process webcam proctoring images';
$string['viewreport'] = 'View proctoring report';

// Export.
$string['exportcsv'] = 'Export to CSV';

// Max captures.
$string['maxcaptures'] = 'Maximum snapshots';
$string['maxcaptures_help'] = 'The maximum number of snapshots to capture per attempt. Set to unlimited for continuous monitoring throughout the quiz.';
$string['unlimited'] = 'Unlimited';

// Admin settings.
$string['settings_heading'] = 'Webcam Proctoring Settings';
$string['default_snapshotinterval'] = 'Default snapshot interval';
$string['default_snapshotinterval_desc'] = 'Default time between snapshot captures for new quizzes.';
$string['default_threshold'] = 'Default sensitivity threshold';
$string['default_threshold_desc'] = 'Default face matching sensitivity for new quizzes.';
$string['image_quality'] = 'Image quality';
$string['image_quality_desc'] = 'WebP compression quality (0-100). Lower values create smaller files but lower quality images.';
$string['max_image_width'] = 'Maximum image width';
$string['max_image_width_desc'] = 'Images will be resized to this maximum width to save storage space.';

// Errors.
$string['error_saving_image'] = 'Error saving webcam image. Please try again.';
$string['error_processing'] = 'Error processing webcam image.';
$string['error_no_camera'] = 'Unable to access camera. Please check your camera permissions.';
$string['error_api'] = 'Face verification service temporarily unavailable. Please try again.';

// Privacy.
$string['privacy:metadata:quizaccess_webcamproctor_attempts'] = 'Stores proctoring attempt records during quiz attempts.';
$string['privacy:metadata:quizaccess_webcamproctor_attempts:userid'] = 'The ID of the user whose quiz attempt is being proctored.';
$string['privacy:metadata:quizaccess_webcamproctor_attempts:timecreated'] = 'The time when the proctoring session was started.';
$string['privacy:metadata:quizaccess_webcamproctor_images'] = 'Stores webcam images captured during quiz attempts for identity verification.';
$string['privacy:metadata:quizaccess_webcamproctor_images:attemptid'] = 'The ID of the proctoring attempt this image belongs to.';
$string['privacy:metadata:quizaccess_webcamproctor_images:imagedata'] = 'The base64-encoded webcam image data.';
$string['privacy:metadata:quizaccess_webcamproctor_images:timecreated'] = 'The time when the image was captured.';
$string['privacy:metadata:quizaccess_webcamproctor_images:confidence'] = 'The AI face matching confidence score as a percentage.';
$string['privacy:metadata:quizaccess_webcamproctor_images:processstatus'] = 'The processing status: pending, processed, or error.';
$string['privacy:metadata:openai'] = 'Webcam images are sent to OpenAI for AI-powered face comparison.';
$string['privacy:metadata:openai:webcamimage'] = 'The webcam image data sent to OpenAI for face verification.';

// Capabilities.
$string['webcamproctor:viewreport'] = 'View webcam proctoring reports';
$string['webcamproctor:manageattempts'] = 'Manage proctoring attempt statuses';

// Plugin description.
$string['plugindescription'] = 'Webcam proctoring is enabled for this quiz. You must allow camera access before starting. A baseline photo will be taken at the start and periodic snapshots will be captured during your attempt to verify your identity.';
