<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Filter Form -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
                <i class="bi bi-card-checklist text-primary"></i>
                <span>Daftar Ujian Tersedia</span>
            </h4>
            <p class="text-muted fs-3xs mb-0">
                Jadwal ujian aktif yang ditugaskan khusus untuk rombel <strong class="text-primary"><?= e($studentClass['name'] ?? '-') ?></strong>.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary fs-3xs px-2.5 py-1 rounded-pill">
                <i class="bi bi-mortarboard me-1"></i> <?= count($exams) ?> Ujian Ditemukan
            </span>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <form method="GET" action="<?= base_url('siswa/exams') ?>" class="row g-2 align-items-center">
        <!-- Subject Filter -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 border-slate-200"><i class="bi bi-book text-muted fs-xs"></i></span>
                <select name="subject_id" class="form-select bg-light border-start-0 border-slate-200 fs-xs" onchange="this.form.submit()">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ((int)$selectedSubject === (int)$sub['id']) ? 'selected' : '' ?>>
                            <?= e($sub['name']) ?> (<?= e($sub['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Status Filter -->
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 border-slate-200"><i class="bi bi-funnel text-muted fs-xs"></i></span>
                <select name="status" class="form-select bg-light border-start-0 border-slate-200 fs-xs" onchange="this.form.submit()">
                    <option value="all" <?= ($selectedStatus === 'all') ? 'selected' : '' ?>>Semua Status Waktu</option>
                    <option value="ongoing" <?= ($selectedStatus === 'ongoing') ? 'selected' : '' ?>>Sedang Berlangsung</option>
                    <option value="upcoming" <?= ($selectedStatus === 'upcoming') ? 'selected' : '' ?>>Akan Datang (Terjadwal)</option>
                </select>
            </div>
        </div>

        <!-- Search Input -->
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 border-slate-200"><i class="bi bi-search text-muted fs-xs"></i></span>
                <input type="text" name="search" class="form-control bg-light border-start-0 border-slate-200 ps-0 fs-xs" placeholder="Cari judul atau kode..." value="<?= e($search) ?>">
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3.5 w-100 fw-semibold shadow-xs">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if (!empty($selectedSubject) || $selectedStatus !== 'all' || !empty($search)): ?>
                <a href="<?= base_url('siswa/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Exam Grid Cards -->
<?php if (empty($exams)): ?>
    <div class="card border-0 shadow-xs rounded-4 p-5 bg-white text-center">
        <div class="empty-state py-4 text-center">
            <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                <i class="bi bi-journal-x fs-2"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">Tidak Ada Ujian Ditemukan</h6>
            <p class="text-muted fs-xs mb-3">Tidak ditemukan jadwal ujian yang sesuai dengan kriteria filter atau rombel Anda saat ini.</p>
            <a href="<?= base_url('siswa/exams') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3.5">
                <i class="bi bi-arrow-clockwise me-1"></i> Tampilkan Semua Ujian
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($exams as $ex): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 border-0 shadow-xs rounded-4 p-3.5 bg-white d-flex flex-column justify-content-between">
                    <div>
                        <!-- Header Status & Subject -->
                        <div class="d-flex justify-content-between align-items-center mb-2.5">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-3xs">
                                <?= e($ex['subject_name']) ?>
                            </span>
                            <?php if (isset($activeSessionExamMap[$ex['exam_id']])): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill fs-3xs px-2.5 py-0.5 fw-semibold">
                                    <i class="bi bi-broadcast me-1"></i> Sesi Berjalan
                                </span>
                            <?php elseif ($ex['time_status'] === 'ongoing'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs px-2.5 py-0.5">
                                    <i class="bi bi-circle-fill fs-4xs me-1"></i> Berlangsung
                                </span>
                            <?php elseif ($ex['time_status'] === 'upcoming'): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill fs-3xs px-2.5 py-0.5">
                                    <i class="bi bi-clock me-1"></i> Terjadwal
                                </span>
                            <?php else: ?>
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill fs-3xs px-2.5 py-0.5">
                                    <i class="bi bi-clock-history me-1"></i> Berakhir
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Exam Title & Teacher -->
                        <h6 class="fw-bold text-slate-900 mb-1 fs-xs">
                            <a href="<?= base_url('siswa/exams/show/' . $ex['exam_id']) ?>" class="text-slate-900 text-decoration-none hover-primary">
                                <?= e($ex['title']) ?>
                            </a>
                        </h6>
                        <div class="d-flex align-items-center gap-2 text-muted fs-3xs mb-3">
                            <span><i class="bi bi-hash"></i> <?= e($ex['exam_code']) ?></span>
                            <span>&bull;</span>
                            <span><i class="bi bi-person me-0.5"></i> <?= e($ex['teacher_name']) ?></span>
                        </div>

                        <!-- Schedule & Duration Spec Box -->
                        <div class="bg-slate-50 rounded-3 p-2.5 border border-slate-200 mb-3 fs-2xs d-flex flex-column gap-1.5">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-calendar-event text-primary me-1"></i> Jadwal:</span>
                                <span class="fw-semibold text-slate-800"><?= date('d M Y, H:i', strtotime($ex['start_time'])) ?> WIB</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-hourglass-split text-warning me-1"></i> Durasi:</span>
                                <span class="fw-semibold text-slate-800"><?= (int)$ex['duration_minutes'] ?> Menit</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-award text-success me-1"></i> Standar KKM:</span>
                                <span class="fw-semibold text-slate-800"><?= number_format((float)$ex['passing_grade'], 0) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-camera-video text-danger me-1"></i> Dual Kamera:</span>
                                <span class="fw-semibold <?= (int)$ex['is_dual_cam_required'] === 1 ? 'text-danger' : 'text-secondary' ?>">
                                    <?= (int)$ex['is_dual_cam_required'] === 1 ? 'Wajib Smartphone' : 'Tunggal' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer Action -->
                    <div class="border-top border-slate-100 pt-2.5 mt-auto">
                        <?php if (isset($activeSessionExamMap[$ex['exam_id']])): ?>
                            <a href="<?= base_url('siswa/exams/show/' . $ex['exam_id']) ?>" class="btn btn-sm btn-warning w-100 rounded-pill fw-bold shadow-xs d-flex align-items-center justify-content-center gap-1.5">
                                <i class="bi bi-play-circle-fill"></i>
                                <span>Lanjutkan Ujian</span>
                            </a>
                        <?php elseif ($ex['time_status'] === 'ongoing'): ?>
                            <a href="<?= base_url('siswa/exams/show/' . $ex['exam_id']) ?>" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-xs">
                                <i class="bi bi-play-circle-fill me-1"></i> Masuk & Kerjakan
                            </a>
                        <?php elseif ($ex['time_status'] === 'upcoming'): ?>
                            <a href="<?= base_url('siswa/exams/show/' . $ex['exam_id']) ?>" class="btn btn-sm btn-outline-primary w-100 rounded-pill fw-semibold">
                                <i class="bi bi-info-circle me-1"></i> Lihat Rincian Jadwal
                            </a>
                        <?php else: ?>
                            <a href="<?= base_url('siswa/exams/show/' . $ex['exam_id']) ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-pill fw-semibold">
                                <i class="bi bi-clock-history me-1"></i> Ujian Telah Berakhir
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
