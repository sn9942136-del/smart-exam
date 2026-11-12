<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/users') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5 me-1" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Reset Kata Sandi Pengguna</h5>
                </div>
                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fs-xs"><i class="bi bi-shield-lock me-1"></i>Admin Override</span>
            </div>

            <div class="card-body p-4">
                <!-- User Summary Box -->
                <div class="p-3 rounded-3 bg-slate-50 border border-slate-100 mb-4 d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center fs-5" style="width: 42px; height: 42px;">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h6 class="fw-bold text-slate-900 mb-0 fs-sm"><?= e($user['name']) ?></h6>
                        <small class="text-muted fs-xs"><?= e($user['email']) ?> &bull; @<?= e($user['username']) ?></small>
                    </div>
                </div>

                <form action="<?= base_url('admin/users/reset-password/' . $user['id']) ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Kata Sandi Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password_input" class="form-control border-slate-200 fs-xs rounded-start-3" placeholder="Minimal 6 karakter" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary border-slate-200 fs-xs rounded-end-3" onclick="generateRandomPass()" title="Generate Password Acak">
                                <i class="bi bi-magic me-1"></i> Acak Sandi
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-slate-800 fs-xs">Konfirmasi Kata Sandi Baru <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password_input" class="form-control border-slate-200 fs-xs rounded-3" placeholder="Ulangi kata sandi baru" required minlength="6">
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top border-slate-100">
                        <a href="<?= base_url('admin/users') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                        <button type="submit" class="btn btn-sm btn-warning text-dark fw-semibold rounded-pill px-4 shadow-xs">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Kata Sandi Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function generateRandomPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input1 = document.getElementById('new_password_input');
    const input2 = document.getElementById('confirm_password_input');
    if (input1 && input2) {
        input1.type = 'text';
        input2.type = 'text';
        input1.value = pass;
        input2.value = pass;
        App.toast('Kata sandi acak dibuat: ' + pass, 'info', 'Auto-Generate Password');
    }
}
</script>
