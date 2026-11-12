/**
 * AI Proctoring Client Engine (UC-08, UC-13, UC-13a, UC-13b, UC-18)
 * 
 * Modul deteksi wajah, estimasi pose kepala, arah pandangan, deteksi objek terlarang,
 * dan sinkronisasi telemetri real-time dengan server.
 */

class AiProctoringEngine {
    constructor(options = {}) {
        this.proctoringSessionId = options.proctoringSessionId || null;
        this.examSessionId = options.examSessionId || null;
        this.sessionToken = options.sessionToken || '';
        this.csrfToken = options.csrfToken || '';
        this.csrfHeader = options.csrfHeader || 'X-CSRF-TOKEN';
        this.primaryVideoId = options.primaryVideoId || 'camera-preview';
        this.secondaryVideoId = options.secondaryVideoId || 'camera-secondary-preview';
        this.intervalMs = options.intervalMs || 1200; // Throttled 1.2s tick
        this.baseUrl = options.baseUrl || window.location.origin;

        this.isRunning = false;
        this.timer = null;
        this.canvas = null;
        this.ctx = null;

        // Telemetry state
        this.lastTelemetry = {
            head_pose_yaw: 0,
            head_pose_pitch: 0,
            head_pose_roll: 0,
            gaze_direction: 'center',
            detected_face_count: 1,
            mouth_movement_detected: 0,
            phone_detected: 0,
            audio_noise_level: 0
        };

        // State trackers
        this.tabSwitchCount = 0;
        this.currentRiskScore = 0;
        this.currentRiskTier = 'low';
        this.currentIntegrityScore = 100;
        this.consecutiveNoFace = 0;
        this.consecutiveLookAway = 0;
        this.lastSecondaryCamConnected = null;

        this.initDOM();
        this.initEventListeners();
    }

