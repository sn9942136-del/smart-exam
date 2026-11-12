<!-- Breadcrumbs -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 fs-xs">
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <?php if (!empty($crumb['url'])): ?>
                        <li class="breadcrumb-item"><a href="<?= e($crumb['url']) ?>" class="text-decoration-none text-muted"><?= e($crumb['title']) ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active text-slate-800 fw-semibold" aria-current="page"><?= e($crumb['title']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0 text-slate-900 tracking-tight">Edit Profil Saya</h4>
    </div>
    <div>
        <a href="<?= base_url('profile') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5 fs-xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Profil</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Pengaturan Foto Profil -->
    <div class="col-lg-4">
        <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4 text-center mb-4">
            <h6 class="fw-bold text-slate-900 mb-3 fs-xs text-start pb-2 border-bottom border-slate-100">
                <i class="bi bi-camera me-1.5 text-primary"></i>Foto Profil Pengguna
            </h6>

            <!-- Avatar Preview Box -->
            <div class="position-relative d-inline-block my-2">
                <div id="avatarPreviewContainer">
                    <?php if (!empty(user_avatar($user['avatar'] ?? null))): ?>
                        <img id="avatarPreviewImg" 
                             src="<?= e(user_avatar($user['avatar'])) ?>" 
                             alt="<?= e($user['name']) ?>" 
                             class="rounded-circle object-fit-cover shadow-sm border border-3 border-white" 
                             style="width: 120px; height: 120px;">
                    <?php else: ?>
                        <div id="avatarPlaceholder" 
                             class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-1 shadow-sm border border-3 border-white mx-auto" 
                             style="width: 120px; height: 120px; background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <img id="avatarPreviewImg" 
                             src="" 
                             alt="Preview Avatar" 
                             class="rounded-circle object-fit-cover shadow-sm border border-3 border-white d-none" 
                             style="width: 120px; height: 120px;">
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-2 mb-3">
                <span class="fw-semibold text-slate-900 d-block fs-sm"><?= e($user['name']) ?></span>
                <span class="badge <?= role_badge_class($userRole) ?> mt-1 fs-3xs"><?= role_label($userRole) ?></span>
            </div>

            <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-3 text-start fs-3xs text-muted mb-3">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Format foto yang didukung: <strong>JPG, JPEG, PNG, WebP</strong>. Maksimal ukuran berkas <strong>2 MB</strong>.
            </div>

            <!-- Tombol Aksi Hapus Foto (Jika memiliki custom avatar) -->
            <?php if (!empty($user['avatar']) && str_starts_with($user['avatar'], 'uploads/avatars/')): ?>
                <form action="<?= base_url('profile/delete-avatar') ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus foto profil dan kembali ke avatar inisial?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-xs rounded-pill px-3 py-1.5 w-100">
                        <i class="bi bi-trash me-1"></i> Hapus Foto Kustom
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Kanan: Formulir Edit Profil -->
    <div class="col-lg-8">
        <div class="card vui-card border border-slate-200 shadow-sm rounded-4 p-4">
            <h6 class="fw-bold text-slate-900 mb-3 pb-2 border-bottom border-slate-100 fs-xs d-flex align-items-center gap-2">
                <i class="bi bi-person-gear text-primary"></i>
                <span>Perbarui Data Akun</span>
            </h6>

            <form action="<?= base_url('profile/update') ?>" method="POST" enctype="multipart/form-data" id="profileForm" novalidate>
                <?= csrf_field() ?>

                <!-- Unggah Foto Baru -->
                <div class="mb-3.5">
                    <label for="avatarInput" class="form-label fs-xs fw-semibold text-slate-800 mb-1">
                        Pilih Berkas Foto Profil Baru (Opsional)
                    </label>
                    <input type="file" 
                           class="form-control form-control-sm fs-xs" 
                           id="avatarInput" 
                           name="avatar" 
                           accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text fs-3xs text-muted">
                        Pilih gambar jika Anda ingin mengganti foto profil. Pratinjau akan langsung terlihat di sebelah kiri.
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <!-- Nama Lengkap -->
                    <div class="col-md-6">
                        <label for="name" class="form-label fs-xs fw-semibold text-slate-800 mb-1">
                            Nama Lengkap <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-sm fs-xs" 
                               id="name" 
                               name="name" 
                               value="<?= e($user['name']) ?>" 
                               required 
                               maxlength="150" 
                               placeholder="Nama lengkap Anda">
                    </div>

                    <!-- Username -->
                    <div class="col-md-6">
                        <label for="username" class="form-label fs-xs fw-semibold text-slate-800 mb-1">
                            Username <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-sm fs-xs" 
                               id="username" 
                               name="username" 
                               value="<?= e($user['username']) ?>" 
                               required 
                               maxlength="50" 
                               placeholder="Username akun">
                    </div>

                    <!-- Jenis Kelamin -->
                    <div class="col-md-6">
                        <label for="gender" class="form-label fs-xs fw-semibold text-slate-800 mb-1">
                            Jenis Kelamin
                        </label>
                        <select class="form-select form-select-sm fs-xs" id="gender" name="gender">
                            <option value="" <?= empty($user['gender']) ? 'selected' : '' ?>>-- Pilih Jenis Kelamin --</option>
                            <option value="L" <?= (($user['gender'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki (L)</option>
                            <option value="P" <?= (($user['gender'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan (P)</option>
                        </select>
                    </div>

                    <!-- NIP / NISN (Read-Only) -->
                    <div class="col-md-6">
                        <label class="form-label fs-xs fw-semibold text-slate-800 mb-1">
                            <?= in_array($userRole, ['peserta_didik', 'siswa'], true) ? 'NISN' : 'NIP' ?>
                            <span class="badge bg-secondary-subtle text-secondary fs-4xs ms-1">Terproteksi</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-sm fs-xs bg-light" 
                               value="<?= e($user['nip_nisn'] ?? '-') ?>" 
                               disabled 
                               readonly>
                        <div class="form-text fs-4xs text-muted">Dikelola oleh Administrator melalui Data Pokok.</div>
                    </div>
                </div>

                <!-- Bagian Kontak & Keamanan (Read-Only dengan Penjelasan Keamanan Protokol OTP) -->
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-3 mb-4">
                    <div class="d-flex align-items-center gap-1.5 mb-2.5">
                        <i class="bi bi-shield-lock-fill text-primary"></i>
                        <span class="fw-bold text-slate-900 fs-xs">Saluran Notifikasi & Kontak Terverifikasi</span>
                    </div>

                    <div class="row g-3 fs-xs">
                        <div class="col-md-6">
                            <label class="form-label text-muted fs-3xs mb-1">Alamat Email Utama</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control bg-white border-start-0 text-slate-800 font-monospace" value="<?= e($user['email']) ?>" readonly disabled>
                            </div>
                            <small class="fs-4xs text-muted mt-1 d-block">Terhubung dengan layanan Gmail SMTP untuk pemulihan akun.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted fs-3xs mb-1">Nomor WhatsApp Aktif</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-whatsapp"></i></span>
                                <input type="text" class="form-control bg-white border-start-0 text-slate-800 font-monospace" value="<?= e($user['phone'] ?? '-') ?>" readonly disabled>
                            </div>
                            <small class="fs-4xs text-muted mt-1 d-block">Terhubung dengan Fonnte API untuk kode OTP verifikasi.</small>
                        </div>
                    </div>

                    <div class="mt-2.5 pt-2 border-top border-slate-200 fs-3xs text-muted d-flex align-items-center gap-1.5">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Demi keamanan autentikasi ujian, perubahan Email dan Nomor WhatsApp memerlukan permohonan ke Administrator.</span>
                    </div>
                </div>

                <!-- Bagian Preferensi Tema Tampilan -->
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-3 mb-4 theme-preference-box">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-1.5 mb-0.5">
                                <i class="bi bi-palette-fill text-primary"></i>
                                <span class="fw-bold text-slate-900 fs-xs">Preferensi Tema Tampilan</span>
                            </div>
                            <small class="fs-4xs text-muted d-block">Pilih tampilan antarmuka yang paling nyaman bagi Anda (tersimpan permanen ke akun).</small>
                        </div>
                        <div class="btn-group btn-group-sm theme-segmented-control" role="group" aria-label="Tema Tampilan">
                            <button type="button" 
                                    class="btn btn-sm theme-btn-light <?= theme_mode() === 'light' ? 'btn-primary active shadow-xs' : 'btn-light border text-muted' ?>" 
                                    onclick="App.setTheme('light', event)">
                                <span class="me-1">☀️</span> Terang
                            </button>
                            <button type="button" 
                                    class="btn btn-sm theme-btn-dark <?= theme_mode() === 'dark' ? 'btn-primary active shadow-xs' : 'btn-light border text-muted' ?>" 
                                    onclick="App.setTheme('dark', event)">
                                <span class="me-1">🌙</span> Gelap
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="d-flex justify-content-end gap-2 pt-2 border-top border-slate-100">
                    <a href="<?= base_url('profile') ?>" class="btn btn-light btn-sm rounded-pill px-4 py-2 fs-xs fw-semibold border border-slate-200">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 py-2 fs-xs fw-semibold shadow-xs">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.getElementById('avatarInput');
    const previewImg = document.getElementById('avatarPreviewImg');
    const placeholder = document.getElementById('avatarPlaceholder');

    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validasi ukuran sisi klien
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran berkas melebihi 2 MB. Harap pilih gambar yang lebih kecil.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                        previewImg.classList.remove('d-none');
                    }
                    if (placeholder) {
                        placeholder.classList.add('d-none');
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

