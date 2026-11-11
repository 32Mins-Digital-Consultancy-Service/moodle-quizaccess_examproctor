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

/**
 * Admin settings.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $p = 'quizaccess_examproctor';
    $settings->add(new admin_setting_configtext('quizaccess_examproctor/retentiondays',
        get_string('settings_retention', $p), get_string('settings_retention_desc', $p), 30, PARAM_INT));
    $settings->add(new admin_setting_configcheckbox('quizaccess_examproctor/allowautosubmit',
        get_string('settings_allowautosubmit', $p), get_string('settings_allowautosubmit_desc', $p), 0));
    $settings->add(new admin_setting_configcheckbox('quizaccess_examproctor/proctorpreviews',
        get_string('settings_proctorpreviews', $p), get_string('settings_proctorpreviews_desc', $p), 0));
}
