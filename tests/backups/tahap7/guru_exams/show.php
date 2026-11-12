<?php
// app/Views/guru/exams/show.php
$examCode = $exam['exam_code'] ?? $exam['code'] ?? 'EXAM';
$examTitle = $exam['title'] ?? 'Ujian';
$activeToken = $exam['active_token']['token'] ?? $exam['active_token']['token_code'] ?? null;
?>

<div class="container-fluid py-2">
    <!-- Breadcrumb & Top Bar -->
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/exams') ?>" class="text-decoration-none">Manajemen Ujian</a></li>
                    <li class="breadcrumb-item active text-truncate" style="max-width: 280px;" aria-current="page"><?= e($examTitle) ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="fw-bold text-slate-900 mb-0 fs-5"><?= e($examTitle) ?></h4>
                <span class="badge bg-slate-100 text-slate-700 font-monospace rounded-pill px-2.5 py-0.5 fs-xs"><?= e($examCode) ?></span>
                <?php
                $statusColors = [
                    'draft' => 'bg-secondary-subtle text-secondary',
                    'published' => 'bg-info-subtle text-info',
                    'scheduled' => 'bg-primary-subtle text-primary',
                    'ongoing' => 'bg-warning-subtle text-warning-emphasis',
                    'in_progress' => 'bg-warning-subtle text-warning-emphasis',
                    'completed' => 'bg-success-subtle text-success',
                    'archived' => 'bg-secondary-subtle text-secondary'
                ];
                $stColor = $statusColors[$exam['status']] ?? 'bg-secondary-subtle text-secondary';
                ?>
                <span class="badge <?= $stColor ?> px-2.5 py-1 text-uppercase fs-xs fw-medium rounded-pill">
                    <?= e($exam['status']) ?>
                </span>
            </div>
            <p class="text-muted fs-xs mb-0 mt-1">
                <i class="bi bi-book me-1"></i><?= e($exam['subject_name'] ?? 'Mata Pelajaran') ?> 
                <span class="mx-2">•</span>
                <i class="bi bi-archive me-1"></i>Bank Soal: <strong><?= e($exam['bank_title'] ?? '-') ?></strong>
                <span class="mx-2">•</span>
                <i class="bi bi-person me-1"></i>Pembuat: <?= e($exam['created_by_name'] ?? '-') ?>
            </p>
        </div>

        <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
            <!-- Live Monitoring Dashboard Button -->
            <a href="<?= base_url('guru/exams/monitoring/' . $exam['id']) ?>" class="btn btn-sm btn-danger rounded-pill d-flex align-items-center gap-1.5 px-3.5 shadow-xs">
                <span class="spinner-grow spinner-grow-sm text-white" style="width: 0.5rem; height: 0.5rem;" role="status"></span>
                <i class="bi bi-display"></i>
                <span class="fw-semibold">Live Monitoring</span>
            </a>

            <!-- Preview Button -->
            <a href="<?= base_url('guru/exams/preview/' . $exam['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill d-flex align-items-center gap-1.5 px-3.5">
                <i class="bi bi-eye"></i>
                <span>Preview</span>
            </a>

            <!-- Edit Button -->
            <a href="<?= base_url('guru/exams/edit/' . $exam['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill d-flex align-items-center gap-1.5 px-3.5">
                <i class="bi bi-pencil-square"></i>
                <span>Edit</span>
            </a>

            <!-- Status Dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3.5" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-flag me-1"></i> Status
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-xs border-slate-100 rounded-3 fs-xs">
                    <li><h6 class="dropdown-header">Ubah Status Ujian</h6></li>
                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateStatus('draft')"><i class="bi bi-pencil text-secondary me-2"></i>Draft</a></li>
                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateStatus('scheduled')"><i class="bi bi-calendar-check text-primary me-2"></i>Scheduled</a></li>
                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateStatus('published')"><i class="bi bi-send-check text-info me-2"></i>Published</a></li>
                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateStatus('ongoing')"><i class="bi bi-play-circle-fill text-warning me-2"></i>Ongoing (Aktif)</a></li>
                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateStatus('completed')"><i class="bi bi-check-circle-fill text-success me-2"></i>Completed</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-1.5 text-danger" href="javascript:void(0)" onclick="updateStatus('archived')"><i class="bi bi-archive me-2"></i>Archived</a></li>
                </ul>
            </div>
            
            <form id="statusForm" action="<?= base_url('guru/exams/status/' . $exam['id']) ?>" method="POST" class="d-none">
                <?= csrf_field() ?>
                <input type="hidden" name="status" id="targetStatus" value="">
            </form>

            <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Kembali">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 p-3.5">
                    <div class="rounded-3 bg-primary-subtle text-primary p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-patch-question fs-5"></i>
                    </div>
                    <div>
                        <span class="text-muted fw-medium fs-xs d-block">Jumlah Soal</span>
                        <h4 class="fw-bold mb-0 text-dark fs-5"><?= count($exam['questions'] ?? []) ?> <span class="fs-xs fw-normal text-muted">/ <?= $exam['total_questions'] ?> Target</span></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 p-3.5">
                    <div class="rounded-3 bg-warning-subtle text-warning p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-stopwatch fs-5"></i>
                    </div>
                    <div>
                        <span class="text-muted fw-medium fs-xs d-block">Durasi & KKM</span>
                        <h4 class="fw-bold mb-0 text-dark fs-5"><?= $exam['duration_minutes'] ?>' <span class="fs-xs fw-normal text-muted">| KKM <?= (int)($exam['passing_grade'] ?? 75) ?></span></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 p-3.5">
                    <div class="rounded-3 bg-success-subtle text-success p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-people fs-5"></i>
                    </div>
                    <div>
                        <span class="text-muted fw-medium fs-xs d-block">Peserta Terdaftar</span>
                        <h4 class="fw-bold mb-0 text-dark fs-5"><?= count($exam['participants'] ?? []) ?> <span class="fs-xs fw-normal text-muted">Siswa</span></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-primary-subtle bg-opacity-25 border border-primary-subtle">
                <div class="card-body d-flex align-items-center justify-content-between p-3.5">
                    <div>
                        <span class="text-primary fw-semibold fs-xs d-block">Token Ujian Terpusat</span>
                        <?php if ($activeToken): ?>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="font-monospace fw-bold fs-5 text-primary tracking-wider" id="examTokenCode"><?= e($activeToken) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-primary p-1 rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="Salin Token" onclick="copyToken('<?= e($activeToken) ?>')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary mt-1 rounded-pill">Nonaktif</span>
                        <?php endif; ?>
                    </div>
                    <form action="<?= base_url('guru/exams/token/refresh/' . $exam['id']) ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary rounded-circle p-2 shadow-xs" title="Rilis/Perbarui Token Baru" onclick="return confirm('Buat token ujian 6-digit baru?')">
                            <i class="bi bi-arrow-clockwise fs-6"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="card border-0 shadow-xs rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-header bg-white border-bottom border-slate-100 p-0">
            <ul class="nav nav-tabs nav-justified border-bottom-0" id="examDetailTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-3 fw-semibold fs-xs d-flex align-items-center justify-content-center gap-2" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                        <i class="bi bi-shield-check text-primary"></i> Ringkasan & AI Rules
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-semibold fs-xs d-flex align-items-center justify-content-center gap-2" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button" role="tab">
                        <i class="bi bi-list-check text-success"></i> Butir Soal Terpilih (<?= count($exam['questions'] ?? []) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-semibold fs-xs d-flex align-items-center justify-content-center gap-2" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#schedules" type="button" role="tab">
                        <i class="bi bi-calendar3 text-warning"></i> Jadwal & Kelas (<?= count($exam['schedules'] ?? []) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-semibold fs-xs d-flex align-items-center justify-content-center gap-2" id="tokens-tab" data-bs-toggle="tab" data-bs-target="#tokens" type="button" role="tab">
                        <i class="bi bi-key-fill text-info"></i> Token Ujian (<?= count($exam['tokens'] ?? $exam['all_tokens'] ?? []) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 fw-semibold fs-xs d-flex align-items-center justify-content-center gap-2" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button" role="tab">
                        <i class="bi bi-people text-secondary"></i> Peserta Terdaftar (<?= count($exam['participants'] ?? []) ?>)
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="examDetailTabContent">
                
                <!-- TAB 1: RINGKASAN & AI RULES -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h5 class="fw-bold text-dark mb-3 fs-6"><i class="bi bi-info-circle text-primary me-2"></i>Informasi & Parameter Utama</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0 fs-xs border-slate-200">
                                    <tbody>
                                        <tr>
                                            <th class="bg-light w-40 text-muted">Judul Ujian</th>
                                            <td class="fw-semibold text-dark"><?= e($exam['title']) ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Deskripsi / Petunjuk</th>
                                            <td><?= nl2br(e($exam['description'] ?? 'Tidak ada petunjuk khusus.')) ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Mata Pelajaran</th>
                                            <td><?= e($exam['subject_name'] ?? '-') ?> (<?= e($exam['subject_code'] ?? '-') ?>)</td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Tahun Ajaran / Semester</th>
                                            <td><?= e($exam['academic_year_name'] ?? '-') ?> - <?= e($exam['semester_name'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Bank Soal Sumber</th>
                                            <td><a href="<?= base_url('guru/question-banks/show/' . ($exam['question_bank_id'] ?? 0)) ?>" class="fw-semibold text-decoration-none"><?= e($exam['bank_title'] ?? '-') ?></a></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Durasi Pengerjaan</th>
                                            <td><span class="badge bg-warning-subtle text-warning-emphasis fw-medium fs-xs px-2.5 py-1 rounded-pill"><?= $exam['duration_minutes'] ?> Menit</span></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Nilai Kelulusan (KKM)</th>
                                            <td><span class="badge bg-success-subtle text-success fw-medium fs-xs px-2.5 py-1 rounded-pill"><?= (int)($exam['passing_grade'] ?? 75) ?> Poin</span></td>
                                        </tr>
                                        <tr>
                                            <th class="bg-light text-muted">Acak Soal / Acak Opsi</th>
                                            <td>
                                                <span class="badge <?= ($exam['randomize_questions'] ?? 1) ? 'bg-primary-subtle text-primary' : 'bg-slate-100 text-slate-700' ?> rounded-pill px-2.5 py-1 fw-medium me-1">
                                                    <?= ($exam['randomize_questions'] ?? 1) ? 'Acak Soal: Aktif' : 'Acak Soal: Tidak' ?>
                                                </span>
                                                <span class="badge <?= ($exam['randomize_options'] ?? 1) ? 'bg-primary-subtle text-primary' : 'bg-slate-100 text-slate-700' ?> rounded-pill px-2.5 py-1 fw-medium">
                                                    <?= ($exam['randomize_options'] ?? 1) ? 'Acak Opsi: Aktif' : 'Acak Opsi: Tidak' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Security & AI Proctoring Rules -->
                        <div class="col-lg-6">
                            <h5 class="fw-bold text-dark mb-3 fs-6"><i class="bi bi-robot text-danger me-2"></i>Konfigurasi Keamanan & AI Proctoring</h5>
                            
                            <div class="d-flex flex-column gap-3">
                                <!-- Dual Camera Box -->
                                <div class="p-3 rounded-3 border d-flex align-items-center justify-content-between <?= ($exam['require_dual_camera'] ?? 1) ? 'bg-danger-subtle bg-opacity-25 border-danger-subtle' : 'bg-light border-slate-200' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 <?= ($exam['require_dual_camera'] ?? 1) ? 'bg-danger text-white' : 'bg-secondary text-white' ?> p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-camera-video fs-5"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block fs-xs">Dual Camera Proctoring (Webcam & HP)</span>
                                             <small class="text-muted fs-xs">Kamera depan pengawas wajah & kamera samping ruang ujian.</small>
                                        </div>
                                    </div>
                                    <span class="badge <?= ($exam['require_dual_camera'] ?? 1) ? 'bg-danger text-white' : 'bg-secondary' ?> rounded-pill px-2.5 py-1">
                                        <?= ($exam['require_dual_camera'] ?? 1) ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                </div>

                                <!-- AI Violation Detection Box -->
                                <div class="p-3 rounded-3 border d-flex align-items-center justify-content-between <?= ($exam['enable_ai_proctoring'] ?? 1) ? 'bg-primary-subtle bg-opacity-25 border-primary-subtle' : 'bg-light border-slate-200' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 <?= ($exam['enable_ai_proctoring'] ?? 1) ? 'bg-primary text-white' : 'bg-secondary text-white' ?> p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-shield-lock fs-5"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block fs-xs">AI Proctoring Real-time Detection</span>
                                            <small class="text-muted fs-xs">Deteksi multi-wajah, toleh arah, audio bising, & perangkat sekunder.</small>
                                        </div>
                                    </div>
                                    <span class="badge <?= ($exam['enable_ai_proctoring'] ?? 1) ? 'bg-primary text-white' : 'bg-secondary' ?> rounded-pill px-2.5 py-1">
                                        <?= ($exam['enable_ai_proctoring'] ?? 1) ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                </div>

                                <!-- Detail Aturan AI Rules -->
                                <?php $rules = $exam['rules'] ?? []; ?>
                                <div class="card border border-slate-200 rounded-3 p-3 bg-light">
                                    <span class="fw-semibold text-dark fs-xs mb-2 d-block text-uppercase">Parameter Deteksi Detail:</span>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= ($rules['detect_multiple_faces'] ?? 1) ? 'check-circle-fill text-success' : 'x-circle-fill text-muted' ?>"></i>
                                                <span class="fs-xs">Deteksi Banyak Orang</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= ($rules['detect_look_away'] ?? 1) ? 'check-circle-fill text-success' : 'x-circle-fill text-muted' ?>"></i>
                                                <span class="fs-xs">Deteksi Menoleh / Lari Fokus</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= ($rules['detect_audio_noise'] ?? 1) ? 'check-circle-fill text-success' : 'x-circle-fill text-muted' ?>"></i>
                                                <span class="fs-xs">Deteksi Suara Mencurigakan</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= ($rules['detect_secondary_device'] ?? 1) ? 'check-circle-fill text-success' : 'x-circle-fill text-muted' ?>"></i>
                                                <span class="fs-xs">Deteksi Gawai Tambahan</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= !($rules['allow_tab_switch'] ?? 0) ? 'lock-fill text-danger' : 'unlock text-warning' ?>"></i>
                                                <span class="fs-xs">Pindah Tab: <?= ($rules['allow_tab_switch'] ?? 0) ? 'Maks ' . ($rules['max_tab_switches'] ?? 3) . 'x' : 'Dilarang' ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= ($rules['lock_browser'] ?? 1) ? 'fullscreen text-primary' : 'fullscreen-exit text-muted' ?>"></i>
                                                <span class="fs-xs">Kunci Layar Penuh</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: BUTIR SOAL TERPILIH -->
                <div class="tab-pane fade" id="questions" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Daftar Butir Soal Terkait</h5>
                        <a href="<?= base_url('guru/exams/preview/' . $exam['id']) ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">
                            <i class="bi bi-eye me-1.5"></i> Pratinjau Kunci & Tampilan Siswa
                        </a>
                    </div>

                    <?php if (empty($exam['questions'])): ?>
                        <div class="alert alert-warning d-flex align-items-center gap-2 rounded-3 fs-xs">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div>Belum ada butir soal yang tertaut pada ujian ini. Silakan periksa bank soal sumber.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-xs">
                                <thead class="table-light border-bottom border-slate-100">
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th style="width: 130px;">Tipe Soal</th>
                                        <th>Pertanyaan</th>
                                        <th style="width: 100px;">Poin</th>
                                        <th style="width: 80px;" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam['questions'] as $index => $q): ?>
                                        <tr>
                                            <td class="fw-bold text-muted"><?= $index + 1 ?></td>
                                            <td>
                                                <?php if (($q['question_type'] ?? '') === 'multiple_choice'): ?>
                                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 fw-medium">Pilihan Ganda</span>
                                                <?php elseif (($q['question_type'] ?? '') === 'essay'): ?>
                                                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 fw-medium">Esai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5 fw-medium">Benar/Salah</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark text-truncate" style="max-width: 500px;">
                                                    <?= e(strip_tags($q['content'] ?? $q['question_text'] ?? '')) ?>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-slate-100 text-slate-700 rounded-pill px-2 py-0.5"><?= (float)($q['score_weight'] ?? $q['marks'] ?? $q['base_weight'] ?? 1) ?> Poin</span></td>
                                            <td class="text-end">
                                                <a href="<?= base_url('guru/questions/preview/' . ($q['question_id'] ?? $q['id'] ?? 0)) ?>" target="_blank" class="btn btn-xs btn-outline-secondary rounded-pill" title="Preview Butir Soal">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 3: JADWAL & KELAS PESERTA -->
                <div class="tab-pane fade" id="schedules" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Jadwal Pelaksanaan Per Kelas</h5>
                    </div>

                    <?php if (empty($exam['schedules'])): ?>
                        <div class="alert alert-warning rounded-3 fs-xs">Belum ada jadwal kelas yang dikonfigurasi.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-xs">
                                <thead class="table-light border-bottom border-slate-100">
                                    <tr>
                                        <th>Kelas / Rombel</th>
                                        <th>Waktu Mulai</th>
                                        <th>Waktu Selesai</th>
                                        <th>Toleransi Keterlambatan</th>
                                        <th>Status Jadwal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam['schedules'] as $sch): ?>
                                        <tr>
                                            <td class="fw-bold text-primary">
                                                <i class="bi bi-mortarboard me-1.5"></i><?= e($sch['class_name'] ?? 'Semua Kelas') ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar-event me-1 text-muted"></i>
                                                <?= date('d M Y, H:i', strtotime($sch['start_time'])) ?> WIB
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar-x me-1 text-muted"></i>
                                                <?= date('d M Y, H:i', strtotime($sch['end_time'])) ?> WIB
                                            </td>
                                            <td><?= $sch['grace_period_minutes'] ?> Menit</td>
                                            <td>
                                                <?php
                                                $schStat = $sch['status'] ?? 'scheduled';
                                                $schBadge = [
                                                    'active' => 'bg-success-subtle text-success',
                                                    'scheduled' => 'bg-primary-subtle text-primary',
                                                    'expired' => 'bg-secondary-subtle text-secondary'
                                                ];
                                                ?>
                                                <span class="badge <?= $schBadge[$schStat] ?? 'bg-secondary-subtle text-secondary' ?> text-uppercase rounded-pill px-2.5 py-1 fw-medium">
                                                    <?= e($schStat) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 4: TOKEN UJIAN TERPUSAT -->
                <div class="tab-pane fade" id="tokens" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 fs-6">Riwayat & Manajemen Token Ujian Terpusat</h5>
                            <p class="text-muted fs-xs mb-0">Token 6 digit unik alphanumeric untuk otorisasi masuk peserta didik ke ruang ujian.</p>
                        </div>
                        <form action="<?= base_url('guru/exams/token/refresh/' . $exam['id']) ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs d-flex align-items-center gap-1.5">
                                <i class="bi bi-key-fill"></i>
                                <span>Generate Token Baru</span>
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-xs">
                            <thead class="table-light border-bottom border-slate-100">
                                <tr>
                                    <th>Kode Token</th>
                                    <th>Valid Dari</th>
                                    <th>Valid Sampai</th>
                                    <th>Total Digunakan</th>
                                    <th>Status Token</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $tokensList = $exam['tokens'] ?? $exam['all_tokens'] ?? [];
                                if (empty($tokensList)): 
                                ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada token yang dirilis.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tokensList as $tok): 
                                        $tCode = $tok['token'] ?? $tok['token_code'] ?? '';
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="font-monospace fw-bold fs-6 px-3 py-1 bg-slate-100 rounded-pill border text-primary">
                                                    <?= e($tCode) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d M Y, H:i', strtotime($tok['valid_from'])) ?> WIB</td>
                                            <td><?= date('d M Y, H:i', strtotime($tok['valid_until'])) ?> WIB</td>
                                            <td><span class="badge bg-slate-100 text-slate-700 rounded-pill px-2 py-0.5"><?= (int)($tok['used_count'] ?? 0) ?> Siswa</span></td>
                                            <td>
                                                <span class="badge <?= !empty($tok['is_active']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2.5 py-1 fw-medium">
                                                    <?= !empty($tok['is_active']) ? 'AKTIF' : 'NONAKTIF' ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form action="<?= base_url('guru/exams/token/toggle/' . $tok['id']) ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill" title="Toggle Status Aktif">
                                                        <i class="bi bi-power"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill" onclick="copyToken('<?= e($tCode) ?>')" title="Salin">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 5: PESERTA TERDAFTAR -->
                <div class="tab-pane fade" id="participants" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 fs-6">Daftar Peserta Didik Terdaftar</h5>
                            <p class="text-muted fs-xs mb-0">Total <?= count($exam['participants'] ?? []) ?> siswa dari rombel terpilih siap mengikuti ujian.</p>
                        </div>
                    </div>

                    <?php if (empty($exam['participants'])): ?>
                        <div class="alert alert-info rounded-3 fs-xs">Belum ada siswa terdaftar pada kelas terpilih.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-xs">
                                <thead class="table-light border-bottom border-slate-100">
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>Nama Siswa</th>
                                        <th>NIS / Username</th>
                                        <th>Kelas</th>
                                        <th>Status Pengerjaan</th>
                                        <th>Nilai / Skor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam['participants'] as $pIdx => $part): ?>
                                        <tr>
                                            <td class="text-muted fw-bold"><?= $pIdx + 1 ?></td>
                                            <td class="fw-semibold text-dark"><?= e($part['student_name'] ?? 'Siswa') ?></td>
                                            <td><span class="font-monospace text-muted"><?= e($part['student_nis'] ?? '-') ?></span></td>
                                            <td><span class="badge bg-slate-100 text-slate-700 rounded-pill px-2 py-0.5"><?= e($part['class_name'] ?? '-') ?></span></td>
                                            <td>
                                                <?php
                                                $pStat = $part['status'] ?? 'registered';
                                                $badgeMap = [
                                                    'registered' => 'bg-secondary-subtle text-secondary',
                                                    'in_progress' => 'bg-warning-subtle text-warning-emphasis',
                                                    'submitted' => 'bg-success-subtle text-success',
                                                    'graded' => 'bg-primary-subtle text-primary',
                                                    'disqualified' => 'bg-danger-subtle text-danger'
                                                ];
                                                $bColor = $badgeMap[$pStat] ?? 'bg-secondary-subtle text-secondary';
                                                ?>
                                                <span class="badge <?= $bColor ?> px-2.5 py-1 text-uppercase rounded-pill fw-medium"><?= e($pStat) ?></span>
                                            </td>
                                            <td class="fw-bold"><?= isset($part['score']) ? number_format((float)$part['score'], 1) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>
</div>

<script>
function copyToken(code) {
    navigator.clipboard.writeText(code).then(() => {
        App.toast('Token ' + code + ' berhasil disalin!', 'success');
    }).catch(err => {
        App.toast('Gagal menyalin token', 'danger');
    });
}

function updateStatus(status) {
    if (confirm('Ubah status ujian menjadi: ' + status.toUpperCase() + '?')) {
        document.getElementById('targetStatus').value = status;
        document.getElementById('statusForm').submit();
    }
}
</script>
