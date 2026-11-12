<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — CONTROLLED MIGRATION RUNNER: MIGRATION 010
 * ==============================================================================
 * Menjalankan migrasi 010 secara terkontrol khusus pada database testing:
 * `ujian_dual_kamera_test`.
 * ==============================================================================
 */

$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

$db = \App\Libraries\Database::getConnection();

// 1. Validasi Keamanan Database: PASTIKAN DATABASE ADALAH DATABASE TESTING
$activeDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "====================================================================\n";
echo "  CONTROLLED MIGRATION 010: NOTIFICATION QUEUE CONCURRENCY          \n";
echo "====================================================================\n";
echo "Active Database Check: [{$activeDb}]\n";

if ($activeDb !== 'ujian_dual_kamera_test') {
    echo "\033[41;37m[FATAL ERROR] Target database BUKAN database testing!\033[0m\n";
    echo "Eksekusi migrasi dihentikan seketika demi keamanan database operasional.\n";
    exit(1);
}

// 2. Periksa Jumlah Data Sebelum Migrasi
$countBefore = (int)$db->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();
echo "Jumlah data notifikasi log sebelum migrasi: {$countBefore} baris.\n";

// 3. Buat Tabel Backup Sebelum Perubahan Skema
echo "Membuat tabel backup: notification_logs_backup_pre_concurrency_fix...\n";
$db->exec("DROP TABLE IF EXISTS notification_logs_backup_pre_concurrency_fix");
$db->exec("CREATE TABLE notification_logs_backup_pre_concurrency_fix LIKE notification_logs");
$db->exec("INSERT INTO notification_logs_backup_pre_concurrency_fix SELECT * FROM notification_logs");
$countBackup = (int)$db->query("SELECT COUNT(*) FROM notification_logs_backup_pre_concurrency_fix")->fetchColumn();
echo "  ✓ Backup selesai: {$countBackup} baris berhasil disalin ke notification_logs_backup_pre_concurrency_fix.\n\n";

// 4. Eksekusi Script Migrasi 010
$sqlFile = __DIR__ . '/migrations/010_enhance_notification_logs_queue_concurrency.sql';
if (!file_exists($sqlFile)) {
    echo "\033[31m[ERROR] File migrasi {$sqlFile} tidak ditemukan!\033[0m\n";
    exit(1);
}

$sqlContent = file_get_contents($sqlFile);
echo "Mengeksekusi SQL Migrasi 010...\n";
$db->exec($sqlContent);
echo "  ✓ ALTER TABLE berhasil dieksekusi.\n\n";

// 5. Validasi Integritas Data dan Struktur Pasca-Migrasi
$countAfter = (int)$db->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();
echo "Jumlah data notifikasi log setelah migrasi: {$countAfter} baris.\n";

if ($countBefore !== $countAfter) {
    echo "\033[31m[PERINGATAN] Jumlah baris berbeda! Sebelum: {$countBefore}, Sesudah: {$countAfter}\033[0m\n";
} else {
    echo "  ✓ Verifikasi 100% data lama utuh tanpa kehilangan baris.\n\n";
}

// 6. Cetak Kolom Baru untuk Verifikasi
echo "Kolom notification_logs pasca-migrasi:\n";
$cols = $db->query("DESCRIBE notification_logs")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo " - {$col['Field']} | {$col['Type']} | Null: {$col['Null']} | Default: {$col['Default']} | Extra: {$col['Extra']}\n";
}

echo "\n\033[32m>>> MIGRASI 010 BERHASIL DILAKSANAKAN PADA UJIAN_DUAL_KAMERA_TEST <<<\033[0m\n";
