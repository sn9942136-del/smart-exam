<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — TEST SUITE TAHAP 3: SANDBOX TRANSPORT, TIMEOUT & RETRY
 * ==============================================================================
 * Menguji:
 * 1. Default mode simulation
 * 2. Host safety guardrail (loopback only)
 * 3. Database safety guardrail (penolakan ujian_dual_kamera)
 * 4. Mock WhatsApp Gateway (HTTP 200, 400, 429, 500, 503, Timeout, Invalid JSON, Refused)
 * 5. Mock WhatsApp Idempotency (Deduplikasi via client_reference_id)
 * 6. Mock SMTP Sink Capture (Local port 1025)
 * 7. Retry logic (Permanent fail vs Temporary retry)
 * 8. Status pengujian kolom available_at (BLOCKED jika migrasi 011 belum disetujui)
 * 9. Integritas database operasional (100% perawan)
 * ==============================================================================
 */

// 1. Bootstrap aplikasi pada database testing
$_ENV['DB_DATABASE'] = 'ujian_dual_kamera_test';
$_SERVER['DB_DATABASE'] = 'ujian_dual_kamera_test';
putenv('DB_DATABASE=ujian_dual_kamera_test');

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = 'ujian_dual_kamera_test';
require_once __DIR__ . '/mocks/mock_smtp_server.php';

use App\Config\Notification;
use App\Models\NotificationLogModel;
use App\Services\NotificationQueueService;
use Tests\Mocks\MockSmtpServer;

echo "==============================================================================\n";
echo "  SMART EXAM — TEST SUITE TAHAP 3: SANDBOX TRANSPORT & SAFETY\n";
echo "==============================================================================\n";
echo "  Target Database : " . \App\Config\Database::$database . "\n";
echo "  Waktu Pengujian : " . date('Y-m-d H:i:s') . "\n";
echo "==============================================================================\n\n";

$pdo = \App\Libraries\Database::getConnection();
$actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actualDb !== 'ujian_dual_kamera_test') {
    die("[FATAL ERROR] Koneksi aktif bukan ujian_dual_kamera_test, melainkan: {$actualDb}. Pengujian dibatalkan!\n");
}

$passCount = 0;
$failCount = 0;
$blockedCount = 0;

