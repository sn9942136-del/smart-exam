<div class="row justify-content-center w-100 m-0">
    <div class="col-11 col-sm-9 col-md-7 col-lg-5 col-xl-4 px-0">
        <div class="card vui-card p-4 p-sm-4 p-md-4 position-relative border" style="border-radius: var(--vui-card-radius, 1rem); box-shadow: var(--vui-shadow-modal);">
            <!-- Top Branding Accent -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center text-primary rounded-3 mb-2.5 shadow-xs" style="width: 48px; height: 48px; background-color: rgba(37, 99, 235, 0.08); border-radius: var(--vui-radius, 0.625rem); border: 1px solid rgba(37, 99, 235, 0.2);">
                    <i class="bi bi-shield-lock-fill fs-4 text-primary"></i>
                </div>
                <h4 class="fw-bold mb-1 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-family: var(--font-heading);">Lupa Kata Sandi?</h4>
                <p class="text-body-secondary fs-xs mb-0">Masukkan email atau nomor akun terdaftar.</p>
            </div>

            <form action="<?= base_url('forgot-password') ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="channels[]" value="email">
                <input type="hidden" name="channels[]" value="whatsapp">

                <!-- Input Akun / Email / Username -->
                <div class="mb-3">
                    <label for="login" class="form-label fs-xs fw-semibold mb-1.5">
                        Email, Username, atau WhatsApp <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 ps-3 pe-2 bg-body-tertiary text-body-secondary" style="border-radius: var(--vui-radius, 0.625rem) 0 0 var(--vui-radius, 0.625rem);">
                            <i class="bi bi-person-badge fs-6"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0 ps-2 fs-xs" 
                               style="border-radius: 0 var(--vui-radius, 0.625rem) var(--vui-radius, 0.625rem) 0;"
                               id="login" 
                               name="login" 
                               value="<?= e(old('login')) ?>" 
                               placeholder="Email / username / WhatsApp" 
                               autocomplete="username" 
                               required 
                               autofocus>
                    </div>
                </div>

                <!-- Input Konfirmasi Nomor WhatsApp (Opsional) -->
                <div class="mb-3.5">
                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                        <label for="phone" class="form-label fs-xs fw-semibold mb-0">
                            Konfirmasi WhatsApp
                        </label>
                        <span class="text-body-secondary fs-3xs">Opsional</span>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 ps-3 pe-2 bg-body-tertiary text-success" style="border-radius: var(--vui-radius, 0.625rem) 0 0 var(--vui-radius, 0.625rem);">
                            <i class="bi bi-whatsapp fs-6"></i>
                        </span>
                        <input type="tel" 
                               class="form-control border-start-0 ps-2 fs-xs" 
                               style="border-radius: 0 var(--vui-radius, 0.625rem) var(--vui-radius, 0.625rem) 0;"
                               id="phone" 
                               name="phone" 
                               value="<?= e(old('phone')) ?>" 
                               placeholder="Contoh: 08123456789" 
                               autocomplete="tel">
                    </div>
                </div>

                <div class="d-flex align-items-center gap-1.5 mb-3.5 text-body-secondary fs-3xs">
                    <i class="bi bi-shield-check text-primary"></i>
                    <span>Kode OTP dikirim otomatis ke Email &amp; WhatsApp terdaftar.</span>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold fs-sm shadow-xs mb-3" id="submitBtn" style="border-radius: var(--vui-radius, 0.625rem); background-color: var(--vui-primary, #2563eb); border-color: var(--vui-primary, #2563eb); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);">
                    <i class="bi bi-send-fill me-1.5"></i> Kirim Kode OTP
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