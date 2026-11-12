<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CONCURRENT WORKER REAL PROCESS TEST BENCHMARK
 * ==============================================================================
 * Script ini menguji secara nyata apakah dua proses PHP CLI independen yang
 * berjalan PARALEL secara bersamaan dapat memicu double dispatch pada
 * implementasi antrean saat ini.
 * ==============================================================================
 */

$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

$db = \App\Libraries\Database::getConnection();

// Bersihkan data uji concurrency terdahulu
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%concurrent_audit%'");

// 1. Masukkan 1 item antrean baru untuk diperebutkan oleh Worker A dan Worker B
$now = date('Y-m-d H:i:s');
$payload = json_encode([
    'title'       => 'Audit Uji Balap Konkurensi',
    'preview'     => 'Uji perebutan item tunggal oleh 2 worker paralel',
    'queued_at'   => $now,
    'simulation'  => true
]);

$stmt = $db->prepare("
    INSERT INTO notification_logs (channel, recipient, status, response_payload, sent_at, created_at)
    VALUES ('whatsapp', '081299991111_concurrent_audit', 'queued', :payload, :sent_at, :created_at)
");
$stmt->execute([
    ':payload'    => $payload,
    ':sent_at'    => $now,
    ':created_at' => $now
]);
$testId = (int)$db->lastInsertId();

echo "====================================================================\n";
echo "  BENCHMARK PENGUJIAN KONKURENSI WORKER PARALEL NYATA             \n";
echo "====================================================================\n";
echo "Target Item ID: {$testId}\n";
echo "Status Awal   : queued\n";
echo "Menjalankan Worker A dan Worker B secara paralel via proc_open...\n\n";

// File log audit dispatcher untuk memverifikasi apakah fungsi dispatch terpanggil 2 kali
$auditLogFile = __DIR__ . '/concurrent_dispatch_audit.log';
if (file_exists($auditLogFile)) {
    unlink($auditLogFile);
}

// Script worker child mandiri
$workerScript = <<<'PHP'
<?php
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");
require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

$workerName = $argv[1] ?? 'Worker-Unknown';
$testId = (int)($argv[2] ?? 0);
$logFile = $argv[3] ?? '';

$queueService = new \App\Services\NotificationQueueService();
$db = \App\Libraries\Database::getConnection();

// Ambil item
$stmt = $db->prepare("SELECT * FROM notification_logs WHERE id = :id AND status = 'queued'");
$stmt->execute([':id' => $testId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "[{$workerName}] Item #{$testId} tidak ditemukan berstatus queued.\n";
    exit(0);
}

// Jalankan processItem pada QueueService dengan identitas worker
$res = $queueService->processItem($item, $workerName);

if (!empty($logFile)) {
    if (($res['dispatched'] ?? false) === true) {
        file_put_contents($logFile, "[{$workerName}] EXECUTED_DISPATCH: Item #{$testId} pada " . date('H:i:s.') . substr((string)microtime(), 2, 4) . " -> Status: {$res['status']}\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "[{$workerName}] SKIPPED_NO_DISPATCH: Item #{$testId} (Klaim ditolak atomik) -> Status: {$res['status']}\n", FILE_APPEND);
    }
}

echo "[{$workerName}] Hasil: {$res['status']} | Claimed: " . ($res['claimed'] ? 'TRUE' : 'FALSE') . " | Dispatched: " . ($res['dispatched'] ? 'TRUE' : 'FALSE') . "\n";
exit(0);
PHP;

$childScriptPath = __DIR__ . '/temp_worker_runner.php';
file_put_contents($childScriptPath, $workerScript);

// Jalankan Worker A dan Worker B serentak menggunakan proc_open
$phpBin = 'C:\\xampp\\php\\php.exe';
$cmdA = "\"{$phpBin}\" \"{$childScriptPath}\" \"Worker-A\" {$testId} \"{$auditLogFile}\"";
$cmdB = "\"{$phpBin}\" \"{$childScriptPath}\" \"Worker-B\" {$testId} \"{$auditLogFile}\"";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$procA = proc_open($cmdA, $descriptors, $pipesA);
$procB = proc_open($cmdB, $descriptors, $pipesB);

$outA = stream_get_contents($pipesA[1]);
$errA = stream_get_contents($pipesA[2]);
fclose($pipesA[0]); fclose($pipesA[1]); fclose($pipesA[2]);

$outB = stream_get_contents($pipesB[1]);
$errB = stream_get_contents($pipesB[2]);
fclose($pipesB[0]); fclose($pipesB[1]); fclose($pipesB[2]);

proc_close($procA);
proc_close($procB);

echo "Output Worker A:\n" . trim($outA) . "\n\n";
echo "Output Worker B:\n" . trim($outB) . "\n\n";

if (file_exists($auditLogFile)) {
    $auditLogs = file_get_contents($auditLogFile);
    echo "--- Rekaman Log Eksekusi Dispatch Riil ---\n";
    echo $auditLogs;
    echo "------------------------------------------\n";

    $dispatchCount = substr_count($auditLogs, 'EXECUTED_DISPATCH');
    echo "\nTotal Eksekusi Dispatch Terjadi : {$dispatchCount} kali.\n";
    if ($dispatchCount === 1) {
        echo "\033[32m[SUKSES] PRE-DISPATCH ATOMIC CLAIM BEKERJA SEMPURNA! Hanya tepat 1 dispatch yang terjadi.\033[0m\n";
        echo "Worker kedua ditolak sebelum memanggil fungsi pengiriman (Zero Double Dispatch).\n";
    } else {
        echo "\033[31m[GAGAL] Terjadi {$dispatchCount} kali pemanggilan dispatch!\033[0m\n";
    }
}

// Bersihkan file sementara
if (file_exists($childScriptPath)) {
    unlink($childScriptPath);
}
if (file_exists($auditLogFile)) {
    unlink($auditLogFile);
}
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%concurrent_audit%'");

echo "\nPengujian benchmark konkurensi selesai.\n";
