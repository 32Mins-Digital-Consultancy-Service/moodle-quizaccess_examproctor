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
 * Lets other plugins add fields to the proctoring section of the quiz settings form.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Add fields to the exam proctoring quiz settings')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class settings_form {
    /**
     * Constructor.
     *
     * @param \mod_quiz_mod_form $quizform the quiz settings form
     * @param \MoodleQuickForm $mform the form being built
     */
    public function __construct(
        /** @var \mod_quiz_mod_form the quiz settings form */
        public readonly \mod_quiz_mod_form $quizform,
        /** @var \MoodleQuickForm the form being built */
        public readonly \MoodleQuickForm $mform,
    ) {
    }
}
