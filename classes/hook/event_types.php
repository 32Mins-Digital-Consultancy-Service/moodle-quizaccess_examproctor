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

namespace quizaccess_examproctor\hook;

use core\attribute;

/**
 * Lets other plugins declare the event types their browser checks send.
 *
 * The server only records event types declared by the core or through this hook.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Declare exam proctoring event types')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class event_types {
    /** @var array type => definition */
    protected array $types = [];

    /**
     * Declare an event type.
     *
     * @param string $type up to 30 characters, a-z, 0-9 and _
     * @param bool $violation whether it counts as a violation
     * @param string $label label shown in the report
     * @param int $maximagewidth largest image width accepted with the event (0 = no image)
     * @param int $maximageheight largest image height accepted with the event
     * @param int $maximagebytes largest image size accepted with the event
     * @param bool $flag whether the report should highlight it for review although it is not a violation
     */
    public function add(
        string $type,
        bool $violation,
        string $label,
        int $maximagewidth = 0,
        int $maximageheight = 0,
        int $maximagebytes = 0,
        bool $flag = false
    ): void {
        if (!preg_match('/^[a-z0-9_]{1,30}$/', $type)) {
            throw new \coding_exception('Invalid proctoring event type: ' . $type);
        }
        $this->types[$type] = (object) [
            'violation' => $violation,
            'label' => $label,
            'maxw' => $maximagewidth,
            'maxh' => $maximageheight,
            'maxbytes' => $maximagebytes,
            'flag' => $flag,
        ];
    }

    /**
     * Declared types.
     *
     * @return array
     */
    public function get_types(): array {
        return $this->types;
    }
}
