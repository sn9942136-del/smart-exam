<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row g-4 mb-4">
    <!-- Header Page Info -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs">
                            <i class="bi bi-camera-video me-1"></i> WebRTC MediaDevices
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs" id="secureContextBadge">
                            <i class="bi bi-shield-check me-1"></i> Secure Context (HTTPS/Localhost)
                        </span>
                        <?php if (!empty($exam)): ?>
                            <span class="badge bg-dark text-white fs-3xs">
                                <?= e($exam['title'] ?? 'Ujian') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Kalibrasi & Diagnostik Dual Kamera AI</h4>
                    <p class="text-muted fs-xs mb-0">
                        Pastikan kamera depan (wajah) dan kamera sekunder (sudut pandang ruangan) berfungsi jernih, stabil, dan memiliki pencahayaan memadai sebelum memulai ujian.
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#mobileCamGuideModal">
                        <i class="bi bi-phone me-1"></i> Panduan Kamera HP
                    </button>
                    <?php if (!empty($exam)): ?>
                        <a href="<?= base_url('siswa/exams/show/' . $exam['id']) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Ujian
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CameraPermissionComponent: Status Izin & Peringatan Privasi -->
    <div class="col-12" id="permissionComponentArea">
        <div class="alert alert-light border shadow-xs rounded-4 p-3.5 mb-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-shield-lock-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Jaminan Keamanan & Privasi Peserta</h6>
                    <small class="text-muted fs-3xs d-block">
                        Akses feed kamera hanya digunakan untuk verifikasi integritas ujian secara lokal/terenkripsi. Sistem tidak melakukan perekaman tanpa persetujuan eksplisit.
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span id="permissionStatusBadge" class="badge bg-secondary text-white px-3 py-1.5 rounded-pill fs-3xs fw-bold">
                    <i class="bi bi-hourglass-split me-1"></i> Memeriksa Izin...
                </span>
                <button type="button" id="btnRequestPermission" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                    <i class="bi bi-camera me-1"></i> Berikan Izin Kamera
                </button>
            </div>
        </div>
    </div>

    <!-- CameraSelectorComponent: Dropdown Pemilihan Perangkat -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0 fs-xs">
                    <i class="bi bi-sliders text-primary me-1"></i> Pilih Perangkat Kamera (Device Selector)
                </h6>
                <button type="button" id="btnRefreshDevices" class="btn btn-outline-secondary btn-xs rounded-pill px-2.5">
                    <i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang Perangkat
                </button>
            </div>

            <div class="row g-3">
                <!-- Select Kamera 1: Depan/Wajah -->
                <div class="col-md-6">
                    <label class="form-label fs-xs fw-semibold text-dark mb-1">
                        <i class="bi bi-person-video me-1 text-primary"></i> Kamera 1: Depan (Wajah & Eye-Tracking) <span class="text-danger">*</span>
                    </label>
                    <select id="primaryCameraSelect" class="form-select fs-xs rounded-3">
                        <option value="">-- Menunggu Deteksi Perangkat Kamera --</option>
                    </select>
                    <div class="form-text fs-3xs text-muted">Disarankan menggunakan webcam bawaan laptop atau webcam utama.</div>
                </div>

                <!-- Select Kamera 2: Sekunder/Samping -->
                <div class="col-md-6">
                    <label class="form-label fs-xs fw-semibold text-dark mb-1">
                        <i class="bi bi-camera-reels me-1 text-info"></i> Kamera 2: Sekunder (Samping / Sudut Ruangan)
                    </label>
                    <select id="secondaryCameraSelect" class="form-select fs-xs rounded-3">
                        <option value="">-- Menunggu Deteksi Perangkat Kamera --</option>
                    </select>
                    <div class="form-text fs-3xs text-muted">Gunakan webcam USB eksternal atau kamera smartphone sebagai kamera sudut samping.</div>
                </div>
            </div>

            <!-- Single Camera Fallback Notice -->
            <div id="singleCameraNotice" class="alert alert-warning border-0 rounded-3 p-3 fs-3xs text-dark mt-3 mb-0 d-none">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-6 text-warning flex-shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="d-block text-dark">Hanya 1 Perangkat Kamera Terdeteksi:</strong>
                        Jika ujian ini mewajibkan <em>Dual Camera Proctoring</em>, silakan sambungkan kamera kedua (misal: Webcam USB atau gunakan aplikasi DroidCam/Iriun Webcam di HP).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DualCameraPreview: Live Video Feeds -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
            <h6 class="fw-bold text-dark mb-3 fs-xs">
                <i class="bi bi-display text-primary me-1"></i> Live Dual Camera Feeds (Pratinjau Langsung)
            </h6>

            <div class="row g-3">
                <!-- Feed 1: Front Camera -->
                <div class="col-md-6">
                    <div class="position-relative bg-dark rounded-4 overflow-hidden border shadow-xs text-center d-flex align-items-center justify-content-center" style="min-height: 240px; aspect-ratio: 4/3;">
                        <!-- Live Video Element -->
                        <video id="cam1PreviewVideo" class="w-100 h-100 object-fit-cover d-none" autoplay playsinline muted></video>

                        <!-- Placeholder off state -->
                        <div id="cam1Placeholder" class="p-4 text-white-50">
                            <i class="bi bi-camera-video-off fs-1 d-block mb-2 text-secondary"></i>
                            <span class="fs-xs fw-semibold text-light d-block">Kamera Depan Nonaktif</span>
                            <small class="fs-3xs text-white-50">Pilih perangkat di atas untuk memulai</small>
                        </div>

                        <!-- Overlay Header -->
                        <div class="position-absolute top-0 start-0 w-100 p-2 d-flex justify-content-between align-items-center bg-gradient-dark">
                            <span class="badge bg-primary text-white fs-3xs fw-bold px-2 py-1 shadow-xs">
                                <i class="bi bi-person-fill me-0.5"></i> CAM 1: DEPAN
                            </span>
                            <span id="cam1StatusBadge" class="badge bg-secondary fs-3xs">Nonaktif</span>
                        </div>

                        <!-- Overlay Footer -->
                        <div class="position-absolute bottom-0 start-0 w-100 p-2 d-flex justify-content-between align-items-center bg-gradient-dark-bottom">
                            <span id="cam1Resolution" class="fs-3xs text-white-90 font-monospace">-</span>
                            <span class="badge bg-dark-50 text-white-90 fs-3xs"><i class="bi bi-eye-fill text-info me-1"></i>Face Angle</span>
                        </div>
                    </div>
                </div>

                <!-- Feed 2: Secondary Camera -->
                <div class="col-md-6">
                    <div class="position-relative bg-dark rounded-4 overflow-hidden border shadow-xs text-center d-flex align-items-center justify-content-center" style="min-height: 240px; aspect-ratio: 4/3;">
                        <!-- Live Video Element -->
                        <video id="cam2PreviewVideo" class="w-100 h-100 object-fit-cover d-none" autoplay playsinline muted></video>

                        <!-- Placeholder off state -->
                        <div id="cam2Placeholder" class="p-4 text-white-50">
                            <i class="bi bi-camera-video-off fs-1 d-block mb-2 text-secondary"></i>
                            <span class="fs-xs fw-semibold text-light d-block">Kamera Samping Nonaktif</span>
                            <small class="fs-3xs text-white-50">Opsional / Wajib jika ada aturan dual cam</small>
                        </div>

                        <!-- Overlay Header -->
                        <div class="position-absolute top-0 start-0 w-100 p-2 d-flex justify-content-between align-items-center bg-gradient-dark">
                            <span class="badge bg-info text-dark fs-3xs fw-bold px-2 py-1 shadow-xs">
                                <i class="bi bi-camera-reels-fill me-0.5"></i> CAM 2: SAMPING
                            </span>
                            <span id="cam2StatusBadge" class="badge bg-secondary fs-3xs">Nonaktif</span>
                        </div>

                        <!-- Overlay Footer -->
                        <div class="position-absolute bottom-0 start-0 w-100 p-2 d-flex justify-content-between align-items-center bg-gradient-dark-bottom">
                            <span id="cam2Resolution" class="fs-3xs text-white-90 font-monospace">-</span>
                            <span class="badge bg-dark-50 text-white-90 fs-3xs"><i class="bi bi-bounding-box text-warning me-1"></i>Room Angle</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CameraHealthCheck: Diagnostik Kecerahan, Resolusi, & Kesiapan -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
            <div>
                <h6 class="fw-bold text-dark mb-3 fs-xs">
                    <i class="bi bi-activity text-success me-1"></i> Hasil Kalibrasi & Diagnostik
                </h6>

                <!-- 1. Meter Pencahayaan (Brightness Analyzer) -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="fw-bold text-dark fs-3xs">Pencahayaan Ruangan (Luminance):</small>
                        <span id="brightnessScoreValue" class="fs-3xs font-monospace fw-bold text-muted">0%</span>
                    </div>
                    <div class="progress mb-2" style="height: 6px;">
                        <div id="brightnessProgressBar" class="progress-bar bg-secondary" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <small id="lightingStatusText" class="text-muted fs-3xs d-block">
                        Menunggu feed video kamera utama aktif...
                    </small>
                </div>

                <!-- 2. Resolusi & Aliran Frame -->
                <div class="p-3 bg-light rounded-3 border mb-3 fs-3xs">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Resolusi Kamera 1:</span>
                        <strong id="cam1ResText" class="text-dark">-</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Resolusi Kamera 2:</span>
                        <strong id="cam2ResText" class="text-dark">-</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Aliran Video (Stream):</span>
                        <strong id="streamFlowStatusText" class="text-secondary">Standby</strong>
                    </div>
                </div>

                <!-- 3. Checklist Kesiapan Masuk Ujian -->
                <h6 class="fw-bold text-dark mb-2 fs-3xs text-uppercase tracking-wider">Checklist Kesiapan:</h6>
                <div class="d-flex flex-column gap-1.5 fs-3xs text-muted mb-4">
                    <div class="d-flex align-items-center gap-2" id="chkPermission">
                        <i class="bi bi-circle text-muted"></i>
                        <span>Izin browser untuk WebRTC aktif</span>
                    </div>
                    <div class="d-flex align-items-center gap-2" id="chkCam1">
                        <i class="bi bi-circle text-muted"></i>
                        <span>Kamera 1 (Depan) terhubung dan jernih</span>
                    </div>
                    <div class="d-flex align-items-center gap-2" id="chkCam2">
                        <i class="bi bi-circle text-muted"></i>
                        <span>Kamera 2 (Samping) terhubung</span>
                    </div>
                    <div class="d-flex align-items-center gap-2" id="chkLighting">
                        <i class="bi bi-circle text-muted"></i>
                        <span>Pencahayaan wajah memadai</span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="button" id="btnSaveAndProceed" class="btn btn-primary-gradient rounded-3 py-2.5 fw-bold shadow-sm" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Simpan & Lanjutkan ke Ujian
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Panduan Kamera Smartphone -->
<div class="modal fade" id="mobileCamGuideModal" tabindex="-1" aria-labelledby="mobileCamGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-phone text-primary fs-5"></i>
                    <h6 class="modal-title fw-bold fs-6 mb-0 text-dark" id="mobileCamGuideModalLabel">
                        Menghubungkan Kamera Smartphone
                    </h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 fs-xs text-muted">
                <p class="mb-3 text-dark">
                    Jika komputer atau laptop Anda tidak memiliki webcam kedua, Anda dapat menggunakan smartphone sebagai kamera sekunder dengan cara berikut:
                </p>
                <ol class="ps-3 mb-0 d-flex flex-column gap-2">
                    <li>Install aplikasi webcam virtual di PC dan Smartphone (misal: <strong>DroidCam</strong>, <strong>Iriun Webcam</strong>, atau <strong>Camo</strong>).</li>
                    <li>Hubungkan HP dan Laptop ke jaringan Wi-Fi yang sama atau gunakan kabel data USB.</li>
                    <li>Buka aplikasi di HP dan PC hingga terhubung.</li>
                    <li>Klik tombol <strong>"Pindai Ulang Perangkat"</strong> di halaman ini, lalu pilih nama kamera virtual tersebut pada dropdown <em>Kamera 2</em>.</li>
                    <li>Posisikan HP di samping/belakang meja agar memperlihatkan tangan, layar laptop, dan area sekitar Anda.</li>
                </ol>
            </div>
            <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Load Modular JavaScript WebRTC -->
