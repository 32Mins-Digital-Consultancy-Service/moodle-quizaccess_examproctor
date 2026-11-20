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
define('CLI_SCRIPT', true);
require(getenv('MOODLE_DIR') . '/config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
use quizaccess_examproctor\external\log_event;
$ids = json_decode(trim(file_get_contents(getenv('EP_IDS'))));
$ok = 0; $fail = 0;
function check($cond, $msg) { global $ok, $fail; if ($cond) { $ok++; echo "  PASS $msg\n"; } else { $fail++; echo "  FAIL $msg\n"; } }
function jpeg($w, $h, $q = 60) { $im = imagecreatetruecolor($w, $h); for ($i=0;$i<200;$i++) imagesetpixel($im, rand(0,$w-1), rand(0,$h-1), rand(0,0xffffff)); ob_start(); imagejpeg($im, null, $q); return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean()); }

set_config('allowautosubmit', 1, 'quizaccess_examproctor');
$DB->set_field('quizaccess_examproctor', 'requirecamera', 1, ['quizid' => $ids->quiz]);
$quizobj = \mod_quiz\quiz_settings::create($ids->quiz);
$quiz = $quizobj->get_quiz();
echo "Settings load (get_settings_sql + hooks)\n";
$loaded = \mod_quiz\access_manager::load_settings($ids->quiz);
check(isset($loaded['examproctor_enabled']) && $loaded['examproctor_enabled'] == 1, 'settings load through access_manager');
check(array_key_exists('examproctor_minors', $loaded), 'new minors field loaded');

echo "Student starts an attempt\n";
$student = $DB->get_record('user', ['id' => $ids->student]);
\core\session\manager::set_user($student);
$rule = quizaccess_examproctor::make($quizobj, time(), false);
check($rule !== null, 'rule active');
check($rule->prevent_access() === false, 'browser request allowed');
check($rule->prevent_new_attempt(0, false) === false, 'no hook listener -> attempt allowed');
$items = $rule->consent_items();
check(count($items) >= 4, 'consent items: ' . count($items));
$rule->notify_preflight_check_passed(null);
$timenow = time();
$quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
$quba->set_preferred_behaviour($quiz->preferredbehaviour);
$n1 = 1 + $DB->count_records('quiz_attempts', ['quiz'=>$ids->quiz,'userid'=>$student->id]);
$attempt = quiz_create_attempt($quizobj, $n1, false, $timenow - 600, false, $student->id);
quiz_start_new_attempt($quizobj, $quba, $attempt, $n1, $timenow - 600);
quiz_attempt_save_started($quizobj, $quba, $attempt);
$attempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id]);
check($attempt->state === 'inprogress', 'attempt in progress #' . $attempt->id);

echo "Attempt page: server-side start + consent record\n";
$_GET['attempt'] = $attempt->id;
$page = new moodle_page(); $page->set_pagetype('mod-quiz-attempt'); $page->set_url('/mod/quiz/attempt.php', ['attempt'=>$attempt->id]); $page->set_context($quizobj->get_context());
$rule->setup_attempt_page($page);
check($DB->record_exists('quizaccess_examproctor_log', ['attemptid'=>$attempt->id,'eventtype'=>'proctoring_started']), 'proctoring_started row written by server');
check($DB->record_exists('quizaccess_examproctor_log', ['attemptid'=>$attempt->id,'eventtype'=>'consent']), 'consent row written with version');
$page2 = new moodle_page(); $page2->set_pagetype('mod-quiz-summary'); $page2->set_context($quizobj->get_context());
$rule->setup_attempt_page($page2);
check(strpos($page2->requires->get_end_code(), 'quizaccess_examproctor/proctor') !== false, 'JS loaded on summary page');
check($DB->count_records('quizaccess_examproctor_log', ['attemptid'=>$attempt->id,'eventtype'=>'consent']) == 1, 'consent recorded once only');

