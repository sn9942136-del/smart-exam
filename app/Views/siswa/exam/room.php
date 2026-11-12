<?php
/**
 * Room Ujian Siswa (Mesin CBT Fullscreen)
 * UC-10: Mengerjakan Soal Ujian
 * UC-11: Auto-Save Jawaban
 * UC-12: Submit Ujian
 * UC-14: Rekoneksi Sesi
 */
?>

<div class="container-fluid max-w-1400">
    <div class="row g-3 g-lg-4">
        <!-- Kolom Kiri: Lembar Soal & Pilihan Jawaban -->
        <div class="col-lg-8 col-xl-9">
            <div class="exam-question-card mb-3">
                <!-- Header Soal: Nomor, Tipe Soal, & Bobot Poin -->
                <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-3 border-bottom border-slate-100 gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fs-5 fw-bold text-dark">
                            Soal No. <span id="currentQuestionNumber" class="text-primary">1</span>
                        </span>
                        <span class="text-muted fs-xs">dari <span id="totalQuestionsCount"><?= count($questions) ?></span></span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span id="currentQuestionTypeBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs rounded-pill px-2.5 py-1 fw-semibold">
                            Pilihan Ganda
                        </span>
                        <span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-3xs rounded-pill px-2.5 py-1 fw-medium">
                            Bobot: <strong id="currentQuestionWeight">1.0</strong> Poin
                        </span>
                    </div>
                </div>

                <!-- Gambar Lampiran Soal (Jika Ada) -->
                <div id="questionImageContainer" class="d-none text-center mb-3"></div>

                <!-- Isi Teks Soal -->
                <div id="questionContentBody" class="exam-question-text user-select-none">
                    <!-- Konten soal akan dirender secara dinamis oleh ExamEngine -->
                </div>

                <!-- Pilihan Jawaban / Textarea Esai -->
                <div id="questionOptionsArea" class="mb-4">
                    <!-- Opsi jawaban dirender oleh ExamEngine -->
                </div>

                <!-- Footer Navigasi Lembar Soal -->
                <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top border-slate-100 gap-2">
                    <!-- Tombol Soal Sebelumnya -->
                    <button type="button" id="btnPrevQuestion" class="btn btn-outline-secondary btn-sm px-3.5 py-1.5 rounded-pill fw-semibold" onclick="examEngine.prevQuestion()">
                        <i class="bi bi-chevron-left me-1"></i> Soal Sebelumnya
                    </button>

                    <!-- Checkbox Tandai Ragu-ragu -->
                    <div class="form-check form-switch d-inline-flex align-items-center gap-2 m-0 bg-warning-subtle text-warning-emphasis px-3 py-1.5 rounded-pill border border-warning-subtle">
                        <input class="form-check-input mt-0" type="checkbox" id="flagRaguCheckbox" onchange="examEngine.toggleFlag()">
                        <label class="form-check-label fs-xs fw-bold flag-checkbox-label" for="flagRaguCheckbox">
                            <i class="bi bi-flag-fill me-1 text-warning"></i> Ragu-ragu
                        </label>
                    </div>

                    <!-- Tombol Soal Berikutnya -->
                    <button type="button" id="btnNextQuestion" class="btn btn-primary btn-sm px-4 py-1.5 rounded-pill fw-semibold shadow-xs" onclick="examEngine.nextQuestion()">
                        Soal Berikutnya <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Status Kamera AI & Nomor Palet Soal -->
        <div class="col-lg-4 col-xl-3">
            <!-- Status Kamera AI & Pengawasan Real-Time -->
            <?php if (!empty($exam['is_dual_cam_required']) || !empty($exam['has_ai_proctoring']) || !isset($exam['has_ai_proctoring'])): ?>
                <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <span class="fs-xs fw-bold text-dark d-flex align-items-center gap-1.5">
                            <i class="bi bi-shield-check text-success"></i> Pengawasan AI
                        </span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs rounded-pill" id="proctoringStateBadge">
                            <i class="bi bi-broadcast me-0.5"></i> Aktif
                        </span>
                    </div>
                    <div class="d-flex flex-column gap-1.5 fs-3xs">
                        <div class="d-flex justify-content-between align-items-center px-2.5 py-1.5 rounded-3 bg-light border border-slate-100">
                            <span class="d-flex align-items-center gap-1.5 text-secondary">
                                <i class="bi bi-camera-video text-primary"></i>
                                <span>Kamera Depan</span>
                            </span>
                            <span class="badge bg-success-subtle text-success" id="cam1StatusBadge">Terkoneksi</span>
                        </div>
                        <?php if (!empty($exam['is_dual_cam_required'])): ?>
                            <div class="d-flex justify-content-between align-items-center px-2.5 py-1.5 rounded-3 bg-light border border-slate-100">
                                <span class="d-flex align-items-center gap-1.5 text-secondary">
                                    <i class="bi bi-phone text-warning"></i>
                                    <span>Kamera Samping</span>
                                </span>
                                <span class="badge bg-success-subtle text-success" id="cam2StatusBadge">Terhubung</span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center px-2.5 py-1.5 rounded-3 bg-light border border-slate-100">
                            <span class="d-flex align-items-center gap-1.5 text-secondary">
                                <i class="bi bi-exclamation-triangle text-danger"></i>
                                <span>Teguran AI</span>
                            </span>
                            <span class="badge bg-secondary-subtle text-dark" id="warningCountBadge">0 Teguran</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Palet Nomor Soal -->
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white position-sticky" style="top: 74px;">
                <!-- Header Palet -->
                <div class="d-flex justify-content-between align-items-center mb-2.5">
                    <h6 class="fw-bold text-dark mb-0 fs-xs d-flex align-items-center">
                        <i class="bi bi-grid-3x3-gap-fill text-primary me-1.5"></i> Nomor Soal
                    </h6>
                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-3xs rounded-pill">
                        Total <?= count($questions) ?> Soal
                    </span>
                </div>

                <!-- Legenda Status Soal -->
                <div class="d-flex flex-wrap gap-2 mb-3 fs-3xs text-muted">
                    <div class="d-flex align-items-center gap-1">
                        <span class="exam-palette-btn answered" style="width: 12px; height: 12px; min-width: 12px; border-radius: 3px;"></span>
                        <span>Dijawab (<strong id="countAnsweredBadge"><?= (int)($counts['answered'] ?? 0) ?></strong>)</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="exam-palette-btn flagged" style="width: 12px; height: 12px; min-width: 12px; border-radius: 3px;"></span>
                        <span>Ragu (<strong id="countFlaggedBadge"><?= (int)($counts['flagged'] ?? 0) ?></strong>)</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="exam-palette-btn" style="width: 12px; height: 12px; min-width: 12px; border-radius: 3px;"></span>
                        <span>Belum (<strong id="countUnansweredBadge"><?= (int)($counts['unanswered'] ?? count($questions)) ?></strong>)</span>
                    </div>
                </div>

                <!-- Grid Tombol Nomor Soal -->
                <div class="exam-palette-grid mb-3">
                    <?php foreach ($questions as $idx => $q): ?>
                        <button type="button" 
                                id="paletteBtn_<?= $idx ?>" 
                                class="exam-palette-btn" 
                                onclick="examEngine.goToQuestion(<?= $idx ?>)"
                                title="Soal Nomor <?= $idx + 1 ?>"
                                aria-label="Pilih Soal Nomor <?= $idx + 1 ?>">
                            <?= $idx + 1 ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Tombol Selesaikan & Submit Ujian -->
                <div class="d-grid pt-2 border-top border-slate-100">
                    <button type="button" class="btn btn-danger rounded-3 py-2 fw-bold shadow-xs d-flex align-items-center justify-content-center gap-1.5" onclick="examEngine.openSubmitModal()" aria-label="Selesaikan dan Kumpulkan Ujian">
                        <i class="bi bi-check2-circle fs-5"></i> Selesaikan Ujian
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Submit Ujian -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-question-diamond-fill text-danger fs-5"></i>
                    <h6 class="modal-title fw-bold fs-6 mb-0 text-dark" id="confirmSubmitModalLabel">
                        Konfirmasi Pengumpulan Ujian
                    </h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="fs-xs text-muted mb-3">
                    Apakah Anda yakin ingin mengakhiri dan mengumpulkan lembar jawaban ujian ini? Setelah dikumpulkan, Anda tidak dapat mengubah jawaban lagi.
                </p>

                <!-- Ringkasan Status Lembar Jawaban -->
                <div class="row g-2 text-center mb-4">
                    <div class="col-4">
                        <div class="p-2.5 bg-success-subtle text-success border border-success-subtle rounded-3">
                            <small class="fs-3xs d-block text-muted">Sudah Dijawab</small>
                            <span class="fs-5 fw-bold" id="modalSummaryAnswered">0</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2.5 bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-3">
                            <small class="fs-3xs d-block text-muted">Ragu-ragu</small>
                            <span class="fs-5 fw-bold" id="modalSummaryFlagged">0</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2.5 bg-secondary-subtle text-secondary border border-secondary-subtle rounded-3">
                            <small class="fs-3xs d-block text-muted">Belum Dijawab</small>
                            <span class="fs-5 fw-bold" id="modalSummaryUnanswered">0</span>
                        </div>
                    </div>
                </div>

                <!-- Checkbox Konfirmasi -->
                <div class="form-check p-3 bg-light rounded-3 border mb-3">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="checkAgreeSubmit" onchange="document.getElementById('btnConfirmSubmitExam').disabled = !this.checked">
                    <label class="form-check-label fs-xs text-dark fw-semibold" for="checkAgreeSubmit">
                        Saya yakin telah memeriksa seluruh jawaban dan ingin mengumpulkan ujian ini.
                    </label>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm px-3" data-bs-dismiss="modal">
                    Batal &amp; Lanjutkan Ujian
                </button>
                <button type="button" id="btnConfirmSubmitExam" class="btn btn-danger rounded-pill btn-sm px-4 fw-semibold shadow-xs" onclick="examEngine.submitExam()" disabled>
                    <i class="bi bi-check2-circle me-1.5"></i> Ya, Kumpulkan Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Video Feeds for Client-Side AI Proctoring -->
