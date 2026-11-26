# Changelog - Webcam Proctoring Quiz Access Plugin

All notable changes to this plugin will be documented in this file.

## [v2.3.0] - 2026-11-26

Go-live readiness release. Tested on Moodle 4.5, 5.2 and 5.3.

### Fixed
- Students can start a proctored quiz again: the consent and baseline photo fields are registered in the start form.
- The baseline photo is saved with the attempt, including new attempts.
- Snapshots are taken during the attempt (the runtime script is loaded on attempt pages).
- "Hold attempt for review" now stops a held attempt from being continued or submitted.
- With no face-matching service set, or with AI paused, photos are marked "not checked by AI" instead of flagging every student.
- Version number is above v2.2.3 again, and the v2.2.3 upgrade steps are restored, so sites on v2.2.3 or v2.2.4 upgrade cleanly.
- Report photo pop-up works on Moodle 5.x (Bootstrap 5), and the name fields debugging notice is gone.
- Editing a quiz no longer clears the notification email template and CC addresses.
- Snapshots are refused after the attempt is closed, and only JPEG, PNG or WebP images are accepted.

### Added
- Responsible AI (EU AI Act): plain-language notice before the quiz, consent checkbox, site switch to pause AI checks, processor name setting, and model, threshold and service recorded with each result.
- Site settings for the face-matching service, retention period and pause switch.
- Scheduled task that deletes photos after the retention period.
- Backup and restore of per-quiz settings.
- Privacy export of students' photos and reviewer data.
- Proctoring badge with self-view during attempts, with camera retry and "camera lost" flag.
- New capability quizaccess/webcamproctor:viewverifyid for Verify ID columns.
- PHPUnit tests.

### Changed
- Consent is part of the start form instead of a pop-up, and works with keyboard and screen readers.
- The camera check only looks at brightness and detail, not skin tone.
- All text uses language strings. Code passes the Moodle code checker.
- Report and snapshot capabilities are marked as handling personal data.
- Removed unused third-party library declaration.

## [v2.2.4] - 2026-08-29

### Changed
- RELEASE RECOVERY: Republished the reviewed authoritative source under a new immutable tag because the historical tag contained a different source tree. No functional changes.

## [2.0.8] - 2025-12-22

### Changed
- Added official Moodle 5.x compatibility declaration (`$plugin->supported = [400, 500]`)



## [2.0.7] - 2025-12-20

### Changed
- Migrated to centralized download architecture
- Updated versioned ZIP filename

## [2.0.0] - 2025-12-01

### Added
- Enhanced webcam proctoring
- AI-powered behavior analysis
- Photo capture during quiz

## [1.0.0] - 2025-06-01

### Added
- Initial release
- Webcam proctoring for quizzes
- Moodle 4.0+ compatibility
