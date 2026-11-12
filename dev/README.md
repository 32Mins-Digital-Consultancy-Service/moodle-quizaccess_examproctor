# Check scripts (not part of the plugin package)

Re-runnable checks used for each release. Run them on a **test** Moodle only: they create courses, users and attempts.

    export MOODLE_DIR=/path/to/moodle            # folder with config.php
    export EP_IDS=/tmp/examproctor_ids.json      # written by seed.php
    php dev/seed.php                             # course, quiz, 3 users (student1, student2, teacher1; Pass#1234)
    php dev/check_core.php                       # 42 server-side checks (attempts, quotas, auto-submit, report, retention, privacy)

`seed.php` uses Moodle's data generator; it includes a tiny stand-in for PHPUnit's TestCase so it runs without
PHPUnit installed.

Browser checks (real headless Chromium with a fake camera):

    npm i @sparticuz/chromium@131 puppeteer-core@23
    EP_URL=http://localhost EP_CMID=<quiz cmid> node dev/browser/flow.js          # answer, next page, submit
    EP_URL=http://localhost EP_CMID=<quiz cmid> node dev/browser/flow_refuse.js   # browser refuses fullscreen

PHPUnit tests are in `tests/` and run in CI (`.github/workflows/ci.yml`).
