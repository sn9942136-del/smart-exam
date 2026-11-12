<div class="row justify-content-center w-100 m-0">
    <div class="col-11 col-sm-9 col-md-7 col-lg-5 col-xl-4 px-0">
        <div class="card vui-card p-4 p-sm-4 p-md-4 position-relative border" style="border-radius: var(--vui-card-radius, 1rem); box-shadow: var(--vui-shadow-modal);">
            <!-- Top Branding Accent -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center text-success rounded-3 mb-2.5 shadow-xs" style="width: 48px; height: 48px; background-color: var(--vui-success-subtle, #ecfdf5); border-radius: var(--vui-radius, 0.625rem); border: 1px solid rgba(16, 185, 129, 0.2);">
                    <i class="bi bi-shield-check fs-4 text-success"></i>
                </div>
                <h4 class="fw-bold mb-1 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-family: var(--font-heading);">Kata Sandi Baru</h4>
                <p class="text-body-secondary fs-xs mb-2">Akun Terverifikasi:</p>
                <span class="badge rounded-pill bg-body-tertiary text-body border border-secondary-subtle px-3 py-1 font-monospace fs-xs fw-medium">
                    <?= e($email ?? '') ?>
                </span>
            </div>

            <form action="<?= base_url('reset-password') ?>" method="POST" id="resetPasswordForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token ?? '') ?>">

                <!-- New Password Input -->
                <div class="mb-3">
                    <label for="new_password" class="form-label fs-xs fw-semibold mb-1.5">
                        Kata Sandi Baru <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 ps-3 pe-2 bg-body-tertiary text-body-secondary" style="border-radius: var(--vui-radius, 0.625rem) 0 0 var(--vui-radius, 0.625rem);">
                            <i class="bi bi-lock fs-6"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0 border-end-0 px-2 fs-xs" 
                               id="new_password" 
                               name="new_password" 
                               placeholder="Minimal 8 karakter" 
                               required 
                               autofocus 
                               minlength="8" 
                               autocomplete="new-password">
                        <button type="button" 
                                class="btn border-start-0 pe-3 ps-2 bg-body text-body-secondary border" 
                                style="border-radius: 0 var(--vui-radius, 0.625rem) var(--vui-radius, 0.625rem) 0;"
                                onclick="togglePasswordVisibility('new_password', this)" 
                                tabindex="-1" 
                                aria-label="Tampilkan atau sembunyikan kata sandi baru"
                                title="Tampilkan/Sembunyikan Sandi">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password Input -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label fs-xs fw-semibold mb-1.5">
                        Konfirmasi Kata Sandi <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 ps-3 pe-2 bg-body-tertiary text-body-secondary" style="border-radius: var(--vui-radius, 0.625rem) 0 0 var(--vui-radius, 0.625rem);">
                            <i class="bi bi-shield-lock fs-6"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0 border-end-0 px-2 fs-xs" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="Ulangi kata sandi baru" 
                               required 
                               minlength="8" 
                               autocomplete="new-password">
                        <button type="button" 
                                class="btn border-start-0 pe-3 ps-2 bg-body text-body-secondary border" 
                                style="border-radius: 0 var(--vui-radius, 0.625rem) var(--vui-radius, 0.625rem) 0;"
                                onclick="togglePasswordVisibility('confirm_password', this)" 
                                tabindex="-1" 
                                aria-label="Tampilkan atau sembunyikan konfirmasi kata sandi"
                                title="Tampilkan/Sembunyikan Sandi">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold fs-sm shadow-xs mb-3" id="submitBtn" style="border-radius: var(--vui-radius, 0.625rem); background-color: var(--vui-primary, #2563eb); border-color: var(--vui-primary, #2563eb); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);">
                    <i class="bi bi-check2-circle me-1.5"></i> Simpan Sandi Baru
                </button>
            </form>

            <div class="pt-3 border-top border-secondary-subtle text-center">
                <a href="<?= base_url('login') ?>" class="fs-xs text-decoration-none d-inline-flex align-items-center gap-1 text-body-secondary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali ke Masuk</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'bi bi-eye-slash text-primary';
    } else {
        input.type = 'password';
        if (icon) icon.className = 'bi bi-eye';
    }
}
</script>