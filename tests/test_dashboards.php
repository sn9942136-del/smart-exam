<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Config/Routes.php';

$authService = new \App\Services\AuthService();

echo "=== TEST GURU LOGIN & DASHBOARD ACCESS ===\n";
$guruLogin = $authService->attempt('guru@example.com', 'Guru123!');
echo "Guru Login Success: " . ($guruLogin['success'] ? 'YES' : 'NO') . PHP_EOL;
echo "Guru User Role: " . user_role() . PHP_EOL;
echo "Guru has_role('guru'): " . (has_role('guru') ? 'YES' : 'NO') . PHP_EOL;

try {
    $guruCtrl = new \App\Controllers\GuruDashboardController();
    $guruResp = $guruCtrl->index();
    echo "Guru Dashboard HTTP Status: " . $guruResp->getStatusCode() . PHP_EOL;
    echo "Guru Dashboard Content Length: " . strlen($guruResp->getContent()) . " bytes" . PHP_EOL;
} catch (\Throwable $e) {
    echo "[ERROR GURU DASHBOARD] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

echo "\n=== TEST SISWA LOGIN & DASHBOARD ACCESS ===\n";
$siswaLogin = $authService->attempt('siswa@example.com', 'Siswa123!');
echo "Siswa Login Success: " . ($siswaLogin['success'] ? 'YES' : 'NO') . PHP_EOL;
echo "Siswa User Role: " . user_role() . PHP_EOL;
echo "Siswa has_role('peserta_didik'): " . (has_role('peserta_didik') ? 'YES' : 'NO') . PHP_EOL;
echo "Siswa has_role('siswa'): " . (has_role('siswa') ? 'YES' : 'NO') . PHP_EOL;

try {
    $siswaCtrl = new \App\Controllers\StudentDashboardController();
    $siswaResp = $siswaCtrl->index();
    echo "Siswa Dashboard HTTP Status: " . $siswaResp->getStatusCode() . PHP_EOL;
    echo "Siswa Dashboard Content Length: " . strlen($siswaResp->getContent()) . " bytes" . PHP_EOL;
} catch (\Throwable $e) {
    echo "[ERROR SISWA DASHBOARD] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

echo "\n=== TEST ADMIN LOGIN & DASHBOARD ACCESS ===\n";
$adminLogin = $authService->attempt('admin@example.com', 'Admin123!');
echo "Admin Login Success: " . ($adminLogin['success'] ? 'YES' : 'NO') . PHP_EOL;
echo "Admin User Role: " . user_role() . PHP_EOL;

try {
    $adminCtrl = new \App\Controllers\AdminDashboardController();
    $adminResp = $adminCtrl->index();
    echo "Admin Dashboard HTTP Status: " . $adminResp->getStatusCode() . PHP_EOL;
    echo "Admin Dashboard Content Length: " . strlen($adminResp->getContent()) . " bytes" . PHP_EOL;
} catch (\Throwable $e) {
    echo "[ERROR ADMIN DASHBOARD] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
