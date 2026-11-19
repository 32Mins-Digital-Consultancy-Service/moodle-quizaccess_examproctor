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

namespace quizaccess_examproctor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_examproctor\local\attempt_finisher;
use quizaccess_examproctor\local\event_registry;
use quizaccess_examproctor\local\helper;

/**
 * Records a proctoring event for the current user's in-progress attempt.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_event extends external_api {
    /** Events allowed per attempt: one per second of attempt time on average, plus this burst. */
    const EVENT_BURST = 120;

    /** Images allowed per attempt: expected snapshots x 1.5 + one evidence frame per 15 s + this burst. */
    const IMAGE_BURST = 20;

    /** Hard caps per attempt, whatever the attempt length. */
    const MAX_EVENTS = 20000;
    /** Hard cap on images per attempt. */
    const MAX_IMAGES = 3000;

    /** Violations kept per attempt (disk guard only: violations are never dropped by the event quota). */
    const MAX_VIOLATIONS = 2000;

    /** The same violation type again within this many seconds is recorded as a repeat, not a new violation. */
    const REPEAT_SECONDS = 5;

    /** Heartbeats closer together than this are ignored. */
    const HEARTBEAT_MIN_GAP = 20;

    /** Seconds the browser gets to submit the page itself before the server finishes the attempt. */
    const AUTOSUBMIT_GRACE = 20;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt id'),
            'eventtype' => new external_value(PARAM_ALPHANUMEXT, 'Event type'),
            'details' => new external_value(PARAM_TEXT, 'Extra details', VALUE_DEFAULT, ''),
            'imagedata' => new external_value(PARAM_RAW, 'JPEG data URL', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Record the event.
     *
     * @param int $attemptid
     * @param string $eventtype
     * @param string $details
     * @param string $imagedata
     * @return array
     */
    public static function execute(int $attemptid, string $eventtype, string $details = '', string $imagedata = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'eventtype' => $eventtype,
            'details' => $details,
            'imagedata' => $imagedata,
        ]);

        $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], '*', MUST_EXIST);
        if ((int) $attempt->userid !== (int) $USER->id) {
            throw new \invalid_parameter_exception('Not your attempt');
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $result = [
            'status' => 'ignored',
            'violations' => 0,
            'maxviolations' => 0,
            'violation' => false,
            'submitnow' => false,
            'autosubmitted' => false,
            'redirecturl' => '',
        ];

        $settings = helper::settings((int) $quiz->id);
        if (!$settings || empty($settings->enabled) || !empty($attempt->preview) || $attempt->state !== 'inprogress') {
            return $result;
        }

        $type = $params['eventtype'];
        $def = event_registry::get($type);
        if (!$def || in_array($type, event_registry::SERVER_ONLY, true)) {
            throw new \invalid_parameter_exception('Unknown event type');
        }

        // Auto-submit, step 2: the browser asks to finish (no page form available), or it ignored step 1.
        $requested = $DB->get_field(
            'quizaccess_examproctor_log',
            'MIN(timecreated)',
            ['attemptid' => $attempt->id, 'eventtype' => 'autosubmit_requested']
        );
        if ($requested && ($type === 'autosubmit_now' || time() - $requested > self::AUTOSUBMIT_GRACE)) {
            return self::finish($attempt, $context, $result);
        }
        if ($type === 'autosubmit_now') {
            return $result;
        }

        // Heartbeat: one row per attempt, updated in place, so a quiet student never looks silent.
        if ($type === 'heartbeat') {
            return self::heartbeat($attempt, $result);
        }

        // Flood control. Harmless events and violations have separate quotas, so filling one with harmless
        // events can never stop real violations being recorded; images over their quota are dropped, not events.
        $quota = self::check_quota($attempt, $settings, (bool) $def->violation, $params['imagedata'] !== '');
        if ($quota === 'drop') {
            $result['status'] = 'quota';
            return $result;
        }
        if ($quota === 'noimage') {
            if ($type === 'snapshot') {
                $result['status'] = 'quota';
                return $result;
            }
            $params['imagedata'] = '';
        }

        $max = helper::autosubmit_allowed() ? (int) $settings->maxviolations : 0;

        $isviolation = $def->violation ? 1 : 0;
        $details = $params['details'];
        if (
            $isviolation && $DB->record_exists_select(
                'quizaccess_examproctor_log',
                'attemptid = ? AND eventtype = ? AND isviolation = 1 AND timecreated >= ?',
                [$attempt->id, $type, time() - self::REPEAT_SECONDS]
            )
        ) {
            // The same problem reported again within seconds (two tabs, a retry): keep it, but count it once.
            $isviolation = 0;
            $details = '(repeat) ' . $details;
        }

        $log = (object) [
            'quizid' => $quiz->id,
            'attemptid' => $attempt->id,
            'userid' => $USER->id,
            'eventtype' => $type,
            'details' => \core_text::substr($details, 0, 255),
            'isviolation' => $isviolation,
            'hassnapshot' => 0,
            'timecreated' => time(),
        ];
        $log->id = $DB->insert_record('quizaccess_examproctor_log', $log);

        if ($params['imagedata'] !== '') {
            if (!$def->maxbytes) {
                // Images are only accepted where the event type declares them.
                $DB->delete_records('quizaccess_examproctor_log', ['id' => $log->id]);
                throw new \invalid_parameter_exception('This event type takes no image');
            }
            if (self::store_image($context, (int) $log->id, $params['imagedata'], $def)) {
                $DB->set_field('quizaccess_examproctor_log', 'hassnapshot', 1, ['id' => $log->id]);
            } else if ($type === 'snapshot') {
                // A snapshot without a valid image is meaningless; a violation without one still counts.
                $DB->delete_records('quizaccess_examproctor_log', ['id' => $log->id]);
                throw new \invalid_parameter_exception('Invalid image');
            }
        }

        if ($type === 'session_start') {
            self::check_sessions($attempt);
        }

        if ($isviolation) {
            \quizaccess_examproctor\event\violation_recorded::create([
                'objectid' => $attempt->id,
                'relateduserid' => $attempt->userid,
                'context' => $context,
                'other' => ['eventtype' => $type, 'quizid' => (int) $quiz->id],
            ])->trigger();
        }

        $violations = $DB->count_records('quizaccess_examproctor_log', [
            'attemptid' => $attempt->id,
            'isviolation' => 1,
        ]);

        $result['status'] = 'ok';
        $result['violations'] = $violations;
        $result['maxviolations'] = $max;
        $result['violation'] = (bool) $isviolation;

        // Auto-submit, step 1: ask the browser to submit the page so answers typed since the last autosave are kept.
        // If it does not, the next call, the next page load or the enforce_autosubmit task (every minute) finishes it.
        if ($isviolation && $max > 0 && $violations >= $max && !$requested) {
            helper::log($attempt, 'autosubmit_requested', 'Violation limit reached (' . $violations . ')');
            $result['submitnow'] = true;
        }

        return $result;
    }

    /**
     * Finish the attempt on the server.
     *
     * @param \stdClass $attempt
     * @param \context_module $context
     * @param array $result
     * @return array
     */
    protected static function finish(\stdClass $attempt, \context_module $context, array $result): array {
        try {
            $url = attempt_finisher::finish((int) $attempt->id);
            helper::log($attempt, 'autosubmit', 'Violation limit reached');
            \quizaccess_examproctor\event\attempt_autosubmitted::create([
                'objectid' => $attempt->id,
                'relateduserid' => $attempt->userid,
                'context' => $context,
                'other' => ['quizid' => (int) $attempt->quiz],
            ])->trigger();
            $result['status'] = 'ok';
            $result['autosubmitted'] = true;
            $result['redirecturl'] = $url->out(false);
        } catch (\Throwable $e) {
            debugging('Proctoring auto-submit failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $result;
    }

    /**
     * Per-attempt quotas.
     *
     * @param \stdClass $attempt
     * @param \stdClass $settings
     * @param bool $violation
     * @param bool $withimage
     * @return string '' (ok), 'noimage' (record without the image) or 'drop'
     */
    protected static function check_quota(
        \stdClass $attempt,
        \stdClass $settings,
        bool $violation,
        bool $withimage
    ): string {
        global $DB;

        $elapsed = max(0, time() - (int) $attempt->timestart);
        $reason = '';
        $outcome = '';
        if ($violation) {
            if (
                $DB->count_records('quizaccess_examproctor_log', ['attemptid' => $attempt->id, 'isviolation' => 1])
                    >= self::MAX_VIOLATIONS
            ) {
                $reason = 'violations';
                $outcome = 'drop';
            }
        } else {
            $others = $DB->count_records('quizaccess_examproctor_log', ['attemptid' => $attempt->id, 'isviolation' => 0]);
            if ($others >= min(self::MAX_EVENTS, $elapsed + self::EVENT_BURST)) {
                $reason = 'events';
                $outcome = 'drop';
            }
        }
        if ($outcome === '' && $withimage) {
            $images = $DB->count_records('quizaccess_examproctor_log', ['attemptid' => $attempt->id, 'hassnapshot' => 1]);
            $expected = helper::expected_snapshots($settings, (int) $attempt->timestart, time());
            $allowed = (int) ceil($expected * 1.5) + intdiv($elapsed, 15) + self::IMAGE_BURST;
            if ($images >= min(self::MAX_IMAGES, $allowed)) {
                $reason = 'images';
                $outcome = 'noimage';
            }
        }
        if (
            $reason !== '' && !$DB->record_exists(
                'quizaccess_examproctor_log',
                ['attemptid' => $attempt->id, 'eventtype' => 'quota_exceeded', 'details' => $reason]
            )
        ) {
            helper::log($attempt, 'quota_exceeded', $reason);
        }
        return $outcome;
    }

    /**
     * Record a heartbeat by updating the attempt's single heartbeat row.
     *
     * @param \stdClass $attempt
     * @param array $result
     * @return array
     */
    protected static function heartbeat(\stdClass $attempt, array $result): array {
        global $DB;
        $row = $DB->get_record('quizaccess_examproctor_log', ['attemptid' => $attempt->id, 'eventtype' => 'heartbeat']);
        if (!$row) {
            helper::log($attempt, 'heartbeat', '1');
        } else if (time() - (int) $row->timecreated >= self::HEARTBEAT_MIN_GAP) {
            $DB->update_record('quizaccess_examproctor_log', (object) ['id' => $row->id, 'timecreated' => time(),
                'details' => (string) ((int) $row->details + 1)]);
        }
        $result['status'] = 'ok';
        return $result;
    }

    /**
     * Flag an attempt opened in more than one browser tab or device.
     *
     * @param \stdClass $attempt
     */
    protected static function check_sessions(\stdClass $attempt): void {
        global $DB;
        $sessions = $DB->count_records(
            'quizaccess_examproctor_log',
            ['attemptid' => $attempt->id, 'eventtype' => 'session_start']
        );
        if ($sessions >= 2) {
            helper::log($attempt, 'multiple_sessions', $sessions . ' browser sessions');
        }
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok, ignored or quota'),
            'violations' => new external_value(PARAM_INT, 'Violations so far in this attempt'),
            'maxviolations' => new external_value(PARAM_INT, 'Violation limit (0 = none)'),
            'violation' => new external_value(PARAM_BOOL, 'Whether this event counted as a violation'),
            'submitnow' => new external_value(PARAM_BOOL, 'The browser should submit the attempt now'),
            'autosubmitted' => new external_value(PARAM_BOOL, 'Whether the attempt was submitted by the server'),
            'redirecturl' => new external_value(PARAM_RAW, 'Where to send the user after auto-submit'),
        ]);
    }

    /**
     * Validate and store a JPEG data URL for a log row.
     *
     * @param \context $context
     * @param int $logid
     * @param string $imagedata
     * @param \stdClass $def event type definition with image limits
     * @return bool
     */
    protected static function store_image(\context $context, int $logid, string $imagedata, \stdClass $def): bool {
        global $USER;

        $prefix = 'data:image/jpeg;base64,';
        if (strpos($imagedata, $prefix) !== 0) {
            return false;
        }
        $binary = base64_decode(substr($imagedata, strlen($prefix)), true);
        if ($binary === false || $binary === '' || strlen($binary) > $def->maxbytes) {
            return false;
        }
        $info = @getimagesizefromstring($binary);
        if (
            !$info || $info['mime'] !== 'image/jpeg' || $info[0] > $def->maxw || $info[1] > $def->maxh
                || $info[0] < 16 || $info[1] < 16
        ) {
            return false;
        }

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'quizaccess_examproctor',
            'filearea' => 'snapshots',
            'itemid' => $logid,
            'filepath' => '/',
            'filename' => 'snap_' . $logid . '.jpg',
            'userid' => $USER->id,
        ], $binary);

        return true;
    }
}