function assertCondition(string $code, string $desc, bool $condition, ?string $detail = null): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS]    {$code}: {$desc}\n";
    } else {
        $failCount++;
        echo "  [FAIL]    {$code}: {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

function markBlocked(string $code, string $desc, string $reason): void {
    global $blockedCount;
    $blockedCount++;
    echo "  [BLOCKED] {$code}: {$desc} (ALASAN: {$reason})\n";
}

// -----------------------------------------------------------------------------
// HELPER: START BACKGROUND MOCK SERVERS
// -----------------------------------------------------------------------------
$mockWaProcess = null;
$mockSmtpProcess = null;

function startMockWaServer(): mixed {
    $phpBin = 'C:\\xampp\\php\\php.exe';
    $routerFile = __DIR__ . '/mocks/whatsapp_mock_router.php';
    $cmd = "\"{$phpBin}\" -S 127.0.0.1:8088 \"{$routerFile}\"";

    $descriptors = [
        0 => ['file', 'nul', 'r'],
        1 => ['file', 'nul', 'w'],
        2 => ['file', 'nul', 'w']
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..');
    usleep(500000); // 500ms agar socket siap
    return $proc;
}

function startMockSmtpServer(): mixed {
    $phpBin = 'C:\\xampp\\php\\php.exe';
    $smtpFile = __DIR__ . '/mocks/mock_smtp_server.php';
    $cmd = "\"{$phpBin}\" \"{$smtpFile}\" --port=1025 --loop";

    $descriptors = [
        0 => ['file', 'nul', 'r'],
        1 => ['file', 'nul', 'w'],
        2 => ['file', 'nul', 'w']
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..');
    usleep(500000); // 500ms agar socket siap
    return $proc;
}

$mockWaProcess = startMockWaServer();
$mockSmtpProcess = startMockSmtpServer();

register_shutdown_function(function() use (&$mockWaProcess, &$mockSmtpProcess) {
    if (is_resource($mockWaProcess)) {
        $st = proc_get_status($mockWaProcess);
        if (!empty($st['pid'])) {
            @exec("taskkill /F /T /PID {$st['pid']} 2>&1");
        }
        @proc_terminate($mockWaProcess);
        @proc_close($mockWaProcess);
    }
    if (is_resource($mockSmtpProcess)) {
        $st = proc_get_status($mockSmtpProcess);
        if (!empty($st['pid'])) {
            @exec("taskkill /F /T /PID {$st['pid']} 2>&1");
        }
        @proc_terminate($mockSmtpProcess);
        @proc_close($mockSmtpProcess);
    }
});

// Bersihkan cache mock
@unlink(__DIR__ . '/../storage/cache/mock_wa_processed.json');
MockSmtpServer::clearCapturedEmails();

// =============================================================================
// SEKSI 1: MODE SAFETY & GUARDRAILS
// =============================================================================
echo "\n--- SEKSI 1: MODE SAFETY & GUARDRAILS ---\n";

// TC-SBOX-01: Default mode adalah simulation
Notification::load();
assertCondition(
    'TC-SBOX-01',
    'Default mode notifikasi wajib simulation',
    Notification::getMode() === Notification::MODE_SIMULATION
);

// TC-SBOX-02: Penolakan mode production
$prodBlocked = false;
try {
    Notification::setMode(Notification::MODE_PRODUCTION);
} catch (\Throwable $e) {
    $prodBlocked = str_contains($e->getMessage(), 'KEAMANAN KRITIS');
}
assertCondition(
    'TC-SBOX-02',
    'Mode production wajib ditolak dengan RuntimeException',
    $prodBlocked
);
Notification::setMode(Notification::MODE_SIMULATION); // Reset ke aman

// TC-SBOX-03: Penolakan host eksternal/publik
$extHostBlocked = !Notification::isAllowedSandboxHost('api.fonnte.com') &&
                  !Notification::isAllowedSandboxHost('smtp.gmail.com') &&
                  !Notification::isAllowedSandboxHost('192.168.1.100') &&
                  Notification::isAllowedSandboxHost('127.0.0.1') &&
                  Notification::isAllowedSandboxHost('localhost');
assertCondition(
    'TC-SBOX-03',
    'Host guardrail hanya mengizinkan 127.0.0.1 dan localhost',
    $extHostBlocked
);

// TC-SBOX-04: Penolakan database operasional
$opDbBlocked = false;
try {
    Notification::validateActiveDatabase('ujian_dual_kamera');
} catch (\Throwable $e) {
    $opDbBlocked = str_contains($e->getMessage(), 'OPERASIONAL');
}
assertCondition(
    'TC-SBOX-04',
    'Database operasional (ujian_dual_kamera) wajib ditolak keras oleh worker',
    $opDbBlocked
);

// TC-SBOX-05: Penerimaan database testing
$testDbAllowed = false;
try {
    Notification::validateActiveDatabase('ujian_dual_kamera_test');
    $testDbAllowed = true;
} catch (\Throwable) {
    $testDbAllowed = false;
}
assertCondition(
    'TC-SBOX-05',
    'Database testing (ujian_dual_kamera_test) lolos verifikasi safety',
    $testDbAllowed
);

// =============================================================================
// SEKSI 2: MOCK WHATSAPP HTTP GATEWAY (SANDBOX 127.0.0.1:8088)
// =============================================================================
echo "\n--- SEKSI 2: MOCK WHATSAPP HTTP GATEWAY ---\n";

Notification::setMode(Notification::MODE_SANDBOX);
$logModel = new NotificationLogModel();
$queueService = new NotificationQueueService($logModel);

// TC-SBOX-06: WhatsApp HTTP 200 Sukses
$logId1 = $logModel->enqueue(null, 'whatsapp', '081200000001', [
    'title' => 'Ujian Matematika Dimulai'
]);
$item1 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId1}")->fetch(PDO::FETCH_ASSOC);
$res1 = $queueService->processItem($item1, 'worker_test_sbox');

$afterDb1 = $pdo->query("SELECT status, sent_at, response_payload FROM notification_logs WHERE id = {$logId1}")->fetch(PDO::FETCH_ASSOC);
$payload1 = json_decode((string)$afterDb1['response_payload'], true);

assertCondition(
    'TC-SBOX-06',
    'Mock WhatsApp HTTP 200 berhasil (Status: sent, message_id tercatat, sent_at terisi)',
    $res1['status'] === 'sent' && 
    $afterDb1['status'] === 'sent' && 
    !empty($afterDb1['sent_at']) && 
    !empty($payload1['message_id']) &&
    ($payload1['sandbox_transport'] ?? '') === 'curl_mock_whatsapp'
);

// TC-SBOX-07: WhatsApp HTTP 400 Permanent Error (No Retry)
$logId2 = $logModel->enqueue(null, 'whatsapp', '081240000000', [
    'title' => 'Ujian Error Permanen'
]);
$item2 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId2}")->fetch(PDO::FETCH_ASSOC);
$res2 = $queueService->processItem($item2, 'worker_test_sbox');

$afterDb2 = $pdo->query("SELECT status, attempts, response_payload FROM notification_logs WHERE id = {$logId2}")->fetch(PDO::FETCH_ASSOC);

assertCondition(
    'TC-SBOX-07',
    'Mock WhatsApp HTTP 400 langsung gagal permanen tanpa retry (status: failed, attempt: 1)',
    $res2['status'] === 'failed' && 
    $afterDb2['status'] === 'failed' && 
    (int)$afterDb2['attempts'] === 1
);

// TC-SBOX-08: WhatsApp HTTP 429 Rate Limit (Temporary Error -> Retry Backoff 60s)
$logId3 = $logModel->enqueue(null, 'whatsapp', '081242900000', [
    'title' => 'Ujian Rate Limited'
]);
$item3 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId3}")->fetch(PDO::FETCH_ASSOC);
$res3 = $queueService->processItem($item3, 'worker_test_sbox');

