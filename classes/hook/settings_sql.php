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
 * Lets other plugins load their per-quiz settings together with the quiz.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[attribute\label('Load extra exam proctoring quiz settings')]
#[attribute\tags('quiz', 'quizaccess_examproctor')]
final class settings_sql {
    /** @var string[] select fields */
    protected array $fields = [];
    /** @var string[] joins */
    protected array $joins = [];
    /** @var array params */
    protected array $params = [];

    /**
     * Constructor.
     *
     * @param int $quizid
     */
    public function __construct(
        /** @var int the quiz id */
        public readonly int $quizid,
    ) {
    }

    /**
     * Add select fields (aliased, unique names) and a LEFT JOIN on quiz.id.
     *
     * @param string $fields e.g. "xx.foo AS myplugin_foo"
     * @param string $join e.g. "LEFT JOIN {myplugin_quiz} xx ON xx.quizid = quiz.id"
     * @param array $params named params
     */
    public function add(string $fields, string $join, array $params = []): void {
        $this->fields[] = $fields;
        $this->joins[] = $join;
        $this->params += $params;
    }

    /**
     * Collected SQL parts.
     *
     * @return array [fields[], joins[], params]
     */
    public function get_parts(): array {
        return [$this->fields, $this->joins, $this->params];
    }
}
