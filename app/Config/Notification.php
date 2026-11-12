<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;
use InvalidArgumentException;

/**
 * Notification Configuration Class
 * Mengatur mode eksekusi notifikasi (simulation, sandbox, production)
 * dengan guardrail keamanan ketat terhadap host loopback dan proteksi database.
 */
class Notification
{
    public const MODE_SIMULATION = 'simulation';
    public const MODE_SANDBOX    = 'sandbox';
    public const MODE_PRODUCTION = 'production';

    // Mode default aplikasi: SELALU SIMULATION
    public static string $mode = self::MODE_SIMULATION;

    // Konfigurasi endpoint WhatsApp Mock & Provider Gateway
    public static string $whatsappEndpoint = 'http://127.0.0.1:8088/api/send';
    public static string $whatsappApiUrl   = 'http://127.0.0.1:8088/api/send';
    public static string $whatsappApiToken = '';
    public static string $whatsappSender   = '';
    public static ?string $testPhone       = null;

    // Konfigurasi host SMTP (Default: smtp.gmail.com atau Mock Sink 127.0.0.1)
    public static string $smtpHost       = 'smtp.gmail.com';
    public static int $smtpPort          = 587;
    public static string $smtpEncryption = 'tls'; // 'tls' (STARTTLS), 'ssl' (Direct SSL), 'none'
    public static string $smtpUsername   = '';
    public static string $smtpPassword   = '';
    public static string $smtpFromEmail  = '';
    public static string $smtpFromName   = 'Smart Exam';
    public static ?string $smtpReplyTo   = null;

    // Guardrail pembatasan penerima pengujian (Opsional: membatasi pengiriman hanya ke email penguji)
    public static ?string $testRecipient = null;

    // Timeout jaringan terukur (dalam detik)
    public static int $curlConnectTimeout = 5;
    public static int $curlTimeout        = 15;
    public static int $smtpConnectTimeout = 10;
    public static int $smtpTimeout        = 15;

    // Pengaturan antrean & retry backoff (dalam detik)
    public static int $leaseTimeoutSeconds = 300;
    public static int $retryDelayAttempt1  = 60;   // Jeda retry 1: 1 menit
    public static int $retryDelayAttempt2  = 300;  // Jeda retry 2: 5 menit
    public static int $maxAttempts         = 3;

    protected static bool $loaded = false;

    /**
     * Muat konfigurasi awal dari file .env
     */
    public static function load(bool $force = false): void
    {
        if (self::$loaded && !$force) {
            return;
        }
        self::$loaded = true;

        $rawMode = strtolower(trim((string)App::env('NOTIFICATION_MODE', self::MODE_SIMULATION)));
        if (!in_array($rawMode, [self::MODE_SIMULATION, self::MODE_SANDBOX], true)) {
            // Default aman jika mode tidak dikenal atau jika diset 'production'
            self::$mode = self::MODE_SIMULATION;
        } else {
            self::$mode = $rawMode;
        }

        $envHost = trim((string)App::env('SMTP_HOST', ''));
        self::$smtpHost       = !empty($envHost) ? $envHost : (self::$mode === self::MODE_SANDBOX ? '127.0.0.1' : 'smtp.gmail.com');
        $envPort = (int)App::env('SMTP_PORT', 0);
        self::$smtpPort       = ($envPort > 0) ? $envPort : (self::$smtpHost === '127.0.0.1' ? 1025 : 587);
        self::$smtpEncryption = strtolower(trim((string)App::env('SMTP_ENCRYPTION', (self::$smtpHost === '127.0.0.1' ? 'none' : 'tls'))));
        self::$smtpUsername   = trim((string)App::env('SMTP_USERNAME', ''));
        self::$smtpPassword   = trim((string)App::env('SMTP_PASSWORD', ''));

        $fromEmail = trim((string)App::env('SMTP_FROM_EMAIL', ''));
        if (empty($fromEmail) && filter_var(self::$smtpUsername, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = self::$smtpUsername;
        }
        self::$smtpFromEmail  = $fromEmail;
        self::$smtpFromName   = trim((string)App::env('SMTP_FROM_NAME', 'Smart Exam'));
        $replyTo              = trim((string)App::env('SMTP_REPLY_TO', ''));
        self::$smtpReplyTo    = !empty($replyTo) ? $replyTo : $fromEmail;

        $testRec = trim((string)App::env('NOTIFICATION_TEST_RECIPIENT', ''));
        self::$testRecipient  = !empty($testRec) ? $testRec : null;

        // Muat konfigurasi WhatsApp Gateway & Guardrail Telepon
        $envWaUrl = trim((string)App::env('WHATSAPP_API_URL', ''));
        if (!empty($envWaUrl)) {
            self::$whatsappApiUrl   = $envWaUrl;
            self::$whatsappEndpoint = $envWaUrl;
        } else {
            self::$whatsappApiUrl   = 'http://127.0.0.1:8088/api/send';
            self::$whatsappEndpoint = 'http://127.0.0.1:8088/api/send';
        }
        self::$whatsappApiToken = trim((string)App::env('WHATSAPP_API_TOKEN', ''));
        self::$whatsappSender   = trim((string)App::env('WHATSAPP_SENDER', ''));

        $testPhone = trim((string)App::env('NOTIFICATION_TEST_PHONE', ''));
        if (!empty($testPhone)) {
            $parsedPhones = array_map([self::class, 'normalizePhoneNumber'], explode(',', $testPhone));
            self::$testPhone = implode(',', array_filter($parsedPhones));
        } else {
            self::$testPhone = null;
        }
    }

    /**
     * Atur mode secara terprogram dengan guardrail ketat
     */
    public static function setMode(string $mode): void
    {
        self::$loaded = true;
        $cleanMode = strtolower(trim($mode));

        if ($cleanMode === self::MODE_PRODUCTION) {
            throw new RuntimeException("KEAMANAN KRITIS: Mode 'production' tidak diizinkan pada tahap ini.");
        }

        if (!in_array($cleanMode, [self::MODE_SIMULATION, self::MODE_SANDBOX], true)) {
            throw new InvalidArgumentException("Mode notifikasi tidak valid: '{$mode}'. Pilihan: simulation, sandbox.");
        }

        self::$mode = $cleanMode;

        // Jika berpindah ke mode sandbox dan host belum diatur secara eksplisit di .env, defaultkan ke mock sink lokal
        if ($cleanMode === self::MODE_SANDBOX && empty(App::env('SMTP_HOST'))) {
            self::$smtpHost = '127.0.0.1';
            self::$smtpPort = 1025;
            self::$smtpEncryption = 'none';
        }
    }

    /**
     * Dapatkan mode aktif saat ini
     */
    public static function getMode(): string
    {
        return self::$mode;
    }

    /**
     * Cek apakah host diizinkan untuk mode sandbox (Hanya 127.0.0.1 dan localhost)
     */
    public static function isAllowedSandboxHost(string $host): bool
    {
        $cleanHost = strtolower(trim($host));
        return in_array($cleanHost, ['127.0.0.1', 'localhost'], true);
    }

    /**
     * Validasi URL endpoint WhatsApp Mock
     * Menolak skema non-http/https, menolak hostname eksternal
     */
    public static function validateSandboxEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        if (!self::isAllowedSandboxHost($host)) {
            return false;
        }

        // Mode sandbox hanya boleh menggunakan protokol HTTP ke localhost
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http') {
            return false;
        }

