/**
 * Camera Manager Module (UC-07, UC-09, UC-14)
 * Orkestrator utama WebRTC Dual Camera, integrasi Device Enumeration, Preview,
 * Analisis Kecerahan Lingkungan (Canvas Luminance), Deteksi Resolusi, dan Rekoneksi.
 */
class CameraManager {
    constructor(config = {}) {
        this.primaryVideoId = config.primaryVideoId || 'cam1PreviewVideo';
        this.secondaryVideoId = config.secondaryVideoId || 'cam2PreviewVideo';

        // Callbacks
        this.onDevicesFound = config.onDevicesFound || function() {};
        this.onStreamStarted = config.onStreamStarted || function() {};
        this.onStreamStopped = config.onStreamStopped || function() {};
        this.onHealthUpdate = config.onHealthUpdate || function() {};
        this.onStatusChange = config.onStatusChange || function() {};
        this.onError = config.onError || function() {};

        this.devices = [];
        this.activeStreams = {
            primary: null,
            secondary: null
        };
        this.selectedDeviceIds = {
            primary: null,
            secondary: null
        };
        this.healthIntervals = {
            primary: null,
            secondary: null
        };

        // Canvas offscreen untuk analisis luminansi/kecerahan
        this.analysisCanvas = document.createElement('canvas');
        this.analysisCanvas.width = 32;
        this.analysisCanvas.height = 32;
        this.analysisCtx = this.analysisCanvas.getContext('2d', { willReadFrequently: true });

        // Inisialisasi modul rekoneksi
        this.reconnector = new CameraReconnect({
            maxRetries: 5,
            initialDelay: 1500,
            onDisconnect: (key, msg) => {
                this.onStatusChange(key, 'disconnected', msg);
            },
            onReconnecting: (key, data) => {
                this.onStatusChange(key, 'reconnecting', `Kamera terputus. Menyambung ulang... (Percobaan ${data.attempt}/${data.maxRetries})`);
            },
            onReconnected: (key, newStream) => {
                this.attachStreamToElement(key, newStream);
                this.onStatusChange(key, 'active', 'Kamera berhasil tersambung kembali.');
                this.onStreamStarted(key, newStream, this.getDeviceDetails(this.selectedDeviceIds[key]));
            },
            onFailed: (key, errorMsg) => {
                this.onStatusChange(key, 'failed', errorMsg);
                this.onError(key, { code: 'RECONNECT_FAILED', message: errorMsg });
            }
        });
    }

    /**
     * Inisialisasi awal: cek keamanan HTTPS & muat daftar perangkat kamera
     */
    async init() {
        if (!CameraPermission.isSecureContext()) {
            const err = CameraPermission.parseError({ name: 'SecurityError' });
            this.onError('system', err);
            return { success: false, error: err };
        }

        if (!CameraPermission.isSupported()) {
            const err = CameraPermission.parseError({ name: 'NotFoundError' });
            this.onError('system', err);
            return { success: false, error: err };
        }

        // Minta izin awal agar label perangkat dapat terbaca oleh browser
        const perm = await CameraPermission.requestPermission();
        if (!perm.success) {
            this.onError('system', perm.error);
            return { success: false, error: perm.error };
        }

        // Ambil daftar kamera
        this.devices = await CameraPermission.getVideoDevices();
        this.onDevicesFound(this.devices);

        return {
            success: true,
            devices: this.devices,
            deviceCount: this.devices.length
        };
    }

    /**
     * Muat ulang / Segarkan daftar perangkat kamera (refresh device list)
     */
    async refreshDevices() {
        this.devices = await CameraPermission.getVideoDevices();
        this.onDevicesFound(this.devices);
        return this.devices;
    }

