<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION CONCURRENCY, CRASH RECOVERY, & LEASE ROBUSTNESS
 * ==============================================================================
 * Memvalidasi:
 * 1. Concurrency: Dua worker paralel nyata via proc_open hanya 1 yang memicu dispatch.
 * 2. Active lease protection: Item yang sedang diproses tidak dapat direbut worker lain.
 * 3. Expired lease recovery (Worker crash): Item dengan lease kedaluwarsa diambil alih secara aman.
 * 4. Token ownership guard: Worker yang kehilangan hak sewa tidak dapat melakukan finalisasi.
 * 5. Retry policy: Item yang melebihi batas retry (3x) dihentikan dan ditandai failed.
 * 6. Non-blocking queue: Item antrean lain tetap diproses meski ada item yang sedang diklaim.
 * 7. Nullable sent_at: sent_at bernilai NULL saat queued/processing, hanya terisi saat sent.
 * ==============================================================================
 */

$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Models\NotificationLogModel;
use App\Services\NotificationQueueService;

function reportTest(string $code, string $desc, bool $status, string $extra = ''): void {
    $tag = $status ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo " {$tag} {$code}: {$desc}\n";
    if (!empty($extra)) {
        echo "        \033[36mINFO:\033[0m {$extra}\n";
    }
    if (!$status) {
        echo "        \033[41;37mGAGAL: Validasi tidak sesuai ekspektasi.\033[0m\n";
    }
}

$total = 0;
$passed = 0;

function assertCondition(string $code, string $desc, bool $cond, string $extra = ''): void {
    global $total, $passed;
    $total++;
    if ($cond) {
        $passed++;
    }
    reportTest($code, $desc, $cond, $extra);
}

echo "====================================================================\n";
echo "   TEST SUITE: CONCURRENCY, CRASH RECOVERY, & LEASE ROBUSTNESS      \n";
echo "====================================================================\n";
echo "Target Database: " . \App\Config\Database::$database . "\n\n";

$db = \App\Libraries\Database::getConnection();
$logModel = new NotificationLogModel();
$queueService = new NotificationQueueService($logModel, 300, 3);

// Bersihkan data uji sebelumnya
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%concurrency_suite%'");

// -----------------------------------------------------------------------------
// 1. PENGUJIAN DUA WORKER PARALEL NYATA (PROC_OPEN)
// -----------------------------------------------------------------------------
echo "--- 1. PENGUJIAN DUA WORKER PARALEL NYATA (PROC_OPEN) ---\n";

$resEnqueue = $queueService->enqueue(
    null,
    'whatsapp',
    '081299992222_concurrency_suite',
    ['title' => 'Uji Balap Paralel Nyata']
);
$testId = (int)$resEnqueue['log_id'];

$auditLog = __DIR__ . '/test_parallel_audit.log';
if (file_exists($auditLog)) {
    unlink($auditLog);
}

$childScript = __DIR__ . '/temp_runner_child.php';
$childCode = <<<'PHP'
<?php
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");
require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

$name = $argv[1] ?? 'Worker';
$id = (int)$argv[2];
$log = $argv[3];

$qs = new \App\Services\NotificationQueueService();
$db = \App\Libraries\Database::getConnection();

$stmt = $db->prepare("SELECT * FROM notification_logs WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$res = $qs->processItem($row, $name);
if (($res['dispatched'] ?? false) === true) {
    file_put_contents($log, "DISPATCH_CALLED by {$name}\n", FILE_APPEND);
} else {
    file_put_contents($log, "DISPATCH_SKIPPED by {$name}\n", FILE_APPEND);
}
exit(0);
PHP;
file_put_contents($childScript, $childCode);

$php = 'C:\\xampp\\php\\php.exe';
$cmd1 = "\"{$php}\" \"{$childScript}\" Worker1 {$testId} \"{$auditLog}\"";
$cmd2 = "\"{$php}\" \"{$childScript}\" Worker2 {$testId} \"{$auditLog}\"";

$desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$p1 = proc_open($cmd1, $desc, $pipes1);
$p2 = proc_open($cmd2, $desc, $pipes2);

fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]);
fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]);
proc_close($p1);
proc_close($p2);

$auditContent = file_exists($auditLog) ? file_get_contents($auditLog) : '';
$dispatchCalls = substr_count($auditContent, 'DISPATCH_CALLED');
$dispatchSkips = substr_count($auditContent, 'DISPATCH_SKIPPED');

assertCondition(
    'TC-CONCUR-01',
    'Dua worker paralel nyata merebutkan item yang sama: Tepat 1 worker yang mengeksekusi dispatch',
    $dispatchCalls === 1 && $dispatchSkips === 1,
    "Jumlah dispatch terpanggil: {$dispatchCalls}, Jumlah dispatch dilewati: {$dispatchSkips}"
);

// Bersihkan file sementara
if (file_exists($childScript)) unlink($childScript);
if (file_exists($auditLog)) unlink($auditLog);

