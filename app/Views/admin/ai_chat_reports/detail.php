<!-- Breadcrumb Navigation -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb fs-xs mb-0">
        <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url('admin/ai-chat-reports') ?>" class="text-decoration-none">Laporan Chatbot AI</a></li>
        <li class="breadcrumb-item active text-slate-600" aria-current="page">Detail Sesi #<?= (int)$session['id'] ?></li>
    </ol>
</nav>

<!-- Page Header & Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 tracking-tight" style="color: var(--vui-text-main, #0f172a);">
            <i class="bi bi-chat-quote-fill text-primary me-2"></i>Detail Percakapan Chatbot AI
        </h4>
        <span class="fs-xs text-muted">Audit riwayat percakapan asli antara pengguna dan asisten AI Smart Exam (Mode Baca Saja).</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/ai-chat-reports') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-arrow-left"></i> Kembali ke Laporan
        </a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-1.5" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Cetak Percakapan
        </button>
    </div>
</div>

<div class="row g-3">
    <!-- Kolom Kiri: Metadata Sesi & Profil Pengguna -->
    <div class="col-12 col-lg-4 col-xl-3">
        <!-- Card 1: Profil Pengguna -->
        <div class="card vui-card p-3.5 border-0 shadow-xs mb-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold fs-4 flex-shrink-0" style="width: 48px; height: 48px;">
                    <?= strtoupper(substr((string)($session['user_name'] ?? 'U'), 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <h6 class="fw-bold text-slate-900 mb-0.5 text-truncate"><?= e($session['user_name'] ?? 'Anonim') ?></h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-3xs px-2.5 py-0.5">
                        <?= e($session['role_label']) ?>
                    </span>
                </div>
            </div>

            <div class="fs-xs border-top border-slate-100 pt-2.5 d-flex flex-column gap-2">
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Email Pengguna</span>
                    <span class="text-slate-800 fw-medium"><?= e($session['user_email'] ?? '-') ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Username / NIP / NISN</span>
                    <span class="text-slate-800 fw-medium"><?= e($session['username'] ?? $session['nip_nisn'] ?? '-') ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Status Akun</span>
                    <span class="badge bg-success-subtle text-success fs-3xs px-2 py-0.5"><?= ucfirst($session['user_status'] ?? 'active') ?></span>
                </div>
            </div>
        </div>

        <!-- Card 2: Metadata Sesi Percakapan -->
        <div class="card vui-card p-3.5 border-0 shadow-xs">
            <h6 class="fw-bold fs-xs text-uppercase tracking-wider text-muted mb-2.5">
                <i class="bi bi-info-circle me-1"></i> Informasi Sesi
            </h6>

            <div class="fs-xs d-flex flex-column gap-2">
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">ID Percakapan</span>
                    <span class="font-monospace fw-bold text-slate-800">#<?= (int)$session['id'] ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">UUID Sesi</span>
                    <span class="font-monospace fs-3xs text-muted text-break"><?= e($session['session_uuid']) ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Judul Sesi</span>
                    <span class="fw-medium text-slate-800"><?= e($session['title'] ?: 'Percakapan Baru') ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Konteks Mode</span>
                    <?php if ($session['is_exam_mode']): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-3xs px-2 py-0.5">
                            <i class="bi bi-shield-shaded me-1"></i>Mode Ujian (Exam Session #<?= (int)$session['exam_session_id'] ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-light text-slate-600 border border-slate-200 fs-3xs px-2 py-0.5">
                            Mode Asisten Bebas
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Waktu Dibuat</span>
                    <span class="text-slate-800"><?= date('d/m/Y H:i:s', strtotime($session['created_at'])) ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Pembaruan Terakhir</span>
                    <span class="text-slate-800"><?= date('d/m/Y H:i:s', strtotime($session['updated_at'])) ?></span>
                </div>
                <div>
                    <span class="text-muted d-block fs-3xs text-uppercase fw-semibold">Total Pesan</span>
                    <span class="badge bg-primary text-white fs-3xs px-2 py-0.5"><?= count($session['messages']) ?> Pesan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Seluruh Urutan Pesan Kronologis (Chat Bubbles) -->
    <div class="col-12 col-lg-8 col-xl-9">
        <div class="card vui-card border-0 shadow-xs h-100 d-flex flex-column">
            <div class="p-3.5 border-bottom border-slate-100 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-xs text-slate-800">
                    <i class="bi bi-chat-dots text-primary me-1.5"></i> Riwayat Lengkap Percakapan
                </span>
                <span class="badge rounded-pill bg-light text-muted border border-slate-200 fs-3xs px-2.5 py-1">
                    Kronologis (Paling Awal &rarr; Paling Baru)
                </span>
            </div>

            <!-- Chat Messages Container -->
            <div class="p-3 p-md-4 flex-grow-1" style="background-color: var(--vui-bg-body, #f8fafc); min-height: 480px;">
                <?php if (empty($session['messages'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-chat-left-dots fs-1 opacity-40 mb-2 d-block"></i>
                        <h6 class="fs-sm fw-semibold mb-1">Belum Ada Pesan Tersimpan</h6>
                        <p class="fs-xs mb-0">Sesi percakapan ini dibuat namun tidak ada rekaman pesan.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3.5">
                        <?php foreach ($session['messages'] as $m): ?>
                            <?php 
                                $isUser = ($m['sender'] === 'user');
                                $isAssistant = ($m['sender'] === 'assistant');
                                $isError = ((int)$m['is_error'] === 1);
                            ?>

                            <?php if ($isUser): ?>
                                <!-- User Chat Bubble (Kanan) -->
                                <div class="d-flex justify-content-end mb-2">
                                    <div class="d-flex flex-column align-items-end" style="max-width: 84%;">
                                        <div class="d-flex align-items-center gap-1.5 mb-1 fs-3xs text-muted">
                                            <span class="fw-semibold text-slate-700"><?= e($session['user_name'] ?? 'Pengguna') ?></span>
                                            <span>&bull;</span>
                                            <span><?= date('H:i:s (d/m/Y)', strtotime($m['created_at'])) ?></span>
                                        </div>
                                        <div class="p-3.5 rounded-4 shadow-2xs text-white" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); border-bottom-right-radius: 4px !important; font-size: 0.875rem; line-height: 1.55; word-break: break-word;">
                                            <?= nl2br(e($m['message'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- AI Assistant Chat Bubble (Kiri) -->
                                <div class="d-flex justify-content-start mb-2">
                                    <div class="d-flex flex-column align-items-start" style="max-width: 90%;">
                                        <div class="d-flex align-items-center gap-1.5 mb-1 fs-3xs text-muted flex-wrap">
                                            <span class="d-inline-flex align-items-center gap-1 fw-bold text-primary">
                                                <i class="bi bi-robot"></i> Smart Assistant
                                            </span>
                                            <?php if (!empty($m['model_used'])): ?>
                                                <span class="badge bg-light text-slate-600 border border-slate-200 fs-3xs px-2 py-0.5">
                                                    <?= e($m['model_used']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ((int)$m['tokens_used'] > 0): ?>
                                                <span class="badge bg-light text-slate-500 border border-slate-200 fs-3xs px-2 py-0.5">
                                                    <?= (int)$m['tokens_used'] ?> tokens
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($isError): ?>
                                                <span class="badge bg-danger rounded-pill fs-3xs px-2 py-0.5">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Ada Kendala
                                                </span>
                                            <?php endif; ?>
                                            <span>&bull;</span>
                                            <span><?= date('H:i:s (d/m/Y)', strtotime($m['created_at'])) ?></span>
                                        </div>
                                        <div class="p-3.5 rounded-4 shadow-2xs border" style="background-color: var(--vui-bg-card, #ffffff); border-color: <?= $isError ? '#fca5a5' : 'var(--vui-border, #e2e8f0)' ?> !important; border-bottom-left-radius: 4px !important; font-size: 0.875rem; line-height: 1.6; color: var(--vui-text-body, #334155); word-break: break-word;">
                                            <?= nl2br(e($m['message'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Bar Status -->
            <div class="p-3 border-top border-slate-100 d-flex justify-content-between align-items-center fs-3xs text-muted">
                <span><i class="bi bi-shield-check text-success me-1"></i> Audit Log Resmi Smart Exam &bull; Read-Only</span>
                <span>ID Sesi #<?= (int)$session['id'] ?></span>
            </div>
        </div>
    </div>
</div>

