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
 * Browser-side proctoring for quiz attempts.
 *
 * Core checks: webcam alive / covered / frozen (pixel maths, no model), snapshots, tab and window focus with the
 * time away, fullscreen, copy/paste. Other plugins add checks through the extension API (see `api` below):
 * the server lists their AMD modules in cfg.extensions and each module's init(api, config) is called.
 *
 * Written in ES5 AMD syntax.
 *
 * @module     quizaccess_examproctor/proctor
 * @copyright  2026 32Mins Digital
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core_form/changechecker'], function(Ajax, ChangeChecker) {
    'use strict';

    var SNAPSHOT_WIDTH = 320;
    var SNAPSHOT_HEIGHT = 240;
    var THROTTLE_MS = 3000; // Same event type at most this often.
    var TICK_MS = 500; // Camera analysis cadence.
    var REPEAT_MS = 15000; // While a problem continues, record it again this often.
    var GRACE_MS = 3000; // A problem must last this long before it blocks and is recorded.
    var CAMERA_GRACE_MS = 2500; // Don't flash the camera screen while a remembered permission starts the camera.
    var SLOW_PASS_MS = 800; // An analysis pass slower than this counts as slow...
    var SLOW_PASSES = 3; // ...and this many in a row switch the extra analysers off.
    var DARK_LEVEL = 12; // Mean brightness (0-255) below which the camera counts as covered.
    var FLAT_LEVEL = 8; // Brightness spread below which the picture is a flat field (hand over the lens).
    // A finger over the lens in a lit room glows red instead of going dark: almost every pixel strongly red...
    var GLOW_SHARE = 0.9;
    // ...and almost no detail (mean difference between neighbouring pixels, 0-255).
    var GLOW_DETAIL = 4;
    var BRIEF_MS = 2000; // A window switch shorter than this is flagged for review, not counted as a violation.
    var PROMPT_SUSPEND_MS = 15000; // Ignore focus changes while the browser's camera permission prompt is open.
    var RESUME_TAIL_MS = 1000; // ...and for this long after it closes, while focus returns to the page.
    var SAMPLE_W = 64; // Tiny grayscale copy used for covered / frozen / motion maths.
    var SAMPLE_H = 48;
    var MAX_GAP_MS = 4000; // Frames further apart than this are not compared.
    var NOISE_DELTA = 0.5; // Any pixel change above this means the feed is alive.
    var FROZEN_IDENTICAL_MS = 15000;
    var FROZEN_STALL_MS = 5000;
    var WARMUP_MS = 5000; // Ignore covered / frozen while the camera adjusts after starting.
    var HEARTBEAT_MS = 60000; // "Still here" signal, so a quiet learner never looks silent in the report.
    var VIOLATION_TYPES = ['camera_denied', 'camera_lost', 'camera_covered', 'video_frozen', 'tab_hidden',
        'window_blur', 'fullscreen_exit', 'copy', 'paste', 'contextmenu'];

    var cfg = null;
    var strings = {};
    var started = false;
    var debugMode = /[?&]examproctordebug=1/.test(window.location.search);

    var video = null;
    var canvas = null;
    var stream = null;
    var cameraActive = false;
    var cameraFailed = false;
    var cameraGraceUntil = 0;
    var cameraWarmUntil = 0;

    var overlay = null;
    var overlayText = null;
    var overlayButton = null;
    var banner = null;
    var bannerTimer = null;
    var badgeEl = null;
    var badgeDot = null;
    var badgeText = null;

    var blockers = {camera: false, fullscreen: false, problem: null};
    var extraBlockers = []; // [{name, text, button, onClick}] in the order they were added.
    var focusSuspendedUntil = 0;
    var lastSent = {};
    var submitting = false;

    var analysers = [];
    var analysersOff = false;
    var slowPasses = 0;
    var lastTickMs = 0;
    var problem = null;
    var problemSince = 0;
    var lastProblemReport = 0;

    var sampleCanvas = null;
    var prevFrame = null;
    var prevTime = 0;
    var identicalSince = 0;
    var lastVideoTime = -1;
    var videoTimeSince = 0;

    var hiddenAt = 0;
    var fullscreenWaived = false; // The browser refused fullscreen: the quiz continues and the report shows it.
    var fullscreenRefused = false; // Strict quizzes: the browser refused, the paper stays hidden.
    var blurAt = 0;
    var blurConfirmed = false; // The window switch lasted BRIEF_MS and was recorded as a violation.
    var blurTimer = null;

    // Small per-attempt memory in this tab, so a page reload does not start from scratch.
    var store = {
        get: function(key) {
            try {
                return window.sessionStorage.getItem('examproctor_' + cfg.attemptid + '_' + key);
            } catch (e) {
                return null;
            }
        },
        set: function(key, value) {
            try {
                window.sessionStorage.setItem('examproctor_' + cfg.attemptid + '_' + key, value);
            } catch (e) {
                // Storage unavailable: start fresh on every page.
            }
        }
    };

    // -------------------------------------------------------------------
    // Server communication.
    // -------------------------------------------------------------------

    var sendEvent = function(type, details, imagedata) {
        return Ajax.call([{
            methodname: 'quizaccess_examproctor_log_event',
            args: {
                attemptid: cfg.attemptid,
                eventtype: type,
                details: details || '',
                imagedata: imagedata || ''
            }
        }])[0].then(function(result) {
            handleResult(type, result);
            return result;
        }, function() {
            // Network or server errors must never break the quiz itself.
            return null;
        });
    };

    var report = function(type, details, imagedata) {
        var now = Date.now();
        if (lastSent[type] && now - lastSent[type] < THROTTLE_MS) {
            return;
        }
        lastSent[type] = now;
        sendEvent(type, details, imagedata);
    };

    /**
     * Stop Moodle's "Leave page? Changes may not be saved" prompt from blocking an automatic submit or redirect.
     *
     * @param {HTMLFormElement|null} form
     */
    var markFormSubmitted = function(form) {
        try {
            if (form) {
                ChangeChecker.markFormSubmitted(form);
            }
            ChangeChecker.disableAllChecks();
        } catch (e) {
            // Older pages without the module: nothing to suppress.
        }
    };

    /**
     * Submit the attempt from the page, so answers typed since the last autosave are saved too.
     */
    var submitAttempt = function() {
        if (submitting) {
            return;
        }
        submitting = true;
        showOverlay(strings.autosubmit, null);
        var form = document.getElementById('responseform');
        if (form) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'finishattempt';
            input.value = '1';
            form.appendChild(input);
            markFormSubmitted(form);
            form.submit();
        } else {
            sendEvent('autosubmit_now', '');
        }
    };

    var handleResult = function(type, result) {
        if (!result) {
            return;
        }
        if (result.autosubmitted && result.redirecturl) {
            showOverlay(strings.autosubmit, null);
            markFormSubmitted(document.getElementById('responseform'));
            window.location.href = result.redirecturl;
            return;
        }
        if (result.submitnow) {
            submitAttempt();
            return;
        }
        if (cfg.preview && (VIOLATION_TYPES.indexOf(type) !== -1 || strings['warn_' + type])) {
            showBanner((strings['warn_' + type] || strings.warn_generic) + ' ' + strings.preview_note);
            return;
        }
        if (result.violation) {
            var message = strings['warn_' + type] || strings.warn_generic;
            if (result.maxviolations > 0) {
                message += ' (' + result.violations + '/' + result.maxviolations + ')';
            }
            showBanner(message);
        }
    };

    // -------------------------------------------------------------------
    // Banner, badge and the blocking screen. Kept small and calm: the quiz stays the main thing on the page,
    // and the theme's own colours are used so the look fits every Moodle site.
    // -------------------------------------------------------------------

    var showBanner = function(message) {
        if (!banner) {
            banner = document.createElement('div');
            banner.setAttribute('role', 'alert');
            banner.className = 'alert alert-warning shadow-sm mb-0';
            banner.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:100001;' +
                'pointer-events:none;width:max-content;max-width:calc(100vw - 24px);padding:8px 16px;' +
                'font-size:0.95rem;line-height:1.4;text-align:center;border-radius:12px;';
            document.body.appendChild(banner);
        }
        banner.textContent = message;
        banner.style.display = 'block';
        window.clearTimeout(bannerTimer);
        bannerTimer = window.setTimeout(function() {
            banner.style.display = 'none';
        }, 6000);
    };

    var updateBadge = function() {
        if (!badgeEl) {
            return;
        }
        var blocked = overlay && overlay.style.display !== 'none';
        badgeDot.style.background = blocked ? 'var(--bs-warning, #f0ad4e)' : 'var(--bs-success, #198754)';
        var text = cfg.preview ? strings.active_preview : strings.active;
        if (cfg.preview || debugMode) {
            var camerastate = 'not used';
            if (cfg.requirecamera) {
                camerastate = cameraActive ? 'on' : 'off';
            }
            text += ' | v' + (cfg.release || '?') + ' | ' + cfg.mode +
                ' | camera: ' + camerastate +
                ' | analysers: ' + (analysersOff ? 'OFF (too slow)' : analysers.map(function(a) {
                    return a.name;
                }).join(',') || 'none') +
                ' | fullscreen: ' + (cfg.requirefullscreen ? 'required' : 'no') +
                ' | issue: ' + (problem || 'none') +
                ' | pass: ' + lastTickMs + ' ms' +
                ' | screen: ' + (blocked ? 'BLOCKED' : 'clear');
        }
        badgeText.textContent = text;
    };

    var buildBadge = function() {
        badgeEl = document.createElement('div');
        badgeEl.setAttribute('aria-hidden', 'true');
        badgeEl.className = 'badge badge-light bg-light text-dark border rounded-pill text-wrap text-start';
        badgeEl.style.cssText = 'position:fixed;left:12px;bottom:12px;z-index:100000;font-size:12px;font-weight:500;' +
            'pointer-events:none;max-width:70vw;padding:4px 10px;opacity:0.9;';
        badgeDot = document.createElement('span');
        badgeDot.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;' +
            'vertical-align:middle;';
        badgeText = document.createElement('span');
        badgeText.style.verticalAlign = 'middle';
        badgeEl.appendChild(badgeDot);
        badgeEl.appendChild(badgeText);
        document.body.appendChild(badgeEl);
        updateBadge();
    };

    var buildOverlay = function() {
        overlay = document.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', strings.dialoglabel || 'Exam proctoring');
        // The backdrop keeps the paper hidden; the message sits on a plain card in the theme's colours.
        overlay.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(17,17,17,0.94);' +
            'align-items:center;justify-content:center;padding:16px;';
        var card = document.createElement('div');
        card.className = 'card shadow';
        card.style.cssText = 'width:100%;max-width:30rem;padding:28px 24px;text-align:center;border-radius:12px;';
        var label = document.createElement('div');
        label.className = 'text-muted small mb-2';
        label.textContent = strings.dialoglabel || 'Exam proctoring';
        overlayText = document.createElement('p');
        overlayText.id = 'examproctor-overlay-text';
        overlayText.setAttribute('aria-live', 'assertive');
        overlayText.style.cssText = 'font-size:1.15rem;line-height:1.45;margin:0 0 20px;';
        overlayButton = document.createElement('button');
        overlayButton.type = 'button';
        overlayButton.className = 'btn btn-primary';
        overlayButton.addEventListener('click', onOverlayButton);
        overlay.setAttribute('aria-describedby', overlayText.id);
        card.appendChild(label);
        card.appendChild(overlayText);
        card.appendChild(overlayButton);
        overlay.appendChild(card);
        // Keep keyboard focus inside the dialog while it is shown.
        overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                if (overlayButton.style.display !== 'none') {
                    overlayButton.focus();
                }
            }
        });
        document.body.appendChild(overlay);
    };

    var showOverlay = function(text, buttonLabel) {
        var wasHidden = overlay.style.display === 'none';
        overlayText.textContent = text;
        if (buttonLabel) {
            overlayButton.textContent = buttonLabel;
            overlayButton.style.display = '';
        } else {
            overlayButton.style.display = 'none';
        }
        overlay.style.display = 'flex';
        overlay.style.pointerEvents = 'auto';
        if (wasHidden) {
            overlay.tabIndex = -1;
            (buttonLabel ? overlayButton : overlay).focus();
        }
    };

    var hideOverlay = function() {
        overlay.style.display = 'none';
        overlay.style.pointerEvents = 'none';
    };

    var activeExtraBlocker = function() {
        return extraBlockers.length ? extraBlockers[0] : null;
    };

    var refreshOverlay = function() {
        if (submitting) {
            return;
        }
        var extra = activeExtraBlocker();
        if (blockers.camera) {
            if (cameraFailed || Date.now() >= cameraGraceUntil) {
                showOverlay(strings.camerarequired, strings.camerabutton);
            } else {
                hideOverlay();
            }
        } else if (extra) {
            showOverlay(extra.text, extra.button || null);
        } else if (blockers.fullscreen) {
            showOverlay(fullscreenRefused ? strings.fullscreen_refused : strings.fullscreenrequired, strings.fullscreenbutton);
        } else if (blockers.problem) {
            // No button: clears by itself once the problem is fixed.
            showOverlay(strings['ovl_' + blockers.problem] || strings.warn_generic, null);
        } else {
            hideOverlay();
        }
        updateBadge();
    };

    var onOverlayButton = function() {
        var extra = activeExtraBlocker();
        if (blockers.camera) {
            startCamera();
        } else if (extra && extra.onClick) {
            extra.onClick();
        } else if (blockers.fullscreen) {
            requestFullscreen();
        }
    };

    /**
     * The browser cannot or will not go fullscreen (policy, kiosk, frame, old device). Never lock the learner out:
     * continue without fullscreen, record it for the teacher, and do not ask again in this attempt.
     *
     * @param {String} reason
     */
    var waiveFullscreen = function(reason) {
        if (fullscreenWaived || document.fullscreenElement) {
            return;
        }
        if (cfg.fullscreenstrict) {
            // This quiz keeps the paper hidden: record the refusal once and ask the learner to allow fullscreen.
            if (!store.get('fsrefused')) {
                store.set('fsrefused', '1');
                sendEvent('fullscreen_unsupported', 'refused (paper kept hidden): ' + reason);
            }
            fullscreenRefused = true;
            refreshOverlay();
            return;
        }
        fullscreenWaived = true;
        store.set('fswaived', '1');
        blockers.fullscreen = false;
        sendEvent('fullscreen_unsupported', reason);
        showBanner(strings.fullscreen_waived);
        refreshOverlay();
    };

    var requestFullscreen = function() {
        var el = document.documentElement;
        if (!el.requestFullscreen) {
            waiveFullscreen('requestFullscreen not available');
            return;
        }
        var check = window.setTimeout(function() {
            if (!document.fullscreenElement) {
                waiveFullscreen('fullscreen did not start within 3 s');
            }
        }, 3000);
        var request;
        try {
            request = el.requestFullscreen();
        } catch (e) {
            window.clearTimeout(check);
            waiveFullscreen(e && e.name ? e.name : 'refused');
            return;
        }
        if (request && request.catch) {
            request.catch(function(e) {
                window.clearTimeout(check);
                waiveFullscreen((e && (e.name || e.message)) ? String(e.name || e.message).substring(0, 100) : 'refused');
            });
        }
    };

    // -------------------------------------------------------------------
    // Camera.
    // -------------------------------------------------------------------

    var startCamera = function() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraFailed = true;
            report('camera_denied', 'getUserMedia unavailable (HTTPS required?)');
            blockers.camera = true;
            refreshOverlay();
            return;
        }
        // The browser's permission prompt takes focus: that is not the learner leaving the quiz.
        api.suspendFocus(PROMPT_SUSPEND_MS);
        navigator.mediaDevices.getUserMedia({
            video: {width: {ideal: 640}, height: {ideal: 480}},
            audio: false
        }).then(function(mediaStream) {
            api.suspendFocus(0);
            stream = mediaStream;
            video.srcObject = mediaStream;
            video.play();
            cameraActive = true;
            cameraWarmUntil = Date.now() + WARMUP_MS;
            blockers.camera = false;
            refreshOverlay();
            mediaStream.getVideoTracks().forEach(function(track) {
                track.addEventListener('ended', function() {
                    cameraActive = false;
                    blockers.camera = true;
                    cameraFailed = true;
                    report('camera_lost');
                    refreshOverlay();
                });
            });
            window.setTimeout(function() {
                var last = parseInt(store.get('snap') || '0', 10);
                if (Date.now() - last > cfg.snapshotinterval * 1000) {
                    takeSnapshot();
                }
            }, 3000);
            return mediaStream;
        }).catch(function(error) {
            api.suspendFocus(0);
            cameraActive = false;
            blockers.camera = true;
            cameraFailed = true;
            report('camera_denied', error && error.name ? error.name : '');
            refreshOverlay();
        });
    };

    var captureFrame = function() {
        if (!cameraActive || !video || video.readyState < 2) {
            return '';
        }
        canvas.getContext('2d').drawImage(video, 0, 0, SNAPSHOT_WIDTH, SNAPSHOT_HEIGHT);
        return canvas.toDataURL('image/jpeg', 0.6);
    };

    var takeSnapshot = function() {
        var data = captureFrame();
        if (data && !document.hidden) {
            store.set('snap', String(Date.now()));
            sendEvent('snapshot', '', data);
        }
    };

    var buildPreview = function() {
        video = document.createElement('video');
        video.muted = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('aria-hidden', 'true');
        // Bottom-left, above the status badge, away from the quiz's own buttons; a click shrinks or restores it.
        video.title = strings.selfview_toggle || '';
        video.style.cssText = 'position:fixed;left:12px;bottom:44px;width:160px;height:120px;z-index:100000;' +
            'border:2px solid var(--bs-primary, #0f6cbf);border-radius:6px;background:#000;object-fit:cover;cursor:pointer;';
        video.addEventListener('click', function() {
            var small = video.style.width === '64px';
            video.style.width = small ? '160px' : '64px';
            video.style.height = small ? '120px' : '48px';
        });
        document.body.appendChild(video);
        canvas = document.createElement('canvas');
        canvas.width = SNAPSHOT_WIDTH;
        canvas.height = SNAPSHOT_HEIGHT;
    };

    // -------------------------------------------------------------------
    // Core camera analysis (no model): covered and frozen camera; then the registered analysers.
    // -------------------------------------------------------------------

    /**
     * Shrink the frame to a 64x48 grayscale copy and compare it with the previous one.
     *
     * @return {Object} {fresh, changed, noisy, mean, spread, red, detail}: % of pixels moved / changed at all,
     *     brightness stats, share of strongly red pixels (0-1) and mean neighbour difference (detail, 0-255)
     */
    var sampleFrame = function() {
        var now = Date.now();
        if (!sampleCanvas) {
            sampleCanvas = document.createElement('canvas');
            sampleCanvas.width = SAMPLE_W;
            sampleCanvas.height = SAMPLE_H;
        }
        var ctx = sampleCanvas.getContext('2d', {willReadFrequently: true});
        ctx.drawImage(video, 0, 0, SAMPLE_W, SAMPLE_H);
        var data = ctx.getImageData(0, 0, SAMPLE_W, SAMPLE_H).data;
        var n = SAMPLE_W * SAMPLE_H;
        var cur = new Float32Array(n);
        var sum = 0;
        var reds = 0;
        var detail = 0;
        var i;
        for (i = 0; i < n; i++) {
            var r = data[i * 4];
            var g = data[i * 4 + 1];
            var b = data[i * 4 + 2];
            cur[i] = 0.299 * r + 0.587 * g + 0.114 * b;
            sum += cur[i];
            if (r > 40 && r > 1.6 * g && r > 1.6 * b) {
                reds++;
            }
            if (i % SAMPLE_W) {
                detail += Math.abs(cur[i] - cur[i - 1]);
            }
        }
        var mean = sum / n;
        var sq = 0;
        for (i = 0; i < n; i++) {
            cur[i] -= mean;
            sq += cur[i] * cur[i];
        }
        var result = {fresh: false, changed: 0, noisy: 0, mean: mean, spread: Math.sqrt(sq / n), red: reds / n,
            detail: detail / ((SAMPLE_W - 1) * SAMPLE_H)};
        if (prevFrame && now - prevTime <= MAX_GAP_MS) {
            var changed = 0;
            var noisy = 0;
            for (i = 0; i < n; i++) {
                var diff = Math.abs(cur[i] - prevFrame[i]);
                if (diff > 25) {
                    changed++;
                }
                if (diff > NOISE_DELTA) {
                    noisy++;
                }
            }
            result.fresh = true;
            result.changed = changed * 100 / n;
            result.noisy = noisy * 100 / n;
        } else {
            identicalSince = 0;
        }
        prevFrame = cur;
        prevTime = now;
        return result;
    };

    var coreCheck = function(sample) {
        var now = Date.now();
        var track = stream && stream.getVideoTracks()[0];
        if ((track && track.muted) || sample.mean < DARK_LEVEL || sample.spread < FLAT_LEVEL) {
            return {type: 'camera_covered', details: 'brightness ' + Math.round(sample.mean)};
        }
        if (sample.red >= GLOW_SHARE && sample.detail < GLOW_DETAIL) {
            return {type: 'camera_covered', details: 'brightness ' + Math.round(sample.mean) + ', red glow (lens covered)'};
        }
        if (!videoTimeSince || video.currentTime !== lastVideoTime) {
            lastVideoTime = video.currentTime;
            videoTimeSince = now;
        }
        if (sample.fresh) {
            identicalSince = sample.noisy < 1 ? (identicalSince || now) : 0;
        }
        if (now - videoTimeSince > FROZEN_STALL_MS) {
            return {type: 'video_frozen', details: 'video clock stalled'};
        }
        if (identicalSince && now - identicalSince > FROZEN_IDENTICAL_MS) {
            return {type: 'video_frozen', details: 'identical frames'};
        }
        return null;
    };

    var runAnalysers = function(sample) {
        if (analysersOff) {
            return null;
        }
        var ctx = {video: video, sample: sample, now: Date.now(), hidden: document.hidden};
        for (var i = 0; i < analysers.length; i++) {
            var res = null;
            try {
                res = analysers[i].evaluate(ctx);
            } catch (e) {
                res = null;
            }
            if (res && res.type && res.type !== 'ok') {
                return res;
            }
        }
        return null;
    };

    var tick = function() {
        if (!cameraActive || !video || video.readyState < 2) {
            return;
        }
        if (Date.now() < cameraWarmUntil) {
            // Exposure and focus settle during the first seconds: a dark or still first frame is not a problem.
            prevFrame = null;
            videoTimeSince = 0;
            return;
        }
        var sample = sampleFrame();
        var result = coreCheck(sample) || runAnalysers(sample);
        var now = Date.now();
        if (!result) {
            if (problem) {
                problem = null;
                blockers.problem = null;
                refreshOverlay();
            }
            return;
        }
        if (problem !== result.type) {
            problem = result.type;
            problemSince = now;
            lastProblemReport = 0;
        }
        var grace = result.grace !== undefined ? result.grace : GRACE_MS;
        if (now - problemSince >= grace) {
            blockers.problem = result.soft ? null : result.type;
            refreshOverlay();
            if (!lastProblemReport) {
                // A problem counts once, however long it lasts...
                lastProblemReport = now;
                sendEvent(result.type, result.details, captureFrame());
            } else if (now - lastProblemReport >= REPEAT_MS) {
                // ...while it goes on, evidence is kept without counting new violations.
                lastProblemReport = now;
                sendEvent('problem_continues', result.type + ' for ' + Math.round((now - problemSince) / 1000) + ' s' +
                    (result.details ? ', ' + result.details : ''), captureFrame());
            }
        }
    };

    var scheduleTick = function(delay) {
        window.setTimeout(function() {
            var took = 0;
            if (document.hidden) {
                // Timers are throttled in hidden tabs: start the frozen-video clocks again on return.
                videoTimeSince = 0;
                identicalSince = 0;
                prevFrame = null;
            } else {
                var t0 = Date.now();
                try {
                    tick();
                } catch (e) {
                    // Never let analysis errors stop the loop or the quiz.
                }
                took = Date.now() - t0;
                lastTickMs = took;
            }
            slowPasses = took > SLOW_PASS_MS ? slowPasses + 1 : 0;
            if (slowPasses >= SLOW_PASSES && analysers.length && !analysersOff) {
                // Switch the expensive analysers off, keep the cheap covered / frozen checks running.
                analysersOff = true;
                blockers.problem = null;
                problem = null;
                sendEvent('analysis_disabled', took + ' ms per pass');
                showBanner(strings.analysis_disabled_note);
                refreshOverlay();
            }
            updateBadge();
            scheduleTick(Math.max(TICK_MS, took * 6));
        }, delay);
    };

    // -------------------------------------------------------------------
    // Focus, fullscreen and clipboard.
    // -------------------------------------------------------------------

    var focusSuspended = function() {
        return Date.now() < focusSuspendedUntil;
    };

    var awayDetails = function(since) {
        return 'away ' + Math.max(1, Math.round((Date.now() - since) / 1000)) + ' s';
    };

    var registerListeners = function() {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Switching to another app often hides the tab a moment after the window lost focus: that is the
                // same departure, already being recorded as a window switch, so it does not count a second time.
                if (!focusSuspended() && !blurAt) {
                    hiddenAt = Date.now();
                    report('tab_hidden');
                }
            } else if (hiddenAt) {
                sendEvent('tab_returned', awayDetails(hiddenAt));
                hiddenAt = 0;
            }
        });

        window.addEventListener('blur', function() {
            // Focus moving into an iframe (e.g. the essay editor) keeps document.hasFocus() true.
            window.setTimeout(function() {
                if (!document.hidden && !document.hasFocus() && !focusSuspended() && !blurAt) {
                    blurAt = Date.now();
                    blurConfirmed = false;
                    // Counted only if the learner stays away: a moment's switch (often the browser's own UI) is
                    // flagged for review when they come back instead.
                    window.clearTimeout(blurTimer);
                    blurTimer = window.setTimeout(function() {
                        if (blurAt && !document.hasFocus()) {
                            blurConfirmed = true;
                            sendEvent('window_blur', '');
                        }
                    }, BRIEF_MS);
                }
            }, 300);
        });
        window.addEventListener('focus', function() {
            if (blurAt) {
                window.clearTimeout(blurTimer);
                sendEvent(blurConfirmed ? 'window_returned' : 'window_brief', awayDetails(blurAt));
                blurAt = 0;
                blurConfirmed = false;
            }
        });

        if (cfg.requirefullscreen) {
            if (!document.documentElement.requestFullscreen) {
                // For example iOS Safari: the requirement cannot be enforced; say so in the report.
                if (!store.get('fsunsupported')) {
                    store.set('fsunsupported', '1');
                    sendEvent('fullscreen_unsupported', (navigator.userAgent || '').substring(0, 200));
                }
                if (cfg.fullscreenstrict) {
                    blockers.fullscreen = true;
                    fullscreenRefused = true;
                }
            } else {
                fullscreenWaived = store.get('fswaived') === '1';
                blockers.fullscreen = !document.fullscreenElement && !fullscreenWaived;
                document.addEventListener('fullscreenchange', function() {
                    if (document.fullscreenElement) {
                        blockers.fullscreen = false;
                        fullscreenRefused = false;
                    } else if (!fullscreenWaived) {
                        blockers.fullscreen = true;
                        if (!focusSuspended() && !submitting) {
                            report('fullscreen_exit');
                        }
                    }
                    refreshOverlay();
                });
            }
        }

        if (cfg.blockclipboard) {
            ['copy', 'cut'].forEach(function(name) {
                document.addEventListener(name, function(e) {
                    e.preventDefault();
                    report('copy', name);
                });
            });
            document.addEventListener('paste', function(e) {
                e.preventDefault();
                report('paste');
            });
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                report('contextmenu');
            });
        }
    };

    // -------------------------------------------------------------------
    // Extension API.
    // -------------------------------------------------------------------

    var api = {
        /** @return {Object} the page configuration */
        getConfig: function() {
            return cfg;
        },
        store: store,
        sendEvent: sendEvent,
        report: report,
        showBanner: showBanner,
        captureFrame: captureFrame,
        /** @return {HTMLVideoElement|null} the webcam video element (null when the camera is not used) */
        getVideo: function() {
            return cfg.requirecamera ? video : null;
        },
        /** @return {Boolean} whether the webcam is running */
        isCameraActive: function() {
            return cameraActive;
        },
        /**
         * Add warning and overlay texts: keys warn_<type> (banner) and ovl_<type> (blocking screen).
         *
         * @param {Object} more
         */
        addStrings: function(more) {
            Object.keys(more || {}).forEach(function(key) {
                strings[key] = more[key];
            });
        },
        /**
         * Register a camera analyser. evaluate(ctx) gets {video, sample, now, hidden} every ~0.5 s and returns
         * null / {type: 'ok'} or {type, details, soft, grace}. soft problems are recorded without blocking.
         *
         * @param {Object} analyser {name, evaluate}
         */
        registerAnalyser: function(analyser) {
            analysers.push(analyser);
            updateBadge();
        },
        /**
         * Hide the quiz behind a screen until clearBlocker(name) is called.
         *
         * @param {String} name
         * @param {String} text
         * @param {String|null} button label, or null for no button
         * @param {Function|null} onClick
         */
        setBlocker: function(name, text, button, onClick) {
            extraBlockers = extraBlockers.filter(function(b) {
                return b.name !== name;
            });
            extraBlockers.push({name: name, text: text, button: button, onClick: onClick});
            refreshOverlay();
        },
        clearBlocker: function(name) {
            extraBlockers = extraBlockers.filter(function(b) {
                return b.name !== name;
            });
            refreshOverlay();
        },
        /**
         * Ignore focus and fullscreen changes for a while (for example while a browser permission dialog is open).
         *
         * @param {Number} ms 0 to stop ignoring, after a short tail while focus returns from the closing dialog
         */
        suspendFocus: function(ms) {
            focusSuspendedUntil = Date.now() + (ms || RESUME_TAIL_MS);
        }
    };

    var loadExtensions = function() {
        (cfg.extensions || []).forEach(function(ext) {
            require([ext.module], function(mod) {
                try {
                    mod.init(api, ext.config || {});
                } catch (e) {
                    sendEvent('extension_error', ext.module + ': ' + (e && e.message ? e.message : e));
                }
            }, function() {
                sendEvent('extension_error', ext.module + ': could not load');
            });
        });
    };

    return {
        /**
         * Entry point called from PHP.
         *
         * @param {Object} [config] settings; by default read from the page data written by rule.php
         */
        init: function(config) {
            if (started) {
                return;
            }
            cfg = config || window.quizaccess_examproctor_config;
            if (!cfg) {
                return;
            }
            started = true;
            strings = cfg.strings || {};

            buildOverlay();
            buildBadge();
            registerListeners();
            if (!store.get('started')) {
                store.set('started', '1');
                sendEvent('session_start', (navigator.userAgent || '').substring(0, 200));
            }

            if (cfg.requirecamera) {
                buildPreview();
                blockers.camera = true;
                cameraGraceUntil = Date.now() + CAMERA_GRACE_MS;
                window.setTimeout(refreshOverlay, CAMERA_GRACE_MS + 50);
                startCamera();
                window.setInterval(takeSnapshot, cfg.snapshotinterval * 1000);
                scheduleTick(TICK_MS);
            }
            window.setInterval(function() {
                sendEvent('heartbeat', '');
            }, HEARTBEAT_MS);
            loadExtensions();
            refreshOverlay();
        }
    };
});
