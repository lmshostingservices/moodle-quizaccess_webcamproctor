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
 * Camera preview and baseline photo capture for the quiz preflight form.
 *
 * @module     quizaccess_webcamproctor/preflight
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

const PHOTO_WIDTH = 320;
const PHOTO_HEIGHT = 240;
const CHECK_INTERVAL = 500;

let strings = {};
let stream = null;
let checkTimer = null;

/**
 * Load the strings used by this module.
 *
 * @returns {Promise}
 */
const loadStrings = async() => {
    const keys = ['camera_on', 'camera_ready', 'camera_dark', 'photo_taken', 'webcam_permission_denied',
        'webcam_not_available', 'error_no_camera', 'camera_off'];
    const values = await getStrings(keys.map((key) => ({key, component: 'quizaccess_webcamproctor'})));
    keys.forEach((key, index) => {
        strings[key] = values[index];
    });
};

/**
 * Stop the camera and the frame check.
 */
const stopCamera = () => {
    if (checkTimer) {
        clearInterval(checkTimer);
        checkTimer = null;
    }
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }
};

/**
 * Whether the current video frame shows a usable picture (not a covered or dark camera).
 *
 * This only checks brightness and detail. It does not try to detect faces or skin, so it treats
 * everyone the same; identity is checked later by the face-matching service and by people.
 *
 * @param {HTMLVideoElement} video The video element.
 * @param {HTMLCanvasElement} canvas A working canvas.
 * @returns {boolean}
 */
const frameIsUsable = (video, canvas) => {
    if (!video.videoWidth) {
        return false;
    }
    canvas.width = 64;
    canvas.height = 48;
    const context = canvas.getContext('2d', {willReadFrequently: true});
    context.drawImage(video, 0, 0, 64, 48);
    const data = context.getImageData(0, 0, 64, 48).data;
    let sum = 0;
    let sumsq = 0;
    const count = data.length / 4;
    for (let i = 0; i < data.length; i += 4) {
        const luma = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        sum += luma;
        sumsq += luma * luma;
    }
    const mean = sum / count;
    const deviation = Math.sqrt(Math.max(0, sumsq / count - mean * mean));
    return mean > 25 && deviation > 8;
};

/**
 * Set up the camera controls inside the preflight form.
 *
 * @param {Object} options Options from PHP.
 * @param {Number} options.quizid The quiz id.
 */
export const init = async(options) => {
    const root = document.querySelector('[data-region="webcamproctor-camera"]');
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    root.dataset.quizid = options.quizid;

    const form = root.closest('form');
    const input = form ? form.querySelector('input[name="webcamproctor_baseline"]') : null;
    const video = root.querySelector('[data-region="video"]');
    const photo = root.querySelector('[data-region="photo"]');
    const canvas = root.querySelector('[data-region="canvas"]');
    const status = root.querySelector('[data-region="status"]');
    const startButton = root.querySelector('[data-action="start"]');
    const captureButton = root.querySelector('[data-action="capture"]');
    const retakeButton = root.querySelector('[data-action="retake"]');
    if (!input) {
        return;
    }

    await loadStrings();

    const setStatus = (key) => {
        status.textContent = strings[key];
    };

    const showPhoto = (dataurl) => {
        photo.src = dataurl;
        photo.hidden = false;
        video.hidden = true;
        startButton.hidden = true;
        captureButton.hidden = true;
        retakeButton.hidden = false;
        setStatus('photo_taken');
    };

    const startCamera = async() => {
        stopCamera();
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {width: {ideal: 640}, height: {ideal: 480}, facingMode: 'user'},
                audio: false,
            });
        } catch (error) {
            if (error.name === 'NotAllowedError') {
                setStatus('webcam_permission_denied');
            } else if (error.name === 'NotFoundError') {
                setStatus('webcam_not_available');
            } else {
                setStatus('error_no_camera');
            }
            return;
        }
        video.srcObject = stream;
        video.hidden = false;
        photo.hidden = true;
        startButton.hidden = true;
        captureButton.hidden = false;
        retakeButton.hidden = true;
        setStatus('camera_on');
        captureButton.disabled = true;
        let checks = 0;
        let lastUsable = null;
        checkTimer = setInterval(() => {
            checks++;
            const usable = frameIsUsable(video, canvas);
            // Give the camera a moment to adjust before reporting a dark or covered picture.
            if (usable !== lastUsable && (usable || checks > 3)) {
                lastUsable = usable;
                captureButton.disabled = !usable;
                setStatus(usable ? 'camera_ready' : 'camera_dark');
            }
        }, CHECK_INTERVAL);
    };

    startButton.addEventListener('click', startCamera);
    retakeButton.addEventListener('click', () => {
        input.value = '';
        startCamera();
    });
    captureButton.addEventListener('click', () => {
        canvas.width = PHOTO_WIDTH;
        canvas.height = PHOTO_HEIGHT;
        canvas.getContext('2d').drawImage(video, 0, 0, PHOTO_WIDTH, PHOTO_HEIGHT);
        input.value = canvas.toDataURL('image/jpeg', 0.8);
        stopCamera();
        showPhoto(input.value);
    });
    window.addEventListener('pagehide', stopCamera);

    // The form is shown again after a validation error: keep the photo already taken.
    if (input.value) {
        showPhoto(input.value);
    }
};
