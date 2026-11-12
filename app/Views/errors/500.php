<div class="container py-5 my-auto">
    <div class="row justify-content-center text-center">
        <div class="col-md-10 col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white text-start">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill fs-xs fw-bold">
                        <i class="bi bi-x-octagon-fill me-1"></i> HTTP 500 INTERNAL SERVER ERROR
                    </span>
                    <span class="badge bg-dark-subtle text-dark fs-xs">Logger Active</span>
                </div>

                <h1 class="display-3 fw-extrabold text-danger mb-2">500</h1>
                <h2 class="fw-bold fs-4 mb-2">Terjadi Kesalahan Pada Server</h2>
                <p class="text-muted fs-6 mb-4">
                    Sistem mengalami kendala saat memproses permintaan Anda. Log error telah dicatat ke <code>storage/logs/</code> untuk penelusuran developer.
                </p>

                <?php if (!empty($debug) && isset($exception) && $exception instanceof \Throwable): ?>
                    <div class="bg-dark text-white rounded-3 p-3 mb-4 font-monospace fs-xs overflow-auto">
                        <div class="text-danger fw-bold mb-1">
                            <?= e(get_class($exception)) ?>: <?= e($exception->getMessage()) ?>
                        </div>
                        <div class="text-secondary mb-2">
                            in <?= e($exception->getFile()) ?>:<?= e((string)$exception->getLine()) ?>
                        </div>
                        <div class="border-top border-secondary pt-2 text-white-50">
                            <strong>Stack Trace:</strong>
                            <pre class="text-white-50 mb-0 mt-1" style="white-space: pre-wrap;"><?= e($exception->getTraceAsString()) ?></pre>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <a href="<?= base_url() ?>" class="btn btn-primary-gradient px-4 py-2 rounded-pill fw-semibold">
                        <i class="bi bi-house-door me-1"></i> Kembali ke Beranda
                    </a>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Muat Ulang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