<script src="<?= asset('js/camera-permission.js') ?>"></script>
<script src="<?= asset('js/camera-reconnect.js') ?>"></script>
<script src="<?= asset('js/camera-manager.js') ?>"></script>

<!-- Inisialisasi Halaman Pre-Check -->
<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const isDualCamRequired = <?= (!empty($exam['is_dual_cam_required']) || !empty($rules['is_dual_cam_required'])) ? 'true' : 'false' ?>;
        const examId = <?= !empty($exam['id']) ? (int)$exam['id'] : 'null' ?>;
        const saveConfigUrl = <?= json_encode(base_url('siswa/exam/pre-check/save-config')) ?>;
        const csrfToken = <?= json_encode(csrf_token()) ?>;

        const permissionBadge = document.getElementById('permissionStatusBadge');
        const btnRequestPerm = document.getElementById('btnRequestPermission');
        const btnRefresh = document.getElementById('btnRefreshDevices');
        const primarySelect = document.getElementById('primaryCameraSelect');
        const secondarySelect = document.getElementById('secondaryCameraSelect');
        const singleCamNotice = document.getElementById('singleCameraNotice');
        const btnSaveProceed = document.getElementById('btnSaveAndProceed');

        // Initial state for checklist
        let state = {
            hasPermission: false,
            isPrimaryActive: false,
            isSecondaryActive: false,
            isLightingOk: false
        };

        const cameraManager = new CameraManager({
            primaryVideoId: 'cam1PreviewVideo',
            secondaryVideoId: 'cam2PreviewVideo',
            onDevicesFound: (devices) => {
                populateDeviceDropdowns(devices);
            },
            onStreamStarted: (key, stream, device) => {
                updateStreamUI(key, true, device);
                validateChecklist();
            },
            onStreamStopped: (key) => {
                updateStreamUI(key, false, null);
                validateChecklist();
            },
            onHealthUpdate: (key, health) => {
                if (key === 'primary') {
                    updateLightingUI(health.brightness);
                    document.getElementById('cam1ResText').textContent = health.resolution.label;
                    document.getElementById('cam1Resolution').textContent = `${health.resolution.width}x${health.resolution.height}`;
                } else if (key === 'secondary') {
                    document.getElementById('cam2ResText').textContent = health.resolution.label;
                    document.getElementById('cam2Resolution').textContent = `${health.resolution.width}x${health.resolution.height}`;
                }

                const flowText = document.getElementById('streamFlowStatusText');
                if (flowText) {
                    flowText.textContent = health.isActive ? 'Lancar (Active)' : 'Freeze / Lambat';
                    flowText.className = health.isActive ? 'text-success fw-bold' : 'text-warning fw-bold';
                }
                validateChecklist();
            },
            onStatusChange: (key, status, msg) => {
                const badgeId = (key === 'primary') ? 'cam1StatusBadge' : 'cam2StatusBadge';
                const badge = document.getElementById(badgeId);
                if (badge) {
                    badge.textContent = status.toUpperCase();
                    if (status === 'active') badge.className = 'badge bg-success';
                    else if (status === 'connecting' || status === 'reconnecting') badge.className = 'badge bg-warning text-dark';
                    else badge.className = 'badge bg-secondary';
                }
            },
            onError: (key, err) => {
                if (window.App && typeof window.App.toast === 'function') {
                    window.App.toast(err.message || 'Kendala kamera', 'warning', err.title || 'Kamera');
                }
            }
        });

        // 1. Initial Permission & Device Load
        async function runInitialCheck() {
            permissionBadge.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memeriksa...';
            const initRes = await cameraManager.init();

            if (initRes.success) {
                state.hasPermission = true;
                permissionBadge.className = 'badge bg-success text-white px-3 py-1.5 rounded-pill fs-3xs fw-bold';
                permissionBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Izin Diberikan';
                btnRequestPerm.classList.add('d-none');
                updateChecklistIcon('chkPermission', true);

                // Auto-start primary camera if devices exist
                if (initRes.devices.length > 0) {
                    primarySelect.value = initRes.devices[0].deviceId;
                    await cameraManager.startCamera('primary', initRes.devices[0].deviceId);
                }

                // If second device exists, auto-start secondary camera
                if (initRes.devices.length > 1) {
                    secondarySelect.value = initRes.devices[1].deviceId;
                    await cameraManager.startCamera('secondary', initRes.devices[1].deviceId);
                }
            } else {
                permissionBadge.className = 'badge bg-danger text-white px-3 py-1.5 rounded-pill fs-3xs fw-bold';
                permissionBadge.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Butuh Izin';
                btnRequestPerm.classList.remove('d-none');
                updateChecklistIcon('chkPermission', false);
            }
        }

        function populateDeviceDropdowns(devices) {
            primarySelect.innerHTML = '<option value="">-- Pilih Kamera Utama (Depan) --</option>';
            secondarySelect.innerHTML = '<option value="">-- Nonaktif / Pilih Kamera Sekunder --</option>';

            if (devices.length === 0) {
                primarySelect.innerHTML = '<option value="">(Tidak ada kamera terdeteksi)</option>';
                secondarySelect.innerHTML = '<option value="">(Tidak ada kamera terdeteksi)</option>';
                singleCamNotice.classList.remove('d-none');
                return;
            }

            if (devices.length === 1) {
                singleCamNotice.classList.remove('d-none');
            } else {
                singleCamNotice.classList.add('d-none');
            }

            devices.forEach((d, i) => {
                const opt1 = document.createElement('option');
                opt1.value = d.deviceId;
                opt1.textContent = `${d.label} (Device #${i + 1})`;
                primarySelect.appendChild(opt1);

                const opt2 = document.createElement('option');
                opt2.value = d.deviceId;
                opt2.textContent = `${d.label} (Device #${i + 1})`;
                secondarySelect.appendChild(opt2);
            });
        }

        function updateStreamUI(key, isActive, device) {
            const videoId = (key === 'primary') ? 'cam1PreviewVideo' : 'cam2PreviewVideo';
            const placeholderId = (key === 'primary') ? 'cam1Placeholder' : 'cam2Placeholder';
            const videoEl = document.getElementById(videoId);
            const placeholderEl = document.getElementById(placeholderId);

            if (isActive) {
                videoEl?.classList.remove('d-none');
                placeholderEl?.classList.add('d-none');
                if (key === 'primary') state.isPrimaryActive = true;
                if (key === 'secondary') state.isSecondaryActive = true;
            } else {
                videoEl?.classList.add('d-none');
                placeholderEl?.classList.remove('d-none');
                if (key === 'primary') state.isPrimaryActive = false;
                if (key === 'secondary') state.isSecondaryActive = false;
            }

            updateChecklistIcon(key === 'primary' ? 'chkCam1' : 'chkCam2', isActive);
        }

        function updateLightingUI(brightness) {
            const bar = document.getElementById('brightnessProgressBar');
            const val = document.getElementById('brightnessScoreValue');
            const txt = document.getElementById('lightingStatusText');

            if (bar && val && txt) {
                val.textContent = `${brightness.percentage}%`;
                bar.style.width = `${brightness.percentage}%`;

                if (brightness.status === 'good') {
                    bar.className = 'progress-bar bg-success';
                    txt.className = 'text-success fs-3xs d-block fw-semibold';
                    txt.textContent = brightness.message;
                    state.isLightingOk = true;
                } else if (brightness.status === 'too_dark') {
                    bar.className = 'progress-bar bg-danger';
                    txt.className = 'text-danger fs-3xs d-block fw-semibold';
                    txt.textContent = brightness.message;
                    state.isLightingOk = false;
                } else {
                    bar.className = 'progress-bar bg-warning';
                    txt.className = 'text-warning-emphasis fs-3xs d-block fw-semibold';
                    txt.textContent = brightness.message;
                    state.isLightingOk = true;
                }
            }

            updateChecklistIcon('chkLighting', state.isLightingOk);
        }

        function updateChecklistIcon(elemId, isPassed) {
            const el = document.getElementById(elemId);
            if (!el) return;
            const icon = el.querySelector('i');
            if (icon) {
                if (isPassed) {
                    icon.className = 'bi bi-check-circle-fill text-success';
                    el.classList.add('text-dark', 'fw-semibold');
                    el.classList.remove('text-muted');
                } else {
                    icon.className = 'bi bi-circle text-muted';
                    el.classList.remove('text-dark', 'fw-semibold');
                    el.classList.add('text-muted');
                }
            }
        }

        function validateChecklist() {
            const isReady = state.isPrimaryActive && (isDualCamRequired ? state.isSecondaryActive : true);
            btnSaveProceed.disabled = !isReady;
        }

        // Event Handlers
        btnRequestPerm?.addEventListener('click', () => runInitialCheck());
        btnRefresh?.addEventListener('click', async () => {
            btnRefresh.disabled = true;
            btnRefresh.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            await cameraManager.refreshDevices();
            btnRefresh.disabled = false;
            btnRefresh.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Pindai Ulang Perangkat';
        });

        primarySelect?.addEventListener('change', (e) => {
            const devId = e.target.value;
            if (devId) {
                cameraManager.startCamera('primary', devId);
            } else {
                cameraManager.stopCamera('primary');
            }
        });

        secondarySelect?.addEventListener('change', (e) => {
            const devId = e.target.value;
            if (devId) {
                cameraManager.startCamera('secondary', devId);
            } else {
                cameraManager.stopCamera('secondary');
            }
        });

        btnSaveProceed?.addEventListener('click', async () => {
            btnSaveProceed.disabled = true;
            btnSaveProceed.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

            try {
                const res = await fetch(saveConfigUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        primary_device_id: primarySelect.value,
                        secondary_device_id: secondarySelect.value
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    if (examId) {
                        window.location.href = <?= json_encode(base_url('siswa/exams/show/')) ?> + examId;
                    } else {
                        window.location.href = <?= json_encode(base_url('siswa/exams')) ?>;
                    }
                } else {
                    alert('Gagal menyimpan konfigurasi kamera: ' + (data.error || 'Unknown error'));
                    btnSaveProceed.disabled = false;
                    btnSaveProceed.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan & Lanjutkan ke Ujian';
                }
            } catch (err) {
                alert('Terjadi kesalahan jaringan: ' + err.message);
                btnSaveProceed.disabled = false;
                btnSaveProceed.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan & Lanjutkan ke Ujian';
            }
        });

        // Run on load
        runInitialCheck();
    });
</script>
