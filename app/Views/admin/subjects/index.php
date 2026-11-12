<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Action Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-book-fill text-primary"></i>
            <span>Mata Pelajaran</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Kelola daftar kurikulum mata pelajaran sekolah, kode mapel, dan relasi bank soal ujian.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/subjects/create') ?>" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs">
            <i class="bi bi-plus-circle me-1"></i> Tambah Mata Pelajaran Baru
        </a>
    </div>
</div>

<!-- Search Card -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form action="<?= base_url('admin/subjects') ?>" method="GET" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-slate-200 border-end-0 text-muted rounded-start-pill ps-3"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-slate-200 border-start-0 ps-0 rounded-end-pill fs-xs" placeholder="Cari nama atau kode mata pelajaran (contoh: MAT-12)..." value="<?= e($search ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-xs">Cari</button>
            <?php if (!empty($search)): ?>
                <a href="<?= base_url('admin/subjects') ?>" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Subjects Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3">#</th>
                    <th class="py-3 px-3">Kode Mapel</th>
                    <th class="py-3 px-3">Nama Mata Pelajaran</th>
                    <th class="py-3 px-3">Deskripsi</th>
                    <th class="py-3 px-3">Bank Soal Terkait</th>
                    <th class="py-3 px-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-book fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Mata Pelajaran</h6>
                                <p class="text-muted fs-xs mb-3">Tidak ditemukan data mata pelajaran yang cocok dengan pencarian Anda.</p>
                                <a href="<?= base_url('admin/subjects/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 shadow-xs">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Mata Pelajaran Baru
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $idx => $s): ?>
                        <tr>
                            <td class="px-3"><?= (($currentPage - 1) * $perPage) + $idx + 1 ?></td>
                            <td class="px-3">
                                <span class="badge rounded-pill bg-primary-subtle text-primary font-monospace fs-xs"><?= e($s['code']) ?></span>
                            </td>
                            <td class="px-3">
                                <span class="fw-bold text-dark fs-6"><?= e($s['name']) ?></span>
                            </td>
                            <td class="px-3 text-muted"><?= e($s['description'] ?? '-') ?></td>
                            <td class="px-3">
                                <span class="badge rounded-pill bg-slate-100 text-slate-700 border"><?= $s['question_banks_count'] ?? 0 ?> Bank Soal</span>
                            </td>
                            <td class="px-3 text-end">
                                <div class="d-flex justify-content-end gap-1.5">
                                    <a href="<?= base_url('admin/subjects/edit/' . $s['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2" onclick="App.confirm('Apakah Anda yakin ingin menghapus mata pelajaran [<?= e($s['name']) ?>]?', () => App.submitPost('<?= base_url('admin/subjects/delete/' . $s['id']) ?>'))">
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
