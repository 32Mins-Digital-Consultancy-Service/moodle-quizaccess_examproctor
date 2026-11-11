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

namespace quizaccess_examproctor\task;

use quizaccess_examproctor\local\attempt_finisher;
use quizaccess_examproctor\local\helper;

/**
 * Every minute: submit attempts that reached the violation limit but whose browser did not submit them
 * (for example a student who blocks the page's requests on a one-page quiz).
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforce_autosubmit extends \core\task\scheduled_task {

    /**
     * Name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_enforceautosubmit', 'quizaccess_examproctor');
    }

    /**
     * Run.
     */
    public function execute() {
        global $DB;
        $grace = \quizaccess_examproctor\external\log_event::AUTOSUBMIT_GRACE;
        $sql = "SELECT a.*
                  FROM {quiz_attempts} a
                  JOIN {quizaccess_examproctor_log} l ON l.attemptid = a.id AND l.eventtype = 'autosubmit_requested'
                 WHERE a.state IN ('inprogress', 'overdue') AND l.timecreated < :cutoff
                   AND NOT EXISTS (SELECT 1 FROM {quizaccess_examproctor_log} d
                                    WHERE d.attemptid = a.id AND d.eventtype = 'autosubmit')";
        $attempts = $DB->get_records_sql($sql, ['cutoff' => time() - $grace], 0, 200);
        foreach ($attempts as $attempt) {
            try {
                attempt_finisher::finish((int) $attempt->id);
                helper::log($attempt, 'autosubmit', 'Violation limit reached (submitted by the server)');
                $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, MUST_EXIST);
                \quizaccess_examproctor\event\attempt_autosubmitted::create([
                    'objectid' => $attempt->id,
                    'relateduserid' => $attempt->userid,
                    'context' => \context_module::instance($cm->id),
                    'other' => ['quizid' => (int) $attempt->quiz],
                ])->trigger();
                mtrace('Submitted attempt ' . $attempt->id . ' after the violation limit.');
            } catch (\Throwable $e) {
                mtrace('Could not submit attempt ' . $attempt->id . ': ' . $e->getMessage());
            }
        }
    }
}
