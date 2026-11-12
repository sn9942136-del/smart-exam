<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-3xs text-uppercase fw-semibold d-block mb-1">Hasil Ujian Dirilis</span>
                <h3 class="fw-bold text-primary mb-0 fs-4"><?= (int)($stats['total_results'] ?? 0) ?></h3>
                <small class="text-muted fs-3xs">Telah dinilai & dipublikasikan</small>
            </div>
            <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-file-earmark-check-fill fs-5"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-3xs text-uppercase fw-semibold d-block mb-1">Tuntas / Lulus KKM</span>
                <h3 class="fw-bold text-success mb-0 fs-4"><?= (int)($stats['passed_count'] ?? 0) ?></h3>
                <small class="text-success fs-3xs">Memenuhi standar kelulusan</small>
            </div>
            <div class="rounded-circle bg-success-subtle text-success p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-award-fill fs-5"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100 d-flex flex-row align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-3xs text-uppercase fw-semibold d-block mb-1">Rata-rata Nilai</span>
                <h3 class="fw-bold text-warning-emphasis mb-0 fs-4"><?= number_format((float)($stats['avg_score'] ?? 0), 1) ?></h3>
                <small class="text-muted fs-3xs">Skala 0 - 100</small>
            </div>
            <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-bar-chart-fill fs-5"></i>
            </div>
        </div>
    </div>
</div>

<!-- Page Header & Filter Form -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
                <i class="bi bi-trophy text-primary"></i>
                <span>Daftar Nilai & Hasil Ujian</span>
            </h4>
            <p class="text-muted fs-3xs mb-0">
                Nilai resmi yang telah dirilis oleh guru pengampu atau diumumkan otomatis oleh sistem.
            </p>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="<?= base_url('siswa/results') ?>" class="row g-2 align-items-center">
        <!-- Subject Filter -->
        <div class="col-md-5">
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

        <!-- Search Filter -->
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 border-slate-200"><i class="bi bi-search text-muted fs-xs"></i></span>
                <input type="text" name="search" class="form-control bg-light border-start-0 border-slate-200 ps-0 fs-xs" placeholder="Cari judul ujian atau kode..." value="<?= e($search) ?>">
            </div>
        </div>

        <!-- Filter Action -->
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3.5 w-100 fw-semibold shadow-xs">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if (!empty($selectedSubject) || !empty($search)): ?>
                <a href="<?= base_url('siswa/results') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Results List Card -->
<div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white mb-4">
    <?php if (empty($results)): ?>
        <div class="empty-state py-5 px-3 text-center">
            <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                <i class="bi bi-trophy fs-2"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">Belum Ada Hasil Ujian yang Dirilis</h6>
            <p class="text-muted fs-xs mb-3">Hasil ujian Anda sedang dalam proses pemeriksaan esai atau belum dipublikasikan oleh guru.</p>
            <a href="<?= base_url('siswa/history') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                <i class="bi bi-clock-history me-1"></i> Cek Status di Riwayat Ujian
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-xs">
                <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                    <tr>
                        <th class="py-3 px-3">Ujian & Mata Pelajaran</th>
                        <th class="py-3 px-3">Waktu Selesai</th>
                        <th class="py-3 px-3">Integritas AI</th>
                        <th class="py-3 px-3">Skor Akhir</th>
                        <th class="py-3 px-3">Status Kelulusan</th>
                        <th class="py-3 px-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fs-xs">
                    <?php foreach ($results as $res): ?>
                        <?php 
                        $finalScore = (float)$res['final_score'];
                        $passingGrade = (float)$res['passing_grade'];
                        $isPassed = ($finalScore >= $passingGrade);
                        $integrityScore = (float)($res['proctoring_integrity_score'] ?? 100);
                        ?>
                        <tr>
                            <td class="px-3">
                                <div class="fw-semibold text-slate-900 fs-xs"><?= e($res['exam_title']) ?></div>
                                <div class="text-muted fs-3xs">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill me-1"><?= e($res['subject_name']) ?></span>
                                    <span>Kode: <?= e($res['exam_code']) ?></span> &bull; 
                                    <span>Guru: <?= e($res['teacher_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-3">
                                <span class="text-slate-800 d-block fw-medium">
                                    <?= !empty($res['session_submit_time']) ? date('d M Y, H:i', strtotime($res['session_submit_time'])) : date('d M Y, H:i', strtotime($res['created_at'])) ?> WIB
                                </span>
                                <small class="text-muted fs-3xs">Durasi: <?= (int)$res['duration_minutes'] ?> Menit</small>
                            </td>
                            <td class="px-3">
                                <?php if ($integrityScore >= 85): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-0.5">
                                        <i class="bi bi-shield-check me-1"></i> <?= number_format($integrityScore, 0) ?>%
                                    </span>
                                <?php elseif ($integrityScore >= 60): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-0.5">
                                        <i class="bi bi-shield-exclamation me-1"></i> <?= number_format($integrityScore, 0) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-0.5">
                                        <i class="bi bi-shield-x me-1"></i> <?= number_format($integrityScore, 0) ?>%
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <span class="fw-bold fs-5 <?= $isPassed ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($finalScore, 1) ?>
                                </span>
                                <small class="text-muted fs-3xs d-block">KKM: <?= number_format($passingGrade, 0) ?></small>
                            </td>
                            <td class="px-3">
                                <?php if ($isPassed): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                        <i class="bi bi-check2 me-1"></i> TUNTAS / LULUS
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill">
                                        <i class="bi bi-arrow-repeat me-1"></i> REMEDIAL
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-end">
                                <a href="<?= base_url('siswa/results/show/' . $res['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3 fw-semibold">
                                    <i class="bi bi-eye me-1"></i> Rincian Evaluasi
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