// -----------------------------------------------------------------------------
// 2. PENGUJIAN PROTEKSI SEWA KLAIM AKTIF (ACTIVE LEASE PROTECTION)
// -----------------------------------------------------------------------------
echo "\n--- 2. PROTEKSI SEWA KLAIM AKTIF (ACTIVE LEASE) ---\n";

$enqueueActive = $queueService->enqueue(
    null,
    'email',
    'active_lease@example.com_concurrency_suite',
    ['title' => 'Uji Active Lease']
);
$activeId = (int)$enqueueActive['log_id'];

// Worker A mengklaim item (lease 300 detik)
$claimedA = $logModel->claimItem($activeId, 'token_worker_A', 300, 3);

// Worker B mencoba mengklaim item yang sama saat sewa masih aktif (baru 0 detik)
$claimedB = $logModel->claimItem($activeId, 'token_worker_B', 300, 3);

assertCondition(
    'TC-CONCUR-02',
    'Item dengan sewa aktif (processing) tidak dapat direbut oleh worker lain',
    $claimedA === true && $claimedB === false,
    "Worker A Claim: " . ($claimedA ? 'TRUE' : 'FALSE') . " | Worker B Claim: " . ($claimedB ? 'TRUE' : 'FALSE')
);

// -----------------------------------------------------------------------------
// 3. PENGUJIAN RECOVERY WORKER CRASH (EXPIRED LEASE RECOVERY)
// -----------------------------------------------------------------------------
echo "\n--- 3. RECOVERY WORKER CRASH (EXPIRED LEASE TIMEOUT) ---\n";

// Simulasikan Worker A mati mendadak setelah klaim (mundurkan claimed_at menjadi 400 detik lalu)
$db->exec("UPDATE notification_logs SET claimed_at = (NOW() - INTERVAL 400 SECOND) WHERE id = {$activeId}");

// Worker C datang dan mencoba mengklaim item yang telah kedaluwarsa (lease 300s)
$claimedC = $logModel->claimItem($activeId, 'token_worker_C', 300, 3);

// Ambil data untuk verifikasi pemilik baru
$recoveredItem = $db->query("SELECT * FROM notification_logs WHERE id = {$activeId}")->fetch(PDO::FETCH_ASSOC);

assertCondition(
    'TC-CONCUR-03',
    'Item dari worker yang crash (sewa kedaluwarsa > 300s) berhasil dipulihkan & diklaim oleh worker baru',
    $claimedC === true && $recoveredItem['claimed_by'] === 'token_worker_C',
    "Pemilik klaim baru: {$recoveredItem['claimed_by']}, Status: {$recoveredItem['status']}, Attempts: {$recoveredItem['attempts']}"
);

// -----------------------------------------------------------------------------
// 4. PENGUJIAN WORKER KEHILANGAN KLAIM (TOKEN OWNERSHIP GUARD)
// -----------------------------------------------------------------------------
echo "\n--- 4. WORKER KEHILANGAN KLAIM (TOKEN OWNERSHIP GUARD) ---\n";

// Worker A (yang bangun kembali setelah timeout) mencoba melakukan finalisasi dengan token lamanya
$finalizedA = $logModel->finalizeClaim($activeId, 'token_worker_A', 'sent', ['info' => 'Mencoba finalize token usang']);

// Worker C (pemilik sah saat ini) melakukan finalisasi
$finalizedC = $logModel->finalizeClaim($activeId, 'token_worker_C', 'sent', ['info' => 'Finalize sah pemilik aktif']);

assertCondition(
    'TC-CONCUR-04',
    'Worker yang kehilangan hak sewa dilarang melakukan finalisasi; hanya pemilik sah yang berhasil',
    $finalizedA === false && $finalizedC === true,
    "Finalisasi Token Usang (A): " . ($finalizedA ? 'BERHASIL (SALAH)' : 'DITOLAK (BENAR)') . " | Finalisasi Sah (C): " . ($finalizedC ? 'BERHASIL' : 'GAGAL')
);

// -----------------------------------------------------------------------------
// 5. PENGUJIAN RETRY POLICY & MAX ATTEMPTS
// -----------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN RETRY POLICY & MAX ATTEMPTS ---\n";

$enqueueRetry = $queueService->enqueue(
    null,
    'whatsapp',
    '081288880000_concurrency_suite',
    ['title' => 'Uji Max Attempts']
);
$retryId = (int)$enqueueRetry['log_id'];

// Set attempts = 3 (batas maksimal)
$db->exec("UPDATE notification_logs SET attempts = 3 WHERE id = {$retryId}");

// Coba klaim item yang sudah mencapai batas percobaan
$claimMaxed = $logModel->claimItem($retryId, 'token_worker_retry', 300, 3);

// Jalankan processItem untuk memverifikasi transisi otomatis ke status failed
$rowRetry = $db->query("SELECT * FROM notification_logs WHERE id = {$retryId}")->fetch(PDO::FETCH_ASSOC);
$processMaxedRes = $queueService->processItem($rowRetry);

