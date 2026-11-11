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
 * Strings for quizaccess_examproctor.
 *
 * @package    quizaccess_examproctor
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Exam proctoring';

// Capabilities.
$string['examproctor:viewreport'] = 'View the exam proctoring report and webcam images';
$string['examproctor:deleteevidence'] = 'Delete the proctoring evidence of an attempt';

// Privacy.
$string['privacy:metadata:log'] = 'Events recorded while a student takes a proctored quiz.';
$string['privacy:metadata:log:userid'] = 'The student taking the quiz.';
$string['privacy:metadata:log:quizid'] = 'The quiz.';
$string['privacy:metadata:log:attemptid'] = 'The quiz attempt the event belongs to.';
$string['privacy:metadata:log:eventtype'] = 'The kind of event, for example tab switch or webcam snapshot.';
$string['privacy:metadata:log:details'] = 'Extra details about the event, for example how long the student was away.';
$string['privacy:metadata:log:isviolation'] = 'Whether the event counts as a violation.';
$string['privacy:metadata:log:hassnapshot'] = 'Whether a webcam image is stored with the event.';
$string['privacy:metadata:log:timecreated'] = 'When the event happened.';
$string['privacy:metadata:files'] = 'Webcam images captured during the quiz attempt.';
$string['privacy:metadata:session'] = 'The time and version of the consent notice are kept in the session until the attempt starts.';

// Quiz settings form.
$string['enabled'] = 'Enable exam proctoring';
$string['enabled_help'] = 'When enabled, students must consent before each attempt, the quiz must be taken in a web browser (not the mobile app), and their browser activity (and, if set, webcam snapshots) is recorded during the attempt. Teacher previews are not proctored.';
$string['minors'] = 'Learners may be under 18';
$string['minors_help'] = 'Use this for school students or any group that may include minors. The webcam is never used: only tab switching, window focus, fullscreen and copy/paste events are recorded, and the consent notice says so.';
$string['requirecamera'] = 'Require webcam';
$string['requirecamera_help'] = 'Students must allow webcam access for the whole attempt. Snapshots are saved for the proctoring report. A stopped, covered, black or frozen camera blocks the quiz until it is fixed and is recorded as a violation. Browsers require HTTPS for webcam access.';
$string['snapshotinterval'] = 'Snapshot interval';
$string['snapshotinterval_help'] = 'How often a webcam snapshot is saved. 60 seconds is recommended for large exams: shorter intervals multiply the load on the server.';
$string['requirefullscreen'] = 'Require fullscreen';
$string['requirefullscreen_help'] = 'The quiz page is hidden until the student enters fullscreen. Browsers always leave fullscreen when a page reloads, so set Layout > New page to "Never, all questions on one page" for proctored quizzes.';
$string['blockclipboard'] = 'Block copy, paste and right-click';
$string['maxviolations'] = 'Violations before auto-submit';
$string['maxviolations_help'] = 'The attempt is submitted automatically once this many violations are recorded. The page is saved first, so answers typed on it are kept. Set to 0 to never auto-submit.';
$string['maxviolationsinvalid'] = 'Enter 0 or a positive whole number.';
$string['retentiondays'] = 'Keep proctoring data for (days)';
$string['retentiondays_help'] = 'Events and webcam images of this quiz are deleted this many days after they were recorded. 0 uses the site default.';
$string['retentiondaysinvalid'] = 'Enter a number of days from 0 to 3650.';

