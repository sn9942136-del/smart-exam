<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — NOTIFICATION QUEUE SIMULATION WORKER (CLI)
 * ==============================================================================
 * Worker latar belakang mandiri untuk memproses antrean notifikasi (WhatsApp & Email).
 *
 * Penggunaan:
 *   php bin/notification_worker.php [--limit=10] [--once] [--db=ujian_dual_kamera_test] [--verbose]
 *
 * Opsi:
 *   --limit=N     Jumlah item maksimal per batch (default: 10)
 *   --once        Jalankan satu kali batch lalu selesai (default)
 *   --loop        Jalankan berulang secara daemon/worker loop
 *   --sleep=N     Waktu jeda antar-batch dalam detik jika menggunakan --loop (default: 3)
 *   --db=NAME     Nama database target (default: sesuai konfigurasi .env / testing)
 *   --verbose     Cetak rincian setiap item yang diproses
 * ==============================================================================
 */

// 1. Parsing argumen CLI
$options = getopt('', ['limit::', 'once', 'loop', 'sleep::', 'db::', 'mode::', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Penggunaan: php bin/notification_worker.php [OPSI]\n";
    echo "  --limit=N     Jumlah item maksimal per batch (default: 10)\n";
    echo "  --once        Jalankan satu kali batch lalu selesai (default)\n";
    echo "  --loop        Jalankan berulang secara terus-menerus\n";
    echo "  --sleep=N     Jeda waktu antar iterasi jika --loop (default: 3 detik)\n";
    echo "  --db=NAME     Spesifikasikan target database\n";
    echo "  --mode=MODE   Mode pengiriman: simulation (default) atau sandbox\n";
    echo "  --verbose     Cetak detail setiap item antrean\n";
    exit(0);
}

$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 10;
$isLoop = isset($options['loop']);
$sleepSeconds = isset($options['sleep']) ? max(1, (int)$options['sleep']) : 3;
$isVerbose = isset($options['verbose']);

// Atur target database jika didefinisikan lewat CLI
if (isset($options['db'])) {
    $targetDb = trim((string)$options['db']);
    $_ENV['DB_DATABASE'] = $targetDb;
    $_SERVER['DB_DATABASE'] = $targetDb;
    putenv("DB_DATABASE={$targetDb}");
}

// 2. Bootstrap Sistem Smart Exam
require_once __DIR__ . '/../app/bootstrap.php';

if (isset($targetDb)) {
    \App\Config\Database::$database = $targetDb;
}

// 3. Konfigurasi Mode & Safety Guardrails
\App\Config\Notification::load();
if (isset($options['mode'])) {
    try {
        \App\Config\Notification::setMode(trim((string)$options['mode']));
    } catch (\Throwable $e) {
        fwrite(STDERR, "[FATAL ERROR] " . $e->getMessage() . "\n");
        exit(1);
    }
}
$currentMode = \App\Config\Notification::getMode();

// 4. Validasi Database Aktual (SELECT DATABASE())
try {
    $pdo = \App\Libraries\Database::getConnection();
    $actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    \App\Config\Notification::validateActiveDatabase($actualDb);
} catch (\Throwable $e) {
    fwrite(STDERR, "[SAFETY GUARD TRIGGERED] " . $e->getMessage() . "\n");
    exit(2);
}

// 5. Validasi Sandbox Host (jika mode sandbox aktif)
if ($currentMode === \App\Config\Notification::MODE_SANDBOX) {
    if (!\App\Config\Notification::validateSandboxEndpoint(\App\Config\Notification::$whatsappEndpoint)) {
        fwrite(STDERR, "[SAFETY GUARD] Endpoint WhatsApp sandbox tidak diizinkan: " . \App\Config\Notification::$whatsappEndpoint . "\n");
        exit(3);
    }
    $isLoopbackSmtp = \App\Config\Notification::isAllowedSandboxHost(\App\Config\Notification::$smtpHost);
    if (!$isLoopbackSmtp && empty(\App\Config\Notification::$testRecipient)) {
        fwrite(STDERR, "[SAFETY GUARD] Host SMTP eksternal ('" . \App\Config\Notification::$smtpHost . "') pada mode sandbox memerlukan pembatasan NOTIFICATION_TEST_RECIPIENT. Demi keamanan data siswa, tentukan NOTIFICATION_TEST_RECIPIENT atau gunakan host 127.0.0.1.\n");
        exit(4);
    }
    $isLoopbackWa = \App\Config\Notification::isAllowedSandboxHost(parse_url(\App\Config\Notification::$whatsappApiUrl, PHP_URL_HOST) ?: '');
    if (!$isLoopbackWa && empty(\App\Config\Notification::$testPhone)) {
        fwrite(STDERR, "[SAFETY GUARD] Gateway WhatsApp eksternal ('" . \App\Config\Notification::$whatsappApiUrl . "') pada mode sandbox memerlukan pembatasan NOTIFICATION_TEST_PHONE. Demi keamanan privasi nomor siswa/guru, tentukan NOTIFICATION_TEST_PHONE atau gunakan mock 127.0.0.1.\n");
        exit(5);
    }
}

$smtpSummary = \App\Config\Notification::getSmtpConfigSummary();
$smtpStatusText = $smtpSummary['is_configured'] 
    ? "Terkonfigurasi ({$smtpSummary['host']}:{$smtpSummary['port']} [{$smtpSummary['encryption']}])" 
    : "Belum Lengkap (Parameter belum diset: " . implode(', ', $smtpSummary['missing']) . ")";

$waSummary = \App\Config\Notification::getWhatsAppConfigSummary();
$waStatusText = $waSummary['is_configured']
    ? "Terkonfigurasi ({$waSummary['api_url']})"
    : "Standby Mock (127.0.0.1:8088)";

echo "==============================================================================\n";
echo "  SMART EXAM — NOTIFICATION QUEUE WORKER\n";
echo "==============================================================================\n";
echo "  Database Aktual : {$actualDb}\n";
echo "  Mode Notifikasi : " . strtoupper($currentMode) . "\n";
echo "  Mode Eksekusi   : " . ($isLoop ? "Daemon Loop (Sleep {$sleepSeconds}s)" : "Single Batch (--once)") . "\n";
echo "  Limit per Batch : {$limit}\n";
echo "  SMTP Provider   : {$smtpStatusText}\n";
if (!empty($smtpSummary['test_recipient'])) {
    echo "  Test Recipient  : {$smtpSummary['test_recipient']} (Pembatasan Aktif)\n";
}
echo "  WhatsApp Gateway: {$waStatusText}\n";
if (!empty($waSummary['test_phone'])) {
    echo "  Test Phone      : {$waSummary['test_phone']} (Pembatasan Aktif)\n";
}
echo "  Status Jaringan : " . ($currentMode === 'sandbox' ? "SANDBOX LOKAL / TERISOLASI" : "SIMULASI ONLY (In-Memory)") . "\n";
echo "  Waktu Mulai     : " . date('Y-m-d H:i:s') . "\n";
echo "==============================================================================\n\n";

$queueService = new \App\Services\NotificationQueueService();

$iteration = 0;
do {
    $iteration++;
    $timeStr = date('H:i:s');
    
    $workerId = 'cli_worker_' . getmypid();
    $batchResult = $queueService->processBatch($limit, $workerId);
    $fetched = $batchResult['total_fetched'];
    $sent = $batchResult['sent'];
    $failed = $batchResult['failed'];
    $skipped = $batchResult['skipped'];
    $elapsed = $batchResult['elapsed_ms'];

    if ($fetched > 0 || $iteration === 1 || $isVerbose) {
        echo "[{$timeStr}] [Batch #{$iteration}] Terambil: {$fetched} | Terkirim: {$sent} | Gagal: {$failed} | Skipped: {$skipped} | Durasi: {$elapsed}ms\n";

        if ($isVerbose && !empty($batchResult['results'])) {
            foreach ($batchResult['results'] as $idx => $res) {
                $statusTag = strtoupper($res['status']);
                echo "       #{$res['log_id']} [{$res['channel']}] {$res['recipient']} -> {$statusTag} ({$res['message']})\n";
            }
        }
    }

    if ($isLoop) {
        sleep($sleepSeconds);
    }
} while ($isLoop);

echo "\n[Worker Selesai] Total iterasi: {$iteration}. Seluruh batch selesai diproses.\n";
exit(0);
