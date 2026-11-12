<!-- Breadcrumbs -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 fs-xs">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if (!empty($crumb['url'])): ?>
                        <li class="breadcrumb-item"><a href="<?= e($crumb['url']) ?>" class="text-decoration-none text-muted"><?= e($crumb['title']) ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active text-slate-800 fw-semibold" aria-current="page"><?= e($crumb['title']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0 text-slate-900 tracking-tight">Profil Saya</h4>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= base_url('profile/edit') ?>" class="btn btn-primary btn-sm rounded-pill px-3.5 py-1.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-xs">
            <i class="bi bi-pencil-square"></i>
            <span>Edit Profil</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Kartu Identitas & Ringkasan Akun -->
    <div class="col-lg-4">
        <div class="card vui-card border border-slate-200 shadow-sm rounded-4 overflow-hidden mb-4">
            <!-- Header Banner Background -->
            <div style="height: 90px; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);"></div>
            
            <div class="card-body p-4 text-center position-relative pt-0">
                <!-- Avatar Profil -->
                <div class="position-relative d-inline-block" style="margin-top: -48px;">
                    <?php if (!empty(user_avatar($user['avatar'] ?? null))): ?>
                        <img src="<?= e(user_avatar($user['avatar'])) ?>" 
                             alt="<?= e($user['name']) ?>" 
                             class="rounded-circle object-fit-cover shadow border border-4 border-white bg-white" 
                             style="width: 96px; height: 96px;">
                    <?php else: ?>
                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-1 shadow border border-4 border-white mx-auto" 
                             style="width: 96px; height: 96px; background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="position-absolute bottom-0 end-0 p-1.5 bg-success border border-white border-2 rounded-circle" title="Akun Aktif">
                        <span class="visually-hidden">Online</span>
                    </span>
                </div>

                <h5 class="fw-bold text-slate-900 mt-3 mb-1"><?= e($user['name']) ?></h5>
                <p class="text-muted fs-xs mb-2 text-truncate"><?= e($user['email']) ?></p>
                
                <div class="d-flex justify-content-center gap-1.5 mb-3">
                    <span class="badge <?= role_badge_class($userRole) ?> px-3 py-1.5 rounded-pill fs-3xs fw-semibold">
                        <?= role_label($userRole) ?>
                    </span>
                    <span class="badge <?= ($user['status'] === 'active') ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?> px-2.5 py-1.5 rounded-pill fs-3xs">
                        <?= ucfirst(e($user['status'])) ?>
                    </span>
                </div>

                <div class="border-top border-slate-100 pt-3 text-start fs-xs">
                    <div class="d-flex justify-content-between py-1.5 border-bottom border-slate-50">
                        <span class="text-muted"><i class="bi bi-person me-1.5"></i>Username</span>
                        <span class="fw-semibold text-slate-800"><?= e($user['username']) ?></span>
                    </div>
                    <?php if (!empty($user['nip_nisn'])): ?>
                        <div class="d-flex justify-content-between py-1.5 border-bottom border-slate-50">
                            <span class="text-muted"><i class="bi bi-card-text me-1.5"></i><?= in_array($userRole, ['peserta_didik', 'siswa'], true) ? 'NISN' : 'NIP' ?></span>
                            <span class="fw-semibold font-monospace text-slate-800"><?= e($user['nip_nisn']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between py-1.5 border-bottom border-slate-50">
                        <span class="text-muted"><i class="bi bi-whatsapp me-1.5 text-success"></i>WhatsApp</span>
                        <span class="fw-semibold font-monospace text-slate-800"><?= !empty($user['phone']) ? e($user['phone']) : '<span class="text-muted fst-italic">Belum diisi</span>' ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1.5">
                        <span class="text-muted"><i class="bi bi-gender-ambiguous me-1.5"></i>Jenis Kelamin</span>
                        <span class="fw-semibold text-slate-800">
                            <?php 
                                if (($user['gender'] ?? '') === 'L') echo 'Laki-laki';
                                elseif (($user['gender'] ?? '') === 'P') echo 'Perempuan';
                                else echo '<span class="text-muted fst-italic">Belum disetel</span>';
                            ?>
                        </span>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="<?= base_url('profile/edit') ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill py-2 fw-semibold">
                        <i class="bi bi-gear-fill me-1"></i> Kelola Profil
                    </a>
                </div>
            </div>
        </div>

        <!-- Kartu Keamanan & Status Akun -->
        <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4">
            <h6 class="fw-bold text-slate-900 mb-3 fs-xs d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary"></i>
                <span>Keamanan & Akses Akun</span>
            </h6>
            <div class="fs-xs text-muted">
                <div class="d-flex justify-content-between py-1.5 border-bottom border-slate-50">
                    <span>ID Pengguna</span>
                    <span class="font-monospace text-slate-800 fw-medium">#<?= (int)$user['id'] ?></span>
                </div>
                <div class="d-flex justify-content-between py-1.5 border-bottom border-slate-50">
                    <span>Terdaftar Sejak</span>
                    <span class="text-slate-800"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1.5 border-bottom border-slate-50">
                    <span>Tema Tampilan</span>
                    <span class="badge <?= is_dark_mode() ? 'bg-dark text-white border border-secondary' : 'bg-light text-dark border border-secondary-subtle' ?> fs-3xs">
                        <i class="bi <?= is_dark_mode() ? 'bi-moon-stars-fill text-info' : 'bi-sun-fill text-warning' ?> me-1"></i>
                        <?= is_dark_mode() ? 'Tema Gelap' : 'Tema Terang' ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between py-1.5">
                    <span>Terakhir Masuk</span>
                    <span class="text-slate-800"><?= !empty($user['last_login_at']) ? date('d M Y H:i', strtotime($user['last_login_at'])) . ' WIB' : 'Belum tercatat' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Detail Informasi Lengkap & Spesifik Peran -->
    <div class="col-lg-8">
        
        <!-- BAGIAN 1: INFORMASI KHUSUS ROLE (AKADEMIK / PEKERJAAN) -->
        <?php if (in_array($userRole, ['peserta_didik', 'siswa'], true)): ?>
            <!-- Detail Akademik Peserta Didik -->
            <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3.5 pb-2 border-bottom border-slate-100">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-mortarboard-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-slate-900 mb-0">Informasi Akademik Peserta Didik</h6>
                            <small class="text-muted fs-3xs">Data rombongan belajar dan status kelas aktif</small>
                        </div>
                    </div>
                    <?php if (!empty($roleDetails['academic'])): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-3xs px-2.5 py-1">
                            <?= ucfirst(e($roleDetails['academic']['status'] ?? 'Aktif')) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($roleDetails['academic'])): $acad = $roleDetails['academic']; ?>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-slate-50 rounded-3 border border-slate-100">
                                <span class="text-muted fs-3xs d-block mb-1">Kelas Saat Ini</span>
                                <span class="fw-bold text-slate-900 fs-sm"><?= e($acad['class_name'] ?? '-') ?></span>
                                <span class="text-muted fs-3xs ms-1">(Tingkat <?= e($acad['grade_level'] ?? '-') ?>)</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-slate-50 rounded-3 border border-slate-100">
                                <span class="text-muted fs-3xs d-block mb-1">Program Studi / Jurusan</span>
                                <span class="fw-bold text-primary fs-sm"><?= e($acad['major'] ?? '-') ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-slate-50 rounded-3 border border-slate-100">
                                <span class="text-muted fs-3xs d-block mb-1">Tahun Akademik</span>
                                <span class="fw-semibold text-slate-800 fs-sm"><?= e($acad['academic_year_name'] ?? '-') ?></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-slate-50 rounded-3 border border-slate-100">
                                <span class="text-muted fs-3xs d-block mb-1">Nomor Absen / Presensi</span>
                                <span class="fw-semibold font-monospace text-slate-800 fs-sm"><?= e($acad['roll_number'] ?? '-') ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted fs-xs">
                        <i class="bi bi-info-circle fs-3 text-slate-300 d-block mb-2"></i>
                        <span>Belum ada data penetapan kelas untuk akun peserta didik ini.</span>
                    </div>
                <?php endif; ?>

                <!-- Statistik Ujian Siswa -->
                <?php if (!empty($roleDetails['exam_stats'])): $stats = $roleDetails['exam_stats']; ?>
                    <div class="mt-4 pt-3 border-top border-slate-100">
                        <h6 class="fw-semibold text-slate-800 fs-xs mb-3">Ringkasan Riwayat Ujian</h6>
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-2.5 rounded-3 bg-light border border-slate-100">
                                    <div class="fs-4 fw-bold text-slate-900"><?= (int)$stats['total_exams'] ?></div>
                                    <span class="text-muted fs-3xs">Ujian Diikuti</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2.5 rounded-3 bg-light border border-slate-100">
                                    <div class="fs-4 fw-bold text-primary"><?= number_format((float)($stats['avg_score'] ?? 0), 1) ?></div>
                                    <span class="text-muted fs-3xs">Rata-rata Nilai</span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2.5 rounded-3 bg-light border border-slate-100">
                                    <div class="fs-4 fw-bold text-success"><?= (int)$stats['passed_exams'] ?></div>
                                    <span class="text-muted fs-3xs">Ujian Lulus</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($userRole === 'guru'): ?>
            <!-- Detail Penugasan & Mata Pelajaran Guru -->
            <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex align-items-center gap-2 mb-3.5 pb-2 border-bottom border-slate-100">
                    <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-briefcase-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-slate-900 mb-0">Informasi Tugas Pengajar</h6>
                        <small class="text-muted fs-3xs">Daftar mata pelajaran diampu dan perwalian kelas</small>
                    </div>
                </div>

                <?php if (!empty($roleDetails['homeroom_class'])): $hr = $roleDetails['homeroom_class']; ?>
                    <div class="alert alert-info border-info-subtle rounded-3 py-2 px-3 mb-3 fs-xs d-flex align-items-center gap-2">
                        <i class="bi bi-star-fill text-warning fs-6"></i>
                        <div>
                            <strong>Wali Kelas:</strong> Ditugaskan sebagai Wali Kelas <strong><?= e($hr['class_name']) ?></strong> (Tingkat <?= e($hr['grade_level']) ?>, <?= e($hr['major']) ?>)
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fs-xs fw-semibold text-slate-700 mb-1.5">Mata Pelajaran yang Diampu:</label>
                    <?php if (!empty($roleDetails['subjects'])): ?>
                        <div class="d-flex flex-wrap gap-1.5">
                            <?php foreach ($roleDetails['subjects'] as $sub): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fs-xs fw-medium">
                                    <i class="bi bi-book me-1"></i><?= e($sub['subject_name']) ?> <span class="font-monospace opacity-75">(<?= e($sub['subject_code']) ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fs-xs mb-0 fst-italic">Belum ada mata pelajaran yang ditugaskan ke akun ini.</p>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label fs-xs fw-semibold text-slate-700 mb-1.5">Kelas yang Diajar:</label>
                    <?php if (!empty($roleDetails['classes'])): ?>
                        <div class="d-flex flex-wrap gap-1.5">
                            <?php foreach ($roleDetails['classes'] as $cls): ?>
                                <span class="badge bg-slate-100 text-slate-800 border border-slate-200 rounded-pill px-2.5 py-1.5 fs-xs">
                                    <i class="bi bi-people me-1 text-muted"></i><?= e($cls['class_name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fs-xs mb-0 fst-italic">Belum ada kelas yang terhubung.</p>
                    <?php endif; ?>
                </div>

                <div class="pt-3 border-top border-slate-100 text-muted fs-xs d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-collection me-1"></i>Bank Soal Dibuat:</span>
                    <span class="fw-bold text-slate-800"><?= (int)($roleDetails['question_banks_count'] ?? 0) ?> Paket Soal</span>
                </div>
            </div>

        <?php else: ?>
            <!-- Detail Peran Administrator -->
            <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex align-items-center gap-2 mb-3.5 pb-2 border-bottom border-slate-100">
                    <div class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-shield-lock-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-slate-900 mb-0">Hak Akses & Wewenang Administrator</h6>
                        <small class="text-muted fs-3xs">Hak istimewa pengawasan sistem Smart Exam</small>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-slate-50 rounded-3 border border-slate-100 h-100">
                            <span class="text-muted fs-3xs d-block mb-1">Tingkat Otoritas</span>
                            <span class="fw-bold text-danger fs-sm"><?= role_label($userRole) ?></span>
                            <p class="text-muted fs-3xs mt-1 mb-0">Memiliki wewenang manajemen pengguna, konfigurasi model AI, dan pengawasan ujian.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-slate-50 rounded-3 border border-slate-100 h-100">
                            <span class="text-muted fs-3xs d-block mb-1">Cakupan Izin Sistem</span>
                            <span class="fw-bold text-slate-900 fs-sm"><?= (int)($roleDetails['permissions_count'] ?? 0) ?> Hak Akses Aktif</span>
                            <p class="text-muted fs-3xs mt-1 mb-0">Termasuk hak audit notifikasi, backup data, dan manajemen jadwal ujian.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- BAGIAN 2: RINCIAN KONTAK & IDENTITAS PENGGUNA -->
        <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4">
            <h6 class="fw-bold text-slate-900 mb-3 pb-2 border-bottom border-slate-100 fs-xs d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-primary"></i>
                <span>Informasi Kontak & Akun</span>
            </h6>

            <div class="row g-3 fs-xs">
                <div class="col-md-6">
                    <label class="form-label text-muted fs-3xs mb-1">Alamat Email Terdaftar</label>
                    <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-3 d-flex align-items-center justify-content-between">
                        <span class="font-monospace text-slate-800 text-truncate me-2"><?= e($user['email']) ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs flex-shrink-0">
                            <i class="bi bi-check-circle me-1"></i>Terverifikasi
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted fs-3xs mb-1">Nomor WhatsApp Notifikasi OTP</label>
                    <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-3 d-flex align-items-center justify-content-between">
                        <span class="font-monospace text-slate-800"><?= !empty($user['phone']) ? e($user['phone']) : '-' ?></span>
                        <?php if (!empty($user['phone'])): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs flex-shrink-0">
                                <i class="bi bi-whatsapp me-1"></i>Tersambung Fonnte
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary fs-3xs flex-shrink-0">Kosong</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted fs-3xs mb-1">Nama Pengguna (Username)</label>
                    <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-3">
                        <span class="text-slate-800 fw-medium"><?= e($user['username']) ?></span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-muted fs-3xs mb-1"><?= in_array($userRole, ['peserta_didik', 'siswa'], true) ? 'Nomor Induk Siswa Nasional (NISN)' : 'Nomor Induk Pegawai (NIP)' ?></label>
                    <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-3">
                        <span class="font-monospace text-slate-800 fw-medium"><?= !empty($user['nip_nisn']) ? e($user['nip_nisn']) : '<span class="text-muted fst-italic">Belum tercatat</span>' ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top border-slate-100 d-flex justify-content-between align-items-center">
                <span class="text-muted fs-3xs">Ingin memperbarui nama atau foto profil Anda?</span>
                <a href="<?= base_url('profile/edit') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 fs-xs fw-semibold">
                    <i class="bi bi-pencil-square me-1"></i> Edit Profil
                </a>
            </div>
        </div>

    </div>
</div>

