<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Main Import Card -->
        <div class="card border-0 rounded-4 bg-white shadow-xs mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-sm btn-light border rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="fw-bold text-slate-900 mb-0 fs-6">Impor Butir Soal Masal</h5>
                        <p class="text-muted fs-3xs mb-0">Bank Soal: <strong><?= e($bank['title']) ?></strong> (<?= e($bank['code']) ?>)</p>
                    </div>
                </div>
                <a href="<?= base_url('guru/question-banks/template') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 shadow-xs">
                    <i class="bi bi-download me-1"></i> Unduh Format Template (.CSV)
                </a>
            </div>

            <form method="POST" action="<?= base_url('guru/question-banks/' . $bank['id'] . '/import') ?>" enctype="multipart/form-data" class="p-4 needs-validation" novalidate>
                <?= csrf_field() ?>

                <div class="mb-4">
                    <label class="form-label">Pilih Berkas Soal <span class="text-danger">*</span></label>
                    <input type="file" name="import_file" class="form-control" accept=".csv, .xlsx, .xls, .json, .tsv" required>
                    <div class="form-text fs-3xs text-muted">
                        Mendukung format <strong>Excel (.xlsx)</strong>, <strong>CSV (.csv)</strong>, dan <strong>JSON (.json)</strong>. Maksimal 10MB.
                    </div>
                </div>

                <!-- Panduan Format Kolom -->
                <div class="card bg-light border border-slate-100 rounded-3 p-3 mb-4">
                    <h6 class="fw-bold text-slate-800 fs-xs mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Petunjuk Struktur Kolom File Impor:</span>
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm fs-3xs bg-white mb-0">
                            <thead class="table-light fs-xs text-uppercase text-secondary">
                                <tr>
                                    <th>Header Kolom</th>
                                    <th>Wajib?</th>
                                    <th>Nilai yang Diizinkan / Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-monospace fw-bold">type</td>
                                    <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Wajib</span></td>
                                    <td><code>multiple_choice</code>, <code>true_false</code>, atau <code>essay</code></td>
                                </tr>
                                <tr>
                                    <td class="font-monospace fw-bold">content</td>
                                    <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Wajib</span></td>
                                    <td>Teks pertanyaan / butir soal</td>
                                </tr>
                                <tr>
                                    <td class="font-monospace fw-bold">difficulty</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary rounded-pill">Opsional</span></td>
                                    <td><code>easy</code> (Mudah), <code>medium</code> (Sedang), <code>hard</code> (Sukar). Default: <em>medium</em></td>
                                </tr>
                                <tr>
                                    <td class="font-monospace fw-bold">weight</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary rounded-pill">Opsional</span></td>
                                    <td>Angka bobot nilai (contoh: <code>2.00</code>). Default: <em>1.00</em></td>
                                </tr>
                                <tr>
                                    <td class="font-monospace fw-bold">option_a s/d option_e</td>
                                    <td><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">Kondisional</span></td>
                                    <td>Wajib diisi minimal opsi A &amp; B untuk soal pilihan ganda.</td>
                                </tr>
                                <tr>
                                    <td class="font-monospace fw-bold">correct_answer</td>
                                    <td><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">Kondisional</span></td>
                                    <td>Huruf <code>A</code>, <code>B</code>, <code>C</code>, <code>D</code>, <code>E</code> (pilihan ganda) atau <code>Benar</code> / <code>Salah</code> (benar/salah).</td>
                                </tr>
                                <tr>
                                    <td class="font-monospace fw-bold">explanation</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary rounded-pill">Opsional</span></td>
                                    <td>Pembahasan soal atau pedoman penskoran esai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                        <i class="bi bi-arrow-left me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 shadow-xs fw-semibold">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Mulai Impor Berkas
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Import Logs for This Bank -->
        <?php if (!empty($recentImports)): ?>
            <div class="card border-0 shadow-xs rounded-4 bg-white p-4">
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-secondary"></i>
                    <span>Riwayat Impor Terakhir Bank Soal Ini</span>
                </h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-xs">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Nama Berkas</th>
                                <th>Format</th>
                                <th>Hasil</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentImports as $imp): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($imp['created_at'])) ?></td>
                                    <td class="fw-semibold text-dark"><?= e($imp['file_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border text-uppercase rounded-pill"><?= e($imp['file_type']) ?></span></td>
                                    <td><strong><?= (int)$imp['total_imported'] ?></strong> butir diimpor</td>
                                    <td>
                                        <?php if ($imp['status'] === 'completed'): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill">Sukses</span>
                                        <?php elseif ($imp['status'] === 'failed'): ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill">Gagal</span>
                                        <?php else: ?>
                                            <span class="badge bg-info-subtle text-info rounded-pill">Proses</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
