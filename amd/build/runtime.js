/**
 * Webcam Proctoring runtime module for capturing snapshots during quiz.
 *
 * @module     quizaccess_webcamproctor/runtime
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    let stream = null;
    let snapshotInterval = null;
    let config = {};
    let snapshotCount = 0;

    /**
     * Initialize runtime proctoring.
     *
     * @param {Object} options Configuration options.
     */
    const init = function(options) {
        config = {
            attemptid: options.attemptid,
            quizid: options.quizid,
            interval: (options.snapshotinterval || 60) * 1000,
            randomOffset: 30000, // +/-30 seconds random variation
            maxCaptures: options.maxcaptures || 0, // 0 = unlimited
            imageQuality: 0.6,
            maxWidth: 240,
            maxHeight: 180
        };

        // Create hidden video element for background capture.
        createHiddenCapture();

        // Show proctoring active indicator.
        showProctorIndicator();
    };

    /**
     * Create hidden video element for background snapshots.
     */
    const createHiddenCapture = async function() {
        try {
            // Clean up any existing capture elements before creating new ones.
            if (stream) {
                stream.getTracks().forEach(function(track) { track.stop(); });
                stream = null;
            }
            var oldVideo = document.getElementById('webcamproctor-runtime-video');
            if (oldVideo) {
                oldVideo.remove();
            }
            var oldCanvas = document.getElementById('webcamproctor-runtime-canvas');
            if (oldCanvas) {
                oldCanvas.remove();
            }

            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 320 },
                    height: { ideal: 240 },
                    facingMode: 'user'
                },
                audio: false
            });

            var video = document.createElement('video');
            video.id = 'webcamproctor-runtime-video';
            video.srcObject = stream;
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            video.style.position = 'absolute';
            video.style.left = '-9999px';
            video.style.width = '1px';
            video.style.height = '1px';
            document.body.appendChild(video);

            var canvas = document.createElement('canvas');
            canvas.id = 'webcamproctor-runtime-canvas';
            canvas.style.display = 'none';
            document.body.appendChild(canvas);

            // Reset retry counter on successful camera acquisition.
            cameraRetryCount = 0;

            // Start periodic snapshots.
            startSnapshots(video, canvas);

        } catch (error) {
            console.error('Proctoring webcam error:', error);
            updateIndicator('error', 'Camera access lost');
            retryCameraAccess();
        }
    };

    /**
     * Get randomized interval with +/-30 second offset.
     *
     * @return {number} Randomized interval in milliseconds.
     */
    const getRandomizedInterval = function() {
        const offset = (Math.random() * 2 - 1) * config.randomOffset;
        return Math.max(10000, config.interval + offset); // Minimum 10 seconds
    };

    /**
     * Schedule next snapshot with random interval.
     *
     * @param {HTMLVideoElement} video The video element.
     * @param {HTMLCanvasElement} canvas The canvas element.
     */
    const scheduleNextSnapshot = function(video, canvas) {
        // Check if max captures reached.
        if (config.maxCaptures > 0 && snapshotCount >= config.maxCaptures) {
            updateIndicator('active', 'Proctoring complete (' + snapshotCount + ' snapshots)');
            return;
        }

        const nextInterval = getRandomizedInterval();
        snapshotInterval = setTimeout(function() {
            captureSnapshot(video, canvas);
            scheduleNextSnapshot(video, canvas);
        }, nextInterval);
    };

    /**
     * Start periodic snapshot capture.
     *
     * @param {HTMLVideoElement} video The video element.
     * @param {HTMLCanvasElement} canvas The canvas element.
     */
    const startSnapshots = function(video, canvas) {
        // Capture first snapshot after short delay.
        setTimeout(function() {
            captureSnapshot(video, canvas);
            scheduleNextSnapshot(video, canvas);
        }, 5000);
    };

    /**
     * Capture and upload a snapshot.
     *
     * @param {HTMLVideoElement} video The video element.
     * @param {HTMLCanvasElement} canvas The canvas element.
     */
    const captureSnapshot = function(video, canvas) {
        if (!video || video.paused || video.ended) {
            return;
        }

        const ctx = canvas.getContext('2d');
        canvas.width = config.maxWidth;
        canvas.height = config.maxHeight;
        ctx.drawImage(video, 0, 0, config.maxWidth, config.maxHeight);

        // Convert to WebP with compression.
        let imageData;
        try {
            imageData = canvas.toDataURL('image/webp', config.imageQuality);
        } catch (e) {
            imageData = canvas.toDataURL('image/jpeg', config.imageQuality);
        }

        // Calculate size.
        const sizeBytes = Math.round(imageData.length * 3 / 4);

        // Upload to server.
        uploadSnapshot(imageData, sizeBytes);
    };

    /**
     * Upload snapshot to server.
     *
     * @param {string} imageData Base64 image data.
     * @param {number} sizeBytes Image size in bytes.
     */
    const uploadSnapshot = function(imageData, sizeBytes) {
        Ajax.call([{
            methodname: 'quizaccess_webcamproctor_save_snapshot',
            args: {
                attemptid: config.attemptid,
                imagedata: imageData,
                imagesize: sizeBytes
            }
        }])[0].done(function(response) {
            if (response.success) {
                snapshotCount++;
                updateIndicator('active', 'Proctoring active (' + snapshotCount + ' snapshots)');

                if (response.flagged) {
                    updateIndicator('flagged', 'Identity verification issue detected');
                }
            }
        }).fail(function(error) {
            console.error('Snapshot upload error:', error);
            notifyServerCameraIssue('upload_failed');
        });
    };

    /**
     * Show proctoring indicator in corner.
     */
    const showProctorIndicator = function() {
        const indicator = document.createElement('div');
        indicator.id = 'webcamproctor-indicator';
        indicator.innerHTML = `
            <div class="proctor-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
            </div>
            <span class="proctor-text">Proctoring active</span>
        `;
        indicator.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 10000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        `;
        document.body.appendChild(indicator);
    };

    /**
     * Update proctoring indicator.
     *
     * @param {string} status The status (active, flagged, error).
     * @param {string} message The message to display.
     */
    const updateIndicator = function(status, message) {
        const indicator = document.getElementById('webcamproctor-indicator');
        if (!indicator) {
            return;
        }

        const text = indicator.querySelector('.proctor-text');
        if (text) {
            text.textContent = message;
        }

        // Update color based on status.
        switch (status) {
            case 'active':
                indicator.style.background = 'rgba(40, 167, 69, 0.9)';
                break;
            case 'flagged':
                indicator.style.background = 'rgba(255, 193, 7, 0.9)';
                break;
            case 'error':
                indicator.style.background = 'rgba(220, 53, 69, 0.9)';
                break;
        }
    };

    let cameraRetryCount = 0;
    const MAX_CAMERA_RETRIES = 3;

    const retryCameraAccess = function() {
        if (cameraRetryCount >= MAX_CAMERA_RETRIES) {
            updateIndicator('error', 'Camera lost  -  proctoring interrupted');
            notifyServerCameraIssue('camera_lost');
            return;
        }
        cameraRetryCount++;
        setTimeout(function() {
            updateIndicator('flagged', 'Reconnecting camera (attempt ' + cameraRetryCount + '/' + MAX_CAMERA_RETRIES + ')...');
            createHiddenCapture();
        }, 5000 * cameraRetryCount);
    };

    const notifyServerCameraIssue = function(reason) {
        Ajax.call([{
            methodname: 'quizaccess_webcamproctor_save_snapshot',
            args: {
                attemptid: config.attemptid,
                imagedata: '',
                imagesize: 0
            }
        }])[0].done(function() {}).fail(function() {});
    };

    /**
     * Clean up resources.
     */
    const cleanup = function() {
        if (snapshotInterval) {
            clearTimeout(snapshotInterval);
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    };

    // Clean up when page unloads.
    window.addEventListener('beforeunload', cleanup);

    return {
        init: init
    };
});
