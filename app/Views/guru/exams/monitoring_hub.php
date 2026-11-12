<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Header Live Monitoring Hub -->
<div class="card border-0 shadow-xs rounded-4 text-white p-3.5 p-lg-4 mb-4 position-relative overflow-hidden" style="background: var(--gradient-guru);">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative z-1">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 bg-white text-danger p-2.5 d-flex align-items-center justify-content-center shadow-xs flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="bi bi-broadcast fs-4 text-danger"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h4 class="fw-bold mb-0 text-white tracking-tight">Pusat Pemantauan Ujian Live</h4>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill fs-3xs px-2.5 py-0.5 fw-semibold d-inline-flex align-items-center gap-1">
                        <span class="badge-dot pulse-danger"></span>
                        Real-Time Hub
                    </span>
                </div>
                <div class="text-white-75 fs-xs d-flex flex-wrap align-items-center gap-2 gap-md-3">
                    <span class="d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-shield-check text-white-50"></i> Dual Camera Vision Engine
                    </span>
                    <span class="text-white-50 d-none d-sm-inline">&bull;</span>
                    <span class="d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-person-workspace text-white-50"></i> Pengawas: <?= e($currentUser['name'] ?? 'Pengajar') ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url("{$basePrefix}/exams") ?>" class="btn btn-sm btn-light text-primary rounded-pill px-3.5 py-1.5 fw-semibold shadow-xs d-inline-flex align-items-center gap-2">
                <i class="bi bi-calendar2-check"></i>
                <span>Manajemen Ujian</span>
            </a>
            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3.5 py-1.5 fw-medium d-inline-flex align-items-center gap-2" onclick="App.openCameraDiagnostic()">
                <i class="bi bi-camera-video"></i>
                <span>Diagnostik Kamera</span>
            </button>
        </div>
    </div>
</div>

<!-- Active Live Exam Rooms Section -->
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-6">
            <i class="bi bi-play-circle-fill text-danger"></i>
            <span>Sesi Ujian yang Memerlukan Pemantauan Aktif</span>
        </h5>
        <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill fs-3xs px-2.5 py-1">
            <?= count($activeExams) ?> Ruang Aktif
        </span>
    </div>

    <?php if (empty($activeExams)): ?>
        <!-- Informative Empty State Card -->
        <div class="card border border-slate-200 shadow-3xs rounded-4 p-4 p-md-5 bg-white text-center">
            <div class="py-2">
                <div class="rounded-circle bg-slate-100 text-slate-400 p-3 mx-auto mb-3 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px;">
                    <i class="bi bi-broadcast fs-2 text-slate-500"></i>
                </div>
                <h5 class="fw-bold text-slate-900 mb-1 fs-5">Tidak Ada Sesi Ujian yang Sedang Berlangsung</h5>
                <p class="text-muted fs-xs mb-4 mx-auto" style="max-width: 520px;">
                    Saat ini belum ada jadwal ujian berstatus <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs">Published</span> atau <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill fs-3xs">Ongoing</span> yang memerlukan pemantauan live.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="<?= base_url("{$basePrefix}/exams") ?>" class="btn btn-primary btn-sm rounded-pill px-4 py-2 fw-semibold shadow-xs d-inline-flex align-items-center gap-2">
                        <i class="bi bi-calendar2-check"></i>
                        <span>Buka Manajemen Ujian</span>
                    </a>
                    <a href="<?= base_url("{$basePrefix}/dashboard") ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-4 py-2 fw-medium d-inline-flex align-items-center gap-2">
                        <i class="bi bi-arrow-left"></i>
                        <span>Kembali ke Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($activeExams as $exam): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border border-slate-200 shadow-3xs rounded-4 p-3.5 bg-white h-100 d-flex flex-column justify-content-between transition-all hover-bg-light">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2.5">
                                <span class="badge bg-danger text-white rounded-pill fs-3xs px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                    <span class="badge-dot pulse-light"></span>
                                    <span>LIVE</span>
                                </span>
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 font-monospace rounded-pill fs-3xs">
                                    Token: <?= e($exam['token'] ?? '-') ?>
                                </span>
                            </div>

                            <h6 class="fw-bold text-slate-900 mb-1 fs-sm"><?= e($exam['title']) ?></h6>
                            <div class="fs-2xs text-muted mb-3 d-flex flex-wrap gap-2 align-items-center">
                                <span class="d-inline-flex align-items-center gap-1"><i class="bi bi-book text-primary"></i> <?= e($exam['subject_name'] ?? '-') ?></span>
                                <span class="text-slate-300">&bull;</span>
                                <span class="d-inline-flex align-items-center gap-1"><i class="bi bi-clock text-info"></i> <?= (int)$exam['duration_minutes'] ?> Menit</span>
                            </div>

                            <div class="p-2.5 rounded-3 bg-slate-50 border border-slate-200 mb-3 fs-3xs text-slate-700">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>AI Proctoring:</span>
                                    <strong class="<?= !empty($exam['is_proctoring_active']) ? 'text-success' : 'text-secondary' ?>">
                                        <?= !empty($exam['is_proctoring_active']) ? 'Aktif' : 'Nonaktif' ?>
                                    </strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Mode Kamera:</span>
                                    <strong class="<?= !empty($exam['require_dual_camera']) ? 'text-info' : 'text-secondary' ?>">
                                        <?= !empty($exam['require_dual_camera']) ? 'Dual Camera' : 'Kamera Tunggal' ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <a href="<?= base_url("{$basePrefix}/exams/monitoring/{$exam['id']}") ?>" class="btn btn-danger btn-sm w-100 py-2 rounded-3 fw-semibold fs-xs shadow-xs d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-display"></i>
                            <span>Masuk Ruang Live Monitoring</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- All Recent Exams Summary Table -->
