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
 * Lets other plugins add links to the proctoring report and the quiz page.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Add links to the exam proctoring report')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class report_links {
    /** @var array list of [url, label] */
    protected array $links = [];

    /**
     * Constructor.
     *
     * @param \stdClass $settings proctoring settings of the quiz
     * @param object $cm the course module (cm_info or stdClass)
     * @param \context_module $context the module context
     */
    public function __construct(
        /** @var \stdClass proctoring settings */
        public readonly \stdClass $settings,
        /** @var object the course module (cm_info or stdClass) */
        public readonly object $cm,
        /** @var \context_module the module context */
        public readonly \context_module $context,
    ) {
    }

    /**
     * Add a link.
     *
     * @param \moodle_url $url
     * @param string $label
     */
    public function add(\moodle_url $url, string $label): void {
        $this->links[] = [$url, $label];
    }

    /**
     * Links added by listeners.
     *
     * @return array
     */
    public function get_links(): array {
        return $this->links;
    }
}
