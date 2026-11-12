<div class="container py-5 my-auto">
    <div class="row justify-content-center text-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 p-5 bg-white position-relative overflow-hidden">
                <div class="error-badge-pill mb-3">
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill fs-xs fw-bold">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i> HTTP 404 NOT FOUND
                    </span>
                </div>
                
                <h1 class="display-1 fw-extrabold text-danger tracking-tight mb-2">404</h1>
                <h2 class="fw-bold fs-4 mb-3">Halaman Tidak Ditemukan</h2>
                <p class="text-muted fs-6 mb-4">
                    Maaf, rute atau halaman yang Anda tuju <code><?= isset($requestedPath) ? e($requestedPath) : '' ?></code> tidak tersedia di dalam sistem ujian ini.
                </p>

                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= base_url() ?>" class="btn btn-primary-gradient px-4 py-2 rounded-pill fw-semibold">
                        <i class="bi bi-house-door me-1"></i> Kembali ke Beranda
                    </a>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill" onclick="window.history.back()">
                        <i class="bi bi-arrow-left me-1"></i> Halaman Sebelumnya
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
