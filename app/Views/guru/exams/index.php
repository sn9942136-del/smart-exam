<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Title & Header Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="vui-page-title mb-1 d-flex align-items-center gap-2">
            <span class="vui-icon-wrap" style="width: 32px; height: 32px; font-size: 1rem;">
                <i class="bi bi-journal-bookmark"></i>
            </span>
            <span>Jadwal & Manajemen Ujian</span>
        </h4>
        <p class="vui-text-muted mb-0">Atur pelaksanaan ujian, konfigurasi pengawasan Dual Kamera AI, dan kelola token ujian Smart Exam.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('guru/exams/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-plus-lg"></i>
            <span>Buat Jadwal Ujian Baru</span>
        </a>
    </div>
</div>

<!-- Summary Metric Badges -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card vui-card p-3.5 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-bold text-uppercase d-block tracking-wider">Total Ujian Saya</span>
                    <h4 class="fw-bold text-slate-900 mb-0 mt-1 fs-5"><?= (int)($summary['total_exams'] ?? 0) ?></h4>
                </div>
                <div class="vui-icon-wrap">
                    <i class="bi bi-journal-bookmark"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card vui-card p-3.5 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-bold text-uppercase d-block tracking-wider">Terjadwal & Siap</span>
                    <h4 class="fw-bold text-info mb-0 mt-1 fs-5"><?= (int)(($summary['scheduled_exams'] ?? 0) + ($summary['published_exams'] ?? 0)) ?></h4>
                </div>
                <div class="vui-icon-wrap vui-icon-info">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card vui-card p-3.5 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-bold text-uppercase d-block tracking-wider">Sedang Berlangsung</span>
                    <h4 class="fw-bold text-danger mb-0 mt-1 fs-5"><?= (int)($summary['ongoing_exams'] ?? 0) ?></h4>
                </div>
                <div class="vui-icon-wrap vui-icon-danger">
                    <i class="bi bi-broadcast"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card vui-card p-3.5 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-bold text-uppercase d-block tracking-wider">Telah Selesai</span>
                    <h4 class="fw-bold text-success mb-0 mt-1 fs-5"><?= (int)($summary['completed_exams'] ?? 0) ?></h4>
                </div>
                <div class="vui-icon-wrap vui-icon-success">
                    <i class="bi bi-check2-all"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar Card -->
<div class="card vui-card p-3 mb-4">
    <form method="GET" action="<?= base_url('guru/exams') ?>" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-end-0 border-slate-200 text-muted"><i class="bi bi-search fs-xs"></i></span>
                <input type="text" name="search" class="form-control bg-white border-start-0 border-slate-200 ps-0 fs-xs" placeholder="Cari judul, kode, atau mapel..." value="<?= e($filters['search'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="subject_id" class="form-select form-select-sm border-slate-200 fs-xs">
                <option value="">-- Semua Mata Pelajaran --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ((string)($filters['subject_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>>
                        <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm border-slate-200 fs-xs">
                <option value="">-- Semua Status --</option>
                <option value="draft" <?= (($filters['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= (($filters['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published (Siap)</option>
                <option value="scheduled" <?= (($filters['status'] ?? '') === 'scheduled') ? 'selected' : '' ?>>Scheduled (Terjadwal)</option>
                <option value="ongoing" <?= (($filters['status'] ?? '') === 'ongoing') ? 'selected' : '' ?>>Ongoing (Berlangsung)</option>
                <option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed (Selesai)</option>
                <option value="archived" <?= (($filters['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Archived (Arsip)</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1 rounded-pill fw-semibold shadow-xs d-inline-flex align-items-center justify-content-center gap-1.5">
                <i class="bi bi-filter"></i>
                <span>Filter</span>
            </button>
            <?php if (!empty($filters['search']) || !empty($filters['subject_id']) || !empty($filters['status'])): ?>
                <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Main Table Card -->
<div class="card vui-card overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="ps-4 py-3" style="width: 25%;">Kode & Judul Ujian</th>
                    <th class="py-3" style="width: 18%;">Mata Pelajaran</th>
                    <th class="py-3" style="width: 16%;">Waktu & Durasi</th>
                    <th class="text-center py-3" style="width: 13%;">Soal & Peserta</th>
                    <th class="text-center py-3" style="width: 12%;">Token Aktif</th>
                    <th class="text-center py-3" style="width: 8%;">Status</th>
                    <th class="text-end pe-4 py-3" style="width: 8%;">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                <i class="bi bi-journal-x fs-3"></i>
                            </div>
                            <div class="fw-semibold text-slate-800 fs-6 mb-1">Belum ada Jadwal Ujian yang ditemukan.</div>
                            <p class="fs-xs text-muted mb-3">Mulai buat jadwal ujian baru dan tautkan bank soal serta kelas peserta.</p>
                            <a href="<?= base_url('guru/exams/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-plus-lg"></i>
                                <span>Buat Jadwal Ujian</span>
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 font-monospace rounded-pill px-2.5 py-0.5 mb-1 fs-3xs d-inline-block">
                                    <?= e($exam['code']) ?>
                                </span>
                                <a href="<?= base_url('guru/exams/show/' . $exam['id']) ?>" class="fw-bold text-decoration-none text-slate-900 d-block hover-primary text-truncate" style="max-width: 260px;">
                                    <?= e($exam['title']) ?>
                                </a>
                                <div class="fs-3xs text-muted mt-0.5">
                                    KKM: <strong><?= number_format((float)$exam['passing_grade'], 1) ?></strong>
                                    <?php if (!empty($exam['is_dual_cam_required'])): ?>
                                        &bull; <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0 fs-3xs"><i class="bi bi-camera-video me-1"></i>Dual Cam</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fw-medium d-inline-block text-truncate" style="max-width: 180px;">
                                    <?= e($exam['subject_name'] ?? '-') ?>
                                </span>
                                <div class="fs-3xs text-muted text-capitalize mt-0.5"><?= str_replace('_', ' ', $exam['type']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-clock me-1 text-primary"></i> <?= (int)$exam['duration_minutes'] ?> Menit
                                </div>
                                <?php if (!empty($exam['earliest_schedule'])): ?>
                                    <div class="fs-3xs text-muted">
                                        <?= date('d M Y, H:i', strtotime($exam['earliest_schedule'])) ?> WIB
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill px-2 py-0.5 fs-3xs me-1">
                                    <strong><?= (int)$exam['question_count'] ?></strong> Soal
                                </span>
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill px-2 py-0.5 fs-3xs">
                                    <strong><?= (int)$exam['participant_count'] ?></strong> Siswa
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($exam['active_token'])): ?>
                                    <span class="badge bg-dark text-white font-monospace fs-xs px-2.5 py-1 rounded-pill tracking-wider shadow-xs">
                                        <?= e($exam['active_token']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 fs-3xs">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($exam['status'] === 'published'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fw-medium">Siap</span>
                                <?php elseif ($exam['status'] === 'scheduled'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fw-medium">Terjadwal</span>
                                <?php elseif (in_array($exam['status'], ['ongoing', 'in_progress'], true)): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-medium animate-pulse">Berlangsung</span>
                                <?php elseif ($exam['status'] === 'completed'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-medium">Selesai</span>
                                <?php elseif ($exam['status'] === 'draft'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 fw-medium">Draft</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fw-medium">Arsip</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= base_url('guru/exams/monitoring/' . $exam['id']) ?>" class="btn btn-outline-danger btn-xs" title="Live Monitoring Real-Time">
                                        <i class="bi bi-display me-1"></i> Monitor
                                    </a>
                                    <a href="<?= base_url('guru/exams/show/' . $exam['id']) ?>" class="btn btn-outline-primary btn-xs" title="Kelola Ujian & Token">
                                        <i class="bi bi-gear"></i>
                                    </a>
                                    <a href="<?= base_url('guru/exams/preview/' . $exam['id']) ?>" class="btn btn-outline-info btn-xs" title="Pratinjau Ujian">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('guru/exams/edit/' . $exam['id']) ?>" class="btn btn-outline-secondary btn-xs" title="Edit Ujian">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if (!empty($pagination) && $pagination['last_page'] > 1): ?>
        <div class="card-footer bg-white border-top border-slate-100 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fs-xs text-muted">
                Halaman <strong><?= $pagination['current_page'] ?></strong> dari <strong><?= $pagination['last_page'] ?></strong> (Total <?= (int)$pagination['total'] ?> ujian)
            </span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($pagination['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('guru/exams?page=' . ($pagination['current_page'] - 1) . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <li class="page-item <?= ($pagination['current_page'] === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('guru/exams?page=' . $i . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($pagination['current_page'] >= $pagination['last_page']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('guru/exams?page=' . ($pagination['current_page'] + 1) . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
