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
 * Lets other plugins add browser-side checks to a proctored attempt page.
 *
 * Listeners add AMD modules with add_extension(). Each module must export init(api, config); the api lets it
 * register camera analysers, show blocking screens and send events (see amd/src/proctor.js).
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Add checks to a proctored quiz attempt page')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class attempt_page {
    /** @var array list of [module, config] */
    protected array $extensions = [];

    /**
     * Constructor.
     *
     * @param \stdClass $settings proctoring settings of the quiz
     * @param \stdClass $attempt the in-progress attempt
     * @param \mod_quiz\quiz_settings $quizobj the quiz
     * @param string $mode 'attempt' (question pages) or 'summary' (summary page)
     * @param bool $preview whether this is a teacher preview
     */
    public function __construct(
        /** @var \stdClass proctoring settings */
        public readonly \stdClass $settings,
        /** @var \stdClass the attempt */
        public readonly \stdClass $attempt,
        /** @var \mod_quiz\quiz_settings the quiz */
        public readonly \mod_quiz\quiz_settings $quizobj,
        /** @var string page mode */
        public readonly string $mode,
        /** @var bool teacher preview */
        public readonly bool $preview,
    ) {
    }

    /**
     * Add an AMD module to start on the page.
     *
     * @param string $module AMD module name, e.g. local_example/checks
     * @param array $config data passed to the module's init()
     */
    public function add_extension(string $module, array $config = []): void {
        $this->extensions[] = ['module' => $module, 'config' => $config];
    }

    /**
     * Modules added by listeners.
     *
     * @return array
     */
    public function get_extensions(): array {
        return $this->extensions;
    }
}
