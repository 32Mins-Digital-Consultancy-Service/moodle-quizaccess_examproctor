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

use quizaccess_examproctor\hook\event_types;

/**
 * The event types the server accepts, with their violation flag, report label and image limits.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_registry {

    /** Webcam snapshot / evidence frame limits. */
    const CAM_W = 640;
    /** Webcam image height limit. */
    const CAM_H = 480;
    /** Webcam image size limit in bytes. */
    const CAM_BYTES = 512000;

    /** @var array|null cached definitions */
    protected static ?array $types = null;

    /**
     * Core event types: [violation, image allowed]. Types sent by the browser and types written by the server.
     */
    const CORE = [
        // Written by the server.
        'proctoring_started' => [0, 0],
        'consent' => [0, 0],
        'multiple_sessions' => [0, 0],
        'quota_exceeded' => [0, 0],
        'autosubmit_requested' => [0, 0],
        'autosubmit' => [0, 0],
        'webservice_blocked' => [0, 0],
        // Sent by the browser.
        'session_start' => [0, 0],
        'snapshot' => [0, 1],
        'camera_denied' => [1, 0],
        'camera_lost' => [1, 1],
        'camera_covered' => [1, 1],
        'video_frozen' => [1, 1],
        'tab_hidden' => [1, 0],
        'tab_returned' => [0, 0],
        'window_blur' => [1, 0],
        'window_returned' => [0, 0],
        'fullscreen_exit' => [1, 0],
        'fullscreen_unsupported' => [0, 0],
        'copy' => [1, 0],
        'paste' => [1, 0],
        'contextmenu' => [1, 0],
        'analysis_disabled' => [0, 0],
        'extension_error' => [0, 0],
        'autosubmit_now' => [0, 0],
        'heartbeat' => [0, 0],
        'problem_continues' => [0, 1],
    ];

    /** Events that are only ever written by the server, never accepted from the browser. */
    const SERVER_ONLY = ['proctoring_started', 'consent', 'multiple_sessions', 'quota_exceeded',
        'autosubmit_requested', 'autosubmit', 'webservice_blocked'];

    /** Events the report highlights as needing a look although they are not violations. */
    const FLAGGED = ['multiple_sessions', 'quota_exceeded', 'analysis_disabled', 'extension_error', 'fullscreen_unsupported'];

    /**
     * All event types.
     *
     * @return array type => {violation, label, maxw, maxh, maxbytes}
     */
    public static function all(): array {
        if (self::$types !== null) {
            return self::$types;
        }
        $types = [];
        foreach (self::CORE as $type => [$violation, $image]) {
            $types[$type] = (object) [
                'violation' => (bool) $violation,
                'label' => get_string('event_' . $type, 'quizaccess_examproctor'),
                'maxw' => $image ? self::CAM_W : 0,
                'maxh' => $image ? self::CAM_H : 0,
                'maxbytes' => $image ? self::CAM_BYTES : 0,
                'flag' => in_array($type, self::FLAGGED, true),
            ];
        }
        $hook = new event_types();
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        foreach ($hook->get_types() as $type => $def) {
            if (!isset($types[$type])) {
                $types[$type] = $def;
            }
        }
        self::$types = $types;
        return $types;
    }

    /**
     * Types the report highlights for review (core list plus types other plugins flag).
     *
     * @return string[]
     */
    public static function flagged_types(): array {
        $out = [];
        foreach (self::all() as $type => $def) {
            if (!empty($def->flag)) {
                $out[] = $type;
            }
        }
        return $out;
    }

    /**
     * Definition of one type, or null if unknown.
     *
     * @param string $type
     * @return \stdClass|null
     */
    public static function get(string $type): ?\stdClass {
        return self::all()[$type] ?? null;
    }

    /**
     * Label for the report; unknown types (from a plugin that was removed) show their raw name.
     *
     * @param string $type
     * @return string
     */
    public static function label(string $type): string {
        $def = self::get($type);
        return $def ? $def->label : $type;
    }

    /**
     * Reset the cache (tests).
     */
    public static function reset(): void {
        self::$types = null;
    }
}