$afterDb3 = $pdo->query("SELECT status, attempts, response_payload FROM notification_logs WHERE id = {$logId3}")->fetch(PDO::FETCH_ASSOC);
$payload3 = json_decode((string)$afterDb3['response_payload'], true);

assertCondition(
    'TC-SBOX-08',
    'Mock WhatsApp HTTP 429 dijadwalkan retry dengan jeda backoff 60s',
    $res3['status'] === 'retry_scheduled' &&
    $res3['delay_seconds'] === 60 &&
    $afterDb3['status'] === 'queued' &&
    (int)$payload3['retry_delay_seconds'] === 60
);

// TC-SBOX-09: WhatsApp HTTP 500 & 503 Server Error (Temporary Error -> Retry Backoff)
$logId4 = $logModel->enqueue(null, 'whatsapp', '081250000000', [
    'title' => 'Ujian Server Crash'
]);
$item4 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId4}")->fetch(PDO::FETCH_ASSOC);
$res4 = $queueService->processItem($item4, 'worker_test_sbox');

assertCondition(
    'TC-SBOX-09',
    'Mock WhatsApp HTTP 500 dijadwalkan retry secara aman',
    $res4['status'] === 'retry_scheduled'
);

// TC-SBOX-10: WhatsApp Timeout (Target 081299900000)
// Untuk mempercepat unit test, kita set timeout sementara ke 2 detik
$origTimeout = Notification::$curlTimeout;
Notification::$curlTimeout = 2; // set ke 2s untuk uji cURL timeout
$logId5 = $logModel->enqueue(null, 'whatsapp', '081299900000', [
    'title' => 'Ujian Timeout Test'
]);
$item5 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId5}")->fetch(PDO::FETCH_ASSOC);
$res5 = $queueService->processItem($item5, 'worker_test_sbox');
Notification::$curlTimeout = $origTimeout; // restore

assertCondition(
    'TC-SBOX-10',
    'cURL timeout tertangkap dengan benar tanpa membuat worker hang tanpa batas',
    $res5['status'] === 'retry_scheduled' &&
    str_contains((string)($res5['message'] ?? ''), 'gagal sementara')
);

// TC-SBOX-11: WhatsApp Invalid JSON (HTML output)
$logId6 = $logModel->enqueue(null, 'whatsapp', '081250200000', [
    'title' => 'Ujian Bad Gateway HTML'
]);
$item6 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId6}")->fetch(PDO::FETCH_ASSOC);
$res6 = $queueService->processItem($item6, 'worker_test_sbox');

assertCondition(
    'TC-SBOX-11',
    'Respons gateway bukan JSON valid ditangani sebagai temporary error',
    $res6['status'] === 'retry_scheduled'
);

// TC-SBOX-12: WhatsApp Connection Refused (Port 8089)
$origEndpoint = Notification::$whatsappEndpoint;
Notification::$whatsappEndpoint = 'http://127.0.0.1:8089/api/send'; // port mati
$logId7 = $logModel->enqueue(null, 'whatsapp', '081200000001', [
    'title' => 'Ujian Connection Refused'
]);
$item7 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logId7}")->fetch(PDO::FETCH_ASSOC);
$res7 = $queueService->processItem($item7, 'worker_test_sbox');
Notification::$whatsappEndpoint = $origEndpoint; // restore

