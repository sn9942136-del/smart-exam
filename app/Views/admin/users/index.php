<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Action Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-people-fill text-primary"></i>
            <span>Manajemen Pengguna</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Kelola data akun Admin, Guru, dan Peserta Didik beserta hak akses dan status aktivasi.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/users/create') ?>" class="btn btn-primary btn-sm rounded-pill px-3.5 fw-semibold shadow-xs">
            <i class="bi bi-person-plus me-1"></i> Tambah Pengguna Baru
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form action="<?= base_url('admin/users') ?>" method="GET" class="row g-2 align-items-center">
        <input type="hidden" name="sort" value="<?= e($sort ?? 'name') ?>">
        <input type="hidden" name="order" value="<?= e($order ?? 'ASC') ?>">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-slate-200 border-end-0 text-muted rounded-start-pill ps-3"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-slate-200 border-start-0 ps-0 rounded-end-pill fs-xs" placeholder="Cari nama, email, username, NIP/NISN..." value="<?= e($search ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Role --</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['name']) ?>" <?= ($roleFilter === $r['name']) ? 'selected' : '' ?>>
                        <?= e($r['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Status --</option>
                <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Nonaktif</option>
                <option value="banned" <?= ($statusFilter === 'banned') ? 'selected' : '' ?>>Ditangguhkan (Banned)</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-xs">Filter</button>
            <?php if (!empty($search) || !empty($roleFilter) || !empty($statusFilter)): ?>
                <a href="<?= base_url('admin/users') ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset Filter"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="usersTable">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3">#</th>
                    <th class="py-3 px-3">
                        <a href="<?= base_url('admin/users?' . http_build_query(array_merge($_GET ?? [], [
                            'sort'  => 'name',
                            'order' => ($sort === 'name' && $order === 'ASC') ? 'DESC' : 'ASC',
                            'page'  => 1
                        ]))) ?>" 
                           class="text-decoration-none text-slate-700 d-inline-flex align-items-center gap-1.5 fw-semibold" 
                           title="Urutkan berdasarkan abjad nama (saat ini: <?= ($sort === 'name' && $order === 'DESC') ? 'Z - A' : 'A - Z (Abjad)' ?>)">
                            <span>Pengguna</span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-4xs py-0.5 px-1.5 rounded-pill">
                                <i class="bi bi-sort-alpha-<?= ($sort === 'name' && $order === 'DESC') ? 'up-alt' : 'down' ?> me-0.5"></i><?= ($sort === 'name' && $order === 'DESC') ? 'Z-A' : 'A-Z' ?>
                            </span>
                        </a>
                    </th>
                    <th class="py-3 px-3">NIP / NISN</th>
                    <th class="py-3 px-3">Role</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-3">Login Terakhir</th>
                    <th class="py-3 px-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-people fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Tidak Ditemukan Pengguna</h6>
                                <p class="text-muted fs-xs mb-3">Tidak ditemukan data pengguna yang cocok dengan kriteria filter atau pencarian Anda.</p>
                                <a href="<?= base_url('admin/users/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 shadow-xs">
                                    <i class="bi bi-person-plus me-1"></i> Tambah Pengguna Baru
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $index => $u): ?>
                        <tr>
                            <td class="px-3"><?= (($currentPage - 1) * $perPage) + $index + 1 ?></td>
                            <td class="px-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <span class="fw-semibold text-dark d-block"><?= e($u['name']) ?></span>
                                        <small class="text-muted"><?= e($u['email']) ?> &bull; @<?= e($u['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3">
                                <span class="font-monospace text-dark"><?= e($u['nip_nisn'] ?? '-') ?></span>
                            </td>
                            <td class="px-3">
                                <?php
                                $roleDisplay = $u['role_display_names'] ?? 'Peserta Didik';
                                $roleClass = match($u['role_names'] ?? '') {
                                    'super_admin' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    'admin' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'guru' => 'bg-info-subtle text-info border border-info-subtle',
                                    default => 'bg-success-subtle text-success border border-success-subtle'
                                };
                                ?>
                                <span class="badge rounded-pill <?= $roleClass ?>"><?= e($roleDisplay) ?></span>
                            </td>
                            <td class="px-3">
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                                <?php elseif ($u['status'] === 'inactive'): ?>
                                    <span class="badge rounded-pill bg-slate-100 text-slate-700"><i class="bi bi-dash-circle me-1"></i>Nonaktif</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger"><i class="bi bi-slash-circle me-1"></i>Banned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-muted">
                                <?= !empty($u['last_login_at']) ? date('d M Y, H:i', strtotime($u['last_login_at'])) : '<span class="text-muted">Belum pernah</span>' ?>
                            </td>
                            <td class="px-3 text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border-0 rounded-pill p-1.5" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 fs-xs">
                                        <li>
                                            <a class="dropdown-item py-1.5" href="<?= base_url('admin/users/edit/' . $u['id']) ?>">
                                                <i class="bi bi-pencil me-2 text-primary"></i> Edit Profil & Role
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-1.5" href="<?= base_url('admin/users/reset-password/' . $u['id']) ?>">
                                                <i class="bi bi-key me-2 text-warning"></i> Reset Kata Sandi
                                            </a>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item py-1.5" onclick="App.submitPost('<?= base_url('admin/users/toggle-status/' . $u['id']) ?>')">
                                                <i class="bi bi-arrow-repeat me-2 text-info"></i> Ubah Status (<?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>)
                                            </button>
                                        </li>
                                        <?php if ((int)$u['id'] !== (int)user_id()): ?>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <button type="button" class="dropdown-item py-1.5 text-danger" onclick="App.confirm('Apakah Anda yakin ingin menonaktifkan/menghapus pengguna [<?= e($u['name']) ?>]?', () => App.submitPost('<?= base_url('admin/users/delete/' . $u['id']) ?>'))">
                                                    <i class="bi bi-trash me-2"></i> Hapus Pengguna
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
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
