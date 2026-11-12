<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/users') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5 me-1" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Tambah Pengguna Baru</h5>
                </div>
                <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">Formulir Pengguna</span>
            </div>

            <div class="card-body p-4">
                <form action="<?= base_url('admin/users/store') ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <!-- Nama Lengkap -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>" placeholder="Contoh: Budi Santoso, M.Pd" value="<?= e($old['name'] ?? '') ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Role / Peran -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Peran Akun (Role) <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select border-slate-200 fs-xs rounded-3 <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Peran --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= (($old['role_id'] ?? '') == $r['id']) ? 'selected' : '' ?>>
                                        <?= e($r['display_name']) ?> (<?= e($r['name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['role_id'])): ?>
                                <div class="invalid-feedback"><?= e($errors['role_id'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['email']) ? 'is-invalid' : '' ?>" placeholder="contoh@sekolah.sch.id" value="<?= e($old['email'] ?? '') ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['email'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Username -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Username Login <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['username']) ? 'is-invalid' : '' ?>" placeholder="username_pengguna" value="<?= e($old['username'] ?? '') ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?= e($errors['username'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Kata Sandi Awal <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="Minimal 6 karakter" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= e($errors['password'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- NIP / NISN -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">NIP / NISN <span class="text-muted fw-normal fs-xs">(Opsional)</span></label>
                            <input type="text" name="nip_nisn" class="form-control border-slate-200 fs-xs rounded-3 <?= isset($errors['nip_nisn']) ? 'is-invalid' : '' ?>" placeholder="Nomor Induk Pegawai / Siswa" value="<?= e($old['nip_nisn'] ?? '') ?>">
                            <?php if (isset($errors['nip_nisn'])): ?>
                                <div class="invalid-feedback"><?= e($errors['nip_nisn'][0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- No Telepon -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Nomor WhatsApp / HP <span class="text-muted fw-normal fs-xs">(Opsional)</span></label>
                            <input type="text" name="phone" class="form-control border-slate-200 fs-xs rounded-3" placeholder="08xxxxxxxxxx" value="<?= e($old['phone'] ?? '') ?>">
                        </div>

                        <!-- Jenis Kelamin -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Jenis Kelamin</label>
                            <select name="gender" class="form-select border-slate-200 fs-xs rounded-3">
                                <option value="">-- Pilih --</option>
                                <option value="L" <?= (($old['gender'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= (($old['gender'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>

                        <!-- Status Awal -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Status Akun</label>
                            <select name="status" class="form-select border-slate-200 fs-xs rounded-3">
                                <option value="active" <?= (($old['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Aktif</option>
                                <option value="inactive" <?= (($old['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>

                        <!-- Kelas / Rombel (Khusus Siswa) -->
                        <div class="col-md-6" id="classSelectWrapper">
                            <label class="form-label fw-semibold text-slate-800 fs-xs d-flex align-items-center gap-1.5">
                                <span>Kelas / Rombel</span>
                                <span class="badge bg-primary-subtle text-primary fs-3xs">Khusus Siswa</span>
                            </label>
                            <select name="class_id" class="form-select border-slate-200 fs-xs rounded-3">
                                <option value="">-- Tanpa Kelas / Tentukan Nanti --</option>
                                <?php if (!empty($classes)): ?>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (($old['class_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (Tingkat <?= e($c['grade_level']) ?><?= !empty($c['major']) ? ' - ' . e($c['major']) : '' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text fs-3xs text-muted">Dapat diatur langsung sekarang atau nanti via menu Manajemen Kelas.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/users') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-save me-1"></i> Simpan Pengguna
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
