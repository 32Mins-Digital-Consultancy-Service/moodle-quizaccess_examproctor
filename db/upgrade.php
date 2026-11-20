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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for the exam proctoring rule.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_quizaccess_examproctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026102100) {
        // Face / head-pose detection settings (0.2.0).
        $table = new xmldb_table('quizaccess_examproctor');
        $fields = [
            new xmldb_field('detectface', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'maxviolations'),
            new xmldb_field('maxyaw', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '30', 'detectface'),
            new xmldb_field('maxpitch', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '25', 'maxyaw'),
            new xmldb_field('facegrace', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '3', 'maxpitch'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026102100, 'quizaccess', 'examproctor');
    }

    if ($oldversion < 2026102600) {
        // Camera-based motion detection settings (0.3.0).
        $table = new xmldb_table('quizaccess_examproctor');
        $fields = [
            new xmldb_field('detectmotion', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'facegrace'),
            new xmldb_field('motionsens', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'detectmotion'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026102600, 'quizaccess', 'examproctor');
    }

    if ($oldversion < 2026102700) {
        // Identity verification (0.4.0): per-quiz mode and the submissions table.
        $table = new xmldb_table('quizaccess_examproctor');
        $field = new xmldb_field('idmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'motionsens');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('quizaccess_examproctor_id');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('note', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('submissions', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('quizid', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('quiz_user', XMLDB_INDEX_UNIQUE, ['quizid', 'userid']);
            $table->add_index('timesubmitted', XMLDB_INDEX_NOTUNIQUE, ['timesubmitted']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026102700, 'quizaccess', 'examproctor');
    }

    if ($oldversion < 2026103000) {
        // Same-person identity check (0.5.0).
        $table = new xmldb_table('quizaccess_examproctor');
        $fields = [
            new xmldb_field('detectidentity', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'idmode'),
            new xmldb_field('identitysens', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'detectidentity'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026103000, 'quizaccess', 'examproctor');
    }

    if ($oldversion < 2026110700) {
        // 1.0.0: camera-analysis, identity, microphone and screen settings are no longer part of this plugin;
        // other plugins can extend this one through hooks. The per-quiz values are kept in a config value for
        // them to import; identity photos from the alpha are deleted.
        $table = new xmldb_table('quizaccess_examproctor');

        $moved = ['detectface', 'maxyaw', 'maxpitch', 'facegrace', 'detectmotion', 'motionsens', 'idmode',
            'detectidentity', 'identitysens', 'micmode', 'micsens', 'screenmode', 'screeninterval'];
        $present = [];
        foreach ($moved as $name) {
            if ($dbman->field_exists($table, new xmldb_field($name))) {
                $present[] = $name;
            }
        }
        if ($present) {
            $legacy = [];
            $rs = $DB->get_recordset('quizaccess_examproctor', null, '', 'quizid, ' . implode(', ', $present));
            foreach ($rs as $row) {
                $legacy[$row->quizid] = $row;
            }
            $rs->close();
            set_config('legacysettings', json_encode($legacy), 'quizaccess_examproctor');
            foreach ($present as $name) {
                $dbman->drop_field($table, new xmldb_field($name));
            }
        }

        $fields = [
            new xmldb_field('minors', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enabled'),
            new xmldb_field('retentiondays', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'maxviolations'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $field = new xmldb_field('snapshotinterval', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '60');
        $dbman->change_field_default($table, $field);

        // Identity verification is no longer part of this plugin: delete the alpha's photos and table.
        $idtable = new xmldb_table('quizaccess_examproctor_id');
        if ($dbman->table_exists($idtable)) {
            $fs = get_file_storage();
            $contexts = $DB->get_fieldset_sql("SELECT DISTINCT contextid FROM {files}
                WHERE component = 'quizaccess_examproctor' AND filearea IN ('idphoto', 'idselfie')");
            foreach ($contexts as $contextid) {
                $fs->delete_area_files($contextid, 'quizaccess_examproctor', 'idphoto');
                $fs->delete_area_files($contextid, 'quizaccess_examproctor', 'idselfie');
            }
            $dbman->drop_table($idtable);
        }

        $logtable = new xmldb_table('quizaccess_examproctor_log');
        foreach (
            [new xmldb_index('attempt_type', XMLDB_INDEX_NOTUNIQUE, ['attemptid', 'eventtype']),
                new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated'])] as $index
        ) {
            if (!$dbman->index_exists($logtable, $index)) {
                $dbman->add_index($logtable, $index);
            }
        }

        unset_config('visionurl', 'quizaccess_examproctor');
        unset_config('wasmurl', 'quizaccess_examproctor');
        unset_config('modelurl', 'quizaccess_examproctor');
        unset_config('faceapiurl', 'quizaccess_examproctor');
        unset_config('faceapimodels', 'quizaccess_examproctor');
        unset_config('idmaxmb', 'quizaccess_examproctor');
        unset_config('idmaxsubmissions', 'quizaccess_examproctor');
        unset_config('idretentiondays', 'quizaccess_examproctor');

        upgrade_plugin_savepoint(true, 2026110700, 'quizaccess', 'examproctor');
    }

    if ($oldversion < 2026111200) {
        // 1.3.0: index for the every-minute auto-submit task; the exact consent text per attempt.
        $table = new xmldb_table('quizaccess_examproctor_log');
        $index = new xmldb_index('type_time', XMLDB_INDEX_NOTUNIQUE, ['eventtype', 'timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('quizaccess_examproctor_consent');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('version', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('texthash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('notice', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('quizid', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
            $table->add_key('attemptid', XMLDB_KEY_FOREIGN_UNIQUE, ['attemptid'], 'quiz_attempts', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026111200, 'quizaccess', 'examproctor');
    }

    if ($oldversion < 2026111400) {
        // 1.3.1: per-quiz choice when the browser refuses fullscreen.
        $table = new xmldb_table('quizaccess_examproctor');
        $field = new xmldb_field('fullscreenstrict', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'requirefullscreen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026111400, 'quizaccess', 'examproctor');
    }

    return true;
}
