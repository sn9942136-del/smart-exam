<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/academic-years') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5 me-1" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Edit Tahun Ajaran: <?= e($year['name']) ?></h5>
                </div>
                <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">Master Data</span>
            </div>

            <div class="card-body p-4">
                <form action="<?= base_url('admin/academic-years/update/' . $year['id']) ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Nama Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($old['name'] ?? $year['name']) ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['name'][0]) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control border-slate-200 fs-xs rounded-3" value="<?= e($old['start_date'] ?? $year['start_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control border-slate-200 fs-xs rounded-3" value="<?= e($old['end_date'] ?? $year['end_date']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active_check" <?= !empty($old['is_active'] ?? $year['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label text-slate-800 fw-medium fs-xs" for="is_active_check">Tetapkan sebagai Tahun Ajaran Aktif</label>
                        </div>
                        <div class="form-text fs-3xs text-muted ps-4">Jika dicentang, tahun ajaran aktif lainnya akan otomatis dinonaktifkan.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/academic-years') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