<video id="camera-preview" autoplay playsinline muted style="display:none;"></video>
<video id="camera-secondary-preview" autoplay playsinline muted style="display:none;"></video>

<!-- Inisialisasi Skrip ExamEngine & AiProctoringEngine -->
<script>
    const examQuestions = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const savedAnswers = <?= json_encode($savedAnswers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const examConfig = {
        sessionToken: <?= json_encode($sessionToken) ?>,
        questions: examQuestions,
        savedAnswers: savedAnswers,
        remainingSeconds: <?= (int)$remainingSeconds ?>,
        submitUrl: <?= json_encode(base_url('siswa/exam/submit')) ?>,
        csrfToken: <?= json_encode(csrf_token()) ?>
    };

    const hasAiProctoring = <?= !empty($exam['has_ai_proctoring']) || !isset($exam['has_ai_proctoring']) ? 'true' : 'false' ?>;

    let examEngine = null;
    let aiProctoringEngine = null;

    document.addEventListener('DOMContentLoaded', async () => {
        // 1. Inisialisasi Engine CBT
        examEngine = new ExamEngine(examConfig);

        // 2. Inisialisasi Engine AI Proctoring jika aktif
        if (hasAiProctoring) {
            aiProctoringEngine = new AiProctoringEngine({
                examSessionId: <?= (int)($session['id'] ?? 0) ?>,
                sessionToken: <?= json_encode($sessionToken) ?>,
                csrfToken: <?= json_encode(csrf_token()) ?>,
                baseUrl: <?= json_encode(base_url()) ?>
            });

            // Mulai kamera untuk inferensi client AI
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
                    const videoEl = document.getElementById('camera-preview');
                    if (videoEl) {
                        videoEl.srcObject = stream;
                        videoEl.onloadedmetadata = () => {
                            videoEl.play();
                            aiProctoringEngine.start();
                        };
                    }
                } catch (camErr) {
                    console.warn('[AI Proctoring] Izin kamera tidak aktif atau perangkat tidak tersedia:', camErr);
                    // Tetap jalankan proctoring untuk deteksi tab switch / blur
                    aiProctoringEngine.start();
                }
            } else {
                aiProctoringEngine.start();
            }
        }
    });
</script>