echo "log_event\n";
$r = log_event::execute($attempt->id, 'session_start', 'Firefox');
check($r['status'] === 'ok', 'session_start ok');
$r = log_event::execute($attempt->id, 'snapshot', '', jpeg(320, 240));
check($r['status'] === 'ok' && $DB->record_exists('quizaccess_examproctor_log', ['attemptid'=>$attempt->id,'eventtype'=>'snapshot','hassnapshot'=>1]), 'valid 320x240 snapshot stored');
try { log_event::execute($attempt->id, 'snapshot', '', jpeg(1920, 1080)); check(false, 'oversize image rejected'); } catch (invalid_parameter_exception $e) { check(true, 'oversize 1920x1080 image rejected'); }
try { log_event::execute($attempt->id, 'tab_hidden', '', jpeg(320, 240)); check(false, 'image on imageless type'); } catch (invalid_parameter_exception $e) { check(true, 'image on tab_hidden rejected'); }
try { log_event::execute($attempt->id, 'consent', 'forged'); check(false, 'server-only type'); } catch (invalid_parameter_exception $e) { check(true, 'server-only event "consent" refused from browser'); }
try { log_event::execute($attempt->id, 'face_magic', ''); check(false, 'unknown'); } catch (invalid_parameter_exception $e) { check(true, 'unknown event type refused'); }
log_event::execute($attempt->id, 'session_start', 'Chrome second tab');
check($DB->record_exists('quizaccess_examproctor_log', ['attemptid'=>$attempt->id,'eventtype'=>'multiple_sessions']), 'second session flagged multiple_sessions');
$r1 = log_event::execute($attempt->id, 'tab_hidden');
$r2 = log_event::execute($attempt->id, 'copy');
check($r2['violations'] == 2 && !$r2['submitnow'], 'two violations, no submit yet');
$r3 = log_event::execute($attempt->id, 'fullscreen_exit');
check($r3['submitnow'] === true, 'limit 3 reached -> browser asked to submit the page first');
check($DB->get_field('quiz_attempts', 'state', ['id'=>$attempt->id]) === 'inprogress', 'attempt still open until the page is submitted');
$r4 = log_event::execute($attempt->id, 'autosubmit_now');
check($r4['autosubmitted'] === true && $r4['redirecturl'] !== '', 'autosubmit_now finishes attempt via compat finisher');
check($DB->get_field('quiz_attempts', 'state', ['id'=>$attempt->id]) === 'finished', 'attempt state finished');
check($DB->record_exists('logstore_standard_log', ['eventname'=>'\\quizaccess_examproctor\\event\\violation_recorded']), 'Moodle event violation_recorded in standard log');
check($DB->record_exists('logstore_standard_log', ['eventname'=>'\\quizaccess_examproctor\\event\\attempt_autosubmitted']), 'Moodle event attempt_autosubmitted');
check($DB->record_exists('logstore_standard_log', ['eventname'=>'\\quizaccess_examproctor\\event\\consent_given']), 'Moodle event consent_given');

echo "Quota (flood control)\n";
$student2 = $DB->get_record('user', ['id' => $ids->student2]);
\core\session\manager::set_user($student2);
$quba2 = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
$quba2->set_preferred_behaviour($quiz->preferredbehaviour);
$n2 = 1 + $DB->count_records('quiz_attempts', ['quiz'=>$ids->quiz,'userid'=>$student2->id]);
$a2 = quiz_create_attempt($quizobj, $n2, false, time() - 5, false, $student2->id);
quiz_start_new_attempt($quizobj, $quba2, $a2, $n2, time() - 5);
quiz_attempt_save_started($quizobj, $quba2, $a2);
$DB->set_field('quizaccess_examproctor', 'maxviolations', 0, ['quizid'=>$ids->quiz]);
$statuses = [];
for ($i = 0; $i < 140; $i++) { $statuses[] = log_event::execute($a2->id, 'tab_returned', 'spam')['status']; }
check(in_array('quota', $statuses, true), 'flood stopped by quota after ' . array_search('quota', $statuses) . ' events');
check($DB->count_records('quizaccess_examproctor_log', ['attemptid'=>$a2->id,'eventtype'=>'quota_exceeded','details'=>'events']) == 1, 'quota_exceeded logged once per kind');
$imgstatus = [];
for ($i = 0; $i < 40; $i++) { try { $imgstatus[] = log_event::execute($a2->id, 'camera_covered', 'x', jpeg(320,240))['status']; } catch (Exception $e) { $imgstatus[] = 'err'; } }
$kept = $DB->count_records('quizaccess_examproctor_log', ['attemptid'=>$a2->id,'eventtype'=>'camera_covered']);
$withimg = $DB->count_records('quizaccess_examproctor_log', ['attemptid'=>$a2->id,'eventtype'=>'camera_covered','hassnapshot'=>1]);
check($kept == 40 && $withimg < 40, "image flood: events kept, images capped ($withimg of $kept)");

