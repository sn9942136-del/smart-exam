<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Title & Header Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 fs-5">Manajemen Bank Soal</h4>
        <p class="text-muted fs-3xs mb-0">Kelola kumpulan bank soal, butir soal pilihan ganda, benar/salah, dan esai.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('guru/question-banks/template') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-medium">
            <i class="bi bi-file-earmark-arrow-down me-1"></i> Unduh Template CSV
        </a>
        <a href="<?= base_url('guru/question-banks/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs">
            <i class="bi bi-plus-lg me-1"></i> Buat Bank Soal Baru
        </a>
    </div>
</div>

<!-- Summary Metric Badges -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-3xs text-muted fw-semibold">Total Bank Soal</span>
                    <h4 class="fw-bold text-slate-900 mb-0 mt-1 fs-5"><?= (int)($summary['total_banks'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-primary-subtle text-primary p-2 fs-5">
                    <i class="bi bi-collection"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-3xs text-muted fw-semibold">Total Butir Soal</span>
                    <h4 class="fw-bold text-primary mb-0 mt-1 fs-5"><?= (int)($summary['total_questions'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-info-subtle text-info p-2 fs-5">
                    <i class="bi bi-question-diamond"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-3xs text-muted fw-semibold">Bank Soal Aktif</span>
                    <h4 class="fw-bold text-success mb-0 mt-1 fs-5"><?= (int)($summary['active_banks'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-success-subtle text-success p-2 fs-5">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fs-3xs text-muted fw-semibold">Status Draft</span>
                    <h4 class="fw-bold text-warning mb-0 mt-1 fs-5"><?= (int)($summary['draft_banks'] ?? 0) ?></h4>
                </div>
                <div class="rounded-3 bg-warning-subtle text-warning p-2 fs-5">
                    <i class="bi bi-pencil-square"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar Card -->
<div class="card border-0 rounded-4 shadow-xs p-3 bg-white mb-4">
    <form method="GET" action="<?= base_url('guru/question-banks') ?>" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari judul, kode, atau mapel..." value="<?= e($filters['search'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="subject_id" class="form-select form-select-sm">
                <option value="">-- Semua Mata Pelajaran --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ((string)($filters['subject_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>>
                        <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- Semua Status --</option>
                <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Aktif</option>
                <option value="draft" <?= (($filters['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
                <option value="archived" <?= (($filters['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Arsip</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1 rounded-pill fw-medium">
                <i class="bi bi-filter me-1"></i> Filter
            </button>
            <?php if (!empty($filters['search']) || !empty($filters['subject_id']) || !empty($filters['status'])): ?>
                <a href="<?= base_url('guru/question-banks') ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Main Table Card -->
<div class="card border-0 rounded-4 shadow-xs overflow-hidden bg-white">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom">
                <tr>
                    <th class="ps-4" style="width: 15%;">Kode Bank</th>
                    <th style="width: 30%;">Judul & Deskripsi</th>
                    <th style="width: 20%;">Mata Pelajaran</th>
                    <th class="text-center" style="width: 12%;">Jumlah Soal</th>
                    <th class="text-center" style="width: 10%;">Status</th>
                    <th class="text-end pe-4" style="width: 13%;">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($banks)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-semibold">Tidak ada data Bank Soal yang ditemukan.</div>
                            <p class="fs-3xs text-muted mb-3">Coba ubah kata kunci pencarian atau buat bank soal baru.</p>
                            <a href="<?= base_url('guru/question-banks/create') ?>" class="btn btn-xs btn-primary rounded-pill px-3">
                                <i class="bi bi-plus-lg me-1"></i> Buat Bank Soal
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($banks as $bank): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge bg-body-secondary text-dark font-monospace border px-2 py-1">
                                    <?= e($bank['code']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="fw-bold text-decoration-none text-dark d-block hover-primary">
                                    <?= e($bank['title']) ?>
                                </a>
                                <?php if (!empty($bank['description'])): ?>
                                    <span class="text-muted fs-3xs d-block text-truncate" style="max-width: 320px;">
                                        <?= e($bank['description']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold">
                                    <?= e($bank['subject_name'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border px-2.5 py-1.5 fs-3xs fw-bold">
                                    <i class="bi bi-question-circle me-1 text-primary"></i> <?= (int)$bank['total_questions'] ?> Butir
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($bank['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5">Aktif</span>
                                <?php elseif ($bank['status'] === 'draft'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5">Draft</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5">Arsip</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-outline-primary btn-xs" title="Buka Butir Soal">
                                        <i class="bi bi-folder2-open"></i> Kelola
                                    </a>
                                    <a href="<?= base_url('guru/question-banks/edit/' . $bank['id']) ?>" class="btn btn-outline-secondary btn-xs" title="Edit Metadata">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-xs" onclick="App.confirm('Apakah Anda yakin ingin menghapus bank soal ini? Seluruh butir soal di dalamnya juga akan terhapus.', () => App.submitPost('<?= base_url('guru/question-banks/delete/' . $bank['id']) ?>'))" title="Hapus Bank Soal">
                                        <i class="bi bi-trash"></i>
                                    </button>
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
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <span class="fs-xs text-muted">
                Menampilkan halaman <strong><?= $pagination['current_page'] ?></strong> dari <strong><?= $pagination['last_page'] ?></strong> (Total <?= $pagination['total'] ?> data)
            </span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($pagination['current_page'] <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('guru/question-banks?page=' . ($pagination['current_page'] - 1) . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . urlencode($filters['subject_id'] ?? '') . '&status=' . urlencode($filters['status'] ?? '')) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                        <li class="page-item <?= ($pagination['current_page'] === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('guru/question-banks?page=' . $i . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . urlencode($filters['subject_id'] ?? '') . '&status=' . urlencode($filters['status'] ?? '')) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($pagination['current_page'] >= $pagination['last_page']) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('guru/question-banks?page=' . ($pagination['current_page'] + 1) . '&search=' . urlencode($filters['search'] ?? '') . '&subject_id=' . urlencode($filters['subject_id'] ?? '') . '&status=' . urlencode($filters['status'] ?? '')) ?>">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
