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


namespace quizaccess_examproctor;

use quizaccess_examproctor\external\log_event;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for the event logger: quotas, repeats, heartbeat, auto-submit.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_examproctor\external\log_event
 * @covers     \quizaccess_examproctor\task\enforce_autosubmit
 */
final class log_event_test extends \advanced_testcase {

    /**
     * A proctored quiz with one in-progress attempt by a student, started $ago seconds ago.
     *
     * @param int $ago
     * @param int $requirecamera
     * @return \stdClass attempt
     */
    protected function make_attempt(int $ago = 600, int $requirecamera = 1): \stdClass {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(['course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100, 'sumgrades' => 1]);
        $qgen = $gen->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $question = $qgen->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);
        $DB->insert_record('quizaccess_examproctor', (object) ['quizid' => $quiz->id, 'enabled' => 1, 'minors' => 0,
            'requirecamera' => $requirecamera, 'snapshotinterval' => 60, 'requirefullscreen' => 1, 'blockclipboard' => 1,
            'maxviolations' => 3, 'retentiondays' => 0]);

        $this->setUser($student);
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour('deferredfeedback');
        $start = time() - $ago;
        $attempt = quiz_create_attempt($quizobj, 1, false, $start, false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $start);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return $DB->get_record('quiz_attempts', ['id' => $attempt->id]);
    }

    public function test_harmless_flood_does_not_stop_violations(): void {
        $attempt = $this->make_attempt(10);
        $statuses = [];
        for ($i = 0; $i < 200; $i++) {
            $statuses[] = log_event::execute($attempt->id, 'tab_returned', 'spam')['status'];
        }
        $this->assertContains('quota', $statuses);
        $result = log_event::execute($attempt->id, 'tab_hidden');
        $this->assertSame('ok', $result['status']);
        $this->assertTrue($result['violation']);
    }

    public function test_repeated_problem_counts_once(): void {
        $attempt = $this->make_attempt();
        log_event::execute($attempt->id, 'camera_covered', 'brightness 4');
        $again = log_event::execute($attempt->id, 'camera_covered', 'brightness 4');
        $this->assertFalse($again['violation']);
        $continues = log_event::execute($attempt->id, 'problem_continues', 'camera_covered for 15 s');
        $this->assertFalse($continues['violation']);
        $this->assertSame(1, $continues['violations']);
    }

    public function test_heartbeat_keeps_one_row(): void {
        global $DB;
        $attempt = $this->make_attempt(600, 0);
        for ($i = 0; $i < 3; $i++) {
            log_event::execute($attempt->id, 'heartbeat');
        }
        $this->assertSame(1, $DB->count_records('quizaccess_examproctor_log',
            ['attemptid' => $attempt->id, 'eventtype' => 'heartbeat']));
    }

    public function test_autosubmit_off_unless_site_allows(): void {
        global $DB;
        $attempt = $this->make_attempt();
        foreach (['tab_hidden', 'window_blur', 'copy', 'paste'] as $type) {
            $result = log_event::execute($attempt->id, $type);
        }
        $this->assertFalse($result['submitnow']);
        $this->assertSame('inprogress', $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->id]));
    }

    public function test_autosubmit_enforced_when_browser_does_not_submit(): void {
        global $DB;
        $attempt = $this->make_attempt();
        set_config('allowautosubmit', 1, 'quizaccess_examproctor');
        foreach (['tab_hidden', 'window_blur', 'copy'] as $type) {
            $result = log_event::execute($attempt->id, $type);
        }
        $this->assertTrue($result['submitnow']);
        $DB->execute("UPDATE {quizaccess_examproctor_log} SET timecreated = timecreated - 60
            WHERE attemptid = ? AND eventtype = 'autosubmit_requested'", [$attempt->id]);
        $this->setAdminUser();
        ob_start();
        (new \quizaccess_examproctor\task\enforce_autosubmit())->execute();
        ob_end_clean();
        $this->assertSame('finished', $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->id]));
    }

    public function test_camera_faults_flagged_not_counted(): void {
        $attempt = $this->make_attempt();
        foreach (['camera_denied', 'camera_lost', 'video_frozen'] as $type) {
            $result = log_event::execute($attempt->id, $type, 'fault');
            $this->assertFalse($result['violation']);
        }
        $this->assertSame(0, $result['violations']);
        $flagged = \quizaccess_examproctor\local\event_registry::flagged_types();
        $this->assertContains('camera_lost', $flagged);
        $this->assertContains('video_frozen', $flagged);
    }

    public function test_server_only_type_refused(): void {
        $attempt = $this->make_attempt();
        $this->expectException(\invalid_parameter_exception::class);
        log_event::execute($attempt->id, 'consent', 'forged');
    }
}
