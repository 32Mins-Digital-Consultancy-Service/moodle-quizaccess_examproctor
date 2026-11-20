# Exam proctoring for Moodle quizzes

`quizaccess_examproctor` · 1.3.4 · Moodle 4.5 LTS to 5.3 LTS · GPL v3

Browser-based proctoring for the standard Moodle quiz. Learners agree to a clear consent notice, the webcam takes
snapshots, tab and window switches are recorded with the time away, and teachers get one report per quiz. It works
inside the quiz you already use: open and close dates, time limits, attempts, overrides and grades keep working.

## What learners see

- A consent notice before every attempt that lists exactly what this quiz checks, and a camera check before the
  exam timer starts.
- During the attempt, a small "Proctoring on" label and their own camera preview in the corner (a click makes it
  smaller).
- If something needs fixing (camera covered, fullscreen left), the paper is hidden behind a short message until it
  is fixed. When something is recorded, a short note appears at the top for a few seconds.

## What is checked

| Check | Recorded as |
|---|---|
| Webcam on for the whole attempt, a snapshot every 30, 60, 120 or 300 s | Snapshot |
| Camera covered, dark or glowing red (finger on the lens) | Violation, with a picture |
| Camera refused, stopped or frozen | Flag for review |
| Leaving the quiz tab or window, with the time away | Violation (a switch under 2 s is a flag) |
| Leaving fullscreen | Violation. If the browser refuses fullscreen, each quiz chooses: continue and flag, or keep the paper hidden |
| Copy, paste, right-click | Blocked and recorded as a violation |
| The same attempt open in a second tab, browser or device | Flag |

A problem that lasts counts once. One action that raises two events (leaving for another app) counts once.
Optional auto-submit after a set number of violations stays off for the whole site until an administrator allows it.
"Learners may be under 18" turns the webcam off: only browser events are recorded and the consent notice says so.

## What teachers get

**View proctoring report** on the quiz page:

- every attempt, with a one-line summary (violations · flags · missing snapshots · sessions) and live status while
  attempts run;
- sort ("needs a look first"), search and filter;
- a timeline per attempt with pictures, violations in red and flags in orange;
- CSV and Excel downloads;
- a button to delete one attempt's evidence (the consent record is kept until normal retention).

## Requirements

Moodle 4.5 LTS to 5.3 LTS, HTTPS, and cron every minute. Learners need a laptop or desktop with a webcam and a
current Chrome, Edge or Firefox. The Moodle mobile app is refused for proctored quizzes.

## Install

1. Download the ZIP from this repository's **Releases** page (or clone the repository).
2. Site administration > Plugins > Install plugins, and upload the ZIP. Or copy the folder to
   `mod/quiz/accessrule/examproctor` (Moodle 5.1+: `public/mod/quiz/accessrule/examproctor`) and open Site
   administration > Notifications.
3. Edit a quiz > Extra restrictions on attempts > **Enable exam proctoring**.

Recommended: Layout > New page = "Never, all questions on one page" (browsers leave fullscreen on every page load),
and a 60-second snapshot interval for large exams.

Site settings (Site administration > Plugins > Activity modules > Quiz > Exam proctoring): data retention (30 days
by default, per-quiz override), allow auto-submit, and apply proctoring to teacher previews (for testing).

## With Safe Exam Browser

A web page cannot stop a learner switching apps or using a second device; this plugin detects and records it. To
prevent it on institution computers, also use Moodle's own Safe Exam Browser rule:

1. Install Safe Exam Browser on the exam computers.
2. In the quiz, Safe Exam Browser > Require the use of Safe Exam Browser = "Yes - Configure manually".
3. Keep "Enable exam proctoring" on, and allow the camera in Safe Exam Browser's settings.
4. Try one attempt on an exam computer before the exam.

## Privacy

Webcam images are personal data. Tell learners, keep the retention period short and follow local law (GDPR, DPDP
Act). Events and images are deleted automatically after the retention period; Moodle's privacy tools export and
delete a learner's data. Backup, restore and duplicate copy the settings, never the evidence.

## For developers

Other plugins can add checks through hooks and the public API `quizaccess_examproctor\api`. Run `npx grunt amd` in
a Moodle checkout after changing `amd/src`. Check scripts live under `dev/` (not in the release ZIP).

## Licence and credits

GNU General Public License v3 or later (see COPYING.txt), like Moodle itself.

Made by 32Mins Digital, the team behind ChitraVaani. Developed by Sribalaji Ravi and team.
