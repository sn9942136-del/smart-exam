<?php
// app/Views/guru/exams/preview.php
$examTitle = $exam['title'] ?? 'Pratinjau Ujian';
$examCode = $exam['exam_code'] ?? $exam['code'] ?? 'EXAM';
$questions = $exam['questions_with_options'] ?? $exam['questions'] ?? [];
$totalQuestions = count($questions);
$isGuru = !empty($isGuru);
$dashboardUrl = $isGuru ? base_url('guru/dashboard') : base_url('admin/dashboard');
$examsListUrl = $isGuru ? base_url('guru/exams') : base_url('admin/exams');
$examDetailUrl = $backUrl ?? ($isGuru ? base_url('guru/exams/show/' . $exam['id']) : base_url('admin/exams/show/' . $exam['id']));
?>

<div class="container-fluid py-2">
    <!-- Breadcrumb & Mode Selector Header -->
    <div class="card vui-card mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1 fs-3xs">
                            <li class="breadcrumb-item"><a href="<?= $dashboardUrl ?>" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?= $examsListUrl ?>" class="text-decoration-none">Manajemen Ujian</a></li>
                            <li class="breadcrumb-item"><a href="<?= $examDetailUrl ?>" class="text-decoration-none text-truncate" style="max-width: 200px;"><?= e($examTitle) ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Pratinjau Ujian</li>
                        </ol>
                    </nav>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="vui-page-title mb-0 fs-5"><?= e($examTitle) ?></h4>
                        <span class="badge rounded-pill bg-slate-100 text-slate-700 border border-slate-200 font-monospace fs-3xs"><?= e($examCode) ?></span>
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-3xs"><i class="bi bi-clock me-1"></i><?= $exam['duration_minutes'] ?> Menit</span>
                        <?php if (!empty($exam['is_dual_cam_required'])): ?>
                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fs-3xs"><i class="bi bi-camera-video me-1"></i>Dual Camera AI Active</span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-slate-100 text-slate-700 border border-slate-200 fs-3xs"><i class="bi bi-camera me-1"></i>Single Cam</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mode Switcher -->
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group p-1 bg-slate-100 rounded-pill border border-slate-200" role="group">
                        <button type="button" class="btn btn-sm btn-primary active rounded-pill px-3 shadow-xs" id="btnStudentView" onclick="switchView('student')">
                            <i class="bi bi-mortarboard me-1"></i> Tampilan Siswa
                        </button>
                        <button type="button" class="btn btn-sm btn-light rounded-pill px-3 text-dark" id="btnTeacherView" onclick="switchView('teacher')">
                            <i class="bi bi-key me-1"></i> Kunci & Rubrik
                        </button>
                    </div>

                    <a href="<?= $examDetailUrl ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-arrow-left"></i>
                        <span>Kembali</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($questions)): ?>
        <div class="card vui-card p-5 text-center">
            <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                <i class="bi bi-inbox fs-3"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Belum Ada Soal Terpilih</h5>
            <p class="text-muted fs-xs mb-3">Bank soal sumber belum memiliki butir soal yang dapat ditampilkan.</p>
            <div>
                <a href="<?= base_url('guru/question-banks/show/' . ($exam['question_bank_id'] ?? 0)) ?>" class="btn btn-primary rounded-pill btn-sm px-4 fw-semibold shadow-xs">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Soal ke Bank Soal
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- ========================================== -->
        <!-- 1. STUDENT VIEW SIMULATION (Default)       -->
        <!-- ========================================== -->
        <div id="studentViewContainer">
            <div class="row g-4">
                <!-- Left: Question Player -->
                <div class="col-lg-8">
                    <!-- Exam Header Bar Mock -->
                    <div class="card vui-card mb-3 bg-dark text-white p-3 border-dark">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-danger fs-3xs animate-pulse">● LIVE PROCTORING</span>
                                <span class="fs-3xs text-white-50">Webcam: <strong class="text-success">TERHUBUNG</strong> | Samping: <strong class="text-success">TERHUBUNG</strong></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 font-monospace fs-6 text-warning fw-bold">
                                <i class="bi bi-clock-history"></i>
                                <span id="timerDisplay"><?= sprintf('%02d:00:00', (int)($exam['duration_minutes'] / 60)) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Question Slides Container -->
                    <?php foreach ($questions as $idx => $q): ?>
                        <div class="question-slide card vui-card p-4 mb-4 <?= $idx === 0 ? '' : 'd-none' ?>" id="qSlide_<?= $idx ?>">
                            <div class="d-flex align-items-center justify-content-between border-bottom border-slate-100 pb-3 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill bg-primary px-3 py-1.5 fs-3xs fw-semibold">Soal No. <?= $idx + 1 ?></span>
                                    <span class="badge rounded-pill bg-slate-100 text-slate-700 border border-slate-200 fs-3xs">Bobot: <?= (float)($q['score_weight'] ?? $q['marks'] ?? $q['base_weight'] ?? 1) ?> Poin</span>
                                </div>
                                <div>
                                    <?php if (($q['question_type'] ?? '') === 'multiple_choice'): ?>
                                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fs-3xs">Pilihan Ganda</span>
                                    <?php elseif (($q['question_type'] ?? '') === 'essay'): ?>
                                        <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle fs-3xs">Esai Terbuka</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fs-3xs">Benar / Salah</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Question Text -->
                            <div class="fs-6 text-dark mb-4 lh-base">
                                <?= $q['content'] ?? $q['question_text'] ?? '' ?>
                            </div>

                            <?php 
                            $mediaImg = $q['question_image'] ?? $q['media_url'] ?? '';
                            if (!empty($mediaImg)): 
                            ?>
                                <div class="mb-4 text-center">
                                    <img src="<?= base_url($mediaImg) ?>" alt="Media Soal" class="img-fluid rounded-3 border border-slate-200 shadow-2xs" style="max-height: 280px;">
                                </div>
                            <?php endif; ?>

                            <!-- Options Form Mock -->
                            <div class="options-container mb-4">
                                <?php if (($q['question_type'] ?? '') === 'multiple_choice'): ?>
                                    <div class="d-flex flex-column gap-2.5">
                                        <?php 
                                        $options = $q['options'] ?? [];
                                        $optLetters = ['A', 'B', 'C', 'D', 'E'];
                                        foreach ($options as $optIdx => $opt): 
                                            $letter = $opt['option_key'] ?? $optLetters[$optIdx] ?? chr(65 + $optIdx);
                                        ?>
                                            <label class="p-3 rounded-3 border border-slate-200 d-flex align-items-center gap-3 cursor-pointer student-opt-label bg-white shadow-2xs transition-all">
                                                <input type="radio" name="student_ans_<?= $q['question_id'] ?? $q['id'] ?>" value="<?= $opt['id'] ?>" class="form-check-input mt-0 fs-5" onchange="markAnswered(<?= $idx ?>)">
                                                <div class="d-flex align-items-center gap-2.5 w-100">
                                                    <span class="badge rounded-pill bg-slate-100 text-slate-700 border border-slate-200 fw-bold px-2.5 py-1"><?= $letter ?></span>
                                                    <span class="text-dark fs-xs"><?= e($opt['content'] ?? $opt['option_text'] ?? '') ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (($q['question_type'] ?? '') === 'essay'): ?>
                                    <textarea class="form-control rounded-3 p-3 border-slate-200 fs-xs" rows="5" placeholder="Ketikkan jawaban esai Anda di sini..." oninput="markAnswered(<?= $idx ?>)"></textarea>
                                <?php else: ?>
                                    <div class="d-flex gap-3">
                                        <label class="p-3 rounded-3 border border-slate-200 flex-fill d-flex align-items-center gap-3 cursor-pointer bg-white shadow-2xs">
                                            <input type="radio" name="student_ans_<?= $q['question_id'] ?? $q['id'] ?>" value="1" class="form-check-input mt-0 fs-5" onchange="markAnswered(<?= $idx ?>)">
                                            <span class="fw-bold text-success fs-xs"><i class="bi bi-check-circle me-1"></i> BENAR (TRUE)</span>
                                        </label>
                                        <label class="p-3 rounded-3 border border-slate-200 flex-fill d-flex align-items-center gap-3 cursor-pointer bg-white shadow-2xs">
                                            <input type="radio" name="student_ans_<?= $q['question_id'] ?? $q['id'] ?>" value="0" class="form-check-input mt-0 fs-5" onchange="markAnswered(<?= $idx ?>)">
                                            <span class="fw-bold text-danger fs-xs"><i class="bi bi-x-circle me-1"></i> SALAH (FALSE)</span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Navigation Controls -->
                            <div class="d-flex align-items-center justify-content-between pt-3 border-top border-slate-100">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5" <?= $idx === 0 ? 'disabled' : '' ?> onclick="goToQuestion(<?= $idx - 1 ?>)">
                                    <i class="bi bi-chevron-left me-1"></i> Sebelumnya
                                </button>
                                
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3.5" onclick="toggleRagu(<?= $idx ?>)">
                                    <i class="bi bi-flag me-1"></i> Ragu-Ragu
                                </button>

                                <?php if ($idx === $totalQuestions - 1): ?>
                                    <button type="button" class="btn btn-sm btn-success rounded-pill px-4 fw-semibold shadow-xs" onclick="finishMock()">
                                        <i class="bi bi-check2-circle me-1"></i> Selesai Ujian
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs" onclick="goToQuestion(<?= $idx + 1 ?>)">
                                        Berikutnya <i class="bi bi-chevron-right ms-1"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Right: Navigator 1..N & Proctoring Monitor Preview -->
                <div class="col-lg-4">
                    <!-- Question Grid Navigator -->
                    <div class="card vui-card p-4 mb-4 sticky-top" style="top: 20px; z-index: 10;">
                        <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                            <span class="vui-icon-wrap" style="width: 28px; height: 28px; font-size: 0.875rem;"><i class="bi bi-grid-3x3-gap"></i></span>
                            <span>Nomor Soal</span>
                        </h6>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary nav-q-btn rounded-3 fw-semibold <?= $i === 0 ? 'btn-primary text-white' : '' ?>" id="navBtn_<?= $i ?>" style="width: 40px; height: 40px;" onclick="goToQuestion(<?= $i ?>)">
                                    <?= $i + 1 ?>
                                </button>
                            <?php endfor; ?>
                        </div>

                        <div class="d-flex flex-column gap-2 fs-3xs text-muted border-top border-slate-100 pt-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-success p-1" style="width: 10px; height: 10px;"></span> Sudah Dijawab
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-warning p-1" style="width: 10px; height: 10px;"></span> Ragu-Ragu
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-slate-200 p-1" style="width: 10px; height: 10px;"></span> Belum Dijawab
                            </div>
                        </div>

                        <!-- Mini Camera Frame Preview -->
                        <div class="mt-4 p-3 bg-dark rounded-3 text-center text-white">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <small class="text-danger fw-bold fs-3xs">● AI DUAL CAMERA</small>
                                <span class="badge rounded-pill bg-success fs-3xs">LIVE</span>
                            </div>
                            <div class="rounded-2 p-4 text-white-50 border border-secondary mb-2" style="aspect-ratio: 16/9; background-color: #0b1120; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                <i class="bi bi-person-bounding-box fs-1 text-primary"></i>
                                <small class="mt-2 text-white-50 fs-3xs">Face Tracking Active (Preview Mode)</small>
                            </div>
                            <small class="text-white-50 fs-3xs">Webcam Depan 1080p | HP Samping Terkalibrasi</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2. TEACHER VIEW (Answer Key & Explanations)-->
        <!-- ========================================== -->
        <div id="teacherViewContainer" class="d-none">
            <div class="card vui-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-slate-100 pb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1 fs-6 d-flex align-items-center gap-2">
                            <span class="vui-icon-wrap" style="width: 28px; height: 28px; font-size: 0.875rem;"><i class="bi bi-journal-check"></i></span>
                            <span>Kunci Jawaban, Pembahasan, & Bobot Butir Soal</span>
                        </h5>
                        <p class="text-muted fs-3xs mb-0">Tampilan lengkap seluruh <?= $totalQuestions ?> butir soal dengan kunci jawaban resmi dan catatan rubrik.</p>
                    </div>
                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 fw-semibold fs-3xs">
                        Total Bobot: <?= array_sum(array_map(fn($q) => (float)($q['score_weight'] ?? $q['marks'] ?? $q['base_weight'] ?? 1), $questions)) ?> Poin
                    </span>
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php foreach ($questions as $tIdx => $tq): ?>
                        <div class="card border border-slate-200 rounded-3 p-3 bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-primary rounded-pill px-2.5 py-1 fw-bold fs-3xs">No. <?= $tIdx + 1 ?> (<?= (float)($tq['score_weight'] ?? $tq['marks'] ?? $tq['base_weight'] ?? 1) ?> Poin)</span>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill font-monospace fs-3xs"><?= e($tq['question_type'] ?? '') ?></span>
                            </div>

                            <div class="fw-medium text-dark mb-3 fs-xs">
                                <?= $tq['content'] ?? $tq['question_text'] ?? '' ?>
                            </div>

                            <?php 
                            $tMedia = $tq['question_image'] ?? $tq['media_url'] ?? '';
                            if (!empty($tMedia)): 
                            ?>
                                <div class="mb-3">
                                    <img src="<?= base_url($tMedia) ?>" class="img-fluid rounded border border-slate-200" style="max-height: 180px;">
                                </div>
                            <?php endif; ?>

                            <!-- Options & Correct Key -->
                            <?php if (!empty($tq['options'])): ?>
                                <div class="d-flex flex-column gap-2 mb-3">
                                    <?php 
                                    $optLetters = ['A', 'B', 'C', 'D', 'E'];
                                    foreach ($tq['options'] as $oIdx => $opt): 
                                        $isCorrect = (bool)($opt['is_correct'] ?? false);
                                        $letter = $opt['option_key'] ?? $optLetters[$oIdx] ?? chr(65 + $oIdx);
                                    ?>
                                        <div class="p-2.5 rounded-2 border d-flex align-items-center justify-content-between <?= $isCorrect ? 'bg-success-subtle border-success text-success fw-bold' : 'bg-white border-slate-200' ?>">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge rounded-pill <?= $isCorrect ? 'bg-success text-white' : 'bg-light text-muted border border-slate-200' ?>"><?= $letter ?></span>
                                                <span class="fs-xs"><?= e($opt['content'] ?? $opt['option_text'] ?? '') ?></span>
                                            </div>
                                            <?php if ($isCorrect): ?>
                                                <span class="badge bg-success rounded-pill px-2.5 py-1 fs-3xs"><i class="bi bi-check-lg me-1"></i> KUNCI JAWABAN</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($tq['explanation'])): ?>
                                <div class="p-3 bg-info-subtle border border-info-subtle rounded-2">
                                    <small class="fw-bold text-info-emphasis d-block mb-1 fs-3xs"><i class="bi bi-lightbulb me-1"></i>Pembahasan / Rubrik Penilaian:</small>
                                    <div class="text-dark fs-xs"><?= nl2br(e($tq['explanation'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.transition-all { transition: all 0.2s ease-in-out; }
.student-opt-label:hover {
    border-color: #3b82f6 !important;
    background-color: #f8fafc !important;
}
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .4; }
}
</style>

<script>
let currentQuestionIndex = 0;
const totalQuestions = <?= $totalQuestions ?>;
const answeredStatus = {};
const raguStatus = {};

function switchView(view) {
    const studentCont = document.getElementById('studentViewContainer');
    const teacherCont = document.getElementById('teacherViewContainer');
    const btnStudent = document.getElementById('btnStudentView');
    const btnTeacher = document.getElementById('btnTeacherView');

    if (view === 'student') {
        studentCont?.classList.remove('d-none');
        teacherCont?.classList.add('d-none');
        btnStudent.className = 'btn btn-sm btn-primary active rounded-pill px-3 shadow-xs';
        btnTeacher.className = 'btn btn-sm btn-light rounded-pill px-3 text-dark';
    } else {
        studentCont?.classList.add('d-none');
        teacherCont?.classList.remove('d-none');
        btnTeacher.className = 'btn btn-sm btn-primary active rounded-pill px-3 shadow-xs';
        btnStudent.className = 'btn btn-sm btn-light rounded-pill px-3 text-dark';
    }
}

function goToQuestion(index) {
    if (index < 0 || index >= totalQuestions) return;
    
    // Hide old slide
    const oldSlide = document.getElementById('qSlide_' + currentQuestionIndex);
    if (oldSlide) oldSlide.classList.add('d-none');

    // Update old nav button style
    updateNavBtnStyle(currentQuestionIndex);

    // Show new slide
    currentQuestionIndex = index;
    const newSlide = document.getElementById('qSlide_' + currentQuestionIndex);
    if (newSlide) newSlide.classList.remove('d-none');

    // Highlight current nav button
    const currentBtn = document.getElementById('navBtn_' + currentQuestionIndex);
    if (currentBtn) {
        currentBtn.classList.remove('btn-outline-secondary', 'btn-success', 'btn-warning');
        currentBtn.classList.add('btn-primary', 'text-white');
    }
}

function markAnswered(index) {
    answeredStatus[index] = true;
    updateNavBtnStyle(index);
}

function toggleRagu(index) {
    raguStatus[index] = !raguStatus[index];
    updateNavBtnStyle(index);
    if (raguStatus[index]) {
        if (typeof App !== 'undefined' && App.toast) {
            App.toast('Soal no. ' + (index + 1) + ' ditandai ragu-ragu', 'warning');
        }
    }
}

function updateNavBtnStyle(index) {
    const btn = document.getElementById('navBtn_' + index);
    if (!btn) return;
    
    if (index === currentQuestionIndex) {
        btn.className = 'btn btn-sm btn-primary text-white nav-q-btn rounded-3 fw-semibold';
        return;
    }

    if (raguStatus[index]) {
        btn.className = 'btn btn-sm btn-warning text-dark nav-q-btn rounded-3 fw-semibold';
    } else if (answeredStatus[index]) {
        btn.className = 'btn btn-sm btn-success text-white nav-q-btn rounded-3 fw-semibold';
    } else {
        btn.className = 'btn btn-sm btn-outline-secondary nav-q-btn rounded-3 fw-semibold';
    }
}

function finishMock() {
    if (typeof App !== 'undefined' && App.toast) {
        App.toast('Ini adalah mode pratinjau (Preview). Tidak menyimpan jawaban.', 'info');
    } else {
        alert('Ini adalah mode pratinjau (Preview). Tidak menyimpan jawaban.');
    }
}
</script>
