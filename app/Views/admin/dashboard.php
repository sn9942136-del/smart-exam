<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<!-- Header Greeting & Sub-Navigation (Ref: Modern Simple Sleek) -->
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

<!-- 4 Clean Metric Cards (Ref: SmartUTBK Aesthetic) -->
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
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= (!empty($stats['total_users']) && (int)$stats['total_users'] > 0) ? (int)$stats['total_users'] : ((int)($stats['total_teachers'] ?? 0) + (int)($stats['total_students'] ?? 0) + (int)($stats['total_admins'] ?? 0)) ?>
                </div>
                <span><?= $stats['total_admins'] ?? 1 ?> admin &bull; <?= $stats['total_teachers'] ?? 0 ?> guru &bull; <?= $stats['total_students'] ?? 0 ?> siswa</span>
            </div>
        </div>
    </div>

    <!-- Stat 2: Bank Soal -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #3b82f6;"></div>
                <div class="smart-metric-title">Bank Soal</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= $stats['total_qbanks'] ?? 0 ?> bank
                </div>
                <span>soal tersimpan di sistem</span>
            </div>
        </div>
    </div>

    <!-- Stat 3: Paket Ujian -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                    <i class="bi bi-calendar2-check-fill"></i>
                </div>
                <div class="smart-metric-line" style="background: #f59e0b;"></div>
                <div class="smart-metric-title">Paket Ujian</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-body-emphasis mb-0.5">
                    <?= $stats['total_exams'] ?? 0 ?> paket
                </div>
                <span>jadwal ujian aktif</span>
            </div>
        </div>
    </div>

    <!-- Stat 4: AI Proctoring -->
    <div class="col-6 col-lg-3">
        <div class="smart-metric-card">
            <div>
                <div class="smart-metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="smart-metric-line" style="background: #10b981;"></div>
                <div class="smart-metric-title">AI Proctoring</div>
            </div>
            <div class="smart-metric-sub">
                <div class="fw-bold fs-5 text-success mb-0.5">
                    Aktif
                </div>
                <span>Dual Camera Vision Engine</span>
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

        <!-- Card 3: Manajemen Pengguna -->
        <div class="col-md-4">
            <div class="smart-action-card">
                <div>
                    <div class="smart-metric-icon mb-3" style="background: rgba(147, 51, 234, 0.15); color: #a855f7;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h6 class="fw-bold mb-1 fs-xs" style="color: var(--vui-text-main, #0f172a);">Manajemen Pengguna</h6>
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

<!-- Section: Parameter AI & Jejak Audit -->
<div class="row g-3 mb-4" id="sectionAudit">
    <!-- Left: Parameter AI Card -->
    <div class="col-lg-4">
        <div class="vui-card p-3.5 p-lg-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                        <i class="bi bi-cpu-fill text-primary"></i>
                        <span>Parameter AI</span>
                    </h6>
                    <span class="vui-badge-soft fs-3xs">
                        <i class="bi bi-eye"></i> Vision
                    </span>
                </div>

                <div class="p-2.5 rounded-3 bg-body-tertiary border mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-body-secondary fs-3xs">Sensitivitas Gaze</span>
                        <span class="badge bg-body text-primary border fs-3xs font-monospace">0.75</span>
                    </div>
                    <small class="fw-semibold fs-xs d-block mt-0.5">Toleransi Standar</small>
                </div>

                <div class="p-2.5 rounded-3 bg-body-tertiary border mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-body-secondary fs-3xs">Batas Wajah Hilang</span>
                        <span class="badge bg-body text-danger border fs-3xs font-monospace">5s</span>
                    </div>
                    <small class="fw-semibold fs-xs d-block mt-0.5">Maksimal 5 Detik</small>
                </div>

                <div class="p-2.5 rounded-3 bg-body-tertiary border mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-body-secondary fs-3xs">Sync Dual Kamera</span>
                        <span class="badge bg-body text-success border fs-3xs font-monospace">1000ms</span>
                    </div>
                    <small class="fw-semibold fs-xs d-block mt-0.5">Real-Time Sync</small>
                </div>
            </div>

            <a href="<?= base_url('admin/system-configuration?tab=proctoring') ?>" class="btn btn-primary btn-sm w-100 py-2 rounded-3 fw-semibold fs-xs shadow-xs d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-sliders"></i>
                <span>Sesuaikan Parameter AI</span>
            </a>
        </div>
    </div>

    <!-- Right: Recent System Audit Logs Table -->
    <div class="col-lg-8">
        <div class="vui-card overflow-hidden h-100 d-flex flex-column justify-content-between">
            <div class="card-header bg-body py-3 px-3.5 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="fw-bold mb-0 d-flex align-items-center gap-2" style="font-size: 0.9rem;">
                    <i class="bi bi-journal-text text-primary"></i>
                    <span>Jejak Audit Aktivitas</span>
                </h6>
                <span class="vui-badge-soft fs-3xs">
                    <i class="bi bi-clock-history"></i> Audit Trail
                </span>
            </div>
            <div class="table-responsive flex-grow-1">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-body-tertiary text-body-secondary fs-3xs text-uppercase fw-semibold border-bottom">
                        <tr>
                            <th class="py-2 px-3 text-center" style="width: 40px;">#</th>
                            <th class="py-2 px-3">Aksi</th>
                            <th class="py-2 px-3">Modul</th>
                            <th class="py-2 px-3">Pengguna</th>
                            <th class="py-2 px-3 text-end">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="fs-xs">
                        <?php if (empty($recentAuditLogs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-journal-x fs-3 text-muted d-block mb-1"></i>
                                    <span>Belum ada catatan aktivitas audit sistem.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($recentAuditLogs, 0, 5) as $idx => $log): ?>
                                <tr>
                                    <td class="px-3 text-center text-body-secondary fs-3xs"><?= $idx + 1 ?></td>
                                    <td class="px-3">
                                        <span class="vui-badge-soft font-monospace fs-3xs">
                                            <?= e($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        <span class="badge bg-body-tertiary text-body border rounded-pill fs-3xs px-2 py-0.5">
                                            <?= e($log['module']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        <span class="fw-semibold d-block fs-xs"><?= e($log['user_name'] ?? 'Sistem') ?></span>
                                    </td>
                                    <td class="px-3 text-end text-body-secondary fs-3xs">
                                        <?= date('d M, H:i', strtotime($log['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
        document.getElementById('sectionAudit').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
