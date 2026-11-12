<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<!-- Header Greeting & Sub-Navigation -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold tracking-tight mb-2" style="color: var(--vui-text-main, #0f172a); font-size: 1.5rem;">
            Halo, <?= e(explode(' ', $user['name'] ?? 'Administrator')[0]) ?> 👋
        </h3>
        <div class="d-flex align-items-center gap-1.5 flex-wrap">
            <button type="button" class="smart-pill-btn active" id="tabBtnRingkasan" onclick="showDashboardSection('ringkasan')">
                <i class="bi bi-grid-fill"></i> Ringkasan
            </button>
            <button type="button" class="smart-pill-btn" id="tabBtnManajemen" onclick="showDashboardSection('manajemen')">
                <i class="bi bi-gear-fill"></i> Manajemen
            </button>
            <button type="button" class="smart-pill-btn" id="tabBtnAudit" onclick="showDashboardSection('audit')">
                <i class="bi bi-journal-text"></i> Jejak Audit
            </button>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/system-configuration?tab=proctoring') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 py-2 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-cpu"></i>
            <span>Konfigurasi AI</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 py-2 fw-medium d-inline-flex align-items-center gap-1.5" onclick="App.openCameraDiagnostic()">
            <i class="bi bi-camera-video"></i>
            <span>Diagnostik Kamera</span>
        </button>
    </div>
</div>

<!-- 4 Clean Metric Cards -->
<div class="row g-3 mb-4" id="sectionRingkasan">
    <!-- Stat 1: Total User -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(147, 51, 234, 0.15); color: #a855f7;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #a855f7;"></div>
                <div class="smart-metric-title">Total User</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5"><?= $stats['total_users'] ?? 0 ?> user</div>
                <span>terdaftar di sistem</span>
            </div>
        </div>
    </div>

    <!-- Stat 2: Total Ujian -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #3b82f6;"></div>
                <div class="smart-metric-title">Paket Ujian</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5"><?= $stats['total_exams'] ?? 0 ?> ujian</div>
                <span>terjadwal aktif</span>
            </div>
        </div>
    </div>

    <!-- Stat 3: AI Proctoring -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="smart-metric-line" style="background: #10b981;"></div>
                <div class="smart-metric-title">Sesi Aktif</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-success mb-0.5"><?= $stats['active_sessions'] ?? 0 ?> online</div>
                <span>pengawasan real-time</span>
            </div>
        </div>
    </div>

    <!-- Stat 4: Event Pelanggaran -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #ef4444;"></div>
                <div class="smart-metric-title">Anomali AI</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-danger mb-0.5"><?= $stats['proctoring_flags'] ?? 0 ?> insiden</div>
                <span>terdeteksi proctoring</span>
            </div>
        </div>
    </div>
</div>

<!-- Section: Manajemen (Ref: 3 Cards Row) -->
<div class="mb-4" id="sectionManajemen">
    <h6 class="fw-bold mb-3 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-size: 0.95rem;">
        Manajemen
    </h6>
    <div class="row g-3">
        <!-- Card 1: Bank Soal & Ujian -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Bank Soal &amp; Ujian</h6>
                    <p class="fs-2xs text-muted mb-3">Kelola butir soal, paket ujian, token, dan jadwal ujian.</p>
                </div>
                <a href="<?= base_url('admin/exams') ?>" class="smart-action-link">
                    <span>Kelola</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Card 2: Data Master Akademik -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <i class="bi bi-buildings-fill"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Data Master Akademik</h6>
                    <p class="fs-2xs text-muted mb-3">Atur kelas, rombel, mata pelajaran, dan periode akademik.</p>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <a href="<?= base_url('admin/classes') ?>" class="smart-action-link">
                        <span>Kelas &amp; Mapel</span> <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="<?= base_url('admin/academic-years') ?>" class="smart-action-link text-muted">
                        <span>Tahun Ajaran &rarr;</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 3: Manajemen User -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(147, 51, 234, 0.15); color: #a855f7;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Manajemen User</h6>
                    <p class="fs-2xs text-muted mb-3">Kelola akun pengguna, hak akses, status aktif, dan reset sandi.</p>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <a href="<?= base_url('admin/users') ?>" class="smart-action-link">
                        <span>Kelola Pengguna</span> <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="<?= base_url('admin/roles') ?>" class="smart-action-link text-muted">
                        <span>Role &rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showDashboardSection(section) {
    document.querySelectorAll('.smart-pill-btn').forEach(btn => btn.classList.remove('active'));
    if (section === 'ringkasan') {
        document.getElementById('tabBtnRingkasan').classList.add('active');
        document.getElementById('sectionRingkasan').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'manajemen') {
        document.getElementById('tabBtnManajemen').classList.add('active');
        document.getElementById('sectionManajemen').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else if (section === 'audit') {
        document.getElementById('tabBtnAudit').classList.add('active');
        document.getElementById('sectionAudit')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
