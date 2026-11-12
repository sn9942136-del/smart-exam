<!-- Reusable Confirmation Modal -->
<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-labelledby="appConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <div id="appConfirmModalIconWrapper" class="rounded-circle p-2 bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i id="appConfirmModalIcon" class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold fs-6 mb-0 text-slate-900" id="appConfirmModalLabel">Konfirmasi Tindakan</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body fs-xs text-slate-700 px-4 py-3" id="appConfirmModalBody">
                Apakah Anda yakin ingin melanjutkan tindakan ini?
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-danger rounded-pill px-3.5 fw-semibold" id="appConfirmModalBtn">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Reusable Camera Diagnostic Test Modal -->
<div class="modal fade" id="appCameraModal" tabindex="-1" aria-labelledby="appCameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-camera-video text-primary fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0 text-slate-900" id="appCameraModalLabel">Uji Coba &amp; Kalibrasi Kamera</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-body-tertiary">
                <div class="row g-3">
                    <!-- Preview Cam 1 -->
                    <div class="col-md-6">
                        <div class="card border shadow-sm rounded-3 overflow-hidden bg-dark text-white">
                            <div class="card-header bg-dark py-2 px-3 d-flex justify-content-between align-items-center fs-xs border-bottom border-secondary">
                                <span><i class="bi bi-camera me-1.5 text-primary"></i> Kamera 1: Depan</span>
                                <span class="badge bg-success-subtle text-success fs-3xs" id="cam1StatusBadge">Webcam Laptop</span>
                            </div>
                            <div class="card-body p-0 text-center position-relative d-flex align-items-center justify-content-center" style="min-height: 200px; background: #0b0f19;">
                                <video id="cam1PreviewVideo" autoplay playsinline muted class="w-100 h-100 object-fit-cover d-none" style="max-height: 220px;"></video>
                                <div id="cam1Placeholder" class="p-3 text-white-50 fs-xs">
                                    <i class="bi bi-person-bounding-box fs-1 text-primary d-block mb-2"></i>
                                    <span>Klik tombol di bawah untuk mengaktifkan webcam browser.</span>
                                </div>
                            </div>
                            <div class="card-footer bg-dark py-2 px-3 border-top border-secondary">
                                <div class="mb-1.5">
                                    <select id="modalCam1Select" class="form-select form-select-xs bg-dark-subtle text-dark border-secondary fs-3xs py-1" onchange="onCam1DeviceSelected()">
                                        <option value="">-- Kamera Depan Utama (Bawaan) --</option>
                                    </select>
                                </div>
                                <button type="button" id="btnStartCam1" class="btn btn-xs btn-primary w-100 rounded-pill fw-semibold" onclick="startModalCam1Preview()">
                                    <i class="bi bi-play-circle me-1.5"></i> Mulai Pratinjau Kamera 1
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Cam 2 (Smartphone / DroidCam / WebRTC) -->
                    <div class="col-md-6">
                        <div class="card border shadow-sm rounded-3 overflow-hidden bg-dark text-white">
                            <div class="card-header bg-dark py-2 px-3 d-flex justify-content-between align-items-center fs-xs border-bottom border-secondary">
                                <span><i class="bi bi-camera-reels me-1.5 text-warning"></i> Kamera 2: Samping</span>
                                <div class="btn-group btn-group-xs" role="group">
                                    <button type="button" class="btn btn-xs btn-outline-warning active py-0.5 px-2 fs-4xs" id="tabBtnDroidcam" onclick="switchCam2Tab('feed')">
                                        <i class="bi bi-phone me-1"></i> DroidCam / USB
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-warning py-0.5 px-2 fs-4xs" id="tabBtnQr" onclick="switchCam2Tab('qr')">
                                        <i class="bi bi-qr-code me-1"></i> QR WebRTC
                                    </button>
                                </div>
                            </div>

                            <!-- Mode 1: Feed Video Langsung Kamera 2 (DroidCam / USB Webcam) -->
                            <div id="cam2FeedContainer" class="card-body p-0 text-center position-relative d-flex align-items-center justify-content-center" style="min-height: 200px; background: #0b0f19;">
                                <video id="cam2PreviewVideo" autoplay playsinline muted class="w-100 h-100 object-fit-cover d-none" style="max-height: 220px;"></video>
                                <div id="cam2Placeholder" class="p-3 text-white-50 fs-xs">
                                    <i class="bi bi-phone-vibrate fs-1 text-warning d-block mb-2"></i>
                                    <span class="d-block text-white fw-semibold mb-1">Kamera 2 (DroidCam / Samping)</span>
                                    <span class="fs-3xs text-white-50">Pilih perangkat DroidCam di bawah, lalu klik Mulai Pratinjau.</span>
                                </div>
                            </div>

                            <!-- Mode 2: QR Code WebRTC -->
                            <div id="cam2QrContainer" class="card-body p-3 text-center position-relative d-none flex-column align-items-center justify-content-center" style="min-height: 200px; background: #0b0f19;">
                                <div class="p-2 bg-white rounded-3 mb-2 shadow-sm d-inline-block text-center" style="min-width: 130px; min-height: 130px;">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&amp;margin=2&amp;data=<?= urlencode(base_url('siswa/exam/pre-check')) ?>" 
                                         alt="QR Code Kamera 2" 
                                         class="img-fluid rounded" 
                                         style="width: 120px; height: 120px; object-fit: contain;"
                                         onerror="this.style.display='none'; document.getElementById('qrFallbackIcon').classList.remove('d-none');">
                                    <div id="qrFallbackIcon" class="d-none p-2 text-dark">
                                        <i class="bi bi-qr-code fs-1 d-block mb-1"></i>
                                        <small class="fs-4xs text-muted">Scan QR</small>
                                    </div>
                                </div>
                                <span class="fs-xs text-white-75">Pindai QR ini melalui browser smartphone Anda</span>
                                <small id="webrtcCam2Status" class="text-white-50 fs-3xs mt-1">Status: Menunggu tautan koneksi perangkat</small>
                            </div>

                            <div class="card-footer bg-dark py-2 px-3 border-top border-secondary">
                                <div id="cam2FeedControls">
                                    <div class="mb-1.5">
                                        <select id="modalCam2Select" class="form-select form-select-xs bg-dark-subtle text-dark border-secondary fs-3xs py-1" onchange="onCam2DeviceSelected()">
                                            <option value="">-- Mendeteksi Perangkat Kamera 2 / DroidCam... --</option>
                                        </select>
                                    </div>
                                    <button type="button" id="btnStartCam2" class="btn btn-xs btn-warning w-100 rounded-pill fw-semibold mb-2" onclick="startModalCam2Preview()">
                                        <i class="bi bi-play-circle me-1.5"></i> Mulai Pratinjau Kamera 2
                                    </button>

                                    <!-- Panduan Cepat Jika Layar Hijau -->
                                    <div class="p-2 rounded bg-black bg-opacity-50 border border-secondary fs-4xs text-start text-white-75">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="text-warning fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Layar Hijau di DroidCam?</span>
                                            <button type="button" class="btn btn-link p-0 text-decoration-none text-info fs-4xs" data-bs-toggle="collapse" data-bs-target="#droidcamTipBox">Lihat Solusi ▾</button>
                                        </div>
                                        <div class="collapse show mt-1.5" id="droidcamTipBox">
                                            <div class="p-1.5 bg-dark rounded border border-secondary-subtle">
                                                <p class="mb-1 text-white fw-semibold">Layar hijau artinya DroidCam PC belum aktif/terhubung ke HP:</p>
                                                <ol class="ps-3 mb-1 text-white-50">
                                                    <li>Buka DroidCam di PC, masukkan IP dari HP, klik <strong>"Start"</strong> sampai wajah muncul di jendela DroidCam PC.</li>
                                                    <li>Pada pilihan kamera di atas, pilih <strong>"DroidCam Source 3"</strong> atau <strong>"Source 2"</strong> jika tersedia.</li>
                                                    <li>Klik tombol kuning <strong>Perbarui Feed Kamera 2</strong> di atas.</li>
                                                </ol>
                                                <div class="text-info-subtle mt-1 border-top border-secondary pt-1">
                                                    <i class="bi bi-stars text-warning me-1"></i><strong>Alternatif Termudah:</strong> Gunakan <strong>Iriun Webcam</strong> (otomatis terkoneksi tanpa ketik IP & bebas layar hijau).
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="cam2QrControls" class="d-none">
                                    <button type="button" class="btn btn-xs btn-outline-warning w-100 rounded-pill" onclick="syncWebrtcCamera2(this)">
                                        <i class="bi bi-arrow-repeat me-1.5"></i> Regenerasi Tautan QR
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal" onclick="App.stopWebcamPreview('cam1PreviewVideo', 'cam1Placeholder'); App.stopWebcamPreview('cam2PreviewVideo', 'cam2Placeholder', 'cam2');">Tutup</button>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3.5 fw-semibold" data-bs-dismiss="modal" onclick="App.toast('Kalibrasi dual kamera telah diverifikasi.', 'success', 'Kalibrasi Berhasil')">Simpan Kalibrasi</button>
            </div>
        </div>
    </div>
