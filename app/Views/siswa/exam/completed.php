<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-xs rounded-4 p-4 p-md-5 bg-white text-center mb-4">
            <!-- Icon Celebratory -->
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle shadow-xs" style="width: 80px; height: 80px;">
                    <i class="bi bi-patch-check-fill fs-1"></i>
                </div>
            </div>

            <!-- Title & Subtitle -->
            <h3 class="fw-bold text-dark mb-2">Ujian Berhasil Dikumpulkan!</h3>
            <p class="text-muted fs-xs mb-4">
                Terima kasih, seluruh lembar jawaban ujian Anda telah berhasil disimpan dan tercatat di server kami secara aman.
            </p>

            <!-- Informasi Rekap Sesi Ujian -->
            <div class="card bg-light border-0 rounded-4 p-3.5 mb-4 text-start">
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text-fill text-primary"></i>
                    <span>Ringkasan Pengumpulan</span>
                </h6>

                <div class="row g-3 fs-xs">
                    <div class="col-sm-6">
                        <span class="text-muted d-block fs-3xs">Waktu Mulai</span>
                        <strong class="text-dark">
                            <?= !empty($session['start_time']) ? date('d M Y, H:i:s', strtotime($session['start_time'])) : '-' ?> WIB
                        </strong>
                    </div>

                    <div class="col-sm-6">
                        <span class="text-muted d-block fs-3xs">Waktu Dikumpulkan</span>
                        <strong class="text-dark">
                            <?= !empty($session['end_time']) ? date('d M Y, H:i:s', strtotime($session['end_time'])) : date('d M Y, H:i:s') ?> WIB
                        </strong>
                    </div>

                    <div class="col-sm-6">
                        <span class="text-muted d-block fs-3xs">Status Lembar Ujian</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-3xs fw-bold">
                            <i class="bi bi-check-circle me-1"></i> Selesai (Submitted)
                        </span>
                    </div>

                    <div class="col-sm-6">
                        <span class="text-muted d-block fs-3xs">Kode Sesi</span>
                        <span class="font-monospace text-secondary fs-3xs"><?= e(substr($session['session_token'] ?? '', 0, 16)) ?>...</span>
                    </div>
                </div>

                <?php if (!empty($result)): ?>
                    <hr class="my-3 text-secondary-subtle">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted d-block fs-3xs">Skor Otomatis (Pilihan Ganda)</span>
                            <strong class="text-dark fs-5"><?= number_format((float)($result['total_score'] ?? 0), 1) ?></strong>
                            <small class="text-muted fs-3xs"> / 100 Poin</small>
                        </div>
                        <div>
                            <?php
                            $status = $result['status'] ?? 'pending';
                            if ($status === 'passed'): ?>
                                <span class="badge bg-success text-white px-3 py-1.5 rounded-pill fs-xs">Lulus</span>
                            <?php elseif ($status === 'failed'): ?>
                                <span class="badge bg-danger text-white px-3 py-1.5 rounded-pill fs-xs">Belum Lulus</span>
                            <?php else: ?>
                                <span class="badge bg-info text-white px-3 py-1.5 rounded-pill fs-xs">Menunggu Penilaian Esai</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="<?= base_url('siswa/dashboard') ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold fs-xs">
                    <i class="bi bi-house-door me-1"></i> Dashboard Siswa
                </a>
                <a href="<?= base_url('siswa/history') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold fs-xs shadow-xs">
                    <i class="bi bi-clock-history me-1"></i> Lihat Riwayat Ujian
                </a>
            </div>
        </div>
    </div>
</div>
