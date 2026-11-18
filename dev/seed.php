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
namespace PHPUnit\Framework { if (!class_exists('PHPUnit\\Framework\\TestCase')) { abstract class TestCase {} } }
namespace {
define('CLI_SCRIPT', true);
require(getenv('MOODLE_DIR') . '/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
set_exception_handler(function($e) { echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"; exit(1); }); \core\cron::setup_user();
$gen = new testing_data_generator();
function getuser($gen, array $record) {
    global $DB;
    return $DB->get_record('user', ['username' => $record['username'], 'deleted' => 0]) ?: $gen->create_user($record);
}
$course = $gen->create_course(['shortname' => 'EPTEST' . time()]);
$quizgen = $gen->get_plugin_generator('mod_quiz');
$quiz = $quizgen->create_instance(['course' => $course->id, 'name' => 'Final Exam', 'questionsperpage' => 0, 'grade' => 10, 'sumgrades' => 2]);
$qgen = $gen->get_plugin_generator('core_question');
$cat = $qgen->create_question_category();
foreach ([1,2] as $i) {
  $form = (object)['category'=>$cat->id.','.$cat->contextid,'name'=>"TF $i",'questiontext'=>['text'=>"Statement $i is true?",'format'=>FORMAT_HTML],
   'generalfeedback'=>['text'=>'','format'=>FORMAT_HTML],'defaultmark'=>1,'penalty'=>1,'correctanswer'=>1,
   'feedbacktrue'=>['text'=>'','format'=>FORMAT_HTML],'feedbackfalse'=>['text'=>'','format'=>FORMAT_HTML],'showstandardinstruction'=>0,'status'=>\core_question\local\bank\question_version_status::QUESTION_STATUS_READY];
  $qq = (object)['category'=>$cat->id,'qtype'=>'truefalse','createdby'=>2,'modifiedby'=>2,'contextid'=>$cat->contextid];
  $saved = question_bank::get_qtype('truefalse')->save_question($qq, $form);
  quiz_add_quiz_question($saved->id, $quiz);
}
$DB->insert_record('quizaccess_examproctor', (object)['quizid'=>$quiz->id,'enabled'=>1,'minors'=>0,'requirecamera'=>1,'snapshotinterval'=>30,'requirefullscreen'=>1,'blockclipboard'=>1,'maxviolations'=>3,'retentiondays'=>45]);
$quiz2 = $quizgen->create_instance(['course' => $course->id, 'name' => 'Second quiz', 'questionsperpage' => 0, 'grade' => 10, 'sumgrades' => 1]);
$student = getuser($gen, ['username'=>'student1','firstname'=>'Asha','lastname'=>'Kumar','email'=>'asha@example.com']);
$student2 = getuser($gen, ['username'=>'student2','firstname'=>'Ravi','lastname'=>'Shah','email'=>'ravi@example.com']);
$teacher = getuser($gen, ['username'=>'teacher1']);
$gen->enrol_user($student->id, $course->id, 'student');
$gen->enrol_user($student2->id, $course->id, 'student');
$gen->enrol_user($teacher->id, $course->id, 'editingteacher');
// v0.5 ID row + log rows.
require_once($CFG->dirroot . '/user/lib.php'); foreach ([$student, $student2, $teacher] as $u) { update_internal_user_password($u, 'Pass#1234'); }
file_put_contents(getenv('EP_IDS') ?: '/tmp/examproctor_ids.json', json_encode(['course'=>$course->id,'quiz'=>$quiz->id,'cmid'=>$quiz->cmid,'student'=>$student->id,'student2'=>$student2->id,'teacher'=>$teacher->id]) . "\n");
echo "Test data created. Student logins: student1 / student2, password Pass#1234\n";

}
