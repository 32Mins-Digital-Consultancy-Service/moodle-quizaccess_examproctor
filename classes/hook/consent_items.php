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
 * Lets other plugins add lines to the consent notice shown before a proctored attempt.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Add lines to the exam proctoring consent notice')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class consent_items {
    /** @var string[] lines */
    protected array $items = [];

    /**
     * Constructor.
     *
     * @param \stdClass $settings proctoring settings of the quiz
     * @param \mod_quiz\quiz_settings $quizobj the quiz
     */
    public function __construct(
        /** @var \stdClass proctoring settings */
        public readonly \stdClass $settings,
        /** @var \mod_quiz\quiz_settings the quiz */
        public readonly \mod_quiz\quiz_settings $quizobj,
    ) {
    }

    /**
     * Add a line (plain text).
     *
     * @param string $item
     */
    public function add(string $item): void {
        $this->items[] = $item;
    }

    /**
     * Lines added by listeners.
     *
     * @return string[]
     */
    public function get_items(): array {
        return $this->items;
    }
}