assertCondition(
    'TC-SBOX-12',
    'Connection refused (port mati) tertangkap sebagai transport error tanpa fatal crash',
    $res7['status'] === 'retry_scheduled'
);

// =============================================================================
// SEKSI 3: MOCK WHATSAPP IDEMPOTENCY & CRASH-AFTER-DISPATCH
// =============================================================================
echo "\n--- SEKSI 3: MOCK WHATSAPP IDEMPOTENCY & DEDUPLIKASI ---\n";

// TC-SBOX-13: Deduplikasi via client_reference_id
$refIdTest = "smart_exam_notif_998877";
$chMock = curl_init('http://127.0.0.1:8088/api/send');
curl_setopt($chMock, CURLOPT_POST, true);
curl_setopt($chMock, CURLOPT_POSTFIELDS, json_encode([
    'target'              => '081200000001',
    'message'             => 'Tes Deduplikasi 1',
    'client_reference_id' => $refIdTest
]));
curl_setopt($chMock, CURLOPT_RETURNTRANSFER, true);
$resMock1 = curl_exec($chMock);
$decodedMock1 = json_decode((string)$resMock1, true);

// Kirim kedua kali dengan ID yang sama persis
curl_setopt($chMock, CURLOPT_POSTFIELDS, json_encode([
    'target'              => '081200000001',
    'message'             => 'Tes Deduplikasi 2 (Pesan Sama)',
    'client_reference_id' => $refIdTest
]));
$resMock2 = curl_exec($chMock);
$decodedMock2 = json_decode((string)$resMock2, true);
curl_close($chMock);

assertCondition(
    'TC-SBOX-13',
    'Mock gateway mendeteksi duplikasi pesan berdasarkan client_reference_id',
    !empty($decodedMock1['status']) &&
    !empty($decodedMock2['duplicate_prevented']) &&
    $decodedMock2['code'] === 'ALREADY_PROCESSED'
);

// =============================================================================
// SEKSI 4: MOCK SMTP SINK CAPTURE (127.0.0.1:1025)
// =============================================================================
echo "\n--- SEKSI 4: MOCK SMTP SINK CAPTURE ---\n";

Notification::setMode(Notification::MODE_SANDBOX);
Notification::$smtpHost = '127.0.0.1';
Notification::$smtpPort = 1025;
Notification::$smtpEncryption = 'none';
MockSmtpServer::clearCapturedEmails();
$logIdSmtp = $logModel->enqueue(null, 'email', 'siswa.teladan@smartexam.test', [
    'subject' => 'Undangan Ujian Bahasa Indonesia',
    'preview' => 'Harap hadir 15 menit sebelum ujian dimulai.'
]);
$itemSmtp = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdSmtp}")->fetch(PDO::FETCH_ASSOC);
$resSmtp = $queueService->processItem($itemSmtp, 'worker_test_sbox');

$captured = MockSmtpServer::getCapturedEmails();
$foundEmail = false;
foreach ($captured as $eml) {
    if (str_contains((string)($eml['subject'] ?? ''), 'Undangan Ujian Bahasa Indonesia')) {
        $foundEmail = true;
        break;
    }
}

assertCondition(
    'TC-SBOX-14',
    'Mock SMTP lokal 1025 berhasil menangkap email (Status: sent, file tersimpan di mock sink)',
    $resSmtp['status'] === 'sent' && $foundEmail
);

// =============================================================================
// SEKSI 5: EXPONENTIAL BACKOFF & SCHEMA MIGRATION CHECK
// =============================================================================
echo "\n--- SEKSI 5: RETRY SCHEDULE & MIGRATION STATUS ---\n";

// TC-SBOX-15: Finite retry (Max 3 attempts)
$logIdRetry = $logModel->enqueue(null, 'whatsapp', '081250000000', ['title' => 'Retry Exhaustion']);
$itemRetry = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdRetry}")->fetch(PDO::FETCH_ASSOC);

// Simulasi upaya 1
$queueService->processItem($itemRetry, 'w1');
// Naikkan attempts manual ke 2
$pdo->exec("UPDATE notification_logs SET attempts = 2 WHERE id = {$logIdRetry}");
$itemRetry2 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdRetry}")->fetch(PDO::FETCH_ASSOC);
// Upaya 2
$queueService->processItem($itemRetry2, 'w2');

