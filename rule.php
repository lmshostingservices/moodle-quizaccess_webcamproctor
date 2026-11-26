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

use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use quizaccess_webcamproctor\local\image_validator;
use quizaccess_webcamproctor\local\manager;

/**
 * A rule implementing webcam proctoring for quiz attempts.
 *
 * Flow: the student agrees and takes a baseline photo in the preflight form. The photo is kept in
 * the session until Moodle creates the attempt, then saved against it when the attempt page loads.
 * The attempt page loads the runtime module, which sends periodic snapshots to the web service.
 */
class quizaccess_webcamproctor extends access_rule_base {
    /** @var stdClass|null|false Cached quiz proctoring settings. */
    private $cachedsettings = null;

    /**
     * Create the rule when proctoring is enabled for the quiz.
     *
     * @param quiz_settings $quizobj The quiz settings object.
     * @param int $timenow The current time.
     * @param bool $canignoretimelimits Whether time limits can be ignored.
     * @return quizaccess_webcamproctor|null The rule instance or null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->webcamproctor_enabled)) {
            return null;
        }
        return new self($quizobj, $timenow);
    }

    /**
     * Describe the rule on the quiz page, with a report link for staff.
     *
     * @return array The description strings.
     */
    public function description(): array {
        $messages = [get_string('plugindescription', 'quizaccess_webcamproctor')];
        $context = $this->quizobj->get_context();
        if (has_capability('quizaccess/webcamproctor:viewreport', $context)) {
            $url = new moodle_url('/mod/quiz/accessrule/webcamproctor/report.php', ['id' => $this->quizobj->get_cmid()]);
            $messages[] = html_writer::link($url, get_string('report_title', 'quizaccess_webcamproctor'));
        }
        return $messages;
    }

    /**
     * Stop the student continuing an attempt that is on hold for teacher review.
     *
     * @return string|false A message, or false if access is allowed.
     */
    public function prevent_access() {
        global $USER;
        if (!$this->get_setting('blocksubmission', 0)) {
            return false;
        }
        if (manager::is_attempt_held((int) $this->quiz->id, (int) $USER->id)) {
            return get_string('submission_blocked', 'quizaccess_webcamproctor');
        }
        return false;
    }

    /**
     * Whether the preflight check (consent and baseline photo) is required.
     *
     * @param int|null $attemptid The attempt id if this is a continue attempt.
     * @return bool Whether preflight check is required.
     */
    public function is_preflight_check_required($attemptid) {
        if ($attemptid && manager::has_baseline((int) $attemptid)) {
            return false;
        }
        return true;
    }

    /**
     * Add the consent notice, camera and baseline capture to the preflight form.
     *
     * @param preflight_check_form $quizform The form.
     * @param MoodleQuickForm $mform The form definition.
     * @param int|null $attemptid The attempt id if continuing.
     */
    public function add_preflight_check_form_fields(preflight_check_form $quizform, MoodleQuickForm $mform, $attemptid) {
        global $PAGE, $OUTPUT;

        $interval = (int) $this->get_setting('snapshotinterval', 60);
        $retention = manager::get_retention_days();
        $a = (object) [
            'interval' => $interval >= 60 ? get_string('numminutes', '', round($interval / 60, 1)) :
                get_string('numseconds', '', $interval),
            'processor' => s(manager::get_processor_name()),
            'retention' => $retention > 0 ? get_string('numdays', '', $retention) :
                get_string('retention_never_short', 'quizaccess_webcamproctor'),
        ];

        $mform->addElement('header', 'webcamproctorheader', get_string('preflight_title', 'quizaccess_webcamproctor'));

        $items = [
            get_string('notice_baseline', 'quizaccess_webcamproctor'),
            get_string('notice_snapshots', 'quizaccess_webcamproctor', $a),
            manager::is_paused() ? get_string('notice_ai_paused', 'quizaccess_webcamproctor') :
                get_string('notice_ai', 'quizaccess_webcamproctor', $a),
            get_string('notice_review', 'quizaccess_webcamproctor'),
            get_string('notice_retention', 'quizaccess_webcamproctor', $a),
            get_string('notice_rights', 'quizaccess_webcamproctor'),
        ];
        $mform->addElement('html', $OUTPUT->render_from_template('quizaccess_webcamproctor/preflight', [
            'intro' => get_string('preflight_message', 'quizaccess_webcamproctor'),
            'items' => array_map(fn($text) => ['text' => $text], $items),
        ]));

        $mform->addElement(
            'static',
            'webcamproctor_camera',
            get_string('camera', 'quizaccess_webcamproctor'),
            $OUTPUT->render_from_template('quizaccess_webcamproctor/camera', [])
        );

        $mform->addElement('hidden', 'webcamproctor_baseline', '');
        $mform->setType('webcamproctor_baseline', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'checkbox',
            'webcamproctor_consent_check',
            '',
            get_string('preflight_consent', 'quizaccess_webcamproctor')
        );

        $PAGE->requires->js_call_amd('quizaccess_webcamproctor/preflight', 'init', [[
            'quizid' => (int) $this->quiz->id,
        ]]);
    }

