# Changes

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
