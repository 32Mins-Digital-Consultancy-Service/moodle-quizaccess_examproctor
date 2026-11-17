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

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Backs up the proctoring settings of a quiz (settings only: events and images are personal data and stay).
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizaccess_examproctor_subplugin extends backup_mod_quiz_access_subplugin {

    /**
     * Structure.
     *
     * @return backup_subplugin_element
     */
    protected function define_quiz_subplugin_structure() {
        parent::define_quiz_subplugin_structure();

        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('quizaccess_examproctor', null, ['enabled', 'minors', 'requirecamera',
            'snapshotinterval', 'requirefullscreen', 'fullscreenstrict', 'blockclipboard', 'maxviolations', 'retentiondays']);

        $subplugin->add_child($wrapper);
        $wrapper->add_child($settings);
        $settings->set_source_table('quizaccess_examproctor', ['quizid' => backup::VAR_ACTIVITYID]);

        return $subplugin;
    }
}