    /**
     * Memulai stream kamera tertentu ('primary' atau 'secondary')
     * @param {'primary'|'secondary'} cameraKey
     * @param {string} deviceId
     */
    async startCamera(cameraKey, deviceId) {
        if (!deviceId) {
            this.stopCamera(cameraKey);
            return { success: false, error: 'Device ID tidak boleh kosong.' };
        }

        this.stopCamera(cameraKey);
        this.selectedDeviceIds[cameraKey] = deviceId;
        this.onStatusChange(cameraKey, 'connecting', 'Menghubungkan ke perangkat kamera...');

        const constraints = {
            video: {
                deviceId: { exact: deviceId },
                width: { ideal: 1280, min: 640 },
                height: { ideal: 720, min: 480 },
                frameRate: { ideal: 24, max: 30 }
            },
            audio: false
        };

        try {
            let stream = null;
            try {
                // Percobaan 1: Dengan constraint exact deviceId & ideal 720p
                stream = await navigator.mediaDevices.getUserMedia(constraints);
            } catch (exactErr) {
                console.warn(`[CameraManager] Exact constraint failed for '${cameraKey}', fallback to loose constraint:`, exactErr);
                // Fallback: Tanpa exact constraint jika browser/OS memiliki driver virtual
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: deviceId },
                    audio: false
                });
            }

            this.activeStreams[cameraKey] = stream;
            this.attachStreamToElement(cameraKey, stream);

            // Pasang pemantau rekoneksi
            this.reconnector.startMonitoring(cameraKey, stream, deviceId, async () => {
                return await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: deviceId },
                    audio: false
                });
            });

            // Mulai analisis kualitas frame & kecerahan secara berkala (1.2 detik sekali)
            this.startHealthMonitoring(cameraKey);

            this.onStatusChange(cameraKey, 'active', 'Kamera aktif dan feed video berjalan stabil.');
            this.onStreamStarted(cameraKey, stream, this.getDeviceDetails(deviceId));

            return { success: true, stream };
        } catch (err) {
            console.error(`[CameraManager] Gagal memulai kamera '${cameraKey}':`, err);
            const parsed = CameraPermission.parseError(err);
            this.onStatusChange(cameraKey, 'error', parsed.message);
            this.onError(cameraKey, parsed);
            return { success: false, error: parsed };
        }
    }

    /**
     * Memasang MediaStream ke elemen HTML <video>
     */
    attachStreamToElement(cameraKey, stream) {
        const videoId = (cameraKey === 'primary') ? this.primaryVideoId : this.secondaryVideoId;
        const videoEl = document.getElementById(videoId);

        if (videoEl) {
            videoEl.srcObject = stream;
            videoEl.muted = true;
            videoEl.playsInline = true;
            videoEl.autoplay = true;

            videoEl.play().catch(e => {
                console.warn(`[CameraManager] Autoplay caught for ${cameraKey}:`, e);
            });
        }
    }

    /**
     * Memulai loop analisis kecerahan, resolusi, dan aliran frame
     */
    startHealthMonitoring(cameraKey) {
        if (this.healthIntervals[cameraKey]) {
            clearInterval(this.healthIntervals[cameraKey]);
        }

        let lastTime = 0;
        let freezeCounter = 0;

        this.healthIntervals[cameraKey] = setInterval(() => {
            const videoId = (cameraKey === 'primary') ? this.primaryVideoId : this.secondaryVideoId;
            const videoEl = document.getElementById(videoId);

            if (!videoEl || videoEl.readyState < 2 || !this.activeStreams[cameraKey]) {
                return;
            }

            // 1. Cek Aliran Frame (Frame Flow)
            const currentTime = videoEl.currentTime;
            let isFrameAdvancing = true;
            if (currentTime === lastTime) {
                freezeCounter++;
                if (freezeCounter >= 3) {
                    isFrameAdvancing = false;
                }
            } else {
                freezeCounter = 0;
                lastTime = currentTime;
            }

            // 2. Cek Resolusi Video Aktual
            const width = videoEl.videoWidth || 0;
            const height = videoEl.videoHeight || 0;
            let resolutionLabel = 'Unknown';
            let resolutionGrade = 'warning';

            if (width >= 1920 && height >= 1080) {
                resolutionLabel = `1080p Full HD (${width}x${height})`;
                resolutionGrade = 'excellent';
            } else if (width >= 1280 && height >= 720) {
                resolutionLabel = `720p HD (${width}x${height})`;
                resolutionGrade = 'good';
            } else if (width >= 640 && height >= 480) {
                resolutionLabel = `480p SD (${width}x${height})`;
                resolutionGrade = 'acceptable';
            } else if (width > 0) {
                resolutionLabel = `Low (${width}x${height})`;
                resolutionGrade = 'poor';
            }

            // 3. Analisis Luminansi & Kecerahan (Canvas Sampling 32x32)
            let brightnessScore = 0;
            let brightnessStatus = 'unknown';
            let brightnessMessage = 'Memeriksa pencahayaan...';

            try {
                if (this.analysisCtx && width > 0 && height > 0) {
                    this.analysisCtx.drawImage(videoEl, 0, 0, 32, 32);
                    const imgData = this.analysisCtx.getImageData(0, 0, 32, 32);
                    const data = imgData.data;
                    let totalLuminance = 0;
                    const totalPixels = 32 * 32;

                    for (let i = 0; i < data.length; i += 4) {
                        const r = data[i];
                        const g = data[i + 1];
                        const b = data[i + 2];
                        // Standar persepsi luminansi ITU-R BT.601
                        const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
                        totalLuminance += luminance;
                    }

                    brightnessScore = Math.round(totalLuminance / totalPixels);

                    if (brightnessScore < 45) {
                        brightnessStatus = 'too_dark';
                        brightnessMessage = 'Pencahayaan Kurang (Ruangan Terlalu Gelap)';
                    } else if (brightnessScore > 215) {
                        brightnessStatus = 'too_bright';
                        brightnessMessage = 'Silau / Terlalu Terang (Overexposed)';
                    } else {
                        brightnessStatus = 'good';
                        brightnessMessage = 'Pencahayaan Ideal & Wajah Terlihat Jelas';
                    }
                }
            } catch (canvasErr) {
                console.warn('[CameraManager] Canvas analysis issue:', canvasErr);
            }

            // Emit data kesehatan kamera
            const healthData = {
                cameraKey: cameraKey,
                isActive: isFrameAdvancing,
                resolution: {
                    width: width,
                    height: height,
                    label: resolutionLabel,
                    grade: resolutionGrade
                },
                brightness: {
                    score: brightnessScore, // 0 - 255
                    percentage: Math.round((brightnessScore / 255) * 100),
                    status: brightnessStatus,
                    message: brightnessMessage
                },
                timestamp: new Date().toISOString()
            };

            this.onHealthUpdate(cameraKey, healthData);
        }, 1200);
    }

    /**
     * Menghentikan stream kamera tertentu
     */
    stopCamera(cameraKey) {
        if (this.healthIntervals[cameraKey]) {
            clearInterval(this.healthIntervals[cameraKey]);
            this.healthIntervals[cameraKey] = null;
        }

        this.reconnector.stopMonitoring(cameraKey);

        const stream = this.activeStreams[cameraKey];
        if (stream) {
            stream.getTracks().forEach(track => {
                track.stop();
            });
            this.activeStreams[cameraKey] = null;
        }

        const videoId = (cameraKey === 'primary') ? this.primaryVideoId : this.secondaryVideoId;
        const videoEl = document.getElementById(videoId);
        if (videoEl) {
            videoEl.srcObject = null;
        }

        this.onStatusChange(cameraKey, 'inactive', 'Kamera nonaktif.');
        this.onStreamStopped(cameraKey);
    }

    /**
     * Menghentikan seluruh kamera dan pemantauan
     */
    stopAll() {
        this.stopCamera('primary');
        this.stopCamera('secondary');
        this.reconnector.destroy();
    }

    /**
     * Dapatkan detail perangkat dari ID
     */
    getDeviceDetails(deviceId) {
        if (!deviceId) return null;
        return this.devices.find(d => d.deviceId === deviceId) || { deviceId, label: 'Kamera' };
    }

    /**
     * Periksa apakah status dual kamera siap untuk memulai ujian
     */
    getReadinessStatus(requireDualCamera = true) {
        const isPrimaryActive = !!(this.activeStreams.primary && this.activeStreams.primary.active);
        const isSecondaryActive = !!(this.activeStreams.secondary && this.activeStreams.secondary.active);

        if (requireDualCamera) {
            return {
                isReady: isPrimaryActive && isSecondaryActive,
                primaryReady: isPrimaryActive,
                secondaryReady: isSecondaryActive,
                message: (isPrimaryActive && isSecondaryActive)
                    ? 'Dual kamera telah siap dan terkalibrasi!'
                    : 'Kedua kamera (Kamera Depan & Kamera Samping) wajib terhubung aktif.'
            };
        }

        return {
            isReady: isPrimaryActive,
            primaryReady: isPrimaryActive,
            secondaryReady: isSecondaryActive,
            message: isPrimaryActive
                ? 'Kamera utama siap digunakan.'
                : 'Kamera utama wajib terhubung aktif sebelum memulai ujian.'
        };
    }
}

// Attach globally
window.CameraManager = CameraManager;