// Quiz view page and consent.
$string['proctorednotice'] = 'This quiz is proctored. Your webcam and browser activity are monitored during the attempt. Take it in a web browser on a computer.';
$string['proctorednotice_minors'] = 'This quiz is proctored. Your browser activity (tab switching, fullscreen, copy and paste) is recorded during the attempt. The webcam is not used.';
$string['viewreportlink'] = 'View proctoring report';
$string['webservicenotallowed'] = 'This quiz is proctored and can only be taken in a web browser, not in the mobile app.';
$string['consent_intro'] = 'This quiz is proctored. During the attempt:';
$string['consent_camera'] = 'Your webcam must stay on. A snapshot is saved about every {$a} seconds, and when a problem is detected, for your teacher to review.';
$string['consent_minors'] = 'Your webcam is NOT used. Only your browser activity is recorded.';
$string['consent_focus'] = 'Switching tabs or leaving the quiz window is recorded, with how long you were away.';
$string['consent_fullscreen'] = 'The quiz must stay in fullscreen mode.';
$string['consent_clipboard'] = 'Copy, paste and right-click are disabled and attempts to use them are recorded.';
$string['consent_maxviolations'] = 'Your attempt is submitted automatically after {$a} violations.';
$string['consent_retention'] = 'Recorded events and images are deleted after {$a} days.';
$string['consent_version'] = 'Notice version {$a}. Your consent is recorded with your attempt.';
$string['consent_checkbox'] = 'I understand and consent to being monitored during this quiz';
$string['consent_required'] = 'You must consent to proctoring to start this quiz.';

// JavaScript.
$string['js_camerarequired'] = 'Webcam access is required for this quiz. Please allow camera access to continue.';
$string['js_camerabutton'] = 'Enable camera';
$string['js_fullscreenrequired'] = 'This quiz must be taken in fullscreen mode.';
$string['js_fullscreenbutton'] = 'Enter fullscreen';
$string['js_autosubmit'] = 'Too many violations were recorded. Your attempt is being submitted.';
$string['js_dialoglabel'] = 'Exam proctoring';
$string['js_warn_generic'] = 'Violation recorded.';
$string['js_warn_tab_hidden'] = 'Warning: you left the quiz tab. This has been recorded.';
$string['js_warn_window_blur'] = 'Warning: the quiz window lost focus. This has been recorded.';
$string['js_warn_fullscreen_exit'] = 'Warning: you exited fullscreen. This has been recorded.';
$string['js_warn_copy'] = 'Copying is disabled during this quiz. This has been recorded.';
$string['js_warn_paste'] = 'Pasting is disabled during this quiz. This has been recorded.';
$string['js_warn_contextmenu'] = 'Right-click is disabled during this quiz. This has been recorded.';
$string['js_warn_camera_lost'] = 'Warning: your camera stopped. This has been recorded.';
$string['js_warn_camera_denied'] = 'Warning: camera access was refused. This has been recorded.';
$string['js_warn_camera_covered'] = 'Warning: your camera is covered or blocked. This has been recorded.';
$string['js_warn_video_frozen'] = 'Warning: your camera video appears frozen. This has been recorded.';
$string['js_ovl_camera_covered'] = 'Your camera image is blocked or too dark. Uncover the camera and make sure the room is lit.';
$string['js_ovl_video_frozen'] = 'Your camera video appears frozen. Check that the camera works, close other apps using it, or reconnect it.';
$string['js_analysis_disabled_note'] = 'Camera analysis was switched off because this computer is too slow. This has been recorded.';
$string['js_active'] = 'Exam proctoring active';
$string['js_active_preview'] = 'Exam proctoring active (preview - nothing is recorded)';
$string['js_preview_note'] = '(Preview: not recorded)';

// Admin settings.
$string['settings_retention'] = 'Keep proctoring data for (days)';
$string['settings_retention_desc'] = 'Site default: events and webcam images are deleted this many days after they were recorded. A quiz can set its own period. 0 = keep until the quiz is deleted (not recommended under GDPR / DPDP).';
$string['settings_proctorpreviews'] = 'Apply proctoring to teacher previews (testing)';
$string['settings_proctorpreviews_desc'] = 'When ticked, the browser-side checks also run when a teacher or admin previews a quiz, so you can test without a student account. Previews are never recorded, never count violations and are never auto-submitted.';
$string['task_cleanup'] = 'Delete expired proctoring events and images';

// Moodle events.
$string['eventviolation_recorded'] = 'Proctoring violation recorded';
$string['eventattempt_autosubmitted'] = 'Attempt submitted after proctoring violations';
$string['eventevidence_deleted'] = 'Proctoring evidence deleted';
$string['eventconsent_given'] = 'Consent to proctoring given';

