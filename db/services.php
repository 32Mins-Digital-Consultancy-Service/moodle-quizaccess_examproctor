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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External functions (AJAX) for the proctoring rule.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_examproctor_log_event' => [
        'classname' => 'quizaccess_examproctor\external\log_event',
        'description' => 'Record a proctoring event (and optional image) for the current user\'s quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        // Writes only to the database, never to the session: do not block the quiz's own requests.
        'readonlysession' => true,
    ],
];
