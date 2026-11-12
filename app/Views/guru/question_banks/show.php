<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Bank Header Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-slate-100 text-slate-700 font-monospace border px-2.5 py-1 rounded-pill">
                    <?= e($bank['code']) ?>
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold rounded-pill">
                    <i class="bi bi-book me-1"></i> <?= e($bank['subject_name'] ?? 'Mata Pelajaran') ?>
                </span>
                <?php if ($bank['status'] === 'active'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">Aktif</span>
                <?php elseif ($bank['status'] === 'draft'): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 rounded-pill">Draft</span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill">Arsip</span>
                <?php endif; ?>
            </div>
            <h4 class="fw-bold text-dark mb-1 fs-5"><?= e($bank['title']) ?></h4>
            <?php if (!empty($bank['description'])): ?>
                <p class="text-muted fs-xs mb-0"><?= nl2br(e($bank['description'])) ?></p>
            <?php endif; ?>
            <div class="fs-3xs text-muted mt-2">
                <i class="bi bi-person me-1"></i> Dibuat oleh: <strong><?= e($bank['creator_name'] ?? 'Guru Pengajar') ?></strong> &bull;
                <i class="bi bi-clock me-1"></i> Terakhir diperbarui: <?= date('d M Y H:i', strtotime($bank['updated_at'] ?? 'now')) ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('guru/question-banks/' . $bank['id'] . '/questions/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs">
                <i class="bi bi-plus-lg me-1.5"></i> Tambah Soal Manual
            </a>
            <a href="<?= base_url('guru/ai-questions?bank_id=' . $bank['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="bi bi-robot me-1.5"></i> Generasi Soal AI
            </a>
            <a href="<?= base_url('guru/question-banks/' . $bank['id'] . '/import') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-file-earmark-arrow-up me-1.5"></i> Impor File
            </a>
            <a href="<?= base_url('guru/question-banks/edit/' . $bank['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-pencil me-1.5"></i> Edit Info
            </a>
        </div>
    </div>

    <!-- Quick Distribution Statistics Bar -->
    <div class="row g-2 pt-3 border-top mt-2">
        <div class="col-sm-6 col-md-3">
            <div class="p-2.5 rounded-3 bg-light border border-slate-100 d-flex align-items-center gap-2.5">
                <div class="rounded-2 bg-primary-subtle text-primary p-2 fs-6"><i class="bi bi-list-check"></i></div>
                <div>
                    <div class="fs-3xs text-muted">Pilihan Ganda</div>
                    <div class="fw-bold text-dark fs-xs"><?= (int)($bank['stats']['total_mc'] ?? 0) ?> Butir</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="p-2.5 rounded-3 bg-light border border-slate-100 d-flex align-items-center gap-2.5">
                <div class="rounded-2 bg-info-subtle text-info p-2 fs-6"><i class="bi bi-check2-square"></i></div>
                <div>
                    <div class="fs-3xs text-muted">Benar / Salah</div>
                    <div class="fw-bold text-dark fs-xs"><?= (int)($bank['stats']['total_tf'] ?? 0) ?> Butir</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="p-2.5 rounded-3 bg-light border border-slate-100 d-flex align-items-center gap-2.5">
                <div class="rounded-2 bg-warning-subtle text-warning-emphasis p-2 fs-6"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <div class="fs-3xs text-muted">Soal Esai</div>
                    <div class="fw-bold text-dark fs-xs"><?= (int)($bank['stats']['total_essay'] ?? 0) ?> Butir</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="p-2.5 rounded-3 bg-light border border-slate-100 d-flex align-items-center gap-2.5">
                <div class="rounded-2 bg-success-subtle text-success p-2 fs-6"><i class="bi bi-award"></i></div>
                <div>
                    <div class="fs-3xs text-muted">Total Bobot Nilai</div>
                    <div class="fw-bold text-success fs-xs"><?= number_format((float)($bank['stats']['total_weight'] ?? 0), 2) ?> Poin</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Toolbar for Questions -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form method="GET" action="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0 fs-xs" placeholder="Cari teks soal atau pembahasan..." value="<?= e($filters['search'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm fs-xs">
                <option value="">-- Semua Jenis Soal --</option>
                <option value="multiple_choice" <?= (($filters['type'] ?? '') === 'multiple_choice') ? 'selected' : '' ?>>Pilihan Ganda</option>
                <option value="true_false" <?= (($filters['type'] ?? '') === 'true_false') ? 'selected' : '' ?>>Benar / Salah</option>
                <option value="essay" <?= (($filters['type'] ?? '') === 'essay') ? 'selected' : '' ?>>Esai / Uraian</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="difficulty" class="form-select form-select-sm fs-xs">
                <option value="">-- Semua Tingkat --</option>
                <option value="easy" <?= (($filters['difficulty'] ?? '') === 'easy') ? 'selected' : '' ?>>Mudah</option>
                <option value="medium" <?= (($filters['difficulty'] ?? '') === 'medium') ? 'selected' : '' ?>>Sedang</option>
                <option value="hard" <?= (($filters['difficulty'] ?? '') === 'hard') ? 'selected' : '' ?>>Sukar</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1 rounded-pill">
                <i class="bi bi-filter me-1"></i> Saring
            </button>
            <?php if (!empty($filters['search']) || !empty($filters['type']) || !empty($filters['difficulty'])): ?>
                <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Questions List -->
<?php if (empty($questions)): ?>
    <div class="card border-0 shadow-xs rounded-4 p-5 bg-white text-center">
        <i class="bi bi-patch-question fs-1 text-muted mb-2 d-block"></i>
        <h6 class="fw-bold text-dark mb-1">Belum Ada Butir Soal</h6>
        <p class="fs-xs text-muted mb-3">Mulai buat butir soal manual atau impor sekaligus menggunakan template file Excel/CSV.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= base_url('guru/question-banks/' . $bank['id'] . '/questions/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs">
                <i class="bi bi-plus-lg me-1.5"></i> Tambah Soal Manual
            </a>
            <a href="<?= base_url('guru/question-banks/' . $bank['id'] . '/import') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">
                <i class="bi bi-file-earmark-arrow-up me-1.5"></i> Impor File
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3 mb-4">
        <?php foreach ($questions as $idx => $q): ?>
            <div class="card border-0 shadow-xs rounded-4 bg-white p-4 position-relative hover-shadow transition">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
                    <!-- Badges -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold fs-xs">
                            #<?= $q['order_number'] ?? ($idx + 1) ?>
                        </span>

                        <!-- Jenis Soal -->
                        <?php if ($q['type'] === 'multiple_choice'): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5">
                                <i class="bi bi-list-check me-1"></i> Pilihan Ganda
                            </span>
                        <?php elseif ($q['type'] === 'true_false'): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5">
                                <i class="bi bi-check2-square me-1"></i> Benar / Salah
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5">
                                <i class="bi bi-file-earmark-text me-1"></i> Esai / Uraian
                            </span>
                        <?php endif; ?>

                        <!-- Tingkat Kesulitan -->
                        <?php if ($q['difficulty'] === 'easy'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5">Mudah</span>
                        <?php elseif ($q['difficulty'] === 'hard'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5">Sukar</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5">Sedang</span>
                        <?php endif; ?>

                        <!-- Bobot -->
                        <span class="badge bg-light text-dark border rounded-pill px-2.5">
                            Bobot: <strong><?= number_format((float)$q['weight'], 2) ?></strong>
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="btn-group btn-group-sm">
                        <a href="<?= base_url('guru/questions/preview/' . $q['id']) ?>" class="btn btn-outline-secondary btn-xs" title="Pratinjau Soal">
                            <i class="bi bi-eye"></i> Preview
                        </a>
                        <a href="<?= base_url('guru/questions/edit/' . $q['id']) ?>" class="btn btn-outline-secondary btn-xs" title="Edit Soal">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-xs" onclick="App.confirm('Apakah Anda yakin ingin menghapus butir soal ini?', () => App.submitPost('<?= base_url('guru/questions/delete/' . $q['id']) ?>'))" title="Hapus Soal">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </div>
                </div>

                <!-- Teks Soal -->
                <div class="mb-3 text-dark fs-xs fw-medium lh-base">
                    <?= nl2br(e($q['content'])) ?>
                </div>

                <!-- Gambar Soal Jika Ada -->
                <?php if (!empty($q['question_image'])): ?>
                    <div class="mb-3">
                        <img src="<?= base_url(e($q['question_image'])) ?>" alt="Gambar Soal" class="img-fluid rounded-3 border shadow-2xs" style="max-height: 240px;">
                    </div>
                <?php endif; ?>

                <!-- Pilihan Jawaban (Jika Pilihan Ganda atau Benar/Salah) -->
                <?php if ($q['type'] === 'multiple_choice' || $q['type'] === 'true_false'): ?>
                    <div class="row g-2 mb-3">
                        <?php foreach (($q['options'] ?? []) as $opt): ?>
                            <div class="col-12">
                                <div class="p-2.5 rounded-3 border <?= !empty($opt['is_correct']) ? 'bg-success-subtle border-success text-success fw-bold' : 'bg-body-tertiary text-dark' ?> fs-xs d-flex align-items-center gap-2">
                                    <span class="badge <?= !empty($opt['is_correct']) ? 'bg-success text-white' : 'bg-secondary text-white' ?> rounded-circle p-1.5" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                        <?= e($opt['option_key']) ?>
                                    </span>
                                    <span class="flex-grow-1"><?= e($opt['content']) ?></span>
                                    <?php if (!empty($opt['is_correct'])): ?>
                                        <span class="badge bg-success text-white rounded-pill fs-3xs px-2 py-1">
                                            <i class="bi bi-check-lg me-1"></i> Kunci Benar
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pembahasan / Kunci Jawaban Esai -->
                <?php if (!empty($q['explanation'])): ?>
                    <div class="p-3 rounded-3 bg-light-subtle border border-dashed text-dark fs-3xs">
                        <div class="fw-bold text-primary mb-1 d-flex align-items-center gap-1">
                            <i class="bi bi-lightbulb"></i> Pembahasan / Panduan Koreksi:
                        </div>
                        <div class="text-secondary"><?= nl2br(e($q['explanation'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