// Report.
$string['reporttitle'] = 'Proctoring report';
$string['notenabled'] = 'Proctoring is not switched on for this quiz. Earlier data is still shown.';
$string['retentionnotice'] = 'Events and images are deleted automatically {$a} days after they were recorded.';
$string['retentionkeep'] = 'Events and images are kept until the quiz is deleted.';
$string['noevents'] = 'No proctoring events for this attempt.';
$string['noattempts'] = 'No attempts match.';
$string['user'] = 'Student';
$string['attempt'] = 'Attempt';
$string['state'] = 'State';
$string['violations'] = 'Violations';
$string['violation'] = 'Violation';
$string['snapshots'] = 'Snapshots';
$string['snapshotsreceived'] = 'Snapshots (received / expected)';
$string['expectedsnapshots'] = 'Expected snapshots';
$string['sessions'] = 'Browser sessions';
$string['flags'] = 'Needs a look';
$string['livestatus'] = 'Status';
$string['live_active'] = 'In progress - last signal {$a} ago';
$string['live_silent'] = 'In progress - SILENT for {$a}';
$string['live_nodata'] = 'In progress - no data from the browser';
$string['flag_nodata'] = 'No proctoring data';
$string['flag_gap'] = 'Missing snapshots';
$string['flag_multisession'] = 'Several browser sessions';
$string['flag_other'] = '{$a} warning(s)';
$string['flagshelp'] = '"No proctoring data": the browser never ran the proctoring script (blocked or disabled) - treat as unproctored. "Missing snapshots": fewer webcam snapshots arrived than the attempt length requires. "Several browser sessions": the attempt was opened in more than one tab, browser or device. Attempts with no data are listed first.';
$string['searchstudent'] = 'Student name or e-mail';
$string['flaggedonly'] = 'Only attempts that need a look';
$string['downloadsummary'] = 'Attempts ({$a})';
$string['downloadevents'] = 'All events ({$a})';
$string['viewdetails'] = 'View details';
$string['backtosummary'] = 'Back to all attempts';
$string['showviolationsonly'] = 'Show violations only';
$string['showallevents'] = 'Show all events';
$string['deleteevidence'] = 'Delete this attempt\'s evidence';
$string['deleteevidenceconfirm'] = 'Delete every proctoring event and webcam image of this attempt by {$a}? This cannot be undone.';
$string['evidencedeleted'] = 'The proctoring evidence of the attempt was deleted.';
$string['time'] = 'Time';
$string['event'] = 'Event';
$string['details'] = 'Details';
$string['snapshot'] = 'Image';
$string['snapshotof'] = 'Webcam image at {$a}';

// Event labels.
$string['event_proctoring_started'] = 'Attempt page opened (server)';
$string['event_consent'] = 'Consent given';
$string['event_multiple_sessions'] = 'Opened in another tab, browser or device';
$string['event_quota_exceeded'] = 'Too many events from the browser (blocked)';
$string['event_autosubmit_requested'] = 'Violation limit reached';
$string['event_autosubmit'] = 'Submitted automatically';
$string['event_webservice_blocked'] = 'Blocked: not a web browser';
$string['event_session_start'] = 'Proctoring started in the browser';
$string['event_snapshot'] = 'Webcam snapshot';
$string['event_camera_denied'] = 'Camera access refused';
$string['event_camera_lost'] = 'Camera stopped';
$string['event_camera_covered'] = 'Camera covered or dark';
$string['event_video_frozen'] = 'Camera video frozen';
$string['event_tab_hidden'] = 'Left the quiz tab';
$string['event_tab_returned'] = 'Came back to the quiz tab';
$string['event_window_blur'] = 'Switched to another window';
$string['event_window_returned'] = 'Came back to the quiz window';
$string['event_fullscreen_exit'] = 'Left fullscreen';
$string['event_fullscreen_unsupported'] = 'Fullscreen not supported by this device';
$string['event_copy'] = 'Tried to copy';
$string['event_paste'] = 'Tried to paste';
$string['event_contextmenu'] = 'Tried to right-click';
$string['event_analysis_disabled'] = 'Camera analysis switched off (computer too slow)';
$string['event_extension_error'] = 'An extra check could not start';
$string['event_autosubmit_now'] = 'Submit requested by the browser';
