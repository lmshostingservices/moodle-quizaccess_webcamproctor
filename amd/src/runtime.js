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
 * Periodic webcam snapshots during a proctored quiz attempt.
 *
 * @module     quizaccess_webcamproctor/runtime
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';

const SNAPSHOT_WIDTH = 240;
const SNAPSHOT_HEIGHT = 180;
const MAX_CAMERA_RETRIES = 3;

let config = {};
let stream = null;
let video = null;
let canvas = null;
let timer = null;
let indicator = null;
let retries = 0;

/**
 * Show a status in the proctoring badge.
 *
 * @param {String} state One of active, warning or error.
 * @param {String} key Language string key.
 * @param {*} param Optional string parameter.
 */
const setIndicator = async(state, key, param) => {
    if (!indicator) {
        return;
    }
    indicator.dataset.state = state;
    indicator.querySelector('.webcamproctor-indicator-text').textContent =
        await getString(key, 'quizaccess_webcamproctor', param);
};

/**
 * Call the snapshot web service.
 *
 * @param {String} imagedata The data URI, or an empty string for an event.
 * @param {String} event An event name such as cameralost, or an empty string.
 * @returns {Promise}
 */
const send = (imagedata, event) => Ajax.call([{
    methodname: 'quizaccess_webcamproctor_save_snapshot',
    args: {
        attemptid: config.attemptid,
        imagedata: imagedata,
        imagesize: imagedata.length,
        event: event,
    },
}])[0];

/**
 * Whether the attempt has used up its snapshot allowance.
 *
 * @returns {boolean}
 */
const limitReached = () => config.maxcaptures > 0 && config.snapshotcount >= config.maxcaptures;

/**
 * Take and upload one snapshot.
 */
const capture = () => {
    if (!video || !video.videoWidth) {
        return;
    }
    if (video.paused) {
        video.play().catch(() => null);
    }
    canvas.width = SNAPSHOT_WIDTH;
    canvas.height = SNAPSHOT_HEIGHT;
    canvas.getContext('2d').drawImage(video, 0, 0, SNAPSHOT_WIDTH, SNAPSHOT_HEIGHT);
    send(canvas.toDataURL('image/jpeg', 0.7), '').then((response) => {
        if (response.success) {
            config.snapshotcount++;
            setIndicator(response.flagged ? 'warning' : 'active', 'proctoring_active_count', config.snapshotcount);
        }
        return response;
    }).catch(() => {
        setIndicator('warning', 'snapshot_failed');
    });
};

/**
 * Schedule the next snapshot at a slightly random time, so students cannot predict it.
 *
 * @param {Number} delay Milliseconds until the next snapshot.
 */
const schedule = (delay) => {
    if (limitReached()) {
        setIndicator('active', 'proctoring_complete', config.snapshotcount);
        return;
    }
    timer = setTimeout(() => {
        capture();
        const spread = Math.min(30, config.interval / 2) * 1000;
        schedule(Math.max(15000, config.interval * 1000 + (Math.random() * 2 - 1) * spread));
    }, delay);
};

/**
 * Stop the camera and any pending snapshot.
 */
const stop = () => {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }
};

/**
 * Start the camera, retrying a few times before reporting that it was lost.
 */
const startCamera = async() => {
    stop();
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {width: {ideal: 320}, height: {ideal: 240}, facingMode: 'user'},
            audio: false,
        });
    } catch (error) {
        if (retries < MAX_CAMERA_RETRIES) {
            retries++;
            setIndicator('warning', 'camera_reconnecting', {attempt: retries, max: MAX_CAMERA_RETRIES});
            setTimeout(startCamera, 5000 * retries);
        } else {
            setIndicator('error', 'camera_lost');
            send('', 'cameralost').catch(() => null);
        }
        return;
    }
    retries = 0;
    video.srcObject = stream;
    video.play().catch(() => null);
    stream.getVideoTracks().forEach((track) => track.addEventListener('ended', startCamera));
    setIndicator('active', 'proctoring_active');
    // First snapshot comes at a random point in the first interval.
    schedule((5 + Math.random() * Math.min(config.interval, 25)) * 1000);
};

/**
 * Start proctoring on an attempt page.
 *
 * @param {Object} options Options from PHP.
 * @param {Number} options.attemptid The quiz attempt id.
 * @param {Number} options.snapshotinterval Seconds between snapshots.
 * @param {Number} options.maxcaptures Maximum snapshots per attempt, 0 for unlimited.
 * @param {Number} options.snapshotcount Snapshots already taken.
 */
export const init = (options) => {
    if (indicator) {
        return;
    }
    config = {
        attemptid: options.attemptid,
        interval: options.snapshotinterval || 60,
        maxcaptures: options.maxcaptures || 0,
        snapshotcount: options.snapshotcount || 0,
    };

    indicator = document.createElement('div');
    indicator.className = 'webcamproctor-indicator';
    indicator.setAttribute('role', 'status');
    indicator.dataset.state = 'active';
    // A small self-view keeps the student informed, and browsers pause videos that are not visible.
    video = document.createElement('video');
    video.className = 'webcamproctor-selfview';
    video.autoplay = true;
    video.muted = true;
    video.playsInline = true;
    video.setAttribute('aria-hidden', 'true');
    indicator.appendChild(video);
    const text = document.createElement('span');
    text.className = 'webcamproctor-indicator-text';
    indicator.appendChild(text);
    document.body.appendChild(indicator);
    canvas = document.createElement('canvas');
    canvas.hidden = true;
    document.body.appendChild(canvas);

    window.addEventListener('pagehide', stop);
    startCamera();
};
