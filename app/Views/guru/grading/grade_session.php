<div class="container-fluid py-2">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-xs">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/grading') ?>" class="text-decoration-none">Penilaian</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/grading/exam/' . $header['exam_id']) ?>" class="text-decoration-none">Penyerahan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Koreksi Lembar Jawaban</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-5">
                <i class="bi bi-pencil-square text-primary"></i> Meja Koreksi & Review Jawaban
            </h4>
            <p class="text-muted fs-xs mb-0">
                Peserta: <strong class="text-dark"><?= e($header['student_name']) ?></strong> (<?= e($header['student_nis'] ?? $header['student_email']) ?>) | 
                Ujian: <strong class="text-dark"><?= e($header['exam_title']) ?></strong> | 
                Mata Pelajaran: <strong class="text-dark"><?= e($header['subject_name']) ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('guru/grading/exam/' . $header['exam_id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 d-flex align-items-center gap-1.5">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <a href="<?= base_url('guru/reports/session/' . $header['id'] . '/pdf') ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3.5 d-flex align-items-center gap-1.5" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i> Unduh Rapor PDF
            </a>
        </div>
    </div>

    <!-- Student Score & Status Summary Banner -->
    <div class="card border-0 shadow-xs rounded-4 mb-4 bg-white p-4">
        <div class="row align-items-center g-3">
            <div class="col-md-3 border-end border-slate-100">
                <div class="text-muted fs-xs fw-medium">Nilai Akhir Saat Ini</div>
                <div class="d-flex align-items-baseline gap-2 mt-1">
                    <h2 class="fw-bold mb-0 text-primary fs-3" id="finalScoreDisplay">
                        <?= number_format((float)($header['final_score'] ?? 0.0), 1) ?>
                    </h2>
                    <span class="text-muted fs-xs">/ 100</span>
                </div>
                <div class="mt-2">
                    <?php if (!empty($header['is_passed'])): ?>
                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fs-xs fw-medium">
                            <i class="bi bi-check-lg"></i> LULUS (KKM: <?= (float)$header['passing_grade'] ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fs-xs fw-medium">
                            <i class="bi bi-x-lg"></i> REMEDIAL (KKM: <?= (float)$header['passing_grade'] ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-3 border-end border-slate-100">
                <div class="text-muted fs-xs fw-medium">Rincian Perolehan Nilai</div>
                <div class="d-flex flex-column gap-1.5 mt-2 fs-xs">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Nilai Pilihan Ganda:</span>
                        <strong class="text-slate-800"><?= number_format((float)($header['multiple_choice_score'] ?? 0), 1) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Nilai Esai:</span>
                        <strong class="text-primary" id="essayScoreDisplay"><?= number_format((float)($header['essay_score'] ?? 0), 1) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Integritas AI:</span>
                        <strong class="text-success"><?= number_format((float)($header['integrity_score'] ?? 100), 0) ?>%</strong>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Form Feedback Global Guru -->
                <div class="p-3 bg-slate-50 rounded-3 border border-slate-200">
                    <label class="form-label mb-1.5 d-flex justify-content-between fs-xs">
                        <span class="fw-semibold text-slate-800"><i class="bi bi-chat-left-quote text-primary me-1"></i> Catatan & Umpan Balik Guru untuk Peserta:</span>
                        <span class="text-muted fw-normal">Tampil pada rapor</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <textarea class="form-control bg-white border-slate-200 fs-xs" id="globalTeacherFeedback" rows="2" placeholder="Contoh: Pemahaman konsep sudah baik, tingkatkan ketelitian pada soal cerita..."><?= e($header['exam_feedback'] ?? '') ?></textarea>
                        <button type="button" class="btn btn-primary px-3 fw-semibold shadow-xs" onclick="GradeSessionModule.saveGlobalFeedback(<?= (int)($header['result_id'] ?? 0) ?>)">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions Answer Review List -->
    <div class="d-flex flex-column gap-3 mb-4">
        <h5 class="fw-bold text-slate-900 mb-0 fs-6">Rincian Lembar Jawaban Peserta (<?= count($answers) ?> Butir Soal)</h5>

        <?php foreach ($answers as $idx => $ans): ?>
            <?php 
                $num = $idx + 1;
                $isEssay = ($ans['question_type'] === 'essay');
                $isCorrect = !empty($ans['is_correct']);
                $maxWeight = (float)($ans['question_weight'] > 0 ? $ans['question_weight'] : ($isEssay ? 4.0 : 1.0));
                $earnedScore = (float)($ans['score_earned'] ?? 0.0);
            ?>
            <div class="card border rounded-3 shadow-sm question-answer-card <?= $isEssay ? 'border-start border-4 border-warning' : '' ?>" id="answer_card_<?= $ans['id'] ?>">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-2.5 py-1 fs-3xs">Soal #<?= $num ?></span>
                        <span class="badge bg-secondary-subtle text-secondary fs-3xs text-uppercase"><?= e($ans['question_type']) ?></span>
                        <span class="badge bg-light text-slate-700 border fs-3xs">Bobot Max: <?= $maxWeight ?></span>
                    </div>

                    <div>
                        <?php if (!$isEssay): ?>
                            <?php if ($isCorrect): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-2 fs-3xs fw-bold">
                                    <i class="bi bi-check-circle-fill"></i> BENAR (+<?= $earnedScore ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-2 fs-3xs fw-bold">
                                    <i class="bi bi-x-circle-fill"></i> SALAH (0)
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1 rounded-2 fs-3xs fw-bold" id="badge_essay_status_<?= $ans['id'] ?>">
                                <i class="bi bi-pencil-fill"></i> Nilai Esai: <?= $earnedScore ?> / <?= $maxWeight ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Pertanyaan -->
                    <div class="fw-semibold text-slate-800 fs-sm mb-3">
                        <?= nl2br(e($ans['question_content'])) ?>
                    </div>

                    <!-- Pilihan Ganda Display -->
                    <?php if (!$isEssay): ?>
                        <div class="mb-3">
                            <label class="form-label mb-2 fs-3xs text-muted text-uppercase">Pilihan Jawaban & Respon Siswa:</label>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($ans['options'] as $optIdx => $opt): ?>
                                    <?php 
                                        $optLabel = chr(65 + $optIdx);
                                        $isStudentChoice = ((int)$ans['selected_option_id'] === (int)$opt['id']);
                                        $isKeyCorrect = !empty($opt['is_correct']);
                                        
                                        $bgClass = 'bg-slate-50 border';
                                        $badgeExtra = '';

                                        if ($isKeyCorrect && $isStudentChoice) {
                                            $bgClass = 'bg-success-subtle border border-success text-success-emphasis';
                                            $badgeExtra = '<span class="badge bg-success ms-auto fs-3xs"><i class="bi bi-check-lg"></i> Jawaban Siswa & Kunci Benar</span>';
                                        } elseif ($isStudentChoice && !$isKeyCorrect) {
                                            $bgClass = 'bg-danger-subtle border border-danger text-danger-emphasis';
                                            $badgeExtra = '<span class="badge bg-danger ms-auto fs-3xs"><i class="bi bi-x-lg"></i> Pilihan Siswa (Salah)</span>';
                                        } elseif ($isKeyCorrect) {
                                            $bgClass = 'bg-success-subtle border border-success text-success-emphasis';
                                            $badgeExtra = '<span class="badge bg-success ms-auto fs-3xs"><i class="bi bi-key-fill"></i> Kunci Jawaban Benar</span>';
                                        }
                                    ?>
                                    <div class="p-2.5 rounded-2 d-flex align-items-center gap-2 fs-3xs <?= $bgClass ?>">
                                        <span class="fw-bold font-monospace"><?= $optLabel ?>.</span>
                                        <span><?= e($opt['option_text']) ?></span>
                                        <?= $badgeExtra ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <!-- Esai Response & Grading Controls (UC-23) -->
                    <?php else: ?>
                        <div class="mb-3 p-3 bg-slate-50 rounded-2 border">
                            <label class="form-label mb-1 d-flex justify-content-between fs-3xs text-muted text-uppercase">
                                <span><i class="bi bi-person-fill text-primary"></i> Jawaban Esai Siswa:</span>
                                <span class="fs-3xs text-muted"><?= date('H:i:s', strtotime($ans['answered_at'] ?? 'now')) ?></span>
                            </label>
                            <div class="p-3 bg-white rounded-2 border fs-3xs text-slate-800 font-monospace" style="white-space: pre-wrap;">
                                <?= !empty($ans['essay_answer']) ? e($ans['essay_answer']) : '<em class="text-muted">Siswa tidak mengisi jawaban untuk butir esai ini.</em>' ?>
                            </div>
                        </div>

                        <!-- Form Penilaian Esai Guru -->
                        <div class="p-3 bg-slate-50 rounded-2 border mb-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label mb-1 fs-3xs">
                                        Beri Nilai Esai (0 - <?= $maxWeight ?>):
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.5" min="0" max="<?= $maxWeight ?>" 
                                               class="form-control fw-bold text-center fs-sm" 
                                               id="essay_score_<?= $ans['id'] ?>" 
                                               value="<?= $earnedScore ?>">
                                        <span class="input-group-text bg-white">/ <?= $maxWeight ?></span>
                                    </div>
                                    <div class="d-flex gap-1 mt-1.5">
                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-2" onclick="GradeSessionModule.setQuickScore(<?= $ans['id'] ?>, 0)">0</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-2" onclick="GradeSessionModule.setQuickScore(<?= $ans['id'] ?>, <?= $maxWeight * 0.5 ?>)">50%</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-2" onclick="GradeSessionModule.setQuickScore(<?= $ans['id'] ?>, <?= $maxWeight * 0.75 ?>)">75%</button>
                                        <button type="button" class="btn btn-xs btn-outline-success rounded-2" onclick="GradeSessionModule.setQuickScore(<?= $ans['id'] ?>, <?= $maxWeight ?>)">100%</button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label mb-1 fs-3xs">
                                        Catatan / Feedback Guru untuk Jawaban Ini:
                                    </label>
                                    <input type="text" class="form-control form-control-sm" 
                                           id="essay_feedback_<?= $ans['id'] ?>" 
                                           placeholder="Contoh: Analisis tepat, perhitungan sedikit keliru di baris terakhir." 
                                           value="<?= e($ans['teacher_feedback'] ?? '') ?>">
                                </div>

                                <div class="col-md-2 d-grid align-self-end">
                                    <button type="button" class="btn btn-sm btn-primary rounded-2 fw-medium py-1.5" 
                                            id="btn_save_essay_<?= $ans['id'] ?>"
                                            onclick="GradeSessionModule.saveEssayScore(<?= (int)$ans['id'] ?>, <?= $maxWeight ?>)">
                                        <i class="bi bi-check2"></i> Simpan
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Pembahasan Soal & Tombol AI Explanation (UC-16a) -->
                    <div class="p-3 bg-slate-50 rounded-2 border-top mt-3" id="explanation_box_<?= $ans['id'] ?>">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fs-3xs fw-semibold text-slate-800 d-flex align-items-center gap-1">
                                <i class="bi bi-lightbulb-fill text-warning"></i> Pembahasan & Penjelasan AI:
                            </span>
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-2 d-flex align-items-center gap-1" 
                                    id="btn_ai_<?= $ans['id'] ?>"
                                    onclick="GradeSessionModule.generateAiExplanation(<?= (int)$ans['id'] ?>)">
                                <i class="bi bi-robot"></i> 
                                <span><?= !empty($ans['ai_explanation']) ? 'Regenerasi Penjelasan AI' : 'Generate Penjelasan AI' ?></span>
                            </button>
                        </div>
                        <div class="fs-3xs text-muted" id="ai_text_<?= $ans['id'] ?>">
                            <?php if (!empty($ans['ai_explanation'])): ?>
                                <?= nl2br(e($ans['ai_explanation'])) ?>
                            <?php elseif (!empty($ans['question_explanation'])): ?>
                                <?= nl2br(e($ans['question_explanation'])) ?>
                            <?php else: ?>
                                <span class="fst-italic text-secondary">Belum ada pembahasan AI untuk butir soal ini. Klik tombol di atas untuk membuat penjelasan cerdas secara otomatis.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
const GradeSessionModule = {
    setQuickScore(answerId, val) {
        const input = document.getElementById(`essay_score_${answerId}`);
        if (input) {
            input.value = val;
        }
    },

    async saveEssayScore(answerId, maxWeight) {
        const scoreInput = document.getElementById(`essay_score_${answerId}`);
        const feedbackInput = document.getElementById(`essay_feedback_${answerId}`);
        const btn = document.getElementById(`btn_save_essay_${answerId}`);

        const score = parseFloat(scoreInput?.value || '0');
        const feedback = feedbackInput?.value || '';

        if (score < 0 || score > maxWeight) {
            alert(`Nilai harus antara 0 dan ${maxWeight}`);
            return;
        }

        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const res = await fetch(`${baseUrl}/guru/grading/save-essay-score`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    answer_id: answerId,
                    score: score,
                    feedback: feedback
                })
            });

            const data = await res.json();
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;

            if (data.success) {
                const statusBadge = document.getElementById(`badge_essay_status_${answerId}`);
                if (statusBadge) {
                    statusBadge.innerHTML = `<i class="bi bi-check-circle-fill"></i> Nilai Esai: ${score} / ${maxWeight}`;
                }

                const finalScoreEl = document.getElementById('finalScoreDisplay');
                if (finalScoreEl && data.final_score !== undefined) {
                    finalScoreEl.innerText = parseFloat(data.final_score).toFixed(1);
                }

                if (window.App && typeof window.App.toast === 'function') {
                    window.App.toast('Nilai esai berhasil disimpan.', 'success');
                } else {
                    alert('Nilai esai berhasil disimpan.');
                }
            } else {
                alert(data.error || 'Gagal menyimpan nilai esai.');
            }
        } catch (e) {
            console.error(e);
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
            alert('Kesalahan jaringan.');
        }
    },

    async saveGlobalFeedback(resultId) {
        const feedbackInput = document.getElementById('globalTeacherFeedback');
        const feedback = feedbackInput?.value || '';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const res = await fetch(`${baseUrl}/guru/grading/save-feedback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    result_id: resultId,
                    feedback: feedback
                })
            });

            const data = await res.json();
            if (data.success) {
                if (window.App && typeof window.App.toast === 'function') {
                    window.App.toast('Catatan guru berhasil disimpan.', 'success');
                } else {
                    alert('Catatan guru berhasil disimpan.');
                }
            } else {
                alert(data.error || 'Gagal menyimpan catatan.');
            }
        } catch (e) {
            console.error(e);
            alert('Kesalahan jaringan.');
        }
    },

    async generateAiExplanation(answerId) {
        const textContainer = document.getElementById(`ai_text_${answerId}`);
        const btn = document.getElementById(`btn_ai_${answerId}`);

        if (!textContainer || !btn) return;

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menganalisis...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const res = await fetch(`${baseUrl}/guru/grading/generate-ai-explanation`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ answer_id: answerId })
            });

            const data = await res.json();
            btn.disabled = false;
            btn.innerHTML = origHtml;

            if (data.success && data.ai_explanation) {
                textContainer.innerHTML = data.ai_explanation.replace(/\n/g, '<br>');
                if (window.App && typeof window.App.toast === 'function') {
                    window.App.toast('Penjelasan AI berhasil digenerasi.', 'success');
                }
            } else {
                alert(data.error || 'Gagal menghasilkan penjelasan AI.');
            }
        } catch (e) {
            console.error(e);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            alert('Kesalahan jaringan.');
        }
    }
};
</script>
