<?php
// app/Views/admin/exams/index.php
?>

<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-journal-text text-primary"></i>
            <span>Manajemen Ujian Terpusat</span>
        </h4>
        <p class="text-muted fs-3xs mb-0">Kelola jadwal pelaksanaan ujian, kontrol token 6-digit serentak, dan pantau status ujian aktif.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/exams') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3.5 shadow-xs">
            <i class="bi bi-arrow-clockwise me-1"></i> Segarkan Data
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-journal-text fs-5"></i>
                </div>
                <div>
                    <span class="text-muted fs-3xs fw-bold text-uppercase d-block">Total Ujian</span>
                    <h4 class="fw-bold mb-0 text-slate-900 fs-5"><?= $stats['total'] ?? 0 ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-play-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted fs-3xs fw-bold text-uppercase d-block">Sedang Berlangsung</span>
                    <h4 class="fw-bold mb-0 text-warning-emphasis fs-5"><?= $stats['ongoing'] ?? 0 ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info-subtle text-info p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-calendar-check fs-5"></i>
                </div>
                <div>
                    <span class="text-muted fs-3xs fw-bold text-uppercase d-block">Terjadwal</span>
                    <h4 class="fw-bold mb-0 text-info fs-5"><?= $stats['scheduled'] ?? 0 ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-xs rounded-4 bg-primary-subtle border border-primary-subtle h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white p-2.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-key-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-primary fs-3xs fw-bold text-uppercase d-block">Token Aktif</span>
                    <h4 class="fw-bold mb-0 text-primary fs-5"><?= $stats['active_tokens'] ?? 0 ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search Toolbar -->
