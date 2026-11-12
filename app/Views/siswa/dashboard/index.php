<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<?php if (!empty($ongoingRecovery)): ?>
    <!-- Active Exam Session Recovery Banner (UC-14) -->
    <div class="card border-0 shadow-sm rounded-4 p-3.5 mb-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #0d9488 100%);">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 position-relative z-1">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 bg-warning text-dark p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-0.5">
                        <span class="badge bg-warning text-dark fw-bold px-2 py-0.5 rounded-pill fs-3xs">Sesi Berlangsung</span>
                        <span class="text-white-75 fs-3xs">Kode: <strong><?= e($ongoingRecovery['exam_code']) ?></strong></span>
                    </div>
                    <h5 class="fw-bold mb-0 text-white tracking-tight fs-6"><?= e($ongoingRecovery['exam_title']) ?></h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge px-3 py-1.5 rounded-pill font-monospace fs-xs" style="background: rgba(255,255,255,0.18);">
                    <i class="bi bi-clock-history text-warning me-1"></i> <?= sprintf('%02d:%02d:%02d', floor($ongoingRecovery['remaining_seconds']/3600), floor(($ongoingRecovery['remaining_seconds']%3600)/60), $ongoingRecovery['remaining_seconds']%60) ?>
                </span>
                <a href="<?= $ongoingRecovery['room_url'] ?>" class="btn btn-warning btn-sm rounded-pill px-3.5 py-1.5 fw-bold shadow-xs d-inline-flex align-items-center gap-1.5">
                    <i class="bi bi-play-circle-fill"></i>
                    <span>Lanjutkan Ujian</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Header Greeting & Sub-Navigation (Ref: Modern Simple Sleek) -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold tracking-tight mb-2" style="color: var(--vui-text-main, #0f172a); font-size: 1.5rem;">
            Halo, <?= e(explode(' ', $user['name'] ?? 'Peserta Didik')[0]) ?> 👋
        </h3>
        <div class="d-flex align-items-center gap-1.5 flex-wrap">
            <button type="button" class="smart-pill-btn active" id="siswaTabRingkasan" onclick="showSiswaSection('ringkasan')">
                <i class="bi bi-grid-fill"></i> Ringkasan
            </button>
            <button type="button" class="smart-pill-btn" id="siswaTabUjian" onclick="showSiswaSection('ujian')">
                <i class="bi bi-card-checklist"></i> Ujian Hari Ini
            </button>
            <button type="button" class="smart-pill-btn" id="siswaTabRiwayat" onclick="showSiswaSection('riwayat')">
                <i class="bi bi-clock-history"></i> Riwayat Nilai
            </button>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('siswa/exams') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 py-2 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-card-checklist"></i>
            <span>Daftar Ujian</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 py-2 fw-medium d-inline-flex align-items-center gap-1.5" onclick="App.openCameraDiagnostic()">
            <i class="bi bi-camera-video"></i>
            <span>Kalibrasi Kamera</span>
        </button>
    </div>
</div>

<!-- 4 Clean Metric Cards (Ref: SmartUTBK Aesthetic) -->
<div class="row g-3 mb-4" id="siswaSectionRingkasan">
    <!-- Stat 1: Ujian Tersedia -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                    <i class="bi bi-calendar2-check-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #3b82f6;"></div>
                <div class="smart-metric-title">Ujian Tersedia</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= (int)($stats['total_available'] ?? 0) ?> ujian
                </div>
                <span>di kelas <?= e($studentClass['name'] ?? 'Anda') ?></span>
            </div>
        </div>
    </div>

    <!-- Stat 2: Sedang Berlangsung -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #10b981;"></div>
                <div class="smart-metric-title">Ujian Aktif</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-success mb-0.5">
                    <?= (int)($stats['ongoing_today'] ?? 0) ?> sesi
                </div>
                <span>siap dikerjakan</span>
            </div>
        </div>
    </div>

    <!-- Stat 3: Ujian Selesai -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(147, 51, 234, 0.15); color: #a855f7;">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div class="smart-metric-line" style="background: #a855f7;"></div>
                <div class="smart-metric-title">Ujian Selesai</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= (int)($stats['completed_exams'] ?? 0) ?> ujian
                </div>
                <span>tuntas dikerjakan</span>
            </div>
        </div>
    </div>

    <!-- Stat 4: Rata-rata Nilai -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #f59e0b;"></div>
                <div class="smart-metric-title">Rata-rata Nilai</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-warning mb-0.5">
                    <?= number_format((float)($stats['average_score'] ?? 0), 1) ?>
                </div>
                <span>skor akademik resmi</span>
            </div>
        </div>
    </div>
</div>

<!-- Section: Akses Cepat (Ref: 3 Wide Cards) -->
<div class="mb-4">
    <h6 class="fw-bold mb-3 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-size: 0.95rem;">
        Akses Cepat
    </h6>
    <div class="row g-3">
        <!-- Card 1: Katalog Ujian -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                        <i class="bi bi-card-checklist"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Daftar Ujian</h6>
                    <p class="fs-2xs text-muted mb-3">Lihat seluruh jadwal ujian aktif dan token pengerjaan.</p>
                </div>
                <a href="<?= base_url('siswa/exams') ?>" class="smart-action-link">
                    <span>Buka Ujian</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Card 2: Hasil & Nilai -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Hasil &amp; Nilai</h6>
                    <p class="fs-2xs text-muted mb-3">Periksa rekap nilai, kunci pembahasan, dan umpan balik guru.</p>
                </div>
                <a href="<?= base_url('siswa/results') ?>" class="smart-action-link">
                    <span>Lihat Nilai</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Card 3: Kalibrasi Dual Kamera -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <i class="bi bi-camera-video"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Kalibrasi Kamera</h6>
                    <p class="fs-2xs text-muted mb-3">Uji coba webcam laptop &amp; kamera HP sebelum masuk ruang ujian.</p>
                </div>
                <a href="#" class="smart-action-link" onclick="App.openCameraDiagnostic(); return false;">
                    <span>Uji Kamera</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Section: Ujian Aktif Hari Ini -->
<div class="vui-card p-3.5 p-lg-4 mb-4" id="siswaSectionUjian">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                <i class="bi bi-play-circle text-primary"></i>
                <span>Ujian Aktif untuk Kelas <?= e($studentClass['name'] ?? '') ?></span>
            </h6>
        </div>
        <a href="<?= base_url('siswa/exams') ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold">
            <span>Semua (<?= count($availableExams) ?>) &rarr;</span>
        </a>
    </div>

    <?php if (empty($todayExams)): ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-calendar-x fs-3 text-muted mb-1 d-block"></i>
            <span class="fs-xs">Belum ada ujian berlangsung untuk kelas Anda saat ini.</span>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($todayExams as $ex): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border rounded-3 p-3 bg-body-tertiary d-flex flex-column justify-content-between transition-all">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-body text-body border rounded-pill fs-3xs">
                                    <?= e($ex['subject_name']) ?>
                                </span>
                                <?php if ($ex['time_status'] === 'ongoing'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs">
                                        Berlangsung
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-body-tertiary text-body-secondary border rounded-pill fs-3xs">
                                        Terjadwal
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h6 class="fw-semibold mb-1 fs-xs text-truncate" title="<?= e($ex['title']) ?>">
                                <?= e($ex['title']) ?>
                            </h6>
                            <small class="text-muted fs-3xs d-block mb-2">
                                Guru: <?= e($ex['teacher_name']) ?> &bull; <?= (int)$ex['duration_minutes'] ?> Menit
                            </small>
                        </div>

                        <div class="pt-2 border-top">
                            <a href="<?= base_url('siswa/exams/show/' . $ex['exam_id']) ?>" class="btn btn-sm <?= $ex['time_status'] === 'ongoing' ? 'btn-primary' : 'btn-outline-primary' ?> w-100 rounded-pill py-1 fs-xs fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Buka Ujian
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Section: Riwayat Ujian Terkini -->
<div class="vui-card overflow-hidden mb-4" id="siswaSectionRiwayat">
    <div class="card-header bg-body py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="font-size: 0.9rem;">
            <i class="bi bi-clock-history text-primary"></i>
            <span>Riwayat Ujian Terkini</span>
        </h6>
        <a href="<?= base_url('siswa/history') ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1 fw-semibold">
            <span>Semua &rarr;</span>
        </a>
    </div>

    <?php if (empty($recentHistory)): ?>
        <div class="text-center py-4 text-muted fs-xs">
            <i class="bi bi-journal-x fs-3 text-muted mb-1 d-block"></i>
            <span>Belum ada riwayat pengerjaan ujian.</span>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-xs">
                <thead class="bg-body-tertiary text-body-secondary fs-3xs text-uppercase fw-semibold border-bottom">
                    <tr>
                        <th class="py-2 px-3">Ujian</th>
                        <th class="py-2 px-3">Waktu</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3">Nilai</th>
                        <th class="py-2 px-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recentHistory, 0, 5) as $h): ?>
                        <tr>
                            <td class="px-3">
                                <div class="fw-semibold"><?= e($h['title']) ?></div>
                                <small class="text-muted fs-3xs"><?= e($h['subject_name']) ?></small>
                            </td>
                            <td class="px-3 text-muted fs-3xs">
                                <?= !empty($h['session_submit_time']) ? date('d M, H:i', strtotime($h['session_submit_time'])) : date('d M, H:i', strtotime($h['start_time'])) ?>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($h['result_id']) || $h['session_status'] === 'submitted' || $h['participant_status'] === 'completed'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs px-2 py-0.5">Selesai</span>
                                <?php elseif ($h['session_status'] === 'ongoing'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill fs-3xs px-2 py-0.5">Berlangsung</span>
                                <?php else: ?>
                                    <span class="badge bg-body-tertiary text-body border rounded-pill fs-3xs px-2 py-0.5">Terdaftar</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($h['result_id']) && ($h['result_status'] === 'published' || (int)$h['show_result_immediately'] === 1)): ?>
                                    <span class="fw-bold fs-xs text-primary"><?= number_format((float)$h['final_score'], 1) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fs-3xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-end">
                                <?php if (!empty($h['result_id']) && ($h['result_status'] === 'published' || (int)$h['show_result_immediately'] === 1)): ?>
                                    <a href="<?= base_url('siswa/results/show/' . $h['result_id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-0.5 fs-3xs fw-semibold">
                                        Nilai
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_url('siswa/exams/show/' . $h['exam_id']) ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-0.5 fs-3xs fw-semibold">
                                        Info
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function showSiswaSection(section) {
    document.querySelectorAll('.smart-pill-btn').forEach(btn => btn.classList.remove('active'));
    if (section === 'ringkasan') {
        document.getElementById('siswaTabRingkasan').classList.add('active');
        document.getElementById('siswaSectionRingkasan').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'ujian') {
        document.getElementById('siswaTabUjian').classList.add('active');
        document.getElementById('siswaSectionUjian').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'riwayat') {
        document.getElementById('siswaTabRiwayat').classList.add('active');
        document.getElementById('siswaSectionRiwayat').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
