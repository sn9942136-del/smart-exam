<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Action Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-calendar2-range text-primary"></i>
            <span>Semester</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Kelola semester (Ganjil/Genap) pada masing-masing tahun ajaran dan tetapkan semester aktif.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/semesters/create') ?>" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs">
            <i class="bi bi-plus-circle me-1"></i> Tambah Semester Baru
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form action="<?= base_url('admin/semesters') ?>" method="GET" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-slate-200 border-end-0 text-muted rounded-start-pill ps-3"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-slate-200 border-start-0 ps-0 rounded-end-pill fs-xs" placeholder="Cari semester atau tahun ajaran..." value="<?= e($search ?? '') ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="academic_year_id" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Tahun Ajaran --</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= ($yearFilter === (int)$y['id']) ? 'selected' : '' ?>>
                        T.A. <?= e($y['name']) ?> <?= $y['is_active'] ? '(Aktif)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-xs">Filter</button>
            <?php if (!empty($search) || !empty($yearFilter)): ?>
                <a href="<?= base_url('admin/semesters') ?>" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Semesters Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3">#</th>
                    <th class="py-3 px-3">Nama Semester</th>
                    <th class="py-3 px-3">Kode</th>
                    <th class="py-3 px-3">Tahun Ajaran</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($semesters)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-calendar2-range fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Semester</h6>
                                <p class="text-muted fs-xs mb-3">Tidak ditemukan data semester yang cocok dengan pencarian Anda.</p>
                                <a href="<?= base_url('admin/semesters/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 shadow-xs">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Semester Baru
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($semesters as $idx => $s): ?>
                        <tr>
                            <td class="px-3"><?= (($currentPage - 1) * $perPage) + $idx + 1 ?></td>
                            <td class="px-3">
                                <span class="fw-bold text-dark fs-6">Semester <?= e($s['name']) ?></span>
                            </td>
                            <td class="px-3">
                                <span class="badge rounded-pill bg-primary-subtle text-primary font-monospace"><?= e($s['code']) ?></span>
                            </td>
                            <td class="px-3">
                                <span class="fw-semibold text-dark">T.A. <?= e($s['academic_year_name']) ?></span>
                            </td>
                            <td class="px-3">
                                <?php if ($s['is_active']): ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                                        <i class="bi bi-check2-circle me-1"></i>Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-slate-100 text-slate-700 px-2.5 py-1">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-end">
                                <div class="d-flex justify-content-end gap-1.5">
                                    <?php if (!$s['is_active']): ?>
                                        <button type="button" class="btn btn-xs btn-outline-success rounded-pill px-2.5" title="Set sebagai semester aktif" onclick="App.submitPost('<?= base_url('admin/semesters/set-active/' . $s['id']) ?>')">
                                            <i class="bi bi-check2"></i> Aktifkan
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= base_url('admin/semesters/edit/' . $s['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2" onclick="App.confirm('Apakah Anda yakin ingin menghapus semester [<?= e($s['name']) ?>]?', () => App.submitPost('<?= base_url('admin/semesters/delete/' . $s['id']) ?>'))">
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

    <!-- Pagination -->
    <?php if ($totalItems > 0): ?>
        <div class="px-3 pb-3">
            <?php include __DIR__ . '/../../components/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>
