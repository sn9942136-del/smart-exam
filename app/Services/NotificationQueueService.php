<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationLogModel;
use Throwable;

/**
 * Notification Queue Service
 * Mengelola antrean pengiriman notifikasi saluran eksternal (WhatsApp & Email)
 * secara asinkron/terpisah dari request utama siklus ujian.
 *
 * Menerapkan arsitektur:
 * - Pre-Dispatch Atomic Claim: Item diklaim sebelum memanggil pengiriman/gateway.
 * - Ownership Locking: Finalisasi hanya dapat dieksekusi oleh worker pemegang claimToken sah.
 * - Lock Recovery / Lease Timeout: Pemulihan otomatis jika worker mati (default: 300 detik).
 * - Finite Retry Policy: Dibatasi maksimal 3 kali percobaan untuk mencegah loop racun.
 * - Pure Simulation Mode: Simulasi mock gateway tanpa koneksi riil ke pihak ketiga.
 */
class NotificationQueueService
{
    protected NotificationLogModel $logModel;
    protected ?\App\Libraries\SmtpClient $smtpClient = null;
    protected int $leaseTimeoutSeconds = 300; // 5 menit sewa klaim
    protected int $maxAttempts = 3;           // Batas maksimal retry

    public function __construct(
        ?NotificationLogModel $logModel = null,
        int $leaseTimeoutSeconds = 300,
        int $maxAttempts = 3,
        ?\App\Libraries\SmtpClient $smtpClient = null
    ) {
        $this->logModel = $logModel ?? new NotificationLogModel();
        $this->leaseTimeoutSeconds = max(5, $leaseTimeoutSeconds);
        $this->maxAttempts = max(1, $maxAttempts);
        $this->smtpClient = $smtpClient;
    }

    public function setSmtpClient(\App\Libraries\SmtpClient $client): self
    {
        $this->smtpClient = $client;
        return $this;
    }

    public function getSmtpClient(): \App\Libraries\SmtpClient
    {
        if ($this->smtpClient === null) {
            $this->smtpClient = new \App\Libraries\SmtpClient();
        }
        return $this->smtpClient;
    }

    protected ?\App\Libraries\WhatsAppClient $whatsAppClient = null;

    public function setWhatsAppClient(?\App\Libraries\WhatsAppClient $client): self
    {
        $this->whatsAppClient = $client;
        return $this;
    }

    public function getWhatsAppClient(): \App\Libraries\WhatsAppClient
    {
        if ($this->whatsAppClient === null) {
            $this->whatsAppClient = new \App\Libraries\WhatsAppClient();
        }
        return $this->whatsAppClient;
    }

    public function setLeaseTimeout(int $seconds): self
    {
        $this->leaseTimeoutSeconds = max(2, $seconds);
        return $this;
    }

    public function getLeaseTimeout(): int
    {
        return $this->leaseTimeoutSeconds;
    }

