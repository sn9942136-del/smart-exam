<div class="container-fluid py-2" id="reviewWorkbenchApp">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-xs">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/ai-questions') ?>" class="text-decoration-none">AI Generator</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Review Workbench</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-ui-checks-grid text-primary"></i> Workbench Peninjauan Soal AI
            </h3>
            <p class="text-muted fs-xs mb-0">
                Topik: <strong class="text-dark"><?= e($draft['subject_topic'] ?? '-') ?></strong> | 
                Bank Soal: <strong class="text-dark"><?= e($draft['bank_title'] ?? '-') ?></strong> | 
                Tingkat: <strong class="text-dark"><?= e($draft['grade_level'] ?? '-') ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('guru/ai-questions') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Generator
            </a>
            <?php if ($draft['status'] === 'pending'): ?>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-4 fw-semibold shadow-xs d-flex align-items-center gap-2" id="btnImportTop" onclick="AiReview.submitApprovedQuestions()">
                    <i class="bi bi-check2-all"></i>
                    <span id="btnImportText">Simpan ke Bank Soal</span>
                </button>
            <?php else: ?>
                <a href="<?= base_url('guru/question-banks/show/' . $draft['question_bank_id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-up-right"></i> Buka Bank Soal
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert / Status Banner -->
    <?php if ($draft['status'] === 'success'): ?>
        <div class="alert alert-success border-0 shadow-xs rounded-4 d-flex align-items-center justify-content-between mb-4 p-3.5">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                <div>
                    <h6 class="fw-bold mb-0 text-success">Draf ini telah disetujui & disimpan ke Bank Soal</h6>
                    <span class="fs-xs text-muted">Sebanyak <?= (int)$draft['accepted_count'] ?> butir soal berhasil diimpor pada <?= date('d M Y, H:i', strtotime($draft['updated_at'])) ?>.</span>
                </div>
            </div>
            <a href="<?= base_url('guru/question-banks/show/' . $draft['question_bank_id']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3.5">
                Lihat di Bank Soal
            </a>
        </div>
    <?php else: ?>
        <div class="alert alert-primary bg-primary-subtle text-primary-emphasis border-0 shadow-xs rounded-4 d-flex align-items-center justify-content-between mb-4 p-3.5">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
                <div>
                    <h6 class="fw-bold mb-0 text-primary">Prinsip Human-in-the-Loop (Persetujuan Guru)</h6>
                    <span class="fs-xs text-muted">Periksa setiap butir soal di bawah ini. Anda dapat mengedit teks pertanyaan, opsi, kunci jawaban, atau membatalkan pilihan butir yang tidak sesuai.</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-3" onclick="AiReview.selectAll(true)">Pilih Semua</button>
                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3" onclick="AiReview.selectAll(false)">Batal Semua</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Question Workbench Cards Form -->
    <form id="aiReviewForm" onsubmit="event.preventDefault();">
        <input type="hidden" id="generation_id" value="<?= (int)$draft['id'] ?>">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-slate-900 mb-0 fs-6">
                Daftar Butir Soal Dihasilkan (<?= count($draft['questions'] ?? []) ?> Soal)
            </h5>
            <div class="text-muted fs-xs">
                Terpilih untuk disimpan: <strong class="text-primary fs-sm" id="selectedCountBadge"><?= count($draft['questions'] ?? []) ?></strong> dari <?= count($draft['questions'] ?? []) ?> Soal
            </div>
        </div>

        <?php if (empty($draft['questions'])): ?>
            <div class="card border-0 shadow-xs rounded-4 p-5 text-center text-muted bg-white">
                <i class="bi bi-exclamation-triangle fs-1 text-warning d-block mb-2"></i>
                <h6 class="fw-bold text-slate-800">Tidak ada butir soal dalam draf ini</h6>
                <p class="fs-xs mb-0">Respon AI mungkin kosong atau terjadi kesalahan parsing.</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3 mb-4" id="questionsListContainer">
                <?php foreach ($draft['questions'] as $idx => $q): ?>
                    <?php 
                        $num = $idx + 1;
                        $qType = $q['type'] ?? 'multiple_choice';
                        $qDiff = $q['difficulty'] ?? 'medium';
                        $qWeight = $q['weight'] ?? ($qType === 'essay' ? 4.0 : 2.0);
                        $isPending = ($draft['status'] === 'pending');
                    ?>
                    <div class="card border-0 shadow-xs rounded-4 question-item-card bg-white" id="card_question_<?= $idx ?>" data-index="<?= $idx ?>">
                        <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($isPending): ?>
                                    <div class="form-check m-0">
                                        <input class="form-check-input question-select-toggle" type="checkbox" id="include_<?= $idx ?>" checked onchange="AiReview.updateSelectionStats()">
                                        <label class="form-check-label fw-bold text-slate-800 fs-xs" for="include_<?= $idx ?>">
                                            Gunakan Soal #<?= $num ?>
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-primary px-3 py-1.5 fs-xs">Soal #<?= $num ?></span>
                                <?php endif; ?>

                                <span class="badge rounded-pill bg-slate-100 text-slate-700 fs-3xs text-uppercase">
                                    <?= e($qType) ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="fs-3xs text-muted">Tingkat:</span>
                                    <select class="form-select form-select-sm py-1 px-2 fs-3xs rounded-3 border-slate-200 q-diff-input" <?= !$isPending ? 'disabled' : '' ?>>
                                        <option value="easy" <?= ($qDiff === 'easy') ? 'selected' : '' ?>>Mudah</option>
                                        <option value="medium" <?= ($qDiff === 'medium') ? 'selected' : '' ?>>Sedang</option>
                                        <option value="hard" <?= ($qDiff === 'hard') ? 'selected' : '' ?>>Sulit (HOTS)</option>
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="fs-3xs text-muted">Bobot:</span>
                                    <input type="number" step="0.5" min="0.5" max="20" class="form-control form-control-sm py-1 px-2 fs-3xs rounded-3 border-slate-200 q-weight-input" style="width: 70px;" value="<?= e((string)$qWeight) ?>" <?= !$isPending ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <!-- Pertanyaan / Content -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-slate-800 fs-xs">Teks Pertanyaan / Soal:</label>
                                <textarea class="form-control border-slate-200 fs-xs rounded-3 q-content-input" rows="3" <?= !$isPending ? 'readonly' : '' ?>><?= e($q['content'] ?? '') ?></textarea>
                            </div>

                            <!-- Opsi Pilihan Jawaban (Jika Pilihan Ganda / True False) -->
                            <?php if ($qType !== 'essay' && !empty($q['options'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-slate-800 fs-xs d-flex justify-content-between">
                                        <span>Pilihan Jawaban (Pilih radio untuk menandai Kunci Jawaban Benar):</span>
                                        <span class="fs-3xs text-success fw-normal"><i class="bi bi-check-circle me-1"></i> 1 Jawaban Benar</span>
                                    </label>
                                    <div class="options-container d-flex flex-column gap-2">
                                        <?php foreach ($q['options'] as $optIdx => $opt): ?>
                                            <?php 
                                                $optLabel = $opt['label'] ?? chr(65 + $optIdx);
                                                $isCorrect = !empty($opt['is_correct']);
                                            ?>
                                            <div class="input-group input-group-sm option-row">
                                                <div class="input-group-text bg-slate-50 border-slate-200 border-end-0">
                                                    <input class="form-check-input mt-0 q-correct-radio" type="radio" name="correct_opt_<?= $idx ?>" value="<?= $optIdx ?>" <?= $isCorrect ? 'checked' : '' ?> <?= !$isPending ? 'disabled' : '' ?> title="Tandai sebagai jawaban benar">
                                                    <span class="ms-2 fw-bold text-slate-800"><?= e($optLabel) ?>.</span>
                                                </div>
                                                <input type="text" class="form-control border-slate-200 fs-xs q-opt-text" data-label="<?= e($optLabel) ?>" value="<?= e($opt['text'] ?? '') ?>" <?= !$isPending ? 'readonly' : '' ?>>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Pembahasan / Penjelasan Jawaban -->
                            <div class="p-3 bg-slate-50 rounded-3 border border-slate-100">
                                <label class="form-label mb-1 d-flex align-items-center gap-1 fs-3xs fw-semibold text-slate-700">
                                    <i class="bi bi-journal-text text-primary"></i> Pembahasan / Penjelasan Kunci Jawaban:
                                </label>
                                <textarea class="form-control bg-white border-slate-200 fs-xs rounded-3 q-explanation-input" rows="2" placeholder="Penjelasan solusi..." <?= !$isPending ? 'readonly' : '' ?>><?= e($q['explanation'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Bottom Action Toolbar -->
            <?php if ($draft['status'] === 'pending'): ?>
                <div class="card border-0 shadow-xs rounded-4 p-3 bg-white sticky-bottom mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h6 class="fw-bold text-slate-900 mb-0 fs-sm">Siap menyimpan ke Bank Soal?</h6>
                            <span class="text-muted fs-3xs">Hanya butir soal yang dicentang yang akan dimasukkan ke Bank Soal.</span>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= base_url('guru/ai-questions') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                            <button type="button" class="btn btn-sm btn-success px-4 rounded-pill fw-semibold shadow-xs d-flex align-items-center gap-2" id="btnImportBottom" onclick="AiReview.submitApprovedQuestions()">
                                <i class="bi bi-check2-circle"></i>
                                <span>Setujui & Simpan ke Bank Soal</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </form>
</div>

<!-- AI Review Workbench JS Module -->
<script src="<?= asset('js/ai-question-generator.js') ?>"></script>

