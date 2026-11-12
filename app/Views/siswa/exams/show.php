<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<?php
$isAllowed = $validation['allowed'] ?? false;
$valCode = $validation['code'] ?? 'OK';
$valReason = $validation['reason'] ?? '';
?>

<!-- Validation Alert Banners -->
<?php if (!empty($recoveryData)): ?>
    <!-- Exam Session Recovery Alert Banner (UC-14) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #1e3a8a 0%, #0d9488 100%);">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 position-relative z-1">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-white text-primary p-3 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-arrow-repeat fs-3 text-primary"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark fw-bold px-2.5 py-1 fs-3xs">
                            <i class="bi bi-broadcast me-1"></i> Sesi Berlangsung Ditemukan
                        </span>
                        <span class="text-white-50 fs-3xs">Jam Server Terverifikasi</span>
                    </div>
                    <h5 class="fw-bold mb-1 text-white">Ujian yang sedang berlangsung ditemukan.</h5>
                    <p class="text-white-75 fs-xs mb-2">
                        Anda memiliki sesi pengerjaan aktif untuk ujian <strong><?= e($recoveryData['exam_title']) ?></strong>. Sesi tersimpan aman di server dan Anda dapat langsung melanjutkannya tanpa perlu memasukkan ulang token.
                    </p>
                    <div class="d-flex flex-wrap gap-2 fs-xs text-white">
                        <span class="badge px-3 py-1.5 rounded-pill font-monospace" style="background: rgba(255,255,255,0.18);">
                            <i class="bi bi-clock-history text-warning me-1"></i> Sisa Waktu Server: <?= sprintf('%02d:%02d:%02d', floor($recoveryData['remaining_seconds']/3600), floor(($recoveryData['remaining_seconds']%3600)/60), $recoveryData['remaining_seconds']%60) ?>
                        </span>
                        <span class="badge px-3 py-1.5 rounded-pill" style="background: rgba(255,255,255,0.18);">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> Soal Terjawab: <?= (int)$recoveryData['answered_count'] ?> dari <?= (int)$recoveryData['total_questions'] ?> Soal
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <a href="<?= $recoveryData['room_url'] ?>" class="btn btn-warning btn-lg rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-play-fill fs-5"></i>
                    <span>Lanjutkan Ujian</span>
                </a>
            </div>
        </div>
    </div>
