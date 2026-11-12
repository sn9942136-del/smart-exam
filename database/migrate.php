                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
 * Robust Database Migration Runner CLI
 * ==============================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\App;
use App\Config\Database as DbConfig;
use App\Libraries\Database;
use Database\Seeders\DatabaseSeeder;

echo "\n";
echo "====================================================================\n";
echo "  SISTEM UJIAN ONLINE DUAL KAMERA AI - MIGRATION ENGINE             \n";
echo "====================================================================\n";

DbConfig::load();

$dbHost    = DbConfig::$host;
$dbPort    = DbConfig::$port;
$dbName    = DbConfig::$database;
$dbUser    = DbConfig::$username;
$dbPass    = DbConfig::$password;
$dbCharset = DbConfig::$charset;

$args = $argv ?? [];
$isFresh  = in_array('--fresh', $args, true);
$isStatus = in_array('--status', $args, true);
$isSeed   = in_array('--seed', $args, true) || in_array('-s', $args, true);

// 1. Pastikan Database Terbuat di Server MySQL
echo "[1/4] Memeriksa & Menginisialisasi Database [{$dbName}]...\n";
try {
    $rawDsn = "mysql:host={$dbHost};port={$dbPort};charset={$dbCharset}";
    $rootPdo = new PDO($rawDsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$dbCharset} COLLATE {$dbCharset}_unicode_ci");
    echo "  ✓ Database `{$dbName}` siap.\n";
} catch (PDOException $e) {
    echo "  ✗ Gagal menghubungkan ke server MySQL: " . $e->getMessage() . "\n";
    echo "    Pastikan MySQL di XAMPP telah berjalan pada port {$dbPort}.\n";
    exit(1);
}

// 2. Dapatkan koneksi PDO ke database target
$db = Database::getConnection();

// 3. Tangani Flag --fresh jika diminta
if ($isFresh) {
    echo "  ⚠ Menjalankan reset database (--fresh). Menghapus seluruh tabel...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $existingTables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existingTables as $tbl) {
        $db->exec("DROP TABLE IF EXISTS `{$tbl}`;");
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "  ✓ Seluruh tabel berhasil dibersihkan.\n";
}

// 4. Inisialisasi Tabel Migrations Tracking
$db->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL UNIQUE,
        `batch` INT UNSIGNED NOT NULL,
        `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 5. Pindai Berkas Migrasi SQL (Hanya berkas .sql di root direktori migrations, abaikan subfolder)
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files, SORT_NATURAL);

$executedMigrations = $db->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$lastBatch = (int) $db->query("SELECT MAX(batch) FROM migrations")->fetchColumn();
$currentBatch = $lastBatch + 1;

if ($isStatus) {
    echo "\n[STATUS MIGRASI DATABASE]\n";
    echo str_pad("Nama Berkas Migrasi", 50) . str_pad("Status", 15) . "Batch\n";
    echo str_repeat("-", 75) . "\n";
    foreach ($files as $file) {
        $basename = basename($file);
        $applied = in_array($basename, $executedMigrations, true);
        $batchNum = $applied ? (string)$db->query("SELECT batch FROM migrations WHERE migration = '{$basename}'")->fetchColumn() : "-";
        $statusText = $applied ? "✓ Terpasang" : "○ Pending";
        echo str_pad($basename, 50) . str_pad($statusText, 15) . $batchNum . "\n";
    }
    echo "\n";
    exit(0);
}

echo "[2/4] Menjalankan Berkas Migrasi SQL...\n";
$migratedCount = 0;

foreach ($files as $filePath) {
    $filename = basename($filePath);

    if (in_array($filename, $executedMigrations, true)) {
        echo "  • Lewati [{$filename}] (Sudah dijalankan sebelumnya)\n";
        continue;
    }

    echo "  ▶ Menjalankan migrasi: [{$filename}]...\n";
    $sql = file_get_contents($filePath);

    if (empty(trim($sql))) {
        continue;
    }

    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Eksekusi DDL script
        $db->exec($sql);
        
        // Catat ke tabel tracking migrations
        $stmtTrack = $db->prepare("INSERT INTO migrations (migration, batch, executed_at) VALUES (:migration, :batch, NOW())");
        $stmtTrack->execute([
            ':migration' => $filename,
            ':batch'     => $currentBatch
        ]);

        $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

        echo "    ✓ Berhasil dieksekusi [Batch {$currentBatch}]\n";
        $migratedCount++;
    } catch (\Throwable $e) {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Periksa jika error hanya berupa duplicate column/key yang sebenarnya sudah ada
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
            echo "    ⚠ Peringatan: Elemen sudah ada di database, mencatat migrasi sebagai selesai.\n";
            $stmtTrack = $db->prepare("INSERT INTO migrations (migration, batch, executed_at) VALUES (:migration, :batch, NOW())");
            $stmtTrack->execute([
                ':migration' => $filename,
                ':batch'     => $currentBatch
            ]);
            $migratedCount++;
        } else {
            echo "    ✗ Gagal mengeksekusi migrasi [{$filename}]: " . $msg . "\n";
            exit(1);
        }
    }
}

if ($migratedCount === 0) {
    echo "  ✓ Seluruh berkas migrasi sudah up-to-date. Tidak ada skrip baru.\n";
} else {
    echo "  ✓ Sebanyak {$migratedCount} berkas migrasi berhasil diterapkan.\n";
}

// 6. Verifikasi Jumlah Tabel Terbuat
echo "[3/4] Memverifikasi Skema Tabel di Database...\n";
$tables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
echo "  ✓ Ditemukan " . count($tables) . " tabel aktif di dalam database `{$dbName}`:\n";

$columnWidth = 28;
$columns = 3;
$tableChunks = array_chunk($tables, $columns);
foreach ($tableChunks as $chunk) {
    $line = "    ";
    foreach ($chunk as $tbl) {
        $line .= str_pad("• " . $tbl, $columnWidth);
    }
    echo $line . "\n";
}

// 7. Jalankan Seeder jika argumen --seed, --fresh, atau jika data users masih kosong
echo "\n[4/4] Memeriksa Kebutuhan Database Seeding...\n";
$userCount = 0;
try {
    $userCount = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (\Throwable) {
    $userCount = 0;
}

if ($isSeed || $isFresh || $userCount === 0) {
    echo "  ▶ Menjalankan Database Seeder...\n";
    require_once __DIR__ . '/seeders/DatabaseSeeder.php';
    DatabaseSeeder::run();
} else {
    echo "  ✓ Melewati seeding (Data sudah tersedia dengan {$userCount} pengguna. Gunakan flag --seed untuk menyegarkan data).\n";
}

echo "\n====================================================================\n";
echo "  MIGRASI DATABASE SELESAI DENGAN SUKSES (100% READY)              \n";
echo "====================================================================\n\n";
