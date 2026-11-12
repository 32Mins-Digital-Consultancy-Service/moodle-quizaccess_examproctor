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
 * Shared helpers.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** Version of the consent notice; bump whenever the consent wording changes. */
    const CONSENT_VERSION = '2026-11-07';

    /** Snapshots may lag by this many intervals before the attempt is flagged. */
    const GAP_INTERVALS = 2;

    /**
     * Proctoring settings of a quiz, or null.
     *
     * @param int $quizid
     * @return \stdClass|null
     */
    public static function settings(int $quizid): ?\stdClass {
        global $DB;
        return $DB->get_record('quizaccess_examproctor', ['quizid' => $quizid]) ?: null;
    }

    /**
     * Whether the camera is used for this quiz (never for possible minors).
     *
     * @param \stdClass $settings
     * @return bool
     */
    public static function camera_on(\stdClass $settings): bool {
        return !empty($settings->requirecamera) && empty($settings->minors);
    }

    /**
     * Effective snapshot interval in seconds.
     *
     * @param \stdClass $settings
     * @return int
     */
    public static function interval(\stdClass $settings): int {
        return max(15, (int) $settings->snapshotinterval);
    }

    /**
     * Whether the site allows auto-submit after violations (site setting, off by default).
     *
     * @return bool
     */
    public static function autosubmit_allowed(): bool {
        return (bool) get_config('quizaccess_examproctor', 'allowautosubmit');
    }

    /**
     * Retention period in days for a quiz (0 = keep).
     *
     * @param \stdClass $settings
     * @return int
     */
    public static function retention_days(\stdClass $settings): int {
        $days = (int) ($settings->retentiondays ?? 0);
        if ($days > 0) {
            return $days;
        }
        $site = get_config('quizaccess_examproctor', 'retentiondays');
        return $site === false ? 30 : max(0, (int) $site);
    }

    /**
     * Number of snapshots expected for an attempt so far.
     *
     * @param \stdClass $settings
     * @param int $timestart
     * @param int $timeend
     * @return int
     */
    public static function expected_snapshots(\stdClass $settings, int $timestart, int $timeend): int {
        if (!self::camera_on($settings) || $timeend <= $timestart) {
            return 0;
        }
        return (int) floor(($timeend - $timestart) / self::interval($settings));
    }

    /**
     * Full names for a set of users.
     *
     * @param int[] $userids
     * @return array userid => full name
     */
    public static function names(array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        $fields = implode(',', array_merge(['id'], \core_user\fields::get_name_fields()));
        $names = [];
        foreach ($DB->get_records_list('user', 'id', array_unique($userids), '', $fields) as $user) {
            $names[$user->id] = fullname($user);
        }
        return $names;
    }

    /**
     * Insert a server-side event row.
     *
     * @param \stdClass $attempt
     * @param string $type
     * @param string $details
     * @param int $violation
     * @return int new id
     */
    public static function log(\stdClass $attempt, string $type, string $details = '', int $violation = 0): int {
        global $DB;
        return $DB->insert_record('quizaccess_examproctor_log', (object) [
            'quizid' => $attempt->quiz,
            'attemptid' => $attempt->id,
            'userid' => $attempt->userid,
            'eventtype' => $type,
            'details' => \core_text::substr($details, 0, 255),
            'isviolation' => $violation,
            'hassnapshot' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Delete events and images of some log rows.
     *
     * @param \context $context module context
     * @param string $where SQL condition on quizaccess_examproctor_log
     * @param array $params
     * @return int rows deleted
     */
    public static function delete_logs(\context $context, string $where, array $params): int {
        global $DB;
        $fs = get_file_storage();
        $ids = $DB->get_fieldset_select('quizaccess_examproctor_log', 'id', $where . ' AND hassnapshot = 1', $params);
        foreach ($ids as $id) {
            $fs->delete_area_files($context->id, 'quizaccess_examproctor', 'snapshots', $id);
        }
        $count = $DB->count_records_select('quizaccess_examproctor_log', $where, $params);
        $DB->delete_records_select('quizaccess_examproctor_log', $where, $params);
        // The consent record shares the quizid / attemptid / timecreated columns, so the same condition applies.
        $DB->delete_records_select('quizaccess_examproctor_consent', $where, $params);
        return $count;
    }
}
