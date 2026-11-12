<!-- Breadcrumb Navigation -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb fs-xs mb-0">
        <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item text-slate-400">Sistem & AI</li>
        <li class="breadcrumb-item active text-slate-600" aria-current="page">Laporan Chatbot AI</li>
    </ol>
</nav>

<!-- Page Header with Ambient Accent & Quick Actions -->
<div class="card border-0 shadow-sm rounded-4 text-white p-3.5 p-lg-4 mb-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
    <div class="position-absolute end-0 top-0 bottom-0 w-50 pointer-events-none opacity-10" style="background: radial-gradient(circle at 80% 20%, rgba(37, 99, 235, 0.45) 0%, transparent 65%);"></div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative z-1">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 bg-white text-primary p-2.5 d-flex align-items-center justify-content-center shadow-xs flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="bi bi-chat-square-text-fill fs-4 text-primary"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <h4 class="fw-bold mb-0 text-white tracking-tight">Laporan & Analitik Chatbot AI</h4>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-3xs px-2.5 py-0.5 fw-semibold">Audit Terpadu</span>
                </div>
                <p class="text-white-75 fs-xs mb-0">
                    Audit aktivitas real-time asisten AI, evaluasi pertanyaan pengguna, deteksi kendala, dan ekspor riwayat percakapan.
                </p>
            </div>
        </div>

        <!-- Export Buttons Group -->
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('admin/ai-chat-reports/export-excel?' . http_build_query($filters)) ?>" class="btn btn-sm btn-success rounded-pill px-3.5 py-2 fw-semibold shadow-xs d-inline-flex align-items-center gap-2" id="btnExportExcel" title="Unduh data dalam format Microsoft Excel">
                <i class="bi bi-file-earmark-excel-fill"></i>
                <span>Ekspor Excel</span>
            </a>
            <a href="<?= base_url('admin/ai-chat-reports/export-pdf?' . http_build_query($filters)) ?>" class="btn btn-sm btn-danger rounded-pill px-3.5 py-2 fw-semibold shadow-xs d-inline-flex align-items-center gap-2" id="btnExportPdf" target="_blank" title="Unduh atau cetak laporan dalam format PDF">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                <span>Ekspor PDF</span>
            </a>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. DASHBOARD RINGKASAN STATISTIK (8 METRIK AKTUAL DARI DATABASE)           -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- Stat 1: Total Percakapan -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Total Percakapan</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-primary-subtle text-primary" style="width: 32px; height: 32px;">
                    <i class="bi bi-chat-dots-fill fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight" style="color: var(--vui-text-main, #0f172a);"><?= number_format($statistics['total_sessions']) ?></h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-collection text-primary"></i> Sesi terdaftar di sistem
            </div>
        </div>
    </div>

    <!-- Stat 2: Total Pesan Pengguna -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Pesan Pengguna</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-info-subtle text-info" style="width: 32px; height: 32px;">
                    <i class="bi bi-person-fill fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight" style="color: var(--vui-text-main, #0f172a);"><?= number_format($statistics['total_user_messages']) ?></h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-arrow-up-right text-info"></i> Pertanyaan masuk
            </div>
        </div>
    </div>

    <!-- Stat 3: Total Respons Chatbot -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Respons Chatbot</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-success-subtle text-success" style="width: 32px; height: 32px;">
                    <i class="bi bi-robot fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight" style="color: var(--vui-text-main, #0f172a);"><?= number_format($statistics['total_bot_messages']) ?></h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-check-all text-success"></i> Jawaban AI terkirim
            </div>
        </div>
    </div>

    <!-- Stat 4: Total Pengguna Chatbot -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Pengguna Unik</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-purple-subtle text-purple" style="width: 32px; height: 32px; background-color: rgba(147, 51, 234, 0.1); color: #9333ea;">
                    <i class="bi bi-people-fill fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight" style="color: var(--vui-text-main, #0f172a);"><?= number_format($statistics['total_users']) ?></h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-person-check text-purple"></i> Guru, siswa, & admin
            </div>
        </div>
    </div>

    <!-- Stat 5: Aktivitas Hari Ini -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Aktivitas Hari Ini</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-warning-subtle text-warning-emphasis" style="width: 32px; height: 32px;">
                    <i class="bi bi-clock-history fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight" style="color: var(--vui-text-main, #0f172a);"><?= number_format($statistics['today_messages']) ?> <span class="fs-xs fw-normal text-muted">pesan</span></h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-chat-left-text text-warning"></i> <?= number_format($statistics['today_sessions']) ?> sesi dibuka hari ini
            </div>
        </div>
    </div>

    <!-- Stat 6: Aktivitas Bulan Ini -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Aktivitas Bulan Ini</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-indigo-subtle text-indigo" style="width: 32px; height: 32px; background-color: rgba(99, 102, 241, 0.1); color: #6366f1;">
                    <i class="bi bi-calendar-month-fill fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight" style="color: var(--vui-text-main, #0f172a);"><?= number_format($statistics['month_messages']) ?> <span class="fs-xs fw-normal text-muted">pesan</span></h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-calendar3 text-indigo"></i> <?= number_format($statistics['month_sessions']) ?> sesi bulan ini
            </div>
        </div>
    </div>

    <!-- Stat 7: Percakapan Ada Kendala / Error -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Sesi Ada Kendala</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-danger-subtle text-danger" style="width: 32px; height: 32px;">
                    <i class="bi bi-exclamation-triangle-fill fs-6"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 fs-3 tracking-tight <?= $statistics['error_sessions'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= number_format($statistics['error_sessions']) ?> <span class="fs-xs fw-normal text-muted">sesi</span>
            </h3>
            <div class="fs-3xs text-muted d-flex align-items-center gap-1">
                <i class="bi bi-bug text-danger"></i> <?= number_format($statistics['error_messages']) ?> pesan timeout / kendala AI
            </div>
        </div>
    </div>

    <!-- Stat 8: Ringkasan Penggunaan Role -->
    <div class="col-6 col-md-3 col-xl-3">
        <div class="card vui-card p-3 p-lg-3.5 h-100 border-0 shadow-xs">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fs-2xs text-muted fw-semibold text-uppercase tracking-wider">Distribusi Role</span>
                <div class="d-inline-flex align-items-center justify-content-center rounded-2 bg-teal-subtle text-teal" style="width: 32px; height: 32px; background-color: rgba(20, 184, 166, 0.1); color: #0d9488;">
                    <i class="bi bi-pie-chart-fill fs-6"></i>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs px-2 py-0.5">
                    Siswa: <?= (int)($statistics['role_context_usage']['siswa'] ?? 0) ?>
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs px-2 py-0.5">
                    Guru: <?= (int)($statistics['role_context_usage']['guru'] ?? 0) ?>
                </span>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-3xs px-2 py-0.5">
                    Soal: <?= (int)($statistics['role_context_usage']['question_explainer'] ?? 0) ?>
                </span>
                <span class="badge bg-info-subtle text-info border border-info-subtle fs-3xs px-2 py-0.5">
                    Admin: <?= (int)($statistics['role_context_usage']['admin'] ?? 0) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. FILTER & KONTROL PERIODE GRAFIK                                         -->
<!-- ========================================================================= -->
<div class="card vui-card p-3 p-lg-3.5 border-0 shadow-xs mb-4">
    <form method="GET" action="<?= base_url('admin/ai-chat-reports') ?>" id="filterForm">
        <div class="row g-2.5 align-items-center">
            <!-- Preset Periode Buttons -->
            <div class="col-12 col-xl-5">
                <label class="form-label fs-3xs text-muted fw-semibold text-uppercase mb-1 d-block">Pilih Periode Waktu</label>
                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Preset Periode">
                    <a href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['period' => 'today', 'start_date' => '', 'end_date' => '', 'page' => 1]))) ?>" 
                       class="btn <?= $filters['period'] === 'today' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                        Hari Ini
                    </a>
                    <a href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['period' => '7_days', 'start_date' => '', 'end_date' => '', 'page' => 1]))) ?>" 
                       class="btn <?= $filters['period'] === '7_days' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                        7 Hari
                    </a>
                    <a href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['period' => '30_days', 'start_date' => '', 'end_date' => '', 'page' => 1]))) ?>" 
                       class="btn <?= $filters['period'] === '30_days' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                        30 Hari
                    </a>
                    <a href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['period' => 'this_month', 'start_date' => '', 'end_date' => '', 'page' => 1]))) ?>" 
                       class="btn <?= $filters['period'] === 'this_month' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                        Bulan Ini
                    </a>
                </div>
            </div>

            <!-- Custom Date Range -->
            <div class="col-6 col-sm-3 col-xl-2">
                <label for="startDateInput" class="form-label fs-3xs text-muted fw-semibold text-uppercase mb-1">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm fs-xs" id="startDateInput" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>">
            </div>
            <div class="col-6 col-sm-3 col-xl-2">
                <label for="endDateInput" class="form-label fs-3xs text-muted fw-semibold text-uppercase mb-1">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm fs-xs" id="endDateInput" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>">
            </div>

            <div class="col-12 col-sm-6 col-xl-3 d-flex gap-2 align-items-end pt-sm-3 pt-xl-0">
                <input type="hidden" name="period" value="custom" id="periodInput">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 flex-grow-1 justify-content-center">
                    <i class="bi bi-funnel-fill"></i> Terapkan Filter
                </button>
                <a href="<?= base_url('admin/ai-chat-reports') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- ========================================================================= -->
