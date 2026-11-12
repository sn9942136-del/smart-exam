<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/semesters') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5 me-1" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Tambah Semester Baru</h5>
                </div>
                <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">Master Akademik</span>
            </div>

            <div class="card-body p-4">
                <form action="<?= base_url('admin/semesters/store') ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Tahun Ajaran <span class="text-danger">*</span></label>
                        <select name="academic_year_id" class="form-select border-slate-200 fs-xs rounded-3 <?= isset($errors['academic_year_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= (($old['academic_year_id'] ?? '') == $y['id']) ? 'selected' : '' ?>>
                                    T.A. <?= e($y['name']) ?> <?= $y['is_active'] ? '(Aktif)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['academic_year_id'])): ?>
                            <div class="invalid-feedback"><?= e($errors['academic_year_id'][0]) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Nama Semester <span class="text-danger">*</span></label>
                            <select name="name" class="form-select border-slate-200 fs-xs rounded-3" required onchange="document.getElementById('semester_code_input').value = (this.value === 'Ganjil') ? '1' : '2'">
                                <option value="Ganjil" <?= (($old['name'] ?? '') === 'Ganjil') ? 'selected' : '' ?>>Ganjil</option>
                                <option value="Genap" <?= (($old['name'] ?? '') === 'Genap') ? 'selected' : '' ?>>Genap</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Kode Semester <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="semester_code_input" class="form-control border-slate-200 fs-xs rounded-3" placeholder="Contoh: 1 atau 2" value="<?= e($old['code'] ?? '1') ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active_check" <?= !empty($old['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-slate-800 fw-medium fs-xs" for="is_active_check">Tetapkan sebagai Semester Aktif</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/semesters') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-save me-1"></i> Simpan Semester
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
