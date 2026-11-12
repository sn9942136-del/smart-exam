<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Filter Toolbar -->
<div class="card border rounded-3 shadow-sm p-3 bg-white mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
                <i class="bi bi-clock-history text-primary"></i>
                <span>Riwayat & Status Ujian</span>
            </h4>
            <p class="text-muted fs-3xs mb-0">
                Daftar rekaman seluruh ujian yang telah Anda ikuti beserta status pengerjaan dan penilaiannya.
            </p>
        </div>
        <span class="badge bg-secondary-subtle text-secondary fs-3xs px-2.5 py-1.5 rounded-2">
            <i class="bi bi-journal-text me-1"></i> <?= count($history) ?> Rekaman Ujian
        </span>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="<?= base_url('siswa/history') ?>" class="row g-2 align-items-center">
        <!-- Subject Filter -->
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-end-0"><i class="bi bi-book text-muted"></i></span>
                <select name="subject_id" class="form-select form-select-sm border-start-0" onchange="this.form.submit()">
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
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari judul ujian / kode..." value="<?= e($search) ?>">
            </div>
        </div>

        <!-- Filter Action -->
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary rounded-2 px-3 w-100 fw-medium">
                <i class="bi bi-filter me-1"></i> Cari
            </button>
            <?php if (!empty($selectedSubject) || !empty($search)): ?>
                <a href="<?= base_url('siswa/history') ?>" class="btn btn-sm btn-outline-secondary rounded-2 px-2.5" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- History Table / Cards -->
<div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
    <?php if (empty($history)): ?>
        <div class="text-center py-5">
            <div class="rounded-circle bg-light text-muted p-4 d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                <i class="bi bi-journal-x fs-1"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">Belum Ada Riwayat Ujian</h6>
            <p class="text-muted fs-xs mb-3">Anda belum pernah mengikuti atau menyelesaikan ujian pada filter yang dipilih.</p>
            <a href="<?= base_url('siswa/exams') ?>" class="btn btn-sm btn-primary rounded-pill px-4">
                <i class="bi bi-card-checklist me-1"></i> Lihat Ujian Tersedia
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-xs">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Ujian & Mata Pelajaran</th>
                        <th>Waktu Pelaksanaan</th>
                        <th>Status Sesi</th>
                        <th>Integritas AI</th>
                        <th>Nilai & Kelulusan</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <?php 
                        $isReleased = (!empty($h['result_id']) && ($h['result_status'] === 'published' || (int)$h['show_result_immediately'] === 1));
                        $isPassed = $isReleased && ((float)$h['final_score'] >= (float)$h['passing_grade']);
                        $integrityScore = (float)($h['proctoring_integrity_score'] ?? $h['session_integrity_score'] ?? 100);
                        ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark fs-sm"><?= e($h['title']) ?></div>
                                <div class="text-muted fs-3xs">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1"><?= e($h['subject_name']) ?></span>
                                    <span>Kode: <?= e($h['exam_code']) ?></span> &bull; 
                                    <span>Guru: <?= e($h['teacher_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-dark d-block fw-semibold">
                                    <?= !empty($h['session_submit_time']) ? date('d M Y, H:i', strtotime($h['session_submit_time'])) : (!empty($h['start_time']) ? date('d M Y, H:i', strtotime($h['start_time'])) : '-') ?> WIB
                                </span>
                                <small class="text-muted">Durasi: <?= (int)$h['duration_minutes'] ?> Menit</small>
                            </td>
                            <td>
                                <?php if (!empty($h['result_id']) || $h['session_status'] === 'submitted' || $h['participant_status'] === 'completed'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle-fill me-1"></i> Selesai
                                    </span>
                                <?php elseif ($h['session_status'] === 'ongoing'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-hourglass-split me-1"></i> Sedang Dikerjakan
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        <?= e($h['participant_status'] ?? 'Terdaftar') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($integrityScore >= 85): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-shield-check me-1"></i> <?= number_format($integrityScore, 0) ?>% (Aman)
                                    </span>
                                <?php elseif ($integrityScore >= 60): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-shield-exclamation me-1"></i> <?= number_format($integrityScore, 0) ?>% (Peringatan)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-shield-x me-1"></i> <?= number_format($integrityScore, 0) ?>% (Kritis)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isReleased): ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="fw-extrabold text-primary fs-sm"><?= number_format((float)$h['final_score'], 1) ?></span>
                                        <?php if ($isPassed): ?>
                                            <span class="badge bg-success fs-4xs">Lulus</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger fs-4xs">Remedial</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted fs-3xs">KKM: <?= number_format((float)$h['passing_grade'], 0) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border fs-3xs">
                                        <i class="bi bi-hourglass me-1"></i> Proses Penilaian
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if ($isReleased): ?>
                                    <a href="<?= base_url('siswa/results/show/' . $h['result_id']) ?>" class="btn btn-xs btn-primary-gradient rounded-pill px-3 fw-semibold">
                                        <i class="bi bi-trophy me-1"></i> Lihat Rincian
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_url('siswa/exams/show/' . $h['exam_id']) ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5">
                                        <i class="bi bi-info-circle me-1"></i> Detail
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