echo "Web-service guard (separate process)\n";
$out = shell_exec('php -r \'define("CLI_SCRIPT",1); define("WS_SERVER",1); require "' . getenv('MOODLE_DIR') . '/config.php"; require_once($CFG->dirroot."/mod/quiz/locallib.php"); $q=\mod_quiz\quiz_settings::create(' . $ids->quiz . '); $r=quizaccess_examproctor::make($q,time(),false); echo $r->prevent_access();\' 2>&1');
check(strpos($out, 'web browser') !== false, 'prevent_access blocks web-service clients: "' . trim($out) . '"');

echo "Report SQL\n";
\core\session\manager::set_user(get_admin());
[$sql, $count, $params] = \quizaccess_examproctor\local\report_data::summary_sql($ids->quiz);
$rows = $DB->get_records_sql($sql, $params);
check(count($rows) >= 2 && $DB->count_records_sql($count, $params) == count($rows), 'summary rows: ' . count($rows));
[$sql, $count, $params] = \quizaccess_examproctor\local\report_data::summary_sql($ids->quiz, 'asha', true);
$f = $DB->get_records_sql($sql, $params);
check(count($f) >= 1 && count(array_filter($f, fn($r) => $r->userid != $ids->student)) == 0, 'search + flagged filter: only Asha, ' . count($f) . ' attempt(s)');
$settings = \quizaccess_examproctor\local\helper::settings($ids->quiz);
foreach (array_slice($rows, 0, 0) as $row) { $a = \quizaccess_examproctor\local\report_data::assess($row, $settings); echo "    attempt {$row->attemptid}: sessions={$row->sessions} snaps={$row->snapshots}/{$a->expected} gap=" . (int)$a->gap . " multi=" . (int)$a->multisession . " live='{$a->live}'\n"; }
$silent = array_filter($rows, fn($r) => $r->attemptid == $a2->id);
$sa = \quizaccess_examproctor\local\report_data::assess(reset($silent), $settings);
check($sa->nodata, 'attempt without a browser session is flagged "no proctoring data"');
echo "    first row is attempt " . reset($rows)->attemptid . " (silent attempt " . $a2->id . ")\n";
check((int) reset($rows)->sessions === 0, 'silent attempts listed first');

echo "Retention task\n";
$DB->execute('UPDATE {quizaccess_examproctor_log} SET timecreated = timecreated - ' . (50 * DAYSECS) . ' WHERE attemptid = ?', [$attempt->id]);
$fs = get_file_storage();
$before = count($fs->get_area_files($quizobj->get_context()->id, 'quizaccess_examproctor', 'snapshots', false, 'id', false));
ob_start(); (new \quizaccess_examproctor\task\cleanup_evidence())->execute(); $o = ob_get_clean();
$after = count($fs->get_area_files($quizobj->get_context()->id, 'quizaccess_examproctor', 'snapshots', false, 'id', false));
check($DB->count_records('quizaccess_examproctor_log', ['attemptid'=>$attempt->id]) == 0, 'old events deleted (' . trim($o) . ')');
check($after < $before, "snapshots deleted with them ($before -> $after files)");
check($DB->count_records('quizaccess_examproctor_log', ['attemptid'=>$a2->id]) > 0, 'recent events kept');

echo "Duplicate quiz keeps settings (backup/restore)\n";
$cm = get_coursemodule_from_id('quiz', $ids->cmid);
$course = get_course($ids->course);
$newcm = duplicate_module($course, $cm);
$newset = $DB->get_record('quizaccess_examproctor', ['quizid' => $newcm->instance]);
check($newset && $newset->enabled == 1 && $newset->snapshotinterval == $settings->snapshotinterval, 'duplicated quiz has proctoring settings');
check($DB->count_records('quizaccess_examproctor_log', ['quizid' => $newcm->instance]) == 0, 'evidence not copied to the duplicate');

echo "Privacy\n";
$collection = \quizaccess_examproctor\privacy\provider::get_metadata(new \core_privacy\local\metadata\collection('quizaccess_examproctor'));
check(count($collection->get_collection()) == 4, 'metadata items incl. consent table: ' . count($collection->get_collection()));
$cl = \quizaccess_examproctor\privacy\provider::get_contexts_for_userid($ids->student2);
check(count($cl->get_contextids()) >= 1, 'contexts for student2: ' . count($cl->get_contextids()));
$approved = new \core_privacy\local\request\approved_contextlist($student2, 'quizaccess_examproctor', $cl->get_contextids());
\quizaccess_examproctor\privacy\provider::delete_data_for_user($approved);
check($DB->count_records('quizaccess_examproctor_log', ['userid' => $ids->student2]) == 0, 'privacy delete removes student2 data');

set_config('allowautosubmit', 0, 'quizaccess_examproctor');
echo "\nRESULT: $ok passed, $fail failed\n";
