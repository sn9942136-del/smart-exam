<!-- HERO SECTION (MINIMALIST, ELEGAN, HIGH CONTRAST) -->
<section class="hero-section py-5 bg-white border-bottom position-relative">
    <div class="container py-lg-4">
        <div class="row align-items-center gy-4">
            <!-- Hero Left: Title & Actions -->
            <div class="col-lg-7 text-center text-lg-start">
                <!-- Status Badge -->
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-primary-subtle border border-primary-subtle mb-3 shadow-xs">
                    <span class="badge-dot pulse-success"></span>
                    <span class="fs-xs fw-bold text-primary">Sistem Ujian Online &bull; AI Dual Proctoring</span>
                </div>
                
                <!-- Hero Heading -->
                <h1 class="display-5 fw-extrabold tracking-tight mb-3 text-dark">
                    Platform Ujian Digital Terpadu <br>
                    <span class="text-primary">Berbasis Dual Kamera AI</span>
                </h1>
                
                <!-- Hero Description (Singkat & Padat) -->
                <p class="text-secondary fs-6 mb-4 pe-lg-4 lh-base">
                    Sistem evaluasi akademik terpercaya dengan pengawasan cerdas dua sudut kamera (laptop & smartphone) untuk menjamin integritas dan kejujuran ujian secara real-time.
                </p>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2.5 justify-content-center justify-content-lg-start align-items-center mb-4">
                    <a href="<?= base_url('login') ?>" class="btn btn-primary px-4 py-2.5 rounded-pill shadow-xs fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-right fs-5"></i>
                        <span>Masuk Ruang Ujian</span>
                    </a>
                    <a href="#portal-roles" class="btn btn-outline-secondary px-4 py-2.5 rounded-pill fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-people-fill fs-5"></i>
                        <span>Pilih Akses Portal</span>
                    </a>
                </div>

                <!-- Trust Badges -->
                <div class="d-flex flex-wrap align-items-center gap-2 pt-3 border-top justify-content-center justify-content-lg-start">
                    <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill bg-body-tertiary text-dark fs-xs fw-medium border">
                        <i class="bi bi-shield-check text-success fs-6"></i>
                        <span>Verifikasi Wajah AI</span>
                    </div>
                    <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill bg-body-tertiary text-dark fs-xs fw-medium border">
                        <i class="bi bi-phone text-primary fs-6"></i>
                        <span>Kamera Meja WebRTC</span>
                    </div>
                    <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill bg-body-tertiary text-dark fs-xs fw-medium border">
                        <i class="bi bi-lock-fill text-danger fs-6"></i>
                        <span>Anti Tab-Switch</span>
                    </div>
                </div>
            </div>

            <!-- Hero Right: Dual Camera Simulator Preview -->
            <div class="col-lg-5">
                <div class="card border shadow-sm rounded-4 p-3.5 bg-white">
                    <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-dot pulse-success"></span>
                            <span class="fs-xs fw-bold text-uppercase tracking-wider text-dark">Live Proctoring</span>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs fw-semibold px-2 py-1 rounded-pill">
                            <i class="bi bi-broadcast me-1"></i> Terhubung
                        </span>
                    </div>

                    <!-- Cam 1 Preview -->
                    <div class="rounded-3 mb-2.5 p-3 bg-dark text-white border border-secondary position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-white border-opacity-10">
                            <span class="fs-xs text-white fw-semibold d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-camera-video text-info"></i> Kamera 1: Wajah & Pandangan
                            </span>
                            <span class="badge bg-primary text-white fs-4xs px-2 py-0.5 rounded-pill">99.4% Match</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center py-2 gap-2 text-center">
                            <span class="badge bg-success-subtle text-success fs-3xs py-1 px-2.5 rounded-pill">
                                <i class="bi bi-check2-circle me-1"></i> Wajah Terverifikasi
                            </span>
                            <span class="badge bg-info-subtle text-info fs-3xs py-1 px-2.5 rounded-pill">
                                <i class="bi bi-eye me-1"></i> Fokus Layar
                            </span>
                        </div>
                    </div>

                    <!-- Cam 2 Preview -->
                    <div class="rounded-3 p-3 bg-dark text-white border border-secondary position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-white border-opacity-10">
                            <span class="fs-xs text-white fw-semibold d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-phone text-warning"></i> Kamera 2: Meja & Lingkungan
                            </span>
                            <span class="badge bg-warning text-dark fs-4xs fw-bold px-2 py-0.5 rounded-pill">WebRTC Ready</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-center py-2 text-center">
                            <span class="fs-3xs text-white text-opacity-75 d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-shield-check text-success"></i> Area kerja & meja peserta terpantau aman
                            </span>
                        </div>
                    </div>

                    <!-- Simulator Footer -->
                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center fs-xs text-dark">
                        <div class="d-flex align-items-center gap-1.5">
                            <i class="bi bi-shield-check text-success fs-5"></i>
                            <span>Integritas: <strong class="text-success fw-bold">100/100</strong></span>
                        </div>
                        <span class="badge bg-body-tertiary text-secondary border fs-3xs px-2.5 py-1 rounded-pill">
                            <i class="bi bi-cpu me-1 text-primary"></i> AI Active
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MAIN CONTENT WRAPPER -->
<div class="container my-5">

    <!-- PORTAL AKSES PERAN (RINGKAS & KONTRAS TINGGI) -->
    <section class="mb-5 pt-2" id="portal-roles">
        <div class="text-center mb-4">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill fs-xs fw-semibold mb-2">
                Akses Portal
            </span>
            <h2 class="fw-extrabold tracking-tight mb-2 text-dark">Pilih Hak Akses Pengguna</h2>
            <p class="text-secondary mx-auto max-w-600 fs-6">
                Tiga antarmuka terintegrasi yang aman dan terlindungi proteksi otentikasi.
            </p>
        </div>

        <div class="row g-3 justify-content-center">
            <!-- Siswa Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border shadow-xs p-4 bg-white rounded-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-success-subtle text-success p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="bi bi-mortarboard-fill fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold fs-6 mb-0 text-dark">Peserta Didik</h5>
                                <span class="badge bg-success-subtle text-success fs-3xs">Siswa</span>
                            </div>
                        </div>
                        <p class="text-secondary fs-xs lh-base mb-3">
                            Akses ruang ujian, diagnostik kamera, pengerjaan soal interaktif, dan melihat hasil evaluasi nilai.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="<?= base_url('login') ?>" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-semibold py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Ruang Siswa
                        </a>
                    </div>
                </div>
            </div>

            <!-- Guru Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border shadow-xs p-4 bg-white rounded-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary-subtle text-primary p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="bi bi-person-video3 fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold fs-6 mb-0 text-dark">Guru & Pengawas</h5>
                                <span class="badge bg-primary-subtle text-primary fs-3xs">Tenaga Pendidik</span>
                            </div>
                        </div>
                        <p class="text-secondary fs-xs lh-base mb-3">
                            Kelola bank soal, jadwal ujian, rilis token pengerjaan, pemantauan live kamera, dan penilaian esai.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="<?= base_url('login') ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-semibold py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Portal Guru
                        </a>
                    </div>
                </div>
            </div>

            <!-- Admin Card -->
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 border shadow-xs p-4 bg-white rounded-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-danger-subtle text-danger p-2.5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="bi bi-shield-lock-fill fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold fs-6 mb-0 text-dark">Administrator</h5>
                                <span class="badge bg-danger-subtle text-danger fs-3xs">Sistem & Keamanan</span>
                            </div>
                        </div>
                        <p class="text-secondary fs-xs lh-base mb-3">
                            Pusat kendali pengguna, struktur kelas & mapel, audit aktivitas, dan konfigurasi AI proctoring.
                        </p>
                    </div>
                    <div class="pt-3 border-top">
                        <a href="<?= base_url('login') ?>" class="btn btn-outline-danger btn-sm w-100 rounded-pill fw-semibold py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Dashboard Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ALUR PELAKSANAAN UJIAN (3 LANGKAH RINGKAS) -->
    <section class="mb-5 pt-2" id="workflow">
        <div class="text-center mb-4">
            <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill fs-xs fw-semibold mb-2">
                Alur Ujian
            </span>
            <h2 class="fw-extrabold tracking-tight mb-2 text-dark">3 Langkah Mudah Mengikuti Ujian</h2>
            <p class="text-secondary mx-auto max-w-600 fs-6">
                Proses pengerjaan ujian dirancang ringkas, aman, dan tanpa kendala teknis.
            </p>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100 border shadow-xs p-4 bg-white rounded-4">
                    <div class="d-flex align-items-center gap-3 mb-2.5">
                        <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.875rem;">1</div>
                        <h5 class="fw-bold fs-6 mb-0 text-dark">Login & Verifikasi Akun</h5>
                    </div>
                    <p class="text-secondary fs-xs mb-0 lh-base">
                        Masuk dengan kredensial sekolah untuk memvalidasi jadwal ujian aktif dan memeriksa kesiapan browser.
                    </p>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100 border shadow-xs p-4 bg-white rounded-4">
                    <div class="d-flex align-items-center gap-3 mb-2.5">
                        <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.875rem;">2</div>
                        <h5 class="fw-bold fs-6 mb-0 text-dark">Kalibrasi Dual Kamera</h5>
                    </div>
                    <p class="text-secondary fs-xs mb-0 lh-base">
                        Izinkan kamera depan laptop untuk wajah dan tautkan smartphone via WebRTC untuk memantau area meja.
                    </p>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100 border shadow-xs p-4 bg-white rounded-4">
                    <div class="d-flex align-items-center gap-3 mb-2.5">
                        <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.875rem;">3</div>
                        <h5 class="fw-bold fs-6 mb-0 text-dark">Pengerjaan & Proctoring</h5>
                    </div>
                    <p class="text-secondary fs-xs mb-0 lh-base">
                        Kerjakan soal dengan autosave real-time. Sistem AI menjaga integritas hingga lembar jawaban dikumpulkan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- FITUR UTAMA AI PROCTORING -->
    <section class="mb-5 pt-2" id="features">
        <div class="text-center mb-4">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill fs-xs fw-semibold mb-2">
                Teknologi AI
            </span>
            <h2 class="fw-extrabold tracking-tight mb-2 text-dark">Pengawasan Cerdas & Komprehensif</h2>
            <p class="text-secondary mx-auto max-w-600 fs-6">
                Kombinasi dual kamera dan model AI menghadirkan integritas tinggi dalam evaluasi daring.
            </p>
        </div>

        <div class="row g-3">
            <?php foreach ($features as $f): ?>
                <div class="col-lg-6 col-md-6">
                    <div class="card h-100 border shadow-xs p-3.5 bg-white rounded-4 text-start">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="rounded-3 bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                                <i class="bi <?= $f['icon'] ?> fs-5"></i>
                            </div>
                            <div>
                                <span class="badge bg-primary-subtle text-primary fs-3xs mb-0.5"><?= e($f['badge']) ?></span>
                                <h5 class="fw-bold fs-6 mb-0 text-dark"><?= e($f['title']) ?></h5>
                            </div>
                        </div>
                        <p class="text-secondary fs-xs mb-0 lh-base ps-1">
                            <?= e($f['description']) ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- STATUS OPERASIONAL & KEAMANAN SISTEM (ELEGAN, SEDERHANA & BEBAS CLUTTER) -->
    <section class="mb-4 pt-2" id="system-status">
        <div class="card border shadow-xs rounded-4 bg-white p-3.5 p-lg-4">
            <div class="row align-items-center gy-3">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-2 mb-1.5">
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs fw-semibold">
                            <i class="bi bi-shield-check me-1"></i> Terproteksi
                        </span>
                        <span class="badge bg-body-tertiary text-secondary border fs-3xs">PHP <?= PHP_VERSION ?></span>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark">Status Operasional &amp; Keamanan Sistem</h5>
                    <p class="text-secondary mb-0 fs-xs lh-base">
                        Sistem aktif dengan proteksi keamanan RBAC, enkripsi database, dan pengawasan proctoring siap pakai.
                    </p>
                </div>
                
                <div class="col-lg-6">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2.5 rounded-3 bg-body-tertiary border text-center">
                                <span class="text-secondary fs-3xs d-block mb-1">Koneksi Database</span>
                                <span class="fw-semibold fs-xs <?= $dbConnected ? 'text-success' : 'text-warning' ?>">
                                    <i class="bi bi-circle-fill fs-4xs me-1"></i> <?= $dbConnected ? 'MySQL Terhubung' : 'Standby' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded-3 bg-body-tertiary border text-center">
                                <span class="text-secondary fs-3xs d-block mb-1">AI Vision Engine</span>
                                <span class="fw-semibold fs-xs text-primary">
                                    <i class="bi bi-cpu-fill me-1"></i> Siap Operasi
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden DOM anchors for backward compatibility with JS health check if needed -->
            <div id="apiResponseConsole" class="d-none"></div>
            <span id="apiStatusBadge" class="d-none">Siap</span>
        </div>
    </section>

</div>
