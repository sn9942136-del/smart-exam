<div class="row justify-content-center w-100 m-0">
    <div class="col-11 col-sm-9 col-md-7 col-lg-5 col-xl-4 px-0">
        <div class="card vui-card p-4 p-sm-4 p-md-4 position-relative border" style="border-radius: var(--vui-card-radius, 1rem); box-shadow: var(--vui-shadow-modal);">
            <!-- Top Branding Accent -->
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center text-white mb-2.5 shadow-xs" style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--vui-primary, #2563eb) 0%, #3b82f6 100%); border-radius: var(--vui-radius, 0.625rem); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                    <i class="bi bi-shield-check fs-4 text-white"></i>
                </div>
                <h4 class="fw-bold mb-1 tracking-tight" style="color: var(--vui-text-main, #0f172a); font-family: var(--font-heading);">Smart <span style="color: var(--vui-primary, #2563eb);">Exam</span></h4>
                <p class="text-body-secondary fs-xs mb-0">Platform Ujian Digital Terpadu</p>
            </div>

            <form action="<?= base_url('login') ?>" method="POST" id="loginForm" novalidate>
                <?= csrf_field() ?>

                <!-- Email or Username Input -->
                <div class="mb-3">
                    <label for="login" class="form-label fs-xs fw-semibold mb-1.5">
                        Email atau Username <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 ps-3 pe-2 bg-body-tertiary text-body-secondary" style="border-radius: var(--vui-radius, 0.625rem) 0 0 var(--vui-radius, 0.625rem);">
                            <i class="bi bi-person fs-6"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0 ps-2 fs-xs" 
                               style="border-radius: 0 var(--vui-radius, 0.625rem) var(--vui-radius, 0.625rem) 0;"
                               id="login" 
                               name="login" 
                               value="<?= e(old('login')) ?>" 
                               placeholder="Email atau username" 
                               autocomplete="username" 
                               required 
                               autofocus>
                    </div>
                </div>

                <!-- Password Input with Toggle -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                        <label for="password" class="form-label fs-xs fw-semibold mb-0">
                            Kata Sandi <span class="text-danger">*</span>
                        </label>
                        <a href="<?= base_url('forgot-password') ?>" class="fs-xs text-decoration-none fw-medium" style="color: var(--vui-primary, #2563eb);" tabindex="4">
                            Lupa Sandi?
                        </a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 ps-3 pe-2 bg-body-tertiary text-body-secondary" style="border-radius: var(--vui-radius, 0.625rem) 0 0 var(--vui-radius, 0.625rem);">
                            <i class="bi bi-lock fs-6"></i>
                        </span>
                        <input type="password" 
                               class="form-control border-start-0 border-end-0 px-2 fs-xs" 
                               id="password" 
                               name="password" 
                               placeholder="••••••••" 
                               autocomplete="current-password" 
                               required>
                        <button type="button" 
                                class="btn border-start-0 pe-3 ps-2 bg-body text-body-secondary border" 
                                style="border-radius: 0 var(--vui-radius, 0.625rem) var(--vui-radius, 0.625rem) 0;"
                                onclick="togglePasswordVisibility('password', this)" 
                                tabindex="-1" 
                                aria-label="Tampilkan atau sembunyikan kata sandi"
                                title="Tampilkan/Sembunyikan Sandi">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox & Security Badge -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                    <div class="form-check d-inline-flex align-items-center m-0 p-0">
                        <input class="form-check-input m-0 me-2" type="checkbox" name="remember" value="1" id="remember" style="cursor: pointer;">
                        <label class="form-check-label fs-xs user-select-none mb-0" for="remember" style="cursor: pointer;">
                            Ingatkan saya
                        </label>
                    </div>
                    <span class="badge rounded-pill bg-body-tertiary text-body-secondary border border-secondary-subtle fs-3xs px-2.5 py-1">
                        <i class="bi bi-shield-lock-fill text-success me-1"></i>Keamanan Aktif
                    </span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold fs-sm shadow-xs mb-3" id="submitBtn" style="border-radius: var(--vui-radius, 0.625rem); background-color: var(--vui-primary, #2563eb); border-color: var(--vui-primary, #2563eb); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);">
                    <i class="bi bi-box-arrow-in-right me-1.5"></i> Masuk
                </button>
            </form>

            <!-- Footer Link -->
            <div class="pt-3 border-top border-secondary-subtle text-center">
                <a href="<?= base_url() ?>" class="fs-xs text-decoration-none d-inline-flex align-items-center gap-1 text-body-secondary">
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali ke Beranda</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash text-primary';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>