        return true;
    }

    /**
     * Guardrail Integritas Database Aktual
     * Menolak keras eksekusi worker jika database aktif adalah database operasional.
     */
    public static function validateActiveDatabase(string $actualDb): void
    {
        $cleanDb = strtolower(trim($actualDb));
        if ($cleanDb === 'ujian_dual_kamera') {
            throw new RuntimeException(
                "KEAMANAN KRITIS: Database aktif terdeteksi sebagai 'ujian_dual_kamera' (OPERASIONAL). " .
                "Seluruh proses worker dan dispatch notifikasi DIBATALKAN SEGERA untuk melindungi data siswa."
            );
        }
    }

    /**
     * Periksa apakah konfigurasi SMTP Gmail sudah terisi lengkap
     */
    public static function isSmtpConfigured(): bool
    {
        return !empty(self::$smtpHost)
            && self::$smtpPort > 0
            && !empty(self::$smtpUsername)
            && !empty(self::$smtpPassword)
            && !empty(self::$smtpFromEmail);
    }

    /**
     * Dapatkan daftar nama parameter SMTP yang masih kosong
     */
    public static function getMissingSmtpFields(): array
    {
        $missing = [];
        if (empty(self::$smtpHost)) {
            $missing[] = 'SMTP_HOST';
        }
        if (self::$smtpPort <= 0) {
            $missing[] = 'SMTP_PORT';
        }
        if (empty(self::$smtpUsername)) {
            $missing[] = 'SMTP_USERNAME';
        }
        if (empty(self::$smtpPassword)) {
            $missing[] = 'SMTP_PASSWORD';
        }
        if (empty(self::$smtpFromEmail)) {
            $missing[] = 'SMTP_FROM_EMAIL';
        }
        return $missing;
    }

    /**
     * Dapatkan ringkasan konfigurasi SMTP aman tanpa membocorkan password/secret
     */
    public static function getSmtpConfigSummary(): array
    {
        return [
            'host'          => self::$smtpHost,
            'port'          => self::$smtpPort,
            'encryption'    => self::$smtpEncryption,
            'from_email'    => self::$smtpFromEmail,
            'from_name'     => self::$smtpFromName,
            'username_set'  => !empty(self::$smtpUsername),
            'password_set'  => !empty(self::$smtpPassword),
            'is_configured' => self::isSmtpConfigured(),
            'missing'       => self::getMissingSmtpFields(),
            'test_recipient'=> self::$testRecipient
        ];
    }

    /**
     * Periksa apakah alamat email terdaftar pada akun pengguna di database
     */
    public static function isRegisteredUserEmail(string $email): bool
    {
        $clean = strtolower(trim($email));
        if (empty($clean)) {
            return false;
        }

        try {
            $db = \App\Libraries\Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1");
            $stmt->execute([':email' => $clean]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Periksa apakah nomor telepon terdaftar pada profil akun pengguna di database
     */
    public static function isRegisteredUserPhone(string $phone): bool
    {
        $normalized = self::normalizePhoneNumber($phone);
        if (empty($normalized) || strlen($normalized) < 8) {
            return false;
        }

        try {
            $db = \App\Libraries\Database::getConnection();
            $withZero = str_starts_with($normalized, '62') ? '0' . substr($normalized, 2) : $normalized;
            $stmt = $db->prepare("
                SELECT id FROM users 
                WHERE REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', '') = :norm1 
                   OR phone = :norm2 
                   OR phone = :withZero 
                LIMIT 1
            ");
            $stmt->execute([
                ':norm1'    => $normalized,
                ':norm2'    => $normalized,
                ':withZero' => $withZero
            ]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Guardrail Penerima Email: Memastikan pengujian email hanya dikirim ke penerima yang diizinkan
     */
    public static function isAllowedRecipient(string $recipient): bool
    {
        $clean = strtolower(trim($recipient));
        if (empty($clean) || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // 1. Izinkan jika penerima adalah akun pengguna yang terdaftar di database sistem
        if (self::isRegisteredUserEmail($clean)) {
            return true;
        }

        // 2. Jika variabel pembatasan NOTIFICATION_TEST_RECIPIENT diset (mendukung multi penerima dengan pemisah koma)
        if (self::$testRecipient !== null && self::$testRecipient !== '') {
            $allowed = array_map('trim', explode(',', strtolower(self::$testRecipient)));
            return in_array($clean, $allowed, true);
        }

        // Jika tidak diset dan mode simulation, tetap aman
        return true;
    }

    /**
     * Normalisasi nomor telepon ke format standar (E.164 tanpa +, diawali 62)
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return '';
        }

        // 08123456789 -> 628123456789
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }

        return $clean;
    }

    /**
     * Guardrail Penerima WhatsApp: Memastikan pengujian nomor hanya dikirim ke nomor yang diizinkan
     */
    public static function isAllowedPhoneRecipient(string $phone): bool
    {
        $normalized = self::normalizePhoneNumber($phone);
        if (empty($normalized) || strlen($normalized) < 8) {
            return false;
        }

        // 1. Izinkan jika nomor telepon terdaftar pada profil pengguna di database sistem
        if (self::isRegisteredUserPhone($normalized)) {
            return true;
        }

        // 2. Jika variabel pembatasan NOTIFICATION_TEST_PHONE diset (mendukung multi nomor dengan pemisah koma)
        if (self::$testPhone !== null && self::$testPhone !== '') {
            $allowed = array_map([self::class, 'normalizePhoneNumber'], explode(',', self::$testPhone));
            return in_array($normalized, $allowed, true);
        }

        return true;
    }

    /**
     * Periksa apakah konfigurasi WhatsApp sudah terisi lengkap
     */
    public static function isWhatsAppConfigured(): bool
    {
        if (empty(self::$whatsappApiUrl)) {
            return false;
        }
        $host = parse_url(self::$whatsappApiUrl, PHP_URL_HOST) ?: '';
        $isLoopback = self::isAllowedSandboxHost((string)$host);
        if (!$isLoopback && empty(self::$whatsappApiToken)) {
            return false;
        }
        return true;
    }

    /**
     * Dapatkan daftar nama parameter WhatsApp yang masih kosong
     */
    public static function getMissingWhatsAppFields(): array
    {
        $missing = [];
        if (empty(self::$whatsappApiUrl)) {
            $missing[] = 'WHATSAPP_API_URL';
        }
        $host = parse_url(self::$whatsappApiUrl, PHP_URL_HOST) ?: '';
        $isLoopback = self::isAllowedSandboxHost((string)$host);
        if (!$isLoopback && empty(self::$whatsappApiToken)) {
            $missing[] = 'WHATSAPP_API_TOKEN';
        }
        return $missing;
    }

    /**
     * Dapatkan ringkasan konfigurasi WhatsApp aman tanpa membocorkan token/rahasia
     */
    public static function getWhatsAppConfigSummary(): array
    {
        return [
            'api_url'       => self::$whatsappApiUrl,
            'token_set'     => !empty(self::$whatsappApiToken),
            'sender'        => self::$whatsappSender ?: '(default)',
            'test_phone'    => self::$testPhone,
            'is_configured' => self::isWhatsAppConfigured(),
            'missing'       => self::getMissingWhatsAppFields(),
        ];
    }

    /**
     * Helper penyensoran rahasia (password, token, secret)
     */
    public static function maskSecret(string $secret): string
    {
        $len = strlen(trim($secret));
        if ($len === 0) {
            return '(tidak diatur)';
        }
        return '***[TERPROTEKSI]***';
    }
}

