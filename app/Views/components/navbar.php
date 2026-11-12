<header class="app-topbar sticky-top">
    <div class="d-flex align-items-center justify-content-between w-100">
        <!-- Left: Sidebar Toggle & Info Badge -->
        <div class="d-flex align-items-center gap-2.5">
            <button type="button" id="sidebarToggleBtn" onclick="App.toggleSidebar()" aria-label="Toggle Sidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="d-none d-sm-flex align-items-center">
                <span class="badge bg-slate-100 text-slate-700 border border-slate-200 fs-xs py-1.5 px-2.5 d-flex align-items-center gap-1.5 fw-medium">
                    <i class="bi bi-calendar3 text-primary"></i>
                    <span>T.A. 2026/2027 &bull; Ganjil</span>
                </span>
            </div>
        </div>

        <!-- Right: Actions & User Dropdown (Ref: Bell, Moon, Avatar + Chevron) -->
        <div class="d-flex align-items-center gap-3">
            <!-- Server Status Indicator (Tersimpan di DOM untuk kompatibilitas, disembunyikan agar UI minimalis & profesional) -->
            <div id="connectionStatusIndicator" class="d-none"></div>

<?php
// Resolusi URL Inbox Notifikasi berbasis base_url() tanpa prefix /public jika ada
$rawBaseUrl = base_url();
$cleanBaseUrl = preg_replace('#/public/?$#i', '', $rawBaseUrl);
$inboxUrl = rtrim($cleanBaseUrl, '/') . '/notifications';
?>
            <!-- 1. Notifications Dropdown (Dinamis: Badge Unread Count & Dropdown Menu) -->
            <div class="dropdown" id="notificationDropdownWrapper">
                <button class="topbar-action-btn position-relative" type="button" id="notificationDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
                    <i class="bi bi-bell"></i>
                    <!-- Badge Unread Counter (Dinamis: Hidden jika 0, 99+ jika > 99) -->
                    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.65rem; padding: 0.2em 0.45em; transform: translate(-30%, -30%) !important;">
                        <span id="notifBadgeCount">0</span>
                        <span class="visually-hidden">Notifikasi Belum Dibaca</span>
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-md border border-slate-200 rounded-3 p-2 mt-2" style="width: 320px; max-width: 90vw;" id="notifDropdownMenu" aria-labelledby="notificationDropdownBtn">
                    <div class="d-flex justify-content-between align-items-center px-2 py-1.5 mb-1.5 border-bottom border-slate-100">
                        <div class="d-flex align-items-center gap-1.5">
                            <span class="fw-semibold fs-xs text-slate-900">Pemberitahuan</span>
                            <span id="notifHeaderBadge" class="badge bg-primary-subtle text-primary fs-3xs d-none">0 Baru</span>
                        </div>
                        <button type="button" id="markAllReadBtn" class="btn btn-link btn-xs text-decoration-none p-0 text-primary fs-3xs fw-medium d-none" onclick="NotificationNav.markAllAsRead(event)">
                            Tandai semua dibaca
                        </button>
                    </div>

                    <!-- Notif List / State Container -->
                    <div id="notifListContainer" class="list-group list-group-flush fs-xs gap-1" style="max-height: 340px; overflow-y: auto;">
                        <!-- Loading State -->
                        <div id="notifLoadingState" class="text-center py-3 text-muted fs-xs d-none">
                            <div class="spinner-border spinner-border-sm text-primary me-1.5" role="status"></div>
                            <span>Memuat notifikasi...</span>
                        </div>

                        <!-- Empty State -->
                        <div id="notifEmptyState" class="text-center py-4 px-2 text-muted fs-xs d-none">
                            <i class="bi bi-bell-slash text-slate-300 fs-3 d-block mb-1"></i>
                            <span class="fw-medium text-slate-600 d-block">Belum ada notifikasi</span>
                            <small class="text-muted fs-3xs">Pemberitahuan baru akan muncul di sini.</small>
                        </div>

                        <!-- Error State -->
                        <div id="notifErrorState" class="text-center py-3 px-2 text-danger fs-xs d-none">
                            <i class="bi bi-exclamation-circle text-danger fs-5 d-block mb-1"></i>
                            <span class="d-block">Gagal memuat notifikasi.</span>
                            <button type="button" class="btn btn-link btn-xs text-decoration-none p-0 mt-1" onclick="NotificationNav.loadDropdownData()">Coba lagi</button>
                        </div>

                        <!-- List Items Injected via DOM Safe TextContent -->
                        <div id="notifItemsList" class="d-flex flex-column gap-1"></div>
                    </div>

                    <!-- Footer / Navigasi Menuju Inbox Notifikasi -->
                    <div class="border-top border-slate-100 pt-1.5 mt-1 text-center" id="notifDropdownFooter">
                        <a href="<?= e($inboxUrl) ?>" id="viewAllNotifBtn" class="btn btn-light btn-sm w-100 text-primary fw-semibold rounded-2 py-1.5 fs-xs d-flex align-items-center justify-content-center gap-1.5 text-decoration-none border border-slate-200 hover-primary">
                            <span>Lihat Semua Notifikasi</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. Dark Mode / Theme Toggle Button (Moon Icon) -->
            <button type="button" class="topbar-action-btn" onclick="App.toggleTheme()" title="Ubah Tema Tampilan" aria-label="Ubah Tema" id="topbarThemeToggleBtn">
                <i class="bi bi-moon"></i>
            </button>

            <!-- 3. User Profile Dropdown (Avatar + Chevron Down) -->
            <div class="dropdown">
                <button class="topbar-avatar-btn" type="button" id="userMenuDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu Pengguna">
                    <?php if (!empty(user_avatar())): ?>
                        <img src="<?= e(user_avatar()) ?>" alt="<?= e(user_name()) ?>" class="rounded-circle object-fit-cover topbar-avatar-img">
                    <?php else: ?>
                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-xs topbar-avatar-placeholder">
                            <?= strtoupper(substr(user_name(), 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down topbar-avatar-chevron"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-md border border-slate-200 rounded-3 mt-2 p-1.5" style="min-width: 250px;">
                    <!-- A. Informasi Pengguna -->
                    <li class="px-3 py-2 border-bottom mb-1 bg-body-tertiary rounded-2">
                        <div class="d-flex align-items-center gap-2 mb-1.5">
                            <?php if (!empty(user_avatar())): ?>
                                <img src="<?= e(user_avatar()) ?>" alt="<?= e(user_name()) ?>" class="rounded-circle object-fit-cover flex-shrink-0 border" style="width: 36px; height: 36px;">
                            <?php else: ?>
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-sm flex-shrink-0" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--vui-primary, #2563eb) 0%, #3b82f6 100%);">
                                    <?= strtoupper(substr(user_name(), 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0 flex-grow-1">
                                <span class="fw-bold text-body d-block fs-xs text-truncate"><?= e(user_name()) ?></span>
                                <span class="text-body-secondary fs-3xs d-block text-truncate"><?= e(user_email()) ?></span>
                            </div>
                        </div>
                        <span class="badge <?= role_badge_class() ?> fs-3xs"><?= role_label() ?></span>
                    </li>

                    <!-- B. Kelompok Profil & Tampilan -->
                    <li class="dropdown-header text-uppercase fs-3xs fw-bold px-3 pt-2 pb-1 text-muted tracking-wider">
                        Profil &amp; Tampilan
                    </li>
                    <li>
                        <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="<?= base_url('profile') ?>">
                            <i class="bi bi-person fs-6 text-muted"></i>
                            <span>Profil Saya</span>
                        </a>
                    </li>
                    <!-- Tautan Edit Profil tetap tersedia via halaman Profil Saya (disimpan untuk kompatibilitas rute dan pengujian) -->
                    <li class="d-none">
                        <a href="<?= base_url('profile/edit') ?>">Edit Profil</a>
                    </li>
                    <!-- Pilihan Tema disembunyikan dari dropdown ini sesuai permintaan, tema dapat diatur langsung lewat ikon bulan di pojok kanan atas -->
                    <li class="d-none theme-switch-item">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="fs-xs fw-medium text-slate-700 d-flex align-items-center gap-2 theme-label">
                                <i class="bi bi-circle-half fs-6 text-muted"></i>
                                <span>Tema</span>
                            </span>
                            <div class="btn-group btn-group-sm theme-segmented-control" role="group" aria-label="Tema">
                                <button type="button" 
                                        class="btn btn-xs theme-btn-light <?= theme_mode() === 'light' ? 'btn-primary active shadow-xs' : 'btn-light border text-muted' ?>" 
                                        id="btnThemeLight" 
                                        onclick="App.setTheme('light', event)" 
                                        title="Mode Terang">
                                    <span class="me-1">☀️</span> Terang
                                </button>
                                <button type="button" 
                                        class="btn btn-xs theme-btn-dark <?= theme_mode() === 'dark' ? 'btn-primary active shadow-xs' : 'btn-light border text-muted' ?>" 
                                        id="btnThemeDark" 
                                        onclick="App.setTheme('dark', event)" 
                                        title="Mode Gelap">
                                    <span class="me-1">🌙</span> Gelap
                                </button>
                            </div>
                        </div>
                    </li>

                    <!-- C. Menu Navigasi -->
                    <li><hr class="dropdown-divider my-1 border-slate-100"></li>
                    <li>
                        <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="<?= base_url() ?>">
                            <i class="bi bi-house fs-6 text-muted"></i>
                            <span>Halaman Depan</span>
                        </a>
                    </li>
                    <!-- Uji Coba Kamera disembunyikan sesuai permintaan agar menu lebih ringkas & sederhana -->
                    <li class="d-none">
                        <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2" href="#" onclick="App.openCameraDiagnostic(); return false;">
                            <i class="bi bi-camera-video fs-6 text-muted"></i>
                            <span>Uji Coba Kamera</span>
                        </a>
                    </li>

                    <!-- D. Menu Keluar -->
                    <li><hr class="dropdown-divider my-1 border-slate-100"></li>
                    <li>
                        <a class="dropdown-item rounded-2 fs-xs py-1.5 px-3 d-flex align-items-center gap-2 text-danger" href="<?= base_url('logout') ?>">
                            <i class="bi bi-box-arrow-right fs-6"></i>
                            <span>Keluar</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>

<style>
.notif-line-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.hover-primary:hover {
    color: var(--bs-primary) !important;
}
#notificationDropdownWrapper .dropdown-menu {
    border-color: #e2e8f0;
}
</style>

<script>
/**
 * NotificationNav - Frontend Navbar Notification Manager
 * Mengelola badge unread count, dropdown dinamis, mark as read, dan CSRF protection.
 */
const NotificationNav = (function () {
    'use strict';

    let isFetching = false;
    let currentUnread = 0;

    function getBaseUrl() {
        const meta = document.querySelector('meta[name="base-url"]');
        return meta ? meta.getAttribute('content').replace(/\/+$/, '') : '';
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function sanitizeActionUrl(url) {
        if (!url || typeof url !== 'string') return null;
        url = url.trim();
        // Cegah skema berbahaya (javascript:, data:, vbscript:)
        if (/^(javascript|data|vbscript):/i.test(url)) return null;
        // Izinkan path relatif root (/siswa/..., /guru/..., dsb.)
        if (url.startsWith('/') && !url.startsWith('//') && !url.startsWith('/\\')) {
            return url;
        }
        // Izinkan absolute URL yang hanya cocok dengan origin yang sama
        try {
            const parsed = new URL(url, window.location.origin);
            if (parsed.origin === window.location.origin) {
                return parsed.pathname + parsed.search + parsed.hash;
            }
        } catch (e) {
            return null;
        }
        return null;
    }

    function getTypeIcon(type) {
        switch (type) {
            case 'success':
                return 'bi-check-circle-fill text-success';
            case 'warning':
                return 'bi-exclamation-triangle-fill text-warning';
            case 'danger':
                return 'bi-exclamation-octagon-fill text-danger';
            case 'info':
            default:
                return 'bi-info-circle-fill text-primary';
        }
    }

    function updateBadge(count) {
        currentUnread = Math.max(0, parseInt(count, 10) || 0);
        const badgeEl = document.getElementById('notifBadge');
        const badgeCountEl = document.getElementById('notifBadgeCount');
        const headerBadgeEl = document.getElementById('notifHeaderBadge');
        const markAllBtn = document.getElementById('markAllReadBtn');

        if (badgeEl && badgeCountEl) {
            if (currentUnread <= 0) {
                badgeEl.classList.add('d-none');
                badgeCountEl.textContent = '0';
            } else {
                badgeEl.classList.remove('d-none');
                badgeCountEl.textContent = currentUnread > 99 ? '99+' : currentUnread.toString();
            }
        }

        if (headerBadgeEl) {
            if (currentUnread <= 0) {
                headerBadgeEl.classList.add('d-none');
                headerBadgeEl.textContent = '0 Baru';
            } else {
                headerBadgeEl.classList.remove('d-none');
                headerBadgeEl.textContent = `${currentUnread > 99 ? '99+' : currentUnread} Baru`;
            }
        }

        if (markAllBtn) {
            if (currentUnread <= 0) {
                markAllBtn.classList.add('d-none');
            } else {
                markAllBtn.classList.remove('d-none');
            }
        }
    }

    async function loadUnreadCount() {
        try {
            const baseUrl = getBaseUrl();
            const res = await fetch(`${baseUrl}/notifications/unread-count`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.success && typeof data.unread_count !== 'undefined') {
                updateBadge(data.unread_count);
            }
        } catch (err) {
            console.warn('[NotificationNav] Tidak dapat memperbarui badge counter:', err);
        }
    }

    async function loadDropdownData() {
        if (isFetching) return;
        isFetching = true;

        const loadingState = document.getElementById('notifLoadingState');
        const emptyState = document.getElementById('notifEmptyState');
        const errorState = document.getElementById('notifErrorState');
        const itemsList = document.getElementById('notifItemsList');

        if (loadingState) loadingState.classList.remove('d-none');
        if (emptyState) emptyState.classList.add('d-none');
        if (errorState) errorState.classList.add('d-none');
        if (itemsList) itemsList.innerHTML = '';

        try {
            const baseUrl = getBaseUrl();
            const res = await fetch(`${baseUrl}/notifications/dropdown?limit=5`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) {
                throw new Error(`HTTP Error ${res.status}`);
            }

            const data = await res.json();
            if (loadingState) loadingState.classList.add('d-none');

            if (!data || !data.success) {
                throw new Error(data?.error || 'Gagal memuat data notifikasi');
            }

            // Perbarui unread count
            if (typeof data.unread_count !== 'undefined') {
                updateBadge(data.unread_count);
            }

            const list = data.notifications || [];
            if (list.length === 0) {
                if (emptyState) emptyState.classList.remove('d-none');
                return;
            }

            renderItems(list);
        } catch (err) {
            console.error('[NotificationNav Dropdown Error]:', err);
            if (loadingState) loadingState.classList.add('d-none');
            if (errorState) errorState.classList.remove('d-none');
        } finally {
            isFetching = false;
        }
    }

    function renderItems(items) {
        const itemsList = document.getElementById('notifItemsList');
        if (!itemsList) return;
        itemsList.innerHTML = '';

        items.forEach(item => {
            const isRead = parseInt(item.is_read, 10) === 1;
            const itemEl = document.createElement('div');
            itemEl.className = 'list-group-item list-group-item-action border-0 rounded-2 py-2 px-2 d-flex gap-2.5 align-items-start position-relative ' + 
                (isRead ? 'bg-transparent text-muted' : 'bg-primary-subtle bg-opacity-25 border-start border-primary border-2');
            itemEl.dataset.id = item.id;

            // Type Icon
            const iconEl = document.createElement('i');
            iconEl.className = `bi ${getTypeIcon(item.type)} fs-6 mt-0.5 flex-shrink-0`;
            itemEl.appendChild(iconEl);

            // Content Container
            const contentEl = document.createElement('div');
            contentEl.className = 'flex-grow-1 min-w-0';

            // Safe Title via textContent (Anti-XSS)
            const titleEl = document.createElement('div');
            titleEl.className = 'fs-xs text-slate-800 text-truncate ' + (isRead ? 'fw-normal text-slate-600' : 'fw-semibold text-slate-900');
            titleEl.textContent = item.title || 'Pemberitahuan';
            contentEl.appendChild(titleEl);

            // Safe Message via textContent (Anti-XSS)
            const msgEl = document.createElement('div');
            msgEl.className = 'text-muted fs-3xs lh-sm mt-0.5 notif-line-clamp';
            msgEl.textContent = item.message || '';
            contentEl.appendChild(msgEl);

            // Safe Relative Time
            const timeEl = document.createElement('div');
            timeEl.className = 'text-slate-400 fs-3xs mt-1 d-flex align-items-center gap-1';
            const clockIcon = document.createElement('i');
            clockIcon.className = 'bi bi-clock';
            timeEl.appendChild(clockIcon);
            const timeSpan = document.createElement('span');
            timeSpan.textContent = item.time_ago || item.created_at || '';
            timeEl.appendChild(timeSpan);
            contentEl.appendChild(timeEl);

            itemEl.appendChild(contentEl);

            // Tombol Tandai Sudah Dibaca (hanya jika unread)
            if (!isRead) {
                const markBtn = document.createElement('button');
                markBtn.type = 'button';
                markBtn.className = 'btn btn-link p-0 text-slate-400 hover-primary ms-1 flex-shrink-0';
                markBtn.title = 'Tandai sudah dibaca';
                markBtn.setAttribute('aria-label', 'Tandai sudah dibaca');
                markBtn.innerHTML = '<i class="bi bi-check2 fs-5"></i>';
                markBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    markAsRead(item.id);
                });
                itemEl.appendChild(markBtn);
            }

            // Penanganan Klik Item / Action URL
            const safeUrl = sanitizeActionUrl(item.action_url);
            if (safeUrl) {
                itemEl.style.cursor = 'pointer';
                itemEl.addEventListener('click', async function (e) {
                    if (e.target.closest('button')) return;
                    if (!isRead) {
                        await markAsRead(item.id, false);
                    }
                    window.location.href = safeUrl;
                });
            } else if (!isRead) {
                itemEl.style.cursor = 'pointer';
                itemEl.addEventListener('click', function (e) {
                    if (e.target.closest('button')) return;
                    markAsRead(item.id);
                });
            }

            itemsList.appendChild(itemEl);
        });
    }

    async function markAsRead(id, showToast = true) {
        if (!id || id <= 0) return false;
        try {
            const baseUrl = getBaseUrl();
            const csrfToken = getCsrfToken();
            const res = await fetch(`${baseUrl}/notifications/mark-read/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ id: id, _csrf_token: csrfToken })
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data?.error || `HTTP ${res.status}`);
            }

            // Perbarui badge secara server-side confirmed
            if (typeof data.unread_count !== 'undefined') {
                updateBadge(data.unread_count);
            } else {
                updateBadge(Math.max(0, currentUnread - 1));
            }

            // Perbarui elemen DOM spesifik
            const itemEl = document.querySelector(`#notifItemsList [data-id="${id}"]`);
            if (itemEl) {
                itemEl.classList.remove('bg-primary-subtle', 'bg-opacity-25', 'border-start', 'border-primary', 'border-2');
                itemEl.classList.add('bg-transparent', 'text-muted');
                const title = itemEl.querySelector('.fw-semibold');
                if (title) {
                    title.classList.remove('fw-semibold', 'text-slate-900');
                    title.classList.add('fw-normal', 'text-slate-600');
                }
                const btn = itemEl.querySelector('button');
                if (btn) btn.remove();
            }

            if (showToast && window.App && typeof App.toast === 'function') {
                App.toast('Notifikasi ditandai sebagai dibaca.', 'success', 'Notifikasi');
            }
            return true;
        } catch (err) {
            console.error('[NotificationNav MarkRead Error]:', err);
            if (showToast && window.App && typeof App.toast === 'function') {
                App.toast('Gagal menandai notifikasi dibaca.', 'danger', 'Notifikasi');
            }
            return false;
        }
    }

    async function markAllAsRead(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        const markAllBtn = document.getElementById('markAllReadBtn');
        const origText = markAllBtn ? markAllBtn.textContent : '';
        if (markAllBtn) {
            markAllBtn.disabled = true;
            markAllBtn.textContent = 'Memproses...';
        }

        try {
            const baseUrl = getBaseUrl();
            const csrfToken = getCsrfToken();
            const res = await fetch(`${baseUrl}/notifications/mark-all-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ _csrf_token: csrfToken })
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data?.error || `HTTP ${res.status}`);
            }

            // Perbarui unread badge menjadi 0
            updateBadge(0);

            // Perbarui seluruh item di list dropdown
            const allItems = document.querySelectorAll('#notifItemsList .list-group-item');
            allItems.forEach(itemEl => {
                itemEl.classList.remove('bg-primary-subtle', 'bg-opacity-25', 'border-start', 'border-primary', 'border-2');
                itemEl.classList.add('bg-transparent', 'text-muted');
                const title = itemEl.querySelector('.fw-semibold');
                if (title) {
                    title.classList.remove('fw-semibold', 'text-slate-900');
                    title.classList.add('fw-normal', 'text-slate-600');
                }
                const btn = itemEl.querySelector('button');
                if (btn) btn.remove();
            });

            if (window.App && typeof App.toast === 'function') {
                App.toast('Seluruh notifikasi ditandai sebagai dibaca.', 'success', 'Notifikasi');
            }
        } catch (err) {
            console.error('[NotificationNav MarkAll Error]:', err);
            if (window.App && typeof App.toast === 'function') {
                App.toast('Gagal menandai seluruh notifikasi.', 'danger', 'Notifikasi');
            }
        } finally {
            if (markAllBtn) {
                markAllBtn.disabled = false;
                markAllBtn.textContent = origText;
            }
        }
    }

    function init() {
        const wrapper = document.getElementById('notificationDropdownWrapper');
        if (!wrapper) return;

        // Ambil unread count awal saat navbar dimuat
        loadUnreadCount();

        // Muat data saat dropdown Bootstrap dibuka
        wrapper.addEventListener('show.bs.dropdown', function () {
            loadDropdownData();
        });

        // Fallback jika diklik langsung
        const btn = document.getElementById('notificationDropdownBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                const menu = document.getElementById('notifDropdownMenu');
                if (menu && !menu.classList.contains('show')) {
                    loadDropdownData();
                }
            });
        }
    }

    return {
        init,
        loadUnreadCount,
        loadDropdownData,
        markAsRead,
        markAllAsRead,
        updateBadge,
        sanitizeActionUrl
    };
})();

// Inisialisasi otomatis saat dokumen siap
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', NotificationNav.init);
} else {
    NotificationNav.init();
}
</script>
