<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<?php
// Normalisasi Tab Aktif (Hanya 6 Tab Esensial)
$tabs = [
    'general'      => ['title' => 'Umum & Sekolah',    'icon' => 'bi-buildings'],
    'exam'         => ['title' => 'Standar Ujian CBT', 'icon' => 'bi-journal-check'],
    'proctoring'   => ['title' => 'Dual Kamera & AI',  'icon' => 'bi-camera-video'],
    'ai'           => ['title' => 'Model AI & LLM',    'icon' => 'bi-robot'],
    'security'     => ['title' => 'Keamanan & Sesi',   'icon' => 'bi-shield-check'],
    'notification' => ['title' => 'Notifikasi Gateway','icon' => 'bi-bell'],
];

$curTab = $activeTab ?? 'general';
if ($curTab === 'maintenance' || $curTab === 'diagnostics') {
    $curTab = 'security';
} elseif ($curTab === 'storage' || !array_key_exists($curTab, $tabs)) {
    $curTab = 'general';
}
?>

<!-- Page Header & Action Buttons -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-sliders text-primary"></i>
            <span>Konfigurasi Sistem Terpadu</span>
            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fs-3xs px-2.5 py-1">Smart Exam Engine</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Kelola identitas sekolah, standar ujian CBT, pengawasan kamera AI, model LLM, keamanan, dan notifikasi gateway.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('admin/system-configuration/export') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs" title="Unduh berkas JSON cadangan">
            <i class="bi bi-download me-1"></i> Ekspor
        </a>
        <button type="button" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#importConfigModal">
            <i class="bi bi-upload me-1"></i> Impor
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-xs" onclick="App.confirm('Apakah Anda yakin ingin memulihkan konfigurasi sistem ke baseline default rekomendasi?', () => App.submitPost('<?= base_url('admin/system-configuration/reset') ?>'))">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
        </button>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs" onclick="document.getElementById('systemConfigForm').submit()">
            <i class="bi bi-check2-circle me-1"></i> Simpan
        </button>
    </div>
</div>

