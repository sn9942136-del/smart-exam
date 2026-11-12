<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 bg-white shadow-xs mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('guru/question-banks') ?>" class="btn btn-sm btn-light border rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Buat Bank Soal Baru</h5>
                </div>
                <span class="badge bg-primary-subtle text-primary fs-3xs rounded-pill">Bank Soal</span>
            </div>

            <form method="POST" action="<?= base_url('guru/question-banks/store') ?>" class="p-4 needs-validation" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3 mb-4">
                    <!-- Judul Bank Soal -->
                    <div class="col-12">
                        <label class="form-label">Judul Bank Soal <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" placeholder="Contoh: Bank Soal Penilaian Akhir Semester Ganjil Matematika X" value="<?= e($old['title'] ?? '') ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Mata Pelajaran -->
                    <div class="col-md-6">
                        <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select <?= isset($errors['subject_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((string)($old['subject_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subject_id'])): ?>
                            <div class="invalid-feedback"><?= e($errors['subject_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label">Status Publikasi</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= (($old['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft (Belum Siap Ujian)</option>
                            <option value="active" <?= (($old['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Aktif (Siap Digunakan)</option>
                        </select>
                    </div>

                    <!-- Custom Code (Opsional) -->
                    <div class="col-12">
                        <label class="form-label">Kode Bank Soal <span class="text-muted fw-normal fs-xs">(Opsional)</span></label>
                        <input type="text" name="code" class="form-control font-monospace" placeholder="Kosongkan untuk pembuatan otomatis (contoh: BNK-MAT10-7492)" value="<?= e($old['code'] ?? '') ?>">
                        <div class="form-text fs-3xs text-muted">Jika dikosongkan, sistem akan mengenerate kode unik otomatis berdasarkan kode mapel.</div>
                    </div>

                    <!-- Deskripsi & Petunjuk -->
                    <div class="col-12">
                        <label class="form-label">Deskripsi & Petunjuk Pengerjaan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Tuliskan petunjuk umum pengerjaan, cakupan materi, atau catatan penting..."><?= e($old['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="<?= base_url('guru/question-banks') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                        <i class="bi bi-arrow-left me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                        <i class="bi bi-check-lg me-1"></i> Simpan & Lanjutkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
