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
 * Lets other plugins stop a student starting a proctored attempt (for example until an identity check is done).
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Check before a proctored attempt starts')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class new_attempt {
    /** @var string[] messages */
    protected array $messages = [];

    /**
     * Constructor.
     *
     * @param \stdClass $settings proctoring settings of the quiz
     * @param \mod_quiz\quiz_settings $quizobj the quiz
     * @param int $userid the student
     */
    public function __construct(
        /** @var \stdClass proctoring settings */
        public readonly \stdClass $settings,
        /** @var \mod_quiz\quiz_settings the quiz */
        public readonly \mod_quiz\quiz_settings $quizobj,
        /** @var int the student */
        public readonly int $userid,
    ) {
    }

    /**
     * Prevent the attempt with this message (HTML allowed).
     *
     * @param string $message
     */
    public function prevent(string $message): void {
        $this->messages[] = $message;
    }

    /**
     * Messages added by listeners.
     *
     * @return string[]
     */
    public function get_messages(): array {
        return $this->messages;
    }
}
