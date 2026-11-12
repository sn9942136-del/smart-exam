<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Action Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-shield-check text-primary"></i>
            <span>Role & Hak Akses (RBAC)</span>
        </h4>
        <p class="text-muted fs-3xs mb-0">Kelola hierarki peran pengguna dan matriks hak akses pada setiap modul sistem.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/roles/create') ?>" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs">
            <i class="bi bi-plus-circle me-1"></i> Tambah Role Baru
        </a>
    </div>
</div>

<!-- Roles Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3" style="width: 50px;">#</th>
                    <th class="py-3 px-3">Nama Role</th>
                    <th class="py-3 px-3">Kode Identitas</th>
                    <th class="py-3 px-3">Deskripsi</th>
                    <th class="py-3 px-3">Total Pengguna</th>
                    <th class="py-3 px-3">Permissions</th>
                    <th class="py-3 px-3 text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php foreach ($roles as $idx => $r): ?>
                    <tr>
                        <td class="px-3 text-muted fw-bold"><?= $idx + 1 ?></td>
                        <td class="px-3">
                            <span class="fw-semibold text-slate-900"><?= e($r['display_name']) ?></span>
                            <?php if (in_array($r['name'], ['super_admin', 'admin', 'guru', 'peserta_didik'], true)): ?>
                                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill fs-4xs ms-1">Bawaan</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace rounded-pill fs-3xs"><?= e($r['name']) ?></span>
                        </td>
                        <td class="px-3 text-muted"><?= e($r['description'] ?? '-') ?></td>
                        <td class="px-3">
                            <span class="fw-semibold text-slate-800"><?= $r['users_count'] ?? 0 ?></span> <span class="text-muted fs-3xs">Pengguna</span>
                        </td>
                        <td class="px-3">
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-0.5"><?= $r['permissions_count'] ?? 0 ?> Hak Akses</span>
                        </td>
                        <td class="px-3 text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="<?= base_url('admin/roles/edit/' . $r['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5">
                                    <i class="bi bi-pencil me-1"></i> Edit & Izin
                                </a>
                                <?php if (!in_array($r['name'], ['super_admin', 'admin', 'guru', 'peserta_didik'], true)): ?>
                                    <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2" onclick="App.confirm('Apakah Anda yakin ingin menghapus role [<?= e($r['display_name']) ?>]?', () => App.submitPost('<?= base_url('admin/roles/delete/' . $r['id']) ?>'))" title="Hapus Role">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