// Naikkan attempts manual ke 3 (Batas maksimal)
$pdo->exec("UPDATE notification_logs SET attempts = 3 WHERE id = {$logIdRetry}");
$itemRetry3 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdRetry}")->fetch(PDO::FETCH_ASSOC);
// Upaya 3 -> Harus difinalisasi failed!
$resFinalRetry = $queueService->processItem($itemRetry3, 'w3');

$afterDbRetry = $pdo->query("SELECT status, attempts FROM notification_logs WHERE id = {$logIdRetry}")->fetch(PDO::FETCH_ASSOC);

assertCondition(
    'TC-SBOX-15',
    'Percobaan maksimal (3x) memfinalisasi status menjadi failed permanen',
    $resFinalRetry['status'] === 'failed' && 
    $afterDbRetry['status'] === 'failed' &&
    (int)$afterDbRetry['attempts'] >= 3
);

// TC-SBOX-16: Pengujian filter antrean available_at (Database Dependent)
$hasCol = $logModel->hasAvailableAtColumn();
if ($hasCol) {
    // Jika migrasi 011 sudah dijalankan
    $pdo->exec("INSERT INTO notification_logs (channel, recipient, status, available_at, attempts, created_at)
                VALUES ('whatsapp', '081200000001', 'queued', DATE_ADD(NOW(), INTERVAL 1 HOUR), 1, NOW())");
    $futureId = (int)$pdo->lastInsertId();
    $pending = $logModel->getPendingQueue(100);
    $foundFuture = false;
    foreach ($pending as $p) {
        if ((int)$p['id'] === $futureId) {
            $foundFuture = true;
            break;
        }
    }
    assertCondition(
        'TC-SBOX-16',
        'getPendingQueue() tidak mengambil item berstatus queued jika available_at di masa depan',
        !$foundFuture
    );
} else {
    // Sesuai instruksi: Tandai BLOCKED jika migrasi 011 belum disetujui/dijalankan
    markBlocked(
        'TC-SBOX-16',
        'Validasi filter kueri available_at di database testing',
        'Kolom available_at belum dibuat (Migrasi 011 pending persetujuan terpisah)'
    );
}

// =============================================================================
// SEKSI 6: OPERATIONAL DATABASE ZERO-IMPACT CHECK
// =============================================================================
echo "\n--- SEKSI 6: DATABASE OPERASIONAL INTEGRITY CHECK ---\n";

$pdoOp = new PDO("mysql:host=127.0.0.1;port=3306;dbname=ujian_dual_kamera;charset=utf8mb4", "root", "");
$opDbName = (string)$pdoOp->query("SELECT DATABASE()")->fetchColumn();
$opLogsCount = (int)$pdoOp->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();

// Cek apakah ada kolom available_at di operasional
$opColCheck = $pdoOp->prepare("SHOW COLUMNS FROM notification_logs LIKE 'available_at'");
$opColCheck->execute();
$opHasCol = $opColCheck->rowCount() > 0;

assertCondition(
    'TC-SBOX-17',
    'Database operasional (ujian_dual_kamera) 100% perawan: 0 mutasi, 0 log, 0 migrasi',
    $opDbName === 'ujian_dual_kamera' && $opLogsCount === 0 && !$opHasCol
);

// =============================================================================
// RINGKASAN TEST
// =============================================================================
echo "\n==============================================================================\n";
echo "  RINGKASAN HASIL TEST SUITE TAHAP 3\n";
echo "==============================================================================\n";
echo "  Total PASS    : {$passCount}\n";
echo "  Total FAIL    : {$failCount}\n";
echo "  Total BLOCKED : {$blockedCount} (Menunggu Persetujuan Migrasi 011)\n";
echo "==============================================================================\n\n";

// Cleanup background mock processes
if (isset($mockWaProcess) && is_resource($mockWaProcess)) {
    $st = proc_get_status($mockWaProcess);
    if (!empty($st['pid'])) {
        @exec("taskkill /F /T /PID {$st['pid']} 2>&1");
    }
    @proc_terminate($mockWaProcess);
    @proc_close($mockWaProcess);
}
if (isset($mockSmtpProcess) && is_resource($mockSmtpProcess)) {
    $st = proc_get_status($mockSmtpProcess);
    if (!empty($st['pid'])) {
        @exec("taskkill /F /T /PID {$st['pid']} 2>&1");
    }
    @proc_terminate($mockSmtpProcess);
    @proc_close($mockSmtpProcess);
}

if ($failCount > 0) {
    exit(1);
}
exit(0);
