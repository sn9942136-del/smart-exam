<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<!-- Header Greeting & Sub-Navigation (Ref: Modern Simple Sleek) -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold tracking-tight mb-2" style="color: var(--vui-text-main, #0f172a); font-size: 1.5rem;">
            Halo, <?= e(explode(' ', $user['name'] ?? 'Bpk/Ibu Guru')[0]) ?> 👋
        </h3>
        <div class="d-flex align-items-center gap-1.5 flex-wrap">
            <button type="button" class="smart-pill-btn active" id="guruTabRingkasan" onclick="showGuruSection('ringkasan')">
                <i class="bi bi-grid-fill"></i> Ringkasan
            </button>
            <button type="button" class="smart-pill-btn" id="guruTabManajemen" onclick="showGuruSection('manajemen')">
                <i class="bi bi-gear-fill"></i> Manajemen
            </button>
            <button type="button" class="smart-pill-btn" id="guruTabBank" onclick="showGuruSection('bank')">
                <i class="bi bi-collection"></i> Bank Soal
            </button>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('guru/question-banks/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 py-2 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-plus-circle"></i>
            <span>Buat Bank Soal</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 py-2 fw-medium d-inline-flex align-items-center gap-1.5" onclick="App.openCameraDiagnostic()">
            <i class="bi bi-camera-video"></i>
            <span>Uji Dual Cam</span>
        </button>
    </div>
</div>

<!-- 4 Clean Metric Cards (Ref: SmartUTBK Aesthetic) -->
<div class="row g-3 mb-4" id="guruSectionRingkasan">
    <!-- Stat 1: Bank Soal -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #3b82f6;"></div>
                <div class="smart-metric-title">Bank Soal</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= $stats['question_banks'] ?? 0 ?> bank
                </div>
                <span><?= $stats['total_items'] ?? 0 ?> butir tersimpan</span>
            </div>
        </div>
    </div>

    <!-- Stat 2: Jadwal Ujian -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #f59e0b;"></div>
                <div class="smart-metric-title">Jadwal Ujian</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= $stats['upcoming_exams'] ?? 0 ?> ujian
                </div>
                <span>terjadwal aktif</span>
            </div>
        </div>
    </div>

    <!-- Stat 3: Koreksi Esai -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                    <i class="bi bi-clipboard-check-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #10b981;"></div>
                <div class="smart-metric-title">Koreksi Esai</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= $stats['graded_essays'] ?? 0 ?> dinilai
                </div>
                <span>sisa <?= $stats['pending_essays'] ?? 0 ?> pending</span>
            </div>
        </div>
    </div>

    <!-- Stat 4: Pengawasan Live -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">
                    <i class="bi bi-broadcast"></i>
                </div>
                <div class="smart-metric-line" style="background: #ef4444;"></div>
                <div class="smart-metric-title">Pengawasan Live</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-danger mb-0.5">
                    <?= $stats['active_rooms'] ?? 0 ?> ruang
                </div>
                <span>monitoring aktif</span>
            </div>
        </div>
    </div>
</div>

<!-- Section: Manajemen (Ref: 3 Wide Cards) -->
<div class="mb-4" id="guruSectionManajemen">
    <h6 class="fw-bold mb-3 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-size: 0.95rem;">
        Manajemen
    </h6>
    <div class="row g-3">
        <!-- Card 1: Bank Soal -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                        <i class="bi bi-collection-fill"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Bank Soal</h6>
                    <p class="fs-2xs text-muted mb-3">Kelola butir soal, tambah, edit, dan import format Excel.</p>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <a href="<?= base_url('guru/question-banks') ?>" class="smart-action-link">
                        <span>Buka Bank Soal</span> <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="<?= base_url('guru/question-banks/create') ?>" class="smart-action-link text-muted">
                        <span>+ Baru &rarr;</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 2: AI Question Generator -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(147, 51, 234, 0.15); color: #a855f7;">
                        <i class="bi bi-stars"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">AI Question Gen</h6>
                    <p class="fs-2xs text-muted mb-3">Buat paket soal otomatis menggunakan kecerdasan buatan.</p>
                </div>
                <a href="<?= base_url('guru/ai-questions') ?>" class="smart-action-link">
                    <span>Generate AI</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Card 3: Evaluasi & Live Monitoring -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Monitoring &amp; Penilaian</h6>
                    <p class="fs-2xs text-muted mb-3">Pantau kamera siswa dan lakukan penilaian jawaban esai.</p>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <a href="<?= base_url('guru/exams/monitoring') ?>" class="smart-action-link">
                        <span>Live Monitoring</span> <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="<?= base_url('guru/grading') ?>" class="smart-action-link text-muted">
                        <span>Koreksi Esai &rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section: Bank Soal Terbaru & Alat Cepat -->
<div class="row g-3 mb-4" id="guruSectionBank">
    <!-- Left: Bank Soal Terbaru Table -->
    <div class="col-lg-8">
        <div class="vui-card overflow-hidden h-100 d-flex flex-column justify-content-between">
            <div class="card-header bg-body py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                    <i class="bi bi-collection text-primary"></i>
                    <span>Bank Soal Terbaru</span>
                </h6>
                <a href="<?= base_url('guru/question-banks') ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold">
                    <span>Semua &rarr;</span>
                </a>
            </div>

            <?php if (empty($recentBanks)): ?>
                <div class="text-center py-4 text-muted flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-journal-x fs-3 text-muted mb-1"></i>
                    <span class="fs-xs">Belum ada bank soal tersimpan.</span>
                </div>
            <?php else: ?>
                <div class="table-responsive flex-grow-1">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-body-tertiary text-body-secondary fs-3xs text-uppercase fw-semibold border-bottom">
                            <tr>
                                <th class="py-2 px-3">Judul</th>
                                <th class="py-2 px-3">Mapel</th>
                                <th class="py-2 px-3 text-center">Butir</th>
                                <th class="py-2 px-3">Status</th>
                                <th class="py-2 px-3 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fs-xs">
                            <?php foreach ($recentBanks as $bank): ?>
                                <tr>
                                    <td class="px-3">
                                        <span class="fw-semibold text-truncate d-inline-block" style="max-width: 220px;"><?= e($bank['title']) ?></span>
                                    </td>
                                    <td class="px-3">
                                        <span class="badge bg-body-tertiary text-body border rounded-pill fs-3xs px-2 py-0.5">
                                            <?= e($bank['subject_name'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 text-center fw-semibold"><?= (int)$bank['total_questions'] ?></td>
                                    <td class="px-3">
                                        <?php if ($bank['status'] === 'active'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs px-2 py-0.5">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill fs-3xs px-2 py-0.5">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 text-end">
                                        <a href="<?= base_url('guru/question-banks/show/' . $bank['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0.5 fs-3xs fw-semibold">
                                            Kelola
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Alat Cepat -->
    <div class="col-lg-4">
        <div class="vui-card p-3.5 p-lg-4 h-100 d-flex flex-column justify-content-between">
            <h6 class="fw-bold mb-3 pb-2 border-bottom d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                <i class="bi bi-lightning-charge text-primary"></i>
                <span>Alat Pintar</span>
            </h6>
            <div class="d-flex flex-column gap-2 mb-3">
                <a href="<?= base_url('guru/question-banks/template') ?>" class="p-2.5 rounded-3 border bg-body-tertiary text-decoration-none d-flex align-items-center gap-2.5 transition-all">
                    <div class="rounded-2 bg-info-subtle text-info p-1.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                        <i class="bi bi-file-earmark-excel fs-6"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-semibold fs-xs text-body mb-0">Template Soal Excel</div>
                        <div class="fs-3xs text-muted">Format .csv &bull; Impor Cepat</div>
                    </div>
                </a>

                <button type="button" class="p-2.5 rounded-3 border bg-body-tertiary text-decoration-none d-flex align-items-center gap-2.5 text-start transition-all w-100" onclick="App.openCameraDiagnostic()">
                    <div class="rounded-2 bg-warning-subtle text-warning-emphasis p-1.5 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                        <i class="bi bi-camera-video fs-6"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-semibold fs-xs text-body mb-0">Kalibrasi Dual Kamera</div>
                        <div class="fs-3xs text-muted">Uji webcam &bull; QR Smartphone</div>
                    </div>
                </button>
            </div>
            <a href="<?= base_url('guru/exams') ?>" class="btn btn-outline-primary btn-sm w-100 py-2 rounded-3 fw-semibold fs-xs d-flex align-items-center justify-content-center gap-1.5">
                <i class="bi bi-calendar-check"></i>
                <span>Buka Manajemen Ujian</span>
            </a>
        </div>
    </div>
</div>

<script>
function showGuruSection(section) {
    document.querySelectorAll('.smart-pill-btn').forEach(btn => btn.classList.remove('active'));
    if (section === 'ringkasan') {
        document.getElementById('guruTabRingkasan').classList.add('active');
        document.getElementById('guruSectionRingkasan').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'manajemen') {
        document.getElementById('guruTabManajemen').classList.add('active');
        document.getElementById('guruSectionManajemen').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'bank') {
        document.getElementById('guruTabBank').classList.add('active');
        document.getElementById('guruSectionBank').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