<!-- Main Form Wrapper -->
<form action="<?= base_url('admin/system-configuration/update') ?>" method="POST" id="systemConfigForm" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="current_tab" id="currentTabInput" value="<?= e($curTab) ?>">

    <!-- Nav Tabs Navigation (6 Tab Esensial) -->
    <div class="card border-0 shadow-xs rounded-4 bg-white mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom border-slate-100 p-2">
            <ul class="nav nav-pills flex-nowrap overflow-x-auto gap-1 text-nowrap" id="configTabs" role="tablist">
                <?php foreach ($tabs as $gKey => $meta): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3 fs-xs py-2 px-3 d-flex align-items-center gap-2 <?= ($curTab === $gKey) ? 'active' : '' ?>" 
                                id="tab-btn-<?= $gKey ?>" 
                                data-bs-toggle="pill" 
                                data-bs-target="#tab-pane-<?= $gKey ?>" 
                                type="button" 
                                role="tab" 
                                onclick="document.getElementById('currentTabInput').value = '<?= $gKey ?>'">
                            <i class="bi <?= $meta['icon'] ?>"></i>
                            <span><?= e($meta['title']) ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="configTabContent">

        <!-- ========================================================================= -->
        <!-- TAB 1: IDENTITAS & UMUM (GENERAL)                                         -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= ($curTab === 'general') ? 'show active' : '' ?>" id="tab-pane-general" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-xs">
                            <i class="bi bi-buildings text-primary"></i>
                            <span>Identitas Sekolah & Aplikasi</span>
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Nama Aplikasi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-slate-200 fs-xs rounded-3" name="configs[app_name]" value="<?= e(system_setting('app_name', 'Smart Exam')) ?>" required>
                                <div class="form-text fs-3xs text-muted">Ditampilkan pada navbar dan header CBT.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Nama Resmi Sekolah / Institusi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-slate-200 fs-xs rounded-3" name="configs[institution_name]" value="<?= e(system_setting('institution_name', 'SMA Negeri 1 Smart Exam')) ?>" required>
                                <div class="form-text fs-3xs text-muted">Dicetak pada kartu ujian dan lembar nilai.</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Slogan / Tagline Aplikasi</label>
                                <input type="text" class="form-control border-slate-200 fs-xs rounded-3" name="configs[app_tagline]" value="<?= e(system_setting('app_tagline', 'Platform Ujian Online Berintegritas Tinggi')) ?>">
                                <div class="form-text fs-3xs text-muted">Tampil di beranda login dan header laporan.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-xs">
                            <i class="bi bi-clock-history text-primary"></i>
                            <span>Waktu & Berkas</span>
                        </h6>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Zona Waktu (Timezone) Server</label>
                            <select name="configs[app_timezone]" class="form-select border-slate-200 fs-xs rounded-3">
                                <?php $curTz = system_setting('app_timezone', 'Asia/Jakarta'); ?>
                                <option value="Asia/Jakarta" <?= ($curTz === 'Asia/Jakarta') ? 'selected' : '' ?>>WIB — Asia/Jakarta (UTC+7)</option>
                                <option value="Asia/Makassar" <?= ($curTz === 'Asia/Makassar') ? 'selected' : '' ?>>WITA — Asia/Makassar (UTC+8)</option>
                                <option value="Asia/Jayapura" <?= ($curTz === 'Asia/Jayapura') ? 'selected' : '' ?>>WIT — Asia/Jayapura (UTC+9)</option>
                            </select>
                            <div class="form-text fs-3xs text-muted">Sinkron dengan jadwal timer ujian CBT.</div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Batas Maksimal Unggah Berkas</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="1" max="50" name="configs[upload_max_filesize_mb]" value="<?= e(system_setting('upload_max_filesize_mb', '10')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">MB</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Batas ukuran gambar soal dan foto profil.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: STANDAR UJIAN CBT (EXAM)                                           -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= ($curTab === 'exam') ? 'show active' : '' ?>" id="tab-pane-exam" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-xs">
                            <i class="bi bi-shuffle text-success"></i>
                            <span>Pengacakan & Rilis Hasil</span>
                        </h6>
                        <!-- Randomize Questions -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Acak Urutan Butir Soal</div>
                                <div class="fs-3xs text-muted">Nomor urut soal diacak otomatis untuk tiap peserta.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[default_randomize_questions]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[default_randomize_questions]" value="1" <?= ((string)system_setting('default_randomize_questions', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Randomize Options -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Acak Urutan Opsi Pilihan Ganda</div>
                                <div class="fs-3xs text-muted">Opsi jawaban A-E tersusun unik untuk tiap siswa.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[default_randomize_options]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[default_randomize_options]" value="1" <?= ((string)system_setting('default_randomize_options', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Show Result Instant -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Tampilkan Nilai Instan</div>
                                <div class="fs-3xs text-muted">Langsung tampilkan nilai akhir saat siswa selesai.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[default_show_result_instant]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[default_show_result_instant]" value="1" <?= ((string)system_setting('default_show_result_instant', '0') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Show Explanation -->
                        <div class="mb-0 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Izinkan Melihat Pembahasan</div>
                                <div class="fs-3xs text-muted">Buka akses pembahasan setelah nilai resmi dirilis.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[default_show_explanation]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[default_show_explanation]" value="1" <?= ((string)system_setting('default_show_explanation', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-xs">
                            <i class="bi bi-clock-history text-primary"></i>
                            <span>Disiplin Waktu & Integritas Tab</span>
                        </h6>
                        <!-- Anti Tab Switch Detection -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Deteksi Pindah Tab / Layar</div>
                                <div class="fs-3xs text-muted">Catat pelanggaran jika peserta membuka jendela lain.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[prevent_tab_switch]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[prevent_tab_switch]" value="1" <?= ((string)system_setting('prevent_tab_switch', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Max Tab Switch Allowed -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Batas Toleransi Pindah Tab</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="1" max="10" name="configs[max_tab_switch_allowed]" value="<?= e(system_setting('max_tab_switch_allowed', '3')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Peringatan</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Ujian otomatis terkunci jika melampaui batas ini.</div>
                        </div>

                        <!-- Late Tolerance -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Toleransi Keterlambatan Masuk</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="0" max="60" name="configs[late_tolerance_minutes]" value="<?= e(system_setting('late_tolerance_minutes', '15')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Menit</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Batas waktu masuk sejak ujian dimulai (0 = tanpa toleransi).</div>
                        </div>

                        <!-- Autosave Interval -->
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Interval Simpan Otomatis (Autosave)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="5" max="60" name="configs[autosave_interval_seconds]" value="<?= e(system_setting('autosave_interval_seconds', '15')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Detik</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Jawaban disimpan otomatis ke server secara berkala.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 3: DUAL CAMERA & AI PROCTORING (PROCTORING)                           -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= ($curTab === 'proctoring') ? 'show active' : '' ?>" id="tab-pane-proctoring" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-xs">
                                <i class="bi bi-camera-video text-info"></i>
                                <span>Engine AI Vision & Dual Kamera</span>
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2.5" onclick="App.openCameraDiagnostic()">
                                <i class="bi bi-camera-video me-1"></i> Uji Dual Cam
                            </button>
                        </div>

                        <!-- Strict Dual Cam -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Wajibkan Kalibrasi Dual Kamera</div>
                                <div class="fs-3xs text-muted">Siswa wajib hubungkan kamera smartphone sebelum mulai.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[strict_dual_camera]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[strict_dual_camera]" value="1" <?= ((string)system_setting('strict_dual_camera', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Gaze Tracking Sensitivity -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold text-slate-800 fs-xs mb-0">Sensitivitas Gaze Tracking (Lirikan Mata)</label>
                                <span class="badge rounded-pill bg-info text-white fs-3xs" id="gaze_badge"><?= e(system_setting('gaze_sensitivity_threshold', '0.75')) ?></span>
                            </div>
                            <input type="range" class="form-range" min="0.50" max="0.95" step="0.05" name="configs[gaze_sensitivity_threshold]" value="<?= e(system_setting('gaze_sensitivity_threshold', '0.75')) ?>" oninput="document.getElementById('gaze_badge').textContent = this.value">
                            <div class="d-flex justify-content-between fs-3xs text-muted">
                                <span>0.50 (Longgar)</span>
                                <span>0.75 (Standar)</span>
                                <span>0.95 (Ketat)</span>
                            </div>
                        </div>

                        <!-- Multi Face Detection -->
                        <div class="mb-0 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Deteksi Orang Lain (Multi-Face)</div>
                                <div class="fs-3xs text-muted">Picu anomali jika terdeteksi wajah lain di area ujian.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[multi_face_detection]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[multi_face_detection]" value="1" <?= ((string)system_setting('multi_face_detection', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-xs">
                                <i class="bi bi-shield-exclamation text-danger"></i>
                                <span>Toleransi & Peringatan Pelanggaran</span>
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5" onclick="App.confirm('Kembalikan parameter proctoring ke default pabrikan?', () => App.submitPost('<?= base_url('admin/ai-configuration/reset') ?>'))">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Default
                            </button>
                        </div>

                        <!-- No Face Timeout -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Toleransi Wajah Hilang (No-Face Timeout)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="2" max="30" name="configs[no_face_timeout_seconds]" value="<?= e(system_setting('no_face_timeout_seconds', '5')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Detik</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Peringatan aktif jika wajah tidak terlihat melebihi detik ini.</div>
                        </div>

                        <!-- Max Proctoring Warnings -->
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Batas Maksimal Akumulasi Peringatan AI</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="1" max="15" name="configs[max_proctoring_warnings]" value="<?= e(system_setting('max_proctoring_warnings', '5')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Peringatan</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Pengawas menerima sinyal peringatan jika batas ini terlampaui.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 4: MODEL AI & LLM ENGINE (AI) — REF: SMARTUTBK 3-COLUMN SLEEK          -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= ($curTab === 'ai') ? 'show active' : '' ?>" id="tab-pane-ai" role="tabpanel">
            <div class="row g-3">
                <!-- 1. Card Membuat Soal -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 d-flex flex-column justify-content-between p-3.5">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 d-flex align-items-center gap-1.5 fs-xs text-body-emphasis">
                                    <i class="bi bi-file-earmark-text text-primary"></i>
                                    <span>Membuat Soal</span>
                                </h6>
                                <span class="badge bg-body-tertiary text-muted border rounded-pill fs-4xs">Bank Soal</span>
                            </div>
                            <p class="fs-3xs text-muted mb-3">Model untuk fitur "Generate dengan AI" di Bank Soal Guru.</p>

                            <!-- Ganti ke model -->
                            <div class="mb-3">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Model AI</label>
                                <select name="configs[ai_question_generation_model]" class="form-select form-select-sm border-slate-200 fs-xs rounded-3">
                                    <?php $curQModel = system_setting('ai_question_generation_model', 'gpt-4o-mini'); ?>
                                    <option value="gpt-4o-mini" <?= ($curQModel === 'gpt-4o-mini') ? 'selected' : '' ?>>gpt-4o-mini (Cepat &amp; Terstruktur)</option>
                                    <option value="gemini-3.8-flash" <?= ($curQModel === 'gemini-3.8-flash') ? 'selected' : '' ?>>gemini-3.8-flash (Rendah Latensi)</option>
                                    <option value="claude-3-haiku" <?= ($curQModel === 'claude-3-haiku') ? 'selected' : '' ?>>claude-3-haiku (Anthropic)</option>
                                </select>
                            </div>

                            <!-- Sakelar Aktifkan Generator -->
                            <div class="p-2.5 rounded-3 bg-body-tertiary border mb-0 d-flex justify-content-between align-items-center">
                                <span class="fs-3xs text-body fw-semibold">Izin Generator Guru</span>
                                <div class="form-check form-switch m-0">
                                    <input type="hidden" name="configs[ai_question_generation_enabled]" value="0">
                                    <input class="form-check-input" type="checkbox" name="configs[ai_question_generation_enabled]" value="1" <?= ((string)system_setting('ai_question_generation_enabled', '1') === '1') ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-xs btn-primary rounded-pill px-3 fw-semibold">Simpan</button>
                        </div>
                    </div>
                </div>

                <!-- 2. Card Koreksi Esai -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 d-flex flex-column justify-content-between p-3.5">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 d-flex align-items-center gap-1.5 fs-xs text-body-emphasis">
                                    <i class="bi bi-chat-left-dots text-info"></i>
                                    <span>Penilaian Esai &amp; Evaluasi</span>
                                </h6>
                                <span class="badge bg-body-tertiary text-muted border rounded-pill fs-4xs">Auto-Grading</span>
                            </div>
                            <p class="fs-3xs text-muted mb-3">Model untuk penilaian otomatis jawaban esai siswa.</p>

                            <!-- Toleransi Kesamaan -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fs-3xs text-muted mb-0 fw-semibold">Toleransi Semantik</label>
                                    <span class="badge bg-primary fs-4xs" id="essay_badge"><?= e(system_setting('ai_essay_grading_tolerance', '0.85')) ?></span>
                                </div>
                                <input type="range" class="form-range" min="0.60" max="0.95" step="0.05" name="configs[ai_essay_grading_tolerance]" value="<?= e(system_setting('ai_essay_grading_tolerance', '0.85')) ?>" oninput="document.getElementById('essay_badge').textContent = this.value">
                                <div class="d-flex justify-content-between fs-4xs text-muted">
                                    <span>0.60 (Fleksibel)</span>
                                    <span>0.85 (Standar)</span>
                                    <span>0.95 (Ketat)</span>
                                </div>
                            </div>

                            <!-- Fallback Model -->
                            <div class="mb-0">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Model Cadangan (Fallback)</label>
                                <input type="text" class="form-control form-control-sm border-slate-200 fs-3xs rounded-3 font-monospace" name="configs[gemini_fallback_model]" value="<?= e(system_setting('gemini_fallback_model', 'gemini-flash-lite-latest,gemini-2.5-flash')) ?>">
                                <div class="form-text fs-4xs text-muted">Digunakan otomatis jika provider utama sibuk.</div>
                            </div>
                        </div>

                        <div class="pt-3 border-top mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-xs btn-primary rounded-pill px-3 fw-semibold">Simpan</button>
                        </div>
                    </div>
                </div>

                <!-- 3. Card Chatbot Asisten -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 d-flex flex-column justify-content-between p-3.5">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 d-flex align-items-center gap-1.5 fs-xs text-body-emphasis">
                                    <i class="bi bi-robot text-warning"></i>
                                    <span>Chatbot Asisten</span>
                                </h6>
                                <span class="badge bg-body-tertiary text-muted border rounded-pill fs-4xs">Live AI</span>
                            </div>
                            <p class="fs-3xs text-muted mb-3">Model asisten pintar operasional sistem Smart Exam.</p>

                            <!-- Provider & Model Utama -->
                            <div class="mb-2">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Model AI</label>
                                <select name="configs[gemini_model]" class="form-select form-select-sm border-slate-200 fs-xs rounded-3 mb-1.5">
                                    <?php $curGModel = system_setting('gemini_model', 'gemini-3.5-flash-lite'); ?>
                                    <option value="gemini-3.5-flash-lite" <?= ($curGModel === 'gemini-3.5-flash-lite') ? 'selected' : '' ?>>Google Gemini 3.5 Flash-Lite (Cepat)</option>
                                    <option value="gemini-3.8-flash" <?= ($curGModel === 'gemini-3.8-flash') ? 'selected' : '' ?>>Google Gemini 3.8 Flash (Pintar)</option>
                                    <option value="gemini-2.5-flash" <?= ($curGModel === 'gemini-2.5-flash') ? 'selected' : '' ?>>Google Gemini 2.5 Flash</option>
                                </select>
                            </div>

                            <!-- API Key Input -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fs-3xs text-muted mb-0 fw-semibold">API Key</label>
                                    <span class="badge bg-body-tertiary text-muted border rounded-pill fs-4xs"><?= empty(system_setting('gemini_api_key')) ? 'memakai .env' : 'kunci tersimpan' ?></span>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control border-slate-200 fs-3xs font-monospace rounded-start-3" name="configs[gemini_api_key]" id="gemini_api_key_input" placeholder="••••••••••••••••">
                                    <button class="btn btn-outline-secondary border-slate-200 rounded-end-3" type="button" onclick="App.togglePasswordVisibility('gemini_api_key_input')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text fs-4xs text-muted mt-1">Kosongkan untuk kembali memakai API Key di file .env.</div>
                            </div>

                            <!-- Sakelar Chatbot Asisten -->
                            <div class="p-2.5 rounded-3 bg-body-tertiary border mb-0 d-flex justify-content-between align-items-center">
                                <span class="fs-3xs text-body fw-semibold">Widget Chatbot Aktif</span>
                                <div class="form-check form-switch m-0">
                                    <input type="hidden" name="configs[ai_chat_assistant_enabled]" value="0">
                                    <input class="form-check-input" type="checkbox" name="configs[ai_chat_assistant_enabled]" value="1" <?= ((string)system_setting('ai_chat_assistant_enabled', '1') === '1') ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-xs btn-primary rounded-pill px-3 fw-semibold">Simpan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 5: KEAMANAN & PEMELIHARAAN (SECURITY)                                 -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= ($curTab === 'security') ? 'show active' : '' ?>" id="tab-pane-security" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-xs">
                            <i class="bi bi-shield-lock text-danger"></i>
                            <span>Kebijakan Sesi & Autentikasi</span>
                        </h6>
                        <!-- Single Session Enforcement -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Paksa Sesi Tunggal (Anti Dual-Login)</div>
                                <div class="fs-3xs text-muted">Keluarkan sesi lama jika siswa login di perangkat baru.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[enforce_single_session]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[enforce_single_session]" value="1" <?= ((string)system_setting('enforce_single_session', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- CSRF Protection Toggle -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Proteksi Token Anti-CSRF</div>
                                <div class="fs-3xs text-muted">Wajibkan verifikasi token acak pada setiap form.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[csrf_protection]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[csrf_protection]" value="1" <?= ((string)system_setting('csrf_protection', '1') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Session Lifetime -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Masa Berlaku Sesi Pengguna</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="300" max="86400" step="300" name="configs[session_lifetime]" value="<?= e(system_setting('session_lifetime', '7200')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Detik (<?= round(((int)system_setting('session_lifetime', '7200')) / 3600, 1) ?> Jam)</span>
                            </div>
                        </div>

                        <!-- Rate Limit Login -->
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Batas Maksimal Salah Password Login</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="3" max="20" name="configs[rate_limit_login_max]" value="<?= e(system_setting('rate_limit_login_max', '5')) ?>">
                                <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Kali Gagal</span>
                            </div>
                            <div class="form-text fs-3xs text-muted">Akun dikunci sementara jika gagal login berturut-turut.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-xs">
                            <i class="bi bi-tools text-warning"></i>
                            <span>Mode Pemeliharaan (Maintenance)</span>
                        </h6>
                        <!-- Maintenance Mode Switch -->
                        <div class="mb-3 p-3 rounded-3 bg-slate-50 border border-slate-100 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-slate-800 fs-xs">Status Mode Pemeliharaan</div>
                                <div class="fs-3xs text-muted">Hanya Administrator yang dapat login saat aktif.</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="configs[maintenance_mode]" value="0">
                                <input class="form-check-input fs-5" type="checkbox" name="configs[maintenance_mode]" value="1" <?= ((string)system_setting('maintenance_mode', '0') === '1') ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Pesan Pemeliharaan untuk Siswa</label>
                            <textarea class="form-control border-slate-200 fs-xs rounded-3" rows="3" name="configs[maintenance_message]"><?= e(system_setting('maintenance_message', 'Sistem Ujian Smart Exam sedang dalam pemeliharaan berkala untuk peningkatan performa.')) ?></textarea>
                            <div class="form-text fs-3xs text-muted">Pesan ini ditampilkan pada layar siswa saat mengakses situs.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 6: NOTIFIKASI EMAIL & WHATSAPP (NOTIFICATION)                         -->
        <!-- ========================================================================= -->
        <div class="tab-pane fade <?= ($curTab === 'notification') ? 'show active' : '' ?>" id="tab-pane-notification" role="tabpanel">
            <div class="row g-4">
                <!-- Mode Saluran Notifikasi -->
                <div class="col-12">
                    <div class="card border-0 shadow-xs rounded-4 bg-white p-3.5 d-flex flex-wrap justify-content-between align-items-center flex-row gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary fs-4">
                                <i class="bi bi-bell"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-slate-900 mb-0.5 fs-xs">Mode Saluran Notifikasi</h6>
                                <p class="text-muted fs-3xs mb-0">Atur lingkungan pengiriman notifikasi ujian (Email SMTP &amp; WhatsApp Fonnte).</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select name="configs[notification_mode]" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill" style="min-width: 170px;">
                                <?php $curNotifMode = system_setting('notification_mode', 'sandbox'); ?>
                                <option value="simulation" <?= ($curNotifMode === 'simulation') ? 'selected' : '' ?>>Simulation (Aman Lokal)</option>
                                <option value="sandbox" <?= ($curNotifMode === 'sandbox') ? 'selected' : '' ?>>Sandbox (Uji Terkontrol)</option>
                                <option value="production" <?= ($curNotifMode === 'production') ? 'selected' : '' ?>>Production (Live Masal)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 1. Gmail SMTP -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-xs">
                                <i class="bi bi-envelope-at text-danger"></i>
                                <span>Email Gateway (Gmail SMTP)</span>
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5" id="btnTestSmtp" onclick="App.runSmtpTest()">
                                <i class="bi bi-activity me-1"></i> Uji Koneksi
                            </button>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-8">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">SMTP Host</label>
                                <input type="text" class="form-control form-control-sm border-slate-200 fs-xs rounded-3 font-monospace" name="configs[smtp_host]" value="<?= e(system_setting('smtp_host', 'smtp.gmail.com')) ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Port</label>
                                <input type="number" class="form-control form-control-sm border-slate-200 fs-xs rounded-3" name="configs[smtp_port]" value="<?= e(system_setting('smtp_port', '587')) ?>">
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Username / Email Akun</label>
                                <input type="email" class="form-control form-control-sm border-slate-200 fs-xs rounded-3" name="configs[smtp_username]" value="<?= e(system_setting('smtp_username', '')) ?>" placeholder="nama@gmail.com">
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Password Aplikasi (16 Karakter)</label>
                                <input type="password" class="form-control form-control-sm border-slate-200 fs-xs rounded-3 font-monospace" name="configs[smtp_password]" placeholder="••••••••••••••••">
                            </div>
                        </div>

                        <div class="row g-2 mb-0">
                            <div class="col-6">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Email Pengirim</label>
                                <input type="email" class="form-control form-control-sm border-slate-200 fs-xs rounded-3" name="configs[smtp_from_email]" value="<?= e(system_setting('smtp_from_email', '')) ?>" placeholder="no-reply@sekolah.sch.id">
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Nama Pengirim</label>
                                <input type="text" class="form-control form-control-sm border-slate-200 fs-xs rounded-3" name="configs[smtp_from_name]" value="<?= e(system_setting('smtp_from_name', 'Smart Exam')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. WhatsApp Gateway (Fonnte) -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-xs">
                                <i class="bi bi-whatsapp text-success"></i>
                                <span>WhatsApp Gateway (Fonnte API)</span>
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2.5" id="btnTestWa" onclick="App.runWhatsAppTest()">
                                <i class="bi bi-phone me-1"></i> Uji Koneksi
                            </button>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fs-3xs text-muted mb-1 fw-semibold">Token API Fonnte</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control border-slate-200 fs-3xs font-monospace rounded-start-3" name="configs[whatsapp_api_token]" id="wa_token_input" placeholder="••••••••••••••••">
                                <button class="btn btn-outline-secondary border-slate-200 rounded-end-3" type="button" onclick="App.togglePasswordVisibility('wa_token_input')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text fs-4xs text-muted">Ambil token dari dashboard fonnte.com.</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fs-3xs text-muted mb-1 fw-semibold">URL Endpoint API</label>
                            <input type="url" class="form-control form-control-sm border-slate-200 fs-xs rounded-3 font-monospace" name="configs[whatsapp_api_url]" value="<?= e(system_setting('whatsapp_api_url', 'https://api.fonnte.com/send')) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bottom Sticky Save Footer -->
    <div class="card border-0 shadow-lg rounded-4 bg-white mt-4 sticky-bottom">
        <div class="card-body p-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-primary"></i>
                <span class="fs-xs text-muted">Perubahan langsung berlaku untuk seluruh modul aplikasi secara otomatis.</span>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-4">Batal</a>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold shadow-xs">
                    <i class="bi bi-save me-1"></i> Simpan Konfigurasi
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Modal Import Configuration Backup -->
<div class="modal fade" id="importConfigModal" tabindex="-1" aria-labelledby="importConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="<?= base_url('admin/system-configuration/import') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header bg-white py-3 px-4 border-bottom border-slate-100">
                    <h6 class="modal-title fw-bold text-slate-900 fs-xs d-flex align-items-center gap-2" id="importConfigModalLabel">
                        <i class="bi bi-upload text-info"></i>
                        <span>Impor Cadangan Konfigurasi</span>
                    </h6>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="fs-xs text-muted mb-3">Pilih berkas JSON cadangan konfigurasi (<code>smart_exam_config_*.json</code>) yang sebelumnya diekspor.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Pilih Berkas JSON <span class="text-danger">*</span></label>
                        <input type="file" class="form-control border-slate-200 fs-xs rounded-3" name="config_file" accept=".json,application/json" required>
                    </div>
                </div>
                <div class="modal-footer bg-slate-50 py-2.5 px-4 border-top border-slate-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs">
                        <i class="bi bi-check2 me-1"></i> Mulai Impor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle Password Visibility Helper
if (!window.App) window.App = {};
App.togglePasswordVisibility = function(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
};

// Uji Koneksi SMTP Gmail Live
App.runSmtpTest = function() {
    const btn = document.getElementById('btnTestSmtp');
    if (!btn) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menguji...';

    fetch('<?= base_url('admin/system-configuration/test-smtp') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_token() ?>'
        }
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (res.success) {
            App.alert(res.message, 'success');
        } else {
            App.alert(res.message || 'Koneksi SMTP gagal.', 'danger');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        App.alert('Terjadi kesalahan saat menguji koneksi SMTP: ' + err.message, 'danger');
    });
};

// Uji Koneksi WhatsApp Gateway Live
App.runWhatsAppTest = function() {
    const btn = document.getElementById('btnTestWa');
    if (!btn) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menguji...';

    fetch('<?= base_url('admin/system-configuration/test-whatsapp') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_token() ?>'
        }
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (res.success) {
            App.alert(res.message, 'success');
        } else {
            App.alert(res.message || 'Koneksi WhatsApp Gateway gagal.', 'danger');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        App.alert('Terjadi kesalahan saat menguji koneksi WhatsApp: ' + err.message, 'danger');
    });
};

// Alert Simulasi
App.simulateAlert = function() {
    App.alert('Ini adalah simulasi notifikasi banner sistem Smart Exam.', 'info');
};
</script>
