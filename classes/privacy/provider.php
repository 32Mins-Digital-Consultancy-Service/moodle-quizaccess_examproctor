<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_examproctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the exam proctoring rule.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** Component name used for the file area. */
    const COMPONENT = 'quizaccess_examproctor';

    /**
     * Describe the personal data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_examproctor_log', [
            'userid' => 'privacy:metadata:log:userid',
            'quizid' => 'privacy:metadata:log:quizid',
            'attemptid' => 'privacy:metadata:log:attemptid',
            'eventtype' => 'privacy:metadata:log:eventtype',
            'details' => 'privacy:metadata:log:details',
            'isviolation' => 'privacy:metadata:log:isviolation',
            'hassnapshot' => 'privacy:metadata:log:hassnapshot',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        $collection->add_database_table('quizaccess_examproctor_consent', [
            'userid' => 'privacy:metadata:consent:userid',
            'attemptid' => 'privacy:metadata:consent:attemptid',
            'version' => 'privacy:metadata:consent:version',
            'notice' => 'privacy:metadata:consent:notice',
            'timecreated' => 'privacy:metadata:consent:timecreated',
        ], 'privacy:metadata:consent');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        $collection->add_subsystem_link('core_sessions', [], 'privacy:metadata:session');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quizaccess_examproctor_log} l ON l.quizid = cm.instance
                 WHERE l.userid = :userid";
        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT l.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quizaccess_examproctor_log} l ON l.quizid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);

    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $logs = $DB->get_records('quizaccess_examproctor_log',
                ['quizid' => $cm->instance, 'userid' => $userid], 'timecreated ASC');

            $subcontext = [get_string('pluginname', 'quizaccess_examproctor')];
            $events = [];
            foreach ($logs as $log) {
                $events[] = (object) [
                    'attemptid' => $log->attemptid,
                    'eventtype' => $log->eventtype,
                    'details' => $log->details,
                    'isviolation' => transform::yesno($log->isviolation),
                    'timecreated' => transform::datetime($log->timecreated),
                ];
                if ($log->hassnapshot) {
                    writer::with_context($context)->export_area_files(
                        array_merge($subcontext, ['snapshots', $log->id]),
                        self::COMPONENT, 'snapshots', $log->id);
                }
            }
            $consents = [];
            foreach ($DB->get_records('quizaccess_examproctor_consent', ['quizid' => $cm->instance, 'userid' => $userid],
                    'timecreated ASC') as $c) {
                $consents[] = (object) ['attemptid' => $c->attemptid, 'version' => $c->version, 'notice' => $c->notice,
                    'timecreated' => transform::datetime($c->timecreated)];
            }
            if ($events || $consents) {
                writer::with_context($context)->export_data($subcontext, (object) ['events' => $events,
                    'consents' => $consents]);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records('quizaccess_examproctor_log', ['quizid' => $cm->instance]);
        $DB->delete_records('quizaccess_examproctor_consent', ['quizid' => $cm->instance]);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, 'snapshots');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_for_users_in_context($context, [$userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_for_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Delete logs and snapshot files for the given users in a quiz context.
     *
     * @param \context $context
     * @param int[] $userids
     */
    protected static function delete_for_users_in_context(\context $context, array $userids): void {
        global $DB;

        if (!$context instanceof \context_module || empty($userids)) {
            return;
        }
        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['quizid' => $cm->instance] + $inparams;
        $logids = $DB->get_fieldset_select('quizaccess_examproctor_log', 'id',
            "quizid = :quizid AND userid $insql", $params);

        $fs = get_file_storage();
        foreach ($logids as $logid) {
            $fs->delete_area_files($context->id, self::COMPONENT, 'snapshots', $logid);
        }
        $DB->delete_records_select('quizaccess_examproctor_log', "quizid = :quizid AND userid $insql", $params);
        $DB->delete_records_select('quizaccess_examproctor_consent', "quizid = :quizid AND userid $insql", $params);
    }
}
