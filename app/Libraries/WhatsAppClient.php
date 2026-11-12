<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Config\App;
use App\Config\Notification;
use RuntimeException;
use Throwable;

/**
 * ==============================================================================
 * SMART EXAM — NATIVE WHATSAPP GATEWAY CLIENT
 * ==============================================================================
 * Client HTTP mandiri untuk pengiriman notifikasi WhatsApp Smart Exam.
 * Mendukung berbagai gateway (Fonnte, Wablas, Mock Router Lokal 127.0.0.1:8088,
 * serta Generic REST WhatsApp Gateway).
 *
 * FITUR KEAMANAN & PRIVASI:
 * - Kredensial (API Token / Secret Key) TIDAK PERNAH dibocorkan ke log/database.
 * - Guardrail NOTIFICATION_TEST_PHONE untuk membatasi pengiriman hanya ke nomor tester saat sandbox.
 * - Sanitasi otomatis token ujian ([TERPROTEKSI]) untuk menjaga kerahasiaan ujian siswa.
 * - Normalisasi nomor telepon otomatis (format E.164 tanpa '+', diawali '62').
 * - Idempotency Key (X-Idempotency-Key & client_reference_id) mencegah pesan terkirim ganda saat retry.
 * - Klasifikasi error HTTP presisi (Permanent vs Temporary) untuk retry exponential backoff antrean.
 * ==============================================================================
 */
class WhatsAppClient
{
    protected string $apiUrl;
    protected string $apiToken;
    protected string $sender;
    protected ?string $testPhone = null;
    protected bool $isExplicitTestPhone = false;
    protected int $connectTimeout = 5;
    protected int $timeout = 15;

    /**
     * Inisialisasi WhatsApp Client dengan konfigurasi dari App\Config\Notification
     */
    public function __construct(?array $config = null)
    {
        Notification::load();

        $this->apiUrl         = trim((string)($config['api_url'] ?? Notification::$whatsappApiUrl));
        $this->apiToken       = trim((string)($config['api_token'] ?? Notification::$whatsappApiToken));
        $this->sender         = trim((string)($config['sender'] ?? Notification::$whatsappSender));
        $this->connectTimeout = (int)($config['connect_timeout'] ?? Notification::$curlConnectTimeout);
        $this->timeout        = (int)($config['timeout'] ?? Notification::$curlTimeout);

        // Fallback default endpoint jika apiUrl kosong
        if (empty($this->apiUrl)) {
            $this->apiUrl = Notification::$whatsappEndpoint ?: 'http://127.0.0.1:8088/api/send';
        }

        $isLoopbackMock = (str_contains($this->apiUrl, '127.0.0.1') || str_contains($this->apiUrl, 'localhost'));
        if ($config !== null && array_key_exists('test_phone', $config)) {
            $testPh = $config['test_phone'];
            $this->isExplicitTestPhone = true;
        } else {
            $testPh = $isLoopbackMock ? null : Notification::$testPhone;
        }
        if (!empty($testPh)) {
            $parsedPhones = array_map([Notification::class, 'normalizePhoneNumber'], explode(',', (string)$testPh));
            $this->testPhone = implode(',', array_filter($parsedPhones));
        } else {
            $this->testPhone = null;
        }
    }

    /**
     * Cek apakah client WhatsApp siap digunakan
     */
    public function isConfigured(): bool
    {
        if (empty($this->apiUrl)) {
            return false;
        }
        $isLocalMock = (str_contains($this->apiUrl, '127.0.0.1') || str_contains($this->apiUrl, 'localhost'));
        if (!$isLocalMock && empty($this->apiToken)) {
            return false;
        }
        return true;
    }

