# Exam proctoring (quizaccess_examproctor)

Browser-based proctoring for Moodle quizzes. Free and complete on its own: no account, no licence, no limits.

## Features

- Consent notice before every attempt, recorded with its version, time and number of items
- Webcam required for the whole attempt, snapshots every 30-300 s
- Stopped, refused, covered, black or frozen camera blocks the quiz and is recorded with an image
- Tab switching and window focus loss, recorded with the time away
- Fullscreen enforcement; copy, paste and right-click blocking
- Auto-submit after N violations - the page is saved first, so typed answers are kept
- "Learners may be under 18" mode: no camera, browser events only
- Report: every attempt (attempts that sent nothing are listed first), live status, snapshots received vs expected,
  multiple-session flag, search and filters, paging, timeline with images, CSV / Excel / ODS / JSON export
- Retention: site default 30 days, per-quiz override, nightly deletion, "delete this attempt's evidence" button
- Per-attempt server quotas on events and images; images checked for type, size and dimensions
- Moodle events (violation, auto-submit, consent, evidence deleted) in the standard log
- Quiz backup, restore and duplication keep the settings (evidence is never copied)
- Privacy API (export and delete); capabilities flagged RISK_PERSONAL
- Mobile app / web-service clients are refused (the quiz must be taken in a browser)
- Hooks (Moodle Hooks API) so other plugins can add checks: settings_form, settings_validate, settings_save,
  settings_delete, settings_sql, attempt_page, event_types, new_attempt, consent_items, report_links

## Requirements

Moodle 4.5 or later, HTTPS. Students: a computer with a webcam and a current Chrome, Edge or Firefox.

## Install

1. Copy `examproctor` to `<moodle>/mod/quiz/accessrule/examproctor` (Moodle 5.1+: `<moodle>/public/mod/quiz/accessrule/examproctor`).
2. Site administration > Notifications. Purge caches.
3. Edit a quiz > Extra restrictions on attempts > Enable exam proctoring.

Recommended for proctored quizzes: Layout > New page = "Never, all questions on one page" (browsers leave fullscreen
on every page reload), snapshot interval 60 s for large exams.

## Limits of browser proctoring

A web page cannot lock a computer: switching tabs or a second device can be detected and recorded, not prevented.
For a locked-down device use Safe Exam Browser (supported by Moodle core) together with this plugin.

## Development

`amd/build/*.min.js` are copies of the ES5 sources; run `grunt amd` for a minified build with source maps.
