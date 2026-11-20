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
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['attempt'] = 'Attempt';
$string['backtosummary'] = 'Back to all attempts';
$string['blockclipboard'] = 'Block copy, paste and right-click';
$string['consent_camera'] = 'Your webcam must stay on. A snapshot is saved about every {$a} seconds, and when a problem is detected, for your teacher to review.';
$string['consent_checkbox'] = 'I understand and consent to being monitored during this quiz';
$string['consent_clipboard'] = 'Copy, paste and right-click are disabled and attempts to use them are recorded.';
$string['consent_focus'] = 'Switching tabs or leaving the quiz window is recorded, with how long you were away.';
$string['consent_fullscreen'] = 'The quiz must stay in fullscreen mode.';
$string['consent_intro'] = 'This quiz is proctored. During the attempt:';
$string['consent_maxviolations'] = 'Your attempt is submitted automatically after {$a} violations.';
$string['consent_minors'] = 'Your webcam is not used. Only your browser activity is recorded.';
$string['consent_required'] = 'You must consent to proctoring to start this quiz.';
$string['consent_retention'] = 'Recorded events and images are deleted after {$a} days.';
$string['consent_version'] = 'Notice version {$a}. Your consent is recorded with your attempt.';
$string['deleteevidence'] = 'Delete this attempt\'s evidence';
$string['deleteevidenceconfirm'] = 'Delete every proctoring event and webcam image of this attempt by {$a}? This cannot be undone.';
$string['details'] = 'Details';
$string['devicecheck'] = 'Camera check';
$string['devicecheck_button'] = 'Test my camera';
$string['devicecheck_fail'] = 'The camera could not start. Allow camera access in the browser, close other apps using the camera, and try again.';
$string['devicecheck_insecure'] = 'This browser cannot use the camera on this page (the site must use HTTPS). Contact your teacher.';
$string['devicecheck_ok'] = 'Camera works. You can start the quiz.';
$string['devicecheck_preview'] = 'Camera preview';
$string['downloadevents'] = 'All events ({$a})';
$string['downloadsummary'] = 'Attempts ({$a})';
$string['enabled'] = 'Enable exam proctoring';
$string['enabled_help'] = 'When enabled, learners must consent before each attempt, the quiz must be taken in a web browser (not the mobile app), and their browser activity (and, if set, webcam snapshots) is recorded during the attempt. Teacher previews are not proctored.';
$string['event'] = 'Event';
$string['event_analysis_disabled'] = 'Camera analysis switched off (computer too slow)';
$string['event_autosubmit'] = 'Submitted automatically';
$string['event_autosubmit_now'] = 'Submit requested by the browser';
$string['event_autosubmit_requested'] = 'Violation limit reached';
$string['event_camera_covered'] = 'Camera covered or dark';
$string['event_camera_denied'] = 'Camera access refused';
$string['event_camera_lost'] = 'Camera stopped';
$string['event_consent'] = 'Consent given';
$string['event_contextmenu'] = 'Tried to right-click';
$string['event_copy'] = 'Tried to copy';
$string['event_extension_error'] = 'An extra check could not start';
$string['event_fullscreen_exit'] = 'Left fullscreen';
$string['event_fullscreen_unsupported'] = 'Fullscreen not available (browser refused or unsupported)';
$string['event_heartbeat'] = 'Browser still connected (last signal)';
$string['event_multiple_sessions'] = 'Opened in another tab, browser or device';
$string['event_paste'] = 'Tried to paste';
$string['event_problem_continues'] = 'Problem still going on';
$string['event_proctoring_started'] = 'Attempt page opened (server)';
$string['event_quota_exceeded'] = 'Too many events from the browser (blocked)';
$string['event_session_start'] = 'Proctoring started in the browser';
$string['event_snapshot'] = 'Webcam snapshot';
$string['event_tab_hidden'] = 'Left the quiz tab';
$string['event_tab_returned'] = 'Came back to the quiz tab';
$string['event_video_frozen'] = 'Camera video frozen';
$string['event_window_blur'] = 'Switched to another window';
$string['event_window_brief'] = 'Switched window briefly (under 2 s)';
$string['event_window_returned'] = 'Came back to the quiz window';
$string['eventattempt_autosubmitted'] = 'Attempt submitted after proctoring violations';
$string['eventconsent_given'] = 'Consent to proctoring given';
$string['eventevidence_deleted'] = 'Proctoring evidence deleted';
$string['eventevidence_viewed'] = 'Proctoring evidence viewed';
$string['eventviolation_recorded'] = 'Proctoring violation recorded';
$string['evidencedeleted'] = 'The proctoring evidence of the attempt was deleted.';
$string['examproctor:deleteevidence'] = 'Delete the proctoring evidence of an attempt';
$string['examproctor:viewreport'] = 'View the exam proctoring report and webcam images';
$string['expectedsnapshots'] = 'Expected snapshots';
$string['flag_gap'] = 'Missing snapshots';
$string['flag_multisession'] = 'Several browser sessions';
$string['flag_nodata'] = 'No proctoring data';
$string['flag_other'] = '{$a} warning(s)';
$string['flaggedonly'] = 'Only attempts that need a look';
$string['flags'] = 'Needs a look';
$string['flagshelp'] = '"No proctoring data": the browser never ran the proctoring script (blocked or disabled) - treat as unproctored. "Missing snapshots": fewer webcam snapshots arrived than the attempt length requires. "Several browser sessions": the attempt was opened in more than one tab, browser or device. Attempts with no data are listed first.';
$string['fullscreenstrict'] = 'If the browser refuses fullscreen';
$string['fullscreenstrict_block'] = 'Keep the paper hidden until fullscreen works';
$string['fullscreenstrict_continue'] = 'Continue and flag it for review';
$string['fullscreenstrict_help'] = 'Some browsers, kiosk modes or site settings refuse fullscreen. "Continue and flag": the quiz continues and the report flags "Fullscreen not available" (no learner is locked out). "Keep the paper hidden": the quiz stays hidden until fullscreen works; use this only where you control the computers (for example a lab), because a learner whose browser cannot go fullscreen cannot continue.';
$string['integrity'] = 'Summary';
$string['integrity_line'] = '{$a->violations} violation(s) · {$a->flags} flag(s) · {$a->missing} missing snapshot(s) · {$a->sessions} session(s)';
$string['js_active'] = 'Proctoring on';
$string['js_active_preview'] = 'Proctoring on (preview: nothing is recorded)';
$string['js_analysis_disabled_note'] = 'Camera analysis was switched off because this computer is too slow. This has been recorded.';
$string['js_autosubmit'] = 'Too many violations were recorded. Your attempt is being submitted.';
$string['js_camerabutton'] = 'Turn on camera';
$string['js_camerarequired'] = 'This quiz needs your webcam. Allow camera access to continue.';
$string['js_dialoglabel'] = 'Exam proctoring';
$string['js_fullscreen_refused'] = 'This exam must be taken in fullscreen, but your browser refused it. Allow fullscreen for this site in the browser settings and press the button again, or ask your teacher for help.';
$string['js_fullscreen_waived'] = 'Fullscreen is not available in this browser. You can continue the quiz; your teacher will see this in the report.';
$string['js_fullscreenbutton'] = 'Enter fullscreen';
$string['js_fullscreenrequired'] = 'This quiz runs in fullscreen.';
$string['js_ovl_camera_covered'] = 'Your camera image is blocked or too dark. Uncover the camera and make sure the room is lit.';
$string['js_ovl_video_frozen'] = 'Your camera video appears frozen. Check that the camera works, close other apps using it, or reconnect it.';
$string['js_preview_note'] = '(Preview: not recorded)';
$string['js_selfview_toggle'] = 'Your camera. Click to make it smaller or larger.';
$string['js_warn_camera_covered'] = 'Your camera is covered or blocked. This was recorded.';
$string['js_warn_camera_denied'] = 'Camera access was refused. This was recorded.';
$string['js_warn_camera_lost'] = 'Your camera stopped. This was recorded.';
$string['js_warn_contextmenu'] = 'Right-click is off during this quiz. This was recorded.';
$string['js_warn_copy'] = 'Copying is off during this quiz. This was recorded.';
$string['js_warn_fullscreen_exit'] = 'You left fullscreen. This was recorded.';
$string['js_warn_generic'] = 'This was recorded.';
$string['js_warn_paste'] = 'Pasting is off during this quiz. This was recorded.';
$string['js_warn_tab_hidden'] = 'You left the quiz tab. This was recorded.';
$string['js_warn_video_frozen'] = 'Your camera video looks frozen. This was recorded.';
$string['js_warn_window_blur'] = 'You switched to another window. This was recorded.';
$string['live_active'] = 'In progress - last signal {$a} ago';
$string['live_nodata'] = 'In progress - no data from the browser';
$string['live_silent'] = 'In progress - no signal for {$a}';
$string['livestatus'] = 'Status';
$string['maxviolations'] = 'Violations before auto-submit';
$string['maxviolations_help'] = 'The attempt is submitted automatically once this many violations are recorded. The page is saved first, so answers typed on it are kept; if the browser does not submit it, the server does within about a minute. A problem that lasts (for example a frozen camera) counts once, not every time it is recorded again. Set to 0 to never auto-submit.';
$string['maxviolationsinvalid'] = 'Enter 0 or a positive whole number.';
$string['minors'] = 'Learners may be under 18';
$string['minors_help'] = 'Use this for school pupils or any group that may include minors. The webcam is never used: only tab switching, window focus, fullscreen and copy/paste events are recorded, and the consent notice says so.';
$string['noattempts'] = 'No attempts match.';
$string['noevents'] = 'No proctoring events for this attempt.';
$string['notenabled'] = 'Proctoring is not switched on for this quiz. Earlier data is still shown.';
$string['pluginname'] = 'Exam proctoring';
$string['privacy:metadata:consent'] = 'The exact consent notice a learner agreed to before a proctored attempt.';
$string['privacy:metadata:consent:attemptid'] = 'The attempt the consent was given for.';
$string['privacy:metadata:consent:notice'] = 'The lines of the notice as shown.';
$string['privacy:metadata:consent:timecreated'] = 'When consent was given.';
$string['privacy:metadata:consent:userid'] = 'The learner who consented.';
$string['privacy:metadata:consent:version'] = 'The version of the notice.';
$string['privacy:metadata:files'] = 'Webcam images captured during the quiz attempt.';
$string['privacy:metadata:log'] = 'Events recorded while a learner takes a proctored quiz.';
$string['privacy:metadata:log:attemptid'] = 'The quiz attempt the event belongs to.';
$string['privacy:metadata:log:details'] = 'Extra details about the event, for example how long the learner was away.';
$string['privacy:metadata:log:eventtype'] = 'The kind of event, for example tab switch or webcam snapshot.';
$string['privacy:metadata:log:hassnapshot'] = 'Whether a webcam image is stored with the event.';
$string['privacy:metadata:log:isviolation'] = 'Whether the event counts as a violation.';
$string['privacy:metadata:log:quizid'] = 'The quiz.';
$string['privacy:metadata:log:timecreated'] = 'When the event happened.';
$string['privacy:metadata:log:userid'] = 'The learner taking the quiz.';
$string['privacy:metadata:session'] = 'The time and version of the consent notice are kept in the session until the attempt starts.';
$string['proctorednotice'] = 'This quiz is proctored. Your webcam and browser activity are monitored during the attempt. Take it in a web browser on a computer.';
$string['proctorednotice_minors'] = 'This quiz is proctored. Your browser activity (tab switching, fullscreen, copy and paste) is recorded during the attempt. The webcam is not used.';
$string['reporttitle'] = 'Proctoring report';
$string['requirecamera'] = 'Require webcam';
$string['requirecamera_help'] = 'Learners must allow webcam access for the whole attempt. Snapshots are saved for the proctoring report. A stopped, covered, black or frozen camera blocks the quiz until it is fixed and is recorded as a violation. Browsers require HTTPS for webcam access.';
$string['requirefullscreen'] = 'Require fullscreen';
$string['requirefullscreen_help'] = 'The quiz page is hidden until the learner enters fullscreen. Browsers always leave fullscreen when a page reloads, so set Layout > New page to "Never, all questions on one page" for proctored quizzes.';
$string['retentiondays'] = 'Keep proctoring data for (days)';
$string['retentiondays_help'] = 'Events and webcam images of this quiz are deleted this many days after they were recorded. 0 uses the site default.';
$string['retentiondaysinvalid'] = 'Enter a number of days from 0 to 3650.';
$string['retentionkeep'] = 'Events and images are kept until the quiz is deleted.';
$string['retentionnotice'] = 'Events and images are deleted automatically {$a} days after they were recorded.';
$string['searchstudent'] = 'Learner name or email';
$string['sessions'] = 'Browser sessions';
$string['settings_allowautosubmit'] = 'Allow auto-submit after violations';
$string['settings_allowautosubmit_desc'] = 'Off by default: violations are recorded and flagged for a teacher to judge, and no attempt is submitted automatically. When ticked, teachers can set "Violations before auto-submit" per quiz.';
$string['settings_proctorpreviews'] = 'Apply proctoring to teacher previews (testing)';
$string['settings_proctorpreviews_desc'] = 'When ticked, the browser-side checks also run when a teacher or admin previews a quiz, so you can test without a learner account. Previews are never recorded, never count violations and are never auto-submitted.';
$string['settings_retention'] = 'Keep proctoring data for (days)';
$string['settings_retention_desc'] = 'Site default: events and webcam images are deleted this many days after they were recorded. A quiz can set its own period. 0 = keep until the quiz is deleted (not recommended under GDPR / DPDP).';
$string['showallevents'] = 'Show all events';
$string['showviolationsonly'] = 'Show violations only';
$string['snapshot'] = 'Image';
$string['snapshotinterval'] = 'Snapshot interval';
$string['snapshotinterval_help'] = 'How often a webcam snapshot is saved. 60 seconds is recommended for large exams: shorter intervals multiply the load on the server.';
$string['snapshotof'] = 'Webcam image at {$a}';
$string['snapshots'] = 'Snapshots';
$string['snapshotsreceived'] = 'Snapshots (received / expected)';
$string['sort_name'] = 'Learner name';
$string['sort_needs'] = 'Needs a look first';
$string['sort_recent'] = 'Most recent first';
$string['sort_violations'] = 'Most violations first';
$string['sortby'] = 'Sort';
$string['state'] = 'State';
$string['task_cleanup'] = 'Delete expired proctoring events and images';
$string['task_enforceautosubmit'] = 'Submit attempts that reached the proctoring violation limit';
$string['time'] = 'Time';
$string['user'] = 'Learner';
$string['viewdetails'] = 'View details';
$string['viewreportlink'] = 'View proctoring report';
$string['violation'] = 'Violation';
$string['violations'] = 'Violations';
$string['webservicenotallowed'] = 'This quiz is proctored and can only be taken in a web browser, not in the mobile app.';
