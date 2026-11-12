<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/roles') ?>" class="btn btn-sm btn-light border border-slate-200 rounded-pill p-1 px-2.5 me-1" title="Kembali">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="fw-bold text-slate-900 mb-0 fs-6">Tambah Role Baru</h5>
                        <p class="text-muted fs-3xs mb-0">Tentukan wewenang dan cakupan hak akses sistem</p>
                    </div>
                </div>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fs-3xs fw-medium">RBAC Builder</span>
            </div>

            <div class="card-body p-4">
                <form action="<?= base_url('admin/roles/store') ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fs-xs fw-semibold text-slate-800">Nama Tampilan Role <span class="text-danger">*</span></label>
                            <input type="text" name="display_name" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['display_name']) ? 'is-invalid' : '' ?>" placeholder="Contoh: Pengawas Eksternal" value="<?= e($old['display_name'] ?? '') ?>" required>
                            <?php if (isset($errors['display_name'])): ?>
                                <div class="invalid-feedback fs-3xs"><?= e($errors['display_name'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fs-xs fw-semibold text-slate-800">Kode Identitas Role (Slug) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>" placeholder="contoh: pengawas_eksternal" value="<?= e($old['name'] ?? '') ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback fs-3xs"><?= e($errors['name'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-xs fw-semibold text-slate-800">Deskripsi Peran</label>
                            <textarea name="description" class="form-control border-slate-200 fs-xs rounded-3" rows="2" placeholder="Deskripsi wewenang pengguna dengan peran ini..."><?= e($old['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Permission Matrix Accordion / Grid -->
                    <div class="form-section-title">
                        <i class="bi bi-shield-check"></i>
                        <span>Matriks Hak Akses (Permissions)</span>
                    </div>

                    <div class="row g-3 mb-4">
                        <?php foreach ($permissionsGrouped as $moduleName => $perms): ?>
                            <div class="col-md-6">
                                <div class="card border border-slate-200 rounded-3 p-3 bg-slate-50 h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-slate-200">
                                        <span class="fw-bold text-slate-800 fs-xs text-uppercase tracking-wider">Modul: <?= e($moduleName) ?></span>
                                        <span class="badge bg-slate-100 text-slate-700 border border-slate-200 rounded-pill fs-3xs"><?= count($perms) ?> Izin</span>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5">
                                        <?php foreach ($perms as $p): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" id="perm_<?= $p['id'] ?>" <?= in_array($p['id'], (array)($currentPermIds ?? []), false) ? 'checked' : '' ?>>
                                                <label class="form-check-label text-slate-800 fs-xs" for="perm_<?= $p['id'] ?>">
                                                    <strong><?= e($p['display_name']) ?></strong>
                                                    <span class="text-muted font-monospace fs-3xs d-block">(<?= e($p['name']) ?>)</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/roles') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-save me-1"></i> Simpan Role Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
