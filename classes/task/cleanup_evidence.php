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

use quizaccess_examproctor\local\helper;

/**
 * Deletes proctoring events and images older than each quiz's retention period.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_evidence extends \core\task\scheduled_task {

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'quizaccess_examproctor');
    }

    /**
     * Run.
     */
    public function execute() {
        global $DB;

        // Every quiz with events: its own retention if set, else the site default.
        $quizids = $DB->get_fieldset_sql('SELECT DISTINCT quizid FROM {quizaccess_examproctor_log}');
        $total = 0;
        foreach ($quizids as $quizid) {
            $settings = helper::settings((int) $quizid) ?? (object) ['retentiondays' => 0];
            $days = helper::retention_days($settings);
            if ($days <= 0) {
                continue;
            }
            $cutoff = time() - $days * DAYSECS;
            $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                // Quiz gone: rows are orphans.
                $total += $DB->count_records_select('quizaccess_examproctor_log', 'quizid = ?', [$quizid]);
                $DB->delete_records('quizaccess_examproctor_log', ['quizid' => $quizid]);
                $DB->delete_records('quizaccess_examproctor_consent', ['quizid' => $quizid]);
                continue;
            }
            $context = \context_module::instance($cm->id);
            $total += helper::delete_logs($context, 'quizid = :quizid AND timecreated < :cutoff',
                ['quizid' => $quizid, 'cutoff' => $cutoff], true);
        }
        if ($total) {
            mtrace('Deleted ' . $total . ' expired proctoring event(s) and their images.');
        }
    }
}