    /**
     * Uji koneksi dan status gateway WhatsApp (Fonnte API / Local Mock)
     *
     * @return array [success => bool, message => string, device_status => string|null, details => array]
     */
    public function verifyConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi WhatsApp Gateway belum lengkap: Token atau URL API belum diisi.',
                'device_status' => 'not_configured'
            ];
        }

        $isLocalMock = (str_contains($this->apiUrl, '127.0.0.1') || str_contains($this->apiUrl, 'localhost'));
        if ($isLocalMock) {
            $parsedUrl = parse_url($this->apiUrl);
            $host = $parsedUrl['host'] ?? '127.0.0.1';
            $port = $parsedUrl['port'] ?? 8088;
            $fp = @fsockopen($host, (int)$port, $errno, $errstr, 2);
            if ($fp) {
                fclose($fp);
                return [
                    'success' => true,
                    'message' => "Gateway WhatsApp lokal terhubung pada {$host}:{$port}.",
                    'device_status' => 'connected'
                ];
            }
            return [
                'success' => false,
                'message' => "Gateway WhatsApp lokal tidak merespons pada {$host}:{$port} [{$errstr}].",
                'device_status' => 'disconnected'
            ];
        }

        $deviceEndpoint = 'https://api.fonnte.com/device';
        $ch = curl_init($deviceEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$this->apiToken}",
                'User-Agent: SmartExam-WAClient/1.0'
            ]
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Gagal menghubungi server Fonnte: ' . $this->maskSensitiveText($curlError),
                'device_status' => 'network_error'
            ];
        }

        $json = json_decode((string)$rawResponse, true);
        if ($httpCode === 200 && is_array($json)) {
            $deviceStatus = strtolower((string)($json['device_status'] ?? ''));
            $devicePhone = $json['device'] ?? '-';
            $quota = $json['quota'] ?? '-';
            $name = $json['name'] ?? 'Smart Exam';

            if ($deviceStatus === 'connect' || $deviceStatus === 'connected') {
                return [
                    'success' => true,
                    'message' => "WhatsApp Gateway Terhubung Aktif! Perangkat: {$devicePhone} ({$name}) | Sisa Kuota: {$quota}",
                    'device_status' => 'connected',
                    'details' => $json
                ];
            }

            return [
                'success' => false,
                'message' => "Token valid, namun status perangkat: [{$deviceStatus}]. Silakan scan QR WhatsApp pada dashboard Fonnte.",
                'device_status' => $deviceStatus ?: 'disconnected',
                'details' => $json
            ];
        }

        return [
            'success' => false,
            'message' => "Server Fonnte mengembalikan HTTP {$httpCode}: " . ($json['reason'] ?? 'Respons tidak dikenal.'),
            'device_status' => 'auth_error'
        ];
    }

    /**
     * Normalisasi nomor telepon ke format internasional standar (628xxx)
     */
    public function normalizePhoneNumber(string $phone): string
    {
        return Notification::normalizePhoneNumber($phone);
    }

    /**
     * Format teks pesan WhatsApp dengan format terstruktur, informatif, dan profesional
     */
    public function formatMessage(
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $metadata = []
    ): string {
        if (!empty($metadata['formatted_message'])) {
            return (string)$metadata['formatted_message'];
        }

        // Jika pesan adalah OTP verifikasi, jangan tambahkan elemen dekorasi tambahan
        if (($metadata['type'] ?? '') === 'otp_verification' || str_starts_with(trim($message), '*Smart Exam - Kode Verifikasi*')) {
            return trim($message);
        }

        $dateStr = date('d M Y H:i') . ' WIB';
        $appName = \App\Config\App::$appName ?: 'Smart Exam';

        $cleanTitle = trim($title);
        $cleanMessage = trim($message);

        $formatted = "🔔 *{$appName} — {$cleanTitle}*\n\n";
        $formatted .= "{$cleanMessage}\n\n";

        if (!empty($actionUrl)) {
            // Bentuk URL absolut jika masih relatif
            $fullUrl = $actionUrl;
            if (str_starts_with($actionUrl, '/')) {
                $baseUrl = rtrim((string)App::env('APP_URL', 'http://localhost/PRD/public'), '/');
                $fullUrl = $baseUrl . $actionUrl;
            }
            $formatted .= "🌐 *Tautan Akses:*\n{$fullUrl}\n\n";
        }

        $formatted .= "⏰ _Waktu: {$dateStr}_\n";
        $formatted .= "🔒 _Pesan otomatis sistem {$appName}._";

        return $formatted;
    }

    /**
     * Format pesan WhatsApp khusus kode verifikasi OTP standar final Smart Exam
     */
    public static function formatOtpMessage(
        string $otp,
        ?string $userName = null,
        int $expiresMinutes = 5,
        ?string $actionUrl = null
    ): string {
        $appName = \App\Config\App::$appName ?: 'Smart Exam';

        $msg = "*{$appName} - Kode Verifikasi*\n\n";
        $msg .= "Kode OTP kamu: *{$otp}*\n\n";
        $msg .= "Kode berlaku selama {$expiresMinutes} menit. Jangan bagikan kode ini kepada siapa pun.\n\n";
        $msg .= "Jika kamu tidak meminta kode ini, abaikan pesan tersebut.";

        return $msg;
    }

    /**
     * Kirim notifikasi pesan WhatsApp
     *
     * @param string $to Nomor telepon penerima (contoh: 08123456789 atau 628123456789)
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan notifikasi
     * @param string|null $actionUrl Tautan terkait (opsional)
     * @param array $metadata Data tambahan (log_id, user_id, user_role, client_reference_id, dll)
     * @return array Status hasil pengiriman terstandarisasi untuk antrean worker
     */
    public function send(
        string $to,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $metadata = []
    ): array {
        $now = date('Y-m-d H:i:s');
        $normalizedPhone = $this->normalizePhoneNumber($to);

        // 1. Validasi format dan panjang nomor telepon
        if (empty($normalizedPhone) || strlen($normalizedPhone) < 8 || strlen($normalizedPhone) > 16) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'error_reason' => "Nomor telepon WhatsApp tidak valid: '{$to}' (harus 8-16 digit angka).",
                'processed_at' => $now
            ];
        }

        // 2. Guardrail Penerima Pengujian (Testing / Sandbox Recipient Whitelist - Mendukung multiple dipisah koma)
        if (!empty($this->testPhone)) {
            $allowedPhones = array_map([Notification::class, 'normalizePhoneNumber'], explode(',', $this->testPhone));
            $inWhitelist = in_array($normalizedPhone, $allowedPhones, true);

            // Jika whitelist eksplisit dari constructor (misal unit test), patuhi whitelist ketat
            if ($this->isExplicitTestPhone && !$inWhitelist) {
                return [
                    'status'       => 'failed',
                    'is_permanent' => true,
                    'is_temporary' => false,
                    'http_code'    => 403,
                    'error_reason' => "Pengiriman ditolak oleh guardrail pengujian: Nomor '{$normalizedPhone}' " .
                                      "berbeda dari nomor pengujian yang diizinkan ('{$this->testPhone}').",
                    'processed_at' => $now
                ];
            }

            // Jika whitelist default aplikasi, izinkan jika ada di whitelist ATAU merupakan akun pengguna terdaftar
            if (!$inWhitelist && !Notification::isRegisteredUserPhone($normalizedPhone)) {
                return [
                    'status'       => 'failed',
                    'is_permanent' => true,
                    'is_temporary' => false,
                    'http_code'    => 403,
                    'error_reason' => "Pengiriman ditolak oleh guardrail pengujian: Nomor '{$normalizedPhone}' " .
                                      "berbeda dari nomor pengujian yang diizinkan ('{$this->testPhone}') dan bukan akun terdaftar.",
                    'processed_at' => $now
                ];
            }
        }

        // 3. Sanitasi Privasi: Jika penerima adalah siswa, sensor token ujian (Kecuali pesan verifikasi OTP)
        $userRole = strtolower(trim((string)($metadata['user_role'] ?? '')));
        $isOtp = (($metadata['type'] ?? '') === 'otp_verification');
        if (in_array($userRole, ['siswa', 'peserta_didik'], true) && !$isOtp) {
            $message = $this->sanitizeStudentContent($message);
            $title   = $this->sanitizeStudentContent($title);
        }

        // 4. Susun pesan WhatsApp dengan template detail
        $bodyText = $this->formatMessage($title, $message, $actionUrl, $metadata);

        // 5. Tentukan client_reference_id untuk idempotensi gateway
        $logId = (int)($metadata['log_id'] ?? 0);
        $clientRefId = trim((string)($metadata['client_reference_id'] ?? ($logId > 0 ? "smart_exam_notif_{$logId}" : '')));
        if (empty($clientRefId)) {
            $clientRefId = 'smart_exam_' . bin2hex(random_bytes(6));
        }

        // 6. Eksekusi pengiriman via cURL
        return $this->executeCurlPost($normalizedPhone, $bodyText, $clientRefId, $metadata);
    }

    /**
     * Eksekusi HTTP Request ke WhatsApp Gateway via cURL
     */
    protected function executeCurlPost(
        string $phone,
        string $message,
        string $clientRefId,
        array $metadata = []
    ): array {
        $now = date('Y-m-d H:i:s');
        $endpoint = $this->apiUrl;

        $isLocalMock = (str_contains($endpoint, '127.0.0.1') || str_contains($endpoint, 'localhost'));
        $isFonnte = str_contains($endpoint, 'fonnte.com');

        // Jika endpoint eksternal/Fonnte tetapi token API belum diatur, tolak langsung sebagai error permanen (tanpa cURL)
        if (!$isLocalMock && empty($this->apiToken)) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'http_code'    => 401,
                'error_reason' => 'Token API WhatsApp belum dikonfigurasi (WHATSAPP_API_TOKEN kosong di .env).',
                'processed_at' => $now
            ];
        }

        // Susun payload sesuai target provider
        if ($isFonnte) {
            $postFields = [
                'target'      => $phone,
                'message'     => $message,
                'countryCode' => '62'
            ];
            $payloadString = http_build_query($postFields);
            $headers = [
                'Authorization: ' . $this->apiToken,
                'Content-Type: application/x-www-form-urlencoded',
                'X-Idempotency-Key: ' . $clientRefId
            ];
        } else {
            // Format REST JSON standar (kompatibel dengan Mock Router Smart Exam, Wablas, & Generic Gateway)
            $postArray = [
                'target'              => $phone,
                'phone'               => $phone,
                'message'             => $message,
                'client_reference_id' => $clientRefId
            ];
            if (!empty($this->sender)) {
                $postArray['sender'] = $this->sender;
            }
            $payloadString = json_encode($postArray, JSON_UNESCAPED_UNICODE);
            $headers = [
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . $clientRefId
            ];
            if (!empty($this->apiToken)) {
                $headers[] = 'Authorization: ' . $this->apiToken;
            }
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return [
                'status'       => 'transport_error',
                'is_permanent' => false,
                'is_temporary' => true,
                'error_reason' => 'Gagal menginisialisasi cURL handler untuk WhatsApp Gateway.',
                'processed_at' => $now
            ];
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Mencegah redirect ke URL luar tak terduga
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        // Abaikan SSL verification hanya untuk localhost testing
        if ($isLocalMock) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }

        $responseBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 1. Penanganan cURL Transport Error (Timeout / Connection Refused)
        if ($curlErrno !== 0) {
            $isTimeout = ($curlErrno === CURLE_OPERATION_TIMEDOUT);
            return [
                'status'       => $isTimeout ? 'ambiguous_timeout' : 'transport_error',
                'is_permanent' => false,
                'is_temporary' => true,
                'curl_errno'   => $curlErrno,
                'error_reason' => $isTimeout
                    ? "WhatsApp Gateway Timeout: Melebihi batas {$this->timeout} detik (Status ambigu, perlu rekonsiliasi)."
                    : "cURL Transport Error [{$curlErrno}]: " . $this->maskSensitiveText($curlError),
                'processed_at' => $now
            ];
        }

        // 2. Parse response JSON
        $decoded = is_string($responseBody) ? json_decode($responseBody, true) : null;

        // 3. Status HTTP 200 OK
        if ($httpCode === 200) {
            if (!is_array($decoded)) {
                return [
                    'status'       => 'invalid_response',
                    'is_permanent' => false,
                    'is_temporary' => true,
                    'http_code'    => 200,
                    'error_reason' => 'Respons WhatsApp Gateway HTTP 200 bukan format JSON valid.',
                    'raw_body'     => mb_substr((string)$responseBody, 0, 150),
                    'processed_at' => $now
                ];
            }

            // Periksa jika provider mengembalikan status false dalam body walau HTTP 200 (seperti Fonnte jika token invalid)
            if (isset($decoded['status']) && $decoded['status'] === false) {
                $reason = $decoded['reason'] ?? $decoded['error'] ?? $decoded['message'] ?? 'Gateway menolak pengiriman.';
                return [
                    'status'          => 'failed',
                    'is_permanent'    => true,
                    'is_temporary'    => false,
                    'http_code'       => 200,
                    'provider_status' => 'failed',
                    'error_reason'    => 'WhatsApp Gateway: ' . $this->maskSensitiveText((string)$reason),
                    'payload'         => $decoded,
                    'processed_at'    => $now
                ];
            }

            $rawId = $decoded['message_id'] ?? $decoded['id'] ?? null;
            if (is_array($rawId)) {
                $messageId = (string)reset($rawId);
            } elseif (!empty($rawId)) {
                $messageId = (string)$rawId;
            } else {
                $messageId = 'WA-' . bin2hex(random_bytes(6));
            }

            // Bedakan API Accepted (diterima provider ke antrean) dengan Delivered (terkirim ke handphone penerima)
            $providerStatus = $isFonnte ? 'api_accepted' : ($decoded['process'] ?? ($decoded['detail'] ?? 'sent'));

            return [
                'status'              => 'sent',
                'is_permanent'        => false,
                'is_temporary'        => false,
                'http_code'           => 200,
                'message_id'          => $messageId,
                'provider_status'     => (string)$providerStatus,
                'duplicate_prevented' => !empty($decoded['duplicate_prevented']),
                'client_reference_id' => $clientRefId,
                'payload'             => $decoded,
                'processed_at'        => $now
            ];
        }

        // 4. Status HTTP 400 Bad Request / 404 / 422 (Permanent Error - Jangan Retry)
        if (in_array($httpCode, [400, 404, 422], true)) {
            $reason = is_array($decoded) 
                ? ($decoded['error'] ?? $decoded['message'] ?? 'Bad Request') 
                : "HTTP Error {$httpCode}";
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'http_code'    => $httpCode,
                'error_reason' => "WhatsApp Gateway [HTTP {$httpCode}]: " . $this->maskSensitiveText((string)$reason),
                'processed_at' => $now
            ];
        }

        // 5. Status HTTP 401 Unauthorized / 403 Forbidden (Permanent Error - Token Salah)
        if (in_array($httpCode, [401, 403], true)) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'http_code'    => $httpCode,
                'error_reason' => "Autentikasi WhatsApp Gateway ditolak [HTTP {$httpCode}]: API Token tidak valid atau hak akses ditolak.",
                'processed_at' => $now
            ];
        }

        // 6. Status HTTP 429 Too Many Requests (Temporary Error - Rate Limiting)
        if ($httpCode === 429) {
            return [
                'status'       => 'rate_limited',
                'is_permanent' => false,
                'is_temporary' => true,
                'http_code'    => 429,
                'error_reason' => 'Batas kecepatan WhatsApp Gateway terlampaui (HTTP 429 Rate Limited).',
                'processed_at' => $now
            ];
        }

        // 7. Status HTTP 5xx Server Error (Temporary Error - Retry Backoff)
        if ($httpCode >= 500 && $httpCode <= 599) {
            $reason = is_array($decoded) ? ($decoded['error'] ?? 'Internal Server Error') : "HTTP {$httpCode}";
            return [
                'status'       => 'upstream_error',
                'is_permanent' => false,
                'is_temporary' => true,
                'http_code'    => $httpCode,
                'error_reason' => "WhatsApp Gateway gangguan upstream [HTTP {$httpCode}]: " . $this->maskSensitiveText((string)$reason),
                'processed_at' => $now
            ];
        }

        // 8. Default fallback
        return [
            'status'       => 'failed',
            'is_permanent' => false,
            'is_temporary' => true,
            'http_code'    => $httpCode,
            'error_reason' => "WhatsApp Gateway mengembalikan status tak terduga [HTTP {$httpCode}].",
            'processed_at' => $now
        ];
    }

    protected function sanitizeStudentContent(string $content): string
    {
        // 1. Sensor seluruh token aktif yang terdaftar di database exam_tokens
        try {
            $db = \App\Libraries\Database::getConnection();
            $tokens = $db->query("SELECT DISTINCT token FROM exam_tokens WHERE token IS NOT NULL AND token != ''")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            foreach ($tokens as $tkn) {
                $tkn = trim((string)$tkn);
                if (strlen($tkn) >= 3) {
                    $content = str_ireplace($tkn, '[TERPROTEKSI]', $content);
                }
            }
        } catch (\Throwable) {
            // Lanjutkan jika DB offline
        }

        // 2. Sensor pola token berpenanda eksplisit (Token:, Token ujian Anda:, Token = XYZ, dll)
        $scrubbed = preg_replace('/(\btoken\b[^\:\=\n]{0,30}(?::|=|adalah)\s*)(?!\[?TERPROTEKSI)[A-Za-z0-9]{4,15}\b/i', '$1[TERPROTEKSI]', $content);
        // 3. Sensor pola bracket [TOKEN]
        $scrubbed = preg_replace('/\[(?!TERPROTEKSI)[A-Za-z0-9]{4,15}\]/i', '[TERPROTEKSI]', (string)$scrubbed);
        // 4. Bersihkan penumpukan tag
        $scrubbed = preg_replace('/(\[TERPROTEKSI\])+/', '[TERPROTEKSI]', (string)$scrubbed);

        return $scrubbed ?: $content;
    }

    /**
     * Sensor teks sensitif (token, password, bearer key) agar tidak bocor ke log
     */
    protected function maskSensitiveText(string $text): string
    {
        if (!empty($this->apiToken)) {
            $text = str_ireplace($this->apiToken, '***[TERPROTEKSI]***', $text);
        }
        return preg_replace('/(bearer\s+)[A-Za-z0-9_\-\.]{8,}/i', '$1***[TERPROTEKSI]***', $text) ?: $text;
    }
}
