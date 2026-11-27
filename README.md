# quizaccess_webcamproctor

## Summary

Webcam proctoring for Moodle™ quizzes, with AI face matching and teacher review.

## Description

Before a proctored quiz starts, students read a plain-language notice about what is recorded, give consent and take a baseline photo. During the attempt the plugin takes webcam snapshots at random times and shows a small "webcam proctoring is on" badge with a self-view. Each snapshot is sent to the face-matching service the site administrator configures and compared with the baseline. A low match flags the attempt for a teacher, who makes every decision. Teachers can optionally hold an attempt until they have reviewed it.

If no face-matching service is configured, or the administrator pauses AI checks, photos are stored as "not checked by AI" and nobody is flagged.

Features: per-quiz settings (interval, threshold, maximum snapshots, hold, notifications), teacher report with photo pop-up, CSV export, optional AI Verify ID columns, backup and restore of settings, privacy export and deletion of photos, and automatic deletion of photos after a retention period.

Supports Moodle 4.5 to 5.3.

## Site settings

Site administration > Plugins > Activity modules > Quiz > Webcam proctoring:

- Face-matching service URL, API key and the processor name shown to students.
- Pause AI face matching (photos are still taken for teachers, nobody is flagged).
- Photo retention period (default 90 days).

## Responsible AI

Remote proctoring with face matching is listed as high-risk in the EU AI Act (Annex III 3(d)). This plugin is built so that a person always decides: the AI only flags attempts for review, students are told what happens before they start, the AI can be paused at any time, and the model, threshold and service used for each result are recorded for review.

## Current Release

Version 2.3.0. See CHANGELOG.md.

## Licence

GNU GPL v3 or later.
