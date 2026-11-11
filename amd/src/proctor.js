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
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    var SNAPSHOT_WIDTH = 320;
    var SNAPSHOT_HEIGHT = 240;
    var THROTTLE_MS = 3000;       // Same event type at most this often.
    var TICK_MS = 500;            // Camera analysis cadence.
    var REPEAT_MS = 15000;        // While a problem continues, record it again this often.
    var GRACE_MS = 3000;          // A problem must last this long before it blocks and is recorded.
    var CAMERA_GRACE_MS = 2500;   // Don't flash the camera screen while a remembered permission starts the camera.
    var SLOW_PASS_MS = 800;       // An analysis pass slower than this counts as slow...
    var SLOW_PASSES = 3;          // ...and this many in a row switch the extra analysers off.
    var DARK_LEVEL = 12;          // Mean brightness (0-255) below which the camera counts as covered.
    var FLAT_LEVEL = 8;           // Brightness spread below which the picture is a flat field (hand over the lens).
    var SAMPLE_W = 64;            // Tiny grayscale copy used for covered / frozen / motion maths.
    var SAMPLE_H = 48;
    var MAX_GAP_MS = 4000;        // Frames further apart than this are not compared.
    var NOISE_DELTA = 0.5;        // Any pixel change above this means the feed is alive.
    var FROZEN_IDENTICAL_MS = 15000;
    var FROZEN_STALL_MS = 5000;
    var WARMUP_MS = 5000;         // Ignore covered / frozen while the camera adjusts after starting.
    var HEARTBEAT_MS = 60000;     // "Still here" signal, so a quiet student never looks silent in the report.
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

    var blockers = {camera: false, fullscreen: false, problem: null};
    var extraBlockers = [];        // [{name, text, button, onClick}] in the order they were added.
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
    var blurAt = 0;

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

    var markFormSubmitted = function() {
        try {
            if (window.M && window.M.core_formchangechecker) {
                window.M.core_formchangechecker.set_form_submitted();
            }
        } catch (e) {
            // Only used to suppress the "leave page?" prompt.
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
            markFormSubmitted();
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
            markFormSubmitted();
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
    // Banner, badge and the blocking screen.
    // -------------------------------------------------------------------

    var showBanner = function(message) {
        if (!banner) {
            banner = document.createElement('div');
            banner.setAttribute('role', 'alert');
            banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:100001;padding:12px 16px;' +
                'background:#b02a37;color:#fff;text-align:center;font-weight:600;pointer-events:none;';
            document.body.appendChild(banner);
        }
        banner.textContent = message;
        banner.style.display = 'block';
        window.clearTimeout(bannerTimer);
        bannerTimer = window.setTimeout(function() {
            banner.style.display = 'none';
        }, 7000);
    };

    var updateBadge = function() {
        if (!badgeEl) {
            return;
        }
        var text = cfg.preview ? strings.active_preview : strings.active;
        if (cfg.preview || debugMode) {
            text += ' | v' + (cfg.release || '?') + ' | ' + cfg.mode +
                ' | camera: ' + (cfg.requirecamera ? (cameraActive ? 'on' : 'off') : 'not used') +
                ' | analysers: ' + (analysersOff ? 'OFF (too slow)' : analysers.map(function(a) {
                    return a.name;
                }).join(',') || 'none') +
                ' | fullscreen: ' + (cfg.requirefullscreen ? 'required' : 'no') +
                ' | issue: ' + (problem || 'none') +
                ' | pass: ' + lastTickMs + ' ms' +
                ' | screen: ' + (overlay.style.display === 'none' ? 'clear' : 'BLOCKED');
        }
        badgeEl.textContent = text;
    };

    var buildBadge = function() {
        badgeEl = document.createElement('div');
        badgeEl.setAttribute('aria-hidden', 'true');
        badgeEl.style.cssText = 'position:fixed;left:12px;bottom:12px;z-index:100000;padding:4px 10px;' +
            'background:#0f6cbf;color:#fff;font-size:12px;border-radius:12px;pointer-events:none;max-width:70vw;';
        document.body.appendChild(badgeEl);
        updateBadge();
    };

    var buildOverlay = function() {
        overlay = document.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', strings.dialoglabel || 'Exam proctoring');
        overlay.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.93);' +
            'color:#fff;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:24px;';
        overlayText = document.createElement('p');
        overlayText.id = 'examproctor-overlay-text';
        overlayText.setAttribute('aria-live', 'assertive');
        overlayText.style.cssText = 'font-size:1.4rem;max-width:560px;margin-bottom:24px;';
        overlayButton = document.createElement('button');
        overlayButton.type = 'button';
        overlayButton.className = 'btn btn-primary btn-lg';
        overlayButton.addEventListener('click', onOverlayButton);
        overlay.setAttribute('aria-describedby', overlayText.id);
        overlay.appendChild(overlayText);
        overlay.appendChild(overlayButton);
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
            showOverlay(strings.fullscreenrequired, strings.fullscreenbutton);
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
        } else if (blockers.fullscreen && document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().catch(function() {
                // Refused: the screen stays.
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
        navigator.mediaDevices.getUserMedia({
            video: {width: {ideal: 640}, height: {ideal: 480}},
            audio: false
        }).then(function(mediaStream) {
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
        }).catch(function(error) {
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
        video.style.cssText = 'position:fixed;right:12px;bottom:12px;width:160px;height:120px;z-index:100000;' +
            'border:2px solid #0f6cbf;border-radius:6px;background:#000;object-fit:cover;';
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
     * @return {Object} {fresh, changed, noisy, mean, spread}: % of pixels moved / changed at all, brightness stats
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
        var i;
        for (i = 0; i < n; i++) {
            cur[i] = 0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2];
            sum += cur[i];
        }
        var mean = sum / n;
        var sq = 0;
        for (i = 0; i < n; i++) {
            cur[i] -= mean;
            sq += cur[i] * cur[i];
        }
        var result = {fresh: false, changed: 0, noisy: 0, mean: mean, spread: Math.sqrt(sq / n)};
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
                if (!focusSuspended()) {
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
                    report('window_blur');
                }
            }, 300);
        });
        window.addEventListener('focus', function() {
            if (blurAt) {
                sendEvent('window_returned', awayDetails(blurAt));
                blurAt = 0;
            }
        });

        if (cfg.requirefullscreen) {
            if (!document.documentElement.requestFullscreen) {
                // For example iOS Safari: the requirement cannot be enforced; say so in the report.
                if (!store.get('fsunsupported')) {
                    store.set('fsunsupported', '1');
                    sendEvent('fullscreen_unsupported', (navigator.userAgent || '').substring(0, 200));
                }
            } else {
                blockers.fullscreen = !document.fullscreenElement;
                document.addEventListener('fullscreenchange', function() {
                    if (document.fullscreenElement) {
                        blockers.fullscreen = false;
                    } else {
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
         * @param {Number} ms 0 to stop ignoring
         */
        suspendFocus: function(ms) {
            focusSuspendedUntil = ms ? Date.now() + ms : 0;
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
