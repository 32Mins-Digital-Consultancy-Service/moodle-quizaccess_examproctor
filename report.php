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
 * Proctoring report: per-attempt summary (including attempts that sent nothing) and per-attempt timeline.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use quizaccess_examproctor\local\event_registry;
use quizaccess_examproctor\local\helper;
use quizaccess_examproctor\local\report_data;

$cmid = required_param('cmid', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$what = optional_param('what', 'summary', PARAM_ALPHA);
$search = trim(optional_param('search', '', PARAM_NOTAGS));
$flaggedonly = optional_param('flagged', 0, PARAM_BOOL);
$violationsonly = optional_param('violationsonly', 0, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$sort = optional_param('sort', 'needs', PARAM_ALPHA);
if (!in_array($sort, \quizaccess_examproctor\local\report_data::SORTS, true)) {
    $sort = 'needs';
}

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quizaccess/examproctor:viewreport', $context);

$p = 'quizaccess_examproctor';
$settings = helper::settings((int) $cm->instance) ?? (object) ['requirecamera' => 0, 'minors' => 0,
    'snapshotinterval' => 60, 'retentiondays' => 0, 'enabled' => 0];
$baseparams = ['cmid' => $cmid];
if ($search !== '') {
    $baseparams['search'] = $search;
}
if ($flaggedonly) {
    $baseparams['flagged'] = 1;
}
if ($sort !== 'needs') {
    $baseparams['sort'] = $sort;
}
$url = new moodle_url('/mod/quiz/accessrule/examproctor/report.php', $baseparams + ($attemptid ? ['attemptid' => $attemptid] : []));
$PAGE->set_url($url);
$PAGE->set_title(get_string('reporttitle', $p));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

// Delete the evidence of one attempt (events and images).
if ($delete) {
    require_capability('quizaccess/examproctor:deleteevidence', $context);
    $attempt = $DB->get_record('quiz_attempts', ['id' => $delete, 'quiz' => $cm->instance], '*', MUST_EXIST);
    $backurl = new moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $cmid, 'attemptid' => $delete]);
    if ($confirm && confirm_sesskey()) {
        helper::delete_logs($context, 'attemptid = :attemptid', ['attemptid' => $attempt->id]);
        \quizaccess_examproctor\event\evidence_deleted::create([
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => $context,
            'other' => ['quizid' => (int) $cm->instance],
        ])->trigger();
        redirect(
            new moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $cmid]),
            get_string('evidencedeleted', $p),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    $names = helper::names([$attempt->userid]);
    echo $OUTPUT->confirm(
        get_string('deleteevidenceconfirm', $p, $names[$attempt->userid] ?? $attempt->userid),
        new moodle_url(
            '/mod/quiz/accessrule/examproctor/report.php',
            ['cmid' => $cmid, 'delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]
        ),
        $backurl
    );
    echo $OUTPUT->footer();
    die;
}

// Downloads: the attempt summary with flags, or every event of the quiz.
if (in_array($download, ['csv', 'excel', 'ods', 'json'], true)) {
    require_sesskey();
    $filename = clean_filename(format_string($cm->name) . '_proctoring_' . $what . '_' . userdate(time(), '%Y%m%d'));
    if ($what === 'events') {
        $sql = "SELECT l.id, l.userid, l.eventtype, l.details, l.isviolation, l.hassnapshot, l.timecreated, a.attempt,
                       u.email
                  FROM {quizaccess_examproctor_log} l
                  JOIN {quiz_attempts} a ON a.id = l.attemptid
                  JOIN {user} u ON u.id = l.userid
                 WHERE l.quizid = :quizid
              ORDER BY l.userid, a.attempt, l.timecreated, l.id";
        $rs = $DB->get_recordset_sql($sql, ['quizid' => $cm->instance]);
        $names = [];
        $columns = ['student' => get_string('user', $p), 'email' => get_string('email'),
            'attempt' => get_string('attempt', $p), 'time' => get_string('time', $p), 'event' => get_string('event', $p),
            'violation' => get_string('violation', $p), 'details' => get_string('details', $p),
            'image' => get_string('snapshot', $p)];
        \core\dataformat::download_data($filename, $download, $columns, $rs, function ($log) use (&$names) {
            if (!isset($names[$log->userid])) {
                $names += helper::names([$log->userid]);
            }
            return [
                'student' => $names[$log->userid] ?? $log->userid,
                'email' => $log->email,
                'attempt' => $log->attempt,
                'time' => userdate($log->timecreated, '%Y-%m-%d %H:%M:%S'),
                'event' => event_registry::label($log->eventtype),
                'violation' => $log->isviolation ? get_string('yes') : get_string('no'),
                'details' => $log->details,
                'image' => $log->hassnapshot ? get_string('yes') : get_string('no'),
            ];
        });
        $rs->close();
    } else {
        [$sql, , $params] = report_data::summary_sql((int) $cm->instance, $search, (bool) $flaggedonly);
        $rows = $DB->get_records_sql($sql, $params);
        $names = helper::names(array_map(fn($r) => $r->userid, $rows));
        $columns = ['student' => get_string('user', $p), 'attempt' => get_string('attempt', $p),
            'state' => get_string('state', $p), 'violations' => get_string('violations', $p),
            'snapshots' => get_string('snapshots', $p), 'expected' => get_string('expectedsnapshots', $p),
            'sessions' => get_string('sessions', $p), 'flags' => get_string('flags', $p)];
        \core\dataformat::download_data($filename, $download, $columns, $rows, function ($row) use ($names, $settings, $p) {
            $a = report_data::assess($row, $settings);
            $flags = [];
            if ($a->nodata) {
                $flags[] = get_string('flag_nodata', $p);
            }
            if ($a->gap) {
                $flags[] = get_string('flag_gap', $p);
            }
            if ($a->multisession) {
                $flags[] = get_string('flag_multisession', $p);
            }
            return [
                'student' => $names[$row->userid] ?? $row->userid,
                'attempt' => $row->attempt,
                'state' => $row->state,
                'violations' => (int) $row->violations,
                'snapshots' => (int) $row->snapshots,
                'expected' => $a->expected,
                'sessions' => (int) $row->sessions,
                'flags' => implode('; ', $flags),
            ];
        });
    }
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reporttitle', $p) . ': ' . format_string($cm->name));

if (empty($settings->enabled)) {
    echo $OUTPUT->notification(get_string('notenabled', $p), 'info');
}
$days = helper::retention_days($settings);
echo html_writer::tag(
    'p',
    $days ? get_string('retentionnotice', $p, $days) : get_string('retentionkeep', $p),
    ['class' => 'text-muted small']
);

if ($attemptid) {
    // Detail view: one attempt's timeline.
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $cm->instance], '*', MUST_EXIST);
    $names = helper::names([$attempt->userid]);
    if (!$page) {
        \quizaccess_examproctor\event\evidence_viewed::create([
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => $context,
            'other' => ['quizid' => (int) $cm->instance],
        ])->trigger();
    }

    echo html_writer::tag('p', html_writer::link(new moodle_url(
        '/mod/quiz/accessrule/examproctor/report.php',
        ['cmid' => $cmid]
    ), get_string('backtosummary', $p)));
    echo $OUTPUT->heading(s($names[$attempt->userid] ?? $attempt->userid) . ' - ' .
        get_string('attempt', $p) . ' ' . $attempt->attempt, 3);

    $toggle = new moodle_url($url, ['violationsonly' => $violationsonly ? 0 : 1]);
    $links = [html_writer::link(
        $toggle,
        get_string($violationsonly ? 'showallevents' : 'showviolationsonly', $p),
        ['class' => 'btn btn-secondary btn-sm me-2']
    )];
    if (has_capability('quizaccess/examproctor:deleteevidence', $context)) {
        $links[] = html_writer::link(
            new moodle_url(
                '/mod/quiz/accessrule/examproctor/report.php',
                ['cmid' => $cmid, 'delete' => $attempt->id]
            ),
            get_string('deleteevidence', $p),
            ['class' => 'btn btn-outline-danger btn-sm']
        );
    }
    echo html_writer::div(implode('', $links), 'mb-3');

    $conditions = ['attemptid' => $attempt->id];
    if ($violationsonly) {
        $conditions['isviolation'] = 1;
    }
    $perpage = 100;
    $total = $DB->count_records('quizaccess_examproctor_log', $conditions);
    $logs = $DB->get_records(
        'quizaccess_examproctor_log',
        $conditions,
        'timecreated ASC, id ASC',
        '*',
        $page * $perpage,
        $perpage
    );
    if (!$logs) {
        echo $OUTPUT->notification(get_string('noevents', $p), 'info');
    } else {
        $table = new html_table();
        $table->head = [get_string('time', $p), get_string('event', $p), get_string('details', $p),
            get_string('snapshot', $p)];
        $table->attributes['class'] = 'generaltable';
        foreach ($logs as $log) {
            $label = s(event_registry::label($log->eventtype));
            if ($log->isviolation) {
                $label = html_writer::span($label, 'text-danger fw-bold');
            } else if (in_array($log->eventtype, event_registry::FLAGGED, true)) {
                $label = html_writer::span($label, 'text-warning fw-bold');
            }
            $snapshot = '';
            if ($log->hassnapshot) {
                $imgurl = moodle_url::make_pluginfile_url(
                    $context->id,
                    $p,
                    'snapshots',
                    $log->id,
                    '/',
                    'snap_' . $log->id . '.jpg'
                );
                $snapshot = html_writer::link($imgurl, html_writer::empty_tag('img', [
                    'src' => $imgurl->out(false),
                    'alt' => get_string('snapshotof', $p, userdate($log->timecreated)),
                    'loading' => 'lazy',
                    'style' => 'width:120px;height:auto',
                    'class' => 'img-thumbnail',
                ]), ['target' => '_blank']);
            }
            $table->data[] = [
                userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                $label,
                s($log->details),
                $snapshot,
            ];
        }
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($url, ['violationsonly' => $violationsonly]));
    }
} else {
    // Summary view: every attempt, silent ones first.
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
        'class' => 'd-flex flex-wrap gap-2 align-items-center mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
    echo html_writer::label(get_string('search'), 'examproctor-search', false, ['class' => 'visually-hidden']);
    echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'search', 'id' => 'examproctor-search',
        'value' => $search, 'placeholder' => get_string('searchstudent', $p), 'class' => 'form-control w-auto']);
    echo html_writer::checkbox('flagged', 1, (bool) $flaggedonly, get_string('flaggedonly', $p), ['class' => 'ms-2']);
    $sorts = [];
    foreach (\quizaccess_examproctor\local\report_data::SORTS as $key) {
        $sorts[$key] = get_string('sort_' . $key, $p);
    }
    echo html_writer::label(get_string('sortby', $p), 'examproctor-sort', false, ['class' => 'ms-2']);
    echo html_writer::select($sorts, 'sort', $sort, false, ['id' => 'examproctor-sort', 'class' => 'form-select w-auto']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');

    $dl = [];
    foreach (['summary' => 'downloadsummary', 'events' => 'downloadevents'] as $kind => $str) {
        foreach (['csv' => 'CSV', 'excel' => 'Excel'] as $format => $name) {
            $dl[] = html_writer::link(
                new moodle_url($url, ['download' => $format, 'what' => $kind, 'sesskey' => sesskey()]),
                get_string($str, $p, $name),
                ['class' => 'btn btn-outline-secondary btn-sm me-2 mb-2']
            );
        }
    }
    echo html_writer::div(implode('', $dl), 'mb-2');

    [$sql, $countsql, $params] = report_data::summary_sql((int) $cm->instance, $search, (bool) $flaggedonly, $sort);
    $perpage = 50;
    $total = $DB->count_records_sql($countsql, $params);
    $rows = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    if (!$rows) {
        echo $OUTPUT->notification(get_string('noattempts', $p), 'info');
    } else {
        $names = helper::names(array_map(fn($r) => $r->userid, $rows));
        $table = new html_table();
        $table->head = [get_string('user', $p), get_string('attempt', $p), get_string('livestatus', $p),
            get_string('integrity', $p), get_string('snapshotsreceived', $p), get_string('flags', $p), ''];
        $table->attributes['class'] = 'generaltable';
        foreach ($rows as $row) {
            $a = report_data::assess($row, $settings);
            $flags = [];
            if ($a->nodata) {
                $flags[] = html_writer::span(get_string('flag_nodata', $p), 'badge bg-danger');
            }
            if ($a->gap) {
                $flags[] = html_writer::span(get_string('flag_gap', $p), 'badge bg-danger');
            }
            if ($a->multisession) {
                $flags[] = html_writer::span(get_string('flag_multisession', $p), 'badge bg-warning text-dark');
            }
            if ((int) $row->flags > 0) {
                $flags[] = html_writer::span(get_string('flag_other', $p, (int) $row->flags), 'badge bg-warning text-dark');
            }
            $status = $row->state === 'inprogress'
                ? html_writer::span(s($a->live), $a->liveok ? 'text-success' : 'text-danger fw-bold')
                : get_string('state' . $row->state, 'quiz');
            $snaps = (int) $row->snapshots . ($a->expected ? ' / ' . $a->expected : '');
            $table->data[] = [
                s($names[$row->userid] ?? $row->userid),
                $row->attempt,
                $status,
                get_string('integrity_line', $p, (object) ['violations' => (int) $row->violations, 'flags' => (int) $row->flags,
                    'missing' => max(0, $a->expected - (int) $row->snapshots), 'sessions' => (int) $row->sessions]),
                $snaps,
                implode(' ', $flags),
                html_writer::link(new moodle_url(
                    '/mod/quiz/accessrule/examproctor/report.php',
                    ['cmid' => $cmid, 'attemptid' => $row->attemptid]
                ), get_string('viewdetails', $p)),
            ];
        }
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
        echo html_writer::tag('p', get_string('flagshelp', $p), ['class' => 'small text-muted']);
    }
}

echo $OUTPUT->footer();