<?php elseif (!$isAllowed): ?>
    <?php if ($valCode === 'NOT_STARTED'): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3.5 mb-4 d-flex align-items-start gap-3">
            <div class="rounded-circle bg-warning text-dark p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="bi bi-clock-history fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-dark">Ujian Belum Dimulai</h6>
                <p class="mb-0 fs-xs text-dark-50"><?= e($valReason) ?></p>
            </div>
        </div>
    <?php elseif ($valCode === 'EXPIRED'): ?>
        <div class="alert alert-secondary border-0 shadow-sm rounded-4 p-3.5 mb-4 d-flex align-items-start gap-3">
            <div class="rounded-circle bg-secondary text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="bi bi-calendar-x fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-dark">Waktu Pengerjaan Ujian Telah Berakhir</h6>
                <p class="mb-0 fs-xs text-secondary"><?= e($valReason) ?></p>
            </div>
        </div>
    <?php elseif ($valCode === 'NOT_PUBLISHED'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 p-3.5 mb-4 d-flex align-items-start gap-3">
            <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="bi bi-lock-fill fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-danger">Ujian Belum Dipublikasikan</h6>
                <p class="mb-0 fs-xs text-danger-emphasis"><?= e($valReason) ?></p>
            </div>
        </div>
    <?php elseif ($valCode === 'NOT_REGISTERED'): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 p-3.5 mb-4 d-flex align-items-start gap-3">
            <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="bi bi-person-x fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-danger">Peserta Tidak Terdaftar</h6>
                <p class="mb-0 fs-xs text-danger-emphasis"><?= e($valReason) ?></p>
            </div>
        </div>
    <?php elseif ($valCode === 'ALREADY_SUBMITTED'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3.5 mb-4 d-flex align-items-start gap-3">
            <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="bi bi-check-circle fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-success">Ujian Telah Selesai Dikerjakan</h6>
                <p class="mb-0 fs-xs text-success-emphasis"><?= e($valReason) ?></p>
                <div class="mt-2">
                    <a href="<?= base_url('siswa/history') ?>" class="btn btn-xs btn-success rounded-pill px-3">
                        <i class="bi bi-clock-history me-1"></i> Buka Riwayat Ujian
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Main Exam Overview Card -->
<div class="row g-4">
    <!-- Left Column: Exam Details & AI Rules -->
    <div class="col-lg-8">
        <div class="card border rounded-3 shadow-sm p-4 bg-white mb-4">
            <!-- Header Title -->
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-primary-subtle text-primary fs-3xs">
                            <?= e($exam['subject_name'] ?? 'Mata Pelajaran') ?>
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary fs-3xs">
                            Kode: <?= e($exam['code'] ?? '-') ?>
                        </span>
                    </div>
                    <h5 class="fw-bold text-slate-900 mb-1 fs-6"><?= e($exam['title'] ?? 'Ujian') ?></h5>
                    <p class="text-muted fs-3xs mb-0">
                        Guru Pengampu: <strong class="text-slate-800"><?= e($exam['teacher_name'] ?? '-') ?></strong> &bull;
                        Target Kelas: <strong class="text-primary"><?= e($schedule['class_name'] ?? $studentClass['name'] ?? '-') ?></strong>
                    </p>
                </div>
                <div>
                    <?php if (!empty($recoveryData)): ?>
                        <span class="badge bg-primary text-white px-2.5 py-1 rounded-2 fs-3xs fw-semibold shadow-xs">
                            <i class="bi bi-arrow-repeat me-1"></i> Sesi Aktif Berjalan
                        </span>
                    <?php elseif ($isAllowed): ?>
                        <span class="badge bg-success text-white px-2.5 py-1 rounded-2 fs-3xs fw-semibold shadow-xs">
                            <i class="bi bi-check-circle-fill me-1"></i> Siap Dikerjakan
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary text-white px-2.5 py-1 rounded-2 fs-3xs fw-semibold">
                            <i class="bi bi-slash-circle me-1"></i> Akses Terbatas
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($exam['description'])): ?>
                <div class="alert alert-light border rounded-2 p-3 fs-3xs text-muted mb-4">
                    <strong class="d-block text-slate-800 mb-1"><i class="bi bi-info-circle me-1 text-primary"></i> Petunjuk Ujian:</strong>
                    <?= nl2br(e($exam['description'])) ?>
                </div>
            <?php endif; ?>

            <!-- Specifications Grid -->
            <div class="form-section-title">
                <i class="bi bi-sliders"></i>
                <span>Parameter & Spesifikasi Ujian</span>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-slate-50 rounded-2 border text-center h-100">
                        <i class="bi bi-calendar2-range fs-4 text-primary d-block mb-1"></i>
                        <small class="text-muted fs-3xs d-block">Jadwal Mulai</small>
                        <strong class="fs-3xs text-slate-800"><?= !empty($schedule['start_time']) ? date('d M Y, H:i', strtotime($schedule['start_time'])) : '-' ?> WIB</strong>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-slate-50 rounded-2 border text-center h-100">
                        <i class="bi bi-hourglass-split fs-4 text-warning d-block mb-1"></i>
                        <small class="text-muted fs-3xs d-block">Durasi Waktu</small>
                        <strong class="fs-3xs text-slate-800"><?= (int)($exam['duration_minutes'] ?? 60) ?> Menit</strong>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-slate-50 rounded-2 border text-center h-100">
                        <i class="bi bi-award fs-4 text-success d-block mb-1"></i>
                        <small class="text-muted fs-3xs d-block">KKM Kelulusan</small>
                        <strong class="fs-3xs text-slate-800"><?= number_format((float)($exam['passing_grade'] ?? 75), 0) ?> Poin</strong>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-slate-50 rounded-2 border text-center h-100">
                        <i class="bi bi-camera-video fs-4 text-danger d-block mb-1"></i>
                        <small class="text-muted fs-3xs d-block">Dual Kamera AI</small>
                        <strong class="fs-3xs text-slate-800"><?= (int)($exam['is_dual_cam_required'] ?? 1) === 1 ? 'Wajib Aktif' : 'Nonaktif' ?></strong>
                    </div>
                </div>
            </div>

            <!-- AI Proctoring Protocol Rules -->
            <div class="form-section-title">
                <i class="bi bi-shield-check"></i>
                <span>Protokol Pengawasan & Integritas AI</span>
            </div>
            <div class="row g-2 fs-3xs text-muted">
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2.5 rounded-2 border bg-white">
                        <i class="bi <?= (int)($exam['lock_browser'] ?? 1) === 1 ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i>
                        <span>Browser Lockdown (Layar Penuh Otomatis)</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2.5 rounded-2 border bg-white">
                        <i class="bi <?= (int)($exam['detect_multiple_faces'] ?? 1) === 1 ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i>
                        <span>Deteksi Lebih dari Satu Wajah</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2.5 rounded-2 border bg-white">
                        <i class="bi <?= (int)($exam['detect_look_away'] ?? 1) === 1 ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i>
                        <span>Deteksi Tatapan Menoleh / Tanpa Wajah</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2.5 rounded-2 border bg-white">
                        <i class="bi <?= (int)($exam['detect_secondary_device'] ?? 1) === 1 ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i>
                        <span>Deteksi Smartphone / Perangkat Sekunder</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2.5 rounded-2 border bg-white">
                        <i class="bi <?= (int)($exam['detect_audio_noise'] ?? 1) === 1 ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?>"></i>
                        <span>Deteksi Kebisingan Audio Sekitar</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2.5 rounded-2 border bg-white">
                        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                        <span>Toleransi Keluar Tab: Maks. <?= (int)($exam['max_tab_switches'] ?? 3) ?> Kali</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Verification & Start Action -->
    <div class="col-lg-4">
        <div class="card border rounded-3 shadow-sm p-4 bg-white mb-4">
            <?php if (!empty($recoveryData)): ?>
                <!-- Active Ongoing Session Recovery Action Card (UC-14) -->
                <div class="text-center mb-3">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                        <i class="bi bi-arrow-clockwise fs-4 text-primary"></i>
                    </div>
                    <h6 class="fw-bold text-slate-900 mb-1">Lanjutkan Ujian Aktif</h6>
                    <p class="text-muted fs-3xs mb-0">Ujian yang sedang berlangsung ditemukan.</p>
                </div>

                <div class="p-3 bg-slate-50 rounded-2 border mb-3 fs-3xs d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="bi bi-journal-text me-1 text-primary"></i> Nama Ujian:</span>
                        <strong class="text-slate-800 text-truncate ms-2" style="max-width: 160px;"><?= e($recoveryData['exam_title']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="bi bi-clock-history me-1 text-warning"></i> Sisa Waktu:</span>
                        <strong class="text-primary font-monospace fs-xs"><?= sprintf('%02d:%02d:%02d', floor($recoveryData['remaining_seconds']/3600), floor(($recoveryData['remaining_seconds']%3600)/60), $recoveryData['remaining_seconds']%60) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="bi bi-check2-circle me-1 text-success"></i> Terjawab:</span>
                        <strong class="text-success"><?= (int)$recoveryData['answered_count'] ?> / <?= (int)$recoveryData['total_questions'] ?> Soal</strong>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="<?= $recoveryData['room_url'] ?>" class="btn btn-warning rounded-2 py-2 fw-medium shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-play-circle-fill"></i>
                        <span>Lanjutkan Ujian</span>
                    </a>
                    <a href="<?= base_url('siswa/exam/pre-check/' . ($exam['id'] ?? '')) ?>" class="btn btn-outline-secondary rounded-2 btn-sm fw-medium">
                        <i class="bi bi-camera-video me-1"></i> Kalibrasi Kamera Ulang
                    </a>
                </div>
            <?php elseif ($isAllowed): ?>
                <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2 fs-sm">
                    <i class="bi bi-key-fill text-primary"></i>
                    <span>Konfirmasi Masuk Ujian</span>
                </h6>
                <form action="<?= base_url('siswa/exams/start/' . ($exam['id'] ?? 0)) ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Token Ujian (6 Karakter) <span class="text-danger">*</span></label>
                        <input type="text" name="token" required class="form-control text-center font-monospace fw-bold letter-spacing-lg text-uppercase" placeholder="XXXXXX" maxlength="6" id="studentExamTokenInput" autocomplete="off">
                        <div class="form-text">Dapatkan token dari Pengawas Ruang sebelum ujian dimulai.</div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="<?= base_url('siswa/exam/pre-check/' . ($exam['id'] ?? '')) ?>" class="btn btn-outline-primary rounded-2 btn-sm fw-medium">
                            <i class="bi bi-camera-video me-1"></i> Cek & Kalibrasi Kamera
                        </a>
                        <button type="submit" class="btn btn-primary rounded-2 py-2 fw-medium shadow-sm">
                            <i class="bi bi-play-circle-fill me-1"></i> Mulai Kerjakan Ujian
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="p-3 bg-slate-50 rounded-2 text-center mb-3 border">
                    <i class="bi bi-shield-slash fs-2 text-secondary d-block mb-1"></i>
                    <h6 class="fw-bold text-slate-800 mb-1 fs-sm">Ujian Belum Dapat Dibuka</h6>
                    <p class="text-muted fs-3xs mb-0">Silakan kembali saat jadwal pelaksanaan aktif dan dipublikasikan oleh pengawas.</p>
                </div>
                <a href="<?= base_url('siswa/exams') ?>" class="btn btn-outline-secondary w-100 rounded-2 btn-sm fw-medium">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog Ujian
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
