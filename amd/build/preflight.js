/**
 * Webcam Proctoring preflight check module with privacy consent.
 *
 * @module     quizaccess_webcamproctor/preflight
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {

    let video = null;
    let canvas = null;
    let stream = null;
    let faceDetectionInterval = null;
    let captureButton = null;
    let statusDiv = null;
    let previewContainer = null;
    let baselineInput = null;
    let consentInput = null;
    let config = {};
    let consentModal = null;

    /**
     * Initialize the preflight webcam capture.
     *
     * @param {Object} options Configuration options.
     */
    const init = function(options) {
        config = {
            quizid: options.quizid || 0,
            attemptid: options.attemptid || null,
            snapshotinterval: options.snapshotinterval || 60,
            imageQuality: 0.7,
            maxWidth: 320,
            maxHeight: 240,
            quizname: options.quizname || 'this quiz',
            sitename: options.sitename || 'this site'
        };

        video = document.getElementById('webcamproctor-video');
        canvas = document.getElementById('webcamproctor-canvas');
        captureButton = document.getElementById('webcamproctor-capture');
        statusDiv = document.getElementById('webcamproctor-status');
        previewContainer = document.querySelector('.webcamproctor-preview-container');
        baselineInput = document.getElementById('webcamproctor-baseline');
        consentInput = document.getElementById('webcamproctor-consent');

        if (!video || !canvas || !captureButton) {
            console.error('Webcam proctoring elements not found');
            return;
        }

        // Hide webcam UI initially
        hideWebcamUI();
        
        // Show privacy consent popup first
        showPrivacyConsentPopup();
    };

    /**
     * Hide webcam UI until consent is given.
     */
    const hideWebcamUI = function() {
        const webcamSection = document.querySelector('.webcamproctor-section');
        if (webcamSection) {
            webcamSection.style.opacity = '0.3';
            webcamSection.style.pointerEvents = 'none';
        }
    };

    /**
     * Show webcam UI after consent.
     */
    const showWebcamUI = function() {
        const webcamSection = document.querySelector('.webcamproctor-section');
        if (webcamSection) {
            webcamSection.style.opacity = '1';
            webcamSection.style.pointerEvents = 'auto';
        }
    };

    /**
     * Create and show the privacy consent popup.
     */
    const showPrivacyConsentPopup = function() {
        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.id = 'webcamproctor-consent-backdrop';
        backdrop.innerHTML = `
            <style>
                #webcamproctor-consent-backdrop {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    backdrop-filter: blur(8px);
                    -webkit-backdrop-filter: blur(8px);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    box-sizing: border-box;
                    animation: fadeIn 0.3s ease-out;
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes slideUp {
                    from { 
                        opacity: 0;
                        transform: translateY(30px) scale(0.95);
                    }
                    to { 
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }
                
                .consent-modal {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.95) 100%);
                    border-radius: 24px;
                    box-shadow: 
                        0 25px 50px -12px rgba(0, 0, 0, 0.25),
                        0 0 0 1px rgba(255, 255, 255, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.8);
                    max-width: 520px;
                    width: 100%;
                    overflow: hidden;
                    animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                }
                
                .consent-header {
                    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                    padding: 28px 32px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .consent-header::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                    animation: shimmer 3s ease-in-out infinite;
                }
                
                @keyframes shimmer {
                    0%, 100% { transform: translateX(-30%) translateY(-30%); }
                    50% { transform: translateX(30%) translateY(30%); }
                }
                
                .consent-icon {
                    width: 64px;
                    height: 64px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 16px;
                    backdrop-filter: blur(10px);
                    border: 2px solid rgba(255, 255, 255, 0.3);
                    position: relative;
                    z-index: 1;
                }
                
                .consent-icon svg {
                    width: 32px;
                    height: 32px;
                    color: white;
                }
                
                .consent-header h2 {
                    color: white;
                    font-size: 22px;
                    font-weight: 700;
                    margin: 0 0 8px;
                    letter-spacing: -0.02em;
                    position: relative;
                    z-index: 1;
                }
                
                .consent-header p {
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 14px;
                    margin: 0;
                    font-weight: 500;
                    position: relative;
                    z-index: 1;
                }
                
                .consent-body {
                    padding: 28px 32px;
                }
                
                .consent-intro {
                    font-size: 15px;
                    color: #374151;
                    line-height: 1.6;
                    margin-bottom: 24px;
                    text-align: center;
                }
                
                .consent-features {
                    display: grid;
                    gap: 12px;
                    margin-bottom: 24px;
                }
                
                .consent-feature {
                    display: flex;
                    align-items: flex-start;
                    gap: 14px;
                    padding: 14px 16px;
                    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                    border-radius: 12px;
                    border: 1px solid rgba(226, 232, 240, 0.8);
                    transition: all 0.2s ease;
                }
                
                .consent-feature:hover {
                    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
                    transform: translateX(4px);
                }
                
                .consent-feature-icon {
                    width: 36px;
                    height: 36px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                
                .consent-feature-icon.blue {
                    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                    color: #2563eb;
                }
                
                .consent-feature-icon.green {
                    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                    color: #16a34a;
                }
                
                .consent-feature-icon.purple {
                    background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
                    color: #9333ea;
                }
                
                .consent-feature-icon.amber {
                    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                    color: #d97706;
                }
                
                .consent-feature-icon svg {
                    width: 18px;
                    height: 18px;
                }
                
                .consent-feature-text h4 {
                    font-size: 14px;
                    font-weight: 600;
                    color: #1f2937;
                    margin: 0 0 4px;
                }
                
                .consent-feature-text p {
                    font-size: 13px;
                    color: #6b7280;
                    margin: 0;
                    line-height: 1.5;
                }
                
                .consent-privacy {
                    background: linear-gradient(135deg, #fef3c7 0%, #fef9c3 100%);
                    border: 1px solid #fcd34d;
                    border-radius: 12px;
                    padding: 16px;
                    margin-bottom: 24px;
                }
                
                .consent-privacy h4 {
                    font-size: 14px;
                    font-weight: 600;
                    color: #92400e;
                    margin: 0 0 8px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .consent-privacy h4 svg {
                    width: 16px;
                    height: 16px;
                }
                
                .consent-privacy ul {
                    margin: 0;
                    padding: 0 0 0 20px;
                    font-size: 13px;
                    color: #78350f;
                    line-height: 1.6;
                }
                
                .consent-privacy li {
                    margin-bottom: 4px;
                }
                
                .consent-checkbox-wrapper {
                    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                    border: 2px solid #3b82f6;
                    border-radius: 12px;
                    padding: 16px;
                    margin-bottom: 20px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .consent-checkbox-wrapper:hover {
                    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                }
                
                .consent-checkbox-wrapper.checked {
                    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                    border-color: #22c55e;
                }
                
                .consent-checkbox-label {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    cursor: pointer;
                }
                
                .consent-checkbox {
                    width: 22px;
                    height: 22px;
                    border: 2px solid #3b82f6;
                    border-radius: 6px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    background: white;
                    transition: all 0.2s ease;
                    margin-top: 2px;
                }
                
                .consent-checkbox-wrapper.checked .consent-checkbox {
                    background: #22c55e;
                    border-color: #22c55e;
                }
                
                .consent-checkbox svg {
                    width: 14px;
                    height: 14px;
                    color: white;
                    opacity: 0;
                    transform: scale(0.5);
                    transition: all 0.2s ease;
                }
                
                .consent-checkbox-wrapper.checked .consent-checkbox svg {
                    opacity: 1;
                    transform: scale(1);
                }
                
                .consent-checkbox-text {
                    font-size: 14px;
                    color: #1f2937;
                    line-height: 1.5;
                    font-weight: 500;
                }
                
                .consent-actions {
                    display: flex;
                    gap: 12px;
                }
                
                .consent-btn {
                    flex: 1;
                    padding: 14px 24px;
                    border-radius: 12px;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    border: none;
                    font-family: inherit;
                }
                
                .consent-btn-cancel {
                    background: #f1f5f9;
                    color: #64748b;
                    border: 1px solid #e2e8f0;
                }
                
                .consent-btn-cancel:hover {
                    background: #e2e8f0;
                    color: #475569;
                }
                
                .consent-btn-continue {
                    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                    color: white;
                    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
                }
                
                .consent-btn-continue:hover:not(:disabled) {
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
                }
                
                .consent-btn-continue:disabled {
                    background: #cbd5e1;
                    box-shadow: none;
                    cursor: not-allowed;
                    color: #94a3b8;
                }
                
                .face-guide {
                    text-align: center;
                    padding: 20px;
                    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                    border-radius: 12px;
                    margin-bottom: 20px;
                    border: 1px solid #86efac;
                }
                
                .face-guide-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 12px;
                    border: 3px dashed #22c55e;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(255, 255, 255, 0.8);
                }
                
                .face-guide-icon svg {
                    width: 40px;
                    height: 40px;
                    color: #22c55e;
                }
                
                .face-guide h4 {
                    font-size: 15px;
                    font-weight: 600;
                    color: #166534;
                    margin: 0 0 6px;
                }
                
                .face-guide p {
                    font-size: 13px;
                    color: #15803d;
                    margin: 0;
                }
                
                @media (max-width: 480px) {
                    .consent-modal {
                        border-radius: 20px;
                    }
                    
                    .consent-header {
                        padding: 24px 20px;
                    }
                    
                    .consent-body {
                        padding: 20px;
                    }
                    
                    .consent-actions {
                        flex-direction: column-reverse;
                    }
                }
            </style>
            
            <div class="consent-modal">
                <div class="consent-header">
                    <div class="consent-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2>Webcam Proctoring Required</h2>
                    <p>Identity verification for secure assessment</p>
                </div>
                
                <div class="consent-body">
                    <p class="consent-intro">
                        This quiz uses webcam proctoring to verify your identity and ensure assessment integrity. 
                        Please review the information below before proceeding.
                    </p>
                    
                    <div class="face-guide">
                        <div class="face-guide-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                            </svg>
                        </div>
                        <h4>Centre Your Face in the Frame</h4>
                        <p>Position your face clearly in the webcam view. Good lighting helps!</p>
                    </div>
                    
                    <div class="consent-features">
                        <div class="consent-feature">
                            <div class="consent-feature-icon blue">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="consent-feature-text">
                                <h4>Baseline Photo Capture</h4>
                                <p>A photo will be taken when you start to verify your identity throughout the quiz.</p>
                            </div>
                        </div>
                        
                        <div class="consent-feature">
                            <div class="consent-feature-icon green">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="consent-feature-text">
                                <h4>Periodic Snapshots</h4>
                                <p>Images are captured every ${config.snapshotinterval} seconds during the quiz attempt.</p>
                            </div>
                        </div>
                        
                        <div class="consent-feature">
                            <div class="consent-feature-icon purple">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div class="consent-feature-text">
                                <h4>AI Face Verification</h4>
                                <p>Snapshots are compared to your baseline photo to verify it's you taking the quiz.</p>
                            </div>
                        </div>
                        
                        <div class="consent-feature">
                            <div class="consent-feature-icon amber">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>
                            <div class="consent-feature-text">
                                <h4>Teacher Notifications</h4>
                                <p>Your teacher may be notified if verification issues are detected during your attempt.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="consent-privacy">
                        <h4>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Your Privacy & Data Protection
                        </h4>
                        <ul>
                            <li>Images are stored securely and only accessible to authorized staff</li>
                            <li>Data is used solely for identity verification purposes</li>
                            <li>Images are automatically deleted after the retention period</li>
                            <li>You can request deletion of your data under GDPR/privacy laws</li>
                            <li>No data is shared with third parties</li>
                        </ul>
                    </div>
                    
                    <div class="consent-checkbox-wrapper" id="consent-wrapper">
                        <label class="consent-checkbox-label">
                            <div class="consent-checkbox">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <span class="consent-checkbox-text">
                                I understand and consent to webcam monitoring during this quiz. 
                                I acknowledge that my images will be captured and used for identity verification.
                            </span>
                        </label>
                    </div>
                    
                    <div class="consent-actions">
                        <button type="button" class="consent-btn consent-btn-cancel" id="consent-cancel">
                            Cancel
                        </button>
                        <button type="button" class="consent-btn consent-btn-continue" id="consent-continue" disabled>
                            Continue to Webcam Setup
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(backdrop);
        consentModal = backdrop;

        // Setup event listeners
        const wrapper = document.getElementById('consent-wrapper');
        const continueBtn = document.getElementById('consent-continue');
        const cancelBtn = document.getElementById('consent-cancel');
        let isChecked = false;

        wrapper.addEventListener('click', function() {
            isChecked = !isChecked;
            wrapper.classList.toggle('checked', isChecked);
            continueBtn.disabled = !isChecked;
        });

        continueBtn.addEventListener('click', function() {
            if (isChecked) {
                // Set consent value
                if (consentInput) {
                    consentInput.value = '1';
                }
                
                // Update checkbox if exists
                const consentCheckbox = document.querySelector('[name="webcamproctor_consent_check"]');
                if (consentCheckbox) {
                    consentCheckbox.checked = true;
                }

                // Close modal with animation
                backdrop.style.animation = 'fadeIn 0.2s ease-out reverse';
                setTimeout(function() {
                    backdrop.remove();
                    showWebcamUI();
                    initWebcam();
                    initEventListeners();
                }, 200);
            }
        });

        cancelBtn.addEventListener('click', function() {
            // Go back to previous page
            window.history.back();
        });
    };

    /**
     * Initialize webcam stream.
     */
    const initWebcam = async function() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            });

            video.srcObject = stream;
            video.play();

            setStatus('success', 'Camera connected. Position your face in the frame.');

            // Start face detection.
            startFaceDetection();

        } catch (error) {
            console.error('Webcam error:', error);
            if (error.name === 'NotAllowedError') {
                setStatus('error', 'Camera access denied. Please allow camera access in your browser settings and refresh the page.');
            } else if (error.name === 'NotFoundError') {
                setStatus('error', 'No camera found. Please connect a webcam and refresh the page.');
            } else {
                setStatus('error', 'Camera error: ' + error.message);
            }
        }
    };

    /**
     * Start face detection loop.
     */
    const startFaceDetection = function() {
        faceDetectionInterval = setInterval(function() {
            if (!video || video.paused || video.ended) {
                return;
            }

            const ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth || 320;
            canvas.height = video.videoHeight || 240;
            ctx.drawImage(video, 0, 0);

            // Analyze center region for face-like features.
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const regionSize = 100;

            try {
                const imageData = ctx.getImageData(
                    centerX - regionSize / 2,
                    centerY - regionSize / 2,
                    regionSize,
                    regionSize
                );

                let skinPixels = 0;
                let totalPixels = 0;

                for (let i = 0; i < imageData.data.length; i += 4) {
                    const r = imageData.data[i];
                    const g = imageData.data[i + 1];
                    const b = imageData.data[i + 2];

                    const ycbcr_y  = 0.299 * r + 0.587 * g + 0.114 * b;
                    const ycbcr_cb = 128 - 0.169 * r - 0.331 * g + 0.500 * b;
                    const ycbcr_cr = 128 + 0.500 * r - 0.419 * g - 0.081 * b;

                    const isSkinYCbCr = ycbcr_y > 40 &&
                        ycbcr_cb >= 77 && ycbcr_cb <= 127 &&
                        ycbcr_cr >= 133 && ycbcr_cr <= 173;

                    const isSkinRGB = r > 45 && g > 30 && b > 15 &&
                        r > g && (r - g) > 10 && r > b;

                    if (isSkinYCbCr || isSkinRGB) {
                        skinPixels++;
                    }
                    totalPixels++;
                }

                const skinRatio = skinPixels / totalPixels;

                // Also check for variance (face has more detail than plain background).
                let variance = 0;
                let mean = 0;
                for (let i = 0; i < imageData.data.length; i += 4) {
                    mean += imageData.data[i];
                }
                mean /= (imageData.data.length / 4);

                for (let i = 0; i < imageData.data.length; i += 4) {
                    variance += Math.pow(imageData.data[i] - mean, 2);
                }
                variance /= (imageData.data.length / 4);

                // Face detected if enough skin pixels and variance (texture).
                const faceDetected = skinRatio > 0.15 && variance > 200;

                updateFaceIndicator(faceDetected);

            } catch (e) {
                // Canvas security error or other issue.
                updateFaceIndicator(false);
            }

        }, 200);
    };

    /**
     * Update UI based on face detection.
     *
     * @param {boolean} detected Whether a face was detected.
     */
    const updateFaceIndicator = function(detected) {
        if (detected) {
            previewContainer.classList.add('face-detected');
            previewContainer.classList.remove('no-face');
            captureButton.disabled = false;
            setStatus('success', 'Face detected - you can capture your photo now');
        } else {
            previewContainer.classList.remove('face-detected');
            previewContainer.classList.add('no-face');
            captureButton.disabled = true;
            setStatus('warning', 'Position your face in the center of the frame');
        }
    };

    /**
     * Initialize event listeners.
     */
    const initEventListeners = function() {
        captureButton.addEventListener('click', captureBaseline);

        // Update consent hidden field when checkbox changes.
        const consentCheckbox = document.querySelector('[name="webcamproctor_consent_check"]');
        if (consentCheckbox) {
            consentCheckbox.addEventListener('change', function() {
                consentInput.value = this.checked ? '1' : '0';
            });
        }
    };

    /**
     * Capture baseline photo with visual feedback.
     */
    const captureBaseline = function() {
        if (!video || !canvas) {
            return;
        }

        // Create flash effect
        const flash = document.createElement('div');
        flash.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            opacity: 0;
            pointer-events: none;
            z-index: 100;
            animation: captureFlash 0.4s ease-out;
        `;
        
        // Add flash animation styles if not exists
        if (!document.getElementById('webcamproctor-flash-styles')) {
            const flashStyles = document.createElement('style');
            flashStyles.id = 'webcamproctor-flash-styles';
            flashStyles.textContent = `
                @keyframes captureFlash {
                    0% { opacity: 0; }
                    20% { opacity: 0.9; }
                    100% { opacity: 0; }
                }
                @keyframes successPulse {
                    0% { transform: scale(0); opacity: 0; }
                    50% { transform: scale(1.2); opacity: 1; }
                    100% { transform: scale(1); opacity: 1; }
                }
                @keyframes checkDraw {
                    0% { stroke-dashoffset: 24; }
                    100% { stroke-dashoffset: 0; }
                }
                .webcamproctor-success-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, rgba(34, 197, 94, 0.9) 0%, rgba(22, 163, 74, 0.9) 100%);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    z-index: 101;
                    animation: successPulse 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                    border-radius: inherit;
                }
                .webcamproctor-success-icon {
                    width: 64px;
                    height: 64px;
                    background: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                    margin-bottom: 12px;
                }
                .webcamproctor-success-icon svg {
                    width: 36px;
                    height: 36px;
                    color: #22c55e;
                }
                .webcamproctor-success-icon svg path {
                    stroke-dasharray: 24;
                    stroke-dashoffset: 24;
                    animation: checkDraw 0.4s ease-out 0.2s forwards;
                }
                .webcamproctor-success-text {
                    color: white;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: 16px;
                    font-weight: 600;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                }
                .webcamproctor-captured-container {
                    position: relative;
                    width: 100%;
                    height: 100%;
                }
                .webcamproctor-captured-badge {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                    color: white;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: 12px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
                    z-index: 10;
                }
                .webcamproctor-captured-badge svg {
                    width: 14px;
                    height: 14px;
                }
            `;
            document.head.appendChild(flashStyles);
        }
        
        previewContainer.style.position = 'relative';
        previewContainer.appendChild(flash);

        // Draw current frame to canvas.
        const ctx = canvas.getContext('2d');
        canvas.width = config.maxWidth;
        canvas.height = config.maxHeight;

        // Scale down for smaller file size.
        ctx.drawImage(video, 0, 0, config.maxWidth, config.maxHeight);

        // Convert to WebP for compression.
        let imageData;
        try {
            imageData = canvas.toDataURL('image/webp', config.imageQuality);
        } catch (e) {
            // Fallback to JPEG if WebP not supported.
            imageData = canvas.toDataURL('image/jpeg', config.imageQuality);
        }

        // Store in hidden input.
        baselineInput.value = imageData;

        // Remove flash after animation
        setTimeout(function() {
            flash.remove();
            
            // Show success overlay
            const successOverlay = document.createElement('div');
            successOverlay.className = 'webcamproctor-success-overlay';
            successOverlay.id = 'webcamproctor-success';
            successOverlay.innerHTML = `
                <div class="webcamproctor-success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="webcamproctor-success-text">Photo Captured!</div>
            `;
            previewContainer.appendChild(successOverlay);
            
            // After success animation, show captured image
            setTimeout(function() {
                successOverlay.remove();
                
                // Hide video
                video.style.display = 'none';
                
                // Create container for captured image with badge
                const capturedContainer = document.createElement('div');
                capturedContainer.className = 'webcamproctor-captured-container';
                capturedContainer.id = 'webcamproctor-captured-container';
                
                // Add the captured image
                const img = document.createElement('img');
                img.src = imageData;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = 'inherit';
                img.id = 'webcamproctor-captured';
                
                // Add success badge
                const badge = document.createElement('div');
                badge.className = 'webcamproctor-captured-badge';
                badge.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Verified
                `;
                
                capturedContainer.appendChild(img);
                capturedContainer.appendChild(badge);
                previewContainer.appendChild(capturedContainer);
                
                // Update status with success styling
                setStatus('success', 'Photo captured successfully! Click "Start attempt" below to begin.');
                
            }, 1200);
            
        }, 400);

        // Update button
        captureButton.textContent = 'Retake Photo';
        captureButton.onclick = retakePhoto;

        // Stop face detection.
        if (faceDetectionInterval) {
            clearInterval(faceDetectionInterval);
        }

        // Calculate and log image size.
        const sizeKB = Math.round((imageData.length * 3 / 4) / 1024);
        console.log('Captured image size: ' + sizeKB + ' KB');
    };

    /**
     * Retake the baseline photo.
     */
    const retakePhoto = function() {
        // Remove captured image container (includes badge).
        const capturedContainer = document.getElementById('webcamproctor-captured-container');
        if (capturedContainer) {
            capturedContainer.remove();
        }
        
        // Also try legacy image element
        const img = document.getElementById('webcamproctor-captured');
        if (img) {
            img.remove();
        }
        
        // Remove any success overlay if still present
        const successOverlay = document.getElementById('webcamproctor-success');
        if (successOverlay) {
            successOverlay.remove();
        }

        // Show video again.
        video.style.display = 'block';
        baselineInput.value = '';

        // Restart face detection.
        startFaceDetection();

        // Reset button.
        captureButton.textContent = 'Capture Photo';
        captureButton.onclick = captureBaseline;
        
        // Update status
        setStatus('success', 'Camera ready. Position your face in the frame.');
    };

    /**
     * Set status message.
     *
     * @param {string} type Status type (success, error, warning).
     * @param {string} message The message to display.
     */
    const setStatus = function(type, message) {
        if (!statusDiv) {
            return;
        }
        statusDiv.className = 'webcamproctor-status ' + type;
        statusDiv.textContent = message;
    };

    /**
     * Clean up resources.
     */
    const cleanup = function() {
        if (faceDetectionInterval) {
            clearInterval(faceDetectionInterval);
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        if (consentModal) {
            consentModal.remove();
        }
    };

    // Clean up when page unloads.
    window.addEventListener('beforeunload', cleanup);

    return {
        init: init
    };
});
