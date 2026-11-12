<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Config\App;
use App\Config\Notification;
use RuntimeException;
use Throwable;

/**
 * ==============================================================================
 * SMART EXAM — NATIVE SMTP CLIENT (RFC 5321 & RFC 4954)
 * ==============================================================================
 * Client SMTP ringan, mandiri, tanpa dependency eksternal untuk pengiriman email
 * sistem notifikasi Smart Exam (mendukung Gmail STARTTLS Port 587, SSL Port 465,
 * serta Mock SMTP lokal Port 1025).
 *
 * Fitur Keamanan:
 * - Kredensial (username, password, auth token) TIDAK PERNAH dibocorkan ke log/exception.
 * - Validasi ketat format email penerima (FILTER_VALIDATE_EMAIL).
 * - Guardrail NOTIFICATION_TEST_RECIPIENT untuk mencegah pengiriman salah alamat saat testing.
 * - STARTTLS otomatis dengan crypto stream TLS 1.2 / TLS 1.3.
 * - MIME multipart/alternative otomatis (dukungan HTML & Fallback Plain Text).
 * - Timeout terukur (connect: 10s, read: 15s) dengan klasifikasi error (Permanent vs Temporary).
 * ==============================================================================
 */
class SmtpClient
{
    protected string $host;
    protected int $port;
    protected string $encryption; // 'tls', 'ssl', 'none'
    protected string $username;
    protected string $password;
    protected string $fromEmail;
    protected string $fromName;
    protected string $replyTo;
    protected int $connectTimeout = 10;
    protected int $timeout = 15;
    protected ?string $testRecipient = null;
    protected bool $isExplicitTestRecipient = false;

    /**
     * Inisialisasi SMTP Client dengan konfigurasi default dari App\Config\Notification
     */
    public function __construct(?array $config = null)
    {
        Notification::load();

        $this->host           = (string)($config['host'] ?? Notification::$smtpHost);
        $this->port           = (int)($config['port'] ?? Notification::$smtpPort);
        $this->encryption     = strtolower(trim((string)($config['encryption'] ?? Notification::$smtpEncryption)));
        $this->username       = trim((string)($config['username'] ?? Notification::$smtpUsername));
        $rawPass              = trim((string)($config['password'] ?? Notification::$smtpPassword));
        $noSpacePass          = str_replace(' ', '', $rawPass);
        $this->password       = (strlen($noSpacePass) === 16) ? $noSpacePass : $rawPass;
        $this->fromEmail      = trim((string)($config['from_email'] ?? Notification::$smtpFromEmail));
        $this->fromName       = trim((string)($config['from_name'] ?? Notification::$smtpFromName));
        $this->replyTo        = trim((string)($config['reply_to'] ?? Notification::$smtpReplyTo ?? $this->fromEmail));
        $this->connectTimeout = (int)($config['connect_timeout'] ?? Notification::$smtpConnectTimeout);
        $this->timeout        = (int)($config['timeout'] ?? Notification::$smtpTimeout);

        if ($config !== null && array_key_exists('test_recipient', $config)) {
            $this->testRecipient = $config['test_recipient'];
            $this->isExplicitTestRecipient = true;
        } else {
            $this->testRecipient = ($this->host === '127.0.0.1' || $this->host === 'localhost')
                ? null
                : Notification::$testRecipient;
            $this->isExplicitTestRecipient = false;
        }

        // Jika port adalah 465, otomatis gunakan enkripsi ssl
        if ($this->port === 465 && $this->encryption === 'tls') {
            $this->encryption = 'ssl';
        }

        // Fallback email pengirim ke username jika username berformat email
        if (empty($this->fromEmail) && filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->fromEmail = $this->username;
        }
    }

