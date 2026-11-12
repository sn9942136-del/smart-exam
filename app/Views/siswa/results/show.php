<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<?php
$isReleased = $result['is_released'] ?? false;
$finalScore = (float)($result['final_score'] ?? 0);
$passingGrade = (float)($result['passing_grade'] ?? 75);
$isPassed = ($finalScore >= $passingGrade);
$integrityScore = (float)($result['proctoring_integrity_score'] ?? 100);
$questionAnswers = $questionAnswers ?? [];
?>

<?php if (!$isReleased): ?>
    <!-- Notice: Results Pending Release -->
    <div class="card border-0 shadow-sm rounded-4 p-5 bg-white text-center">
        <div class="rounded-circle bg-warning-subtle text-warning p-4 d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 84px; height: 84px;">
            <i class="bi bi-hourglass-split fs-1 text-warning"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">Hasil Ujian Sedang Dalam Proses Penilaian</h4>
        <p class="text-muted fs-6 mb-4 max-w-md mx-auto">
            Ujian <strong><?= e($result['exam_title']) ?></strong> telah berhasil Anda kumpulkan. Nilai dan evaluasi akan ditampilkan setelah guru pengampu menyelesaikan pemeriksaan dan mempublikasikan hasil resmi.
        </p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= base_url('siswa/history') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-clock-history me-1"></i> Kembali ke Riwayat
            </a>
            <a href="<?= base_url('siswa/dashboard') ?>" class="btn btn-primary-gradient rounded-pill px-4">
                <i class="bi bi-house me-1"></i> Dashboard Siswa
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Released Exam Results View -->
    <div class="row g-4 mb-4">
        <!-- Main Score Hero Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-white text-center position-relative overflow-hidden h-100 d-flex flex-column justify-content-between" style="background: <?= $isPassed ? 'linear-gradient(135deg, #059669 0%, #10b981 100%)' : 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)' ?>;">
                <div>
                    <span class="badge bg-white-20 text-white rounded-pill px-3 py-1.5 fs-xs fw-semibold mb-3">
                        <i class="bi bi-trophy me-1"></i> Evaluasi Nilai Akhir
                    </span>
                    <h5 class="fw-bold mb-1 text-white"><?= e($result['exam_title']) ?></h5>
                    <small class="text-white-75 fs-xs d-block mb-4">
                        <?= e($result['subject_name']) ?> &bull; KKM: <?= number_format($passingGrade, 0) ?>
                    </small>

                    <!-- Big Circular Score Display -->
                    <div class="rounded-circle bg-white text-dark shadow-lg d-inline-flex flex-column align-items-center justify-content-center mx-auto mb-4" style="width: 150px; height: 150px;">
                        <span class="fs-4xs text-uppercase text-muted fw-bold">Skor Nilai</span>
                        <h1 class="display-4 fw-extrabold mb-0 <?= $isPassed ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($finalScore, 1) ?>
                        </h1>
                        <span class="fs-4xs text-muted">dari 100.0</span>
                    </div>

                    <div class="mb-3">
                        <?php if ($isPassed): ?>
                            <span class="badge bg-white text-success fs-sm px-4 py-2 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> TUNTAS / LULUS KKM
                            </span>
                        <?php else: ?>
                            <span class="badge bg-white text-danger fs-sm px-4 py-2 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-arrow-repeat me-1"></i> BELUM TUNTAS (REMEDIAL)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-black-10 rounded-3 p-3 text-start fs-xs text-white-90 mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Waktu Pengumpulan:</span>
                        <strong class="text-white"><?= !empty($result['session_submit_time']) ? date('d M Y, H:i', strtotime($result['session_submit_time'])) : '-' ?> WIB</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Status Publikasi:</span>
                        <strong class="text-white"><i class="bi bi-check2-all me-1"></i> Resmi Dirilis</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown & AI Integrity Column -->
        <div class="col-lg-7">
            <!-- Score Breakdown Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart-steps text-primary"></i>
                    <span>Rincian Komponen Penilaian</span>
                </h6>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <span class="text-muted fs-3xs text-uppercase fw-bold d-block mb-1">Pilihan Ganda</span>
                            <h4 class="fw-bold text-primary mb-0"><?= number_format((float)($result['multiple_choice_score'] ?? 0), 1) ?></h4>
                            <small class="text-muted fs-3xs">Soal Obyektif</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <span class="text-muted fs-3xs text-uppercase fw-bold d-block mb-1">Esai / Uraian</span>
                            <h4 class="fw-bold text-info mb-0"><?= number_format((float)($result['essay_score'] ?? 0), 1) ?></h4>
                            <small class="text-muted fs-3xs">Koreksi Manual & AI</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <span class="text-muted fs-3xs text-uppercase fw-bold d-block mb-1">Jawaban Benar</span>
                            <h4 class="fw-bold text-success mb-0"><?= (int)($result['correct_answers'] ?? 0) ?></h4>
                            <small class="text-muted fs-3xs">Butir Soal</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 bg-body-tertiary border">
                            <span class="text-muted fs-3xs text-uppercase fw-bold d-block mb-1">Jawaban Salah / Kosong</span>
                            <h4 class="fw-bold text-danger mb-0"><?= (int)($result['wrong_answers'] ?? 0) ?></h4>
                            <small class="text-muted fs-3xs">Butir Soal</small>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-3 bg-light border d-flex justify-content-between align-items-center fs-xs">
                    <span class="text-muted">Total Butir Soal Terjawab:</span>
                    <strong class="text-dark"><?= (int)($result['total_answered'] ?? 0) ?> / <?= (int)($result['total_questions'] ?? 0) ?> Soal</strong>
                </div>
            </div>

            <!-- AI Proctoring Integrity Report Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-success"></i>
                        <span>Laporan Pengawasan Integritas AI</span>
                    </h6>
                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-2xs">
                        Dual Cam Verified
                    </span>
                </div>

                <div class="p-3 rounded-3 border bg-body-tertiary mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-xs fw-semibold text-dark">Skor Integritas Pengerjaan:</span>
                        <strong class="fs-6 <?= $integrityScore >= 80 ? 'text-success' : ($integrityScore >= 60 ? 'text-warning' : 'text-danger') ?>">
                            <?= number_format($integrityScore, 1) ?>%
                        </strong>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $integrityScore >= 80 ? 'bg-success' : ($integrityScore >= 60 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= min(max($integrityScore, 0), 100) ?>%;"></div>
                    </div>
                    <small class="text-muted fs-3xs d-block mt-2">
                        <i class="bi bi-info-circle me-1"></i> Skor integritas dihitung secara otomatis melalui deteksi AI terhadap wajah, tatapan mata, kebisingan, dan perangkat sekunder.
                    </small>
                </div>

                <div class="row g-2 fs-2xs text-muted">
                    <div class="col-6">
                        <div class="p-2 border rounded-2 bg-white d-flex align-items-center gap-2">
                            <i class="bi bi-camera-video-fill text-primary"></i>
                            <span>Webcam Depan: <strong>Terhubung</strong></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded-2 bg-white d-flex align-items-center gap-2">
                            <i class="bi bi-phone-fill text-warning"></i>
                            <span>Kamera Smartphone: <strong>Terhubung</strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="d-flex gap-2">
                <a href="<?= base_url('siswa/results') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Nilai
                </a>
                <button type="button" class="btn btn-outline-primary rounded-pill px-4" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Hasil
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- SECTION 2: QUESTION-BY-QUESTION REVIEW & AI EXPLAINER CHATBOT         -->
    <!-- ===================================================================== -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-3 mb-4 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text text-primary"></i>
                    <span>Review Pembahasan & Diskusi AI Tiap Soal</span>
                </h5>
                <p class="text-muted fs-xs mb-0">
                    Pelajari pembahasan setiap butir soal secara mendalam. Anda dapat berkonsultasi langsung dengan <strong>Chatbot AI Gemini</strong> untuk memahami konsep materi dan logika pengerjaan.
                </p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fs-xs fw-semibold">
                <i class="bi bi-stars me-1"></i> Gemini Explainer Ready
            </span>
        </div>

        <?php if (empty($questionAnswers)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                <span>Rincian butir soal belum tersedia atau sedang disinkronkan.</span>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-4">
                <?php foreach ($questionAnswers as $idx => $qa): 
                    $qNum = $qa['order_number'] ?? ($idx + 1);
                    $qType = $qa['question_type'] ?? 'multiple_choice';
                    $isCorrect = (int)($qa['is_correct'] ?? 0);
                    $scoreEarned = (float)($qa['score_earned'] ?? 0);
                    $isEssay = ($qType === 'essay');

                    $cardStatusClass = $isEssay ? 'status-essay' : ($isCorrect === 1 ? 'status-correct' : 'status-wrong');
                    
                    // Selected text & Correct text for JSON passing
                    $studentAnsText = $isEssay ? ($qa['essay_answer'] ?? '') : ($qa['selected_option_text'] ?? '');
                    $correctAnsText = $isEssay ? ($qa['question_explanation'] ?? 'Sesuai rubrik guru') : ($qa['correct_option_text'] ?? '');
                ?>
                    <div class="card rounded-4 p-4 question-review-card <?= $cardStatusClass ?>" id="question-card-<?= (int)$qa['id'] ?>">
                        <!-- Question Header -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark text-white rounded-pill px-2.5 py-1 fs-xs fw-bold">
                                    Soal #<?= (int)$qNum ?>
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 fs-3xs">
                                    <?= $isEssay ? 'Esai / Uraian' : 'Pilihan Ganda' ?>
                                </span>
                                <span class="badge bg-light text-muted border rounded-pill px-2 py-0.5 fs-3xs">
                                    Bobot: <?= (float)($qa['question_weight'] ?? 1) ?> poin
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if ($isEssay): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-1 fs-xs fw-semibold">
                                        <i class="bi bi-pencil me-1"></i> Skor: <?= number_format($scoreEarned, 1) ?>
                                    </span>
                                <?php elseif ($isCorrect === 1): ?>
                                    <span class="badge bg-success text-white rounded-pill px-3 py-1 fs-xs fw-bold shadow-3xs">
                                        <i class="bi bi-check-circle-fill me-1"></i> Benar (+<?= number_format($scoreEarned, 1) ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 fs-xs fw-bold shadow-3xs">
                                        <i class="bi bi-x-circle-fill me-1"></i> Salah (0.0)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Question Content -->
                        <div class="question-content text-dark fs-6 mb-3 p-3 bg-light rounded-3 border">
                            <?= nl2br(e($qa['question_content'])) ?>
                        </div>

                        <!-- Options or Essay Answer Display -->
                        <?php if (!$isEssay && !empty($qa['options'])): ?>
                            <div class="options-container d-flex flex-column gap-2 mb-3">
                                <?php foreach ($qa['options'] as $opt): 
                                    $isCorrectKey = !empty($opt['is_correct']);
                                    $isSelectedByStudent = (!empty($qa['selected_option_id']) && (int)$qa['selected_option_id'] === (int)$opt['id']);
                                    
                                    $optClass = 'option-review-item';
                                    if ($isCorrectKey && $isSelectedByStudent) {
                                        $optClass .= ' is-correct-key';
                                    } elseif ($isCorrectKey) {
                                        $optClass .= ' is-correct-key';
                                    } elseif ($isSelectedByStudent && !$isCorrectKey) {
                                        $optClass .= ' is-student-wrong';
                                    }
                                ?>
                                    <div class="<?= $optClass ?> d-flex justify-content-between align-items-center fs-xs">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge <?= $isCorrectKey ? 'bg-success text-white' : ($isSelectedByStudent ? 'bg-danger text-white' : 'bg-secondary-subtle text-dark') ?> rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                                <?= e($opt['option_key'] ?? '') ?>
                                            </span>
                                            <span><?= e($opt['option_text'] ?? $opt['content'] ?? '') ?></span>
                                        </div>

                                        <div>
                                            <?php if ($isCorrectKey && $isSelectedByStudent): ?>
                                                <span class="badge bg-success text-white rounded-pill px-2 py-0.5 fs-4xs fw-semibold">
                                                    <i class="bi bi-check2-all me-1"></i>Pilihan Anda &bull; Kunci Benar
                                                </span>
                                            <?php elseif ($isCorrectKey): ?>
                                                <span class="badge bg-success-subtle text-success border border-success rounded-pill px-2 py-0.5 fs-4xs fw-semibold">
                                                    <i class="bi bi-check2 me-1"></i>Kunci Jawaban Benar
                                                </span>
                                            <?php elseif ($isSelectedByStudent): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill px-2 py-0.5 fs-4xs fw-semibold">
                                                    <i class="bi bi-x me-1"></i>Pilihan Anda (Kurang Tepat)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($isEssay): ?>
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <span class="text-muted fs-3xs fw-bold text-uppercase d-block mb-1">Jawaban Anda:</span>
                                <p class="mb-2 fs-xs text-dark"><?= !empty($qa['essay_answer']) ? nl2br(e($qa['essay_answer'])) : '<em>(Tidak diisi)</em>' ?></p>

                                <?php if (!empty($qa['teacher_feedback'])): ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <span class="text-info fs-3xs fw-bold text-uppercase d-block mb-0.5">Umpan Balik Guru:</span>
                                        <p class="mb-0 fs-xs text-secondary italic">"<?= e($qa['teacher_feedback']) ?>"</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Explanation Banner (if available) -->
                        <?php if (!empty($qa['question_explanation'])): ?>
                            <div class="p-3 bg-info-subtle border-start border-4 border-info rounded-3 mb-3 fs-xs text-dark">
                                <div class="fw-bold text-info mb-1 d-flex align-items-center gap-1">
                                    <i class="bi bi-lightbulb-fill text-warning"></i>
                                    <span>Penjelasan Kurikulum / Guru:</span>
                                </div>
                                <div class="text-secondary"><?= nl2br(e($qa['question_explanation'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Interactive Question Explainer Chatbot Button -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <small class="text-muted fs-3xs">
                                <i class="bi bi-shield-check text-success me-0.5"></i> Pembahasan AI terlindungi guardrails akademik
                            </small>
                            <button 
                                type="button" 
                                class="btn btn-sm btn-primary-gradient rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-2xs"
                                onclick="EduAiQuestionExplainer.open(
                                    <?= (int)$qa['id'] ?>,
                                    <?= (int)$qNum ?>,
                                    <?= htmlspecialchars(json_encode($qa['question_content']), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($studentAnsText), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($correctAnsText), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= (int)$isCorrect ?>
                                )">
                                <i class="bi bi-stars"></i>
                                <span>💬 Diskusi AI Soal Ini</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL: POST-EXAM QUESTION EXPLAINER CHATBOT (Type 2)                  -->
    <!-- ===================================================================== -->
    <div class="modal fade modal-question-explainer" id="modalQuestionExplainer" tabindex="-1" aria-hidden="true" data-role="siswa">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <!-- Modal Header -->
                <div class="modal-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="rounded-circle bg-primary-subtle p-2 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-lightbulb-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="modal-title fw-bold fs-6 mb-0 text-dark" id="explainerModalQuestionNumber">Pembahasan Soal</h6>
                                <span class="badge bg-primary-subtle text-primary fs-3xs px-2 py-0.5 rounded-pill">AI Explainer</span>
                            </div>
                            <small class="text-muted fs-3xs d-block mt-0.5">
                                <i class="bi bi-shield-check text-success me-1"></i> Khusus Butir Soal Ini &bull; Integritas Akademik
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Guardrail Alert Banner -->
                <div class="bg-primary-subtle text-primary px-4 py-2 fs-3xs border-bottom d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill text-primary"></i>
                    <span><strong>Guardrail Aktif:</strong> Asisten AI hanya akan membahas materi, logika berpikir, dan konsep seputar butir soal ini.</span>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-0 d-flex flex-column">
                    <!-- Question Context Preview Box -->
                    <div id="explainerQuestionContext" class="explainer-context-box p-3 border-bottom bg-slate-50">
                        <!-- Filled dynamically by JavaScript -->
                    </div>

                    <!-- Quick Suggestions for this question -->
                    <div class="p-2.5 bg-body-tertiary border-bottom d-flex gap-1.5 overflow-x-auto no-scrollbar">
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-1 px-3 btn-explainer-chip d-inline-flex align-items-center gap-1.5" data-prompt="Jelaskan langkah penyelesaian dan logika konsep soal ini secara mendalam">
                            <i class="bi bi-mortarboard text-primary"></i>
                            <span>Langkah Logika Konsep</span>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-1 px-3 btn-explainer-chip d-inline-flex align-items-center gap-1.5" data-prompt="Mengapa opsi jawaban saya keliru atau apa jebakan dari soal ini?">
                            <i class="bi bi-exclamation-triangle text-warning"></i>
                            <span>Analisis Kekeliruan Jawaban</span>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill py-1 px-3 btn-explainer-chip d-inline-flex align-items-center gap-1.5" data-prompt="Berikan tips praktis, rumus cepat, atau analogi untuk soal seperti ini">
                            <i class="bi bi-lightning-charge text-danger"></i>
                            <span>Tips &amp; Rumus Praktis</span>
                        </button>
                    </div>

                    <!-- Chat Messages Stream -->
                    <div id="explainerChatMessages" class="explainer-chat-body p-3 flex-grow-1">
                        <!-- Loaded dynamically -->
                    </div>

                    <!-- Typing Indicator -->
                    <div id="explainerChatTyping" class="floating-chat-typing px-3 py-1.5 d-none align-items-center gap-2 text-muted fs-3xs bg-body-tertiary border-top">
                        <div class="typing-dots d-flex gap-1">
                            <span></span><span></span><span></span>
                        </div>
                        <span>Gemini AI sedang menganalisis butir soal...</span>
                    </div>
                </div>

                <!-- Modal Footer (Input Form) -->
                <div class="modal-footer p-3 bg-white border-top">
                    <form id="explainerChatForm" class="w-100 d-flex gap-2" onsubmit="return false;">
                        <input 
                            type="text" 
                            id="explainerChatInput" 
                            class="form-control form-control-sm rounded-pill px-3 fs-xs" 
                            placeholder="Tanyakan hal spesifik tentang konsep atau pembahasan soal ini..."
                            autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3.5 d-flex align-items-center gap-1.5" title="Kirim">
                            <i class="bi bi-send-fill fs-xs"></i>
                            <span class="fs-xs fw-semibold">Tanya</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
