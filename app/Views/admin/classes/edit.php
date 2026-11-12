<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/classes') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5 me-1" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Edit Kelas: <?= e($class['name']) ?></h5>
                </div>
                <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">Master Data</span>
            </div>

            <div class="card-body p-4">
                <form action="<?= base_url('admin/classes/update/' . $class['id']) ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Tahun Ajaran <span class="text-danger">*</span></label>
                        <select name="academic_year_id" class="form-select border-slate-200 fs-xs rounded-3 <?= isset($errors['academic_year_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= (($old['academic_year_id'] ?? $class['academic_year_id']) == $y['id']) ? 'selected' : '' ?>>
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
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Nama Rombel / Kelas <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($old['name'] ?? $class['name']) ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name'][0]) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Tingkat <span class="text-danger">*</span></label>
                            <select name="grade_level" class="form-select border-slate-200 fs-xs rounded-3" required>
                                <option value="10" <?= (($old['grade_level'] ?? $class['grade_level']) === '10') ? 'selected' : '' ?>>10 (X)</option>
                                <option value="11" <?= (($old['grade_level'] ?? $class['grade_level']) === '11') ? 'selected' : '' ?>>11 (XI)</option>
                                <option value="12" <?= (($old['grade_level'] ?? $class['grade_level']) === '12') ? 'selected' : '' ?>>12 (XII)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Jurusan</label>
                            <input type="text" name="major" class="form-control border-slate-200 fs-xs rounded-3" value="<?= e($old['major'] ?? $class['major']) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Wali Kelas <span class="text-muted fw-normal fs-xs">(Guru)</span></label>
                        <select name="homeroom_teacher_id" class="form-select border-slate-200 fs-xs rounded-3">
                            <option value="">-- Pilih Guru Wali Kelas (Opsional) --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (($old['homeroom_teacher_id'] ?? $class['homeroom_teacher_id']) == $t['id']) ? 'selected' : '' ?>>
                                    <?= e($t['name']) ?> (NIP: <?= e($t['nip_nisn'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/classes') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
