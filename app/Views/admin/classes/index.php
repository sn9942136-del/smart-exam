<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Action Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-buildings-fill text-primary"></i>
            <span>Manajemen Kelas & Rombel</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Kelola kelompok rombongan belajar siswa, tingkat kelas (X, XI, XII), jurusan, dan wali kelas.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/classes/create') ?>" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs">
            <i class="bi bi-plus-circle me-1"></i> Tambah Kelas Baru
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form action="<?= base_url('admin/classes') ?>" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-slate-200 border-end-0 text-muted rounded-start-pill ps-3"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-slate-200 border-start-0 ps-0 rounded-end-pill fs-xs" placeholder="Cari nama kelas, jurusan, wali kelas..." value="<?= e($search ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="academic_year_id" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Tahun Ajaran --</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= ($yearFilter === (int)$y['id']) ? 'selected' : '' ?>>
                        T.A. <?= e($y['name']) ?> <?= $y['is_active'] ? '(Aktif)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="grade_level" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Tingkat --</option>
                <option value="10" <?= ($gradeFilter === '10') ? 'selected' : '' ?>>Kelas 10 (X)</option>
                <option value="11" <?= ($gradeFilter === '11') ? 'selected' : '' ?>>Kelas 11 (XI)</option>
                <option value="12" <?= ($gradeFilter === '12') ? 'selected' : '' ?>>Kelas 12 (XII)</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-xs">Filter</button>
            <?php if (!empty($search) || !empty($yearFilter) || !empty($gradeFilter)): ?>
                <a href="<?= base_url('admin/classes') ?>" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Classes Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3">#</th>
                    <th class="py-3 px-3">Nama Rombel</th>
                    <th class="py-3 px-3">Tingkat / Jurusan</th>
                    <th class="py-3 px-3">Tahun Ajaran</th>
                    <th class="py-3 px-3">Wali Kelas</th>
                    <th class="py-3 px-3">Total Siswa</th>
                    <th class="py-3 px-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($classes)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-buildings fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Kelas / Rombel</h6>
                                <p class="text-muted fs-xs mb-3">Tidak ditemukan data kelas yang cocok dengan kriteria filter atau pencarian Anda.</p>
                                <a href="<?= base_url('admin/classes/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 shadow-xs">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Kelas Baru
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classes as $idx => $c): ?>
                        <tr>
                            <td class="px-3"><?= (($currentPage - 1) * $perPage) + $idx + 1 ?></td>
                            <td class="px-3">
                                <span class="fw-bold text-dark fs-6"><?= e($c['name']) ?></span>
                            </td>
                            <td class="px-3">
                                <span class="badge rounded-pill bg-primary-subtle text-primary">Kelas <?= e($c['grade_level']) ?></span>
                                <span class="badge rounded-pill bg-slate-100 text-slate-700 border ms-1"><?= e($c['major'] ?? '-') ?></span>
                            </td>
                            <td class="px-3 text-muted">
                                T.A. <?= e($c['academic_year_name']) ?>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($c['homeroom_teacher_name'])): ?>
                                    <span class="fw-semibold text-dark d-block"><?= e($c['homeroom_teacher_name']) ?></span>
                                    <small class="text-muted font-monospace">NIP: <?= e($c['homeroom_teacher_nip'] ?? '-') ?></small>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Belum Ditentukan</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <a href="<?= base_url('admin/classes/' . $c['id'] . '/students') ?>" class="badge rounded-pill bg-success-subtle text-success text-decoration-none d-inline-flex align-items-center gap-1 py-1 px-2.5 border border-success-subtle shadow-2xs" title="Klik untuk mengelola siswa di kelas <?= e($c['name']) ?>">
                                    <i class="bi bi-people-fill"></i>
                                    <span><?= $c['students_count'] ?? 0 ?> Siswa</span>
                                    <i class="bi bi-chevron-right fs-3xs opacity-75 ms-0.5"></i>
                                </a>
                            </td>
                            <td class="px-3 text-end">
                                <div class="d-flex justify-content-end gap-1.5">
                                    <a href="<?= base_url('admin/classes/' . $c['id'] . '/students') ?>" class="btn btn-xs btn-outline-info rounded-pill px-2.5 fw-semibold" title="Kelola Siswa di Kelas Ini">
                                        <i class="bi bi-people-fill me-1"></i> Kelola Siswa
                                    </a>
                                    <a href="<?= base_url('admin/classes/edit/' . $c['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2" onclick="App.confirm('Apakah Anda yakin ingin menghapus kelas [<?= e($c['name']) ?>]?', () => App.submitPost('<?= base_url('admin/classes/delete/' . $c['id']) ?>'))">
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