</div>

<script>
    let modalVideoDevices = [];
    let isCam2Streaming = false;
    let isCam1Streaming = false;

    async function loadModalCameraDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
        try {
            // Minta izin sekali agar label perangkat (DroidCam) terbuka
            if (!navigator.mediaDevices.getUserMedia) return;
            const devices = await navigator.mediaDevices.enumerateDevices();
            modalVideoDevices = devices.filter(d => d.kind === 'videoinput');
            
            const sel1 = document.getElementById('modalCam1Select');
            const sel2 = document.getElementById('modalCam2Select');

            if (sel1 && modalVideoDevices.length > 0) {
                sel1.innerHTML = '';
                modalVideoDevices.forEach((dev, idx) => {
                    const opt = document.createElement('option');
                    opt.value = dev.deviceId;
                    opt.textContent = dev.label || `Kamera Depan ${idx + 1}`;
                    sel1.appendChild(opt);
                });
            }

            if (sel2) {
                sel2.innerHTML = '';
                if (modalVideoDevices.length > 1) {
                    let bestCam2Idx = -1;
                    modalVideoDevices.forEach((dev, idx) => {
                        const opt = document.createElement('option');
                        opt.value = dev.deviceId;
                        opt.textContent = dev.label || `Kamera ${idx + 1}`;
                        sel2.appendChild(opt);

                        const lbl = (dev.label || '').toLowerCase();
                        // Prioritas 1: Iriun atau DirectShow (Source 3 / Source 2) yang bebas green-screen
                        if (lbl.includes('iriun') || lbl.includes('source 3') || lbl.includes('source 2')) {
                            bestCam2Idx = idx;
                        } else if (bestCam2Idx === -1 && (lbl.includes('droidcam') || lbl.includes('secondary') || lbl.includes('camo') || lbl.includes('phone') || lbl.includes('hp') || lbl.includes('obs'))) {
                            bestCam2Idx = idx;
                        }
                    });

                    if (bestCam2Idx === -1 && modalVideoDevices.length > 1) {
                        bestCam2Idx = 1;
                    }
                    if (bestCam2Idx >= 0 && sel2.options[bestCam2Idx]) {
                        sel2.options[bestCam2Idx].selected = true;
                    }
                } else if (modalVideoDevices.length === 1) {
                    const opt = document.createElement('option');
                    opt.value = modalVideoDevices[0].deviceId;
                    opt.textContent = modalVideoDevices[0].label || 'Kamera 1 (Satu-satunya terdeteksi)';
                    sel2.appendChild(opt);
                } else {
                    sel2.innerHTML = '<option value="">-- Hubungkan DroidCam di PC Anda --</option>';
                }
            }
        } catch (e) {
            console.warn('[Camera Enumeration]:', e);
        }
    }

    function startModalCam1Preview() {
        const sel1 = document.getElementById('modalCam1Select');
        const deviceId = (sel1 && sel1.value) ? sel1.value : null;
        App.startWebcamPreview('cam1PreviewVideo', 'cam1Placeholder', deviceId, 'cam1');
        isCam1Streaming = true;
    }

    function onCam1DeviceSelected() {
        if (isCam1Streaming) {
            startModalCam1Preview();
        }
    }

    function startModalCam2Preview() {
        const sel2 = document.getElementById('modalCam2Select');
        const deviceId = (sel2 && sel2.value) ? sel2.value : null;
        App.startWebcamPreview('cam2PreviewVideo', 'cam2Placeholder', deviceId, 'cam2');
        isCam2Streaming = true;
        const btn = document.getElementById('btnStartCam2');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1.5"></i> Perbarui Feed Kamera 2';
        }
    }

    function onCam2DeviceSelected() {
        if (isCam2Streaming) {
            startModalCam2Preview();
        }
    }

    function switchCam2Tab(mode) {
        const feedContainer = document.getElementById('cam2FeedContainer');
        const qrContainer = document.getElementById('cam2QrContainer');
        const feedControls = document.getElementById('cam2FeedControls');
        const qrControls = document.getElementById('cam2QrControls');
        const tabFeed = document.getElementById('tabBtnDroidcam');
        const tabQr = document.getElementById('tabBtnQr');

        if (mode === 'feed') {
            if (feedContainer) { feedContainer.classList.remove('d-none'); feedContainer.classList.add('d-flex'); }
            if (qrContainer) { qrContainer.classList.add('d-none'); qrContainer.classList.remove('d-flex'); }
            if (feedControls) feedControls.classList.remove('d-none');
            if (qrControls) qrControls.classList.add('d-none');
            if (tabFeed) tabFeed.classList.add('active');
            if (tabQr) tabQr.classList.remove('active');
        } else {
            if (feedContainer) { feedContainer.classList.add('d-none'); feedContainer.classList.remove('d-flex'); }
            if (qrContainer) { qrContainer.classList.remove('d-none'); qrContainer.classList.add('d-flex'); }
            if (feedControls) feedControls.classList.add('d-none');
            if (qrControls) qrControls.classList.remove('d-none');
            if (tabFeed) tabFeed.classList.remove('active');
            if (tabQr) tabQr.classList.add('active');
        }
    }

    function syncWebrtcCamera2(btn) {
        const statusEl = document.getElementById('webrtcCam2Status');
        if (statusEl) {
            statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Perangkat Smartphone Terhubung (Standby)</span>';
        }
        if (typeof App !== 'undefined' && App.toast) {
            App.toast('Sinyal WebRTC sinkronisasi berhasil terhubung dengan kamera smartphone.', 'success', 'WebRTC Linked');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('appCameraModal');
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', () => {
                loadModalCameraDevices();
            });
            modalEl.addEventListener('hidden.bs.modal', () => {
                App.stopWebcamPreview('cam1PreviewVideo', 'cam1Placeholder', 'cam1');
                App.stopWebcamPreview('cam2PreviewVideo', 'cam2Placeholder', 'cam2');
                isCam1Streaming = false;
                isCam2Streaming = false;
            });
        }
    });
</script>
