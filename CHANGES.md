# Changes

## 1.3.4 (2026-11-20)

- One action, one violation: leaving for another app loses the window focus and then hides the tab; within 5 seconds
  the two count once (the second is kept as a repeat).
- A window switch shorter than 2 seconds is flagged for review ("Switched window briefly"), not counted as a
  violation. Short switches are often the browser's own prompts and bars. A switch dropped by the browser's rate limit
  can no longer appear only as "Came back".
- The camera permission prompt no longer counts as leaving the quiz.
- A finger over the camera in a lit room (a red glow instead of a dark picture) now counts as a covered camera.
- Calmer look: a small "Proctoring on" label with a status dot, short notes at the top instead of a full-width red bar,
  and the blocking screen shows its message on a plain card in the site theme's colours.
- Clearer, shorter wording for learners; "email" and "no signal" in the report. Consent notice version 2026-11-20.
- Copyright holder: 32Mins Digital.

## 1.3.3 (2026-11-19)

- Moodle coding style: code checker, PHPDoc and ESLint clean; continuous integration passes on Moodle 4.5 and 5.3.
- GPL v3 licence file (COPYING.txt) and licence section in the README.

## 1.3.2 (2026-11-17)

- Consent notice version 2026-11-14 (the notice wording changed in 1.3.1/1.3.2).

## 1.3.1 (2026-11-14)

- New per-quiz choice "If the browser refuses fullscreen": continue and flag it (default), or keep the paper hidden
  until fullscreen works (for computers you control, such as a lab).
- Deleting an attempt's evidence keeps the consent record until the normal retention period, as proof that the
  learner agreed; retention and privacy deletion still remove it.
- "Learner" instead of "student" in the plugin's own text.
- Tested on PHP 8.4 and MariaDB in addition to PostgreSQL.

## 1.3.0 (2026-11-12)

- Moodle 5.3 LTS: tested on 5.3.1 and 4.5; continuous integration covers 4.5 and 5.3 with PostgreSQL and MariaDB.
- Camera check on the consent screen: students allow the camera before the attempt starts, not in exam time.
- The exact consent notice each student agreed to is stored with the attempt (with a SHA-256 hash), and included in
  privacy export and delete.
- A refused, stopped or frozen camera is recorded and flagged for review, but no longer counts as a violation.
- Automatic submit uses Moodle's form change checker, so no "Leave page?" prompt can stop it.
- The every-minute automatic-submit task uses an index and stays fast on large logs.
- Report: one-line summary per attempt (violations, flags, missing snapshots, sessions) and a sort menu.
  Opening an attempt's evidence is logged as a Moodle event.
- Your camera preview sits at the bottom left and can be made smaller with a click.
- Webcam image limits match the images actually sent (320 x 240).
- Public API class (quizaccess_examproctor\api) for plugins that extend this one through hooks.

## 1.2.1 (2026-11-11)

- When the browser refuses fullscreen, the quiz continues, the student is told, and the teacher sees a flag.

## 1.2.0 (2026-11-11)

- Harmless events and violations have separate quotas; images over their quota are dropped, never the event.
- Heartbeat every minute, so quiet students are not reported as silent.
- A lasting problem counts once; the same violation within 5 seconds counts once; 5-second camera warm-up.
- A task submits attempts that reached the violation limit when the browser did not.
- Automatic submit after violations is off for the whole site until an administrator allows it.
- Minified JavaScript with source maps, PHPUnit tests and a CI workflow.

## 1.1.1 (2026-11-10)

- Fixed a start-up error in the browser script.

## 1.1.0 (2026-11-10)

- Settings are passed to the page without a Moodle debugging warning.

## 1.0.0-rc1 (2026-11-07)

- Report lists every attempt, including those that sent no proctoring data; live status; expected vs received
  snapshots; search, filters, paging and CSV / Excel export.
- Per-attempt quotas; image type and size checks; consent recorded; multiple browser sessions flagged.
- Mobile app and web-service access refused. Proctoring on the summary page. Backup, restore and duplicate keep the
  settings. Retention and an evidence delete button. "Learners may be under 18" mode. Moodle events. Hooks.
