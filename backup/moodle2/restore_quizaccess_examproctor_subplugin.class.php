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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/**
 * Restores the proctoring settings of a quiz (also used when a quiz is duplicated).
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizaccess_examproctor_subplugin extends restore_mod_quiz_access_subplugin {
    /**
     * Paths.
     *
     * @return restore_path_element[]
     */
    protected function define_quiz_subplugin_structure() {
        return [new restore_path_element('quizaccess_examproctor', $this->get_pathfor('/quizaccess_examproctor'))];
    }

    /**
     * Insert the settings for the new quiz.
     *
     * @param array|stdClass $data
     */
    public function process_quizaccess_examproctor($data) {
        global $DB;
        $data = (object) $data;
        unset($data->id);
        $data->quizid = $this->get_new_parentid('quiz');
        if (!$DB->record_exists('quizaccess_examproctor', ['quizid' => $data->quizid])) {
            $DB->insert_record('quizaccess_examproctor', $data);
        }
    }
}