<!-- 3. GRAFIK AKTIVITAS (CHART.JS DENGAN LIGHT & DARK MODE SUPPORT)           -->
<!-- ========================================================================= -->
<div class="row g-3 mb-4">
    <!-- Chart A: Aktivitas Percakapan & Pesan Harian -->
    <div class="col-12 col-xl-8">
        <div class="card vui-card p-3.5 p-lg-4 border-0 shadow-xs h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h6 class="fw-bold mb-0" style="color: var(--vui-text-main, #0f172a);">
                        <i class="bi bi-graph-up text-primary me-1.5"></i> Aktivitas Percakapan Harian
                    </h6>
                    <span class="fs-3xs text-muted">
                        Rentang: <?= date('d M Y', strtotime($chartData['start_date'])) ?> &mdash; <?= date('d M Y', strtotime($chartData['end_date'])) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill bg-light text-slate-700 border fs-3xs px-2.5 py-1">
                        Total Pesan: <strong><?= number_format(array_sum($chartData['total_messages'])) ?></strong>
                    </span>
                </div>
            </div>

            <div class="position-relative" style="min-height: 260px; height: 280px;">
                <canvas id="timelineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart B: Perbandingan Pesan & Penggunaan Role -->
    <div class="col-12 col-xl-4">
        <div class="card vui-card p-3.5 p-lg-4 border-0 shadow-xs h-100">
            <h6 class="fw-bold mb-3" style="color: var(--vui-text-main, #0f172a);">
                <i class="bi bi-pie-chart-fill text-primary me-1.5"></i> Distribusi Pengguna
            </h6>

            <!-- Role Distribution Doughnut -->
            <div class="position-relative mb-3" style="min-height: 160px; height: 180px;">
                <canvas id="roleDoughnutChart"></canvas>
            </div>

            <hr class="my-2 border-slate-100">

            <!-- Message Ratio Comparison -->
            <div class="d-flex justify-content-between align-items-center pt-2">
                <span class="fs-xs fw-semibold" style="color: var(--vui-text-body, #334155);">Rasio Pesan vs Respons</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs px-2">
                    <?= $chartData['user_vs_bot']['user'] ?> Pertanyaan : <?= $chartData['user_vs_bot']['assistant'] ?> Respons
                </span>
            </div>
            <div class="progress mt-2" style="height: 8px;">
                <?php 
                    $totRatio = max(1, $chartData['user_vs_bot']['user'] + $chartData['user_vs_bot']['assistant']);
                    $userPct = round(($chartData['user_vs_bot']['user'] / $totRatio) * 100);
                    $botPct = 100 - $userPct;
                ?>
                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $userPct ?>%;" title="Pertanyaan Pengguna (<?= $userPct ?>%)"></div>
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $botPct ?>%;" title="Respons Chatbot (<?= $botPct ?>%)"></div>
            </div>
            <div class="d-flex justify-content-between fs-3xs text-muted mt-1">
                <span><i class="bi bi-square-fill text-info me-1"></i> User (<?= $userPct ?>%)</span>
                <span><i class="bi bi-square-fill text-success me-1"></i> Assistant (<?= $botPct ?>%)</span>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. TABEL RIWAYAT PERCAKAPAN & FILTER LANJUTAN                             -->
<!-- ========================================================================= -->
<div class="card vui-card border-0 shadow-xs mb-4">
    <!-- Header Tabel & Filter Bar Tambahan -->
    <div class="p-3.5 p-lg-4 border-bottom border-slate-100">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 tracking-tight" style="color: var(--vui-text-main, #0f172a);">
                    <i class="bi bi-clock-history text-primary me-1.5"></i> Riwayat Interaksi Percakapan
                </h5>
                <p class="fs-xs text-muted mb-0">
                    Menampilkan total <strong><?= number_format($sessions['total_rows']) ?></strong> sesi percakapan dari database aktual.
                </p>
            </div>

            <!-- Form Pencarian & Filter Cepat Role/Status -->
            <form method="GET" action="<?= base_url('admin/ai-chat-reports') ?>" class="d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="period" value="<?= e($filters['period']) ?>">
                <?php if (!empty($filters['start_date'])): ?>
                    <input type="hidden" name="start_date" value="<?= e($filters['start_date']) ?>">
                <?php endif; ?>
                <?php if (!empty($filters['end_date'])): ?>
                    <input type="hidden" name="end_date" value="<?= e($filters['end_date']) ?>">
                <?php endif; ?>

                <!-- Filter Role Dropdown -->
                <select name="role" class="form-select form-select-sm fs-xs" style="width: auto;" onchange="this.form.submit()">
                    <option value="all" <?= $filters['role'] === 'all' ? 'selected' : '' ?>>Semua Role</option>
                    <option value="peserta_didik" <?= in_array($filters['role'], ['peserta_didik', 'siswa']) ? 'selected' : '' ?>>Peserta Didik</option>
                    <option value="guru" <?= $filters['role'] === 'guru' ? 'selected' : '' ?>>Guru</option>
                    <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="question_explainer" <?= $filters['role'] === 'question_explainer' ? 'selected' : '' ?>>Penjelas Soal</option>
                </select>

                <!-- Filter Status Dropdown -->
                <select name="status" class="form-select form-select-sm fs-xs" style="width: auto;" onchange="this.form.submit()">
                    <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ada Pesan</option>
                    <option value="error" <?= $filters['status'] === 'error' ? 'selected' : '' ?>>Ada Kendala (Error)</option>
                    <option value="exam_mode" <?= $filters['status'] === 'exam_mode' ? 'selected' : '' ?>>Mode Ujian</option>
                    <option value="empty" <?= $filters['status'] === 'empty' ? 'selected' : '' ?>>Sesi Baru / Kosong</option>
                </select>

                <!-- Input Pencarian -->
                <div class="input-group input-group-sm" style="min-width: 200px;">
                    <input type="text" 
                           class="form-control fs-xs" 
                           name="search" 
                           value="<?= e($filters['search']) ?>" 
                           placeholder="Cari pengguna / judul...">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if (!empty($filters['search'])): ?>
                        <a href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['search' => '', 'page' => 1]))) ?>" class="btn btn-outline-danger" title="Bersihkan Pencarian">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Container Responsive -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 fs-xs" id="chatSessionsTable">
            <thead class="table-light fs-3xs text-uppercase tracking-wider">
                <tr>
                    <th scope="col" class="text-center ps-3" style="width: 4%;">No</th>
                    <th scope="col" style="width: 24%;">Pengguna</th>
                    <th scope="col" style="width: 14%;">Role & Konteks</th>
                    <th scope="col" style="width: 24%;">Judul Percakapan</th>
                    <th scope="col" class="text-center" style="width: 10%;">Jumlah Pesan</th>
                    <th scope="col" class="text-center" style="width: 12%;">Status</th>
                    <th scope="col" class="text-center pe-3" style="width: 12%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sessions['data'])): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div class="mb-2">
                                <i class="bi bi-chat-left-dots fs-1 opacity-40"></i>
                            </div>
                            <h6 class="fw-semibold text-slate-700 mb-1">Belum Ada Riwayat Percakapan</h6>
                            <p class="fs-xs text-muted mb-0">Tidak ditemukan sesi percakapan yang sesuai dengan filter pencarian Anda.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sessions['data'] as $s): ?>
                        <tr>
                            <!-- Nomor Urut -->
                            <td class="text-center font-monospace ps-3 text-muted"><?= $s['index_no'] ?></td>

                            <!-- Info Pengguna -->
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold fs-xs flex-shrink-0" style="width: 34px; height: 34px;">
                                        <?= strtoupper(substr((string)($s['user_name'] ?? 'U'), 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate text-slate-900" style="max-width: 190px;" title="<?= e($s['user_name'] ?? 'Pengguna') ?>">
                                            <?= e($s['user_name'] ?? 'Anonim') ?>
                                        </div>
                                        <div class="fs-3xs text-muted text-truncate" style="max-width: 190px;">
                                            <?= e($s['user_email'] ?? '-') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Role & Konteks Sesi -->
                            <td>
                                <div class="mb-0.5">
                                    <span class="badge bg-light text-slate-700 border border-slate-200 fs-3xs px-2 py-0.5 fw-medium">
                                        <?= e($s['role_label']) ?>
                                    </span>
                                </div>
                                <?php if ($s['is_exam_mode']): ?>
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-3xs px-2 py-0.5">
                                        <i class="bi bi-shield-shaded me-1"></i>Exam Mode
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Judul Percakapan & Waktu -->
                            <td>
                                <div class="fw-medium text-slate-800 text-truncate" style="max-width: 230px;" title="<?= e($s['title'] ?? 'Percakapan') ?>">
                                    <?= e($s['title'] ?: 'Percakapan Baru') ?>
                                </div>
                                <div class="fs-3xs text-muted d-flex align-items-center gap-1.5 mt-0.5">
                                    <i class="bi bi-clock"></i>
                                    <span><?= date('d/m/Y H:i', strtotime($s['updated_at'])) ?></span>
                                </div>
                            </td>

                            <!-- Jumlah Pesan (User + Bot) -->
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold fs-2xs">
                                        <?= (int)$s['total_messages'] ?>
                                    </span>
                                </div>
                                <div class="fs-3xs text-muted mt-0.5">
                                    <span class="text-info"><?= (int)($s['user_message_count'] ?? 0) ?>U</span> / 
                                    <span class="text-success"><?= (int)($s['bot_message_count'] ?? 0) ?>B</span>
                                </div>
                            </td>

                            <!-- Status Sesi -->
                            <td class="text-center">
                                <span class="badge rounded-pill <?= $s['status_badge']['class'] ?> border px-2.5 py-1 fs-3xs fw-medium d-inline-flex align-items-center gap-1">
                                    <i class="bi <?= $s['status_badge']['icon'] ?>"></i>
                                    <span><?= $s['status_badge']['label'] ?></span>
                                </span>
                            </td>

                            <!-- Aksi Detail -->
                            <td class="text-center pe-3">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-1 fs-xs d-inline-flex align-items-center gap-1"
                                            onclick="openSessionDetail(<?= (int)$s['id'] ?>)" 
                                            title="Lihat riwayat percakapan">
                                        <i class="bi bi-eye"></i>
                                        <span>Detail</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if ($sessions['total_pages'] > 1): ?>
        <div class="p-3 border-top border-slate-100 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fs-xs text-muted">
                Halaman <strong><?= $sessions['current_page'] ?></strong> dari <strong><?= $sessions['total_pages'] ?></strong> (Total <?= number_format($sessions['total_rows']) ?> sesi)
            </span>
            <nav aria-label="Navigasi Halaman Riwayat">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= !$sessions['has_prev'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['page' => $sessions['current_page'] - 1]))) ?>">
                            &laquo; Sebelumnya
                        </a>
                    </li>
                    <?php for ($p = max(1, $sessions['current_page'] - 2); $p <= min($sessions['total_pages'], $sessions['current_page'] + 2); $p++): ?>
                        <li class="page-item <?= $p === $sessions['current_page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>">
                                <?= $p ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= !$sessions['has_next'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('admin/ai-chat-reports?' . http_build_query(array_merge($filters, ['page' => $sessions['current_page'] + 1]))) ?>">
                            Berikutnya &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- 5. MODAL DETAIL PERCAKAPAN (CHAT BUBBLE KRONOLOGIS)                       -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalSessionDetail" tabindex="-1" aria-labelledby="modalSessionDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <!-- Modal Header -->
            <div class="modal-header border-bottom border-slate-100 py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-3 bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-chat-text-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-slate-900" id="modalSessionDetailLabel">Detail Percakapan Chatbot AI</h6>
                        <span class="fs-3xs text-muted" id="modalSessionMeta">Memuat data percakapan...</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <!-- Modal Body: Chat History Container -->
            <div class="modal-body p-3 p-lg-4" style="background-color: var(--vui-bg-body, #f8fafc); min-height: 380px; max-height: calc(80vh - 120px);" id="modalChatBody">
                <div class="text-center py-5" id="modalChatSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                    <div class="fs-xs text-muted mt-2">Memuat seluruh urutan pesan percakapan...</div>
                </div>
                <div id="modalChatMessages" class="d-flex flex-column gap-3" style="display: none;"></div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-top border-slate-100 py-2.5 px-4 d-flex justify-content-between align-items-center">
                <span class="fs-3xs text-muted" id="modalMessageCountBadge">0 pesan</span>
                <div class="d-flex gap-2">
                    <a href="#" id="btnOpenFullPage" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5 fs-xs d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-box-arrow-up-right"></i> Buka Layar Penuh
                    </a>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3.5 py-1.5 fw-semibold" data-bs-dismiss="modal">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 6. SCRIPT CHART.JS & MODAL DETAIL AJAX                                    -->
<!-- ========================================================================= -->
<!-- Load Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Inisialisasi Deteksi Tema (Light vs Dark)
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark' || document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

    // Data dari Server
    const chartData = <?= json_encode($chartData) ?>;

    // 2. Render Chart A: Timeline Aktivitas Percakapan & Pesan Harian
    const ctxTimeline = document.getElementById('timelineChart');
    if (ctxTimeline) {
        new Chart(ctxTimeline, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: [
                    {
                        label: 'Pesan Pengguna',
                        data: chartData.user_messages || [],
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.1)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Respons Chatbot',
                        data: chartData.bot_messages || [],
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Sesi Percakapan',
                        data: chartData.sessions || [],
                        borderColor: '#6366f1',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.2,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor, font: { size: 11 } }
                    },
                    tooltip: {
                        padding: 10,
                        boxPadding: 4
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 10 },
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // 3. Render Chart B: Distribusi Role Doughnut
    const ctxRole = document.getElementById('roleDoughnutChart');
    if (ctxRole) {
        const roleKeys = Object.keys(chartData.role_breakdown || {});
        const roleVals = Object.values(chartData.role_breakdown || {});
        const roleLabels = roleKeys.map(k => {
            if (k === 'peserta_didik' || k === 'siswa') return 'Peserta Didik';
            if (k === 'guru') return 'Guru';
            if (k === 'admin' || k === 'super_admin') return 'Admin';
            if (k === 'question_explainer') return 'Penjelas Soal';
            return k.charAt(0).toUpperCase() + k.slice(1);
        });

        new Chart(ctxRole, {
            type: 'doughnut',
            data: {
                labels: roleLabels.length ? roleLabels : ['Belum ada data'],
                datasets: [{
                    data: roleVals.length ? roleVals : [1],
                    backgroundColor: [
                        '#2563eb', // Siswa (Biru)
                        '#16a34a', // Guru (Hijau)
                        '#d97706', // Penjelas Soal (Kuning)
                        '#9333ea', // Admin (Ungu)
                        '#64748b'  // Lainnya (Abu-abu)
                    ],
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            font: { size: 10 },
                            boxWidth: 12,
                            padding: 8
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }
});

// 4. Modal Detail Percakapan AJAX Fetcher
function openSessionDetail(sessionId) {
    const modalEl = document.getElementById('modalSessionDetail');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const spinner = document.getElementById('modalChatSpinner');
    const messagesContainer = document.getElementById('modalChatMessages');
    const metaEl = document.getElementById('modalSessionMeta');
    const countBadge = document.getElementById('modalMessageCountBadge');
    const fullPageBtn = document.getElementById('btnOpenFullPage');

    spinner.style.display = 'block';
    messagesContainer.style.display = 'none';
    messagesContainer.innerHTML = '';
    metaEl.textContent = 'Memuat sesi #' + sessionId + '...';
    fullPageBtn.href = '<?= base_url('admin/ai-chat-reports/detail/') ?>' + sessionId;

    fetch('<?= base_url('admin/ai-chat-reports/detail-ajax/') ?>' + sessionId, {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        spinner.style.display = 'none';
        messagesContainer.style.display = 'flex';

        if (!data.success || !data.session) {
            messagesContainer.innerHTML = '<div class="alert alert-danger fs-xs mb-0">' + (data.error || 'Gagal memuat sesi.') + '</div>';
            return;
        }

        const s = data.session;
        metaEl.innerHTML = '<strong>' + escapeHtml(s.user_name || 'Anonim') + '</strong> &bull; ' + 
                           escapeHtml(s.role_label) + ' &bull; ' + 
                           escapeHtml(s.title || 'Percakapan AI') + ' (ID #' + s.id + ')';
        countBadge.textContent = (s.messages ? s.messages.length : 0) + ' pesan tersimpan';

        if (!s.messages || s.messages.length === 0) {
            messagesContainer.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-dots fs-1 opacity-40 mb-2 d-block"></i>
                    <h6 class="fs-sm fw-semibold mb-1">Belum Ada Pesan</h6>
                    <p class="fs-xs mb-0">Sesi ini telah dibuat tetapi pengguna belum mengirimkan pesan.</p>
                </div>
            `;
            return;
        }

        let chatHtml = '';
        s.messages.forEach(m => {
            const isUser = (m.sender === 'user');
            const isAssistant = (m.sender === 'assistant');
            const isError = (parseInt(m.is_error, 10) === 1);

            if (isUser) {
                chatHtml += `
                    <div class="d-flex justify-content-end mb-2">
                        <div class="d-flex flex-column align-items-end" style="max-width: 82%;">
                            <div class="d-flex align-items-center gap-1.5 mb-1 fs-3xs text-muted">
                                <span class="fw-semibold text-slate-700">${escapeHtml(s.user_name || 'Pengguna')}</span>
                                <span>&bull;</span>
                                <span>${formatTimestamp(m.created_at)}</span>
                            </div>
                            <div class="p-3 rounded-4 shadow-2xs text-white" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border-bottom-right-radius: 4px !important; font-size: 0.85rem; line-height: 1.5; word-break: break-word;">
                                ${escapeHtml(m.message)}
                            </div>
                        </div>
                    </div>
                `;
            } else if (isAssistant) {
                const badgeError = isError ? '<span class="badge bg-danger rounded-pill fs-3xs px-2 py-0.5"><i class="bi bi-exclamation-circle me-1"></i>Ada Kendala</span>' : '';
                const modelBadge = m.model_used ? `<span class="badge bg-light text-slate-600 border border-slate-200 fs-3xs px-2 py-0.5">${escapeHtml(m.model_used)}</span>` : '';

                chatHtml += `
                    <div class="d-flex justify-content-start mb-2">
                        <div class="d-flex flex-column align-items-start" style="max-width: 88%;">
                            <div class="d-flex align-items-center gap-1.5 mb-1 fs-3xs text-muted flex-wrap">
                                <span class="d-inline-flex align-items-center gap-1 fw-bold text-primary">
                                    <i class="bi bi-robot"></i> Smart Assistant
                                </span>
                                ${modelBadge}
                                ${badgeError}
                                <span>&bull;</span>
                                <span>${formatTimestamp(m.created_at)}</span>
                            </div>
                            <div class="p-3.5 rounded-4 shadow-2xs border" style="background-color: var(--vui-bg-card, #ffffff); border-color: ${isError ? '#fca5a5' : 'var(--vui-border, #e2e8f0)'} !important; border-bottom-left-radius: 4px !important; font-size: 0.85rem; line-height: 1.6; color: var(--vui-text-body, #334155); word-break: break-word;">
                                ${formatMessageBody(m.message)}
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        messagesContainer.innerHTML = chatHtml;
    })
    .catch(err => {
        spinner.style.display = 'none';
        messagesContainer.style.display = 'block';
        messagesContainer.innerHTML = '<div class="alert alert-danger fs-xs mb-0">Terjadi kesalahan jaringan saat memuat sesi: ' + escapeHtml(err.message) + '</div>';
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatMessageBody(text) {
    if (!text) return '';
    // Format baris baru menjadi <br> dan escape HTML
    return escapeHtml(text).replace(/\n/g, '<br>');
}

function formatTimestamp(dtStr) {
    if (!dtStr) return '';
    try {
        const d = new Date(dtStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' (' + d.toLocaleDateString() + ')';
    } catch(e) {
        return dtStr;
    }
}
</script>

