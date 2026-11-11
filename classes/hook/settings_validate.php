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
 * Lets other plugins validate their proctoring settings.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Validate exam proctoring quiz settings')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class settings_validate {
    /** @var array field name => error message */
    protected array $errors = [];

    /**
     * Constructor.
     *
     * @param array $data submitted form data
     * @param \mod_quiz_mod_form $quizform the quiz settings form
     */
    public function __construct(
        /** @var array submitted form data */
        public readonly array $data,
        /** @var \mod_quiz_mod_form the quiz settings form */
        public readonly \mod_quiz_mod_form $quizform,
    ) {
    }

    /**
     * Report an error on a field.
     *
     * @param string $field
     * @param string $message
     */
    public function add_error(string $field, string $message): void {
        $this->errors[$field] = $message;
    }

    /**
     * Errors reported by listeners.
     *
     * @return array
     */
    public function get_errors(): array {
        return $this->errors;
    }
}
