<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../components/breadcrumb.php'; ?>

<!-- Page Header Card -->
<div class="card border-0 shadow-xs rounded-4 p-3.5 bg-white mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
                <span class="rounded-3 bg-primary-subtle text-primary p-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="bi bi-inbox-fill fs-5"></i>
                </span>
                <span>Inbox Notifikasi</span>
            </h4>
            <p class="text-muted fs-3xs mb-0">
                Pantau seluruh informasi ujian, aktivitas akun, dan pengumuman sistem secara terpadu.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fs-3xs fw-semibold" id="inboxUnreadBadge">
                <i class="bi bi-bell-fill me-1"></i>
                <span id="inboxUnreadCountText"><?= (int) ($unreadCount ?? 0) ?></span> Belum Dibaca
            </span>
            <button type="button" id="inboxMarkAllBtn" class="btn btn-sm btn-outline-primary rounded-pill px-3.5 py-1.5 fw-semibold shadow-2xs" <?= (($unreadCount ?? 0) <= 0) ? 'disabled' : '' ?>>
                <i class="bi bi-check2-all me-1"></i> Tandai Semua Dibaca
            </button>
        </div>
    </div>
</div>

<!-- Tabs & Filter Bar Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white mb-4 overflow-hidden">
    <div class="p-3 border-bottom border-slate-100 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <!-- Status Filter Tabs -->
        <ul class="nav nav-pills gap-1" id="inboxStatusTabs">
            <li class="nav-item">
                <a href="?status=all<?= !empty($type) ? '&type=' . urlencode($type) : '' ?>" class="nav-link rounded-pill px-3 py-1.5 fs-3xs fw-semibold <?= ($status === 'all') ? 'active' : 'text-slate-600' ?>">
                    Semua
                    <span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1"><?= (int) ($stats['all'] ?? 0) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?status=unread<?= !empty($type) ? '&type=' . urlencode($type) : '' ?>" class="nav-link rounded-pill px-3 py-1.5 fs-3xs fw-semibold <?= ($status === 'unread') ? 'active' : 'text-slate-600' ?>">
                    Belum Dibaca
                    <span class="badge rounded-pill bg-primary ms-1" id="tabUnreadBadge"><?= (int) ($stats['unread'] ?? 0) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?status=read<?= !empty($type) ? '&type=' . urlencode($type) : '' ?>" class="nav-link rounded-pill px-3 py-1.5 fs-3xs fw-semibold <?= ($status === 'read') ? 'active' : 'text-slate-600' ?>">
                    Sudah Dibaca
                    <span class="badge rounded-pill bg-light text-muted border ms-1"><?= (int) ($stats['read'] ?? 0) ?></span>
                </a>
            </li>
        </ul>

        <!-- Type Filter Selector -->
        <div class="d-flex align-items-center gap-2">
            <label for="filterTypeSelect" class="fs-3xs text-muted text-nowrap d-none d-sm-inline">
                <i class="bi bi-funnel me-1"></i> Filter Tipe:
            </label>
            <form method="GET" action="" id="typeFilterForm" class="m-0">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <select name="type" id="filterTypeSelect" class="form-select form-select-sm rounded-pill bg-light border-slate-200 fs-3xs fw-medium px-3" onchange="this.form.submit()">
                    <option value="" <?= empty($type) ? 'selected' : '' ?>>Semua Tipe</option>
                    <option value="info" <?= ($type === 'info') ? 'selected' : '' ?>>Informasi</option>
                    <option value="warning" <?= ($type === 'warning') ? 'selected' : '' ?>>Peringatan</option>
                    <option value="success" <?= ($type === 'success') ? 'selected' : '' ?>>Keberhasilan</option>
                    <option value="danger" <?= ($type === 'danger') ? 'selected' : '' ?>>Penting / Bahaya</option>
                </select>
            </form>
        </div>
    </div>

    <!-- Error State Alert (Hidden by Default) -->
    <div id="inboxErrorAlert" class="alert alert-danger m-3 d-none rounded-3 py-2 px-3 fs-3xs" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <span id="inboxErrorMessage">Terjadi kesalahan. Silakan coba kembali.</span>
    </div>

    <!-- Notifications List Container -->
    <div class="p-3" id="inboxListContainer">
        <?php if (empty($notifications)): ?>
            <!-- Empty State -->
            <div class="empty-state py-5 px-3 text-center" id="inboxEmptyState">
                <div class="empty-state-icon mx-auto mb-3 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-bell-slash fs-2"></i>
                </div>
                <h6 class="empty-state-title fs-6 fw-bold text-dark mb-1">Tidak Ada Notifikasi</h6>
                <p class="empty-state-desc text-muted fs-3xs mb-0 mx-auto" style="max-width: 380px;">
                    <?php if ($status === 'unread'): ?>
                        Hebat! Anda telah membaca seluruh notifikasi.
                    <?php elseif ($status === 'read'): ?>
                        Belum ada notifikasi yang ditandai sebagai sudah dibaca.
                    <?php else: ?>
                        Saat ini belum ada notifikasi yang ditujukan untuk akun Anda.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-2.5" id="inboxItemList">
                <?php foreach ($notifications as $notif): ?>
                    <?php
                    $isUnread = ((int) $notif['is_read'] === 0);
                    $typeClass = match ($notif['type']) {
                        'warning' => ['icon' => 'bi-exclamation-triangle-fill', 'color' => 'warning', 'label' => 'Peringatan'],
                        'danger'  => ['icon' => 'bi-exclamation-octagon-fill', 'color' => 'danger', 'label' => 'Penting'],
                        'success' => ['icon' => 'bi-check-circle-fill', 'color' => 'success', 'label' => 'Berhasil'],
                        default   => ['icon' => 'bi-info-circle-fill', 'color' => 'info', 'label' => 'Informasi'],
                    };
                    ?>
                    <div class="card border rounded-3 p-3 transition-all notif-item <?= $isUnread ? 'bg-light border-primary border-start border-4' : 'bg-white border-slate-100' ?>" data-id="<?= $notif['id'] ?>" data-is-read="<?= $notif['is_read'] ?>">
                        <div class="d-flex align-items-start gap-3">
                            <!-- Type Icon -->
                            <div class="rounded-circle bg-<?= $typeClass['color'] ?>-subtle text-<?= $typeClass['color'] ?> p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="bi <?= $typeClass['icon'] ?> fs-5"></i>
                            </div>

                            <!-- Notification Content -->
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h6 class="fw-bold text-slate-900 mb-0 fs-6 notif-title">
                                            <?= e($notif['title']) ?>
                                        </h6>
                                        <span class="badge bg-<?= $typeClass['color'] ?>-subtle text-<?= $typeClass['color'] ?> border border-<?= $typeClass['color'] ?>-subtle rounded-pill fs-4xs">
                                            <?= $typeClass['label'] ?>
                                        </span>
                                        <?php if ($isUnread): ?>
                                            <span class="badge bg-primary rounded-pill fs-4xs notif-status-badge">Belum Dibaca</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border rounded-pill fs-4xs notif-status-badge">Sudah Dibaca</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted fs-4xs d-flex align-items-center gap-1" title="<?= e($notif['created_at']) ?>">
                                        <i class="bi bi-clock"></i> <?= e($notif['time_ago']) ?>
                                    </small>
                                </div>

                                <p class="text-slate-600 fs-3xs mb-2.5 notif-message" style="white-space: pre-line;">
                                    <?= e($notif['message']) ?>
                                </p>

                                <!-- Action Buttons -->
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if (!empty($notif['action_url'])): ?>
                                        <a href="<?= e($notif['action_url']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fs-3xs fw-semibold notif-action-link" data-id="<?= $notif['id'] ?>" data-url="<?= e($notif['action_url']) ?>" data-is-read="<?= $notif['is_read'] ?>">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Tautan
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($isUnread): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1 fs-3xs fw-medium btn-mark-read" data-id="<?= $notif['id'] ?>" title="Tandai sebagai sudah dibaca">
                                            <i class="bi bi-check2 me-1"></i> Tandai Dibaca
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalItems > 0): ?>
        <div class="px-3 pb-3">
            <?php include __DIR__ . '/../components/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Inline JavaScript Inbox Controller -->
