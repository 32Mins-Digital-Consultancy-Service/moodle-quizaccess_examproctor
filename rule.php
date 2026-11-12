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
 * Quiz access rule that adds browser-based proctoring.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core\di;
use core\hook\manager as hook_manager;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use quizaccess_examproctor\hook;
use quizaccess_examproctor\local\attempt_finisher;
use quizaccess_examproctor\local\helper;

/**
 * Proctoring access rule.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_examproctor extends access_rule_base {

    /** @var stdClass Settings row from quizaccess_examproctor. */
    protected $settings;

    /** Fields of the core settings table edited in the quiz form. */
    const FORM_FIELDS = ['minors', 'requirecamera', 'snapshotinterval', 'requirefullscreen', 'blockclipboard',
        'maxviolations', 'retentiondays'];

    /**
     * Constructor.
     *
     * @param quiz_settings $quizobj
     * @param int $timenow
     * @param stdClass $settings the proctoring settings row
     */
    public function __construct($quizobj, $timenow, $settings) {
        parent::__construct($quizobj, $timenow);
        $this->settings = $settings;
    }

    /**
     * Create an instance if proctoring is enabled for this quiz.
     *
     * @param quiz_settings $quizobj
     * @param int $timenow
     * @param bool $canignoretimelimits
     * @return self|null
     */
    public static function make($quizobj, $timenow, $canignoretimelimits) {
        $settings = helper::settings((int) $quizobj->get_quizid());
        if (!$settings || empty($settings->enabled)) {
            return null;
        }
        return new self($quizobj, $timenow, $settings);
    }

    /**
     * Notice and links shown on the quiz view page.
     *
     * @return array
     */
    public function description() {
        $p = 'quizaccess_examproctor';
        $messages = [get_string(empty($this->settings->minors) ? 'proctorednotice' : 'proctorednotice_minors', $p)];
        $context = $this->quizobj->get_context();
        if (has_capability('quizaccess/examproctor:viewreport', $context)) {
            $url = new moodle_url('/mod/quiz/accessrule/examproctor/report.php', ['cmid' => $this->quizobj->get_cmid()]);
            $messages[] = html_writer::link($url, get_string('viewreportlink', $p));
            $links = new hook\report_links($this->settings, $this->quizobj->get_cm(), $context);
            di::get(hook_manager::class)->dispatch($links);
            foreach ($links->get_links() as [$linkurl, $label]) {
                $messages[] = html_writer::link($linkurl, $label);
            }
        }
        return $messages;
    }

    /**
     * Proctoring needs the browser page: block web-service clients (mobile app, scripts with a token).
     *
     * @return string|false
     */
    public function prevent_access() {
        if (self::is_webservice_request()) {
            return get_string('webservicenotallowed', 'quizaccess_examproctor');
        }
        return false;
    }

    /**
     * Whether this request comes through a web service rather than the browser pages.
     *
     * @return bool
     */
    public static function is_webservice_request(): bool {
        return defined('WS_SERVER') && WS_SERVER && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT);
    }

    /**
     * Let other plugins hold back a new attempt (for example an identity check before the exam).
     *
     * @param int $numprevattempts
     * @param stdClass|false $lastattempt
     * @return string|false
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        global $USER;
        $hook = new hook\new_attempt($this->settings, $this->quizobj, (int) $USER->id);
        di::get(hook_manager::class)->dispatch($hook);
        $messages = $hook->get_messages();
        return $messages ? implode(' ', $messages) : false;
    }

    // -------------------------------------------------------------------
    // Consent screen before a new attempt.
    // -------------------------------------------------------------------

    /**
     * Consent is asked before every new attempt.
     *
     * @param int|null $attemptid
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        return empty($attemptid);
    }

    /**
     * The consent notice items for this quiz.
     *
     * @return string[]
     */
    public function consent_items(): array {
        $p = 'quizaccess_examproctor';
        $items = [];
        if (!empty($this->settings->minors)) {
            $items[] = get_string('consent_minors', $p);
        } else if (!empty($this->settings->requirecamera)) {
            $items[] = get_string('consent_camera', $p, (int) helper::interval($this->settings));
        }
        $items[] = get_string('consent_focus', $p);
        if (!empty($this->settings->requirefullscreen)) {
            $items[] = get_string('consent_fullscreen', $p);
        }
        if (!empty($this->settings->blockclipboard)) {
            $items[] = get_string('consent_clipboard', $p);
        }
        if (empty($this->settings->minors)) {
            $hook = new hook\consent_items($this->settings, $this->quizobj);
            di::get(hook_manager::class)->dispatch($hook);
            $items = array_merge($items, $hook->get_items());
        }
        if (helper::autosubmit_allowed() && (int) $this->settings->maxviolations > 0) {
            $items[] = get_string('consent_maxviolations', $p, (int) $this->settings->maxviolations);
        }
        $items[] = get_string('consent_retention', $p, helper::retention_days($this->settings) ?: '-');
        return $items;
    }

    /**
     * Add the consent notice to the pre-flight form.
     *
     * @param \mod_quiz\form\preflight_check_form $quizform
     * @param MoodleQuickForm $mform
     * @param int|null $attemptid
     */
    public function add_preflight_check_form_fields($quizform, $mform, $attemptid) {
        $p = 'quizaccess_examproctor';
        $html = html_writer::tag('p', get_string('consent_intro', $p));
        $html .= html_writer::alist(array_map('s', $this->consent_items()));
        $html .= html_writer::tag('p', get_string('consent_version', $p, helper::CONSENT_VERSION), ['class' => 'small text-muted']);

        $mform->addElement('header', 'examproctorheader', get_string('pluginname', $p));
        $mform->addElement('static', 'examproctorintro', '', $html);
        if (helper::camera_on($this->settings)) {
            // Device check before the attempt, so camera permission is granted before the exam timer starts.
            $check = html_writer::tag('button', get_string('devicecheck_button', $p), ['type' => 'button',
                'class' => 'btn btn-secondary btn-sm', 'data-examproctor-devicecheck' => '1']);
            $check .= html_writer::tag('video', '', ['class' => 'examproctor-devicecheck-video d-none mt-2',
                'muted' => 'muted', 'playsinline' => 'playsinline', 'width' => 160, 'height' => 120,
                'aria-label' => get_string('devicecheck_preview', $p)]);
            $check .= html_writer::div('', 'examproctor-devicecheck-status small mt-1', ['role' => 'status']);
            $mform->addElement('static', 'examproctordevicecheck', get_string('devicecheck', $p),
                html_writer::div($check, 'examproctor-devicecheck'));
            global $PAGE;
            $PAGE->requires->js_call_amd('quizaccess_examproctor/devicecheck', 'init', [[
                'ok' => get_string('devicecheck_ok', $p),
                'fail' => get_string('devicecheck_fail', $p),
                'insecure' => get_string('devicecheck_insecure', $p),
            ]]);
        }
        $mform->addElement('checkbox', 'examproctorconsent', '', get_string('consent_checkbox', $p));
    }

    /**
     * The consent box must be ticked.
     *
     * @param array $data
     * @param array $files
     * @param array $errors
     * @param int|null $attemptid
     * @return array
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if (empty($data['examproctorconsent'])) {
            $errors['examproctorconsent'] = get_string('consent_required', 'quizaccess_examproctor');
        }
        return $errors;
    }

    /**
     * Remember the consent until the attempt exists; it is written to the log on the first attempt page.
     *
     * @param int|null $attemptid
     */
    public function notify_preflight_check_passed($attemptid) {
        global $SESSION;
        if (!isset($SESSION->quizaccess_examproctor_consent)) {
            $SESSION->quizaccess_examproctor_consent = [];
        }
        $items = $this->consent_items();
        $SESSION->quizaccess_examproctor_consent[$this->quizobj->get_quizid()] = [
            'time' => time(),
            'version' => helper::CONSENT_VERSION,
            'items' => count($items),
            'text' => implode("\n", $items),
        ];
    }

    /**
     * Write the consent and the server-side start record for an attempt (once each).
     *
     * @param stdClass $attempt
     */
    protected function record_attempt_start(stdClass $attempt): void {
        global $DB, $SESSION;

        if (!$DB->record_exists('quizaccess_examproctor_log', ['attemptid' => $attempt->id, 'eventtype' => 'proctoring_started'])) {
            helper::log($attempt, 'proctoring_started', empty($this->settings->minors) ? '' : 'under-18 mode');
        }
        $quizid = $this->quizobj->get_quizid();
        $pending = $SESSION->quizaccess_examproctor_consent[$quizid] ?? null;
        if ($pending && !$DB->record_exists('quizaccess_examproctor_log', ['attemptid' => $attempt->id, 'eventtype' => 'consent'])) {
            $text = (string) ($pending['text'] ?? '');
            $hash = hash('sha256', $pending['version'] . "\n" . $text);
            $id = helper::log($attempt, 'consent', 'notice ' . $pending['version'] . ', ' . $pending['items'] . ' items, sha256 ' .
                substr($hash, 0, 16));
            $DB->set_field('quizaccess_examproctor_log', 'timecreated', (int) $pending['time'], ['id' => $id]);
            if (!$DB->record_exists('quizaccess_examproctor_consent', ['attemptid' => $attempt->id])) {
                $DB->insert_record('quizaccess_examproctor_consent', (object) [
                    'quizid' => $attempt->quiz,
                    'attemptid' => $attempt->id,
                    'userid' => $attempt->userid,
                    'version' => $pending['version'],
                    'texthash' => $hash,
                    'notice' => $text,
                    'timecreated' => (int) $pending['time'],
                ]);
            }
            unset($SESSION->quizaccess_examproctor_consent[$quizid]);
            \quizaccess_examproctor\event\consent_given::create([
                'objectid' => $attempt->id,
                'relateduserid' => $attempt->userid,
                'context' => $this->quizobj->get_context(),
                'other' => ['version' => $pending['version'], 'quizid' => (int) $quizid],
            ])->trigger();
        }
    }

    // -------------------------------------------------------------------
    // Attempt and summary pages: load the proctoring JavaScript.
    // -------------------------------------------------------------------

    /**
     * Load the proctoring script on the attempt page and (lighter) on the summary page.
     *
     * @param moodle_page $page
     */
    public function setup_attempt_page($page) {
        global $DB, $USER;

        if (!in_array($page->pagetype, ['mod-quiz-attempt', 'mod-quiz-summary'], true)) {
            return;
        }
        $mode = $page->pagetype === 'mod-quiz-summary' ? 'summary' : 'attempt';

        $attemptid = optional_param('attempt', 0, PARAM_INT);
        $attempt = $attemptid ? $DB->get_record('quiz_attempts', ['id' => $attemptid, 'userid' => $USER->id]) : false;
        if (!$attempt || $attempt->state !== 'inprogress') {
            return;
        }
        $ispreview = !empty($attempt->preview);
        if ($ispreview && !get_config('quizaccess_examproctor', 'proctorpreviews')) {
            return;
        }

        if (!$ispreview) {
            $this->record_attempt_start($attempt);
            // The browser ignored the request to submit after the violation limit: finish it now.
            $requested = $DB->get_field('quizaccess_examproctor_log', 'MIN(timecreated)',
                ['attemptid' => $attempt->id, 'eventtype' => 'autosubmit_requested']);
            if ($requested && time() - $requested > \quizaccess_examproctor\external\log_event::AUTOSUBMIT_GRACE) {
                $url = attempt_finisher::finish((int) $attempt->id);
                helper::log($attempt, 'autosubmit', 'Violation limit reached (page reload)');
                redirect($url, get_string('js_autosubmit', 'quizaccess_examproctor'));
            }
        }

        $p = 'quizaccess_examproctor';
        $strings = [];
        foreach (['camerarequired', 'camerabutton', 'fullscreenrequired', 'fullscreenbutton', 'autosubmit',
                'warn_generic', 'active', 'active_preview', 'preview_note', 'ovl_camera_covered', 'ovl_video_frozen',
                'dialoglabel', 'analysis_disabled_note', 'fullscreen_waived', 'selfview_toggle'] as $key) {
            $strings[$key] = get_string('js_' . $key, $p);
        }
        foreach (['tab_hidden', 'window_blur', 'fullscreen_exit', 'copy', 'paste', 'contextmenu', 'camera_lost',
                'camera_denied', 'camera_covered', 'video_frozen'] as $type) {
            $strings['warn_' . $type] = get_string('js_warn_' . $type, $p);
        }

        $camera = helper::camera_on($this->settings);
        $extensions = [];
        if (empty($this->settings->minors)) {
            $hook = new hook\attempt_page($this->settings, $attempt, $this->quizobj, $mode, $ispreview);
            di::get(hook_manager::class)->dispatch($hook);
            $extensions = $hook->get_extensions();
        }

        $config = [
            'attemptid' => (int) $attempt->id,
            'mode' => $mode,
            'preview' => $ispreview,
            'release' => (string) (core_plugin_manager::instance()->get_plugin_info($p)->release ?? ''),
            'requirecamera' => $camera,
            'snapshotinterval' => helper::interval($this->settings),
            'requirefullscreen' => (bool) $this->settings->requirefullscreen && $mode === 'attempt',
            'blockclipboard' => (bool) $this->settings->blockclipboard,
            'extensions' => $extensions,
            'strings' => $strings,
        ];

        // The configuration is larger than js_call_amd() accepts without a debugging warning: pass it as page data.
        $page->requires->data_for_js('quizaccess_examproctor_config', $config);
        $page->requires->js_call_amd('quizaccess_examproctor/proctor', 'init', []);
    }

    // -------------------------------------------------------------------
    // Quiz settings form (Extra restrictions section).
    // -------------------------------------------------------------------

    /**
     * Add the proctoring fields to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform
     * @param MoodleQuickForm $mform
     */
    public static function add_settings_form_fields($quizform, $mform) {
        $p = 'quizaccess_examproctor';

        $mform->addElement('selectyesno', 'examproctor_enabled', get_string('enabled', $p));
        $mform->addHelpButton('examproctor_enabled', 'enabled', $p);
        $mform->setDefault('examproctor_enabled', 0);

        $mform->addElement('selectyesno', 'examproctor_minors', get_string('minors', $p));
        $mform->addHelpButton('examproctor_minors', 'minors', $p);
        $mform->setDefault('examproctor_minors', 0);

        $mform->addElement('selectyesno', 'examproctor_requirecamera', get_string('requirecamera', $p));
        $mform->addHelpButton('examproctor_requirecamera', 'requirecamera', $p);
        $mform->setDefault('examproctor_requirecamera', 1);

        $intervals = [];
        foreach ([30, 60, 120, 300] as $seconds) {
            $intervals[$seconds] = get_string('numseconds', 'moodle', $seconds);
        }
        $mform->addElement('select', 'examproctor_snapshotinterval', get_string('snapshotinterval', $p), $intervals);
        $mform->addHelpButton('examproctor_snapshotinterval', 'snapshotinterval', $p);
        $mform->setDefault('examproctor_snapshotinterval', 60);

        $mform->addElement('selectyesno', 'examproctor_requirefullscreen', get_string('requirefullscreen', $p));
        $mform->addHelpButton('examproctor_requirefullscreen', 'requirefullscreen', $p);
        $mform->setDefault('examproctor_requirefullscreen', 1);

        $mform->addElement('selectyesno', 'examproctor_blockclipboard', get_string('blockclipboard', $p));
        $mform->setDefault('examproctor_blockclipboard', 1);

        if (helper::autosubmit_allowed()) {
            $mform->addElement('text', 'examproctor_maxviolations', get_string('maxviolations', $p), ['size' => 4]);
            $mform->setType('examproctor_maxviolations', PARAM_INT);
            $mform->setDefault('examproctor_maxviolations', 0);
            $mform->addHelpButton('examproctor_maxviolations', 'maxviolations', $p);
        } else {
            $mform->addElement('hidden', 'examproctor_maxviolations', 0);
            $mform->setType('examproctor_maxviolations', PARAM_INT);
        }

        $mform->addElement('text', 'examproctor_retentiondays', get_string('retentiondays', $p), ['size' => 4]);
        $mform->setType('examproctor_retentiondays', PARAM_INT);
        $mform->setDefault('examproctor_retentiondays', 0);
        $mform->addHelpButton('examproctor_retentiondays', 'retentiondays', $p);

        foreach (self::FORM_FIELDS as $field) {
            $mform->disabledIf('examproctor_' . $field, 'examproctor_enabled', 'eq', 0);
        }
        foreach (['requirecamera', 'snapshotinterval'] as $field) {
            $mform->disabledIf('examproctor_' . $field, 'examproctor_minors', 'eq', 1);
        }
        $mform->disabledIf('examproctor_snapshotinterval', 'examproctor_requirecamera', 'eq', 0);

        di::get(hook_manager::class)->dispatch(new hook\settings_form($quizform, $mform));
    }

    /**
     * Validate the proctoring fields.
     *
     * @param array $errors
     * @param array $data
     * @param array $files
     * @param mod_quiz_mod_form $quizform
     * @return array
     */
    public static function validate_settings_form_fields(array $errors, array $data, $files, $quizform) {
        $p = 'quizaccess_examproctor';
        if (!empty($data['examproctor_enabled'])) {
            if (isset($data['examproctor_maxviolations']) && (int) $data['examproctor_maxviolations'] < 0) {
                $errors['examproctor_maxviolations'] = get_string('maxviolationsinvalid', $p);
            }
            if (isset($data['examproctor_retentiondays'])
                    && ((int) $data['examproctor_retentiondays'] < 0 || (int) $data['examproctor_retentiondays'] > 3650)) {
                $errors['examproctor_retentiondays'] = get_string('retentiondaysinvalid', $p);
            }
            $hook = new hook\settings_validate($data, $quizform);
            di::get(hook_manager::class)->dispatch($hook);
            $errors = array_merge($errors, $hook->get_errors());
        }
        return $errors;
    }

    /**
     * Save the proctoring settings.
     *
     * @param stdClass $quiz
     */
    public static function save_settings($quiz) {
        global $DB;

        $existing = $DB->get_record('quizaccess_examproctor', ['quizid' => $quiz->id]);
        $enabled = !empty($quiz->examproctor_enabled);

        if (!$enabled) {
            if ($existing) {
                $DB->set_field('quizaccess_examproctor', 'enabled', 0, ['id' => $existing->id]);
            }
        } else {
            $record = (object) [
                'quizid' => $quiz->id,
                'enabled' => 1,
                'minors' => empty($quiz->examproctor_minors) ? 0 : 1,
                'requirecamera' => empty($quiz->examproctor_requirecamera) ? 0 : 1,
                'snapshotinterval' => max(30, (int) ($quiz->examproctor_snapshotinterval ?? 60)),
                'requirefullscreen' => empty($quiz->examproctor_requirefullscreen) ? 0 : 1,
                'blockclipboard' => empty($quiz->examproctor_blockclipboard) ? 0 : 1,
                'maxviolations' => helper::autosubmit_allowed() ? max(0, (int) ($quiz->examproctor_maxviolations ?? 0))
                    : (int) ($existing->maxviolations ?? 0),
                'retentiondays' => min(3650, max(0, (int) ($quiz->examproctor_retentiondays ?? 0))),
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('quizaccess_examproctor', $record);
            } else {
                $DB->insert_record('quizaccess_examproctor', $record);
            }
        }
        di::get(hook_manager::class)->dispatch(new hook\settings_save($quiz, $enabled));
    }

    /**
     * Delete settings, events and images when a quiz is deleted.
     *
     * @param stdClass $quiz
     */
    public static function delete_settings($quiz) {
        global $DB;

        di::get(hook_manager::class)->dispatch(new hook\settings_delete($quiz));

        $DB->delete_records('quizaccess_examproctor', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_examproctor_log', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_examproctor_consent', ['quizid' => $quiz->id]);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, IGNORE_MISSING);
        if ($cm) {
            $context = context_module::instance($cm->id);
            get_file_storage()->delete_area_files($context->id, 'quizaccess_examproctor', 'snapshots');
        }
    }

    /**
     * Load the settings together with the quiz.
     *
     * @param int $quizid
     * @return array
     */
    public static function get_settings_sql($quizid) {
        $fields = ['ep.enabled AS examproctor_enabled'];
        foreach (self::FORM_FIELDS as $field) {
            $fields[] = "ep.$field AS examproctor_$field";
        }
        $joins = ['LEFT JOIN {quizaccess_examproctor} ep ON ep.quizid = quiz.id'];
        $params = [];

        $hook = new hook\settings_sql((int) $quizid);
        di::get(hook_manager::class)->dispatch($hook);
        [$extrafields, $extrajoins, $extraparams] = $hook->get_parts();

        return [
            implode(', ', array_merge($fields, $extrafields)),
            implode(' ', array_merge($joins, $extrajoins)),
            $params + $extraparams,
        ];
    }
}