    /**
     * Validate consent and the baseline photo, and keep the photo until the attempt exists.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @param array $errors The existing errors.
     * @param int|null $attemptid The attempt id.
     * @return array The updated errors.
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['webcamproctor_consent_check'])) {
            $errors['webcamproctor_consent_check'] = get_string('consent_required', 'quizaccess_webcamproctor');
        }

        $image = image_validator::clean((string) ($data['webcamproctor_baseline'] ?? ''));
        if ($image === null) {
            $errors['webcamproctor_camera'] = get_string('baseline_required', 'quizaccess_webcamproctor');
        }

        if (empty($errors)) {
            manager::store_pending_baseline((int) $this->quiz->id, $image);
        }
        return $errors;
    }

    /**
     * Save the baseline straight away when an existing attempt is being continued.
     *
     * For a new attempt the id is still null here, so setup_attempt_page() saves it instead.
     *
     * @param int|null $attemptid The attempt id.
     */
    public function notify_preflight_check_passed($attemptid) {
        global $DB;
        if ($attemptid) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $this->quiz->id]);
            if ($attempt) {
                manager::attach_pending_baseline($attempt);
            }
        }
    }

    /**
     * Save any pending baseline and start periodic snapshots on attempt pages.
     *
     * @param moodle_page $page The page being output.
     */
    public function setup_attempt_page($page) {
        global $DB, $USER;

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (!$attemptid) {
            return;
        }
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $this->quiz->id]);
        if (
            !$attempt || (int) $attempt->userid !== (int) $USER->id ||
                !in_array($attempt->state, [mod_quiz\quiz_attempt::IN_PROGRESS, mod_quiz\quiz_attempt::OVERDUE])
        ) {
            return;
        }

        manager::attach_pending_baseline($attempt);
        $proctor = manager::get_proctor_attempt($attempt->id);
        if (!$proctor || empty($proctor->baselineimageid)) {
            return;
        }
        if ($this->get_setting('blocksubmission', 0) && !empty($proctor->submissionblocked)) {
            // The attempt is on hold, so the page shows an error instead of the quiz: keep the camera off.
            return;
        }

        $page->requires->js_call_amd('quizaccess_webcamproctor/runtime', 'init', [[
            'attemptid' => (int) $attempt->id,
            'snapshotinterval' => (int) $this->get_setting('snapshotinterval', 60),
            'maxcaptures' => (int) $this->get_setting('maxcaptures', 0),
            'snapshotcount' => (int) $proctor->snapshotcount,
        ]]);
    }

    /**
     * Get a per-quiz setting.
     *
     * @param string $name The setting name.
     * @param mixed $default The default value.
     * @return mixed The setting value.
     */
    private function get_setting(string $name, $default = null) {
        global $DB;
        if ($this->cachedsettings === null) {
            $this->cachedsettings = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $this->quiz->id]);
        }
        if ($this->cachedsettings && isset($this->cachedsettings->$name)) {
            return $this->cachedsettings->$name;
        }
        return $default;
    }

    /**
     * Add the proctoring section to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform The quiz form.
     * @param MoodleQuickForm $mform The form definition.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $component = 'quizaccess_webcamproctor';
        $mform->addElement('header', 'webcamproctorheader', get_string('pluginname', $component));

        $mform->addElement('selectyesno', 'webcamproctor_enabled', get_string('webcamproctoring', $component));
        $mform->addHelpButton('webcamproctor_enabled', 'webcamproctoring', $component);
        $mform->setDefault('webcamproctor_enabled', 0);

        $options = [
            30 => get_string('numseconds', '', 30),
            60 => get_string('numminutes', '', 1),
            120 => get_string('numminutes', '', 2),
            180 => get_string('numminutes', '', 3),
            300 => get_string('numminutes', '', 5),
        ];
        $mform->addElement('select', 'webcamproctor_snapshotinterval', get_string('snapshotinterval', $component), $options);
        $mform->addHelpButton('webcamproctor_snapshotinterval', 'snapshotinterval', $component);
        $mform->setDefault('webcamproctor_snapshotinterval', 60);

        $options = [];
        for ($i = 50; $i <= 95; $i += 5) {
            $options[$i] = $i . '%';
        }
        $mform->addElement('select', 'webcamproctor_threshold', get_string('sensitivitythreshold', $component), $options);
        $mform->addHelpButton('webcamproctor_threshold', 'sensitivitythreshold', $component);
        $mform->setDefault('webcamproctor_threshold', 70);

        $maxoptions = [0 => get_string('unlimited', $component)];
        for ($i = 5; $i <= 50; $i += 5) {
            $maxoptions[$i] = $i;
        }
        $mform->addElement('select', 'webcamproctor_maxcaptures', get_string('maxcaptures', $component), $maxoptions);
        $mform->addHelpButton('webcamproctor_maxcaptures', 'maxcaptures', $component);
        $mform->setDefault('webcamproctor_maxcaptures', 0);

        $mform->addElement('selectyesno', 'webcamproctor_blocksubmission', get_string('blocksubmission', $component));
        $mform->addHelpButton('webcamproctor_blocksubmission', 'blocksubmission', $component);
        $mform->setDefault('webcamproctor_blocksubmission', 0);

        $mform->addElement('selectyesno', 'webcamproctor_verifyid', get_string('verifyidintegration', $component));
        $mform->addHelpButton('webcamproctor_verifyid', 'verifyidintegration', $component);
        $mform->setDefault('webcamproctor_verifyid', 0);

        $mform->addElement('selectyesno', 'webcamproctor_notifyteacher', get_string('notifyteacher', $component));
        $mform->addHelpButton('webcamproctor_notifyteacher', 'notifyteacher', $component);
        $mform->setDefault('webcamproctor_notifyteacher', 1);

        $mform->addElement('selectyesno', 'webcamproctor_notifyadmin', get_string('notifyadmin', $component));
        $mform->addHelpButton('webcamproctor_notifyadmin', 'notifyadmin', $component);
        $mform->setDefault('webcamproctor_notifyadmin', 0);

        $mform->addElement('text', 'webcamproctor_ccemails', get_string('ccemails', $component), ['size' => 50]);
        $mform->setType('webcamproctor_ccemails', PARAM_TEXT);
        $mform->addHelpButton('webcamproctor_ccemails', 'ccemails', $component);

        $mform->addElement(
            'editor',
            'webcamproctor_emailtemplate',
            get_string('emailtemplate', $component),
            null,
            ['maxfiles' => 0]
        );
        $mform->setType('webcamproctor_emailtemplate', PARAM_CLEANHTML);
        $mform->addHelpButton('webcamproctor_emailtemplate', 'emailtemplate', $component);

        foreach (
            ['snapshotinterval', 'threshold', 'maxcaptures', 'blocksubmission', 'verifyid', 'notifyteacher',
                'notifyadmin', 'ccemails', 'emailtemplate'] as $name
        ) {
            $mform->hideIf('webcamproctor_' . $name, 'webcamproctor_enabled', 'eq', 0);
        }
    }

    /**
     * Validate the quiz settings form fields.
     *
     * @param array $errors The errors found so far.
     * @param array $data The submitted data.
     * @param array $files The uploaded files.
     * @param mod_quiz_mod_form $quizform The quiz form.
     * @return array The updated errors.
     */
    public static function validate_settings_form_fields(array $errors, array $data, $files, mod_quiz_mod_form $quizform) {
        if (!empty($data['webcamproctor_enabled']) && !empty($data['webcamproctor_ccemails'])) {
            foreach (array_map('trim', explode(',', $data['webcamproctor_ccemails'])) as $email) {
                if ($email !== '' && !validate_email($email)) {
                    $errors['webcamproctor_ccemails'] = get_string('invalidemail');
                    break;
                }
            }
        }
        return $errors;
    }

    /**
     * Save settings from the quiz form.
     *
     * @param stdClass $quiz The quiz object.
     */
    public static function save_settings($quiz) {
        global $DB;

        $record = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $quiz->id]);
        if (!$record && empty($quiz->webcamproctor_enabled)) {
            return;
        }
        $record = $record ?: (object) ['quizid' => $quiz->id];

        $record->enabled = empty($quiz->webcamproctor_enabled) ? 0 : 1;
        $record->snapshotinterval = (int) ($quiz->webcamproctor_snapshotinterval ?? 60);
        $record->sensitivitythreshold = (int) ($quiz->webcamproctor_threshold ?? 70);
        $record->maxcaptures = (int) ($quiz->webcamproctor_maxcaptures ?? 0);
        $record->blocksubmission = empty($quiz->webcamproctor_blocksubmission) ? 0 : 1;
        $record->verifyidintegration = empty($quiz->webcamproctor_verifyid) ? 0 : 1;
        $record->notifyteacher = empty($quiz->webcamproctor_notifyteacher) ? 0 : 1;
        $record->notifyadmin = empty($quiz->webcamproctor_notifyadmin) ? 0 : 1;
        $record->ccemails = trim((string) ($quiz->webcamproctor_ccemails ?? ''));
        $template = $quiz->webcamproctor_emailtemplate ?? '';
        $record->emailtemplate = is_array($template) ? ($template['text'] ?? '') : (string) $template;
        $record->timemodified = time();

        if (empty($record->id)) {
            $DB->insert_record('quizaccess_webcamproctor', $record);
        } else {
            $DB->update_record('quizaccess_webcamproctor', $record);
        }
    }

    /**
     * Delete settings, attempts and images when a quiz is deleted.
     *
     * @param stdClass $quiz The quiz object.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $ids = $DB->get_fieldset_select('quizaccess_webcamproctor_attempts', 'id', 'quizid = ?', [$quiz->id]);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('quizaccess_webcamproctor_images', "attemptid $insql", $params);
        }
        $DB->delete_records('quizaccess_webcamproctor_attempts', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_webcamproctor', ['quizid' => $quiz->id]);
    }

    /**
     * SQL fragments to load the small settings with the quiz.
     *
     * @param int $quizid The quiz id.
     * @return array [fields, join, params].
     */
    public static function get_settings_sql($quizid): array {
        return [
            'webcamproctor.enabled AS webcamproctor_enabled, ' .
                'webcamproctor.snapshotinterval AS webcamproctor_snapshotinterval, ' .
                'webcamproctor.sensitivitythreshold AS webcamproctor_threshold, ' .
                'webcamproctor.maxcaptures AS webcamproctor_maxcaptures, ' .
                'webcamproctor.blocksubmission AS webcamproctor_blocksubmission, ' .
                'webcamproctor.verifyidintegration AS webcamproctor_verifyid, ' .
                'webcamproctor.notifyteacher AS webcamproctor_notifyteacher, ' .
                'webcamproctor.notifyadmin AS webcamproctor_notifyadmin',
            'LEFT JOIN {quizaccess_webcamproctor} webcamproctor ON webcamproctor.quizid = quiz.id',
            [],
        ];
    }

    /**
     * Load the text settings that are only needed by the settings form.
     *
     * @param int $quizid The quiz id.
     * @return array Setting name => value.
     */
    public static function get_extra_settings($quizid) {
        global $DB;
        $record = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $quizid], 'ccemails, emailtemplate');
        if (!$record) {
            return [];
        }
        return [
            'webcamproctor_ccemails' => (string) $record->ccemails,
            'webcamproctor_emailtemplate' => ['text' => (string) $record->emailtemplate, 'format' => FORMAT_HTML],
        ];
    }
}
