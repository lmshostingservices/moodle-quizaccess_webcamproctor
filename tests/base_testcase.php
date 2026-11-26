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

use mod_quiz\quiz_settings;
use stdClass;

/**
 * Shared set-up for webcam proctoring tests.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_testcase extends \advanced_testcase {
    /** @var stdClass Course. */
    protected $course;
    /** @var stdClass Quiz. */
    protected $quiz;
    /** @var stdClass Course module. */
    protected $cm;
    /** @var stdClass Student. */
    protected $student;
    /** @var stdClass Second student. */
    protected $student2;
    /** @var stdClass Teacher. */
    protected $teacher;

    /**
     * Create a course with a proctored quiz.
     *
     * @param array $settings Proctoring settings to override.
     */
    protected function create_proctored_quiz(array $settings = []): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->student2 = $gen->create_and_enrol($this->course, 'student');
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(array_merge([
            'course' => $this->course->id,
            'questionsperpage' => 0,
            'grade' => 10,
            'sumgrades' => 1,
            'webcamproctor_enabled' => 1,
            'webcamproctor_snapshotinterval' => 30,
            'webcamproctor_threshold' => 70,
        ], $settings));
        $this->cm = get_coursemodule_from_instance('quiz', $this->quiz->id);
        $qgen = $gen->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => \context_module::instance($this->cm->id)->id]);
        $question = $qgen->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $this->quiz);
    }

    /**
     * Start a real quiz attempt for a user.
     *
     * @param stdClass $user The user.
     * @return stdClass The quiz_attempts record.
     */
    protected function start_attempt(stdClass $user): stdClass {
        $this->setUser($user);
        $quizobj = quiz_settings::create($this->quiz->id, $user->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $now = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $now, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $now);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return $attempt;
    }

    /**
     * A small real JPEG as a data URI.
     *
     * @return string
     */
    protected function jpeg(): string {
        $img = imagecreatetruecolor(240, 180);
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel($img, rand(0, 239), rand(0, 179), imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255)));
        }
        ob_start();
        imagejpeg($img, null, 90);
        return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
    }

    /**
     * Give an attempt a baseline, as the preflight form does.
     *
     * @param stdClass $attempt The quiz attempt.
     * @return stdClass The proctoring record.
     */
    protected function add_baseline(stdClass $attempt): stdClass {
        global $DB;
        $this->setUser($DB->get_record('user', ['id' => $attempt->userid]));
        local\manager::store_pending_baseline($this->quiz->id, $this->jpeg());
        local\manager::attach_pending_baseline($attempt);
        return $DB->get_record('quizaccess_webcamproctor_attempts', ['attemptid' => $attempt->id]);
    }
}
