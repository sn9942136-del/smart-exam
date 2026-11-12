<!DOCTYPE html>
<html lang="id" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="base-url" content="<?= base_url() ?>">

    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . app_name() : 'Ruang Ujian CBT | ' . app_name() ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@500;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">

    <style>
        /* Additional Layout Specific Overrides */
        .exam-topbar {
            height: 58px;
        }
        @media (max-width: 576px) {
            .exam-topbar {
                height: 52px;
            }
        }
    </style>
</head>
<body class="exam-mode d-flex flex-column min-vh-100">

    <!-- Top Status Bar -->
    <header class="exam-topbar px-3 px-lg-4 d-flex align-items-center justify-content-between">
        <!-- Brand & Exam Title -->
        <div class="d-flex align-items-center gap-2.5 overflow-hidden">
            <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 shadow-xs" style="width: 34px; height: 34px;">
                <i class="bi bi-mortarboard-fill fs-6"></i>
            </div>
            <div class="text-truncate">
                <h6 class="fw-bold mb-0 text-dark text-truncate fs-xs d-none d-md-block"><?= e($exam['title'] ?? 'Ujian CBT') ?></h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border-0 fs-3xs rounded-pill px-2 py-0.5">
                        <?= e($exam['subject_name'] ?? 'Mata Pelajaran') ?>
                    </span>
                    <span class="text-muted fs-3xs d-none d-sm-inline">
                        <i class="bi bi-person-fill me-1 text-slate-400"></i><?= e($user['name'] ?? 'Peserta') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Center: Countdown Timer -->
        <div class="d-flex align-items-center justify-content-center flex-shrink-0 px-2">
            <div id="examCountdownTimer" class="timer-badge-box" role="timer" aria-live="off" aria-label="Sisa Waktu Pengerjaan Ujian" title="Sisa Waktu Ujian">
                <i class="bi bi-stopwatch text-cyan fs-6"></i>
                <span class="fs-xs fw-bold">
                    <span id="timerHours">00</span>:<span id="timerMinutes">00</span>:<span id="timerSeconds">00</span>
                </span>
            </div>
        </div>

        <!-- Right: Auto-Save Status, Online/Offline Badge, & Fullscreen Button -->
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <!-- Auto-save Status Indicator -->
            <div id="autosaveStatus" class="autosave-badge bg-white text-secondary border d-none d-md-inline-flex" title="Status Penyimpanan Lembar Jawaban">
                <i id="autosaveIcon" class="bi bi-cloud-check text-success fs-6"></i>
                <span id="autosaveText" class="fs-3xs">Tersimpan ke Server</span>
            </div>

            <!-- Network Status Indicator (ONLINE / OFFLINE / RECONNECTING / SYNCING) -->
            <div id="networkStatusBadge" class="conn-badge conn-online" role="status" aria-live="polite" title="Status Koneksi Server CBT">
                <span class="pulse-dot bg-success" id="networkStatusDot"></span>
                <span class="fs-3xs fw-bold" id="networkStatusText">ONLINE</span>
            </div>

            <!-- Fullscreen Button -->
            <button type="button" id="btnToggleFullscreen" class="btn btn-outline-secondary btn-sm rounded-3 p-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Beralih Layar Penuh" aria-label="Beralih Layar Penuh">
                <i class="bi bi-arrows-fullscreen fs-xs"></i>
            </button>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-grow-1 p-3 p-lg-4">
        <?= $content ?>
    </main>

    <!-- Auto-Submit Processing Overlay (Modal) -->
    <div id="autoSubmitModal" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 p-4 text-center">
                <div class="mb-3">
                    <div class="spinner-border text-primary" style="width: 3.5rem; height: 3.5rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-2" id="autoSubmitTitle">Waktu Ujian Telah Habis</h5>
                <p class="text-muted fs-xs mb-0" id="autoSubmitMessage">
                    Sistem sedang memfinalisasi dan mengumpulkan seluruh lembar jawaban Anda secara otomatis ke server...
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/exam-timer.js') ?>"></script>
    <script src="<?= asset('js/exam-autosave.js') ?>"></script>
    <script src="<?= asset('js/exam-engine.js') ?>"></script>
    <script src="<?= asset('js/ai-proctoring-engine.js') ?>"></script>
</body>
</html>
