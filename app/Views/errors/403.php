<div class="container py-5 my-auto">
    <div class="row justify-content-center text-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 p-5 bg-white">
                <div class="error-badge-pill mb-3">
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1.5 rounded-pill fs-xs fw-bold">
                        <i class="bi bi-shield-slash-fill me-1"></i> HTTP 403 ACCESS FORBIDDEN
                    </span>
                </div>
                
                <h1 class="display-1 fw-extrabold text-warning tracking-tight mb-2">403</h1>
                <h2 class="fw-bold fs-4 mb-3">Akses Halaman Ditolak</h2>
                <p class="text-muted fs-6 mb-4">
                    Anda tidak memiliki hak akses yang memadai untuk membuka bagian ini. Pastikan Anda masuk dengan role yang sesuai (Super Admin, Administrator, Guru, atau Peserta Didik).
                </p>

                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= base_url() ?>" class="btn btn-primary-gradient px-4 py-2 rounded-pill fw-semibold">
                        <i class="bi bi-house-door me-1"></i> Beranda
                    </a>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill" onclick="window.history.back()">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
