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

namespace quizaccess_webcamproctor;

use mod_quiz\access_manager;
use mod_quiz\quiz_settings;
use quizaccess_webcamproctor\local\manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_testcase.php');

/**
 * Tests for the access rule: preflight, baseline storage, holds and settings.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quizaccess_webcamproctor
 * @covers    \quizaccess_webcamproctor\local\manager
 */
final class rule_test extends base_testcase {
    /**
     * Get the rule instance for the quiz.
     *
     * @return \quizaccess_webcamproctor|null
     */
    private function rule(): ?\quizaccess_webcamproctor {
        return \quizaccess_webcamproctor::make(quiz_settings::create($this->quiz->id), time(), false);
    }

    public function test_rule_only_made_when_enabled(): void {
        $this->create_proctored_quiz(['webcamproctor_enabled' => 0]);
        $this->assertNull($this->rule());
        $this->create_proctored_quiz();
        $this->assertInstanceOf(\quizaccess_webcamproctor::class, $this->rule());
    }

    public function test_preflight_validation_requires_consent_and_real_image(): void {
        $this->create_proctored_quiz();
        $this->setUser($this->student);
        $rule = $this->rule();

        $errors = $rule->validate_preflight_check([], [], [], null);
        $this->assertArrayHasKey('webcamproctor_consent_check', $errors);
        $this->assertArrayHasKey('webcamproctor_camera', $errors);

        $errors = $rule->validate_preflight_check(['webcamproctor_consent_check' => 1,
            'webcamproctor_baseline' => 'data:image/svg+xml;base64,' . base64_encode(str_repeat('<svg/>', 300))], [], [], null);
        $this->assertArrayHasKey('webcamproctor_camera', $errors);
        $this->assertNull(manager::take_pending_baseline($this->quiz->id));

        $errors = $rule->validate_preflight_check(['webcamproctor_consent_check' => 1,
            'webcamproctor_baseline' => $this->jpeg()], [], [], null);
        $this->assertEmpty($errors);
        $this->assertNotNull(manager::take_pending_baseline($this->quiz->id));
    }

    public function test_baseline_saved_when_attempt_page_loads(): void {
        global $DB, $PAGE;
        $this->create_proctored_quiz();
        // Create the attempt first: setUser() in the helper starts a fresh session.
        $attempt = $this->start_attempt($this->student);
        $rule = $this->rule();
        $this->assertTrue($rule->is_preflight_check_required(null));
        $this->assertEmpty($rule->validate_preflight_check(['webcamproctor_consent_check' => 1,
            'webcamproctor_baseline' => $this->jpeg()], [], [], null));
        // Core calls this with a null id for a new attempt.
        $rule->notify_preflight_check_passed(null);

        $_GET['attempt'] = $attempt->id;
        $rule->setup_attempt_page($PAGE);
        unset($_GET['attempt']);

        $record = $DB->get_record('quizaccess_webcamproctor_attempts', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertNotEmpty($record->baselineimageid);
        $this->assertEquals($this->student->id, $record->userid);
        $this->assertFalse($rule->is_preflight_check_required($attempt->id));
        $this->assertStringContainsString('quizaccess_webcamproctor/runtime', $PAGE->requires->get_end_code());
    }

    public function test_baseline_not_attached_to_someone_elses_attempt(): void {
        global $DB;
        $this->create_proctored_quiz();
        $attempt = $this->start_attempt($this->student2);
        $this->setUser($this->student);
        manager::store_pending_baseline($this->quiz->id, $this->jpeg());
        $this->assertFalse(manager::attach_pending_baseline($attempt));
        $this->assertFalse($DB->record_exists('quizaccess_webcamproctor_attempts', ['attemptid' => $attempt->id]));
    }

    public function test_continuing_attempt_saves_baseline_in_notify(): void {
        $this->create_proctored_quiz();
        $attempt = $this->start_attempt($this->student);
        $rule = $this->rule();
        $this->assertTrue($rule->is_preflight_check_required($attempt->id));
        manager::store_pending_baseline($this->quiz->id, $this->jpeg());
        $rule->notify_preflight_check_passed($attempt->id);
        $this->assertFalse($rule->is_preflight_check_required($attempt->id));
    }

    public function test_hold_prevents_access_only_when_enabled(): void {
        global $DB;
        $this->create_proctored_quiz(['webcamproctor_blocksubmission' => 1]);
        $attempt = $this->start_attempt($this->student);
        $record = $this->add_baseline($attempt);
        $this->assertFalse($this->rule()->prevent_access());

        $DB->set_field('quizaccess_webcamproctor_attempts', 'submissionblocked', 1, ['id' => $record->id]);
        $this->assertNotFalse($this->rule()->prevent_access());

        // The camera stays off on the error page of a held attempt.
        $page = new \moodle_page();
        $page->set_context(\context_module::instance($this->cm->id));
        $_GET['attempt'] = $attempt->id;
        $this->rule()->setup_attempt_page($page);
        unset($_GET['attempt']);
        $this->assertStringNotContainsString('quizaccess_webcamproctor/runtime', $page->requires->get_end_code());
        $this->assertNotEmpty((new access_manager(quiz_settings::create($this->quiz->id), time(), false))->prevent_access());

        // Another student is not affected.
        $this->setUser($this->student2);
        $this->assertFalse($this->rule()->prevent_access());

        // Turning the setting off releases the hold.
        $this->setUser($this->student);
        $DB->set_field('quizaccess_webcamproctor', 'blocksubmission', 0, ['quizid' => $this->quiz->id]);
        $this->assertFalse($this->rule()->prevent_access());
    }

    public function test_settings_saved_and_loaded(): void {
        $this->create_proctored_quiz(['webcamproctor_ccemails' => 'a@example.com',
            'webcamproctor_emailtemplate' => ['text' => '<p>{student}</p>', 'format' => FORMAT_HTML],
            'webcamproctor_maxcaptures' => 10]);
        $settings = access_manager::load_settings($this->quiz->id);
        $this->assertEquals(1, $settings['webcamproctor_enabled']);
        $this->assertEquals(10, $settings['webcamproctor_maxcaptures']);
        $this->assertEquals('a@example.com', $settings['webcamproctor_ccemails']);
        $this->assertEquals('<p>{student}</p>', $settings['webcamproctor_emailtemplate']['text']);
    }

    public function test_delete_settings_removes_everything(): void {
        global $DB;
        $this->create_proctored_quiz();
        $this->add_baseline($this->start_attempt($this->student));
        \quizaccess_webcamproctor::delete_settings($this->quiz);
        $this->assertEquals(0, $DB->count_records('quizaccess_webcamproctor'));
        $this->assertEquals(0, $DB->count_records('quizaccess_webcamproctor_attempts'));
        $this->assertEquals(0, $DB->count_records('quizaccess_webcamproctor_images'));
    }
}
