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

namespace quizaccess_examproctor;

use quizaccess_examproctor\local\event_registry;
use quizaccess_examproctor\local\helper;

/**
 * Public API for plugins that extend exam proctoring through its hooks. Use this instead of the local\ classes,
 * which may change between versions.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** Largest webcam image width accepted (pixels). */
    const CAMERA_IMAGE_WIDTH = event_registry::CAM_W;
    /** Largest webcam image height accepted (pixels). */
    const CAMERA_IMAGE_HEIGHT = event_registry::CAM_H;
    /** Largest webcam image size accepted (bytes). */
    const CAMERA_IMAGE_BYTES = event_registry::CAM_BYTES;

    /**
     * Record a server-side event for an attempt.
     *
     * @param \stdClass $attempt quiz_attempts row
     * @param string $type an event type declared by the core or through the event_types hook
     * @param string $details
     * @return int new row id
     */
    public static function record_event(\stdClass $attempt, string $type, string $details = ''): int {
        return helper::log($attempt, $type, $details);
    }

    /**
     * Whether the webcam is used for a quiz's proctoring settings (never for learners who may be under 18).
     *
     * @param \stdClass $settings proctoring settings, as passed to hooks
     * @return bool
     */
    public static function camera_used(\stdClass $settings): bool {
        return helper::camera_on($settings);
    }

    /**
     * Full names for a set of users.
     *
     * @param int[] $userids
     * @return array userid => full name
     */
    public static function user_names(array $userids): array {
        return helper::names($userids);
    }
}
