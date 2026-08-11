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
 * Webcam Proctoring quiz access rule implementation.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use mod_quiz\form\preflight_check_form;

/**
 * A rule implementing webcam proctoring for quiz attempts.
 */
class quizaccess_webcamproctor extends access_rule_base {
    /**
     * Return an appropriate string to describe this rule.
     *
     * @return string The description.
     */
    public function description(): array {
        return [get_string('plugindescription', 'quizaccess_webcamproctor')];
    }

    /**
     * Whether the user should be blocked from starting a new attempt.
     *
     * @param int $numprevattempts The number of previous attempts.
     * @param object $lastattempt The last attempt object.
     * @return string|false A message or false if no restriction.
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        return false;
    }

    /**
     * Whether the access is prevented.
     *
     * @return string|false A message or false if no restriction.
     */
    public function prevent_access() {
        return false;
    }

    /**
     * Whether preflight check is required.
     *
     * @param int|null $attemptid The attempt id if this is a continue attempt.
     * @return bool Whether preflight check is required.
     */
    public function is_preflight_check_required($attemptid) {
        global $DB;

        // If continuing an existing attempt, check if baseline already captured.
        if ($attemptid) {
            $attempt = $DB->get_record('quizaccess_webcamproctor_attempts', [
                'attemptid' => $attemptid
            ]);
            if ($attempt && $attempt->baselineimageid) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add preflight check form fields.
     *
     * @param preflight_check_form $quizform The form.
     * @param MoodleQuickForm $mform The form definition.
     * @param int|null $attemptid The attempt id if continuing.
     */
    public function add_preflight_check_form_fields(
        preflight_check_form $quizform,
        MoodleQuickForm $mform,
        $attemptid
    ) {
        global $PAGE, $OUTPUT;

        $mform->addElement('header', 'webcamproctorheader',
            get_string('preflight_title', 'quizaccess_webcamproctor'));

        // Load AMD module for webcam capture.
        $PAGE->requires->js_call_amd('quizaccess_webcamproctor/preflight', 'init', [
            'quizid' => $this->quiz->id,
            'attemptid' => $attemptid,
            'snapshotinterval' => $this->get_setting('snapshotinterval', 60),
            'maxcaptures' => $this->get_setting('maxcaptures', 0),
        ]);

        // Container for webcam preview and controls.
        $html = '<div id="webcamproctor-preflight" class="webcamproctor-container">';
        $html .= '<div class="webcamproctor-message">' .
            get_string('preflight_message', 'quizaccess_webcamproctor') . '</div>';
        $html .= '<div class="webcamproctor-preview-container">';
        $html .= '<video id="webcamproctor-video" autoplay playsinline muted></video>';
        $html .= '<canvas id="webcamproctor-canvas" style="display:none;"></canvas>';
        $html .= '<div id="webcamproctor-face-indicator" class="face-indicator"></div>';
        $html .= '</div>';
        $html .= '<div class="webcamproctor-status" id="webcamproctor-status"></div>';
        $html .= '<div class="webcamproctor-controls">';
        $html .= '<button type="button" id="webcamproctor-capture" class="btn btn-primary" disabled>';
        $html .= get_string('preflight_button', 'quizaccess_webcamproctor') . '</button>';
        $html .= '</div>';
        $html .= '<input type="hidden" name="webcamproctor_baseline" id="webcamproctor-baseline" value="">';
        $html .= '<input type="hidden" name="webcamproctor_consent" id="webcamproctor-consent" value="0">';
        $html .= '</div>';

        $mform->addElement('html', $html);

        // Consent checkbox.
        $mform->addElement('checkbox', 'webcamproctor_consent_check',
            get_string('preflight_consent', 'quizaccess_webcamproctor'));
        $mform->addRule('webcamproctor_consent_check', 
            get_string('consent_required', 'quizaccess_webcamproctor'), 
            'required', null, 'client');

        // Preflight CSS is loaded from styles.css — no inline injection needed.
    }

    /**
     * Validate preflight check form submission.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @param array $errors The existing errors.
     * @param int|null $attemptid The attempt id.
     * @return array The updated errors.
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        // Check consent.
        if (empty($data['webcamproctor_consent_check'])) {
            $errors['webcamproctor_consent_check'] = 
                get_string('consent_required', 'quizaccess_webcamproctor');
        }

        // Check baseline image was captured.
        if (empty($data['webcamproctor_baseline'])) {
            $errors['webcamproctor_baseline'] = 
                get_string('baseline_required', 'quizaccess_webcamproctor');
        }

        return $errors;
    }

    /**
     * Process preflight check and save baseline image.
     *
     * @param int|null $attemptid The attempt id.
     * @param array $data The form data.
     */
    public function notify_preflight_check_passed($attemptid, $data = null) {
        global $DB, $USER;

        if (empty($data['webcamproctor_baseline'])) {
            return;
        }

        // Create or get proctoring attempt record.
        $attempt = $DB->get_record('quizaccess_webcamproctor_attempts', [
            'attemptid' => $attemptid
        ]);

        if (!$attempt) {
            $attempt = new stdClass();
            $attempt->quizid = $this->quiz->id;
            $attempt->attemptid = $attemptid;
            $attempt->userid = $USER->id;
            $attempt->status = 'pending';
            $attempt->snapshotcount = 0;
            $attempt->notificationsent = 0;
            $attempt->timecreated = time();
            $attempt->timemodified = time();
            $attempt->id = $DB->insert_record('quizaccess_webcamproctor_attempts', $attempt);
        }

        // Save baseline image.
        $image = new stdClass();
        $image->attemptid = $attempt->id;
        $image->imagetype = 'baseline';
        $image->imagedata = $data['webcamproctor_baseline'];
        $image->processstatus = 'pending';
        $image->timecreated = time();
        $imageid = $DB->insert_record('quizaccess_webcamproctor_images', $image);

        // Update attempt with baseline reference.
        $DB->set_field('quizaccess_webcamproctor_attempts', 'baselineimageid', $imageid,
            ['id' => $attempt->id]);
    }

    /** @var object|null|false Cached quiz proctoring settings. */
    private $cachedsettings = null;

    /**
     * Get a setting value.
     *
     * @param string $name The setting name.
     * @param mixed $default The default value.
     * @return mixed The setting value.
     */
    private function get_setting(string $name, $default = null) {
        global $DB;

        if ($this->cachedsettings === null) {
            $this->cachedsettings = $DB->get_record('quizaccess_webcamproctor', [
                'quizid' => $this->quiz->id
            ]);
        }

        if ($this->cachedsettings && isset($this->cachedsettings->$name)) {
            return $this->cachedsettings->$name;
        }

        return $default;
    }

    /**
     * Whether this rule is enabled for the quiz.
     *
     * @param quiz_settings $quizobj The quiz settings object.
     * @param int $timenow The current time.
     * @param bool $canignoretimelimits Whether time limits can be ignored.
     * @return quizaccess_webcamproctor|null The rule instance or null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $DB;

        $settings = $DB->get_record('quizaccess_webcamproctor', [
            'quizid' => $quizobj->get_quizid()
        ]);

        if ($settings && $settings->enabled) {
            return new self($quizobj, $timenow);
        }

        return null;
    }

    /**
     * Add form fields to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform The quiz form.
     * @param MoodleQuickForm $mform The form definition.
     */
    public static function add_settings_form_fields(
        mod_quiz_mod_form $quizform,
        MoodleQuickForm $mform
    ) {
        $mform->addElement('header', 'webcamproctorheader',
            get_string('pluginname', 'quizaccess_webcamproctor'));

        // Enable webcam proctoring.
        $mform->addElement('selectyesno', 'webcamproctor_enabled',
            get_string('webcamproctoring', 'quizaccess_webcamproctor'));
        $mform->addHelpButton('webcamproctor_enabled', 'webcamproctoring', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_enabled', 0);

        // Snapshot interval.
        $options = [
            30 => '30 ' . get_string('seconds'),
            60 => '1 ' . get_string('minute'),
            120 => '2 ' . get_string('minutes'),
            180 => '3 ' . get_string('minutes'),
            300 => '5 ' . get_string('minutes'),
        ];
        $mform->addElement('select', 'webcamproctor_snapshotinterval',
            get_string('snapshotinterval', 'quizaccess_webcamproctor'), $options);
        $mform->addHelpButton('webcamproctor_snapshotinterval', 'snapshotinterval', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_snapshotinterval', 60);
        $mform->hideIf('webcamproctor_snapshotinterval', 'webcamproctor_enabled', 'eq', 0);

        // Sensitivity threshold.
        $options = [];
        for ($i = 50; $i <= 95; $i += 5) {
            $options[$i] = $i . '%';
        }
        $mform->addElement('select', 'webcamproctor_threshold',
            get_string('sensitivitythreshold', 'quizaccess_webcamproctor'), $options);
        $mform->addHelpButton('webcamproctor_threshold', 'sensitivitythreshold', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_threshold', 70);
        $mform->hideIf('webcamproctor_threshold', 'webcamproctor_enabled', 'eq', 0);

        // Block submission on failure.
        $mform->addElement('selectyesno', 'webcamproctor_blocksubmission',
            get_string('blocksubmission', 'quizaccess_webcamproctor'));
        $mform->addHelpButton('webcamproctor_blocksubmission', 'blocksubmission', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_blocksubmission', 0);
        $mform->hideIf('webcamproctor_blocksubmission', 'webcamproctor_enabled', 'eq', 0);

        // Verify ID integration.
        $mform->addElement('selectyesno', 'webcamproctor_verifyid',
            get_string('verifyidintegration', 'quizaccess_webcamproctor'));
        $mform->addHelpButton('webcamproctor_verifyid', 'verifyidintegration', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_verifyid', 0);
        $mform->hideIf('webcamproctor_verifyid', 'webcamproctor_enabled', 'eq', 0);

        // Notification settings header.
        $mform->addElement('static', 'webcamproctor_notifyheader', '',
            '<strong>' . get_string('notificationsettings', 'quizaccess_webcamproctor') . '</strong>');
        $mform->hideIf('webcamproctor_notifyheader', 'webcamproctor_enabled', 'eq', 0);

        // Notify teacher.
        $mform->addElement('selectyesno', 'webcamproctor_notifyteacher',
            get_string('notifyteacher', 'quizaccess_webcamproctor'));
        $mform->setDefault('webcamproctor_notifyteacher', 1);
        $mform->hideIf('webcamproctor_notifyteacher', 'webcamproctor_enabled', 'eq', 0);

        // Notify admin.
        $mform->addElement('selectyesno', 'webcamproctor_notifyadmin',
            get_string('notifyadmin', 'quizaccess_webcamproctor'));
        $mform->setDefault('webcamproctor_notifyadmin', 0);
        $mform->hideIf('webcamproctor_notifyadmin', 'webcamproctor_enabled', 'eq', 0);

        // Maximum captures.
        $maxoptions = [0 => get_string('unlimited', 'quizaccess_webcamproctor')];
        for ($i = 5; $i <= 50; $i += 5) {
            $maxoptions[$i] = $i;
        }
        $mform->addElement('select', 'webcamproctor_maxcaptures',
            get_string('maxcaptures', 'quizaccess_webcamproctor'), $maxoptions);
        $mform->addHelpButton('webcamproctor_maxcaptures', 'maxcaptures', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_maxcaptures', 0);
        $mform->hideIf('webcamproctor_maxcaptures', 'webcamproctor_enabled', 'eq', 0);

        // CC emails.
        $mform->addElement('text', 'webcamproctor_ccemails',
            get_string('ccemails', 'quizaccess_webcamproctor'), ['size' => 50]);
        $mform->setType('webcamproctor_ccemails', PARAM_TEXT);
        $mform->addHelpButton('webcamproctor_ccemails', 'ccemails', 'quizaccess_webcamproctor');
        $mform->hideIf('webcamproctor_ccemails', 'webcamproctor_enabled', 'eq', 0);

        // Email template.
        $mform->addElement('editor', 'webcamproctor_emailtemplate',
            get_string('emailtemplate', 'quizaccess_webcamproctor'), null,
            ['maxfiles' => 0, 'noclean' => true]);
        $mform->setType('webcamproctor_emailtemplate', PARAM_RAW);
        $mform->addHelpButton('webcamproctor_emailtemplate', 'emailtemplate', 'quizaccess_webcamproctor');
        $mform->setDefault('webcamproctor_emailtemplate', [
            'text' => get_string('emailtemplate_default', 'quizaccess_webcamproctor'),
            'format' => FORMAT_HTML
        ]);
        $mform->hideIf('webcamproctor_emailtemplate', 'webcamproctor_enabled', 'eq', 0);
    }

    /**
     * Save settings from the quiz form.
     *
     * @param stdClass $quiz The quiz object.
     */
    public static function save_settings($quiz) {
        global $DB;

        $record = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $quiz->id]);
        $isnew = !$record;

        if ($isnew) {
            $record = new stdClass();
            $record->quizid = $quiz->id;
        }

        $record->enabled = !empty($quiz->webcamproctor_enabled) ? 1 : 0;
        $record->snapshotinterval = $quiz->webcamproctor_snapshotinterval ?? 60;
        $record->sensitivitythreshold = $quiz->webcamproctor_threshold ?? 70;
        $record->maxcaptures = $quiz->webcamproctor_maxcaptures ?? 0;
        $record->blocksubmission = !empty($quiz->webcamproctor_blocksubmission) ? 1 : 0;
        $record->verifyidintegration = !empty($quiz->webcamproctor_verifyid) ? 1 : 0;
        $record->notifyteacher = !empty($quiz->webcamproctor_notifyteacher) ? 1 : 0;
        $record->notifyadmin = !empty($quiz->webcamproctor_notifyadmin) ? 1 : 0;
        $record->ccemails = $quiz->webcamproctor_ccemails ?? '';

        // Handle editor field.
        if (isset($quiz->webcamproctor_emailtemplate)) {
            if (is_array($quiz->webcamproctor_emailtemplate)) {
                $record->emailtemplate = $quiz->webcamproctor_emailtemplate['text'] ?? '';
            } else {
                $record->emailtemplate = $quiz->webcamproctor_emailtemplate;
            }
        }

        $record->timemodified = time();

        if ($isnew) {
            $DB->insert_record('quizaccess_webcamproctor', $record);
        } else {
            $DB->update_record('quizaccess_webcamproctor', $record);
        }
    }

    /**
     * Delete settings when quiz is deleted.
     *
     * @param stdClass $quiz The quiz object.
     */
    public static function delete_settings($quiz) {
        global $DB;

        // Get all attempt records for this quiz.
        $attempts = $DB->get_records('quizaccess_webcamproctor_attempts', ['quizid' => $quiz->id]);
        foreach ($attempts as $attempt) {
            // Delete all images for this attempt.
            $DB->delete_records('quizaccess_webcamproctor_images', ['attemptid' => $attempt->id]);
        }

        // Delete all attempts.
        $DB->delete_records('quizaccess_webcamproctor_attempts', ['quizid' => $quiz->id]);

        // Delete quiz settings.
        $DB->delete_records('quizaccess_webcamproctor', ['quizid' => $quiz->id]);
    }

    /**
     * Get SQL fragments for loading settings.
     * Returns indexed array: [0] = fields, [1] = join, [2] = params
     * CRITICAL: Moodle access_manager.php expects explicit numeric indices [0, 1, 2], not implicit.
     *
     * @param int $quizid The quiz id.
     * @return array Array with [fields, join, params] for SQL.
     */
    public static function get_settings_sql($quizid): array {
        return [
            0 => 'webcamproctor.enabled AS webcamproctor_enabled' .
                ', webcamproctor.snapshotinterval AS webcamproctor_snapshotinterval' .
                ', webcamproctor.sensitivitythreshold AS webcamproctor_threshold' .
                ', webcamproctor.maxcaptures AS webcamproctor_maxcaptures' .
                ', webcamproctor.blocksubmission AS webcamproctor_blocksubmission' .
                ', webcamproctor.verifyidintegration AS webcamproctor_verifyid' .
                ', webcamproctor.notifyteacher AS webcamproctor_notifyteacher' .
                ', webcamproctor.notifyadmin AS webcamproctor_notifyadmin',
            1 => 'LEFT JOIN {quizaccess_webcamproctor} webcamproctor ON webcamproctor.quizid = quiz.id',
            2 => [],
        ];
    }
}
