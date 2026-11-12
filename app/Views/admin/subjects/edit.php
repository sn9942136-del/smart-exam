<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/subjects') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5 me-1" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Edit Mata Pelajaran: <?= e($subject['name']) ?></h5>
                </div>
                <span class="badge rounded-pill bg-primary-subtle text-primary font-monospace fs-xs"><?= e($subject['code']) ?></span>
            </div>

            <div class="card-body p-4">
                <form action="<?= base_url('admin/subjects/update/' . $subject['id']) ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Kode Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control border-slate-200 fs-xs rounded-3 font-monospace text-uppercase <?= isset($errors['code']) ? 'is-invalid' : '' ?>" value="<?= e($old['code'] ?? $subject['code']) ?>" required>
                        <?php if (isset($errors['code'])): ?>
                            <div class="invalid-feedback"><?= e($errors['code'][0]) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($old['name'] ?? $subject['name']) ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['name'][0]) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Deskripsi Mata Pelajaran</label>
                        <textarea name="description" class="form-control border-slate-200 fs-xs rounded-3" rows="3"><?= e($old['description'] ?? ($subject['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/subjects') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
