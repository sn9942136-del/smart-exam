<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Overview -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-clock-history text-primary"></i>
            <span>Log Notifikasi Eksternal</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Audit riwayat pengiriman notifikasi saluran eksternal (WhatsApp Gateway & SMTP Email) serta status simulasi pengiriman.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="<?= base_url('admin/notifications/compose') ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 shadow-xs d-inline-flex align-items-center gap-1.5 fw-semibold">
            <i class="bi bi-send-plus"></i> Kirim Notifikasi Manual
        </a>
        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle py-1.5 px-3 fs-3xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-whatsapp"></i> WA Gateway: Standby (Simulasi)
        </span>
        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle py-1.5 px-3 fs-3xs d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-envelope"></i> SMTP Mailer: Standby (Simulasi)
        </span>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
    <form action="<?= base_url('admin/notification-logs') ?>" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-slate-50 border-slate-200 border-end-0 text-muted rounded-start-pill ps-3">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" name="search" class="form-control border-slate-200 border-start-0 ps-0 rounded-end-pill fs-xs" placeholder="Cari penerima, no WA, email, nama..." value="<?= e($search ?? '') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="channel" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Saluran --</option>
                <option value="whatsapp" <?= ($channelFilter === 'whatsapp') ? 'selected' : '' ?>>WhatsApp</option>
                <option value="email" <?= ($channelFilter === 'email') ? 'selected' : '' ?>>Email</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm border-slate-200 fs-xs rounded-pill">
                <option value="">-- Semua Status --</option>
                <option value="queued" <?= ($statusFilter === 'queued') ? 'selected' : '' ?>>Antrean (Queued)</option>
                <option value="processing" <?= ($statusFilter === 'processing') ? 'selected' : '' ?>>Sedang Diproses (Processing)</option>
                <option value="sent" <?= ($statusFilter === 'sent') ? 'selected' : '' ?>>Terkirim (Sent)</option>
                <option value="failed" <?= ($statusFilter === 'failed') ? 'selected' : '' ?>>Gagal / Dilewati</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold shadow-xs">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if (!empty($search) || !empty($channelFilter) || !empty($statusFilter)): ?>
                <a href="<?= base_url('admin/notification-logs') ?>" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Logs Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="notificationLogsTable">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3" style="width: 50px;">#</th>
                    <th class="py-3 px-3" style="width: 170px;">Waktu Log</th>
                    <th class="py-3 px-3" style="width: 120px;">Saluran</th>
                    <th class="py-3 px-3">Tujuan / Penerima</th>
                    <th class="py-3 px-3">Acuan Notifikasi</th>
                    <th class="py-3 px-3" style="width: 160px;">Status</th>
                    <th class="py-3 px-3 text-end" style="width: 120px;">Payload</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-clock-history fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Riwayat Log Notifikasi Eksternal</h6>
                                <p class="text-muted fs-xs mb-0">
                                    <?= (!empty($search) || !empty($channelFilter) || !empty($statusFilter))
                                        ? 'Tidak ditemukan riwayat log yang sesuai dengan kriteria filter Anda.'
                                        : 'Aktivitas pengiriman atau simulasi multi-channel (WhatsApp & Email) akan tercatat otomatis di sini.' ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $index => $log): ?>
                        <tr>
                            <td class="px-3 text-muted">
                                <?= (($currentPage - 1) * $perPage) + $index + 1 ?>
                            </td>
                            <td class="px-3">
                                <?php
                                    $rawDate = $log['sent_at'] ?? $log['created_at'] ?? null;
                                    $ts = !empty($rawDate) ? strtotime($rawDate) : false;
                                ?>
                                <div class="fw-medium text-slate-800">
                                    <?= $ts !== false ? date('d M Y, H:i', $ts) : '-' ?>
                                </div>
                                <small class="text-muted fs-3xs">
                                    <?= $ts !== false ? date('H:i:s', $ts) . ' WIB' : '-' ?>
                                </small>
                            </td>
                            <td class="px-3">
                                <?php if ($log['channel'] === 'whatsapp'): ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-whatsapp"></i> WhatsApp
                                    </span>
                                <?php elseif ($log['channel'] === 'email'): ?>
                                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-envelope"></i> Email
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-slate-100 text-slate-700">
                                        <?= e(strtoupper($log['channel'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div>
                                        <span class="font-monospace fw-semibold text-slate-900 d-block">
                                            <?= e($log['recipient']) ?>
                                        </span>
                                        <?php if (!empty($log['user_name'])): ?>
                                            <small class="text-muted fs-3xs d-flex align-items-center gap-1">
                                                <i class="bi bi-person"></i> <?= e($log['user_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($log['notification_title'])): ?>
                                    <span class="text-slate-800 fw-medium d-block text-truncate" style="max-width: 250px;">
                                        <?= e($log['notification_title']) ?>
                                    </span>
                                    <small class="text-muted fs-3xs">In-App Ref #<?= (int)$log['notification_id'] ?></small>
                                <?php elseif (!empty($log['notification_id'])): ?>
                                    <span class="text-muted fs-xs">In-App Ref #<?= (int)$log['notification_id'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-3xs">Direct Dispatch</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">
                                         <i class="bi bi-check-circle me-1"></i>Terkirim
                                    </span>
                                <?php elseif ($log['status'] === 'processing'): ?>
                                    <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle" title="Sedang diproses oleh worker">
                                        <i class="bi bi-arrow-repeat me-1"></i>Sedang Diproses
                                    </span>
                                <?php elseif ($log['status'] === 'queued'): ?>
                                    <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle" title="Menunggu proses antrean worker">
                                        <i class="bi bi-hourglass-split me-1"></i>Antrean
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-x-circle me-1"></i>Gagal / Dilewati
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 text-end">
                                <?php if (!empty($log['safe_payload'])): ?>
                                    <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1" data-bs-toggle="modal" data-bs-target="#payloadModal<?= (int)$log['id'] ?>">
                                        <i class="bi bi-code-slash me-1"></i>Detail
                                    </button>

                                    <!-- Modal Detail Payload -->
                                    <div class="modal fade" id="payloadModal<?= (int)$log['id'] ?>" tabindex="-1" aria-labelledby="payloadModalLabel<?= (int)$log['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content border-0 shadow-lg rounded-4 text-start">
                                                <div class="modal-header border-bottom border-slate-100 pb-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                            <i class="bi bi-journal-code"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="modal-title fw-bold text-slate-900 mb-0" id="payloadModalLabel<?= (int)$log['id'] ?>">
                                                                Detail Payload Log #<?= (int)$log['id'] ?>
                                                            </h6>
                                                            <small class="text-muted fs-3xs">Saluran: <?= strtoupper(e($log['channel'])) ?> &bull; Tujuan: <?= e($log['recipient']) ?></small>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body p-3">
                                                    <div class="alert alert-light border border-slate-200 rounded-3 py-2 px-3 mb-3 fs-3xs text-muted d-flex align-items-center gap-2">
                                                        <i class="bi bi-shield-check text-success fs-6"></i>
                                                        <span>Payload ini telah disanitasi otomatis. Parameter rahasia (token, kunci API, kata sandi) diproteksi dari tampilan antarmuka.</span>
                                                    </div>
                                                    <div class="position-relative">
                                                        <pre class="bg-slate-900 text-slate-100 p-3 rounded-3 fs-xs font-monospace mb-0" style="max-height: 380px; overflow-y: auto;"><code><?= e(json_encode($log['safe_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top border-slate-100 pt-2 pb-3">
                                                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3.5" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted fs-3xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Component -->
    <?php if ($totalItems > 0): ?>
        <div class="px-3 pb-3">
            <?php include __DIR__ . '/../../components/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>
