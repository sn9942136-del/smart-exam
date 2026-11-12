/**
 * Camera Reconnection & Fault-Tolerance Module (UC-14)
 * Mendeteksi event disconnect/unplug perangkat dan melakukan rekoneksi otomatis dengan exponential backoff.
 */
class CameraReconnect {
    constructor(config = {}) {
        this.maxRetries = config.maxRetries || 5;
        this.initialDelay = config.initialDelay || 1500;
        this.onDisconnect = config.onDisconnect || function() {};
        this.onReconnecting = config.onReconnecting || function() {};
        this.onReconnected = config.onReconnected || function() {};
        this.onFailed = config.onFailed || function() {};

        this.monitors = {}; // cameraKey -> { deviceId, restartCallback, retryCount, timeoutId, isReconnecting }
        this.initGlobalDeviceListener();
    }

    /**
     * Memasang pendengar event global saat ada penambahan/pengurangan perangkat keras di OS
     */
    initGlobalDeviceListener() {
        if (navigator.mediaDevices && typeof navigator.mediaDevices.addEventListener === 'function') {
            navigator.mediaDevices.addEventListener('devicechange', async () => {
                console.info('[CameraReconnect] Deteksi perubahan perangkat keras (devicechange)');
                // Periksa apakah ada kamera yang sedang dalam status reconnecting
                for (const [key, monitor] of Object.entries(this.monitors)) {
                    if (monitor.isReconnecting) {
                        // Segera coba reconnect jika perangkat baru dicolokkan
                        this.attemptReconnect(key);
                    }
                }
            });
        }
    }

    /**
     * Memulai pemantauan stream kamera terhadap event disconnect
     * @param {string} cameraKey - Identifier ('primary' atau 'secondary')
     * @param {MediaStream} stream - MediaStream yang sedang aktif
     * @param {string} deviceId - ID perangkat yang digunakan
     * @param {Function} restartCallback - Fungsi async () => MediaStream untuk memulai ulang stream
     */
    startMonitoring(cameraKey, stream, deviceId, restartCallback) {
        this.stopMonitoring(cameraKey);

        this.monitors[cameraKey] = {
            deviceId: deviceId,
            restartCallback: restartCallback,
            retryCount: 0,
            timeoutId: null,
            isReconnecting: false
        };

        if (stream && stream.getVideoTracks) {
            const tracks = stream.getVideoTracks();
            tracks.forEach(track => {
                track.onended = () => {
                    console.warn(`[CameraReconnect] Track kamera '${cameraKey}' terputus (onended).`);
                    this.handleTrackEnded(cameraKey);
                };
            });
        }
    }

    /**
     * Handler saat track video terputus
     */
    handleTrackEnded(cameraKey) {
        const monitor = this.monitors[cameraKey];
        if (!monitor) return;

        this.onDisconnect(cameraKey, 'Koneksi kamera terputus (kabel terlepas atau perangkat dinonaktifkan).');
        this.attemptReconnect(cameraKey);
    }

    /**
     * Mencoba rekoneksi dengan exponential backoff
     */
    async attemptReconnect(cameraKey) {
        const monitor = this.monitors[cameraKey];
        if (!monitor) return;

        if (monitor.timeoutId) {
            clearTimeout(monitor.timeoutId);
            monitor.timeoutId = null;
        }

        monitor.isReconnecting = true;

        if (monitor.retryCount >= this.maxRetries) {
            console.error(`[CameraReconnect] Rekoneksi kamera '${cameraKey}' gagal setelah ${this.maxRetries} percobaan.`);
            monitor.isReconnecting = false;
            this.onFailed(cameraKey, `Kamera gagal terhubung kembali setelah ${this.maxRetries} percobaan. Silakan periksa kabel atau sambungkan ulang perangkat.`);
            return;
        }

        monitor.retryCount++;
        const delay = this.initialDelay * Math.pow(1.5, monitor.retryCount - 1);

        this.onReconnecting(cameraKey, {
            attempt: monitor.retryCount,
            maxRetries: this.maxRetries,
            nextDelayMs: Math.round(delay)
        });

        monitor.timeoutId = setTimeout(async () => {
            try {
                console.info(`[CameraReconnect] Mencoba menyambung ulang kamera '${cameraKey}' (Percobaan #${monitor.retryCount})...`);
                const newStream = await monitor.restartCallback();

                if (newStream && newStream.active) {
                    console.info(`[CameraReconnect] Kamera '${cameraKey}' berhasil tersambung kembali!`);
                    monitor.retryCount = 0;
                    monitor.isReconnecting = false;
                    this.startMonitoring(cameraKey, newStream, monitor.deviceId, monitor.restartCallback);
                    this.onReconnected(cameraKey, newStream);
                } else {
                    throw new Error('Stream tidak aktif.');
                }
            } catch (err) {
                console.warn(`[CameraReconnect] Percobaan #${monitor.retryCount} untuk '${cameraKey}' gagal:`, err.message);
                // Lanjutkan ke percobaan berikutnya
                this.attemptReconnect(cameraKey);
            }
        }, delay);
    }

    /**
     * Hentikan pemantauan untuk kamera tertentu
     */
    stopMonitoring(cameraKey) {
        const monitor = this.monitors[cameraKey];
        if (monitor) {
            if (monitor.timeoutId) {
                clearTimeout(monitor.timeoutId);
            }
            delete this.monitors[cameraKey];
        }
    }

    /**
     * Hentikan seluruh pemantauan
     */
    destroy() {
        for (const key of Object.keys(this.monitors)) {
            this.stopMonitoring(key);
        }
    }
}

// Attach globally
window.CameraReconnect = CameraReconnect;