    /**
     * Pastikan tautan aksi pada email selalu berupa Absolute URL (RFC 3986)
     * untuk mencegah penolakan spam filter akibat broken/relative link
     */
    public static function ensureAbsoluteUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        $trimmed = trim($url);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }
        if (function_exists('base_url')) {
            return base_url(ltrim($trimmed, '/'));
        }
        $base = rtrim(\App\Config\App::$appUrl ?? 'http://localhost/PRD/public', '/');
        return $base . '/' . ltrim($trimmed, '/');
    }

    /**
     * Periksa apakah konfigurasi SMTP sudah lengkap untuk pengiriman
     */
    public function isConfigured(): bool
    {
        return !empty($this->host)
            && $this->port > 0
            && !empty($this->username)
            && !empty($this->password)
            && !empty($this->fromEmail);
    }

    /**
     * Dapatkan daftar parameter wajib yang masih kosong
     */
    public function getMissingConfigFields(): array
    {
        $missing = [];
        if (empty($this->host)) {
            $missing[] = 'SMTP_HOST';
        }
        if ($this->port <= 0) {
            $missing[] = 'SMTP_PORT';
        }
        if (empty($this->username)) {
            $missing[] = 'SMTP_USERNAME';
        }
        if (empty($this->password)) {
            $missing[] = 'SMTP_PASSWORD';
        }
        if (empty($this->fromEmail)) {
            $missing[] = 'SMTP_FROM_EMAIL';
        }
        return $missing;
    }

    /**
     * Kirim email melalui SMTP Server
     *
     * @param string $to Alamat email penerima
     * @param string $subject Judul email
     * @param string $htmlBody Konten email format HTML
     * @param string $textBody Konten email format teks polos (fallback)
     * @param array $metadata Data tambahan (user_id, log_id, action_url, dll)
     * @return array Status hasil pengiriman terstandarisasi untuk antrean worker
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array $metadata = []
    ): array {
        $cleanTo = trim($to);

        // 1. Validasi format email penerima (RFC 5322)
        if (empty($cleanTo) || !filter_var($cleanTo, FILTER_VALIDATE_EMAIL)) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'smtp_code'    => null,
                'error_reason' => 'Format email penerima tidak valid atau kosong.',
                'processed_at' => date('Y-m-d H:i:s')
            ];
        }

        // 2. Guardrail Penerima Pengujian (Testing / Sandbox Recipient Whitelist - Mendukung multiple dipisah koma)
        if (!empty($this->testRecipient)) {
            $allowedRecipients = array_map('trim', explode(',', strtolower($this->testRecipient)));
            $inWhitelist = in_array(strtolower($cleanTo), $allowedRecipients, true);

            // Jika whitelist eksplisit dari constructor (misal unit test), patuhi whitelist ketat
            if ($this->isExplicitTestRecipient && !$inWhitelist) {
                return [
                    'status'       => 'failed',
                    'is_permanent' => true,
                    'is_temporary' => false,
                    'smtp_code'    => null,
                    'error_reason' => "Pengiriman ditolak oleh guardrail pengujian: Penerima '{$cleanTo}' " .
                                      "berbeda dari alamat pengujian yang diizinkan ('{$this->testRecipient}').",
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }

            // Jika whitelist default aplikasi, izinkan jika ada di whitelist ATAU merupakan akun pengguna terdaftar
            if (!$inWhitelist && !Notification::isRegisteredUserEmail($cleanTo)) {
                return [
                    'status'       => 'failed',
                    'is_permanent' => true,
                    'is_temporary' => false,
                    'smtp_code'    => null,
                    'error_reason' => "Pengiriman ditolak oleh guardrail pengujian: Penerima '{$cleanTo}' " .
                                      "berbeda dari alamat pengujian yang diizinkan ('{$this->testRecipient}') " .
                                      "dan bukan pengguna terdaftar pada sistem.",
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        // 3. Validasi kelengkapan konfigurasi SMTP
        // Catatan: Jika host adalah loopback (127.0.0.1 port 1025), autentikasi tidak diwajibkan
        $isLoopbackMock = ($this->host === '127.0.0.1' || $this->host === 'localhost');
        if (!$isLoopbackMock && !$this->isConfigured()) {
            $missing = implode(', ', $this->getMissingConfigFields());
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'smtp_code'    => null,
                'error_reason' => "Konfigurasi SMTP belum lengkap: [{$missing}]. " .
                                  "Silakan periksa konfigurasi environment SMTP Gmail Anda.",
                'processed_at' => date('Y-m-d H:i:s')
            ];
        }

        // 4. Buka koneksi socket TCP
        $socket = null;
        try {
            $transport = ($this->encryption === 'ssl') ? 'ssl' : 'tcp';
            $remoteAddress = "{$transport}://{$this->host}:{$this->port}";

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => true,
                    'verify_peer_name'  => true,
                    'allow_self_signed' => false,
                ]
            ]);

            // Pada pengujian lokal mock 127.0.0.1, abaikan verifikasi sertifikat SSL
            if ($isLoopbackMock) {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ]
                ]);
            }

            $socket = @stream_socket_client(
                $remoteAddress,
                $errno,
                $errstr,
                $this->connectTimeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'status'       => 'transport_error',
                    'is_permanent' => false,
                    'is_temporary' => true,
                    'error_reason' => "Gagal terhubung ke server SMTP {$this->host}:{$this->port} [{$errno}]: " .
                                      $this->maskSensitiveText($errstr),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }

            stream_set_timeout($socket, $this->timeout);

            // 5. Baca Greeting Banner 220
            $banner = $this->readResponse($socket);
            if (!str_starts_with($banner, '220')) {
                fclose($socket);
                return [
                    'status'       => 'transport_error',
                    'is_permanent' => false,
                    'is_temporary' => true,
                    'error_reason' => 'Banner SMTP tidak valid: ' . $this->maskSensitiveText(trim($banner)),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }

            // 6. Kirim EHLO
            $clientName = $isLoopbackMock ? '127.0.0.1' : (gethostname() ?: 'localhost');
            $this->sendCommand($socket, "EHLO {$clientName}");
            $ehloResponse = $this->readResponse($socket);

            // 7. Negosiasi STARTTLS (Port 587 Gmail)
            if ($this->encryption === 'tls' && !$isLoopbackMock) {
                $this->sendCommand($socket, "STARTTLS");
                $tlsResponse = $this->readResponse($socket);

                if (!str_starts_with($tlsResponse, '220')) {
                    fclose($socket);
                    return [
                        'status'       => 'transport_error',
                        'is_permanent' => false,
                        'is_temporary' => true,
                        'error_reason' => 'Server SMTP menolak perintah STARTTLS: ' . trim($tlsResponse),
                        'processed_at' => date('Y-m-d H:i:s')
                    ];
                }

                // Aktifkan enkripsi TLS pada stream socket yang sudah terhubung
                $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$cryptoOk) {
                    fclose($socket);
                    return [
                        'status'       => 'transport_error',
                        'is_permanent' => false,
                        'is_temporary' => true,
                        'error_reason' => 'Gagal menegosiasikan enkripsi TLS dengan server SMTP Gmail.',
                        'processed_at' => date('Y-m-d H:i:s')
                    ];
                }

                // Kirim ulang EHLO setelah handshake enkripsi TLS berhasil
                $this->sendCommand($socket, "EHLO {$clientName}");
                $this->readResponse($socket);
            }

            // 8. Autentikasi AUTH LOGIN (RFC 4954)
            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $authPrompt1 = $this->readResponse($socket);

                if (!str_starts_with($authPrompt1, '334')) {
                    fclose($socket);
                    return [
                        'status'       => 'failed',
                        'is_permanent' => true,
                        'is_temporary' => false,
                        'error_reason' => 'Server SMTP tidak menerima perintah AUTH LOGIN: ' . trim($authPrompt1),
                        'processed_at' => date('Y-m-d H:i:s')
                    ];
                }

                // Kirim Username (Base64)
                $this->sendCommand($socket, base64_encode($this->username));
                $authPrompt2 = $this->readResponse($socket);

                if (!str_starts_with($authPrompt2, '334')) {
                    fclose($socket);
                    return [
                        'status'       => 'failed',
                        'is_permanent' => true,
                        'is_temporary' => false,
                        'error_reason' => 'Server SMTP menolak username: ' . trim($authPrompt2),
                        'processed_at' => date('Y-m-d H:i:s')
                    ];
                }

                // Kirim Password (Base64)
                $this->sendCommand($socket, base64_encode($this->password));
                $authResult = $this->readResponse($socket);

                if (!str_starts_with($authResult, '235')) {
                    fclose($socket);
                    // Sanitasi ketat: jangan pernah memuat password dalam pesan error!
                    return [
                        'status'       => 'failed',
                        'is_permanent' => true,
                        'is_temporary' => false,
                        'smtp_code'    => 535,
                        'error_reason' => 'Autentikasi SMTP Gmail ditolak (535): Username atau App Password salah. ' .
                                          'Pastikan menggunakan 16-digit App Password Gmail, bukan password akun Google biasa.',
                        'processed_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            // 9. MAIL FROM
            $fromAddr = !empty($this->fromEmail) ? $this->fromEmail : 'noreply@smartexam.local';
            $this->sendCommand($socket, "MAIL FROM:<{$fromAddr}>");
            $mailFromResp = $this->readResponse($socket);
            if (!str_starts_with($mailFromResp, '250')) {
                fclose($socket);
                return [
                    'status'       => 'transport_error',
                    'is_permanent' => false,
                    'is_temporary' => true,
                    'error_reason' => 'Server SMTP menolak MAIL FROM: ' . trim($mailFromResp),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }

            // 10. RCPT TO
            $this->sendCommand($socket, "RCPT TO:<{$cleanTo}>");
            $rcptResp = $this->readResponse($socket);

            if (!str_starts_with($rcptResp, '250')) {
                fclose($socket);
                $isPermanent = str_starts_with($rcptResp, '550') || str_starts_with($rcptResp, '553');
                return [
                    'status'       => 'failed',
                    'is_permanent' => $isPermanent,
                    'is_temporary' => !$isPermanent,
                    'error_reason' => "Server SMTP menolak penerima <{$cleanTo}>: " . trim($rcptResp),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }

            // 11. DATA
            $this->sendCommand($socket, "DATA");
            $dataResp = $this->readResponse($socket);
            if (!str_starts_with($dataResp, '354')) {
                fclose($socket);
                return [
                    'status'       => 'transport_error',
                    'is_permanent' => false,
                    'is_temporary' => true,
                    'error_reason' => 'Server SMTP menolak perintah DATA: ' . trim($dataResp),
                    'processed_at' => date('Y-m-d H:i:s')
                ];
            }

            // 12. Susun Payload MIME Pesan dengan Header Deliverability Standar RFC 5322 & RFC 3834
            $fromDomain = substr(strrchr($fromAddr, "@"), 1);
            if (empty($fromDomain) || $fromDomain === false) {
                $fromDomain = ($this->host === 'smtp.gmail.com') ? 'gmail.com' : (parse_url(App::$appUrl, PHP_URL_HOST) ?: 'localhost');
            }
            $msgId = "<smartexam." . time() . "." . bin2hex(random_bytes(6)) . "@{$fromDomain}>";
            $boundary = "=_NextPart_" . bin2hex(random_bytes(12));

            if (empty($textBody)) {
                $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
            }

            // Sanitasi dan encoding header RFC 2047
            $safeSubject = trim($subject);
            if (preg_match('/[^\x20-\x7E]/', $safeSubject)) {
                $encodedSubject = '=?UTF-8?B?' . base64_encode($safeSubject) . '?=';
            } else {
                $encodedSubject = $safeSubject;
            }

            $safeFromName = trim($this->fromName ?: 'Smart Exam');
            if (preg_match('/[^\x20-\x7E]/', $safeFromName)) {
                $fromNameHeader = '=?UTF-8?B?' . base64_encode($safeFromName) . '?=';
            } else {
                $fromNameHeader = '"' . addcslashes($safeFromName, '"\\') . '"';
            }

            $replyToAddr = !empty($metadata['reply_to']) ? trim((string)$metadata['reply_to']) : (!empty($this->replyTo) ? $this->replyTo : $fromAddr);

            $rawEmail = "Date: " . date(DATE_RFC2822) . "\r\n" .
                        "From: {$fromNameHeader} <{$fromAddr}>\r\n" .
                        "Reply-To: <{$replyToAddr}>\r\n" .
                        "To: <{$cleanTo}>\r\n" .
                        "Subject: {$encodedSubject}\r\n" .
                        "Message-ID: {$msgId}\r\n" .
                        "MIME-Version: 1.0\r\n" .
                        "Importance: normal\r\n" .
                        "X-Priority: 3\r\n" .
                        "X-Entity-Ref-ID: {$msgId}\r\n" .
                        "Feedback-ID: smartexam:notification:transactional:gmail\r\n" .
                        (!empty($metadata['log_id']) ? "X-SmartExam-Log-Id: {$metadata['log_id']}\r\n" : "") .
                        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n" .
                        "--{$boundary}\r\n" .
                        "Content-Type: text/plain; charset=UTF-8\r\n" .
                        "Content-Transfer-Encoding: base64\r\n\r\n" .
                        chunk_split(base64_encode($textBody), 76, "\r\n") . "\r\n" .
                        "--{$boundary}\r\n" .
                        "Content-Type: text/html; charset=UTF-8\r\n" .
                        "Content-Transfer-Encoding: base64\r\n\r\n" .
                        chunk_split(base64_encode($htmlBody), 76, "\r\n") . "\r\n" .
                        "--{$boundary}--\r\n" .
                        "\r\n.\r\n";

            fwrite($socket, $rawEmail);
            $finalAck = $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            if (str_starts_with($finalAck, '250')) {
                return [
                    'status'        => 'sent',
                    'is_permanent'  => false,
                    'is_temporary'  => false,
                    'message_id'    => $msgId,
                    'smtp_code'     => 250,
                    'smtp_response' => trim($finalAck),
                    'transport'     => 'smtp_client_native',
                    'host'          => $this->host,
                    'port'          => $this->port,
                    'processed_at'  => date('Y-m-d H:i:s')
                ];
            }

            return [
                'status'       => 'transport_error',
                'is_permanent' => false,
                'is_temporary' => true,
                'error_reason' => 'Server SMTP menolak finalisasi pesan: ' . trim($finalAck),
                'processed_at' => date('Y-m-d H:i:s')
            ];
        } catch (Throwable $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            return [
                'status'       => 'transport_error',
                'is_permanent' => false,
                'is_temporary' => true,
                'error_reason' => 'Pengecualian transport SMTP: ' . $this->maskSensitiveText($e->getMessage()),
                'processed_at' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Uji koneksi dan kredensial SMTP tanpa mengirim email (Handshake & Auth Only)
     */
    public function verifyConnection(): array
    {
        $isLoopbackMock = ($this->host === '127.0.0.1' || $this->host === 'localhost');
        if (!$isLoopbackMock && !$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi SMTP belum lengkap: ' . implode(', ', $this->getMissingConfigFields()),
                'details' => Notification::getSmtpConfigSummary()
            ];
        }

        $socket = null;
        try {
            $transport = ($this->encryption === 'ssl') ? 'ssl' : 'tcp';
            $remoteAddress = "{$transport}://{$this->host}:{$this->port}";

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => !$isLoopbackMock,
                    'verify_peer_name'  => !$isLoopbackMock,
                    'allow_self_signed' => $isLoopbackMock,
                ]
            ]);

            $socket = @stream_socket_client($remoteAddress, $errno, $errstr, $this->connectTimeout, STREAM_CLIENT_CONNECT, $context);
            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "Gagal terhubung ke {$this->host}:{$this->port} [{$errno}]: " . $this->maskSensitiveText($errstr)
                ];
            }

            stream_set_timeout($socket, $this->timeout);
            $banner = $this->readResponse($socket);

            $clientName = $isLoopbackMock ? '127.0.0.1' : (gethostname() ?: 'localhost');
            $this->sendCommand($socket, "EHLO {$clientName}");
            $this->readResponse($socket);

            if ($this->encryption === 'tls' && !$isLoopbackMock) {
                $this->sendCommand($socket, "STARTTLS");
                $tlsResp = $this->readResponse($socket);
                if (!str_starts_with($tlsResp, '220')) {
                    fclose($socket);
                    return ['success' => false, 'message' => 'Server menolak STARTTLS: ' . trim($tlsResp)];
                }
                $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$cryptoOk) {
                    fclose($socket);
                    return ['success' => false, 'message' => 'Negosiasi handshake TLS gagal.'];
                }
                $this->sendCommand($socket, "EHLO {$clientName}");
                $this->readResponse($socket);
            }

            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->readResponse($socket);
                $this->sendCommand($socket, base64_encode($this->username));
                $this->readResponse($socket);
                $this->sendCommand($socket, base64_encode($this->password));
                $authResult = $this->readResponse($socket);

                if (!str_starts_with($authResult, '235')) {
                    fclose($socket);
                    return [
                        'success' => false,
                        'message' => 'Autentikasi SMTP ditolak (535): Username atau App Password salah.'
                    ];
                }
            }

            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return [
                'success' => true,
                'message' => "Koneksi dan autentikasi ke SMTP Server ({$this->host}:{$this->port}) berhasil!",
                'banner'  => trim($banner)
            ];
        } catch (Throwable $e) {
            if (is_resource($socket)) {
                fclose($socket);
            }
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat verifikasi: ' . $this->maskSensitiveText($e->getMessage())
            ];
        }
    }

    /**
     * Susun template email HTML responsif yang profesional, aman dari pembocoran token
     */
    public static function buildHtmlTemplate(
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $userName = null
    ): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $safeUserName = !empty($userName) ? htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') : 'Pengguna Smart Exam';
        $absActionUrl = !empty($actionUrl) ? self::ensureAbsoluteUrl($actionUrl) : null;
        $currentYear = date('Y');

        $actionButtonHtml = '';
        if (!empty($absActionUrl)) {
            $safeUrl = htmlspecialchars($absActionUrl, ENT_QUOTES, 'UTF-8');
            $actionButtonHtml = "
                <div style='margin: 28px 0; text-align: center;'>
                    <a href='{$safeUrl}' 
                       style='background: #2563eb; 
                              color: #ffffff; 
                              padding: 12px 28px; 
                              font-weight: 600; 
                              font-size: 14px; 
                              text-decoration: none; 
                              border-radius: 8px; 
                              display: inline-block; 
                              box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);'>
                        Buka Halaman Terkait &rarr;
                    </a>
                    <div style='font-size: 11px; color: #94a3b8; margin-top: 10px; line-height: 1.4;'>
                        Atau salin tautan berikut ke peramban web Anda:<br>
                        <span style='color: #2563eb; word-break: break-all;'>{$safeUrl}</span>
                    </div>
                </div>
            ";
        }

        return "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='color-scheme' content='light dark'>
    <meta name='supported-color-schemes' content='light dark'>
    <title>{$safeTitle}</title>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #334155; line-height: 1.6;'>
    <!-- Preheader preview text for email client inbox snippet -->
    <div style='display: none; max-height: 0px; overflow: hidden; font-size: 0; line-height: 0; mso-hide: all; opacity: 0; color: transparent;'>
        {$safeTitle} &bull; Pemberitahuan resmi sistem Smart Exam untuk {$safeUserName}.
    </div>
    <div style='max-width: 600px; margin: 32px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 28px 32px; text-align: left; border-bottom: 3px solid #2563eb;'>
            <h1 style='margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: -0.025em;'>
                Smart Exam
            </h1>
            <p style='margin: 4px 0 0; color: #94a3b8; font-size: 13px;'>
                Sistem Ujian Online Terintegrasi & Proctoring AI
            </p>
        </div>

        <!-- Body Content -->
        <div style='padding: 32px;'>
            <p style='margin: 0 0 16px; font-size: 15px; color: #64748b;'>
                Halo, <strong style='color: #0f172a;'>{$safeUserName}</strong>
            </p>

            <div style='background-color: #f8fafc; border-left: 4px solid #2563eb; border-radius: 0 8px 8px 0; padding: 18px 20px; margin: 20px 0; border: 1px solid #e2e8f0; border-left-width: 4px;'>
                <h2 style='margin: 0 0 8px; font-size: 16px; color: #1e293b; font-weight: 600;'>
                    {$safeTitle}
                </h2>
                <div style='font-size: 14px; color: #475569; line-height: 1.6;'>
                    {$safeMessage}
                </div>
            </div>

            {$actionButtonHtml}

            <!-- Security Notice -->
            <div style='margin-top: 32px; padding-top: 20px; border-top: 1px dashed #e2e8f0; font-size: 12px; color: #94a3b8;'>
                <p style='margin: 0;'>
                    &#128274; <strong>Keamanan:</strong> Pesan ini dikirimkan secara otomatis oleh sistem Smart Exam. 
                     Pihak sekolah/sistem tidak pernah meminta password atau App Password Anda melalui email.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div style='background-color: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8;'>
            <p style='margin: 0 0 4px;'>
                &copy; {$currentYear} Smart Exam &bull; All Rights Reserved.
            </p>
            <p style='margin: 0;'>
                Jangan membalas email ini karena dikirim dari alamat sistem tanpa pemantauan masuk.
            </p>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Susun template email HTML responsif yang profesional, singkat, padat, dan jelas khusus untuk kode verifikasi OTP
     */
    public static function buildOtpHtmlTemplate(
        string $otp,
        ?string $userName = null,
        int $expiresMinutes = 15,
        ?string $actionUrl = null
    ): string {
        $cleanOtp = htmlspecialchars(trim($otp), ENT_QUOTES, 'UTF-8');
        $formattedOtp = implode(' ', str_split($cleanOtp));
        $safeUserName = !empty($userName) ? htmlspecialchars(trim($userName), ENT_QUOTES, 'UTF-8') : 'Pengguna Smart Exam';
        $currentYear = date('Y');
        $normalizedUrl = self::ensureAbsoluteUrl($actionUrl);

        $actionButtonHtml = '';
        if (!empty($normalizedUrl)) {
            $safeUrl = htmlspecialchars($normalizedUrl, ENT_QUOTES, 'UTF-8');
            $actionButtonHtml = "
                <div style='margin: 20px 0; text-align: center;'>
                    <a href='{$safeUrl}' 
                       style='background: #2563eb; 
                              color: #ffffff; 
                              padding: 10px 24px; 
                              font-weight: 600; 
                              font-size: 13px; 
                              text-decoration: none; 
                              border-radius: 8px; 
                              display: inline-block; 
                              letter-spacing: 0.02em;'>
                        Verifikasi Kode OTP Sekarang &rarr;
                    </a>
                </div>
            ";
        }

        return "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='color-scheme' content='light dark'>
    <meta name='supported-color-schemes' content='light dark'>
    <title>Kode Verifikasi OTP - Smart Exam</title>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f8fafc; color: #1e293b; line-height: 1.5;'>
    <!-- Preheader preview text for email client inbox snippet -->
    <div style='display: none; max-height: 0px; overflow: hidden; font-size: 0; line-height: 0; mso-hide: all; opacity: 0; color: transparent;'>
        Kode verifikasi Smart Exam Anda: {$cleanOtp}. Berlaku {$expiresMinutes} menit. Jangan bagikan kode ini kepada siapa pun.
    </div>
    <div style='max-width: 480px; margin: 30px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05); border: 1px solid #e2e8f0;'>
        <div style='height: 4px; background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);'></div>
        <div style='padding: 24px 28px 16px; border-bottom: 1px solid #f1f5f9;'>
            <div style='font-size: 16px; font-weight: 800; color: #0f172a;'>Smart <span style='color: #2563eb;'>Exam</span> <span style='background-color: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; margin-left: 6px; border: 1px solid #dbeafe;'>Keamanan Akun</span></div>
        </div>
        <div style='padding: 20px 28px 24px;'>
            <p style='margin: 0 0 10px; font-size: 14px; color: #334155;'>Halo, <strong style='color: #0f172a;'>{$safeUserName}</strong></p>
            <p style='margin: 0 0 16px; font-size: 13px; color: #64748b; line-height: 1.5;'>Gunakan kode verifikasi berikut untuk melanjutkan verifikasi akun Anda:</p>
            <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; text-align: center; margin: 16px 0;'>
                <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 600; margin-bottom: 6px;'>Kode Verifikasi (OTP)</div>
                <div style='font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #1e293b; padding: 2px 0;'>{$formattedOtp}</div>
                <div style='margin-top: 8px;'><span style='background-color: #fef3c7; color: #92400e; font-size: 11px; font-weight: 500; padding: 3px 10px; border-radius: 9999px; border: 1px solid #fde68a;'>&#9201; Berlaku {$expiresMinutes} Menit &bull; Sekali Pakai</span></div>
            </div>
            {$actionButtonHtml}
            <div style='background-color: #f8fafc; border-radius: 6px; padding: 10px 14px; margin-top: 16px; border: 1px solid #f1f5f9; font-size: 11px; color: #64748b; line-height: 1.4;'>
                &#128274; <strong style='color: #334155;'>Peringatan Keamanan:</strong> Jangan berikan kode OTP ini kepada siapa pun. Jika Anda tidak meminta kode ini, abaikan email ini.
            </div>
        </div>
        <div style='background-color: #f8fafc; padding: 14px 28px; text-align: center; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8;'>
            &copy; {$currentYear} Smart Exam &bull; Pesan otomatis sistem.
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Susun template email plain-text yang singkat, padat, dan jelas untuk fallback OTP
     */
    public static function buildOtpTextTemplate(
        string $otp,
        ?string $userName = null,
        int $expiresMinutes = 15,
        ?string $actionUrl = null
    ): string {
        $safeUserName = !empty($userName) ? trim($userName) : 'Pengguna Smart Exam';
        $currentYear = date('Y');
        $normalizedUrl = self::ensureAbsoluteUrl($actionUrl);

        $text = "SMART EXAM — KODE VERIFIKASI OTP\n";
        $text .= "Halo, {$safeUserName}!\n\n";
        $text .= "Kode OTP Anda: {$otp}\n";
        $text .= "Berlaku: {$expiresMinutes} Menit (Rahasia & Sekali Pakai)\n\n";

        if (!empty($normalizedUrl)) {
            $text .= "Tautan Verifikasi:\n{$normalizedUrl}\n\n";
        }

        $text .= "Peringatan Keamanan: Jangan berikan kode ini kepada siapa pun demi keamanan akun Anda.\n";
        $text .= "(c) {$currentYear} Smart Exam — Pesan otomatis sistem.\n";

        return $text;
    }

    /**
     * Kirim perintah raw SMTP diakhiri CRLF (\r\n)
     */
    protected function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * Baca respon server SMTP (mendukung baris multiline kode-dash: 250-TEXT)
     */
    protected function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response .= $line;
            // Jika karakter ke-4 adalah spasi (contoh "250 OK" atau "334 ..."), maka transmisi respons selesai
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * Masking teks sensitif (menghapus password atau secret dari teks/log)
     */
    protected function maskSensitiveText(string $text): string
    {
        if (!empty($this->password)) {
            $text = str_replace($this->password, '***[TERPROTEKSI]***', $text);
            $b64 = base64_encode($this->password);
            if (!empty($b64)) {
                $text = str_replace($b64, '***[TERPROTEKSI]***', $text);
            }
        }
        return $text;
    }
}
