<div class="row justify-content-center w-100 m-0">
    <div class="col-11 col-sm-9 col-md-7 col-lg-5 col-xl-4 px-0">
        <div class="card vui-card p-4 p-sm-4 p-md-4 position-relative border" style="border-radius: var(--vui-card-radius, 1rem); box-shadow: var(--vui-shadow-modal);">
            <!-- Top Branding Accent -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center text-white rounded-3 mb-2.5 shadow-xs" style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--vui-primary, #2563eb) 0%, #3b82f6 100%); border-radius: var(--vui-radius, 0.625rem); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                    <i class="bi bi-shield-lock-fill fs-4 text-white"></i>
                </div>
                <h4 class="fw-bold mb-1 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-family: var(--font-heading);">Verifikasi OTP</h4>
                <p class="text-body-secondary fs-xs mb-2">Kode OTP 6 digit telah dikirimkan ke:</p>
                
                <!-- Destination Badges (Email & WhatsApp) -->
                <div class="d-flex flex-wrap justify-content-center gap-1.5 mb-1">
                    <?php if (!empty($email)): ?>
                        <span class="badge rounded-pill bg-body-tertiary text-primary border border-primary-subtle px-2.5 py-1 font-monospace fs-3xs fw-medium d-inline-flex align-items-center gap-1">
                            <i class="bi bi-envelope-fill text-primary"></i>
                            <span><?= e($maskedEmail ?? $email) ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($phone)): ?>
                        <span class="badge rounded-pill bg-body-tertiary text-success border border-success-subtle px-2.5 py-1 font-monospace fs-3xs fw-medium d-inline-flex align-items-center gap-1">
                            <i class="bi bi-whatsapp text-success"></i>
                            <span><?= e($maskedPhone ?? $phone) ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <form action="<?= base_url('verify-otp') ?>" method="POST" id="otpForm" novalidate>
                <?= csrf_field() ?>

                <div class="mb-4">
                    <label for="otp" class="form-label fs-xs fw-semibold mb-2 text-center d-block">
                        Masukkan 6 Digit OTP <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control text-center fs-2 fw-bold font-monospace py-2.5 bg-body-tertiary" 
                           style="border-radius: var(--vui-radius, 0.625rem); letter-spacing: 0.25em;"
                           id="otp" 
                           name="otp" 
                           value="<?= e(old('otp', '')) ?>" 
                           maxlength="6" 
                           pattern="[0-9]{6}" 
                           inputmode="numeric" 
                           placeholder="••••••" 
                           required 
                           autofocus 
                           autocomplete="one-time-code">
                    <div class="form-text text-center fs-3xs mt-2 text-body-secondary">
                        <i class="bi bi-clock-history me-1"></i> Berlaku 5 menit, hanya 1 kali penggunaan.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold fs-sm shadow-xs mb-3" id="submitBtn" style="border-radius: var(--vui-radius, 0.625rem); background-color: var(--vui-primary, #2563eb); border-color: var(--vui-primary, #2563eb); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);">
                    <i class="bi bi-check2-circle me-1.5"></i> Verifikasi OTP
                </button>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const otpInput = document.getElementById('otp');
                    if (otpInput) {
                        otpInput.addEventListener('input', function() {
                            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                        });
                    }
                });
            </script>

            <div class="pt-3 border-top border-secondary-subtle d-flex justify-content-between align-items-center fs-xs">
                <a href="<?= base_url('forgot-password') ?>" class="text-decoration-none d-inline-flex align-items-center gap-1 text-body-secondary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Ubah Akun</span>
                </a>
                <a href="<?= base_url('forgot-password') ?>" class="text-decoration-none fw-medium d-inline-flex align-items-center gap-1" style="color: var(--vui-primary, #2563eb);">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Kirim Ulang OTP</span>
                </a>
            </div>
        </div>
    </div>
</div>