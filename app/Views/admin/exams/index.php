<?php
// app/Views/admin/exams/index.php
?>

<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="vui-page-title mb-1 d-flex align-items-center gap-2">
            <span class="vui-icon-wrap" style="width: 32px; height: 32px; font-size: 1rem;">
                <i class="bi bi-journal-text"></i>
            </span>
            <span>Manajemen Ujian Terpusat</span>
        </h4>
        <p class="vui-text-muted mb-0">Kelola jadwal pelaksanaan ujian, kontrol token 6-digit serentak, dan pantau status ujian aktif Smart Exam.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/exams') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3.5 shadow-xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Segarkan Data</span>
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card vui-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="vui-icon-wrap">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div>
                    <span class="text-muted fs-3xs fw-bold text-uppercase d-block tracking-wider">Total Ujian</span>
                    <h4 class="fw-bold mb-0 text-slate-900 fs-5"><?= (int)($stats['total'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card vui-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="vui-icon-wrap vui-icon-warning">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <div>
                    <span class="text-muted fs-3xs fw-bold text-uppercase d-block tracking-wider">Sedang Berlangsung</span>
                    <h4 class="fw-bold mb-0 text-warning-emphasis fs-5"><?= (int)($stats['ongoing'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card vui-card h-100 p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="vui-icon-wrap vui-icon-info">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <span class="text-muted fs-3xs fw-bold text-uppercase d-block tracking-wider">Terjadwal</span>
                    <h4 class="fw-bold mb-0 text-info fs-5"><?= (int)($stats['scheduled'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card vui-card h-100 p-3 bg-primary-subtle bg-opacity-25 border-primary-subtle">
            <div class="d-flex align-items-center gap-3">
                <div class="vui-icon-wrap bg-primary text-white">
                    <i class="bi bi-key-fill"></i>
                </div>
                <div>
                    <span class="text-primary fs-3xs fw-bold text-uppercase d-block tracking-wider">Token Aktif</span>
                    <h4 class="fw-bold mb-0 text-primary fs-5"><?= (int)($stats['active_tokens'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search Toolbar -->
<div class="card vui-card mb-4">
    <div class="card-body p-3">
        <form action="<?= base_url('admin/exams') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-slate-50 border-end-0 border-slate-200 text-muted"><i class="bi bi-search fs-xs"></i></span>
                    <input type="text" name="search" class="form-control bg-white border-start-0 border-slate-200 ps-0 fs-xs" placeholder="Cari kode, judul, atau nama guru..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="subject_id" class="form-select form-select-sm border-slate-200 fs-xs">
                    <option value="">-- Semua Mata Pelajaran --</option>
                    <?php foreach ($subjects as $sb): ?>
                        <option value="<?= $sb['id'] ?>" <?= ((string)($filters['subject_id'] ?? '') === (string)$sb['id']) ? 'selected' : '' ?>>
                            <?= e($sb['name']) ?> (<?= e($sb['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm border-slate-200 fs-xs">
                    <option value="">-- Semua Status --</option>
                    <option value="draft" <?= (($filters['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="scheduled" <?= (($filters['status'] ?? '') === 'scheduled') ? 'selected' : '' ?>>Scheduled (Terjadwal)</option>
                    <option value="published" <?= (($filters['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published (Siap)</option>
                    <option value="ongoing" <?= (($filters['status'] ?? '') === 'ongoing') ? 'selected' : '' ?>>Ongoing (Berlangsung)</option>
                    <option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed (Selesai)</option>
                    <option value="archived" <?= (($filters['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Archived (Arsip)</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold flex-fill shadow-xs d-inline-flex align-items-center justify-content-center gap-1.5">
                    <i class="bi bi-filter"></i>
                    <span>Filter</span>
                </button>
                <?php if (!empty($filters['search']) || !empty($filters['subject_id']) || !empty($filters['status'])): ?>
                    <a href="<?= base_url('admin/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Reset Filter">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Main Table Card -->
<div class="card vui-card overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="ps-3 py-3" style="width: 45px;">#</th>
                    <th class="py-3" style="width: 25%;">Kode & Judul Ujian</th>
                    <th class="py-3" style="width: 18%;">Mata Pelajaran & Guru</th>
                    <th class="py-3" style="width: 17%;">Jadwal Pelaksanaan</th>
                    <th class="py-3 text-center" style="width: 13%;">Token Ujian</th>
                    <th class="py-3 text-center" style="width: 11%;">Pengawasan</th>
                    <th class="py-3 text-center" style="width: 8%;">Status</th>
                    <th class="py-3 pe-3 text-end" style="width: 8%;">Aksi Admin</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                    <i class="bi bi-journal-x fs-3"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Tidak Ada Ujian Ditemukan</h6>
                                <p class="text-muted fs-xs mb-0">Tidak ditemukan jadwal ujian yang sesuai dengan kriteria filter atau pencarian Anda.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $idx => $ex): ?>
                        <tr>
                            <td class="ps-3 text-muted fw-bold"><?= ($pagination['page'] - 1) * $pagination['per_page'] + $idx + 1 ?></td>
                            <td>
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 font-monospace fs-3xs rounded-pill px-2 py-0.5 mb-1 d-inline-block">
                                    <?= e($ex['exam_code']) ?>
                                </span>
                                <div class="fw-semibold text-slate-900 text-truncate" style="max-width: 240px;">
                                    <a href="<?= base_url('admin/exams/show/' . $ex['id']) ?>" class="text-slate-900 text-decoration-none hover-primary">
                                        <?= e($ex['title']) ?>
                                    </a>
                                </div>
                                <div class="text-muted fs-3xs">
                                    <i class="bi bi-clock me-0.5"></i> <?= (int)$ex['duration_minutes'] ?> Menit &bull; KKM: <strong><?= (int)($ex['passing_grade'] ?? 75) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary text-truncate" style="max-width: 180px;"><?= e($ex['subject_name'] ?? '-') ?></div>
                                <div class="text-muted fs-3xs text-truncate" style="max-width: 180px;">
                                    <i class="bi bi-person me-0.5"></i> <?= e($ex['created_by_name'] ?? '-') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($ex['start_time'])): ?>
                                    <div class="text-slate-800 fw-medium fs-xs">
                                        <i class="bi bi-calendar-event me-1 text-primary"></i><?= date('d M Y, H:i', strtotime($ex['start_time'])) ?>
                                    </div>
                                    <div class="text-muted fs-3xs">
                                        s/d <?= date('d M Y, H:i', strtotime($ex['end_time'])) ?> WIB
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-slate-100 text-muted border border-slate-200 rounded-pill px-2.5 py-1">Belum diatur</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($ex['active_token_code'])): ?>
                                    <div class="d-inline-flex align-items-center gap-1.5">
                                        <span class="font-monospace fw-bold px-2.5 py-1 bg-dark text-white rounded-pill fs-xs shadow-xs tracking-wider">
                                            <?= e($ex['active_token_code']) ?>
                                        </span>
                                        <form action="<?= base_url('admin/exams/token/refresh/' . $ex['id']) ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-xs btn-outline-primary rounded-circle p-1" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;" title="Rilis Token Baru" onclick="return confirm('Generate token baru untuk ujian ini?')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form action="<?= base_url('admin/exams/token/refresh/' . $ex['id']) ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1">
                                            <i class="bi bi-key me-1"></i> Rilis Token
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($ex['require_dual_camera'])): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-medium"><i class="bi bi-camera-video me-1"></i>Dual Cam</span>
                                <?php else: ?>
                                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200 rounded-pill px-2.5 py-1">Single Cam</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $stClass = [
                                    'draft' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                    'scheduled' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'published' => 'bg-info-subtle text-info border border-info-subtle',
                                    'ongoing' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle animate-pulse',
                                    'in_progress' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle animate-pulse',
                                    'completed' => 'bg-success-subtle text-success border border-success-subtle',
                                    'archived' => 'bg-slate-100 text-slate-700 border border-slate-200'
                                ];
                                $st = $ex['status'] ?? 'draft';
                                ?>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-xs dropdown-toggle rounded-pill px-2.5 py-1 fs-3xs fw-semibold text-uppercase <?= $stClass[$st] ?? 'bg-secondary-subtle text-secondary' ?>" type="button" data-bs-toggle="dropdown">
                                        <?= e($st) ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-xs border-slate-100 rounded-3 fs-xs">
                                        <li><h6 class="dropdown-header">Ubah Status Cepat</h6></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'draft')"><i class="bi bi-pencil text-secondary me-2"></i>Draft</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'scheduled')"><i class="bi bi-calendar-check text-primary me-2"></i>Scheduled</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'published')"><i class="bi bi-send-check text-info me-2"></i>Published</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'ongoing')"><i class="bi bi-play-circle-fill text-warning me-2"></i>Ongoing (Aktif)</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'completed')"><i class="bi bi-check-circle-fill text-success me-2"></i>Completed</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item py-1.5 text-danger" href="javascript:void(0)" onclick="quickStatusChange(<?= $ex['id'] ?>, 'archived')"><i class="bi bi-archive me-2"></i>Archived</a></li>
                                    </ul>
                                </div>
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= base_url('admin/exams/show/' . $ex['id']) ?>" class="btn btn-xs btn-outline-primary" title="Detail & Jadwal">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('admin/exams/preview/' . $ex['id']) ?>" class="btn btn-xs btn-outline-info" title="Pratinjau Ujian">
                                        <i class="bi bi-laptop"></i>
                                    </a>
                                    <a href="<?= base_url('admin/exams/edit/' . $ex['id']) ?>" class="btn btn-xs btn-outline-secondary" title="Ubah Jadwal">
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
    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <div class="card-footer bg-white border-top border-slate-100 py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted fs-xs">Menampilkan <strong><?= count($exams) ?></strong> dari <strong><?= (int)$pagination['total'] ?></strong> ujian</span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($pagination['page'] <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= base_url('admin/exams?page=' . ($pagination['page'] - 1) . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>">Sebelumnya</a>
                </li>
                <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                    <li class="page-item <?= ($p == $pagination['page']) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/exams?page=' . $p . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagination['page'] >= $pagination['total_pages']) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= base_url('admin/exams?page=' . ($pagination['page'] + 1) . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . ($filters['subject_id'] ?? '') . '&status=' . ($filters['status'] ?? '')) ?>">Selanjutnya</a>
                </li>
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
