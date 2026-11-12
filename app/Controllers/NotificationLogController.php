<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Notification;
use App\Libraries\Logger;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\NotificationLogModel;
use App\Models\UserModel;
use App\Services\NotificationService;
use Throwable;

/**
 * Notification Log Controller
 * Melayani antarmuka audit & monitoring log notifikasi multi-channel (Email & WhatsApp)
 * serta pengiriman notifikasi kustom ke pengguna terdaftar khusus Administrator dan Super Admin.
 */
class NotificationLogController extends BaseController
{
    protected NotificationLogModel $logModel;
    protected NotificationService $notificationService;
    protected UserModel $userModel;

    public function __construct(
        ?Request $request = null,
        ?NotificationLogModel $logModel = null,
        ?NotificationService $notificationService = null,
        ?UserModel $userModel = null
    ) {
        parent::__construct($request);
        $this->logModel = $logModel ?? new NotificationLogModel();
        $this->notificationService = $notificationService ?? new NotificationService(null, $this->logModel);
        $this->userModel = $userModel ?? new UserModel();
    }

    /**
     * Tampilkan daftar log notifikasi eksternal dengan filter dan paginasi
     */
    public function index(): Response
    {
        // 1. Guardrail Hak Akses Khusus Admin & Super Admin
        if (!has_role(['super_admin', 'admin'])) {
            return $this->redirect(base_url('unauthorized'));
        }

        try {
            // 2. Ambil dan validasi parameter filter GET
            $channelFilter = strtolower(trim((string)($this->request->get('channel', ''))));
            if (!in_array($channelFilter, ['email', 'whatsapp'], true)) {
                $channelFilter = '';
            }

            $statusFilter = strtolower(trim((string)($this->request->get('status', ''))));
            if (!in_array($statusFilter, ['queued', 'processing', 'sent', 'failed'], true)) {
                $statusFilter = '';
            }

            $search = trim((string)($this->request->get('search', '')));
            if (mb_strlen($search) > 100) {
                $search = mb_substr($search, 0, 100);
            }

            // 3. Konfigurasi paginasi
            $page = max(1, (int)($this->request->get('page', 1)));
            $perPage = 15;

            $filters = [
                'channel' => $channelFilter,
                'status'  => $statusFilter,
                'search'  => $search,
            ];

            // 4. Ambil data dari model
            $logs = $this->logModel->getPaginatedLogs($filters, $page, $perPage);
            $totalItems = $this->logModel->countLogs($filters);
            $totalPages = (int) ceil($totalItems / $perPage);

            // Sanitasi payload untuk setiap baris agar aman dari data sensitif
            $sanitizedLogs = array_map(function (array $log) {
                $log['safe_payload'] = $this->sanitizePayloadForDisplay($log['response_payload'] ?? null);
                return $log;
            }, $logs);

            $breadcrumbs = [
                ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                ['title' => 'Log Notifikasi Eksternal']
            ];

            $data = [
                'pageTitle'     => 'Log Notifikasi Eksternal',
                'breadcrumbs'   => $breadcrumbs,
                'logs'          => $sanitizedLogs,
                'totalItems'    => $totalItems,
                'currentPage'   => $page,
                'totalPages'    => $totalPages,
                'perPage'       => $perPage,
                'channelFilter' => $channelFilter,
                'statusFilter'  => $statusFilter,
                'search'        => $search,
            ];

            return $this->view('admin/notifications/logs', $data, 'layouts/admin');
        } catch (Throwable $e) {
            Logger::exception($e);
            Session::setFlash('danger', 'Terjadi kesalahan saat memuat log notifikasi eksternal.');
            return $this->view('admin/notifications/logs', [
                'pageTitle'     => 'Log Notifikasi Eksternal',
                'breadcrumbs'   => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Log Notifikasi Eksternal']
                ],
                'logs'          => [],
                'totalItems'    => 0,
                'currentPage'   => 1,
                'totalPages'    => 1,
                'perPage'       => 15,
                'channelFilter' => '',
                'statusFilter'  => '',
                'search'        => '',
            ], 'layouts/admin');
        }
    }

    /**
     * Tampilkan antarmuka pengiriman notifikasi kustom multi-channel dengan data pengguna terdaftar
     * serta formulir untuk memasukkan/mengedit email dan nomor WhatsApp secara mandiri.
     */
    public function compose(): Response
    {
        // 1. Guardrail Hak Akses Khusus Admin & Super Admin
        if (!has_role(['super_admin', 'admin'])) {
            return $this->redirect(base_url('unauthorized'));
        }

        try {
            $db = \App\Libraries\Database::getConnection();
            $stmt = $db->query("
                SELECT u.id, u.name, u.email, u.phone, u.username, u.nip_nisn, u.status,
                       COALESCE(r.name, 'pengguna') as role_code,
                       COALESCE(r.display_name, 'Pengguna') as role_name
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE u.deleted_at IS NULL AND u.status = 'active'
                ORDER BY r.id ASC, u.name ASC
            ");
            $registeredUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Parameter user_id opsional jika diarahkan dari halaman daftar pengguna atau log
            $preselectedUserId = (int)($this->request->get('user_id', 0));

            $breadcrumbs = [
                ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                ['title' => 'Log Notifikasi', 'url' => base_url('admin/notification-logs')],
                ['title' => 'Kirim Notifikasi']
            ];

            $mode = Notification::$mode ?: 'simulation';
            $isSmtpConfigured = Notification::isSmtpConfigured();
            $isWhatsAppConfigured = Notification::isWhatsAppConfigured();

            $data = [
                'pageTitle'            => 'Kirim Notifikasi Multi-Channel',
                'breadcrumbs'          => $breadcrumbs,
                'users'                => $registeredUsers,
                'preselectedUserId'    => $preselectedUserId,
                'notificationMode'     => $mode,
                'isSmtpConfigured'     => $isSmtpConfigured,
                'isWhatsAppConfigured' => $isWhatsAppConfigured,
                'testEmail'            => Notification::$testRecipient,
                'testPhone'            => Notification::$testPhone,
            ];

            return $this->view('admin/notifications/compose', $data, 'layouts/admin');
        } catch (Throwable $e) {
            Logger::exception($e);
            Session::setFlash('danger', 'Terjadi kesalahan saat memuat form notifikasi: ' . e($e->getMessage()));
            return $this->redirect(base_url('admin/notification-logs'));
        }
    }

    /**
     * Memproses pengiriman notifikasi kustom ke pengguna terdaftar dengan email & nomor yang dapat diedit sendiri
     */
    public function sendCustom(): Response
    {
        // 1. Guardrail Hak Akses Khusus Admin & Super Admin
        if (!has_role(['super_admin', 'admin'])) {
            return $this->redirect(base_url('unauthorized'));
        }

        $userId = (int)($this->request->post('user_id', 0));
        $recipientEmail = trim((string)($this->request->post('recipient_email', '')));
        $recipientPhone = trim((string)($this->request->post('recipient_phone', '')));
        $title = trim((string)($this->request->post('title', '')));
        $message = trim((string)($this->request->post('message', '')));
        $type = strtolower(trim((string)($this->request->post('type', 'info'))));
        $actionUrl = trim((string)($this->request->post('action_url', '')));
        $updateProfile = !empty($this->request->post('update_profile'));

        // Saluran pengiriman (in_app, whatsapp, email)
        $rawChannels = $this->request->post('channels', []);
        $channels = is_array($rawChannels) ? $rawChannels : (array)$rawChannels;
        $validChannels = array_values(array_intersect($channels, [
            NotificationService::CHANNEL_IN_APP,
            NotificationService::CHANNEL_WHATSAPP,
            NotificationService::CHANNEL_EMAIL
        ]));

        $errors = [];

        // 1. Validasi pengguna terdaftar
        if ($userId <= 0) {
            $errors[] = 'Silakan pilih pengguna yang sudah terdaftar.';
        } else {
            $targetUser = $this->userModel->find($userId);
            if (!$targetUser || !empty($targetUser['deleted_at']) || $targetUser['status'] !== 'active') {
                $errors[] = 'Pengguna yang dipilih tidak ditemukan atau sedang dinonaktifkan.';
            }
        }

        // 2. Validasi minimal satu saluran aktif
        if (empty($validChannels)) {
            $errors[] = 'Pilih minimal satu saluran pengiriman (In-App, WhatsApp, atau Email).';
        }

        // 3. Validasi judul & pesan
        if (empty($title)) {
            $errors[] = 'Judul notifikasi wajib diisi.';
        } elseif (mb_strlen($title) > 150) {
            $errors[] = 'Judul notifikasi tidak boleh melebihi 150 karakter.';
        }

        if (empty($message)) {
            $errors[] = 'Pesan notifikasi wajib diisi.';
        } elseif (mb_strlen($message) > 2000) {
            $errors[] = 'Pesan notifikasi tidak boleh melebihi 2000 karakter.';
        }

        // 4. Validasi input email & nomor telepon yang dapat diedit sendiri
        if (in_array(NotificationService::CHANNEL_EMAIL, $validChannels, true)) {
            if (empty($recipientEmail)) {
                $errors[] = 'Alamat email penerima wajib diisi untuk pengiriman saluran Email.';
            } elseif (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format alamat email penerima tidak valid.';
            }
        }

        if (in_array(NotificationService::CHANNEL_WHATSAPP, $validChannels, true)) {
            if (empty($recipientPhone)) {
                $errors[] = 'Nomor WhatsApp penerima wajib diisi untuk pengiriman saluran WhatsApp.';
            } else {
                $normPhone = Notification::normalizePhoneNumber($recipientPhone);
                if (empty($normPhone) || strlen($normPhone) < 8) {
                    $errors[] = 'Nomor WhatsApp tidak valid. Masukkan nomor telepon Indonesia (contoh: 08123456789 atau 628123456789).';
                }
            }
        }

        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                return $this->json([
                    'success' => false,
                    'error'   => implode(' ', $errors)
                ], 422);
            }
            Session::setFlash('danger', implode(' ', $errors));
            return $this->redirect(base_url('admin/notifications/compose' . ($userId > 0 ? "?user_id={$userId}" : '')));
        }

        try {
            $result = $this->notificationService->sendCustomNotification(
                $userId,
                $title,
                $message,
                in_array($type, ['info', 'warning', 'danger', 'success'], true) ? $type : 'info',
                !empty($actionUrl) ? $actionUrl : null,
                $validChannels,
                !empty($recipientEmail) ? $recipientEmail : null,
                !empty($recipientPhone) ? $recipientPhone : null,
                $updateProfile
            );

            if (empty($result['success'])) {
                if ($this->request->isAjax()) {
                    return $this->json([
                        'success' => false,
                        'error'   => $result['error'] ?? 'Pengiriman notifikasi gagal diproses.'
                    ], 500);
                }
                Session::setFlash('danger', $result['error'] ?? 'Pengiriman notifikasi gagal diproses.');
                return $this->redirect(base_url('admin/notifications/compose'));
            }

            // Bangun ringkasan laporan status per-channel
            $reports = [];
            if (isset($result['channels']['in_app'])) {
                $reports[] = 'In-App: Tersimpan di kotak masuk';
            }
            if (isset($result['channels']['whatsapp'])) {
                $waStatus = $result['channels']['whatsapp']['status'] ?? 'queued';
                $reports[] = 'WhatsApp: ' . $waStatus . ' (' . ($recipientPhone ?: '-') . ')';
            }
            if (isset($result['channels']['email'])) {
                $emStatus = $result['channels']['email']['status'] ?? 'queued';
                $reports[] = 'Email: ' . $emStatus . ' (' . ($recipientEmail ?: '-') . ')';
            }

            $successMsg = 'Notifikasi berhasil diproses! [' . implode(' | ', $reports) . '].';
            if ($updateProfile) {
                $successMsg .= ' Profil kontak pengguna di database telah diperbarui.';
            }

            if ($this->request->isAjax()) {
                return $this->json([
                    'success' => true,
                    'message' => $successMsg,
                    'data'    => $result
                ]);
            }

            Session::setFlash('success', $successMsg);
            return $this->redirect(base_url('admin/notification-logs'));
        } catch (Throwable $e) {
            Logger::exception($e);
            if ($this->request->isAjax()) {
                return $this->json([
                    'success' => false,
                    'error'   => 'Terjadi kesalahan sistem: ' . $e->getMessage()
                ], 500);
            }
            Session::setFlash('danger', 'Terjadi kesalahan sistem saat mengirim notifikasi: ' . e($e->getMessage()));
            return $this->redirect(base_url('admin/notifications/compose'));
        }
    }

    /**
     * Endpoint API JSON untuk pencarian pengguna terdaftar secara dinamis
     */
    public function getUsersList(): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $search = trim((string)($this->request->get('q', '')));
        $db = \App\Libraries\Database::getConnection();

        if (!empty($search)) {
            $stmt = $db->prepare("
                SELECT u.id, u.name, u.email, u.phone, u.username, u.nip_nisn,
                       COALESCE(r.name, 'pengguna') as role_code,
                       COALESCE(r.display_name, 'Pengguna') as role_name
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE u.deleted_at IS NULL AND u.status = 'active'
                  AND (u.name LIKE :q1 OR u.email LIKE :q2 OR u.phone LIKE :q3 OR u.username LIKE :q4 OR u.nip_nisn LIKE :q5)
                ORDER BY r.id ASC, u.name ASC
                LIMIT 30
            ");
            $like = '%' . $search . '%';
            $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like]);
        } else {
            $stmt = $db->query("
                SELECT u.id, u.name, u.email, u.phone, u.username, u.nip_nisn,
                       COALESCE(r.name, 'pengguna') as role_code,
                       COALESCE(r.display_name, 'Pengguna') as role_name
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE u.deleted_at IS NULL AND u.status = 'active'
                ORDER BY r.id ASC, u.name ASC
                LIMIT 50
            ");
        }

        return $this->json([
            'success' => true,
            'users'   => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []
        ]);
    }

    protected ?array $cachedExamTokens = null;

    /**
     * Ambil daftar token ujian yang terdaftar di database untuk penyensoran presisi
     */
    protected function getKnownExamTokens(): array
    {
        if ($this->cachedExamTokens !== null) {
            return $this->cachedExamTokens;
        }

        try {
            $db = \App\Libraries\Database::getConnection();
            $tokens = $db->query("SELECT DISTINCT token FROM exam_tokens WHERE token IS NOT NULL AND token != ''")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            $this->cachedExamTokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => $t !== '' && strlen($t) >= 3));
        } catch (\Throwable) {
            $this->cachedExamTokens = [];
        }

        return $this->cachedExamTokens;
    }

    /**
     * Sanitasi dan masking payload JSON agar aman dari token/credential
     */
    protected function sanitizePayloadForDisplay(?string $rawPayload): ?array
    {
        if (empty($rawPayload)) {
            return null;
        }

        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            // Jika bukan JSON valid, lakukan masking pada cuplikan string aman
            return ['raw_preview' => $this->maskSensitiveString(mb_substr($rawPayload, 0, 200))];
        }

        return $this->maskSensitiveValues($decoded);
    }

    /**
     * Masking rekursif untuk key dan isi string yang memuat kata kunci kredensial atau token ujian
     */
    protected function maskSensitiveValues(array $data): array
    {
        $sensitiveKeys = '/(password|token|secret|key|api_key|credential|authorization|auth)/i';

        foreach ($data as $key => $val) {
            if (is_string($key) && preg_match($sensitiveKeys, $key)) {
                $data[$key] = '***[TERPROTEKSI]***';
            } elseif (is_array($val)) {
                $data[$key] = $this->maskSensitiveValues($val);
            } elseif (is_string($val)) {
                $data[$key] = $this->maskSensitiveString($val);
            }
        }

        return $data;
    }

    /**
     * Masking isi string individual: menyamarkan token ujian riil, password, OTP, API key,
     * dan kredensial tanpa merusak format keseluruhan teks.
     */
    protected function maskSensitiveString(string $val): string
    {
        if (trim($val) === '') {
            return $val;
        }

        // Jika string merupakan representasi JSON bersarang, decode dan proses secara rekursif
        if ((str_starts_with($val, '{') && str_ends_with($val, '}')) || (str_starts_with($val, '[') && str_ends_with($val, ']'))) {
            $nested = json_decode($val, true);
            if (is_array($nested)) {
                $maskedNested = $this->maskSensitiveValues($nested);
                return json_encode($maskedNested, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $val;
            }
        }

        // 1. Masking token ujian riil yang terdaftar di database sistem (akurasi 100% tanpa salah sensor)
        $knownTokens = $this->getKnownExamTokens();
        foreach ($knownTokens as $token) {
            if ($token !== '') {
                $val = str_ireplace($token, '***[TERPROTEKSI]***', $val);
            }
        }

        // 2. Masking token format bracket [TOKEN] (hindari penumpukan jika sudah disamarkan)
        $val = preg_replace('/\[(?![\*\[]*TERPROTEKSI)[A-Z0-9]{5,12}\]/', '[***[TERPROTEKSI]***]', $val);

        // 3. Masking token dengan penanda eksplisit (contoh: "token: XYZ", "token adalah XYZ", "token = XYZ")
        $val = preg_replace('/(\btoken\b\s*(?::|=|adalah)\s*)(?![\*\[]*TERPROTEKSI)[A-Za-z0-9]{5,12}\b/i', '$1***[TERPROTEKSI]***', $val);

        // Bersihkan jika terjadi penumpukan tag proteksi
        $val = preg_replace('/(\*{3}\[)+TERPROTEKSI(\]\*{3})+/', '***[TERPROTEKSI]***', $val);
        $val = preg_replace('/\[(\*{3}\[)+TERPROTEKSI(\]\*{3})+\]/', '[***[TERPROTEKSI]***]', $val);

        // 3. Masking Password / Kata Sandi dalam teks
        $val = preg_replace('/(\b(?:password|kata sandi|passwd)\b\s*[:=]\s*)([^\s,;"\'\]\}]+)/i', '$1***[TERPROTEKSI]***', $val);

        // 4. Masking OTP 6-digit dalam teks
        $val = preg_replace('/(\b(?:otp|kode otp|kode verifikasi)\b\s*[:=]?\s*)\b(\d{6})\b/i', '$1***[TERPROTEKSI]***', $val);

        // 5. Masking API Key / Secret Key dalam teks
        $val = preg_replace('/(\b(?:api_key|apikey|secret_key|api-key)\b\s*[:=]\s*)([^\s,;"\'\]\}]+)/i', '$1***[TERPROTEKSI]***', $val);

        // 6. Masking Authorization / Bearer token dalam teks
        $val = preg_replace('/(\b(?:authorization|bearer)\b\s*[:=]?\s*(?:bearer\s+)?)([A-Za-z0-9_\-\.]{15,})/i', '$1***[TERPROTEKSI]***', $val);

        // 7. Masking Google / Cloud API Keys spesifik (AIzaSy...)
        $val = preg_replace('/\b(AIza[0-9A-Za-z-_]{35})\b/', '***[TERPROTEKSI]***', $val);

        return $val;
    }
}
