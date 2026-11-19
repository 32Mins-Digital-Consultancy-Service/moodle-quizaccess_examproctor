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
 * Submits an attempt in a way that works on Moodle 4.5 and on 5.x, where process_finish() is deprecated.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_finisher {
    /**
     * Submit (and grade) an in-progress attempt without processing new responses.
     *
     * @param int $attemptid
     * @return \moodle_url review URL
     */
    public static function finish(int $attemptid): \moodle_url {
        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $now = time();
        if (method_exists($attemptobj, 'process_submit')) {
            // Moodle 5.0+ (MDL-68806).
            $attemptobj->process_submit($now, false);
            if (method_exists($attemptobj, 'process_grade_submission')) {
                $attemptobj->process_grade_submission($now);
            }
        } else {
            $attemptobj->process_finish($now, false);
        }
        return $attemptobj->review_url();
    }
}