    public function setMaxAttempts(int $attempts): self
    {
        $this->maxAttempts = max(1, $attempts);
        return $this;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Daftarkan notifikasi ke dalam antrean (status: queued)
     *
     * @param int|null $notificationId ID notifikasi in-app terkait
     * @param string $channel Saluran target ('whatsapp' atau 'email')
     * @param string $recipient Tujuan (nomor WA atau alamat email)
     * @param array $metadata Data payload teknis tambahan (title, preview, action_url, user_id, dll)
     * @return array Status hasil pendaftaran antrean
     */
    public function enqueue(
        ?int $notificationId,
        string $channel,
        string $recipient,
        array $metadata = []
    ): array {
        $cleanChannel = strtolower(trim($channel));
        $cleanRecipient = trim($recipient);

        // Idempotency guard: Cegah antrean ganda berstatus 'queued' atau 'processing' untuk notifikasi in-app dan channel yang sama
        if ($notificationId !== null && $notificationId > 0) {
            if ($this->logModel->hasActiveQueue($notificationId, $cleanChannel)) {
                return [
                    'success'   => true,
                    'status'    => 'already_queued',
                    'channel'   => $cleanChannel,
                    'recipient' => $cleanRecipient,
                    'message'   => 'Notifikasi sudah berada dalam antrean aktif.'
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        $initialPayload = array_merge([
            'queued_at'     => $now,
            'attempts'      => 0,
            'queue_driver'  => 'database_table',
            'simulation'    => true
        ], $metadata);

        $logId = $this->logModel->enqueue(
            $notificationId,
            $cleanChannel,
            $cleanRecipient,
            $initialPayload
        );

        return [
            'success'   => (bool)$logId,
            'log_id'    => (int)$logId,
            'status'    => 'queued',
            'channel'   => $cleanChannel,
            'recipient' => $cleanRecipient,
            'message'   => 'Notifikasi berhasil didaftarkan ke antrean.'
        ];
    }

    /**
     * Ambil item antrean yang berstatus 'queued' atau 'processing' yang kedaluwarsa
     *
     * @param int $limit Batas jumlah item
     * @return array
     */
    public function getPendingItems(int $limit = 10): array
    {
        return $this->logModel->getPendingQueue($limit, $this->leaseTimeoutSeconds);
    }

    /**
     * Hitung sisa antrean yang masih menunggu diproses
     *
     * @return int
     */
    public function countPendingQueue(): int
    {
        return $this->logModel->countLogs(['status' => 'queued']);
    }

    /**
     * Proses batch antrean yang berstatus 'queued' atau lease kedaluwarsa
     *
     * @param int $limit Batas jumlah antrean yang diproses per batch
     * @param string|null $workerIdentity Identitas worker (opsional, untuk audit log)
     * @return array Ringkasan eksekusi batch worker
     */
    public function processBatch(int $limit = 10, ?string $workerIdentity = null): array
    {
        $startTime = microtime(true);
        $pendingItems = $this->getPendingItems($limit);

        $summary = [
            'total_fetched' => count($pendingItems),
            'processed'     => 0,
            'sent'          => 0,
            'failed'        => 0,
            'skipped'       => 0,
            'elapsed_ms'    => 0,
            'results'       => []
        ];

        if (empty($pendingItems)) {
            $summary['elapsed_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            return $summary;
        }

        foreach ($pendingItems as $item) {
            $result = $this->processItem($item, $workerIdentity);
            $summary['processed']++;
            $summary['results'][] = $result;

            if (($result['status'] ?? '') === 'sent') {
                $summary['sent']++;
            } elseif (($result['status'] ?? '') === 'failed') {
                $summary['failed']++;
            } elseif (str_starts_with((string)($result['status'] ?? ''), 'skipped')) {
                $summary['skipped']++;
            }
        }

        $summary['elapsed_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        return $summary;
    }

    /**
     * Proses satu item antrean dengan Pre-Dispatch Atomic Claim
     * Urutan:
     * 1. Pre-dispatch claim atomik (UPDATE ... SET status = 'processing', claimed_by = token).
     * 2. Jika gagal klaim (rowCount == 0) -> Worker langsung keluar (NO DISPATCH!).
     * 3. Jika berhasil klaim -> Jalankan simulasi/pengiriman.
     * 4. Finalisasi atomik menggunakan token pemegang sewa klaim.
     *
     * @param array $item Baris data dari notification_logs
     * @param string|null $workerIdentity Identitas nama worker
     * @return array
     */
    public function processItem(array $item, ?string $workerIdentity = null): array
    {
        $logId = (int)($item['id'] ?? 0);
        $channel = strtolower((string)($item['channel'] ?? ''));
        $recipient = trim((string)($item['recipient'] ?? ''));
        $currentAttempts = (int)($item['attempts'] ?? 0);

        // Parse payload awal
        $existingPayload = [];
        if (!empty($item['response_payload'])) {
            $decoded = json_decode((string)$item['response_payload'], true);
            if (is_array($decoded)) {
                $existingPayload = $decoded;
            }
        }

        // Cek apakah item sudah melebihi batas retry sebelum klaim
        if ($currentAttempts >= $this->maxAttempts) {
            $this->logModel->claimAndFinalize($logId, 'failed', array_merge($existingPayload, [
                'error_reason' => "Batas maksimal percobaan ({$this->maxAttempts}x) telah terlampaui.",
                'failed_at'    => date('Y-m-d H:i:s')
            ]));

            return [
                'log_id'     => $logId,
                'channel'    => $channel,
                'recipient'  => $recipient,
                'status'     => 'failed',
                'claimed'    => false,
                'dispatched' => false,
                'message'    => "Item telah mencapai batas maksimal retry ({$this->maxAttempts}x)."
            ];
        }

        // Generate token kepemilikan unik untuk sewa klaim ini
        $baseName = $workerIdentity ?? ('worker_' . getmypid());
        $claimToken = $baseName . '_' . bin2hex(random_bytes(6));

        // =========================================================================
        // FASE 1: PRE-DISPATCH ATOMIC CLAIM
        // =========================================================================
        // KLAIM DULU SEBELUM MEMANGGIL PENGIRIMAN / GATEWAY / SIDE-EFFECT
        $claimed = $this->logModel->claimItem(
            $logId,
            $claimToken,
            $this->leaseTimeoutSeconds,
            $this->maxAttempts
        );

        if (!$claimed) {
            // Worker kedua DITOLAK DI SINI! Tidak ada pemanggilan simulasi/kirim sama sekali.
            return [
                'log_id'     => $logId,
                'channel'    => $channel,
                'recipient'  => $recipient,
                'status'     => 'skipped_claim_failed',
                'claimed'    => false,
                'dispatched' => false,
                'message'    => 'Item antrean gagal diklaim (sedang diproses worker lain atau sewa klaim masih aktif).'
            ];
        }

        // =========================================================================
        // FASE 2: EKSEKUSI PENGIRIMAN (HANYA DIEKSEKUSI OLEH PEMILIK KLAIM!)
        // =========================================================================
        $dispatchResult = null;
        try {
            $isSandbox = (\App\Config\Notification::getMode() === \App\Config\Notification::MODE_SANDBOX);

            if ($channel === 'whatsapp') {
                $currentMode = \App\Config\Notification::getMode();
                if ($currentMode === \App\Config\Notification::MODE_SIMULATION) {
                    $dispatchResult = $this->simulateWhatsAppDelivery($recipient, $existingPayload);
                } elseif ($this->whatsAppClient !== null || !\App\Config\Notification::isAllowedSandboxHost(parse_url(\App\Config\Notification::$whatsappApiUrl, PHP_URL_HOST) ?: '')) {
                    $dispatchResult = $this->sendWhatsAppGateway($logId, $recipient, $existingPayload);
                } else {
                    $dispatchResult = $this->sendWhatsAppSandbox($logId, $recipient, $existingPayload);
                }
            } elseif ($channel === 'email') {
                $currentMode = \App\Config\Notification::getMode();
                $isOtp = (($existingPayload['type'] ?? '') === 'otp_verification' || !empty($existingPayload['otp']));
                $isUnitTestDummy = str_ends_with(strtolower(trim($recipient)), '@example.com');

                if ($currentMode === \App\Config\Notification::MODE_SIMULATION) {
                    $dispatchResult = $this->simulateEmailDelivery($recipient, $existingPayload);
                } elseif ($isOtp && !$isUnitTestDummy && \App\Config\Notification::isSmtpConfigured() && !\App\Config\Notification::isAllowedSandboxHost(\App\Config\Notification::$smtpHost)) {
                    $dispatchResult = $this->sendEmailSmtp($logId, $recipient, $existingPayload);
                } elseif ($this->smtpClient !== null || !\App\Config\Notification::isAllowedSandboxHost(\App\Config\Notification::$smtpHost)) {
                    $dispatchResult = $this->sendEmailSmtp($logId, $recipient, $existingPayload);
                } else {
                    $dispatchResult = $this->sendEmailSandbox($logId, $recipient, $existingPayload);
                }
            } else {
                $dispatchResult = [
                    'status'       => 'failed',
                    'is_permanent' => true,
                    'is_temporary' => false,
                    'payload'      => array_merge($existingPayload, [
                        'error_reason' => "Saluran '{$channel}' belum didukung sistem antrean.",
                        'processed_at' => date('Y-m-d H:i:s')
                    ])
                ];
            }
        } catch (Throwable $e) {
            $dispatchResult = [
                'status'       => 'failed',
                'is_permanent' => false,
                'is_temporary' => true,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Pengecualian saat dispatch: ' . $e->getMessage(),
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        $finalPayload = $dispatchResult['payload'];
        $finalPayload['worker_identity'] = $baseName;
        $finalPayload['claim_token'] = $claimToken;

        // Sanitasi Keamanan: Sensor nilai rahasia kode OTP dari payload sebelum disimpan permanen ke database log
        if (!empty($finalPayload['otp'])) {
            $rawOtp = (string)$finalPayload['otp'];
            $finalPayload['otp'] = '***[TERPROTEKSI]***';
            foreach (['preview', 'subject', 'message', 'formatted_message', 'html_body', 'text_body'] as $fld) {
                if (!empty($finalPayload[$fld])) {
                    $finalPayload[$fld] = str_replace($rawOtp, '***[TERPROTEKSI]***', (string)$finalPayload[$fld]);
                }
            }
        }

        // =========================================================================
        // FASE 3: PENANGANAN HASIL DISPATCH (SENT, RETRY, ATAU FAILED)
        // =========================================================================
        if ($dispatchResult['status'] === 'sent') {
            $finalized = $this->logModel->finalizeClaim($logId, $claimToken, 'sent', $finalPayload);
            if (!$finalized) {
                return [
                    'log_id'     => $logId,
                    'channel'    => $channel,
                    'recipient'  => $recipient,
                    'status'     => 'lost_claim',
                    'claimed'    => true,
                    'dispatched' => true,
                    'message'    => 'Worker kehilangan hak sewa klaim sebelum finalisasi diselesaikan.'
                ];
            }
            return [
                'log_id'     => $logId,
                'channel'    => $channel,
                'recipient'  => $recipient,
                'status'     => 'sent',
                'claimed'    => true,
                'dispatched' => true,
                'message'    => 'Pengiriman berhasil diselesaikan oleh worker pemilik klaim.'
            ];
        }

        // Jika terjadi error permanen ATAU attempts sudah mencapai batas maksimal
        $isPermanent = !empty($dispatchResult['is_permanent']);
        $nextAttempt = $currentAttempts + 1; // attempts sudah bertambah saat claimItem()

        if ($isPermanent || $nextAttempt >= $this->maxAttempts) {
            if ($nextAttempt >= $this->maxAttempts && !$isPermanent) {
                $finalPayload['error_reason'] = ($finalPayload['error_reason'] ?? 'Gagal dispatch') . 
                    " (Batas maksimal retry {$this->maxAttempts}x tercapai).";
            }
            $finalized = $this->logModel->finalizeClaim($logId, $claimToken, 'failed', $finalPayload);
            return [
                'log_id'     => $logId,
                'channel'    => $channel,
                'recipient'  => $recipient,
                'status'     => 'failed',
                'claimed'    => true,
                'dispatched' => true,
                'message'    => 'Pengiriman gagal dicatat secara permanen.'
            ];
        }

        // Error sementara (temporary): Jadwalkan retry dengan exponential backoff
        // Attempt 1 -> Jeda 60s, Attempt 2 -> Jeda 300s
        $delaySec = ($nextAttempt <= 1) 
            ? \App\Config\Notification::$retryDelayAttempt1 
            : \App\Config\Notification::$retryDelayAttempt2;

        $this->logModel->scheduleRetry($logId, $claimToken, $delaySec, $finalPayload);

        return [
            'log_id'        => $logId,
            'channel'       => $channel,
            'recipient'     => $recipient,
            'status'        => 'retry_scheduled',
            'delay_seconds' => $delaySec,
            'attempts'      => $nextAttempt,
            'claimed'       => true,
            'dispatched'    => true,
            'message'       => "Pengiriman gagal sementara, dijadwalkan ulang retry dalam {$delaySec} detik (Attempt {$nextAttempt}/{$this->maxAttempts})."
        ];
    }

    /**
     * Dispatch WhatsApp via Mock HTTP Gateway (Sandbox Mode)
     * Hanya boleh menghubungi loopback 127.0.0.1:8088
     */
    protected function sendWhatsAppSandbox(int $logId, string $recipient, array $existingPayload): array
    {
        $endpoint = \App\Config\Notification::$whatsappEndpoint;
        if (!\App\Config\Notification::validateSandboxEndpoint($endpoint)) {
            throw new \RuntimeException("KEAMANAN KRITIS: Endpoint sandbox WhatsApp tidak valid atau mengarah ke luar localhost: '{$endpoint}'");
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $recipient);
        if (empty($recipient) || $recipient === 'N/A' || strlen((string)$cleanPhone) < 8) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Nomor WhatsApp tidak valid atau terlalu pendek (< 8 digit).',
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        $clientRefId = "smart_exam_notif_{$logId}";
        $postData = json_encode([
            'target'              => $recipient,
            'message'             => $existingPayload['message'] ?? ($existingPayload['title'] ?? ($existingPayload['info'] ?? 'Pemberitahuan Ujian Smart Exam')),
            'client_reference_id' => $clientRefId
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return [
                'status'       => 'failed',
                'is_temporary' => true,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Gagal menginisialisasi cURL handler.',
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . $clientRefId
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Anti-redirect ke host publik
        curl_setopt($ch, CURLOPT_TIMEOUT, \App\Config\Notification::$curlTimeout); // 15 detik
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, \App\Config\Notification::$curlConnectTimeout); // 5 detik

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // 1. Cek cURL Transport Error (Timeout atau Refused)
        if ($curlErrno !== 0) {
            $isTimeout = ($curlErrno === CURLE_OPERATION_TIMEDOUT);
            return [
                'status'       => $isTimeout ? 'ambiguous_timeout' : 'transport_error',
                'is_temporary' => true,
                'is_permanent' => false,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => $isTimeout
                        ? "cURL Timeout: Melebihi batas " . \App\Config\Notification::$curlTimeout . "s (Status pengiriman ambigu)"
                        : "cURL Transport Error [{$curlErrno}]: {$curlError}",
                    'curl_errno'   => $curlErrno,
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        // 2. Cek HTTP Status Code
        $decoded = is_string($responseBody) ? json_decode($responseBody, true) : null;

        if ($httpCode === 200) {
            if (!is_array($decoded)) {
                return [
                    'status'       => 'invalid_response',
                    'is_temporary' => true,
                    'is_permanent' => false,
                    'payload'      => array_merge($existingPayload, [
                        'error_reason' => 'Respons gateway HTTP 200 bukan format JSON valid.',
                        'raw_body'     => mb_substr((string)$responseBody, 0, 150),
                        'processed_at' => date('Y-m-d H:i:s')
                    ])
                ];
            }

            return [
                'status'       => 'sent',
                'is_temporary' => false,
                'is_permanent' => false,
                'payload'      => array_merge($existingPayload, $decoded, [
                    'sandbox_transport' => 'curl_mock_whatsapp',
                    'http_code'         => 200,
                    'processed_at'      => date('Y-m-d H:i:s')
                ])
            ];
        }

        if ($httpCode === 400) {
            return [
                'status'       => 'failed',
                'is_temporary' => false,
                'is_permanent' => true, // ERROR PERMANEN: TIDAK PERLU RETRY
                'payload'      => array_merge($existingPayload, is_array($decoded) ? $decoded : [], [
                    'error_reason' => 'Gateway mengembalikan HTTP 400 Bad Request (Error permanen).',
                    'http_code'    => 400,
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        // Error sementara: 429 (Rate limit), 500 (Internal), 503 (Maintenance)
        $isRateLimit = ($httpCode === 429);
        return [
            'status'       => $isRateLimit ? 'rate_limited' : 'server_error',
            'is_temporary' => true,
            'is_permanent' => false,
            'payload'      => array_merge($existingPayload, is_array($decoded) ? $decoded : [], [
                'error_reason' => "Gateway mengembalikan HTTP {$httpCode} (Error sementara / butuh retry).",
                'http_code'    => $httpCode,
                'processed_at' => date('Y-m-d H:i:s')
            ])
        ];
    }

    /**
     * Dispatch Email via Mock SMTP Sink (Sandbox Mode)
     * Hanya boleh menghubungi loopback 127.0.0.1:1025
     */
    protected function sendEmailSandbox(int $logId, string $recipient, array $existingPayload): array
    {
        $host = \App\Config\Notification::$smtpHost;
        $port = \App\Config\Notification::$smtpPort;

        if (!\App\Config\Notification::isAllowedSandboxHost($host)) {
            throw new \RuntimeException("KEAMANAN KRITIS: Host sandbox SMTP tidak valid atau mengarah ke luar localhost: '{$host}'");
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Format email penerima tidak valid.',
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        $timeout = \App\Config\Notification::$smtpConnectTimeout;
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);

        if (!$socket) {
            return [
                'status'       => 'transport_error',
                'is_temporary' => true,
                'is_permanent' => false,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => "Gagal terhubung ke Mock SMTP [{$errno}]: {$errstr}",
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        stream_set_timeout($socket, \App\Config\Notification::$smtpTimeout);

        $readResponse = function($sock) {
            $response = '';
            while ($line = fgets($sock, 515)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };

        // 1. Baca Greeting Banner 220
        $banner = $readResponse($socket);
        if (!str_starts_with($banner, '220')) {
            fclose($socket);
            return [
                'status'       => 'transport_error',
                'is_temporary' => true,
                'payload'      => array_merge($existingPayload, ['error_reason' => 'Banner SMTP tidak valid: ' . trim($banner)])
            ];
        }

        // 2. Kirim EHLO
        fwrite($socket, "EHLO 127.0.0.1\r\n");
        $readResponse($socket);

        // 3. MAIL FROM
        fwrite($socket, "MAIL FROM:<noreply@smartexam.local>\r\n");
        $readResponse($socket);

        // 4. RCPT TO
        fwrite($socket, "RCPT TO:<{$recipient}>\r\n");
        $readResponse($socket);

        // 5. DATA
        fwrite($socket, "DATA\r\n");
        $dataPrompt = $readResponse($socket);

        if (!str_starts_with($dataPrompt, '354')) {
            fclose($socket);
            return [
                'status'       => 'transport_error',
                'is_temporary' => true,
                'payload'      => array_merge($existingPayload, ['error_reason' => 'Server menolak perintah DATA'])
            ];
        }

        // Susun Email
        $msgId = "<smart-exam-{$logId}-" . bin2hex(random_bytes(4)) . "@127.0.0.1>";
        $subject = $existingPayload['subject'] ?? ($existingPayload['title'] ?? 'Pemberitahuan Smart Exam');
        $preview = $existingPayload['preview'] ?? 'Konten notifikasi ujian';

        $rawEmail = "From: Smart Exam <noreply@smartexam.local>\r\n" .
                    "To: <{$recipient}>\r\n" .
                    "Subject: {$subject}\r\n" .
                    "X-SmartExam-Log-Id: {$logId}\r\n" .
                    "Message-ID: {$msgId}\r\n" .
                    "Date: " . date(DATE_RFC2822) . "\r\n" .
                    "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
                    "{$preview}\r\n" .
                    "\r\n.\r\n";

        fwrite($socket, $rawEmail);
        $finalAck = $readResponse($socket);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        if (str_starts_with($finalAck, '250')) {
            return [
                'status'       => 'sent',
                'is_temporary' => false,
                'is_permanent' => false,
                'payload'      => array_merge($existingPayload, [
                    'sandbox_transport' => 'smtp_mock_stream',
                    'smtp_status'       => trim($finalAck),
                    'smtp_code'         => 250,
                    'message_id'        => $msgId,
                    'processed_at'      => date('Y-m-d H:i:s')
                ])
            ];
        }

        return [
            'status'       => 'server_error',
            'is_temporary' => true,
            'payload'      => array_merge($existingPayload, ['error_reason' => 'SMTP menolak pesan: ' . trim($finalAck)])
        ];
    }

    /**
     * Dispatch Email via Native SMTP Client (Gmail SMTP / Production / Staging)
     * Menggunakan SmtpClient mandiri dengan STARTTLS port 587 dan AUTH LOGIN.
     *
     * @param int $logId ID baris notification_logs
     * @param string $recipient Alamat email tujuan
     * @param array $existingPayload Metadata tersimpan
     * @return array
     */
    public function sendEmailSmtp(int $logId, string $recipient, array $existingPayload): array
    {
        // 1. Validasi format email penerima
        if (empty($recipient) || $recipient === 'N/A' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'is_temporary' => false,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Alamat email tidak valid atau kosong.',
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        // 2. Siapkan instance SmtpClient
        $smtpClient = $this->getSmtpClient();
        if (!empty($existingPayload['bypass_test_recipient'])) {
            $smtpClient = new \App\Libraries\SmtpClient(['test_recipient' => null]);
        }

        // 3. Susun data konten email
        $subject = (string)($existingPayload['subject'] ?? ($existingPayload['title'] ?? 'Pemberitahuan Smart Exam'));
        $message = (string)($existingPayload['message'] ?? ($existingPayload['preview'] ?? 'Pemberitahuan sistem Smart Exam.'));
        $rawActionUrl = isset($existingPayload['action_url']) ? (string)$existingPayload['action_url'] : null;
        $actionUrl = !empty($rawActionUrl) ? \App\Libraries\SmtpClient::ensureAbsoluteUrl($rawActionUrl) : null;
        $userName = isset($existingPayload['user_name']) ? (string)$existingPayload['user_name'] : null;

        if (!empty($existingPayload['html_body'])) {
            $htmlBody = (string)$existingPayload['html_body'];
        } elseif (!empty($existingPayload['otp'])) {
            $htmlBody = \App\Libraries\SmtpClient::buildOtpHtmlTemplate(
                (string)$existingPayload['otp'],
                $userName,
                5,
                $actionUrl
            );
        } else {
            $htmlBody = \App\Libraries\SmtpClient::buildHtmlTemplate($subject, $message, $actionUrl, $userName);
        }

        if (!empty($existingPayload['text_body'])) {
            $textBody = (string)$existingPayload['text_body'];
        } elseif (!empty($existingPayload['otp'])) {
            $textBody = \App\Libraries\SmtpClient::buildOtpTextTemplate(
                (string)$existingPayload['otp'],
                $userName,
                5,
                $actionUrl
            );
        } else {
            $textBody = "SMART EXAM — PEMBERITAHUAN SISTEM\n" .
                        ($userName ? "Halo, {$userName}!\n\n" : "") .
                        "{$subject}\n" .
                        str_repeat('-', min(40, max(10, strlen($subject)))) . "\n\n" .
                        strip_tags($message) . "\n" .
                        (!empty($actionUrl) ? "\nTautan Terkait:\n{$actionUrl}\n" : "") .
                        "\n(c) " . date('Y') . " Smart Exam — Pesan otomatis sistem.";
        }

        // 4. Eksekusi pengiriman
        $sendResult = $smtpClient->send($recipient, $subject, $htmlBody, $textBody, [
            'log_id'  => $logId,
            'user_id' => $existingPayload['user_id'] ?? null
        ]);

        $payloadData = array_merge($existingPayload, [
            'smtp_transport' => 'smtp_native_client',
            'smtp_code'      => $sendResult['smtp_code'] ?? null,
            'smtp_response'  => $sendResult['smtp_response'] ?? null,
            'message_id'     => $sendResult['message_id'] ?? null,
            'processed_at'   => date('Y-m-d H:i:s')
        ]);

        if (!empty($sendResult['error_reason'])) {
            $payloadData['error_reason'] = $sendResult['error_reason'];
        }

        return [
            'status'       => $sendResult['status'],
            'is_permanent' => $sendResult['is_permanent'],
            'is_temporary' => $sendResult['is_temporary'],
            'payload'      => $payloadData
        ];
    }

    /**
     * Dispatch WhatsApp via WhatsAppClient (Gateway WhatsApp / Fonnte / Wablas / Custom)
     *
     * @param int $logId ID baris notification_logs
     * @param string $recipient Nomor telepon tujuan
     * @param array $existingPayload Metadata tersimpan
     * @return array
     */
    public function sendWhatsAppGateway(int $logId, string $recipient, array $existingPayload): array
    {
        $whatsAppClient = $this->getWhatsAppClient();
        if (!empty($existingPayload['bypass_test_recipient'])) {
            $whatsAppClient = new \App\Libraries\WhatsAppClient(['test_phone' => null]);
        }

        $title = (string)($existingPayload['title'] ?? 'Pemberitahuan Ujian Smart Exam');
        $message = (string)($existingPayload['message'] ?? ($existingPayload['preview'] ?? $title));
        $actionUrl = isset($existingPayload['action_url']) ? (string)$existingPayload['action_url'] : null;

        $metadata = array_merge($existingPayload, [
            'log_id' => $logId,
        ]);

        if (!empty($existingPayload['otp']) && empty($metadata['formatted_message'])) {
            $metadata['formatted_message'] = $existingPayload['message'] 
                ?? \App\Libraries\WhatsAppClient::formatOtpMessage(
                    (string)$existingPayload['otp'],
                    $existingPayload['user_name'] ?? null,
                    5,
                    $actionUrl
                );
        }

        $sendResult = $whatsAppClient->send($recipient, $title, $message, $actionUrl, $metadata);

        $payloadData = array_merge($existingPayload, [
            'whatsapp_transport' => 'whatsapp_gateway_client',
            'http_code'          => $sendResult['http_code'] ?? null,
            'message_id'         => $sendResult['message_id'] ?? null,
            'provider_status'    => $sendResult['provider_status'] ?? ($sendResult['status'] ?? 'unknown'),
            'recipient_masked'   => \App\Services\PasswordService::maskPhone($recipient),
            'processed_at'       => date('Y-m-d H:i:s')
        ]);

        if (!empty($sendResult['payload']) && is_array($sendResult['payload'])) {
            $payloadData = array_merge($payloadData, $sendResult['payload']);
        }
        if (!empty($sendResult['error_reason'])) {
            $payloadData['error_reason'] = $sendResult['error_reason'];
        }

        return [
            'status'       => $sendResult['status'],
            'is_permanent' => $sendResult['is_permanent'],
            'is_temporary' => $sendResult['is_temporary'],
            'payload'      => $payloadData
        ];
    }

    /**
     * Simulasi Pengiriman WhatsApp (Standby Worker Simulation)
     * Tidak mengirim permintaan HTTP ke WhatsApp Gateway riil.
     *
     * @param string $recipient Nomor telepon WhatsApp
     * @param array $existingPayload Metadata tersimpan
     * @return array
     */
    protected function simulateWhatsAppDelivery(string $recipient, array $existingPayload): array
    {
        $cleanPhone = preg_replace('/[^0-9+]/', '', $recipient);

        // Validasi format nomor telepon
        if (empty($recipient) || $recipient === 'N/A' || strlen((string)$cleanPhone) < 8) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Nomor WhatsApp tidak valid atau kosong.',
                    'worker_sim'   => true,
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        // Simulasi respon sukses WhatsApp Gateway
        $mockMessageId = 'WA-MOCK-' . strtoupper(bin2hex(random_bytes(6)));
        $mockLatencyMs = rand(10, 35);

        return [
            'status'  => 'sent',
            'payload' => array_merge($existingPayload, [
                'mock_gateway'         => 'Simulated WhatsApp Gateway v1.0',
                'gateway_status'       => 'DELIVERED_SIMULATION',
                'message_id'           => $mockMessageId,
                'simulated_latency_ms' => $mockLatencyMs,
                'worker_sim'           => true,
                'processed_at'         => date('Y-m-d H:i:s')
            ])
        ];
    }

    /**
     * Simulasi Pengiriman Email SMTP (Standby Worker Simulation)
     * Tidak membuka socket atau mengirim email SMTP riil.
     *
     * @param string $recipient Alamat email tujuan
     * @param array $existingPayload Metadata tersimpan
     * @return array
     */
    protected function simulateEmailDelivery(string $recipient, array $existingPayload): array
    {
        // Validasi format email
        if (empty($recipient) || $recipient === 'N/A' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'status'       => 'failed',
                'is_permanent' => true,
                'payload'      => array_merge($existingPayload, [
                    'error_reason' => 'Alamat email tidak valid atau kosong.',
                    'worker_sim'   => true,
                    'processed_at' => date('Y-m-d H:i:s')
                ])
            ];
        }

        // Simulasi respon sukses SMTP Server (250 OK)
        $mockMessageId = '<mock-' . bin2hex(random_bytes(8)) . '@smartexam.local>';
        $mockLatencyMs = rand(15, 45);

        return [
            'status'  => 'sent',
            'payload' => array_merge($existingPayload, [
                'mock_mailer'          => 'Simulated SMTP Mailer v1.0',
                'smtp_code'            => 250,
                'smtp_status'          => '2.0.0 OK: Message accepted for delivery (simulated)',
                'message_id'           => $mockMessageId,
                'simulated_latency_ms' => $mockLatencyMs,
                'worker_sim'           => true,
                'processed_at'         => date('Y-m-d H:i:s')
            ])
        ];
    }
}
