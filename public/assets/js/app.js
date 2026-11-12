/**
 * ==============================================================================
 * SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI - CORE JAVASCRIPT
 * Modular Vanilla JS with Fetch API Wrapper, UI Components & Camera Handlers
 * ==============================================================================
 */

const App = (function () {
    'use strict';

    // Ambil base URL dan CSRF token dari meta tags
    const baseUrlMeta = document.querySelector('meta[name="base-url"]');
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');

    const BASE_URL = baseUrlMeta ? baseUrlMeta.getAttribute('content').replace(/\/+$/, '') : '';
    let CSRF_TOKEN = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

    let activeWebcamStream = null;
    let activeWebcamStreams = { cam1: null, cam2: null };

    /**
     * Helper untuk membuat URL absolut
     */
    function url(path = '') {
        const cleanPath = path.replace(/^\/+/, '');
        return cleanPath ? `${BASE_URL}/${cleanPath}` : BASE_URL;
    }

    /**
     * Default headers untuk Fetch API AJAX
     */
    function defaultHeaders(custom = {}) {
        return {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json',
            ...custom
        };
    }

    /**
     * Wrapper Fetch API (GET)
     */
    async function get(path, headers = {}) {
        try {
            const response = await fetch(url(path), {
                method: 'GET',
                headers: defaultHeaders(headers)
            });
            return await handleResponse(response);
        } catch (error) {
            console.error('[App.get Error]:', error);
            throw error;
        }
    }

    /**
     * Wrapper Fetch API (POST / JSON)
     */
    async function post(path, data = {}, headers = {}) {
        try {
            const isFormData = data instanceof FormData;
            const finalHeaders = defaultHeaders(
                isFormData ? headers : { 'Content-Type': 'application/json', ...headers }
            );

            const body = isFormData ? data : JSON.stringify(data);

            const response = await fetch(url(path), {
                method: 'POST',
                headers: finalHeaders,
                body: body
            });

            return await handleResponse(response);
        } catch (error) {
            console.error('[App.post Error]:', error);
            throw error;
        }
    }

    /**
     * Handle & parse response
     */
    async function handleResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        let data;

        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            data = await response.text();
        }

        if (!response.ok) {
            const errorMessage = (typeof data === 'object' && data.message) ? data.message : `HTTP Error ${response.status}`;
            throw new Error(errorMessage);
        }

        return data;
    }

    /**
     * Menampilkan Bootstrap Toast Notification dengan indikator warna
     */
    function toast(message, type = 'primary', title = 'Sistem Ujian AI') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toastId = 'toast_' + Date.now();
        const icon = type === 'success' ? 'bi-check-circle-fill text-success' :
                     type === 'danger' ? 'bi-exclamation-triangle-fill text-danger' :
                     type === 'warning' ? 'bi-exclamation-circle-fill text-warning' : 'bi-info-circle-fill text-primary';

        const borderClass = `toast-border-${type}`;

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center shadow-lg border-0 rounded-3 mb-2 ${borderClass}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-dark text-white border-0 py-2">
                    <i class="bi ${icon} me-2 fs-6"></i>
                    <strong class="me-auto fs-xs">${title}</strong>
                    <small class="text-white-50 fs-3xs">Baru saja</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Tutup"></button>
                </div>
                <div class="toast-body bg-white text-dark fs-xs py-2.5 px-3">
                    ${message}
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        if (toastElement && window.bootstrap) {
            const bsToast = new bootstrap.Toast(toastElement, { delay: 4500 });
            bsToast.show();
            toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
        }
    }

    /**
     * Toggle Sidebar Drawer (Mobile & Desktop)
     */
    function toggleSidebar() {
        if (window.innerWidth < 992) {
            document.body.classList.toggle('sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
        }
    }

    /**
     * Filter baris Data Table berdasarkan query teks
     */
    function filterTable(tableId, query) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const term = query.toLowerCase().trim();
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            if (row.querySelector('td[colspan]')) return; // Abaikan empty state row
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }

    /**
     * Buka Modal Kalibrasi & Diagnostik Kamera
     */
    function openCameraDiagnostic() {
        const modalEl = document.getElementById('appCameraModal');
        if (modalEl && window.bootstrap) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    /**
     * Mulai Stream Webcam Browser (Mendukung Multi-Camera / Dual Cam WebRTC)
     */
    async function startWebcamPreview(videoElemId = 'cam1PreviewVideo', placeholderElemId = 'cam1Placeholder', deviceId = null, streamKey = 'cam1') {
        const video = document.getElementById(videoElemId);
        const placeholder = document.getElementById(placeholderElemId);

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            toast('Browser Anda tidak mendukung akses kamera WebRTC.', 'danger', 'Kamera Error');
            return;
        }

        try {
            if (activeWebcamStreams[streamKey]) {
                if (video) {
                    try { video.pause(); } catch (e) {}
                    video.srcObject = null;
                }
                activeWebcamStreams[streamKey].getTracks().forEach(track => track.stop());
                activeWebcamStreams[streamKey] = null;
            }

            // Beri jeda singkat agar driver DirectShow/Virtual Camera melepaskan handle sebelumnya
            await new Promise(r => setTimeout(r, 120));

            let stream = null;
            const videoConstraint = deviceId 
                ? { deviceId: { exact: deviceId } } 
                : { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' };

            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: videoConstraint, audio: false });
            } catch (errConstraint) {
                // Fallback jika exact constraint ditolak oleh driver virtual
                if (deviceId) {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { deviceId: deviceId },
                        audio: false
                    });
                } else {
                    throw errConstraint;
                }
            }
            activeWebcamStreams[streamKey] = stream;
            if (streamKey === 'cam1') {
                activeWebcamStream = stream;
            }

            if (video) {
                video.srcObject = stream;
                video.muted = true;
                video.playsInline = true;
                video.classList.remove('d-none');
                try {
                    await video.play();
                } catch (e) {
                    console.warn('[Video Play]:', e);
                }
            }
            if (placeholder) {
                placeholder.classList.add('d-none');
            }
            const camLabel = streamKey === 'cam2' ? 'Kamera 2 (Samping / DroidCam)' : 'Kamera 1 (Depan)';
            toast(`${camLabel} aktif. Feed video siap diverifikasi AI.`, 'success', 'Webcam Berhasil');
            return stream;
        } catch (err) {
            console.warn('[Webcam Access]:', err);
            toast('Gagal mengakses kamera: ' + err.message + ' (Pastikan izin webcam diizinkan)', 'warning', 'Izin Kamera');
        }
    }

    /**
     * Hentikan Stream Webcam (Mendukung Multi-Camera)
     */
    function stopWebcamPreview(videoElemId = 'cam1PreviewVideo', placeholderElemId = 'cam1Placeholder', streamKey = null) {
        if (streamKey && activeWebcamStreams[streamKey]) {
            activeWebcamStreams[streamKey].getTracks().forEach(track => track.stop());
            activeWebcamStreams[streamKey] = null;
        } else if (!streamKey) {
            // Hentikan semua stream aktif jika tidak ada streamKey spesifik
            Object.keys(activeWebcamStreams).forEach(k => {
                if (activeWebcamStreams[k]) {
                    activeWebcamStreams[k].getTracks().forEach(track => track.stop());
                    activeWebcamStreams[k] = null;
                }
            });
            activeWebcamStream = null;
        }

        const video = document.getElementById(videoElemId);
        const placeholder = document.getElementById(placeholderElemId);
        if (video) {
            video.srcObject = null;
            video.classList.add('d-none');
        }
        if (placeholder) {
            placeholder.classList.remove('d-none');
        }
    }

    /**
     * Tampilkan Overlay Loading Spinner
     */
    function showSpinner(title = 'Memproses Permintaan', subtitle = 'Mohon tunggu beberapa saat...') {
        const overlay = document.getElementById('appLoadingOverlay');
        const titleEl = document.getElementById('loadingTitle');
        const subtitleEl = document.getElementById('loadingSubtitle');

        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = subtitle;
        if (overlay) overlay.classList.remove('d-none');
    }

    /**
     * Sembunyikan Overlay Loading Spinner
     */
    function hideSpinner() {
        const overlay = document.getElementById('appLoadingOverlay');
        if (overlay) overlay.classList.add('d-none');
    }

    /**
     * Tombol Loading Helper (mencegah klik berkali-kali)
     */
    function btnLoading(btn, text = 'Memproses...') {
        if (!btn) return;
        if (!btn.getAttribute('data-original-html')) {
            btn.setAttribute('data-original-html', btn.innerHTML);
        }
        btn.disabled = true;
        btn.classList.add('is-loading', 'has-spinner');
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span><span>${text}</span>`;
    }

    /**
     * Kembalikan state tombol ke semula
     */
    function btnReset(btn) {
        if (!btn) return;
        const originalHtml = btn.getAttribute('data-original-html');
        if (originalHtml) {
            btn.innerHTML = originalHtml;
        }
        btn.disabled = false;
        btn.classList.remove('is-loading', 'has-spinner');
    }

    /**
     * Modal Konfirmasi Tindakan Berisiko
     */
    function confirm(options, onConfirm) {
        let message = 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
        let title = 'Konfirmasi Tindakan';
        let btnText = 'Ya, Lanjutkan';
        let type = 'danger'; // 'danger', 'warning', 'primary', 'success'

        if (typeof options === 'string') {
            message = options;
        } else if (typeof options === 'object' && options !== null) {
            message = options.message || message;
            title = options.title || title;
            btnText = options.btnText || btnText;
            type = options.type || type;
            if (typeof options.onConfirm === 'function') onConfirm = options.onConfirm;
        }

        const modalEl = document.getElementById('appConfirmModal');
        if (!modalEl || !window.bootstrap) {
            if (window.confirm(message)) {
                if (typeof onConfirm === 'function') onConfirm();
            }
            return;
        }

        const labelEl = document.getElementById('appConfirmModalLabel');
        const bodyEl = document.getElementById('appConfirmModalBody');
        const btnEl = document.getElementById('appConfirmModalBtn');
        const iconEl = document.getElementById('appConfirmModalIcon');
        const iconWrap = document.getElementById('appConfirmModalIconWrapper');

        if (labelEl) labelEl.textContent = title;
        if (bodyEl) bodyEl.textContent = message;
        if (btnEl) {
            btnEl.textContent = btnText;
            btnEl.className = `btn btn-sm btn-${type} rounded-pill px-3 fw-semibold shadow-xs`;
        }

        if (iconWrap && iconEl) {
            iconWrap.className = `rounded-circle p-2 bg-${type}-subtle text-${type} d-flex align-items-center justify-content-center`;
            iconEl.className = type === 'danger' ? 'bi bi-trash3-fill fs-5' :
                               type === 'warning' ? 'bi bi-exclamation-triangle-fill fs-5' :
                               type === 'success' ? 'bi bi-check-circle-fill fs-5' : 'bi bi-shield-exclamation fs-5';
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        btnEl.onclick = function () {
            modal.hide();
            if (typeof onConfirm === 'function') onConfirm();
        };

        modal.show();
    }

    /**
     * Form Validation Helper
     */
    function validateForm(form) {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        return true;
    }

    /**
     * Health check tester helper
     */
    async function checkHealth() {
        const consoleEl = document.getElementById('apiResponseConsole');
        if (consoleEl) {
            consoleEl.innerHTML = '<span class="text-warning"><i class="bi bi-arrow-repeat spin"></i> Menghubungi endpoint /health...</span>';
        }

        try {
            const data = await get('health');
            if (consoleEl) {
                consoleEl.innerHTML = `<span class="text-success"><i class="bi bi-check-all"></i> Respons Berhasil (200 OK):</span>\n` + JSON.stringify(data, null, 2);
            }
            toast(`Status: ${data.status.toUpperCase()} &bull; DB: ${data.database}`, 'success', 'Health Check OK');
        } catch (err) {
            if (consoleEl) {
                consoleEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> Gagal Memanggil API:</span>\n${err.message}`;
            }
            toast(`Gagal memuat status: ${err.message}`, 'danger', 'Health Check Error');
        }
    }

    /**
     * Simulasi peringatan AI Proctoring
     */
    function simulateAlert() {
        const events = [
            { msg: 'Peringatan AI: Arah pandangan mata berpaling dari layar > 3 detik.', type: 'warning' },
            { msg: 'Verifikasi Kamera 2: Posisi smartphone samping stabil merekam sudut meja.', type: 'success' },
            { msg: 'Peringatan Integritas: Terdeteksi upaya berpindah jendela browser (Tab Switch).', type: 'danger' }
        ];
        const randomEvent = events[Math.floor(Math.random() * events.length)];
        toast(randomEvent.msg, randomEvent.type, 'Simulasi AI Proctoring');
    }

    /**
     * Submit form POST secara programmatic dengan CSRF token otomatis
     */
    function submitPost(actionUrl, data = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;
        form.style.display = 'none';

        // Sisipkan CSRF Token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf_token';
        csrfInput.value = CSRF_TOKEN;
        form.appendChild(csrfInput);

        // Sisipkan data payload tambahan jika ada
        for (const [key, value] of Object.entries(data)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }

    // Listener otomatis untuk menutup mobile sidebar jika backdrop diklik
    document.addEventListener('click', function (e) {
        if (document.body.classList.contains('sidebar-open')) {
            const sidebar = document.getElementById('appSidebar');
            const toggleBtn = document.getElementById('sidebarToggleBtn');
            if (sidebar && !sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
                document.body.classList.remove('sidebar-open');
            }
        }
    });

    // Auto-protect submit forms from double-click spam
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.getAttribute('data-no-auto-loading') === 'true') return;

        const submitBtn = form.querySelector('button[type="submit"]:not([disabled])');
        if (submitBtn && form.checkValidity()) {
            const loadingText = submitBtn.getAttribute('data-loading-text') || 'Menyimpan...';
            btnLoading(submitBtn, loadingText);
        }
    });

    /**
     * Dapatkan tema aktif saat ini
     */
    function getTheme() {
        return document.documentElement.getAttribute('data-bs-theme') || 
               document.documentElement.getAttribute('data-theme') || 'light';
    }

    /**
     * Sinkronkan status visual tombol segmented control tema di seluruh halaman
     */
    function updateThemeControlButtons(theme) {
        const isDark = (theme === 'dark');

        document.querySelectorAll('.theme-btn-light, #btnThemeLight, #themeLightBtn').forEach(btn => {
            if (isDark) {
                btn.classList.remove('btn-primary', 'active', 'shadow-xs');
                btn.classList.add('btn-light', 'text-muted');
            } else {
                btn.classList.remove('btn-light', 'text-muted');
                btn.classList.add('btn-primary', 'active', 'shadow-xs');
            }
        });

        document.querySelectorAll('.theme-btn-dark, #btnThemeDark, #themeDarkBtn').forEach(btn => {
            if (isDark) {
                btn.classList.remove('btn-light', 'text-muted');
                btn.classList.add('btn-primary', 'active', 'shadow-xs');
            } else {
                btn.classList.remove('btn-primary', 'active', 'shadow-xs');
                btn.classList.add('btn-light', 'text-muted');
            }
        });
    }

    /**
     * Ubah tema tampilan aplikasi secara instan (Light Mode / Dark Mode)
     * Disederhanakan: tanpa modal/popup konfirmasi, langsung aktif 0ms
     */
    function setTheme(theme, event) {
        if (theme !== 'dark' && theme !== 'light') return;

        // Cegah dropdown tertutup saat mengklik opsi tema
        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        // 1. Terapkan langsung atribut pada <html>
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);

        // 2. Perbarui state tombol segmented control
        updateThemeControlButtons(theme);

        // 3. Simpan di localStorage dengan namespace user ID jika tersedia
        const userMeta = document.querySelector('meta[name="user-id"]');
        const userId = userMeta ? userMeta.getAttribute('content') : '';
        if (userId) {
            try { localStorage.setItem('smart_exam_theme_' + userId, theme); } catch (e) {}
        }
        try { localStorage.setItem('smart_exam_theme_global', theme); } catch (e) {}

        // Pasang cookie di sisi klien
        document.cookie = `smart_exam_theme=${theme}; path=/; max-age=${30 * 86400}; SameSite=Lax`;

        // 4. Kirim pembaruan ke server (AJAX) secara senyap di latar belakang
        post('profile/theme', { theme: theme })
            .catch(() => {
                post('api/theme', { theme: theme }).catch(() => {});
            });
    }

    /**
     * Toggle tema antara light dan dark
     */
    function toggleTheme() {
        const current = getTheme();
        setTheme(current === 'dark' ? 'light' : 'dark');
    }

    // Sinkronkan tema saat halaman selesai dimuat
    document.addEventListener('DOMContentLoaded', function () {
        const activeTheme = getTheme();
        updateThemeControlButtons(activeTheme);
    });

    return {
        url,
        get,
        post,
        submitPost,
        toast,
        toggleSidebar,
        filterTable,
        openCameraDiagnostic,
        startWebcamPreview,
        stopWebcamPreview,
        showSpinner,
        hideSpinner,
        btnLoading,
        btnReset,
        confirm,
        validateForm,
        checkHealth,
        simulateAlert,
        setTheme,
        toggleTheme,
        getTheme
    };
})();

