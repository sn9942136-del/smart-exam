<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<!-- Header Greeting & Sub-Navigation -->
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
                <i class="bi bi-card-checklist"></i> Akses Ujian
            </button>
            <button type="button" class="smart-pill-btn" id="siswaTabKamera" onclick="showSiswaSection('kamera')">
                <i class="bi bi-camera-video"></i> Kalibrasi Kamera
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

<!-- 4 Clean Metric Cards -->
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
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5"><?= $stats['available_exams'] ?? 0 ?> ujian</div>
                <span>siap dikerjakan</span>
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
                <div class="fw-bold fs-5 text-success mb-0.5"><?= $stats['ongoing_exams'] ?? 0 ?> sesi</div>
                <span>berlangsung hari ini</span>
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
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5"><?= $stats['completed_exams'] ?? 0 ?> ujian</div>
                <span>telah tuntas</span>
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
                <div class="fw-bold fs-5 text-warning mb-0.5"><?= number_format((float)($stats['average_score'] ?? 0), 1) ?></div>
                <span>skor akademik</span>
            </div>
        </div>
    </div>
</div>

<!-- Section: Akses Cepat (Ref: 3 Wide Cards) -->
<div class="mb-4" id="siswaSectionUjian">
    <h6 class="fw-bold mb-3 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-size: 0.95rem;">
        Akses Cepat
    </h6>
    <div class="row g-3">
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

        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Hasil &amp; Nilai</h6>
                    <p class="fs-2xs text-muted mb-3">Periksa rekap nilai, kunci pembahasan, dan evaluasi hasil ujian.</p>
                </div>
                <a href="<?= base_url('siswa/results') ?>" class="smart-action-link">
                    <span>Lihat Nilai</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

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

<script>
function showSiswaSection(section) {
    document.querySelectorAll('.smart-pill-btn').forEach(btn => btn.classList.remove('active'));
    if (section === 'ringkasan') {
        document.getElementById('siswaTabRingkasan').classList.add('active');
        document.getElementById('siswaSectionRingkasan').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'ujian') {
        document.getElementById('siswaTabUjian').classList.add('active');
        document.getElementById('siswaSectionUjian').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
