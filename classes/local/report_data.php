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

namespace quizaccess_examproctor\local;

/**
 * Data for the proctoring report: one row per attempt, including attempts with no proctoring data at all.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_data {

    /**
     * SQL for the summary rows.
     *
     * @param int $quizid
     * @param string $search part of the student's name or e-mail
     * @param bool $flaggedonly only attempts with violations or flags
     * @return array [select sql, count sql, params]
     */
    public static function summary_sql(int $quizid, string $search = '', bool $flaggedonly = false): array {
        global $DB;

        $flagged = "'" . implode("','", event_registry::FLAGGED) . "'";
        $params = ['quizid' => $quizid];
        $where = 'a.quiz = :quizid AND a.preview = 0';
        if ($search !== '') {
            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $where .= ' AND (' . $DB->sql_like($fullname, ':s1', false) . ' OR ' . $DB->sql_like('u.email', ':s2', false) . ')';
            $params['s1'] = '%' . $DB->sql_like_escape($search) . '%';
            $params['s2'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        $from = "FROM {quiz_attempts} a
                 JOIN {user} u ON u.id = a.userid
            LEFT JOIN {quizaccess_examproctor_log} l ON l.attemptid = a.id
                WHERE $where";
        $group = 'GROUP BY a.id, a.attempt, a.userid, a.state, a.timestart, a.timefinish';
        $having = $flaggedonly
            ? "HAVING SUM(CASE WHEN l.isviolation = 1 OR l.eventtype IN ($flagged) THEN 1 ELSE 0 END) > 0
                   OR SUM(CASE WHEN l.eventtype = 'session_start' THEN 1 ELSE 0 END) = 0"
            : '';
        $select = "SELECT a.id AS attemptid, a.attempt, a.userid, a.state, a.timestart, a.timefinish,
                          COUNT(l.id) AS events,
                          SUM(CASE WHEN l.isviolation = 1 THEN 1 ELSE 0 END) AS violations,
                          SUM(CASE WHEN l.eventtype = 'snapshot' THEN 1 ELSE 0 END) AS snapshots,
                          SUM(CASE WHEN l.hassnapshot = 1 THEN 1 ELSE 0 END) AS images,
                          SUM(CASE WHEN l.eventtype = 'session_start' THEN 1 ELSE 0 END) AS sessions,
                          SUM(CASE WHEN l.eventtype IN ($flagged) THEN 1 ELSE 0 END) AS flags,
                          MAX(CASE WHEN l.eventtype = 'proctoring_started' OR l.eventtype = 'consent' THEN 0
                                   ELSE l.timecreated END) AS lastevent
                     $from $group $having
                 ORDER BY CASE WHEN SUM(CASE WHEN l.eventtype = 'session_start' THEN 1 ELSE 0 END) = 0 THEN 0 ELSE 1 END,
                          violations DESC, a.timestart DESC";
        $count = "SELECT COUNT(1) FROM (SELECT a.id $from $group $having) t";
        return [$select, $count, $params];
    }

    /**
     * Flags and status for one summary row.
     *
     * @param \stdClass $row summary row
     * @param \stdClass $settings proctoring settings
     * @return \stdClass {nodata, gap, expected, multisession, live, liveok}
     */
    public static function assess(\stdClass $row, \stdClass $settings): \stdClass {
        $end = $row->state === 'inprogress' ? time() : (int) ($row->timefinish ?: time());
        $expected = helper::expected_snapshots($settings, (int) $row->timestart, $end);
        $out = (object) [
            'nodata' => (int) $row->sessions === 0,
            'expected' => $expected,
            'gap' => $expected > 0 && (int) $row->snapshots < $expected - helper::GAP_INTERVALS,
            'multisession' => (int) $row->sessions > 1,
            'live' => '',
            'liveok' => true,
        ];
        if ($row->state === 'inprogress') {
            $last = (int) $row->lastevent;
            $silentafter = helper::interval($settings) * 2 + 30;
            if (!$last) {
                $out->live = get_string('live_nodata', 'quizaccess_examproctor');
                $out->liveok = false;
            } else if (time() - $last > $silentafter) {
                $out->live = get_string('live_silent', 'quizaccess_examproctor', format_time(time() - $last));
                $out->liveok = false;
            } else {
                $out->live = get_string('live_active', 'quizaccess_examproctor', format_time(max(1, time() - $last)));
            }
        }
        return $out;
    }
}
