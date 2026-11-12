<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 rounded-4 bg-white shadow-xs mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-sm btn-light border rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h5 class="fw-bold text-slate-900 mb-0 fs-6">Tambah Butir Soal Baru</h5>
                        <p class="text-muted fs-3xs mb-0">Bank Soal: <strong><?= e($bank['title']) ?></strong> (<?= e($bank['code']) ?>)</p>
                    </div>
                </div>
                <span class="badge bg-slate-100 text-slate-800 border px-2.5 py-1 fs-3xs rounded-pill">
                    Nomor Soal: #<strong><?= (int)($old['order_number'] ?? $nextOrder) ?></strong>
                </span>
            </div>

            <form method="POST" action="<?= base_url('guru/question-banks/' . $bank['id'] . '/questions/store') ?>" enctype="multipart/form-data" class="p-4 needs-validation" novalidate id="questionForm">
                <?= csrf_field() ?>

                <!-- Section 1: Tipe Soal & Kesulitan -->
                <div class="row g-3 mb-4 p-3 bg-light rounded-3 border border-slate-100">
                    <!-- Jenis Soal Selector -->
                    <div class="col-md-5">
                        <label class="form-label">Jenis Soal <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php $currentType = $old['type'] ?? 'multiple_choice'; ?>
                            <div class="form-check form-check-inline me-0">
                                <input class="btn-check" type="radio" name="type" id="type_mc" value="multiple_choice" <?= ($currentType === 'multiple_choice') ? 'checked' : '' ?> onchange="switchQuestionType('multiple_choice')">
                                <label class="btn btn-sm btn-outline-primary rounded-pill px-3" for="type_mc">
                                    <i class="bi bi-list-check me-1"></i> Pilihan Ganda
                                </label>
                            </div>
                            <div class="form-check form-check-inline me-0">
                                <input class="btn-check" type="radio" name="type" id="type_tf" value="true_false" <?= ($currentType === 'true_false') ? 'checked' : '' ?> onchange="switchQuestionType('true_false')">
                                <label class="btn btn-sm btn-outline-info rounded-pill px-3" for="type_tf">
                                    <i class="bi bi-check2-square me-1"></i> Benar / Salah
                                </label>
                            </div>
                            <div class="form-check form-check-inline me-0">
                                <input class="btn-check" type="radio" name="type" id="type_essay" value="essay" <?= ($currentType === 'essay') ? 'checked' : '' ?> onchange="switchQuestionType('essay')">
                                <label class="btn btn-sm btn-outline-warning rounded-pill px-3" for="type_essay">
                                    <i class="bi bi-file-earmark-text me-1"></i> Esai
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tingkat Kesulitan -->
                    <div class="col-md-4">
                        <label class="form-label">Tingkat Kesulitan <span class="text-danger">*</span></label>
                        <?php $currentDiff = $old['difficulty'] ?? 'medium'; ?>
                        <select name="difficulty" class="form-select" required>
                            <option value="easy" <?= ($currentDiff === 'easy') ? 'selected' : '' ?>>Mudah (Easy)</option>
                            <option value="medium" <?= ($currentDiff === 'medium') ? 'selected' : '' ?>>Sedang (Medium)</option>
                            <option value="hard" <?= ($currentDiff === 'hard') ? 'selected' : '' ?>>Sukar (Hard)</option>
                        </select>
                    </div>

                    <!-- Bobot Nilai -->
                    <div class="col-md-3">
                        <label class="form-label">Bobot Nilai / Poin <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" min="0.5" max="100" name="weight" class="form-control <?= isset($errors['weight']) ? 'is-invalid' : '' ?>" value="<?= e($old['weight'] ?? '2.00') ?>" required>
                        <?php if (isset($errors['weight'])): ?>
                            <div class="invalid-feedback"><?= e($errors['weight']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 2: Konten Teks Soal & Gambar Ilustrasi -->
                <div class="mb-4">
                    <label class="form-label">Teks Pertanyaan / Soal <span class="text-danger">*</span></label>
                    <textarea name="content" id="questionContent" class="form-control <?= isset($errors['content']) ? 'is-invalid' : '' ?>" rows="4" placeholder="Tuliskan butir soal di sini..." required><?= e($old['content'] ?? '') ?></textarea>
                    <?php if (isset($errors['content'])): ?>
                        <div class="invalid-feedback"><?= e($errors['content']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Upload Gambar Soal (Opsional) -->
                <div class="mb-4 p-3 border rounded-2 bg-slate-50">
                    <label class="form-label d-flex align-items-center gap-1 mb-1">
                        <i class="bi bi-image"></i> Gambar / Diagram Ilustrasi Soal <span class="text-muted fw-normal fs-xs">(Opsional)</span>
                    </label>
                    <input type="file" name="question_image" id="questionImageInput" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImage(this)">
                    <div class="form-text fs-3xs text-muted">Mendukung format JPG, PNG, WEBP, GIF. Ukuran maksimum 3MB.</div>

                    <!-- Image Preview Container -->
                    <div id="imagePreviewContainer" class="mt-2 d-none">
                        <div class="position-relative d-inline-block">
                            <img id="imagePreview" src="#" alt="Preview Gambar" class="img-thumbnail rounded-2" style="max-height: 200px;">
                            <button type="button" class="btn btn-xs btn-danger position-absolute top-0 end-0 m-1 rounded-circle p-1" onclick="clearImagePreview()" title="Hapus Gambar">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Pilihan Jawaban Dinamis -->

                <!-- 3.1 Pilihan Ganda Container -->
                <div id="container_multiple_choice" class="mb-4 <?= ($currentType !== 'multiple_choice') ? 'd-none' : '' ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label mb-0">Pilihan Jawaban & Kunci Benar <span class="text-danger">*</span></label>
                        <span class="fs-3xs text-muted">Pilih lingkaran radio pada opsi yang merupakan kunci jawaban benar.</span>
                    </div>

                    <?php if (isset($errors['options']) || isset($errors['correct_option'])): ?>
                        <div class="alert alert-danger p-2 fs-3xs mb-3">
                            <?= e($errors['options'] ?? $errors['correct_option'] ?? 'Periksa pilihan jawaban.') ?>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $optKeys = ['A', 'B', 'C', 'D', 'E'];
                    $oldOptions = $old['options'] ?? [];
                    $oldCorrect = $old['correct_option'] ?? 'A';
                    ?>

                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($optKeys as $key): ?>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <div class="form-check me-1">
                                        <input class="form-check-input" type="radio" name="correct_option" id="correct_opt_<?= $key ?>" value="<?= $key ?>" <?= ($oldCorrect === $key) ? 'checked' : '' ?>>
                                    </div>
                                    <strong class="text-primary"><?= $key ?></strong>
                                </span>
                                <input type="text" name="options[<?= $key ?>]" class="form-control" placeholder="Isian pilihan jawaban <?= $key ?>..." value="<?= e($oldOptions[$key] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 3.2 Benar / Salah Container -->
                <div id="container_true_false" class="mb-4 <?= ($currentType !== 'true_false') ? 'd-none' : '' ?>">
                    <label class="form-label">Tentukan Kunci Jawaban yang Benar <span class="text-danger">*</span></label>
                    <?php $oldTf = $old['correct_tf'] ?? 'Benar'; ?>
                    <div class="d-flex gap-3">
                        <div class="p-3 border rounded-2 bg-white flex-grow-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_tf" id="tf_benar" value="Benar" <?= ($oldTf === 'Benar') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-success fs-sm" for="tf_benar">
                                    <i class="bi bi-check-circle me-1"></i> BENAR (TRUE)
                                </label>
                            </div>
                        </div>
                        <div class="p-3 border rounded-2 bg-white flex-grow-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="correct_tf" id="tf_salah" value="Salah" <?= ($oldTf === 'Salah') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-danger fs-sm" for="tf_salah">
                                    <i class="bi bi-x-circle me-1"></i> SALAH (FALSE)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3.3 Esai Informational Banner -->
                <div id="container_essay" class="mb-4 <?= ($currentType !== 'essay') ? 'd-none' : '' ?>">
                    <div class="alert alert-warning border-0 p-3 fs-xs rounded-2 d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle fs-5 text-warning flex-shrink-0"></i>
                        <div>
                            <strong>Petunjuk Soal Esai:</strong>
                            <p class="mb-0 fs-3xs">Soal esai tidak memerlukan opsi jawaban berganda. Tuliskan kata kunci jawaban atau rubrik penilaian pada kolom pembahasan di bawah untuk mempermudah koreksi manual maupun AI Scoring.</p>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Pembahasan & Kunci Jawaban (Opsional) -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-lightbulb me-1 text-primary"></i> Pembahasan / Kunci Penjelasan <span class="text-muted fw-normal fs-xs">(Opsional)</span>
                    </label>
                    <textarea name="explanation" class="form-control" rows="3" placeholder="Tuliskan langkah penyelesaian, rumus, atau alasan kunci jawaban..."><?= e($old['explanation'] ?? '') ?></textarea>
                </div>

                <!-- Hidden Input Order Number -->
                <input type="hidden" name="order_number" value="<?= (int)($old['order_number'] ?? $nextOrder) ?>">

                <!-- Footer Action Buttons -->
                <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top gap-2">
                    <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Bank Soal
                    </a>
                    <div class="d-flex gap-2">
                        <button type="submit" name="submit_action" value="save_and_add" class="btn btn-sm btn-outline-primary rounded-pill px-3.5">
                            <i class="bi bi-plus me-1"></i> Simpan & Tambah Lagi
                        </button>
                        <button type="submit" name="submit_action" value="save_and_exit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-check-lg me-1"></i> Simpan Soal
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchQuestionType(type) {
    document.getElementById('container_multiple_choice').classList.toggle('d-none', type !== 'multiple_choice');
    document.getElementById('container_true_false').classList.toggle('d-none', type !== 'true_false');
    document.getElementById('container_essay').classList.toggle('d-none', type !== 'essay');
}

function previewImage(input) {
    const previewContainer = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        clearImagePreview();
    }
}

function clearImagePreview() {
    const input = document.getElementById('questionImageInput');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    input.value = '';
    preview.src = '#';
    previewContainer.classList.add('d-none');
}
</script>