$rowRetryAfter = $db->query("SELECT * FROM notification_logs WHERE id = {$retryId}")->fetch(PDO::FETCH_ASSOC);

assertCondition(
    'TC-CONCUR-05',
    'Item yang telah mencapai batas percobaan maksimal (3x) tidak dapat diklaim dan ditandai failed',
    $claimMaxed === false && $rowRetryAfter['status'] === 'failed',
    "Status akhir: {$rowRetryAfter['status']}, Attempts: {$rowRetryAfter['attempts']}"
);

// -----------------------------------------------------------------------------
// 6. PENGUJIAN NON-BLOCKING QUEUE (ANTREAN LAIN TETAP BERJALAN)
// -----------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN NON-BLOCKING QUEUE ---\n";

// Buat Item 1 (dikunci dalam processing) dan Item 2 (berstatus queued)
$resLock1 = $queueService->enqueue(null, 'whatsapp', '081277771111_concurrency_suite');
$resQueued2 = $queueService->enqueue(null, 'whatsapp', '081277772222_concurrency_suite');

$lockId1 = (int)$resLock1['log_id'];
$queuedId2 = (int)$resQueued2['log_id'];

// Kunci Item 1
$logModel->claimItem($lockId1, 'lock_worker_1', 300, 3);

// Ambil pending queue: Item 2 harus muncul dalam daftar meski Item 1 sedang terkunci
$pending = $queueService->getPendingItems(100);
$foundQueued2 = false;
$foundLock1 = false;
foreach ($pending as $p) {
    if ((int)$p['id'] === $queuedId2) $foundQueued2 = true;
    if ((int)$p['id'] === $lockId1) $foundLock1 = true;
}

assertCondition(
    'TC-CONCUR-06',
    'Item antrean lain tetap dapat diambil dan diproses tanpa terhambat item yang sedang diklaim',
    $foundQueued2 === true && $foundLock1 === false,
    "Item bebas terambil: " . ($foundQueued2 ? 'YES' : 'NO') . " | Item terkunci tersaring: " . (!$foundLock1 ? 'YES' : 'NO')
);

// -----------------------------------------------------------------------------
// 7. PENGUJIAN NULLABLE SENT_AT
// -----------------------------------------------------------------------------
echo "\n--- 7. PENGUJIAN NULLABLE SENT_AT ---\n";

$enqueueSentAt = $queueService->enqueue(null, 'email', 'sent_at_check@example.com_concurrency_suite');
$sentAtId = (int)$enqueueSentAt['log_id'];

$rowQueued = $db->query("SELECT sent_at, status FROM notification_logs WHERE id = {$sentAtId}")->fetch(PDO::FETCH_ASSOC);

// Klaim item (status processing)
$logModel->claimItem($sentAtId, 'token_sent_at', 300, 3);
$rowProcessing = $db->query("SELECT sent_at, status FROM notification_logs WHERE id = {$sentAtId}")->fetch(PDO::FETCH_ASSOC);

// Finalisasi item (status sent)
$logModel->finalizeClaim($sentAtId, 'token_sent_at', 'sent', ['simulated' => true]);
$rowSent = $db->query("SELECT sent_at, status FROM notification_logs WHERE id = {$sentAtId}")->fetch(PDO::FETCH_ASSOC);

$tcSentAtOk = ($rowQueued['sent_at'] === null)
    && ($rowProcessing['sent_at'] === null)
    && ($rowSent['sent_at'] !== null);

assertCondition(
    'TC-CONCUR-07',
    'sent_at bernilai NULL saat queued dan processing, dan hanya terisi waktu aktual saat status sent',
    $tcSentAtOk,
    "Queued sent_at: " . var_export($rowQueued['sent_at'], true) . " | Processing sent_at: " . var_export($rowProcessing['sent_at'], true) . " | Sent sent_at: {$rowSent['sent_at']}"
);

// Bersihkan data uji setelah selesai
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%concurrency_suite%'");

// -----------------------------------------------------------------------------
// REKAPITULASI PENGUJIAN
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI PENGUJIAN CONCURRENCY & ROBUSTNESS                   \n";
echo "====================================================================\n";
echo "Total Uji Dijalankan : {$total}\n";
echo "Lolos (PASSED)       : {$passed}\n";
echo "Gagal (FAILED)       : " . ($total - $passed) . "\n";
$pct = round(($passed / $total) * 100, 2);
echo "Persentase Kelulusan : {$pct}%\n";
echo "====================================================================\n";

if ($passed === $total) {
    echo "\033[32m>>> SELURUH PENGUJIAN CONCURRENCY & ROBUSTNESS LOLOS (100% PASS) <<<\033[0m\n\n";
    exit(0);
} else {
    echo "\033[31m>>> TERDAPAT PENGUJIAN YANG GAGAL! <<<\033[0m\n\n";
    exit(1);
}
