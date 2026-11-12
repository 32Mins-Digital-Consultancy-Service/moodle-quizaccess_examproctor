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
 * Camera check on the consent screen, before the attempt starts: the browser asks for camera permission here,
 * so it does not use exam time. The camera is released again straight away.
 *
 * @module     quizaccess_examproctor/devicecheck
 * @copyright  2026 32Mins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var run = function(button, strings) {
        var box = button.closest('.examproctor-devicecheck');
        var video = box.querySelector('video');
        var status = box.querySelector('.examproctor-devicecheck-status');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            status.textContent = strings.insecure;
            status.className = 'examproctor-devicecheck-status small mt-1 text-danger';
            return;
        }
        button.disabled = true;
        navigator.mediaDevices.getUserMedia({video: true, audio: false}).then(function(stream) {
            video.srcObject = stream;
            video.muted = true;
            video.classList.remove('d-none');
            video.play();
            status.textContent = strings.ok;
            status.className = 'examproctor-devicecheck-status small mt-1 text-success';
            window.setTimeout(function() {
                stream.getTracks().forEach(function(t) {
                    t.stop();
                });
                video.classList.add('d-none');
                button.disabled = false;
            }, 4000);
            return stream;
        }).catch(function() {
            status.textContent = strings.fail;
            status.className = 'examproctor-devicecheck-status small mt-1 text-danger';
            button.disabled = false;
        });
    };

    return {
        /**
         * Listen for clicks on the check button (the form may be shown in a modal, so listen on the document).
         *
         * @param {Object} strings
         */
        init: function(strings) {
            document.addEventListener('click', function(e) {
                var button = e.target.closest ? e.target.closest('[data-examproctor-devicecheck]') : null;
                if (button) {
                    e.preventDefault();
                    run(button, strings);
                }
            });
        }
    };
});
