<nav class="navbar navbar-expand-lg navbar-dark bg-dark-gradient sticky-top border-bottom border-secondary-subtle">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url() ?>">
            <div class="brand-icon-box">
                <i class="bi bi-camera-video-fill text-primary"></i>
                <i class="bi bi-robot text-info brand-sub-icon"></i>
            </div>
            <div>
                <span class="fw-bold tracking-tight text-white">Smart <span class="text-primary-gradient">Exam</span></span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs rounded-pill ms-1 px-2 py-0.5">CBT Online</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link active" href="<?= base_url() ?>">
                        <i class="bi bi-house-door me-1"></i> Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url() ?>#portal-roles">
                        <i class="bi bi-people me-1"></i> Akses Portal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url() ?>#workflow">
                        <i class="bi bi-play-circle me-1"></i> Alur Ujian
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url() ?>#features">
                        <i class="bi bi-shield-check me-1"></i> Fitur AI
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url() ?>#system-status">
                        <i class="bi bi-hdd-network me-1"></i> Status Sistem
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-2.5 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty(user_avatar())): ?>
                                <img src="<?= e(user_avatar()) ?>" alt="<?= e(user_name()) ?>" class="rounded-circle object-fit-cover flex-shrink-0 border border-white-50" style="width: 26px; height: 26px;">
                            <?php else: ?>
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-xs flex-shrink-0" style="width: 26px; height: 26px; background: linear-gradient(135deg, var(--vui-primary, #2563eb) 0%, #3b82f6 100%);">
                                    <?= strtoupper(substr(user_name(), 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span class="fs-xs fw-medium text-white"><?= e(user_name()) ?></span>
                            <span class="badge <?= role_badge_class() ?> fs-3xs"><?= role_label() ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-md border border-slate-200 rounded-3 mt-2 p-1.5" style="min-width: 250px;">
                            <!-- A. Informasi Pengguna -->
                            <li class="px-3 py-2 border-bottom mb-1 bg-body-tertiary rounded-2">
                                <div class="d-flex align-items-center gap-2 mb-1.5">
                                    <?php if (!empty(user_avatar())): ?>
                                        <img src="<?= e(user_avatar()) ?>" alt="<?= e(user_name()) ?>" class="rounded-circle object-fit-cover flex-shrink-0 border" style="width: 36px; height: 36px;">
                                    <?php else: ?>
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-sm flex-shrink-0" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--vui-primary, #2563eb) 0%, #3b82f6 100%);">
                                            <?= strtoupper(substr(user_name(), 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="fw-bold text-body d-block fs-xs text-truncate"><?= e(user_name()) ?></span>
                                        <span class="text-body-secondary fs-3xs d-block text-truncate"><?= e(user_email()) ?></span>
                                    </div>
                                </div>
                                <span class="badge <?= role_badge_class() ?> fs-3xs"><?= role_label() ?></span>
                            </li>

                            <!-- B. Kelompok Profil & Tampilan -->
                            <li class="dropdown-header text-uppercase fs-3xs fw-bold px-3 pt-2 pb-1 text-muted tracking-wider">
                                Profil &amp; Tampilan
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="<?= base_url('profile') ?>">
                                    <i class="bi bi-person fs-6 text-muted"></i>
                                    <span>Profil Saya</span>
                                </a>
                            </li>
                            <!-- Pilihan Tema disembunyikan sesuai permintaan -->
                            <li class="d-none theme-switch-item">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="fs-xs fw-medium text-slate-700 d-flex align-items-center gap-2 theme-label">
                                        <i class="bi bi-circle-half fs-6 text-muted"></i>
                                        <span>Tema</span>
                                    </span>
                                    <div class="btn-group btn-group-sm theme-segmented-control" role="group" aria-label="Tema">
                                        <button type="button" 
                                                class="btn btn-xs theme-btn-light <?= theme_mode() === 'light' ? 'btn-primary active shadow-xs' : 'btn-light border text-muted' ?>" 
                                                onclick="App.setTheme('light', event)" 
                                                title="Mode Terang">
                                            <span class="me-1">☀️</span> Terang
                                        </button>
                                        <button type="button" 
                                                class="btn btn-xs theme-btn-dark <?= theme_mode() === 'dark' ? 'btn-primary active shadow-xs' : 'btn-light border text-muted' ?>" 
                                                onclick="App.setTheme('dark', event)" 
                                                title="Mode Gelap">
                                            <span class="me-1">🌙</span> Gelap
                                        </button>
                                    </div>
                                </div>
                            </li>

                            <!-- C. Menu Navigasi -->
                            <li><hr class="dropdown-divider my-1 border-slate-100"></li>
                            <?php if (has_role(['superadmin', 'admin'])): ?>
                                <li>
                                    <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="<?= base_url('admin/dashboard') ?>">
                                        <i class="bi bi-speedometer2 fs-6 text-muted"></i>
                                        <span>Dashboard Admin</span>
                                    </a>
                                </li>
                            <?php elseif (has_role('guru')): ?>
                                <li>
                                    <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="<?= base_url('guru/dashboard') ?>">
                                        <i class="bi bi-speedometer2 fs-6 text-muted"></i>
                                        <span>Dashboard Guru</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li>
                                    <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="<?= base_url('siswa/dashboard') ?>">
                                        <i class="bi bi-speedometer2 fs-6 text-muted"></i>
                                        <span>Ruang Ujian Siswa</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <!-- Uji Coba Kamera disembunyikan sesuai permintaan -->
                            <li class="d-none">
                                <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="#" onclick="App.openCameraDiagnostic(); return false;">
                                    <i class="bi bi-camera-video fs-6 text-muted"></i>
                                    <span>Uji Coba Kamera</span>
                                </a>
                            </li>

                            <!-- D. Menu Keluar -->
                            <li><hr class="dropdown-divider my-1 border-slate-100"></li>
                            <li>
                                <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2 text-danger" href="<?= base_url('logout') ?>">
                                    <i class="bi bi-box-arrow-right fs-6"></i>
                                    <span>Keluar</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-2.5" onclick="App.toggleTheme()" title="Ubah Tema Tampilan">
                        <i class="bi <?= theme_mode() === 'dark' ? 'bi-moon-stars-fill text-info' : 'bi-sun-fill text-warning' ?>"></i>
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="App.checkHealth()">
                        <i class="bi bi-activity text-success me-1"></i> Status API
                    </button>
                    <a href="<?= base_url('login') ?>" class="btn btn-primary-gradient btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
