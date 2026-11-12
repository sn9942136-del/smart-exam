<?php

declare(strict_types=1);

namespace Tests\Mocks;

/**
 * ==============================================================================
 * SMART EXAM — MOCK SMTP SINK SERVER (STANDALONE LOCAL SANDBOX)
 * ==============================================================================
 * Server SMTP mock lokal berbasis PHP stream socket untuk menangkap email
 * tanpa relay ke internet.
 *
 * Penggunaan CLI:
 *   php tests/mocks/mock_smtp_server.php [--port=1025] [--loop]
 * ==============================================================================
 */

class MockSmtpServer
{
    protected string $host;
    protected int $port;
    protected string $storageDir;
    protected bool $running = false;

    public function __construct(string $host = '127.0.0.1', int $port = 1025)
    {
        $this->host = $host;
        $this->port = $port;
        $this->storageDir = __DIR__ . '/../../storage/mock_emails';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    public static function getStorageDir(): string
    {
        $dir = __DIR__ . '/../../storage/mock_emails';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function clearCapturedEmails(): void
    {
        $dir = self::getStorageDir();
        $files = glob($dir . '/*.json') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
    }

    public static function getCapturedEmails(): array
    {
        $dir = self::getStorageDir();
        $files = glob($dir . '/*.json') ?: [];
        $emails = [];
        foreach ($files as $f) {
            $content = file_get_contents($f);
            $decoded = json_decode((string)$content, true);
            if (is_array($decoded)) {
                $emails[] = $decoded;
            }
        }
        return $emails;
    }

    /**
     * Jalankan server SMTP sink lokal
     *
     * @param int $maxTransactions Jumlah transaksi email sebelum berhenti (0 = unlimited / loop)
     * @param int $timeoutSec Timeout idle socket dalam detik
     */
    public function run(int $maxTransactions = 1, int $timeoutSec = 5): int
    {
        $context = stream_context_create([
            'socket' => [
                'bindto' => "{$this->host}:{$this->port}"
            ]
        ]);

        $server = @stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$server) {
            throw new \RuntimeException("Gagal menginisialisasi Mock SMTP Server di {$this->host}:{$this->port} -> [{$errno}] {$errstr}");
        }

        stream_set_timeout($server, $timeoutSec);
        $transactionsCompleted = 0;
        $this->running = true;

        while ($this->running) {
            $client = @stream_socket_accept($server, $timeoutSec);
            if (!$client) {
                if ($maxTransactions > 0 && $transactionsCompleted >= $maxTransactions) {
                    break;
                }
                continue;
            }

            stream_set_timeout($client, 5);

            // 1. Kirim Greeting Banner
            fwrite($client, "220 127.0.0.1 Smart Exam Mock SMTP Sink Service Ready\r\n");

            $mailData = [
                'from'        => '',
                'to'          => [],
                'headers'     => [],
                'subject'     => '',
                'body'        => '',
                'raw_message' => '',
                'captured_at' => date('Y-m-d H:i:s')
            ];

            $inDataMode = false;
            $rawBuffer = '';

            while (!feof($client)) {
                $line = fgets($client, 1024);
                if ($line === false) {
                    break;
                }

                if ($inDataMode) {
                    $rawBuffer .= $line;
                    if ($line === ".\r\n" || $line === ".\n") {
                        $inDataMode = false;
                        $mailData['raw_message'] = $rawBuffer;

                        // Parse headers & subject sederhana
                        $parts = explode("\r\n\r\n", $rawBuffer, 2);
                        if (count($parts) < 2) {
                            $parts = explode("\n\n", $rawBuffer, 2);
                        }
                        $headerPart = $parts[0] ?? '';
                        $bodyPart = $parts[1] ?? '';

                        $mailData['body'] = trim($bodyPart);
                        $headerLines = explode("\n", str_replace("\r", "", $headerPart));
                        foreach ($headerLines as $hLine) {
                            if (str_contains($hLine, ':')) {
                                [$hKey, $hVal] = explode(':', $hLine, 2);
                                $cleanKey = trim($hKey);
                                $cleanVal = trim($hVal);
                                $mailData['headers'][$cleanKey] = $cleanVal;
                                if (strcasecmp($cleanKey, 'Subject') === 0) {
                                    $mailData['subject'] = $cleanVal;
                                }
                            }
                        }

                        // Simpan file JSON
                        $uniq = uniqid('email_', true);
                        file_put_contents(
                            $this->storageDir . "/{$uniq}.json",
                            json_encode($mailData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        );

                        fwrite($client, "250 2.0.0 OK: message accepted for local sink capture\r\n");
                        $transactionsCompleted++;
                        if ($maxTransactions > 0 && $transactionsCompleted >= $maxTransactions) {
                            break 2;
                        }
                    }
                    continue;
                }

                $cmd = strtoupper(substr(trim($line), 0, 4));

                if ($cmd === 'HELO' || $cmd === 'EHLO') {
                    fwrite($client, "250-127.0.0.1 Hello [127.0.0.1]\r\n250-SIZE 10485760\r\n250-AUTH LOGIN PLAIN\r\n250-STARTTLS\r\n250 OK\r\n");
                } elseif (str_starts_with(strtoupper($line), 'STARTTLS')) {
                    fwrite($client, "220 2.0.0 Ready to start TLS\r\n");
                } elseif (str_starts_with(strtoupper($line), 'AUTH LOGIN')) {
                    // Prompt 1: Username: (Base64: VXNlcm5hbWU6)
                    fwrite($client, "334 VXNlcm5hbWU6\r\n");
                    $uLine = fgets($client, 1024);
                    $uDecoded = base64_decode(trim((string)$uLine));

                    // Prompt 2: Password: (Base64: UGFzc3dvcmQ6)
                    fwrite($client, "334 UGFzc3dvcmQ6\r\n");
                    $pLine = fgets($client, 1024);
                    $pDecoded = base64_decode(trim((string)$pLine));

                    // Simulasi autentikasi gagal jika password diset 'invalid_password'
                    if ($pDecoded === 'invalid_password' || $pDecoded === 'wrong_secret') {
                        fwrite($client, "535 5.7.8 Authentication credentials invalid\r\n");
                    } else {
                        fwrite($client, "235 2.7.0 Authentication successful\r\n");
                    }
                } elseif (str_starts_with(strtoupper($line), 'MAIL FROM:')) {
                    $mailData['from'] = trim(substr($line, 10));
                    fwrite($client, "250 2.1.0 Sender OK\r\n");
                } elseif (str_starts_with(strtoupper($line), 'RCPT TO:')) {
                    $rcpt = trim(substr($line, 8));
                    if (str_contains($rcpt, 'rejected@') || str_contains($rcpt, 'invalid_recipient@')) {
                        fwrite($client, "550 5.1.1 User unknown\r\n");
                    } else {
                        $mailData['to'][] = $rcpt;
                        fwrite($client, "250 2.1.5 Recipient OK\r\n");
                    }
                } elseif ($cmd === 'DATA') {
                    $inDataMode = true;
                    $rawBuffer = '';
                    fwrite($client, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
                } elseif ($cmd === 'QUIT') {
                    fwrite($client, "221 2.0.0 127.0.0.1 Service closing transmission channel\r\n");
                    break;
                } elseif ($cmd === 'RSET') {
                    $mailData = ['from' => '', 'to' => [], 'headers' => [], 'subject' => '', 'body' => '', 'raw_message' => ''];
                    fwrite($client, "250 2.0.0 Reset OK\r\n");
                } elseif ($cmd === 'NOOP') {
                    fwrite($client, "250 2.0.0 OK\r\n");
                } else {
                    fwrite($client, "500 5.5.1 Command unrecognized\r\n");
                }
            }

            fclose($client);
        }

        fclose($server);
        return $transactionsCompleted;
    }
}

// Eksekusi jika dipanggil via CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $opts = getopt('', ['port::', 'loop', 'once']);
    $port = isset($opts['port']) ? (int)$opts['port'] : 1025;
    $maxTrans = isset($opts['once']) ? 1 : (isset($opts['loop']) ? 0 : 1);

    echo "=== SMART EXAM MOCK SMTP SINK ===\n";
    echo "Listening on 127.0.0.1:{$port}\n";
    echo "Penyimpanan: " . MockSmtpServer::getStorageDir() . "\n";
    $server = new MockSmtpServer('127.0.0.1', $port);
    $count = $server->run($maxTrans, 10);
    echo "Server selesai. Transaksi ditangkap: {$count}\n";
}