<div class="card border-0 shadow-xs rounded-4 mb-4 bg-white">
    <div class="card-body p-3">
        <form action="<?= base_url('admin/exams') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 border-slate-200"><i class="bi bi-search text-muted fs-xs"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-start-0 border-slate-200 ps-0 fs-xs" placeholder="Cari kode, judul, atau nama guru..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="subject_id" class="form-select bg-light border-slate-200 fs-xs">
                    <option value="">-- Semua Mata Pelajaran --</option>
                    <?php foreach ($subjects as $sb): ?>
                        <option value="<?= $sb['id'] ?>" <?= ($filters['subject_id'] ?? '') == $sb['id'] ? 'selected' : '' ?>>
                            <?= e($sb['name']) ?> (<?= e($sb['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select bg-light border-slate-200 fs-xs">
                    <option value="">-- Semua Status --</option>
                    <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="scheduled" <?= ($filters['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="ongoing" <?= ($filters['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold flex-fill shadow-xs">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= base_url('admin/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Main Table -->
<div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3" style="width: 50px;">#</th>
                    <th class="py-3 px-3">Kode & Judul Ujian</th>
                    <th class="py-3 px-3">Mata Pelajaran & Guru</th>
                    <th class="py-3 px-3">Jadwal Pelaksanaan</th>
                    <th class="py-3 px-3">Token Ujian</th>
                    <th class="py-3 px-3">Dual Cam AI</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-3 text-end" style="width: 140px;">Aksi Admin</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-journal-x fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Tidak Ada Ujian Ditemukan</h6>
                                <p class="text-muted fs-xs mb-0">Tidak ditemukan jadwal ujian yang sesuai dengan kriteria filter atau pencarian Anda.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $idx => $ex): ?>
                        <tr>
                            <td class="px-3 text-muted fw-bold"><?= ($pagination['page'] - 1) * $pagination['per_page'] + $idx + 1 ?></td>
                            <td class="px-3">
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 font-monospace fs-3xs mb-1"><?= e($ex['exam_code']) ?></span>
                                <div class="fw-semibold text-slate-900 text-truncate" style="max-width: 240px;">
                                    <a href="<?= base_url('admin/exams/show/' . $ex['id']) ?>" class="text-slate-900 text-decoration-none hover-primary">
                                        <?= e($ex['title']) ?>
                                    </a>
                                </div>
                                <small class="text-muted fs-3xs"><?= $ex['duration_minutes'] ?> Menit &bull; KKM: <?= (int)($ex['passing_grade'] ?? 75) ?></small>
                            </td>
                            <td class="px-3">
                                <div class="fw-semibold text-primary"><?= e($ex['subject_name'] ?? '-') ?></div>
                                <small class="text-muted fs-3xs"><i class="bi bi-person me-1"></i><?= e($ex['created_by_name'] ?? '-') ?></small>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($ex['start_time'])): ?>
                                    <div class="text-slate-800 fw-medium fs-xs">
                                        <i class="bi bi-calendar-event me-1 text-primary"></i><?= date('d M Y, H:i', strtotime($ex['start_time'])) ?>
                                    </div>
                                    <div class="text-muted fs-3xs">
                                        s/d <?= date('d M Y, H:i', strtotime($ex['end_time'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-slate-100 text-muted border border-slate-200 rounded-pill">Belum diatur</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($ex['active_token_code'])): ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="font-monospace fw-bold px-2 py-0.5 bg-primary-subtle text-primary rounded-pill border border-primary-subtle fs-3xs">
                                            <?= e($ex['active_token_code']) ?>
                                        </span>
                                        <form action="<?= base_url('admin/exams/token/refresh/' . $ex['id']) ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-xs btn-outline-primary rounded-circle p-1" title="Rilis Token Baru" onclick="return confirm('Generate token baru untuk ujian ini?')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form action="<?= base_url('admin/exams/token/refresh/' . $ex['id']) ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5">
                                            <i class="bi bi-plus me-1"></i> Rilis Token
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <?php if (($ex['require_dual_camera'] ?? 0)): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill"><i class="bi bi-camera-video me-1"></i>Dual Cam</span>
                                <?php else: ?>
                                    <span class="badge bg-slate-100 text-muted border border-slate-200 rounded-pill">Single Cam</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <?php
                                $stClass = [
                                    'draft' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                    'scheduled' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'published' => 'bg-info-subtle text-info border border-info-subtle',
                                    'ongoing' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                    'in_progress' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                    'completed' => 'bg-success-subtle text-success border border-success-subtle',
                                    'archived' => 'bg-slate-100 text-slate-700 border border-slate-200'
                                ];
                                $st = $ex['status'] ?? 'draft';
                                ?>
                                <div class="dropdown">
                                    <button class="btn btn-xs dropdown-toggle rounded-pill px-2.5 py-1 fs-3xs fw-semibold text-uppercase <?= $stClass[$st] ?? 'bg-secondary-subtle text-secondary' ?>" type="button" data-bs-toggle="dropdown">
                                        <?= e($st) ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 fs-xs">
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'draft')">Draft</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'scheduled')">Scheduled</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'published')">Published</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'ongoing')">Ongoing (Aktif)</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'completed')">Completed</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item py-1.5 text-danger" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'archived')">Archived</a></li>
                                    </ul>
                                </div>
                            </td>
                            <td class="px-3 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="<?= base_url('admin/exams/show/' . $ex['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2" title="Detail & Jadwal">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('admin/exams/preview/' . $ex['id']) ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2" title="Pratinjau Ujian">
                                        <i class="bi bi-laptop"></i>
                                    </a>
                                    <a href="<?= base_url('admin/exams/edit/' . $ex['id']) ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2" title="Ubah Jadwal">
                                        <i class="bi bi-calendar-event"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <div class="card-footer bg-white border-top border-slate-100 py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted fs-xs">Menampilkan <?= count($exams) ?> dari <?= $pagination['total'] ?> ujian</span>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                    <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/exams?page=' . $p . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden Quick Status Form -->
<form id="adminQuickStatusForm" method="POST" action="" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="status" id="adminQuickStatusInput" value="">
</form>

<script>
function quickStatusChange(examId, status) {
    if (confirm('Ubah status ujian ke: ' + status.toUpperCase() + '?')) {
        const form = document.getElementById('adminQuickStatusForm');
        form.action = '<?= base_url('admin/exams/status/') ?>' + examId;
        document.getElementById('adminQuickStatusInput').value = status;
        form.submit();
    }
}
</script>
