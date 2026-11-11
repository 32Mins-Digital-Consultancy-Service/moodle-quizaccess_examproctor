# Changes

## 1.2.1 (2026-11-11)

- Fix: when the browser refused fullscreen (browser policy, kiosk mode, quiz opened in a frame, or the request not
  counted as a click), the "must be taken in fullscreen" screen stayed and blocked every click. Now, if fullscreen
  fails or does not start within 3 s, the quiz continues, the student sees a notice, and "Fullscreen not available"
  is flagged in the report (not counted as a violation). Verified in a real Chromium 131 browser.

## 1.2.0 (2026-11-11)

Fixes from the rc1 review.

- Quota flood: harmless events and violations have separate quotas; filling the harmless quota can no longer stop
  violations being recorded; over the image quota the event is kept and only the image dropped.
- Heartbeat: the browser sends a heartbeat every minute (one row per attempt, updated in place), so a quiet honest
  student with the camera off or in under-18 mode no longer shows as SILENT.
- Repeat counting: a problem that lasts (frozen or covered camera) counts once; while it continues it is recorded
  as "Problem still going on" with an image, not as new violations. The same violation within 5 s counts once.
  Camera warm-up: the first 5 s after the camera starts are not judged.
- Auto-submit dodge: a scheduled task (every minute) submits attempts that reached the limit when the browser did not.
- Rules: auto-submit after violations is off for the whole site until an admin allows it ("never auto-penalties").
- Real minified JavaScript (terser) with source maps; PHPUnit tests and a moodle-plugin-ci workflow.
- Extension event types can be flagged for review in the report.

## 1.1.1 (2026-11-10)

- Fix (critical, 1.1.0 only): the browser script stopped at start-up ("Cannot read properties of undefined
  (reading 'strings')"), so no camera, fullscreen, copy/paste blocking or events ran and every attempt showed
  "No proctoring data". The script now runs; verified in a simulated browser end to end (camera refusal screen,
  screen-share and fullscreen screens, tab-away time, clipboard, auto-submit with the page saved first).

## 1.1.0 (2026-11-10)

- Fix: the attempt page passed over 1 KB of settings through js_call_amd(), which shows a Moodle debugging warning
  to students when developer debugging is on. Settings are now passed as page data.
- Version aligned with Exam Proctor Pro 1.1.0 (which requires this version). No database change.

## 1.0.0-rc1 (2026-11-07)

Audit fixes and the free-core split.

- S1 Report lists attempts with no proctoring data (first), expected vs received snapshots, live status.
- S2 Per-attempt quotas on events and images; logged once as "too many events".
- S3 Web-service clients (mobile app, tokens) are refused by prevent_access().
- S4 Proctoring also runs on the summary page.
- S5 Slow computers: the extra analysers are switched off with a distinct "analysis switched off" event; the
  covered / frozen camera checks keep running.
- S6 Consent recorded per attempt with notice version (log row + Moodle event).
- S7 Images checked for type, byte size and pixel dimensions per event type; server timestamps only.
- S10 Capabilities carry RISK_PERSONAL (and RISK_DATALOSS for deleting evidence).
- S11 An attempt opened in a second tab, browser or device is flagged.
- R1 Backup / restore / duplicate keep the settings.
- R2 Retention for all evidence (site default + per quiz), nightly task, delete-evidence button.
- R3 Auto-submit uses process_submit() + process_grade_submission() on Moodle 5.x, process_finish() on 4.5.
- R5 Auto-submit asks the browser to submit the page first (answers kept); the server finishes after 20 s.
- R6 Devices without fullscreen are recorded ("fullscreen not supported").
- R7 Tab and window events carry the time away.
- R8 Report paging, search, filters, CSV/Excel/ODS/JSON.
- L3 "Learners may be under 18" mode (no camera).
- L4 Complete privacy metadata.
- U2 Blocking screen is an accessible dialog (role, aria-modal, focus).
- C3 Requires Moodle 4.5; supported 4.5-5.3.
- Face, head-turn, motion, same-person, identity, microphone and screen checks moved out of the core; their
  per-quiz settings are kept for import by an extending plugin. The same-person (face matching) check is removed.
