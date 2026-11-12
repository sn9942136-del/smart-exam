/**
 * Camera Permission & Device Enumeration Module (UC-07, UC-09)
 * Mengelola deteksi secure context HTTPS, request permission WebRTC, dan enumerasi device kamera.
 */
class CameraPermission {
    /**
     * Memeriksa apakah halaman berjalan dalam secure context (HTTPS atau Localhost)
     * WebRTC MediaDevices API mewajibkan secure context untuk mengakses kamera.
     */
    static isSecureContext() {
        if (typeof window.isSecureContext !== 'undefined') {
            return window.isSecureContext;
        }
        const proto = window.location.protocol;
        const host = window.location.hostname;
        return proto === 'https:' || host === 'localhost' || host === '127.0.0.1' || host.endsWith('.localhost');
    }

    /**
     * Memeriksa apakah browser mendukung MediaDevices API
     */
    static isSupported() {
        return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function');
    }

    /**
     * Meminta izin akses kamera awal dari pengguna.
     * Izin ini diperlukan agar browser membuka akses label nama perangkat pada enumerateDevices.
     */
    static async requestPermission() {
        if (!this.isSecureContext()) {
            throw new Error('SECURE_CONTEXT_REQUIRED: Akses kamera WebRTC mewajibkan koneksi HTTPS atau Localhost.');
        }

        if (!this.isSupported()) {
            throw new Error('NOT_SUPPORTED: Browser Anda tidak mendukung WebRTC MediaDevices API.');
        }

        try {
            const tempStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });

            // Langsung hentikan stream sementara setelah izin diperoleh
            tempStream.getTracks().forEach(track => track.stop());
            return { success: true, granted: true };
        } catch (err) {
            const parsed = this.parseError(err);
            return {
                success: false,
                granted: false,
                error: parsed
            };
        }
    }

    /**
     * Mengambil daftar seluruh perangkat kamera video input yang terhubung
     * @returns {Promise<Array<{deviceId: string, label: string, groupId: string}>>}
     */
    static async getVideoDevices() {
        if (!this.isSupported()) {
            return [];
        }

        try {
            const allDevices = await navigator.mediaDevices.enumerateDevices();
            const videoInputs = allDevices.filter(device => device.kind === 'videoinput');

            return videoInputs.map((device, index) => {
                let label = device.label ? device.label.trim() : '';
                if (!label) {
                    label = `Kamera Video #${index + 1}`;
                }
                return {
                    deviceId: device.deviceId,
                    label: label,
                    groupId: device.groupId
                };
            });
        } catch (err) {
            console.warn('[CameraPermission] Gagal mengambil daftar perangkat:', err);
            return [];
        }
    }

    /**
     * Menerjemahkan exception browser ke format pesan bahasa Indonesia yang ramah pengguna
     */
    static parseError(err) {
        const name = err.name || 'UnknownError';
        const msg = err.message || '';

        switch (name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                return {
                    code: 'PERMISSION_DENIED',
                    title: 'Izin Akses Kamera Ditolak',
                    message: 'Browser memblokir izin kamera. Silakan klik ikon gembok/kamera di address bar browser Anda dan ubah izin kamera menjadi "Izinkan" (Allow).',
                    suggestion: 'Klik ikon gembok di sebelah URL browser -> Izin Situs -> Izinkan Kamera, lalu refresh halaman.'
                };

            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return {
                    code: 'NO_DEVICE_FOUND',
                    title: 'Kamera Tidak Ditemukan',
                    message: 'Tidak ada perangkat kamera/webcam yang terdeteksi di komputer atau laptop Anda.',
                    suggestion: 'Pastikan webcam internal menyala atau hubungkan webcam USB eksternal / smartphone Anda.'
                };

            case 'NotReadableError':
            case 'TrackStartError':
                return {
                    code: 'DEVICE_IN_USE',
                    title: 'Kamera Sedang Digunakan',
                    message: 'Kamera tidak dapat diakses karena sedang aktif digunakan oleh aplikasi lain (seperti Zoom, Google Meet, OBS, atau tab browser lain).',
                    suggestion: 'Tutup aplikasi lain yang sedang menggunakan kamera, lalu coba lagi.'
                };

            case 'OverconstrainedError':
                return {
                    code: 'CONSTRAINT_ERROR',
                    title: 'Resolusi Tidak Didukung',
                    message: 'Kamera Anda tidak mendukung parameter resolusi atau frame rate yang diminta.',
                    suggestion: 'Sistem akan secara otomatis menyesuaikan ke resolusi default yang didukung kamera.'
                };

            case 'SecurityError':
                return {
                    code: 'INSECURE_CONTEXT',
                    title: 'Koneksi Tidak Aman (Non-HTTPS)',
                    message: 'WebRTC mewajibkan akses melalui protokol HTTPS atau Localhost.',
                    suggestion: 'Pastikan Anda mengakses URL menggunakan https:// atau localhost.'
                };

            default:
                return {
                    code: 'GENERAL_ERROR',
                    title: 'Kendala Akses Kamera',
                    message: msg || 'Terjadi kendala saat menghubungkan ke perangkat kamera.',
                    suggestion: 'Periksa koneksi fisik kamera dan muat ulang halaman.'
                };
        }
    }
}

// Attach globally
window.CameraPermission = CameraPermission;
