<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Title & Header Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 fs-5">Jadwal & Manajemen Ujian</h4>
        <p class="text-muted fs-xs mb-0">Atur pelaksanaan ujian, aturan pengawasan Dual Kamera AI, dan rilis token ujian.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('guru/exams/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs">
            <i class="bi bi-plus-lg me-1.5"></i> Buat Jadwal Ujian Baru
        </a>
    </div>
</div>

<!-- Summary Metric Badges -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-xs rounded-4 p-3.5 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-medium">Total Ujian Saya</span>
                    <h4 class="fw-bold text-slate-900 mb-0 mt-1 fs-5"><?= (int)($summary['total_exams'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-primary-subtle text-primary p-2.5 fs-5">
                    <i class="bi bi-journal-bookmark"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-xs rounded-4 p-3.5 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-medium">Terjadwal & Siap</span>
                    <h4 class="fw-bold text-info mb-0 mt-1 fs-5"><?= (int)(($summary['scheduled_exams'] ?? 0) + ($summary['published_exams'] ?? 0)) ?></h4>
                </div>
                <div class="rounded-3 bg-info-subtle text-info p-2.5 fs-5">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-xs rounded-4 p-3.5 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-medium">Sedang Berlangsung</span>
                    <h4 class="fw-bold text-danger mb-0 mt-1 fs-5"><?= (int)($summary['ongoing_exams'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-danger-subtle text-danger p-2.5 fs-5">
                    <i class="bi bi-broadcast"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-xs rounded-4 p-3.5 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-xs text-muted fw-medium">Telah Selesai</span>
                    <h4 class="fw-bold text-success mb-0 mt-1 fs-5"><?= (int)($summary['completed_exams'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-success-subtle text-success p-2.5 fs-5">
                    <i class="bi bi-check2-all"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar Card -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form method="GET" action="<?= base_url('guru/exams') ?>" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-end-0 border-slate-200"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 border-slate-200 ps-0" placeholder="Cari judul, kode, atau mapel..." value="<?= e($filters['search'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="subject_id" class="form-select form-select-sm border-slate-200">
                <option value="">-- Semua Mata Pelajaran --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ((string)($filters['subject_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>>
                        <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm border-slate-200">
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
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1 rounded-pill fw-semibold shadow-xs">
                <i class="bi bi-filter me-1"></i> Filter
            </button>
            <?php if (!empty($filters['search']) || !empty($filters['subject_id']) || !empty($filters['status'])): ?>
                <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Main Table Card -->
<div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="ps-4" style="width: 25%;">Kode & Judul Ujian</th>
                    <th style="width: 18%;">Mata Pelajaran</th>
                    <th style="width: 15%;">Waktu & Durasi</th>
                    <th class="text-center" style="width: 12%;">Soal & Peserta</th>
                    <th class="text-center" style="width: 12%;">Token Aktif</th>
                    <th class="text-center" style="width: 8%;">Status</th>
                    <th class="text-end pe-4" style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-semibold">Belum ada Jadwal Ujian yang ditemukan.</div>
                            <p class="fs-xs text-muted mb-3">Mulai buat jadwal ujian baru dan tautkan bank soal serta kelas peserta.</p>
                            <a href="<?= base_url('guru/exams/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs">
                                <i class="bi bi-plus-lg me-1.5"></i> Buat Jadwal Ujian
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-slate-100 text-slate-700 font-monospace rounded-pill px-2 py-0.5 mb-1 fs-xs">
                                    <?= e($exam['code']) ?>
                                </span>
                                <a href="<?= base_url('guru/exams/show/' . $exam['id']) ?>" class="fw-bold text-decoration-none text-dark d-block hover-primary">
                                    <?= e($exam['title']) ?>
                                </a>
                                <div class="fs-xs text-muted">
                                    KKM: <strong><?= number_format((float)$exam['passing_grade'], 1) ?></strong>
                                    <?php if (!empty($exam['is_dual_cam_required'])): ?>
                                        &bull; <span class="text-danger fw-semibold"><i class="bi bi-camera-video me-0.5"></i> Dual Cam AI</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-medium d-inline-block text-truncate" style="max-width: 180px;">
                                    <?= e($exam['subject_name'] ?? '-') ?>
                                </span>
                                <div class="fs-xs text-muted text-capitalize mt-0.5"><?= str_replace('_', ' ', $exam['type']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-clock me-1 text-primary"></i> <?= (int)$exam['duration_minutes'] ?> Menit
                                </div>
                                <?php if (!empty($exam['earliest_schedule'])): ?>
                                    <div class="fs-xs text-muted">
                                        <?= date('d M Y, H:i', strtotime($exam['earliest_schedule'])) ?> WIB
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-slate-100 text-slate-700 rounded-pill px-2 py-0.5 fs-xs me-1">
                                    <strong><?= (int)$exam['question_count'] ?></strong> Soal
                                </span>
                                <span class="badge bg-slate-100 text-slate-700 rounded-pill px-2 py-0.5 fs-xs">
                                    <strong><?= (int)$exam['participant_count'] ?></strong> Siswa
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($exam['active_token'])): ?>
                                    <span class="badge bg-dark text-white font-monospace fs-xs px-2.5 py-1 rounded-pill tracking-wider shadow-xs">
                                        <?= e($exam['active_token']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 fs-xs">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($exam['status'] === 'published'): ?>
                                    <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1 fw-medium">Siap</span>
                                <?php elseif ($exam['status'] === 'scheduled'): ?>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-medium">Terjadwal</span>
                                <?php elseif (in_array($exam['status'], ['ongoing', 'in_progress'], true)): ?>
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-medium animate-pulse">Berlangsung</span>
                                <?php elseif ($exam['status'] === 'completed'): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-medium">Selesai</span>
                                <?php elseif ($exam['status'] === 'draft'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1 fw-medium">Draft</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1 fw-medium">Arsip</span>
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
        <div class="p-3 border-top border-slate-100 d-flex justify-content-between align-items-center">
            <span class="fs-xs text-muted">
                Halaman <strong><?= $pagination['current_page'] ?></strong> dari <strong><?= $pagination['last_page'] ?></strong> (Total <?= $pagination['total'] ?> ujian)
            </span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($pagination['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('guru/exams?page=' . ($pagination['current_page'] - 1)) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <li class="page-item <?= ($pagination['current_page'] === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('guru/exams?page=' . $i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($pagination['current_page'] >= $pagination['last_page']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('guru/exams?page=' . ($pagination['current_page'] + 1)) ?>">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
