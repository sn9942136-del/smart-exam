<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom border-slate-100 p-3.5 px-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 fw-semibold fs-xs mb-1.5 d-inline-block">
                            Butir Soal #<?= (int)$question['order_number'] ?>
                        </span>
                        <h5 class="fw-bold text-dark mb-0 fs-base">Pratinjau Soal Ujian</h5>
                        <p class="text-muted fs-xs mb-0">Bank Soal: <?= e($bank['title']) ?> <span class="text-muted opacity-75">(<?= e($bank['code']) ?>)</span></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('guru/questions/edit/' . $question['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                            <i class="bi bi-pencil me-1.5"></i> Edit Soal Ini
                        </a>
                        <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3.5">
                            <i class="bi bi-arrow-left me-1.5"></i> Kembali ke Bank Soal
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <!-- Metadata Badges -->
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4 pb-3 border-bottom border-slate-100">
                    <!-- Tipe -->
                    <?php if ($question['type'] === 'multiple_choice'): ?>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-medium">
                            <i class="bi bi-list-check me-1"></i> Pilihan Ganda
                        </span>
                    <?php elseif ($question['type'] === 'true_false'): ?>
                        <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1 fw-medium">
                            <i class="bi bi-check2-square me-1"></i> Benar / Salah
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1 fw-medium">
                            <i class="bi bi-file-earmark-text me-1"></i> Esai / Uraian
                        </span>
                    <?php endif; ?>

                    <!-- Tingkat Kesulitan -->
                    <?php if ($question['difficulty'] === 'easy'): ?>
                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-medium">Mudah</span>
                    <?php elseif ($question['difficulty'] === 'hard'): ?>
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-medium">Sukar</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1 fw-medium">Sedang</span>
                    <?php endif; ?>

                    <span class="badge bg-slate-100 text-slate-700 rounded-pill px-2.5 py-1 fw-medium">
                        Bobot Nilai: <strong class="text-dark ms-1"><?= number_format((float)$question['weight'], 2) ?> Poin</strong>
                    </span>
                </div>

                <!-- Tampilan Peserta Didik (Student View Mockup) -->
                <div class="p-4 rounded-3 bg-light border border-slate-200 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 text-muted fs-xs text-uppercase fw-semibold tracking-wider">
                        <span><i class="bi bi-display me-1.5"></i> Tampilan di Layar Peserta Didik:</span>
                        <span class="badge bg-slate-800 text-white rounded-pill px-2 py-0.5">Mode Ujian</span>
                    </div>

                    <!-- Soal -->
                    <div class="fs-xs fw-semibold text-dark mb-3 lh-base">
                        <?= nl2br(e($question['content'])) ?>
                    </div>

                    <!-- Gambar -->
                    <?php if (!empty($question['question_image'])): ?>
                        <div class="mb-3 text-center">
                            <img src="<?= base_url(e($question['question_image'])) ?>" alt="Ilustrasi Soal" class="img-fluid rounded-3 border bg-white shadow-xs" style="max-height: 280px;">
                        </div>
                    <?php endif; ?>

                    <!-- Opsi Jawaban Interaktif -->
                    <?php if ($question['type'] === 'multiple_choice' || $question['type'] === 'true_false'): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach (($question['options'] ?? []) as $opt): ?>
                                <label class="p-3 rounded-3 border bg-white text-dark fs-xs d-flex align-items-center gap-3 cursor-pointer shadow-xs transition mb-0">
                                    <input type="radio" name="preview_answer" class="form-check-input flex-shrink-0" style="width: 18px; height: 18px;">
                                    <span class="badge bg-secondary-subtle text-secondary rounded-circle" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;">
                                        <?= e($opt['option_key']) ?>
                                    </span>
                                    <span class="flex-grow-1"><?= e($opt['content']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['type'] === 'essay'): ?>
                        <div class="mb-2">
                            <textarea class="form-control bg-white fs-xs" rows="4" placeholder="Peserta didik mengetikkan jawaban esai di sini..." disabled></textarea>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kunci Jawaban & Pembahasan (Teacher Key View) -->
                <div class="p-4 rounded-3 bg-success-subtle bg-opacity-25 border border-success-subtle">
                    <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold fs-xs">
                        <i class="bi bi-key-fill"></i>
                        <span>Kunci Jawaban & Rubrik Penilaian</span>
                    </div>

                    <?php if ($question['type'] === 'multiple_choice' || $question['type'] === 'true_false'): ?>
                        <div class="mb-3">
                            <div class="fs-xs text-muted mb-1.5">Kunci Jawaban Benar:</div>
                            <?php foreach (($question['options'] ?? []) as $opt): ?>
                                <?php if (!empty($opt['is_correct'])): ?>
                                    <span class="badge bg-success text-white px-3 py-1.5 rounded-pill fs-xs">
                                        <i class="bi bi-check-circle-fill me-1.5"></i> Opsi <?= e($opt['option_key']) ?>: <?= e($opt['content']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($question['explanation'])): ?>
                        <div>
                            <div class="fs-xs text-muted mb-1.5">Pembahasan / Catatan Penilaian:</div>
                            <div class="bg-white p-3 rounded-3 border text-dark fs-xs">
                                <?= nl2br(e($question['explanation'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