<script>
(function() {
    'use strict';

    const CSRF_META = document.querySelector('meta[name="csrf-token"]');
    const CSRF_TOKEN = CSRF_META ? CSRF_META.getAttribute('content') : '';
    const BASE_URL_META = document.querySelector('meta[name="base-url"]');
    const BASE_URL = BASE_URL_META ? BASE_URL_META.getAttribute('content').replace(/\/+$/, '') : '';

    const inboxMarkAllBtn = document.getElementById('inboxMarkAllBtn');
    const inboxUnreadBadge = document.getElementById('inboxUnreadBadge');
    const inboxUnreadCountText = document.getElementById('inboxUnreadCountText');
    const tabUnreadBadge = document.getElementById('tabUnreadBadge');
    const inboxErrorAlert = document.getElementById('inboxErrorAlert');
    const inboxErrorMessage = document.getElementById('inboxErrorMessage');

    function showError(msg) {
        if (!inboxErrorAlert || !inboxErrorMessage) return;
        inboxErrorMessage.textContent = msg;
        inboxErrorAlert.classList.remove('d-none');
        setTimeout(() => {
            inboxErrorAlert.classList.add('d-none');
        }, 5000);
    }

    // Validasi URL internal yang aman
    function isSafeUrl(url) {
        if (!url || typeof url !== 'string') return false;
        const trimmed = url.trim();
        if (/^(javascript|data|vbscript|file):/i.test(trimmed)) return false;
        if (trimmed.startsWith('/')) return true;
        try {
            const parsed = new URL(trimmed, window.location.origin);
            return parsed.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    // Update unread count counter di UI inbox & broadcast ke navbar
    function updateUnreadCounts(newCount) {
        const count = Math.max(0, parseInt(newCount, 10) || 0);
        if (inboxUnreadCountText) {
            inboxUnreadCountText.textContent = count;
        }
        if (tabUnreadBadge) {
            tabUnreadBadge.textContent = count;
        }
        if (inboxMarkAllBtn) {
            inboxMarkAllBtn.disabled = (count <= 0);
        }

        // Sinkronisasi badge navbar jika ada instance NotificationNav
        if (window.NotificationNav && typeof window.NotificationNav.refreshCount === 'function') {
            window.NotificationNav.refreshCount();
        } else {
            const navBadge = document.getElementById('notifBadge');
            const navCount = document.getElementById('notifBadgeCount');
            if (navBadge && navCount) {
                if (count > 0) {
                    navCount.textContent = count > 99 ? '99+' : count;
                    navBadge.classList.remove('d-none');
                } else {
                    navBadge.classList.add('d-none');
                }
            }
        }
    }

    // Handler Tandai Satu Notifikasi Dibaca
    async function markSingleAsRead(id, buttonEl) {
        if (!id || id <= 0) return;

        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Proses...';
        }

        try {
            const response = await fetch(`${BASE_URL}/notifications/mark-read/${encodeURIComponent(id)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({
                    'id': id,
                    '_csrf_token': CSRF_TOKEN
                })
            });

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = `${BASE_URL}/login`;
                    return;
                }
                throw new Error('Gagal menandai notifikasi dibaca.');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Operasi gagal.');
            }

            // Perbarui UI item kartu notifikasi
            const itemCard = document.querySelector(`.notif-item[data-id="${id}"]`);
            if (itemCard) {
                itemCard.classList.remove('bg-light', 'border-primary', 'border-start', 'border-4');
                itemCard.classList.add('bg-white', 'border-slate-100');
                itemCard.setAttribute('data-is-read', '1');

                const statusBadge = itemCard.querySelector('.notif-status-badge');
                if (statusBadge) {
                    statusBadge.className = 'badge bg-light text-muted border rounded-pill fs-4xs notif-status-badge';
                    statusBadge.textContent = 'Sudah Dibaca';
                }

                // Sembunyikan tombol tandai dibaca
                if (buttonEl) {
                    buttonEl.remove();
                }

                // Jika sedang di tab "Belum Dibaca", hilangkan baris tersebut secara halus
                const currentStatus = new URLSearchParams(window.location.search).get('status');
                if (currentStatus === 'unread') {
                    itemCard.style.transition = 'opacity 0.3s ease';
                    itemCard.style.opacity = '0';
                    setTimeout(() => {
                        itemCard.remove();
                        const remaining = document.querySelectorAll('.notif-item');
                        if (remaining.length === 0) {
                            window.location.reload();
                        }
                    }, 300);
                }
            }

            if (typeof data.unread_count !== 'undefined') {
                updateUnreadCounts(data.unread_count);
            }
        } catch (err) {
            showError(err.message || 'Gagal memperbarui status notifikasi.');
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.innerHTML = '<i class="bi bi-check2 me-1"></i> Tandai Dibaca';
            }
        }
    }

    // Handler Tandai Semua Dibaca
    async function markAllAsRead() {
        if (!inboxMarkAllBtn || inboxMarkAllBtn.disabled) return;

        const originalHtml = inboxMarkAllBtn.innerHTML;
        inboxMarkAllBtn.disabled = true;
        inboxMarkAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Memproses...';

        try {
            const response = await fetch(`${BASE_URL}/notifications/mark-all-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams({
                    '_csrf_token': CSRF_TOKEN
                })
            });

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = `${BASE_URL}/login`;
                    return;
                }
                throw new Error('Gagal menandai seluruh notifikasi.');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Operasi gagal.');
            }

            updateUnreadCounts(0);

            // Perbarui seluruh item di halaman aktif
            const currentStatus = new URLSearchParams(window.location.search).get('status');
            if (currentStatus === 'unread') {
                // Di tab unread, seluruh item hilang
                window.location.reload();
            } else {
                document.querySelectorAll('.notif-item').forEach(item => {
                    item.classList.remove('bg-light', 'border-primary', 'border-start', 'border-4');
                    item.classList.add('bg-white', 'border-slate-100');
                    item.setAttribute('data-is-read', '1');

                    const statusBadge = item.querySelector('.notif-status-badge');
                    if (statusBadge) {
                        statusBadge.className = 'badge bg-light text-muted border rounded-pill fs-4xs notif-status-badge';
                        statusBadge.textContent = 'Sudah Dibaca';
                    }

                    const btn = item.querySelector('.btn-mark-read');
                    if (btn) btn.remove();
                });
            }
        } catch (err) {
            showError(err.message || 'Gagal menandai seluruh notifikasi.');
            inboxMarkAllBtn.disabled = false;
            inboxMarkAllBtn.innerHTML = originalHtml;
        }
    }

    // Event delegation untuk tombol tandai dibaca & klik action url
    document.addEventListener('click', function(e) {
        const markBtn = e.target.closest('.btn-mark-read');
        if (markBtn) {
            e.preventDefault();
            const id = markBtn.getAttribute('data-id');
            markSingleAsRead(id, markBtn);
            return;
        }

        const actionLink = e.target.closest('.notif-action-link');
        if (actionLink) {
            const url = actionLink.getAttribute('data-url') || actionLink.getAttribute('href');
            const id = actionLink.getAttribute('data-id');
            const isRead = actionLink.getAttribute('data-is-read');

            if (!isSafeUrl(url)) {
                e.preventDefault();
                showError('Tautan tindakan tidak valid atau tidak aman.');
                return;
            }

            // Jika belum dibaca, tandai dibaca di latar belakang sebelum navigasi
            if (isRead === '0' && id) {
                e.preventDefault();
                fetch(`${BASE_URL}/notifications/mark-read/${encodeURIComponent(id)}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ 'id': id, '_csrf_token': CSRF_TOKEN })
                }).finally(() => {
                    window.location.href = url;
                });
            }
        }
    });

    if (inboxMarkAllBtn) {
        inboxMarkAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllAsRead();
        });
    }
})();
</script>
