# Exam proctoring (quizaccess_examproctor) 1.3.1

Browser-based proctoring for Moodle quizzes: consent, webcam, focus, fullscreen and clipboard checks, and a
report for teachers. Moodle 4.5 LTS to 5.3 LTS.

## Features

- Consent notice before every attempt; the exact text agreed to is stored with the attempt
- Camera check on the consent screen, before the exam timer starts
- Webcam required for the whole attempt, snapshots every 30-300 s
- Stopped, refused, covered or frozen camera blocks the quiz until fixed (covered counts as a violation; the others
  are flagged for review)
- Tab switching and window focus loss, recorded with the time away
- Fullscreen enforcement; per quiz, if the browser refuses fullscreen: continue and flag it, or keep the paper hidden
- Copy, paste and right-click blocking
- Optional auto-submit after N violations: off for the whole site until an administrator allows it
- Heartbeat every minute, so quiet learners are not reported as silent
- "Learners may be under 18" mode: no camera, browser events only
- Report: every attempt (no-data attempts first), live status, a one-line summary, snapshots received vs expected,
  multiple-session flag, sort and filters, paging, timeline with images, CSV / Excel export
- Retention (site default 30 days, per-quiz override), delete-evidence button, privacy export and delete
- Moodle events; backup, restore and duplicate keep settings; mobile app / web-service access refused
- Hooks and a public API (`quizaccess_examproctor\api`) so other plugins can add checks

## Requirements

Moodle 4.5 LTS to 5.3 LTS, HTTPS, cron every minute. Learners: a computer with a webcam and current Chrome, Edge or
Firefox.

## Install

1. Copy `examproctor` to `mod/quiz/accessrule/examproctor` (Moodle 5.1+: `public/mod/quiz/accessrule/examproctor`).
2. Site administration > Notifications. Purge caches.
3. Edit a quiz > Extra restrictions on attempts > Enable exam proctoring.

Recommended: Layout > New page = "Never, all questions on one page" (browsers leave fullscreen on every page load),
snapshot interval 60 s for large exams.

## Using it with Safe Exam Browser

A web page cannot stop a student switching tabs or apps; this plugin detects and records it. To prevent it on
institution computers, also use Moodle's own Safe Exam Browser rule (`quizaccess_seb`, part of Moodle):

1. Install Safe Exam Browser (Windows or macOS) on the exam computers.
2. In the quiz, Safe Exam Browser > Require the use of Safe Exam Browser = "Yes - Configure manually".
3. Keep "Enable exam proctoring" on. In Safe Exam Browser settings, allow the camera (SEB 3.6+ on Windows: "Allow
   access to camera"), and leave the quiz's "Require fullscreen" on: SEB runs in kiosk mode, and if fullscreen is
   refused the quiz continues and the report shows it.
4. Run a test attempt on one exam computer before the exam.

What each adds: Safe Exam Browser locks the computer (no other apps, no tab switching); this plugin adds the webcam,
consent record, snapshots, flags and the report. This combination has not yet been tested in our own runs.

## Limits of browser proctoring

Switching tabs or a second device can be detected and recorded, not prevented, without Safe Exam Browser. Webcam
images are personal data: inform learners, keep the retention period short and check local law (GDPR, DPDP Act).

## Development

`amd/build` is minified with terser; run `npx grunt amd` in a Moodle checkout before a release. Re-runnable check
scripts live in the git repository under `dev/` (not in the release ZIP).