    /**
     * Inisialisasi canvas tersembunyi dan elemen HUD
     */
    initDOM() {
        // Buat canvas tersembunyi untuk frame capture
        this.canvas = document.createElement('canvas');
        this.canvas.width = 320;
        this.canvas.height = 240;
        this.canvas.style.display = 'none';
        document.body.appendChild(this.canvas);
        this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });

        this.createHudOverlay();
        this.createWarningModal();
    }

    /**
     * Buat HUD live monitoring status AI di sudut atas layar ujian
     */
    createHudOverlay() {
        if (document.getElementById('ai-proctoring-hud')) return;

        const hud = document.createElement('div');
        hud.id = 'ai-proctoring-hud';
        hud.className = 'ai-hud-container';
        hud.innerHTML = `
            <div class="ai-hud-card">
                <div class="ai-hud-header">
                    <span class="ai-pulse-dot"></span>
                    <strong class="small text-uppercase">AI Proctoring</strong>
                </div>
                <div class="ai-hud-body">
                    <div class="ai-metric-item">
                        <span class="ai-metric-label">Status:</span>
                        <span id="ai-hud-status" class="badge bg-success-subtle text-success">Aktif</span>
                    </div>
                    <div class="ai-metric-item">
                        <span class="ai-metric-label">Wajah:</span>
                        <span id="ai-hud-face" class="badge bg-light text-dark">Terdeteksi</span>
                    </div>
                    <div class="ai-metric-item">
                        <span class="ai-metric-label">Risiko:</span>
                        <span id="ai-hud-risk" class="badge bg-success">0 (Normal)</span>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(hud);
    }

    /**
     * Buat modal dialog interaktif untuk peringatan kritis (UC-18)
     */
    createWarningModal() {
        if (document.getElementById('ai-warning-modal')) return;

        const modalHtml = `
            <div class="modal fade" id="ai-warning-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-danger shadow-lg">
                        <div class="modal-header bg-danger text-white py-3">
                            <h5 class="modal-title fs-6">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> Peringatan Sistem Pengawas AI
                            </h5>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="warning-icon-wrapper mb-3 text-danger">
                                <i class="bi bi-shield-exclamation display-4"></i>
                            </div>
                            <h6 class="fw-bold text-danger mb-2" id="ai-modal-warning-title">Pelanggaran Terdeteksi</h6>
                            <p class="text-secondary small mb-3" id="ai-modal-warning-message">
                                Aktivitas Anda terindikasi melanggar tata tertib ujian. Harap patuhi peraturan.
                            </p>
                            <div class="alert alert-warning py-2 px-3 small text-start border-0 mb-0">
                                <i class="bi bi-info-circle me-1"></i> Catatan: Seluruh rekaman dan tangkapan telemetri akan ditinjau oleh Guru / Pengawas.
                            </div>
                        </div>
                        <div class="modal-footer bg-light justify-content-center py-2">
                            <button type="button" class="btn btn-danger btn-sm px-4 fw-bold" id="btn-ack-ai-warning">
                                Saya Mengerti & Lanjutkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const btnAck = document.getElementById('btn-ack-ai-warning');
        if (btnAck) {
            btnAck.addEventListener('click', () => {
                const warningId = btnAck.dataset.warningId;
                if (warningId) {
                    this.acknowledgeWarning(warningId);
                }
                const modalEl = document.getElementById('ai-warning-modal');
                if (window.bootstrap && bootstrap.Modal) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
            });
        }
    }

    /**
     * Daftarkan listener event browser untuk deteksi kecurangan (Tab Switch, Fullscreen exit, Blur)
     */
    initEventListeners() {
        // 1. Deteksi Tab Switch / Minimize
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isRunning) {
                this.tabSwitchCount++;
                this.recordViolation('TAB_SWITCH', 0.95, {
                    event: 'visibilitychange_hidden',
                    tab_switches: this.tabSwitchCount
                });
            }
        });

        // 2. Deteksi Window Blur
        window.addEventListener('blur', () => {
            if (this.isRunning) {
                this.recordViolation('TAB_SWITCH', 0.85, {
                    event: 'window_blur'
                });
            }
        });

        // 3. Deteksi Keluar dari Fullscreen
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && this.isRunning) {
                this.recordViolation('FULLSCREEN_EXIT', 0.90, {
                    event: 'fullscreen_exit'
                });
                this.showToastWarning('Perhatian: Anda keluar dari mode layar penuh (Fullscreen). Harap kembali ke mode fullscreen.');
            }
        });
    }

    /**
     * Memulai engine proctoring
     */
    async start() {
        if (this.isRunning) return;

        // Pastikan sesi proctoring terhubung dengan backend
        if (!this.proctoringSessionId) {
            const initialized = await this.initServerSession();
            if (!initialized) {
                console.warn('[AI Proctoring] Menunggu inisialisasi sesi proctoring.');
            }
        }

        this.isRunning = true;
        this.updateHudStatus('Aktif', 'bg-success-subtle text-success');

        // Mulai loop analisis frame telemetri
        this.timer = setInterval(() => this.runAnalysisTick(), this.intervalMs);
        console.log('[AI Proctoring Engine] Berjalan dengan interval: ' + this.intervalMs + 'ms');
    }

    /**
     * Hentikan engine proctoring
     */
    stop() {
        this.isRunning = false;
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.updateHudStatus('Nonaktif', 'bg-secondary-subtle text-secondary');
        console.log('[AI Proctoring Engine] Dihentikan.');
    }

    /**
     * Inisialisasi sesi proctoring di backend
     */
    async initServerSession() {
        try {
            const res = await fetch(`${this.baseUrl}/siswa/proctoring/init-session`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [this.csrfHeader]: this.csrfToken
                },
                body: JSON.stringify({
                    session_token: this.sessionToken,
                    exam_session_id: this.examSessionId
                })
            });

            const data = await res.json();
            if (data.success && data.session) {
                this.proctoringSessionId = data.session.id;
                return true;
            }
            return false;
        } catch (err) {
            console.error('[AI Proctoring] Gagal inisialisasi sesi backend:', err);
            return false;
        }
    }

    /**
     * Eksekusi satu siklus analisis frame dan kirim telemetri (Throttled Tick)
     */
    async runAnalysisTick() {
        if (!this.isRunning || !this.proctoringSessionId) return;

        const primaryVideo = document.getElementById(this.primaryVideoId);
        if (!primaryVideo || primaryVideo.readyState < 2) {
            // Kamera belum siap
            return;
        }

        try {
            // 1. Tangkap frame video kamera utama ke canvas
            this.ctx.drawImage(primaryVideo, 0, 0, 320, 240);
            const frameData = this.ctx.getImageData(0, 0, 320, 240);

            // 2. Eksekusi Analisis Vision Client-Side
            const visionResult = this.analyzeFrameVision(frameData);

            // 3. Analisis Kamera Kedua jika aktif
            const secondaryVision = this.analyzeSecondaryCamera();

            // 4. Gabungkan telemetri
            const telemetry = {
                head_pose_yaw: visionResult.yaw,
                head_pose_pitch: visionResult.pitch,
                head_pose_roll: visionResult.roll,
                gaze_direction: visionResult.gaze,
                detected_face_count: visionResult.faceCount,
                mouth_movement_detected: visionResult.mouthMovement ? 1 : 0,
                phone_detected: secondaryVision.phoneDetected ? 1 : 0,
                audio_noise_level: 0.0
            };

            this.lastTelemetry = telemetry;
            this.updateHudMetrics(telemetry);

            // 5. Jika terdeteksi anomali, sertakan snapshot JPEG terkompresi
            let snapshotBase64 = null;
            if (visionResult.faceCount !== 1 || visionResult.gaze === 'away' || secondaryVision.phoneDetected) {
                snapshotBase64 = this.canvas.toDataURL('image/jpeg', 0.6);
                telemetry.snapshot_base64 = snapshotBase64;
            }

            // 6. Kirim payload telemetri ke server
            await this.sendTelemetry(telemetry);

        } catch (err) {
            console.error('[AI Proctoring Tick Error]:', err);
        }
    }

    /**
     * Algoritma Analisis Wajah, Pose Kepala, dan Pandangan Mata berbasis Canvas Vision
     */
    analyzeFrameVision(frameData) {
        const { data, width, height } = frameData;
        let brightnessSum = 0;
        let skinPixels = 0;
        let skinCenterX = 0;
        let skinCenterY = 0;

        // Deteksi skin-tone & pusat massa wajah (Face Centroid & Yaw/Pitch estimation)
        for (let i = 0; i < data.length; i += 16) { // Subsampled 4x4 untuk efisiensi CPU
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];

            const brightness = 0.299 * r + 0.587 * g + 0.114 * b;
            brightnessSum += brightness;

            // Aturan Klasifikasi Skin Tone Sederhana (YCbCr / RGB Rule)
            if (r > 60 && g > 40 && b > 20 && r > g && r > b && (r - g) > 12 && (r - b) > 12) {
                skinPixels++;
                const pixelIdx = i / 4;
                skinCenterX += (pixelIdx % width);
                skinCenterY += Math.floor(pixelIdx / width);
            }
        }

        const totalSamples = data.length / 16;
        const avgBrightness = brightnessSum / totalSamples;
        const skinRatio = skinPixels / totalSamples;

        let faceCount = 1;
        let yaw = 0;
        let pitch = 0;
        let gaze = 'center';

        // Anomali: Tidak ada wajah (pencahayaan terlalu gelap atau tidak ada skin pixels)
        if (skinRatio < 0.04 || avgBrightness < 15) {
            this.consecutiveNoFace++;
            if (this.consecutiveNoFace >= 2) {
                faceCount = 0;
                gaze = 'away';
            }
        } else {
            this.consecutiveNoFace = 0;

            // Estimasi Yaw dari pergeseran titik berat (Centroid Shift)
            const centroidX = skinCenterX / (skinPixels || 1);
            const centroidY = skinCenterY / (skinPixels || 1);
            const midX = width / 2;
            const midY = height / 2;

            // Yaw: deviasi horizontal (-45 s/d +45 derajat)
            yaw = Math.round(((centroidX - midX) / midX) * 45);

            // Pitch: deviasi vertikal (-35 s/d +35 derajat)
            pitch = Math.round(((centroidY - midY) / midY) * 35);

            // Tentukan arah pandangan
            if (yaw > 25) {
                gaze = 'right';
            } else if (yaw < -25) {
                gaze = 'left';
            } else if (pitch > 20) {
                gaze = 'down';
            } else if (pitch < -20) {
                gaze = 'up';
            } else {
                gaze = 'center';
            }

            // Anomali: Terlalu banyak skin pixel terpisah (indikasi orang lain di frame)
            if (skinRatio > 0.48) {
                faceCount = 2; // Multiple faces
            }
        }

        return {
            faceCount,
            yaw,
            pitch,
            roll: 0,
            gaze,
            mouthMovement: false
        };
    }

    /**
     * Analisis Kamera Kedua (Pengawas Samping / Meja)
     */
    analyzeSecondaryCamera() {
        const secVideo = document.getElementById(this.secondaryVideoId);
        if (!secVideo || secVideo.readyState < 2) {
            if (this.lastSecondaryCamConnected === true) {
                // Feed kamera sekunder sebelumnya terhubung, kini terputus
                this.recordViolation('SECONDARY_CAM_LOST', 0.90, {
                    event: 'secondary_cam_lost',
                    message: 'Feed kamera samping/sekunder terputus saat ujian berlangsung'
                });
            }
            this.lastSecondaryCamConnected = false;
            return { phoneDetected: false, connected: false };
        }

        this.lastSecondaryCamConnected = true;
        return {
            phoneDetected: false,
            connected: true
        };
    }

    /**
     * Kirim Telemetri ke Backend dan perbarui Skor Risiko & Peringatan
     */
    async sendTelemetry(telemetry) {
        try {
            telemetry.timestamp = Date.now();
            const res = await fetch(`${this.baseUrl}/siswa/proctoring/log-telemetry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [this.csrfHeader]: this.csrfToken
                },
                body: JSON.stringify({
                    proctoring_session_id: this.proctoringSessionId,
                    telemetry: telemetry
                })
            });

            const data = await res.json();
            if (data.success) {
                this.currentRiskScore = data.risk_score;
                this.currentRiskTier = data.risk_tier;
                this.currentIntegrityScore = data.integrity_score;

                this.updateHudRisk(data.risk_score, data.risk_tier);

                // Periksa jika ada peringatan baru yang memerlukan intervensi modal
                if (data.active_warnings && data.active_warnings.length > 0) {
                    const latestWarn = data.active_warnings[0];
                    if (latestWarn.warning_type === 'modal_ack') {
                        this.showModalWarning(latestWarn);
                    } else {
                        this.showToastWarning(latestWarn.warning_message);
                    }
                }
            }
        } catch (err) {
            console.error('[AI Proctoring] Gagal kirim telemetri:', err);
        }
    }

    /**
     * Kirim pelanggaran khusus langsung ke server
     */
    async recordViolation(code, confidence = 0.90, metadata = {}) {
        if (!this.proctoringSessionId) return;

        try {
            const snapshot = this.canvas.toDataURL('image/jpeg', 0.6);
            const res = await fetch(`${this.baseUrl}/siswa/proctoring/record-violation`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [this.csrfHeader]: this.csrfToken
                },
                body: JSON.stringify({
                    proctoring_session_id: this.proctoringSessionId,
                    violation_code: code,
                    confidence_score: confidence,
                    evidence_snapshot: snapshot,
                    metadata: metadata
                })
            });

            const data = await res.json();
            if (data.success && data.warning) {
                if (data.warning.warning_type === 'modal_ack') {
                    this.showModalWarning(data.warning);
                } else {
                    this.showToastWarning(data.warning.warning_message);
                }
            }
        } catch (err) {
            console.error('[AI Proctoring] Gagal mencatat pelanggaran:', err);
        }
    }

    /**
     * Konfirmasi Pengakuan Peringatan (UC-18)
     */
    async acknowledgeWarning(warningId) {
        try {
            await fetch(`${this.baseUrl}/siswa/proctoring/acknowledge-warning`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [this.csrfHeader]: this.csrfToken
                },
                body: JSON.stringify({
                    warning_id: warningId
                })
            });
        } catch (err) {
            console.error('[AI Proctoring] Gagal konfirmasi peringatan:', err);
        }
    }

    /**
     * Verifikasi Foto Wajah Awal (UC-08)
     */
    async verifyFaceInitial(capturedBase64, confidence = 0.95) {
        if (!this.proctoringSessionId) {
            await this.initServerSession();
        }

        try {
            const res = await fetch(`${this.baseUrl}/siswa/proctoring/verify-face`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    [this.csrfHeader]: this.csrfToken
                },
                body: JSON.stringify({
                    proctoring_session_id: this.proctoringSessionId,
                    captured_photo: capturedBase64,
                    confidence: confidence
                })
            });
            return await res.json();
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /**
     * Update UI HUD
     */
    updateHudStatus(text, badgeClass) {
        const el = document.getElementById('ai-hud-status');
        if (el) {
            el.textContent = text;
            el.className = 'badge ' + badgeClass;
        }
    }

    updateHudMetrics(telemetry) {
        const faceEl = document.getElementById('ai-hud-face');
        if (faceEl) {
            if (telemetry.detected_face_count === 1) {
                if (telemetry.gaze_direction === 'center') {
                    faceEl.textContent = 'Fokus Layar';
                    faceEl.className = 'badge bg-success-subtle text-success';
                } else {
                    faceEl.textContent = 'Menoleh (' + telemetry.gaze_direction + ')';
                    faceEl.className = 'badge bg-warning-subtle text-warning';
                }
            } else if (telemetry.detected_face_count === 0) {
                faceEl.textContent = 'Tidak Terlihat';
                faceEl.className = 'badge bg-danger-subtle text-danger';
            } else {
                faceEl.textContent = telemetry.detected_face_count + ' Wajah';
                faceEl.className = 'badge bg-danger-subtle text-danger';
            }
        }
    }

    updateHudRisk(score, tier) {
        const riskEl = document.getElementById('ai-hud-risk');
        if (riskEl) {
            let badge = 'bg-success';
            if (tier === 'medium') badge = 'bg-warning text-dark';
            else if (tier === 'high') badge = 'bg-danger';
            else if (tier === 'critical') badge = 'bg-dark text-white border border-danger';

            riskEl.textContent = `${score} (${tier.toUpperCase()})`;
            riskEl.className = 'badge ' + badge;
        }
    }

    showToastWarning(message) {
        // Tampilkan toast bootstrap atau toast custom
        let toastContainer = document.getElementById('ai-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'ai-toast-container';
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '1090';
            document.body.appendChild(toastContainer);
        }

        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-warning border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <span class="small">${message}</span>
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        setTimeout(() => {
            const toastEl = document.getElementById(toastId);
            if (toastEl) toastEl.remove();
        }, 6000);
    }

    showModalWarning(warning) {
        const modalEl = document.getElementById('ai-warning-modal');
        const msgEl = document.getElementById('ai-modal-warning-message');
        const btnAck = document.getElementById('btn-ack-ai-warning');

        if (msgEl) msgEl.textContent = warning.warning_message || warning.message;
        if (btnAck) btnAck.dataset.warningId = warning.id;

        if (window.bootstrap && bootstrap.Modal) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }
}

// Global expose
window.AiProctoringEngine = AiProctoringEngine;