<div class="card border border-slate-200 shadow-3xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-journal-text text-primary"></i>
            <span>Daftar Seluruh Jadwal Ujian Pengajar</span>
        </h6>
        <a href="<?= base_url("{$basePrefix}/exams") ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3 fw-medium">
            Kelola Ujian &rarr;
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-slate-50 text-slate-600 fs-3xs text-uppercase fw-semibold border-bottom border-slate-100">
                <tr>
                    <th class="py-2.5 px-3">Kode &amp; Judul Ujian</th>
                    <th class="py-2.5 px-3">Mata Pelajaran</th>
                    <th class="py-2.5 px-3">Durasi</th>
                    <th class="py-2.5 px-3">Status</th>
                    <th class="py-2.5 px-3 text-end">Aksi Pemantauan</th>
                </tr>
            </thead>
            <tbody class="fs-xs text-slate-800">
                <?php if (empty($allRecentExams)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-journal-x fs-3 text-muted d-block mb-1"></i>
                            <span>Belum ada riwayat jadwal ujian yang terdata.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allRecentExams as $exam): ?>
                        <tr>
                            <td class="px-3">
                                <div class="fw-semibold text-slate-900"><?= e($exam['title']) ?></div>
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 font-monospace rounded-pill fs-3xs"><?= e($exam['code']) ?></span>
                            </td>
                            <td class="px-3">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-3xs">
                                    <?= e($exam['subject_name'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-3 text-muted fs-xs">
                                <?= (int)$exam['duration_minutes'] ?> Menit
                            </td>
                            <td class="px-3">
                                <?php if ($exam['status'] === 'published'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill fs-3xs">Siap / Terbit</span>
                                <?php elseif ($exam['status'] === 'scheduled'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-3xs">Terjadwal</span>
                                <?php elseif (in_array($exam['status'], ['ongoing', 'in_progress'], true)): ?>
                                    <span class="badge bg-danger text-white rounded-pill fs-3xs px-2 py-0.5 fw-semibold">Berlangsung</span>
                                <?php elseif ($exam['status'] === 'completed'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs">Selesai</span>
                                <?php elseif ($exam['status'] === 'draft'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill fs-3xs">Draft</span>
                                <?php else: ?>
                                    <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill fs-3xs">Arsip</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-end">
                                <a href="<?= base_url("{$basePrefix}/exams/monitoring/{$exam['id']}") ?>" class="btn btn-xs btn-outline-danger rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 fw-medium">
                                    <i class="bi bi-display"></i>
                                    <span>Pantau</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
