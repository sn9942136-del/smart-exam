<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Models\NotificationModel;
use App\Services\NotificationService;
use Throwable;

/**
 * Notification Controller
 * Melayani endpoint AJAX & API notifikasi pengguna (Siswa, Guru, Admin)
 * dengan kontrol akses berbasis sesi, proteksi IDOR, dan keamanan CSRF.
 */
class NotificationController extends BaseController
{
    protected NotificationModel $notificationModel;
    protected NotificationService $notificationService;

    public function __construct(
        ?Request $request = null,
        ?NotificationModel $notificationModel = null,
        ?NotificationService $notificationService = null
    ) {
        parent::__construct($request);
        $this->notificationModel = $notificationModel ?? new NotificationModel();
        $this->notificationService = $notificationService ?? new NotificationService($this->notificationModel);
    }

    /**
     * GET AJAX: Mengambil data ringkasan notifikasi terbaru & unread count untuk dropdown navbar
     */
    public function getDropdownData(): Response
    {
        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi login tidak valid atau telah berakhir.'
            ], 401);
        }

        try {
            $limit = max(1, min(10, (int) ($this->request->getGet('limit') ?? 5)));
            $notifications = $this->notificationModel->getLatestForDropdown($userId, $limit);
            $unreadCount = $this->notificationModel->getUnreadCount($userId);

            // Format tanggal & waktu relatif yang ramah pengguna
            $formatted = array_map(function (array $item) {
                return [
                    'id'          => (int) $item['id'],
                    'title'       => e($item['title']),
                    'message'     => e($item['message']),
                    'type'        => $item['type'],
                    'action_url'  => !empty($item['action_url']) ? $this->sanitizeActionUrl($item['action_url']) : null,
                    'is_read'     => (int) $item['is_read'],
                    'read_at'     => $item['read_at'],
                    'time_ago'    => $this->formatTimeAgo($item['created_at']),
                    'created_at'  => date('d M Y H:i', strtotime($item['created_at']))
                ];
            }, $notifications);

            return $this->json([
                'success'       => true,
                'unread_count'  => $unreadCount,
                'notifications' => $formatted
            ], 200);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Gagal memuat data notifikasi. Silakan coba kembali.'
            ], 500);
        }
    }

    /**
     * GET AJAX: Mengambil angka jumlah notifikasi yang belum dibaca (badge counter)
     */
    public function getUnreadCount(): Response
    {
        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi login tidak valid atau telah berakhir.'
            ], 401);
        }

        try {
            $unreadCount = $this->notificationModel->getUnreadCount($userId);

            return $this->json([
                'success'      => true,
                'unread_count' => $unreadCount
            ], 200);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Gagal mengambil jumlah notifikasi.'
            ], 500);
        }
    }

    /**
     * GET View: Halaman Inbox Notifikasi Lengkap
     * Mendukung seluruh role (Siswa, Guru, Admin) dengan layout adaptif,
     * tab status (Semua, Belum Dibaca, Sudah Dibaca), filter tipe, dan pagination.
     */
    public function inbox(): Response
    {
        $userId = (int) user_id();
        if ($userId <= 0) {
            if ($this->request->isAjax()) {
                return $this->json([
                    'success' => false,
                    'error'   => 'Sesi login tidak valid atau telah berakhir.'
                ], 401);
            }
            return $this->redirect(base_url('login'));
        }

        try {
            $status = strtolower(trim((string) ($this->request->getGet('status') ?? 'all')));
            if (!in_array($status, ['all', 'unread', 'read'], true)) {
                $status = 'all';
            }

            $isRead = match ($status) {
                'unread' => 0,
                'read'   => 1,
                default  => null,
            };

            $type = strtolower(trim((string) ($this->request->getGet('type') ?? '')));
            $cleanType = in_array($type, ['info', 'warning', 'danger', 'success'], true) ? $type : null;

            $page = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = max(1, min(50, (int) ($this->request->getGet('per_page') ?? 10)));
            $offset = ($page - 1) * $perPage;

            $items = $this->notificationModel->getUserNotifications($userId, $perPage, $offset, $cleanType, null, $isRead);
            $totalItems = $this->notificationModel->countUserNotifications($userId, $cleanType, null, $isRead);
            $totalPages = (int) ceil($totalItems / $perPage);

            $unreadTotal = $this->notificationModel->getUnreadCount($userId);
            $allTotal = $this->notificationModel->countUserNotifications($userId);
            $readTotal = max(0, $allTotal - $unreadTotal);

            $formatted = array_map(function (array $item) {
                return [
                    'id'          => (int) $item['id'],
                    'title'       => $item['title'],
                    'message'     => $item['message'],
                    'type'        => $item['type'],
                    'action_url'  => !empty($item['action_url']) ? $this->sanitizeActionUrl($item['action_url']) : null,
                    'is_read'     => (int) $item['is_read'],
                    'read_at'     => $item['read_at'],
                    'time_ago'    => $this->formatTimeAgo($item['created_at']),
                    'created_at'  => date('d M Y H:i', strtotime($item['created_at']))
                ];
            }, $items);

            $userRole = (string) (user_role() ?? 'peserta_didik');
            $layout = match ($userRole) {
                'super_admin', 'admin' => 'layouts/admin',
                'guru'                  => 'layouts/guru',
                'peserta_didik', 'siswa' => 'layouts/peserta',
                default                 => 'layouts/main',
            };

            $rolePrefix = match ($userRole) {
                'super_admin', 'admin' => 'admin',
                'guru'                  => 'guru',
                'peserta_didik', 'siswa' => 'siswa',
                default                 => '',
            };

            $roleDashboardUrl = $rolePrefix ? base_url($rolePrefix . '/dashboard') : base_url();
            $roleTitle = match ($userRole) {
                'super_admin', 'admin' => 'Admin',
                'guru'                  => 'Guru',
                'peserta_didik', 'siswa' => 'Siswa',
                default                 => 'Dashboard',
            };

            $breadcrumbs = [
                ['title' => $roleTitle, 'url' => $roleDashboardUrl],
                ['title' => 'Inbox Notifikasi']
            ];

            $data = [
                'pageTitle'    => 'Inbox Notifikasi',
                'breadcrumbs'  => $breadcrumbs,
                'notifications'=> $formatted,
                'totalItems'   => $totalItems,
                'currentPage'  => $page,
                'totalPages'   => $totalPages,
                'perPage'      => $perPage,
                'status'       => $status,
                'type'         => $type,
                'unreadCount'  => $unreadTotal,
                'stats'        => [
                    'all'    => $allTotal,
                    'unread' => $unreadTotal,
                    'read'   => $readTotal
                ]
            ];

            return $this->view('notifications/inbox', $data, $layout);
        } catch (Throwable $e) {
            return $this->view('errors/500', ['error' => 'Gagal memuat inbox notifikasi.'], 'layouts/main');
        }
    }

    /**
     * GET AJAX: Mengambil daftar lengkap notifikasi pengguna dengan paginasi dan filter
     */
    public function index(): Response
    {
        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi login tidak valid atau telah berakhir.'
            ], 401);
        }

        // Jika request browser biasa meminta HTML (bukan AJAX/Fetch), arahkan ke view inbox
        $accept = (string) ($this->request->header('Accept') ?? '');
        $isAjaxOrJson = $this->request->isAjax() || $this->request->getGet('format') === 'json' || str_contains($accept, 'application/json');
        $isHtmlRequest = (str_contains($accept, 'text/html') || php_sapi_name() !== 'cli') && !$isAjaxOrJson;
        if ($isHtmlRequest) {
            return $this->inbox();
        }

        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = max(1, min(50, (int) ($this->request->getGet('per_page') ?? 15)));
            $offset = ($page - 1) * $perPage;

            $type = $this->request->getGet('type');
            $cleanType = in_array($type, ['info', 'warning', 'danger', 'success'], true) ? $type : null;

            $status = strtolower(trim((string) ($this->request->getGet('status') ?? '')));
            $unreadOnly = filter_var($this->request->getGet('unread_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $isReadParam = $this->request->getGet('is_read');

            $isRead = null;
            if ($status === 'unread' || $unreadOnly === true || $isReadParam === '0' || $isReadParam === 0) {
                $isRead = 0;
            } elseif ($status === 'read' || $isReadParam === '1' || $isReadParam === 1) {
                $isRead = 1;
            }

            $items = $this->notificationModel->getUserNotifications($userId, $perPage, $offset, $cleanType, null, $isRead);
            $totalItems = $this->notificationModel->countUserNotifications($userId, $cleanType, null, $isRead);
            $unreadCount = $this->notificationModel->getUnreadCount($userId);

            $formatted = array_map(function (array $item) {
                return [
                    'id'          => (int) $item['id'],
                    'title'       => e($item['title']),
                    'message'     => e($item['message']),
                    'type'        => $item['type'],
                    'action_url'  => !empty($item['action_url']) ? $this->sanitizeActionUrl($item['action_url']) : null,
                    'is_read'     => (int) $item['is_read'],
                    'read_at'     => $item['read_at'],
                    'time_ago'    => $this->formatTimeAgo($item['created_at']),
                    'created_at'  => date('d M Y H:i', strtotime($item['created_at']))
                ];
            }, $items);

            return $this->json([
                'success'      => true,
                'data'         => $formatted,
                'total'        => $totalItems,
                'unread_count' => $unreadCount,
                'status'       => $status ?: ($isRead === 0 ? 'unread' : ($isRead === 1 ? 'read' : 'all')),
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int) ceil($totalItems / $perPage)
            ], 200);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Gagal mengambil daftar notifikasi.'
            ], 500);
        }
    }

    /**
     * POST AJAX: Menandai satu notifikasi spesifik sebagai sudah dibaca
     * Mencegah IDOR: Memastikan hanya notifikasi milik pengguna login yang dapat diubah.
     */
    public function markAsRead(?int $id = null): Response
    {
        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi login tidak valid atau telah berakhir.'
            ], 401);
        }

        // Ambil ID dari parameter rute atau payload request (JSON / POST / Route param)
        if ($id === null || $id <= 0) {
            $fromInput = $this->request->input('id') 
                ?? $this->request->input('notification_id') 
                ?? $this->request->param('id');
            if ($fromInput !== null && is_numeric($fromInput)) {
                $id = (int) $fromInput;
            } else {
                $rawInput = file_get_contents('php://input');
                $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
                $postData = $this->request->getPost() ?: $_POST;
                $payload = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;

                $id = (int) ($payload['id'] ?? $payload['notification_id'] ?? 0);
            }
        }

        if ($id <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'ID notifikasi tidak valid.'
            ], 422);
        }

        try {
            // Scoping ketat berdasarkan user_id di level query model
            $updated = $this->notificationModel->markAsRead($id, $userId);

            if (!$updated) {
                return $this->json([
                    'success' => false,
                    'error'   => 'Notifikasi tidak ditemukan atau Anda tidak memiliki akses ke notifikasi ini.'
                ], 404);
            }

            $newUnread = $this->notificationModel->getUnreadCount($userId);

            return $this->json([
                'success'      => true,
                'message'      => 'Notifikasi berhasil ditandai sebagai dibaca.',
                'unread_count' => $newUnread
            ], 200);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Terjadi kesalahan saat memperbarui status notifikasi.'
            ], 500);
        }
    }

    /**
     * POST AJAX: Menandai seluruh notifikasi milik pengguna login sebagai sudah dibaca
     */
    public function markAllAsRead(): Response
    {
        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi login tidak valid atau telah berakhir.'
            ], 401);
        }

        try {
            $affected = $this->notificationModel->markAllAsRead($userId);

            return $this->json([
                'success'        => true,
                'message'        => 'Seluruh notifikasi berhasil ditandai sebagai dibaca.',
                'affected_count' => $affected,
                'unread_count'   => 0
            ], 200);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Terjadi kesalahan saat memperbarui notifikasi.'
            ], 500);
        }
    }

    /**
     * Helper privat format waktu relatif (Time Ago) standar Bahasa Indonesia
     */
    protected function formatTimeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Baru saja';
        }
        if ($diff < 3600) {
            $mins = max(1, (int) floor($diff / 60));
            return "{$mins} menit lalu";
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return "{$hours} jam lalu";
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return "{$days} hari lalu";
        }

        return date('d M Y H:i', $timestamp);
    }

    /**
     * Sanitasi Action URL agar hanya mengizinkan navigasi internal yang aman
     * Menolak skema berbahaya (javascript:, data:, vbscript:) dan URL eksternal berbahaya.
     */
    public function sanitizeActionUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Tolak skema berbahaya
        if (preg_match('/^(javascript|data|vbscript|file):/i', $url)) {
            return null;
        }

        // Jika URL diawali dengan slash (internal absolute path)
        if (str_starts_with($url, '/')) {
            return base_url(ltrim($url, '/'));
        }

        // Jika diawali http:// atau https://
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parsedApp = parse_url(base_url());
            $parsedUrl = parse_url($url);

            // Hanya izinkan jika host sama dengan aplikasi kita
            if (isset($parsedUrl['host']) && isset($parsedApp['host']) && strtolower($parsedUrl['host']) === strtolower($parsedApp['host'])) {
                return $url;
            }

            return null; // Tolak URL eksternal asing
        }

        // Relative internal path (contoh "siswa/results")
        return base_url($url);
    }
}
