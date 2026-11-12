<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentRole = user_role() ?? 'peserta_didik';
?>
<aside class="app-sidebar text-white flex-shrink-0 d-flex flex-column" id="appSidebar">
    <!-- Brand Sidebar Header -->
    <div class="sidebar-brand-box">
        <a href="<?= base_url() ?>" class="d-flex align-items-center gap-2.5 text-decoration-none min-w-0">
            <div class="sidebar-brand-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="sidebar-brand-text lh-1 text-truncate">
                <span class="fw-bold tracking-tight text-white fs-6 d-block">Smart <span style="color: var(--vui-sidebar-active-text, #60a5fa);">Exam</span></span>
                <span class="fs-3xs text-uppercase fw-semibold" style="color: var(--vui-sidebar-text, #94a3b8); letter-spacing: 0.06em;">CBT Platform</span>
            </div>
        </a>
        <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none p-1 border-0" onclick="App.toggleSidebar()" aria-label="Tutup Navigasi">
            <i class="bi bi-x-lg fs-5"></i>
        </button>
    </div>

    <!-- Navigation Menu Items -->
    <div class="sidebar-nav-container">
        <ul class="nav nav-pills">

            <!-- ========================================== -->
            <!-- ROLE: ADMIN & SUPER ADMIN MENU             -->
            <!-- ========================================== -->
            <?php if (has_role(['super_admin', 'admin'])): ?>
                <li class="nav-header">Utama</li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'admin/dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard Admin</span>
                    </a>
                </li>

                <li class="nav-header">Data Akademik</li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/academic-years') ?>" class="nav-link <?= str_contains($currentPath, 'admin/academic-years') ? 'active' : '' ?>">
                        <i class="bi bi-calendar3"></i>
                        <span>Tahun Ajaran</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/semesters') ?>" class="nav-link <?= str_contains($currentPath, 'admin/semesters') ? 'active' : '' ?>">
                        <i class="bi bi-calendar2-range"></i>
                        <span>Semester</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/classes') ?>" class="nav-link <?= str_contains($currentPath, 'admin/classes') ? 'active' : '' ?>">
                        <i class="bi bi-buildings"></i>
                        <span>Kelas & Rombel</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/subjects') ?>" class="nav-link <?= str_contains($currentPath, 'admin/subjects') ? 'active' : '' ?>">
                        <i class="bi bi-book"></i>
                        <span>Mata Pelajaran</span>
                    </a>
                </li>

                <li class="nav-header">Pengguna & Akses</li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/users') ?>" class="nav-link <?= str_contains($currentPath, 'admin/users') ? 'active' : '' ?>">
                        <i class="bi bi-people"></i>
                        <span>Pengguna</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/roles') ?>" class="nav-link <?= str_contains($currentPath, 'admin/roles') ? 'active' : '' ?>">
                        <i class="bi bi-shield-check"></i>
                        <span>Role & Hak Akses</span>
                    </a>
                </li>

                <li class="nav-header">Manajemen Ujian</li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/exams') ?>" class="nav-link <?= str_contains($currentPath, 'admin/exams') ? 'active' : '' ?>">
                        <i class="bi bi-calendar2-check"></i>
                        <span>Jadwal & Token Ujian</span>
                    </a>
                </li>

                <li class="nav-header">Sistem & AI</li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/system-configuration') ?>" class="nav-link <?= str_contains($currentPath, 'admin/system-configuration') ? 'active' : '' ?>">
                        <i class="bi bi-sliders"></i>
                        <span>Konfigurasi Sistem</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/ai-chat-reports') ?>" class="nav-link <?= (str_contains($currentPath, 'admin/ai-chat-reports') || str_contains($currentPath, 'admin/chat-reports')) ? 'active' : '' ?>">
                        <i class="bi bi-chat-square-text"></i>
                        <span>Laporan Chatbot</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('admin/notifications/compose') ?>" class="nav-link <?= str_contains($currentPath, 'admin/notifications/compose') ? 'active' : '' ?>">
                        <i class="bi bi-send-plus"></i>
                        <span>Kirim Notifikasi</span>
                    </a>
                </li>

            <!-- ========================================== -->
            <!-- ROLE: GURU / PENGAWAS MENU                 -->
            <!-- ========================================== -->
            <?php elseif (has_role('guru')): ?>
                <li class="nav-header">Utama</li>
                <li class="nav-item">
                    <a href="<?= base_url('guru/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'guru/dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard Guru</span>
                    </a>
                </li>

                <li class="nav-header">Ujian & Bank Soal</li>
                <li class="nav-item">
                    <a href="<?= base_url('guru/question-banks') ?>" class="nav-link <?= (str_contains($currentPath, 'guru/question-banks') || str_contains($currentPath, 'guru/questions')) ? 'active' : '' ?>">
                        <i class="bi bi-pencil-square"></i>
                        <span>Bank Soal</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('guru/ai-questions') ?>" class="nav-link <?= str_contains($currentPath, 'guru/ai-questions') ? 'active' : '' ?>">
                        <i class="bi bi-robot"></i>
                        <span class="flex-grow-1">AI Question Gen</span>
                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fs-3xs py-0.5 px-2">AI</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('guru/exams') ?>" class="nav-link <?= (str_contains($currentPath, 'guru/exams') && !str_contains($currentPath, 'monitoring')) ? 'active' : '' ?>">
                        <i class="bi bi-calendar-check"></i>
                        <span>Manajemen Ujian</span>
                    </a>
                </li>

                <li class="nav-header">Evaluasi & Pengawasan</li>
                <li class="nav-item">
                    <a href="<?= base_url('guru/exams/monitoring') ?>" class="nav-link <?= str_contains($currentPath, 'monitoring') ? 'active' : '' ?>">
                        <i class="bi bi-broadcast"></i>
                        <span class="flex-grow-1">Live Monitoring</span>
                        <span class="badge rounded-pill bg-danger text-white fs-3xs py-0.5 px-2 fw-bold">LIVE</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('guru/grading') ?>" class="nav-link <?= str_contains($currentPath, 'guru/grading') ? 'active' : '' ?>">
                        <i class="bi bi-check2-all"></i>
                        <span>Penilaian & Hasil</span>
                    </a>
                </li>

            <!-- ========================================== -->
            <!-- ROLE: PESERTA DIDIK MENU                   -->
            <!-- ========================================== -->
            <?php else: ?>
                <li class="nav-header">Ruang Ujian</li>
                <li class="nav-item">
                    <a href="<?= base_url('siswa/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'siswa/dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-mortarboard-fill"></i>
                        <span>Beranda Siswa</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('siswa/exams') ?>" class="nav-link <?= (str_contains($currentPath, 'siswa/exams') && !str_contains($currentPath, 'siswa/dashboard')) ? 'active' : '' ?>">
                        <i class="bi bi-card-checklist"></i>
                        <span>Daftar Ujian</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('siswa/history') ?>" class="nav-link <?= str_contains($currentPath, 'siswa/history') ? 'active' : '' ?>">
                        <i class="bi bi-clock-history"></i>
                        <span>Riwayat Ujian</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('siswa/results') ?>" class="nav-link <?= str_contains($currentPath, 'siswa/results') ? 'active' : '' ?>">
                        <i class="bi bi-trophy"></i>
                        <span>Hasil & Nilai</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="App.openCameraDiagnostic(); return false;">
                        <i class="bi bi-camera-video"></i>
                        <span>Kalibrasi Kamera</span>
                    </a>
                </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- Sidebar Bottom User Status -->
    <div class="sidebar-footer mt-auto">
        <div class="d-flex align-items-center justify-content-between gap-2 sidebar-user-box">
            <a href="<?= base_url('profile') ?>" class="d-flex align-items-center gap-2 min-w-0 flex-grow-1 text-decoration-none" title="Buka Profil Saya">
                <?php if (!empty(user_avatar())): ?>
                    <img src="<?= e(user_avatar()) ?>" alt="<?= e(user_name()) ?>" class="rounded-circle object-fit-cover flex-shrink-0 border border-secondary" style="width: 34px; height: 34px;">
                <?php else: ?>
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-xs flex-shrink-0" style="width: 34px; height: 34px; background: linear-gradient(135deg, var(--vui-primary, #2563eb) 0%, #3b82f6 100%);">
                        <?= strtoupper(substr(user_name(), 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="sidebar-user-text min-w-0 lh-1">
                    <span class="fw-semibold fs-xs text-white d-block text-truncate"><?= e(user_name()) ?></span>
                    <small class="fs-3xs d-block text-truncate" style="color: var(--vui-sidebar-text, #94a3b8);"><?= role_label() ?></small>
                </div>
            </a>
            <a href="<?= base_url('logout') ?>" class="btn-logout" title="Keluar" aria-label="Keluar">
                <i class="bi bi-box-arrow-right fs-6"></i>
            </a>
        </div>
    </div>
</aside>
