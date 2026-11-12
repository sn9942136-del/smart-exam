<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Environmental Status -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-send-plus text-primary"></i>
            <span>Kirim Notifikasi Multi-Channel</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Kirim notifikasi langsung ke pengguna terdaftar dengan fleksibilitas mengedit alamat email dan nomor WhatsApp sebelum dikirim.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="<?= base_url('admin/notification-logs') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5 shadow-xs d-inline-flex align-items-center gap-1.5 fs-xs">
            <i class="bi bi-clock-history"></i> Riwayat Log
        </a>
        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle py-1.5 px-3 fs-3xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-whatsapp"></i> WA: <?= e(ucfirst($notificationMode ?? 'simulation')) ?>
        </span>
        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle py-1.5 px-3 fs-3xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-envelope"></i> SMTP: <?= e(ucfirst($notificationMode ?? 'simulation')) ?>
        </span>
    </div>
</div>

<!-- Main Form & Live Preview Grid -->
<div class="row g-4">
    <!-- Kolom Kiri: Formulir Pengiriman (7 Cols) -->
    <div class="col-lg-7">
        <form action="<?= base_url('admin/notifications/send-custom') ?>" method="POST" id="customNotificationForm" novalidate>
            <?= csrf_field() ?>

            <!-- Card 1: Penerima Terdaftar & Kontak yang Dapat Diedit -->
            <div class="card border-0 shadow-xs rounded-4 bg-white p-3 p-md-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-slate-100 pb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <h6 class="fw-bold text-slate-900 mb-0 fs-sm">1. Target Pengguna & Kontak Penerima</h6>
                    </div>
                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-3xs fw-normal">Wajib Terdaftar</span>
                </div>

                <!-- Dropdown Pemilihan Pengguna Terdaftar -->
                <div class="mb-3">
                    <label for="userSelect" class="form-label text-slate-700 fw-semibold fs-xs mb-1">
                        Pilih Pengguna Terdaftar <span class="text-danger">*</span>
                    </label>
                    <select name="user_id" id="userSelect" class="form-select border-slate-200 fs-xs rounded-3" required>
                        <option value="">-- Pilih Pengguna Terdaftar (Siswa, Guru, Admin) --</option>
                        <?php foreach ($users as $u): ?>
                            <?php
                                $roleBadge = match ($u['role_code']) {
                                    'super_admin', 'admin' => '[Admin]',
                                    'guru'                  => '[Guru]',
                                    default                 => '[Siswa]',
                                };
                                $isSelected = (isset($preselectedUserId) && (int)$preselectedUserId === (int)$u['id']);
                            ?>
                            <option value="<?= (int)$u['id'] ?>"
                                    data-name="<?= e($u['name']) ?>"
                                    data-role="<?= e($u['role_code']) ?>"
                                    data-role-label="<?= e($u['role_name']) ?>"
                                    data-email="<?= e($u['email'] ?? '') ?>"
                                    data-phone="<?= e($u['phone'] ?? '') ?>"
                                    data-username="<?= e($u['username'] ?? $u['nip_nisn'] ?? '') ?>"
                                    <?= $isSelected ? 'selected' : '' ?>>
                                <?= e($roleBadge . ' ' . $u['name'] . ' (' . ($u['email'] ?: 'Tanpa Email') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text fs-3xs text-muted mt-1">
                        Memilih pengguna akan otomatis memuat alamat email dan nomor WhatsApp resmi yang tersimpan di sistem.
                    </div>
                </div>

                <!-- Kartu Informasi Ringkas Pengguna Terpilih -->
                <div id="selectedUserCard" class="card border border-slate-200 bg-slate-50 rounded-3 p-2.5 mb-3 d-none">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center fs-xs shadow-2xs" id="userAvatarInitial" style="width: 36px; height: 36px;">
                                U
                            </div>
                            <div>
                                <h6 class="fw-bold text-slate-900 mb-0 fs-xs" id="displayUserName">Nama Pengguna</h6>
                                <span class="text-muted fs-3xs" id="displayUserMeta">Username / NIP / NISN</span>
                            </div>
                        </div>
                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fs-3xs" id="displayUserRoleBadge">
                            Siswa
                        </span>
                    </div>
                </div>

                <!-- Kontak yang Dapat Diedit Mandiri (Fitur Inti Permintaan User) -->
                <div class="row g-3">
                    <!-- Input Email yang Dapat Diedit -->
                    <div class="col-md-6">
                        <label for="recipientEmail" class="form-label text-slate-700 fw-semibold fs-xs mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-envelope text-primary me-1"></i> Alamat Email Penerima</span>
                            <span class="badge bg-slate-100 text-slate-500 fs-3xs fw-normal">Dapat Diedit</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-slate-200 text-muted">
                                <i class="bi bi-at"></i>
                            </span>
                            <input type="email" name="recipient_email" id="recipientEmail" class="form-control border-slate-200 fs-xs" placeholder="nama@email.com">
                        </div>
                        <div class="form-text fs-3xs text-muted mt-1">
                            Digunakan untuk pengiriman notifikasi via SMTP Email.
                        </div>
                    </div>

                    <!-- Input Nomor WhatsApp yang Dapat Diedit -->
                    <div class="col-md-6">
                        <label for="recipientPhone" class="form-label text-slate-700 fw-semibold fs-xs mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-whatsapp text-success me-1"></i> Nomor WhatsApp Penerima</span>
                            <span class="badge bg-slate-100 text-slate-500 fs-3xs fw-normal">Dapat Diedit</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-slate-200 text-muted font-monospace fs-xs">
                                +62
                            </span>
                            <input type="text" name="recipient_phone" id="recipientPhone" class="form-control border-slate-200 fs-xs font-monospace" placeholder="08xxxxxxxxxx atau 628xxxx">
                        </div>
                        <div class="form-text fs-3xs text-muted mt-1">
                            Otomatis disesuaikan ke format standar E.164.
                        </div>
                    </div>
                </div>

                <!-- Opsi Sinkronisasi Perubahan ke Database Profil Akun -->
                <div class="mt-3 pt-3 border-top border-slate-100">
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input mt-0" type="checkbox" role="switch" name="update_profile" value="1" id="updateProfileCheck">
                        <label class="form-check-label fs-xs text-slate-700 fw-medium" for="updateProfileCheck">
                            Simpan perubahan email & nomor WhatsApp ini secara permanen ke profil akun pengguna
                        </label>
                    </div>
                    <div class="form-text fs-3xs text-muted ms-4 ps-1 mt-0.5">
                        Jika tidak dicentang, perubahan di atas hanya berlaku sekali untuk pengiriman notifikasi ini saja tanpa mengubah database pengguna.
                    </div>
                </div>
            </div>

            <!-- Card 2: Saluran Pengiriman (Multi-Channel Selector) -->
            <div class="card border-0 shadow-xs rounded-4 bg-white p-3 p-md-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-slate-100 pb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-success-subtle text-success p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-broadcast-pin"></i>
                        </div>
                        <h6 class="fw-bold text-slate-900 mb-0 fs-sm">2. Pilih Saluran Pengiriman</h6>
                    </div>
                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-3xs fw-normal">Minimal 1 Saluran</span>
                </div>

                <div class="row g-2.5">
                    <!-- Saluran: In-App -->
                    <div class="col-md-4">
                        <label class="channel-card d-block p-3 rounded-3 border border-slate-200 cursor-pointer transition-all position-relative h-100 bg-white" for="channelInApp">
                            <div class="form-check p-0 m-0 d-flex flex-column h-100 justify-content-between">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="bi bi-bell-fill"></i>
                                    </div>
                                    <input class="form-check-input channel-checkbox" type="checkbox" name="channels[]" value="in_app" id="channelInApp" checked>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-slate-900 fs-xs mb-0.5">In-App Web</h6>
                                    <p class="text-muted fs-3xs mb-0">Lonceng navbar & kotak masuk web aplikasi.</p>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Saluran: WhatsApp -->
                    <div class="col-md-4">
                        <label class="channel-card d-block p-3 rounded-3 border border-slate-200 cursor-pointer transition-all position-relative h-100 bg-white" for="channelWhatsApp">
                            <div class="form-check p-0 m-0 d-flex flex-column h-100 justify-content-between">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="rounded-circle bg-success-subtle text-success p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="bi bi-whatsapp"></i>
                                    </div>
                                    <input class="form-check-input channel-checkbox" type="checkbox" name="channels[]" value="whatsapp" id="channelWhatsApp" checked>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-slate-900 fs-xs mb-0.5">WhatsApp</h6>
                                    <p class="text-muted fs-3xs mb-0">Kirim pesan langsung ke WhatsApp resmi.</p>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Saluran: Email -->
                    <div class="col-md-4">
                        <label class="channel-card d-block p-3 rounded-3 border border-slate-200 cursor-pointer transition-all position-relative h-100 bg-white" for="channelEmail">
                            <div class="form-check p-0 m-0 d-flex flex-column h-100 justify-content-between">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="rounded-circle bg-info-subtle text-info p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="bi bi-envelope-at"></i>
                                    </div>
                                    <input class="form-check-input channel-checkbox" type="checkbox" name="channels[]" value="email" id="channelEmail" checked>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-slate-900 fs-xs mb-0.5">Email SMTP</h6>
                                    <p class="text-muted fs-3xs mb-0">Surat elektronik formal via Gmail SMTP.</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Card 3: Konten & Tipe Pesan Notifikasi -->
            <div class="card border-0 shadow-xs rounded-4 bg-white p-3 p-md-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-slate-100 pb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-warning-subtle text-warning p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-card-text"></i>
                        </div>
                        <h6 class="fw-bold text-slate-900 mb-0 fs-sm">3. Konten & Format Notifikasi</h6>
                    </div>
                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-3xs fw-normal">Live Sync</span>
                </div>

                <!-- Tipe / Prioritas Notifikasi -->
                <div class="mb-3">
                    <label class="form-label text-slate-700 fw-semibold fs-xs mb-1.5">Tingkat Prioritas / Tipe</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check form-check-inline m-0">
                            <input class="btn-check" type="radio" name="type" id="typeInfo" value="info" checked>
                            <label class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fs-xs d-flex align-items-center gap-1.5" for="typeInfo">
                                <i class="bi bi-info-circle"></i> Informasi
                            </label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input class="btn-check" type="radio" name="type" id="typeSuccess" value="success">
                            <label class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fs-xs d-flex align-items-center gap-1.5" for="typeSuccess">
                                <i class="bi bi-check-circle"></i> Sukses
                            </label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input class="btn-check" type="radio" name="type" id="typeWarning" value="warning">
                            <label class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1 fs-xs d-flex align-items-center gap-1.5" for="typeWarning">
                                <i class="bi bi-exclamation-triangle"></i> Peringatan
                            </label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input class="btn-check" type="radio" name="type" id="typeDanger" value="danger">
                            <label class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fs-xs d-flex align-items-center gap-1.5" for="typeDanger">
                                <i class="bi bi-shield-exclamation"></i> Penting / Bahaya
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Judul Notifikasi -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="notifTitle" class="form-label text-slate-700 fw-semibold fs-xs mb-0">
                            Judul Notifikasi <span class="text-danger">*</span>
                        </label>
                        <span class="fs-3xs text-muted" id="titleCharCounter">0 / 150</span>
                    </div>
                    <input type="text" name="title" id="notifTitle" class="form-control border-slate-200 fs-xs rounded-3" placeholder="Contoh: Jadwal Ujian Tengah Semester Telah Diterbitkan" maxlength="150" required value="Informasi Ujian Smart Exam">
                </div>

                <!-- Isi Pesan Notifikasi -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="notifMessage" class="form-label text-slate-700 fw-semibold fs-xs mb-0">
                            Isi Pesan Notifikasi <span class="text-danger">*</span>
                        </label>
                        <span class="fs-3xs text-muted" id="messageCharCounter">0 / 2000</span>
                    </div>
                    <textarea name="message" id="notifMessage" rows="5" class="form-control border-slate-200 fs-xs rounded-3" placeholder="Tuliskan isi pesan notifikasi di sini. Anda dapat menggunakan format *tebal* untuk penekanan teks penting." maxlength="2000" required>Halo, harap periksa jadwal pelaksanaan ujian Anda di portal Smart Exam dan pastikan perangkat kamera dalam kondisi siap.</textarea>
                    <div class="form-text fs-3xs text-muted mt-1">
                        Tips: Gunakan tanda bintang seperti <code>*penting*</code> untuk menebalkan kata di pesan WhatsApp.
                    </div>
                </div>

                <!-- Tautan Aksi Opsional -->
                <div class="mb-2">
                    <label for="notifActionUrl" class="form-label text-slate-700 fw-semibold fs-xs mb-1 d-flex justify-content-between">
                        <span><i class="bi bi-link-45deg me-1"></i> Tautan Aksi (Action URL)</span>
                        <span class="badge bg-slate-100 text-slate-500 fs-3xs fw-normal">Opsional</span>
                    </label>
                    <input type="text" name="action_url" id="notifActionUrl" class="form-control border-slate-200 fs-xs rounded-3 font-monospace" placeholder="/siswa/exams atau https://..." value="/siswa/exams">
                    <div class="d-flex flex-wrap gap-1.5 mt-1.5">
                        <button type="button" class="btn btn-xs btn-outline-slate rounded-pill px-2 py-0.5 fs-3xs quick-url-btn" data-url="/siswa/exams">
                            <i class="bi bi-mortarboard me-1"></i>/siswa/exams
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-slate rounded-pill px-2 py-0.5 fs-3xs quick-url-btn" data-url="/guru/dashboard">
                            <i class="bi bi-speedometer2 me-1"></i>/guru/dashboard
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-slate rounded-pill px-2 py-0.5 fs-3xs quick-url-btn" data-url="/admin/exams">
                            <i class="bi bi-calendar-check me-1"></i>/admin/exams
                        </button>
                    </div>
                </div>

                <!-- Guardrail Proteksi Token Siswa (Alert Informatif) -->
                <div id="studentSecurityBadge" class="alert alert-warning border border-warning-subtle rounded-3 py-2 px-3 mt-3 mb-0 fs-3xs text-warning-emphasis d-flex align-items-center gap-2 d-none">
                    <i class="bi bi-shield-lock-fill fs-5"></i>
                    <div>
                        <strong>Proteksi Privasi Siswa:</strong> Pengguna ini berstatus <em>Siswa / Peserta Didik</em>. Sistem keamanan Smart Exam akan otomatis menyensor token ujian riil menjadi <code>[TERPROTEKSI]</code> untuk mencegah kebocoran soal.
                    </div>
                </div>
            </div>

            <!-- Card 4: Tombol Aksi Pengiriman -->
            <div class="d-flex align-items-center justify-content-between gap-2 pt-2">
                <a href="<?= base_url('admin/notification-logs') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 py-2 fs-xs">
                    <i class="bi bi-arrow-left me-1"></i> Batal / Kembali
                </a>
                <div class="d-flex align-items-center gap-2">
                    <button type="reset" id="btnResetForm" class="btn btn-sm btn-light border border-slate-200 rounded-pill px-3 py-2 fs-xs text-muted">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                    <button type="submit" id="btnSubmitNotification" class="btn btn-sm btn-primary rounded-pill px-4 py-2 fw-semibold shadow-xs fs-xs d-inline-flex align-items-center gap-2">
                        <i class="bi bi-send-fill"></i>
                        <span>Kirim Notifikasi Sekarang</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Kolom Kanan: Pratinjau Interaktif Real-time Multi-Channel (5 Cols) -->
    <div class="col-lg-5">
        <div class="sticky-top" style="top: 1.5rem; z-index: 10;">
            <!-- Header Tab Pratinjau -->
            <div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white border-bottom border-slate-100 p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-indigo-subtle text-primary p-1.5 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                <i class="bi bi-eye"></i>
                            </div>
                            <h6 class="fw-bold text-slate-900 mb-0 fs-xs">Pratinjau Multi-Channel Langsung</h6>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs">
                            <i class="bi bi-lightning-charge-fill me-0.5"></i> Interaktif
                        </span>
                    </div>

                    <!-- Nav Tabs Saluran -->
                    <ul class="nav nav-pills nav-fill gap-1 bg-slate-50 p-1 rounded-3" id="previewTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-2 fs-3xs rounded-2 fw-medium d-flex align-items-center justify-content-center gap-1" id="preview-wa-tab" data-bs-toggle="pill" data-bs-target="#preview-wa" type="button" role="tab">
                                <i class="bi bi-whatsapp text-success"></i> WhatsApp
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 fs-3xs rounded-2 fw-medium d-flex align-items-center justify-content-center gap-1" id="preview-email-tab" data-bs-toggle="pill" data-bs-target="#preview-email" type="button" role="tab">
                                <i class="bi bi-envelope-at text-primary"></i> Email
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 fs-3xs rounded-2 fw-medium d-flex align-items-center justify-content-center gap-1" id="preview-inapp-tab" data-bs-toggle="pill" data-bs-target="#preview-inapp" type="button" role="tab">
                                <i class="bi bi-bell text-warning"></i> In-App
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Konten Tab Pratinjau -->
                <div class="card-body p-3">
                    <div class="tab-content" id="previewTabContent">
                        <!-- TAB 1: WhatsApp Smartphone Bubble Preview -->
                        <div class="tab-pane fade show active" id="preview-wa" role="tabpanel">
                            <div class="whatsapp-phone-mockup rounded-4 overflow-hidden border border-slate-200 shadow-2xs" style="background-color: #efeae2;">
                                <!-- WA Bar Header -->
                                <div class="p-2.5 d-flex align-items-center justify-content-between text-white" style="background-color: #075e54;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center fw-bold fs-3xs" style="width: 30px; height: 30px;">
                                            <i class="bi bi-whatsapp"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold fs-xs lh-1 mb-0.5">Smart Exam Gateway</div>
                                            <div class="fs-3xs opacity-75" id="previewWaPhone">+62 8xxxxxxxxxx</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-white-50 fs-xs">
                                        <i class="bi bi-telephone"></i>
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </div>
                                </div>

                                <!-- WA Chat Bubble -->
                                <div class="p-3" style="min-height: 250px;">
                                    <div class="bg-white rounded-3 p-3 shadow-2xs position-relative ms-auto" style="max-width: 90%; border-top-right-radius: 2px !important;">
                                        <!-- Header Label -->
                                        <div class="d-flex align-items-center justify-content-between border-bottom border-slate-100 pb-1.5 mb-2">
                                            <span class="badge bg-success-subtle text-success fs-3xs fw-semibold">
                                                <i class="bi bi-patch-check-fill me-1"></i>Notifikasi Resmi
                                            </span>
                                            <span class="fs-3xs text-muted" id="previewWaType">Info</span>
                                        </div>

                                        <!-- Title -->
                                        <div class="fw-bold text-slate-900 fs-xs mb-1" id="previewWaTitle">
                                            *Informasi Ujian Smart Exam*
                                        </div>

                                        <!-- Greeting -->
                                        <div class="fs-3xs text-muted mb-1.5" id="previewWaGreeting">
                                            Yth. Pengguna
                                        </div>

                                        <!-- Message Body -->
                                        <div class="text-slate-700 fs-xs mb-2 text-break" style="white-space: pre-line;" id="previewWaMessage">
                                            Halo, harap periksa jadwal pelaksanaan ujian Anda di portal Smart Exam dan pastikan perangkat kamera dalam kondisi siap.
                                        </div>

                                        <!-- Action Link Button -->
                                        <div id="previewWaActionWrap" class="pt-2 border-top border-slate-100">
                                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success w-100 rounded-pill py-1 fs-3xs d-flex align-items-center justify-content-center gap-1 text-decoration-none">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                                <span id="previewWaActionText">Buka Tautan Terkait</span>
                                            </a>
                                        </div>

                                        <!-- Timestamp & Read Receipts -->
                                        <div class="d-flex align-items-center justify-content-end gap-1 mt-2 text-muted fs-3xs">
                                            <span id="previewWaTime"><?= date('H:i') ?> WIB</span>
                                            <i class="bi bi-check2-all text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: Email Client Card Preview -->
                        <div class="tab-pane fade" id="preview-email" role="tabpanel">
                            <div class="email-mockup rounded-4 overflow-hidden border border-slate-200 shadow-2xs bg-white">
                                <!-- Email Top Info -->
                                <div class="bg-slate-50 border-bottom border-slate-200 p-2.5 fs-3xs text-slate-600">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="fw-bold me-2 text-slate-800" style="width: 48px;">Dari:</span>
                                        <span class="text-truncate">Smart Exam Notification &lt;noreply@sekolah.sch.id&gt;</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="fw-bold me-2 text-slate-800" style="width: 48px;">Kepada:</span>
                                        <span class="text-truncate font-monospace text-primary fw-medium" id="previewEmailRecipient">penerima@email.com</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold me-2 text-slate-800" style="width: 48px;">Subjek:</span>
                                        <span class="text-truncate fw-semibold text-slate-900" id="previewEmailSubject">Informasi Ujian Smart Exam</span>
                                    </div>
                                </div>

                                <!-- Email Body Layout Mockup -->
                                <div class="p-3 bg-white" style="min-height: 250px;">
                                    <!-- Email Header Banner -->
                                    <div class="rounded-3 p-3 text-white mb-3 text-center" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                                        <div class="fs-xs fw-bold text-uppercase tracking-wider opacity-75 mb-0.5">SMART EXAM</div>
                                        <h6 class="fw-bold mb-0 fs-sm" id="previewEmailCardTitle">Informasi Ujian Smart Exam</h6>
                                    </div>

                                    <!-- Greeting -->
                                    <p class="fs-xs text-slate-800 mb-2">
                                        Halo, <strong id="previewEmailName">Pengguna</strong>
                                    </p>

                                    <!-- Message Card -->
                                    <div class="p-2.5 rounded-3 bg-slate-50 border border-slate-200 mb-3 fs-xs text-slate-700" style="white-space: pre-line;" id="previewEmailBody">
                                        Halo, harap periksa jadwal pelaksanaan ujian Anda di portal Smart Exam dan pastikan perangkat kamera dalam kondisi siap.
                                    </div>

                                    <!-- Action Button -->
                                    <div id="previewEmailActionWrap" class="text-center my-3">
                                        <a href="javascript:void(0)" class="btn btn-sm btn-primary rounded-pill px-4 py-1.5 fs-xs fw-semibold shadow-xs">
                                            Buka Halaman Ujian
                                        </a>
                                    </div>

                                    <!-- Security Footer -->
                                    <div class="border-top border-slate-100 pt-2 text-center text-muted fs-3xs">
                                        Pemberitahuan otomatis oleh sistem Smart Exam &bull; Jangan membalas email ini.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: In-App Dropdown Card Preview -->
                        <div class="tab-pane fade" id="preview-inapp" role="tabpanel">
                            <div class="inapp-mockup rounded-4 border border-slate-200 shadow-2xs bg-white p-3">
                                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-slate-100 pb-2">
                                    <span class="fw-bold fs-xs text-slate-900">Simulasi Notifikasi Dropdown Navbar</span>
                                    <span class="badge bg-danger rounded-pill fs-3xs">1 Baru</span>
                                </div>

                                <div class="d-flex align-items-start gap-2.5 p-2.5 rounded-3 bg-primary-subtle border border-primary-subtle">
                                    <div class="rounded-circle bg-primary text-white p-2 d-flex align-items-center justify-content-center shadow-2xs" id="previewInAppIconWrap" style="width: 32px; height: 32px;">
                                        <i class="bi bi-info-circle fs-xs" id="previewInAppIcon"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between mb-0.5">
                                            <h6 class="fw-bold text-slate-900 fs-xs mb-0" id="previewInAppTitle">Informasi Ujian Smart Exam</h6>
                                            <span class="badge rounded-pill bg-primary p-1" style="width: 6px; height: 6px;"></span>
                                        </div>
                                        <p class="text-slate-600 fs-3xs mb-1 text-truncate-2" id="previewInAppMessage">
                                            Halo, harap periksa jadwal pelaksanaan ujian Anda di portal Smart Exam...
                                        </p>
                                        <div class="d-flex align-items-center justify-content-between text-muted fs-3xs">
                                            <span>Baru saja &bull; Hari ini</span>
                                            <span class="text-primary fw-medium" id="previewInAppLink">Buka Tautan &rarr;</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Page Styling -->
<style>
.channel-card {
    transition: all 0.2s ease-in-out;
}
.channel-card:hover {
    border-color: #4f46e5 !important;
    background-color: #f8fafc !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.08);
}
.channel-card.active-channel {
    border-color: #4f46e5 !important;
    background-color: #f5f3ff !important;
}
.cursor-pointer {
    cursor: pointer;
}
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

<!-- Live Synchronous Interaction Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const userSelect = document.getElementById('userSelect');
    const recipientEmail = document.getElementById('recipientEmail');
    const recipientPhone = document.getElementById('recipientPhone');
    const notifTitle = document.getElementById('notifTitle');
    const notifMessage = document.getElementById('notifMessage');
    const notifActionUrl = document.getElementById('notifActionUrl');
    const titleCharCounter = document.getElementById('titleCharCounter');
    const messageCharCounter = document.getElementById('messageCharCounter');
    const studentSecurityBadge = document.getElementById('studentSecurityBadge');

    // Preview elements
    const selectedUserCard = document.getElementById('selectedUserCard');
    const userAvatarInitial = document.getElementById('userAvatarInitial');
    const displayUserName = document.getElementById('displayUserName');
    const displayUserMeta = document.getElementById('displayUserMeta');
    const displayUserRoleBadge = document.getElementById('displayUserRoleBadge');

    const previewWaPhone = document.getElementById('previewWaPhone');
    const previewWaTitle = document.getElementById('previewWaTitle');
    const previewWaGreeting = document.getElementById('previewWaGreeting');
    const previewWaMessage = document.getElementById('previewWaMessage');
    const previewWaType = document.getElementById('previewWaType');
    const previewWaActionWrap = document.getElementById('previewWaActionWrap');
    const previewWaActionText = document.getElementById('previewWaActionText');

    const previewEmailRecipient = document.getElementById('previewEmailRecipient');
    const previewEmailSubject = document.getElementById('previewEmailSubject');
    const previewEmailCardTitle = document.getElementById('previewEmailCardTitle');
    const previewEmailName = document.getElementById('previewEmailName');
    const previewEmailBody = document.getElementById('previewEmailBody');
    const previewEmailActionWrap = document.getElementById('previewEmailActionWrap');

    const previewInAppTitle = document.getElementById('previewInAppTitle');
    const previewInAppMessage = document.getElementById('previewInAppMessage');
    const previewInAppLink = document.getElementById('previewInAppLink');
    const previewInAppIcon = document.getElementById('previewInAppIcon');
    const previewInAppIconWrap = document.getElementById('previewInAppIconWrap');

    // Handler Saluran Checkbox
    const channelCards = document.querySelectorAll('.channel-card');
    channelCards.forEach(function (card) {
        const checkbox = card.querySelector('.channel-checkbox');
        if (checkbox) {
            if (checkbox.checked) {
                card.classList.add('active-channel');
            }
            checkbox.addEventListener('change', function () {
                if (this.checked) {
                    card.classList.add('active-channel');
                } else {
                    card.classList.remove('active-channel');
                }
            });
        }
    });

    // Helper sanitasi teks preview
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return (text || '').replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // Normalisasi preview nomor HP
    function formatPhoneDisplay(raw) {
        const clean = (raw || '').replace(/[^0-9]/g, '');
        if (!clean) return '+62 8xxxxxxxxxx';
        if (clean.startsWith('62')) return '+' + clean;
        if (clean.startsWith('0')) return '+62 ' + clean.substring(1);
        return '+' + clean;
    }

    // Sinkronisasi Pemilihan Pengguna
    function updateSelectedUser() {
        const selectedOpt = userSelect.options[userSelect.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) {
            selectedUserCard.classList.add('d-none');
            studentSecurityBadge.classList.add('d-none');
            previewWaGreeting.textContent = 'Yth. Pengguna';
            previewEmailName.textContent = 'Pengguna';
            return;
        }

        const name = selectedOpt.dataset.name || 'Pengguna';
        const role = selectedOpt.dataset.role || 'peserta_didik';
        const roleLabel = selectedOpt.dataset.roleLabel || 'Pengguna';
        const email = selectedOpt.dataset.email || '';
        const phone = selectedOpt.dataset.phone || '';
        const username = selectedOpt.dataset.username || '';

        // Tampilkan info kartu pengguna
        selectedUserCard.classList.remove('d-none');
        userAvatarInitial.textContent = name.charAt(0).toUpperCase();
        displayUserName.textContent = name;
        displayUserMeta.textContent = (username ? '@' + username + ' &bull; ' : '') + 'ID #' + selectedOpt.value;
        displayUserRoleBadge.textContent = roleLabel;

        // Auto-populate field yang DAPAT DIEDIT sendiri oleh admin
        recipientEmail.value = email;
        recipientPhone.value = phone;

        // Update preview
        previewWaGreeting.textContent = 'Yth. ' + name;
        previewEmailName.textContent = name;
        previewEmailRecipient.textContent = email || 'Belum diisi';
        previewWaPhone.textContent = formatPhoneDisplay(phone);

        // Jika peran adalah siswa, tampilkan guardrail token
        if (role === 'peserta_didik' || role === 'siswa') {
            studentSecurityBadge.classList.remove('d-none');
        } else {
            studentSecurityBadge.classList.add('d-none');
        }
    }

    // Sinkronisasi Konten Pesan Real-time
    function updatePreviewContent() {
        const titleVal = notifTitle.value.trim() || 'Judul Notifikasi';
        const msgVal = notifMessage.value.trim() || 'Isi notifikasi akan tampil di sini...';
        const actionVal = notifActionUrl.value.trim();

        // Counter
        titleCharCounter.textContent = notifTitle.value.length + ' / 150';
        messageCharCounter.textContent = notifMessage.value.length + ' / 2000';

        // WhatsApp Preview
        previewWaTitle.textContent = '*' + titleVal + '*';
        previewWaMessage.textContent = msgVal;
        previewWaPhone.textContent = formatPhoneDisplay(recipientPhone.value);
        if (actionVal) {
            previewWaActionWrap.classList.remove('d-none');
            previewWaActionText.textContent = 'Buka Tautan: ' + actionVal;
        } else {
            previewWaActionWrap.classList.add('d-none');
        }

        // Email Preview
        previewEmailRecipient.textContent = recipientEmail.value.trim() || 'penerima@email.com';
        previewEmailSubject.textContent = titleVal;
        previewEmailCardTitle.textContent = titleVal;
        previewEmailBody.textContent = msgVal;
        if (actionVal) {
            previewEmailActionWrap.classList.remove('d-none');
        } else {
            previewEmailActionWrap.classList.add('d-none');
        }

        // In-App Preview
        previewInAppTitle.textContent = titleVal;
        previewInAppMessage.textContent = msgVal;
        if (actionVal) {
            previewInAppLink.classList.remove('d-none');
        } else {
            previewInAppLink.classList.add('d-none');
        }
    }

    // Sinkronisasi Tipe Notifikasi
    function updateNotificationType() {
        const checkedType = document.querySelector('input[name="type"]:checked');
        const type = checkedType ? checkedType.value : 'info';

        const typeMap = {
            info:    { label: 'Informasi', icon: 'bi-info-circle', bg: 'bg-primary', light: 'bg-primary-subtle' },
            success: { label: 'Sukses',    icon: 'bi-check-circle', bg: 'bg-success', light: 'bg-success-subtle' },
            warning: { label: 'Peringatan', icon: 'bi-exclamation-triangle', bg: 'bg-warning', light: 'bg-warning-subtle' },
            danger:  { label: 'Penting',   icon: 'bi-shield-exclamation', bg: 'bg-danger', light: 'bg-danger-subtle' }
        };

        const config = typeMap[type] || typeMap.info;
        previewWaType.textContent = config.label;

        // In-App Icon Update
        previewInAppIcon.className = 'bi ' + config.icon + ' fs-xs';
        previewInAppIconWrap.className = 'rounded-circle text-white p-2 d-flex align-items-center justify-content-center shadow-2xs ' + config.bg;
    }

    // Quick Action URL Chips
    const quickUrlBtns = document.querySelectorAll('.quick-url-btn');
    quickUrlBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            notifActionUrl.value = this.dataset.url;
            updatePreviewContent();
        });
    });

    // Event Listeners
    userSelect.addEventListener('change', function () {
        updateSelectedUser();
        updatePreviewContent();
    });

    recipientEmail.addEventListener('input', function () {
        previewEmailRecipient.textContent = this.value.trim() || 'penerima@email.com';
    });

    recipientPhone.addEventListener('input', function () {
        previewWaPhone.textContent = formatPhoneDisplay(this.value);
    });

    notifTitle.addEventListener('input', updatePreviewContent);
    notifMessage.addEventListener('input', updatePreviewContent);
    notifActionUrl.addEventListener('input', updatePreviewContent);

    const typeRadios = document.querySelectorAll('input[name="type"]');
    typeRadios.forEach(function (r) {
        r.addEventListener('change', updateNotificationType);
    });

    // Validasi Form Sebelum Submit
    const form = document.getElementById('customNotificationForm');
    const btnSubmit = document.getElementById('btnSubmitNotification');

    form.addEventListener('submit', function (e) {
        const channels = Array.from(document.querySelectorAll('.channel-checkbox:checked')).map(c => c.value);
        if (!userSelect.value) {
            e.preventDefault();
            alert('Silakan pilih salah satu pengguna yang sudah terdaftar.');
            userSelect.focus();
            return false;
        }

        if (channels.length === 0) {
            e.preventDefault();
            alert('Pilih setidaknya satu saluran pengiriman notifikasi (In-App, WhatsApp, atau Email).');
            return false;
        }

        if (channels.includes('email') && !recipientEmail.value.trim()) {
            e.preventDefault();
            alert('Alamat email wajib diisi untuk saluran Email.');
            recipientEmail.focus();
            return false;
        }

        if (channels.includes('whatsapp') && !recipientPhone.value.trim()) {
            e.preventDefault();
            alert('Nomor WhatsApp wajib diisi untuk saluran WhatsApp.');
            recipientPhone.focus();
            return false;
        }

        // Tampilkan loading spinner pada tombol
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span> Mengirim Notifikasi...';
    });

    // Inisialisasi awal
    updateSelectedUser();
    updatePreviewContent();
    updateNotificationType();
});
</script>
