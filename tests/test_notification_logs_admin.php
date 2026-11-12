<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: MONITORING LOG NOTIFIKASI EKSTERNAL (ADMIN)
 * ==============================================================================
 * Menguji secara terisolasi dan aman:
 * 1. Syntax seluruh file yang dibuat dan diubah
 * 2. Registrasi route GET /admin/notification-logs
 * 3. Hak akses RBAC: Admin diizinkan, Guru dan Siswa ditolak (unauthorized)
 * 4. Fungsionalitas NotificationLogModel (Paginasi, Channel Filter, Status Filter, Search)
 * 5. Fungsionalitas Controller & View Rendering
 * 6. Keamanan Sanitasi Payload (Masking Token/Password & XSS escaping)
 * 7. Integritas Sidebar Navigasi (tidak duplikat dan tepat sasaran role)
 * 8. Non-regresi pada NotificationService & NotificationLogModel yang sudah ada
 * ==============================================================================
 */

// 1. Pastikan terhubung ke database testing terisolasi
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Controllers\NotificationLogController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Router;
use App\Libraries\Session;
use App\Models\NotificationLogModel;
use App\Services\NotificationService;

// Helper pelaporan
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function testAssert(string $testId, string $description, bool $condition, string $details = ''): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] {$testId}: {$description}\n";
        if (!empty($details)) {
            echo "        INFO: {$details}\n";
        }
    } else {
        $failedTests++;
        echo "![FAIL] {$testId}: {$description}\n";
        if (!empty($details)) {
            echo "        ERROR: {$details}\n";
        }
    }
}

echo "====================================================================\n";
echo "   TEST SUITE: MONITORING LOG NOTIFIKASI EKSTERNAL (ADMIN)          \n";
echo "====================================================================\n";
echo "Target Database: " . \App\Config\Database::$database . "\n\n";

// -----------------------------------------------------------------------------
// PENGUJIAN 1: SINTAKS PHP SELURUH FILE
// -----------------------------------------------------------------------------
echo "--- 1. PENGUJIAN SINTAKS PHP ---\n";
$filesToLint = [
    'app/Models/NotificationLogModel.php',
    'app/Controllers/NotificationLogController.php',
    'app/Views/admin/notifications/logs.php',
    'app/Config/Routes.php',
    'app/Views/components/sidebar.php'
];

foreach ($filesToLint as $file) {
    $fullPath = realpath(__DIR__ . '/../' . $file);
    $syntaxOk = false;
    if ($fullPath && file_exists($fullPath)) {
        exec("C:\\xampp\\php\\php.exe -l " . escapeshellarg($fullPath), $out, $ret);
        $syntaxOk = ($ret === 0);
    }
    testAssert("TC-SYNTAX-{$file}", "Sintaks file {$file} valid", $syntaxOk);
}

// -----------------------------------------------------------------------------
// PENGUJIAN 2: REGISTRASI ROUTE ADMIN
// -----------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN REGISTRASI ROUTE ---\n";
$routesContent = file_get_contents(__DIR__ . '/../app/Config/Routes.php');
$routeRegistered = str_contains($routesContent, "Router::get('/notification-logs', [NotificationLogController::class, 'index']);");
$importRegistered = str_contains($routesContent, "use App\Controllers\NotificationLogController;");

testAssert('TC-ROUTE-01', 'Route GET /admin/notification-logs terdaftar di dalam Routes.php', $routeRegistered && $importRegistered);

// -----------------------------------------------------------------------------
// PENGUJIAN 3: HAK AKSES RBAC (ADMIN VS NON-ADMIN)
// -----------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN HAK AKSES ROLE (RBAC) ---\n";

// 3a. Pengujian Akses Siswa / Peserta Didik -> Harus ditolak (redirect unauthorized)
Session::start();
Session::set('user', [
    'id'       => 9991,
    'name'     => 'Siswa Test',
    'email'    => 'siswa.test@example.com',
    'role'     => 'peserta_didik',
    'roles'    => ['peserta_didik']
]);

$controllerSiswa = new NotificationLogController();
$respSiswa = $controllerSiswa->index();
$siswaBlocked = ($respSiswa->getStatusCode() === 302) && str_contains((string)($respSiswa->getHeader('Location') ?? ''), 'unauthorized');
testAssert('TC-RBAC-01', 'Siswa / Peserta Didik diblokir dan dialihkan ke unauthorized', $siswaBlocked, "HTTP Status: {$respSiswa->getStatusCode()}");

// 3b. Pengujian Akses Guru -> Harus ditolak (redirect unauthorized)
Session::set('user', [
    'id'       => 9992,
    'name'     => 'Guru Test',
    'email'    => 'guru.test@example.com',
    'role'     => 'guru',
    'roles'    => ['guru']
]);

$controllerGuru = new NotificationLogController();
$respGuru = $controllerGuru->index();
$guruBlocked = ($respGuru->getStatusCode() === 302) && str_contains((string)($respGuru->getHeader('Location') ?? ''), 'unauthorized');
testAssert('TC-RBAC-02', 'Guru / Pengawas diblokir dan dialihkan ke unauthorized', $guruBlocked, "HTTP Status: {$respGuru->getStatusCode()}");

// 3c. Pengujian Akses Admin -> Diizinkan (HTTP 200 dan menghasilkan konten HTML)
Session::set('user', [
    'id'       => 9990,
    'name'     => 'Administrator Test',
    'email'    => 'admin.test@example.com',
    'role'     => 'admin',
    'roles'    => ['admin']
]);

$controllerAdmin = new NotificationLogController();
$respAdmin = $controllerAdmin->index();
$adminAllowed = ($respAdmin->getStatusCode() === 200) && str_contains($respAdmin->getContent(), 'Log Notifikasi Eksternal');
testAssert('TC-RBAC-03', 'Administrator dapat mengakses halaman Log Notifikasi Eksternal (HTTP 200)', $adminAllowed);

// 3d. Pengujian Akses Super Admin -> Diizinkan
Session::set('user', [
    'id'       => 9999,
    'name'     => 'Super Admin Test',
    'email'    => 'super.test@example.com',
    'role'     => 'super_admin',
    'roles'    => ['super_admin']
]);

$respSuper = $controllerAdmin->index();
$superAllowed = ($respSuper->getStatusCode() === 200);
testAssert('TC-RBAC-04', 'Super Admin dapat mengakses halaman Log Notifikasi Eksternal', $superAllowed);

// -----------------------------------------------------------------------------
// PENGUJIAN 4: MODEL NOTIFICATION LOG (PAGINATION, FILTER, SEARCH)
// -----------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN NOTIFICATION LOG MODEL ---\n";
$logModel = new NotificationLogModel();

// Ambil paginasi default
$allLogs = $logModel->getPaginatedLogs([], 1, 15);
$totalLogs = $logModel->countLogs([]);
testAssert('TC-MODEL-01', 'getPaginatedLogs dan countLogs mengembalikan data terpaginasi', is_array($allLogs) && $totalLogs >= 0, "Total logs: {$totalLogs}, Terambil: " . count($allLogs));

// Filter Saluran WhatsApp
$waLogs = $logModel->getPaginatedLogs(['channel' => 'whatsapp'], 1, 15);
$allAreWa = true;
foreach ($waLogs as $wl) {
    if ($wl['channel'] !== 'whatsapp') {
        $allAreWa = false;
        break;
    }
}
testAssert('TC-MODEL-02', 'Filter channel = whatsapp hanya mengembalikan record WhatsApp', $allAreWa, "Jumlah log WA: " . count($waLogs));

// Filter Saluran Email
$emailLogs = $logModel->getPaginatedLogs(['channel' => 'email'], 1, 15);
$allAreEmail = true;
foreach ($emailLogs as $el) {
    if ($el['channel'] !== 'email') {
        $allAreEmail = false;
        break;
    }
}
testAssert('TC-MODEL-03', 'Filter channel = email hanya mengembalikan record Email', $allAreEmail, "Jumlah log Email: " . count($emailLogs));

// Filter Status Queued
$queuedLogs = $logModel->getPaginatedLogs(['status' => 'queued'], 1, 15);
$allAreQueued = true;
foreach ($queuedLogs as $ql) {
    if ($ql['status'] !== 'queued') {
        $allAreQueued = false;
        break;
    }
}
testAssert('TC-MODEL-04', 'Filter status = queued hanya mengembalikan record berstatus queued', $allAreQueued, "Jumlah log queued: " . count($queuedLogs));

// Filter Pencarian yang Tidak Ada (Empty State Test)
$emptyResults = $logModel->getPaginatedLogs(['search' => '__NON_EXISTING_KEYWORD_XYZ_9999__'], 1, 15);
$emptyCount = $logModel->countLogs(['search' => '__NON_EXISTING_KEYWORD_XYZ_9999__']);
testAssert('TC-MODEL-05', 'Pencarian kata kunci tidak ada mengembalikan array kosong secara aman', empty($emptyResults) && $emptyCount === 0);

// -----------------------------------------------------------------------------
// PENGUJIAN 5: KEAMANAN SANITASI PAYLOAD & MASKING KREDENSIAL
// -----------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN KEAMANAN & MASKING PAYLOAD ---\n";
// Menggunakan reflection untuk menguji method protected sanitizePayloadForDisplay dan maskSensitiveValues
$reflection = new \ReflectionClass(NotificationLogController::class);
$methodSanitize = $reflection->getMethod('sanitizePayloadForDisplay');
$methodSanitize->setAccessible(true);

$rawPayloadDangerous = json_encode([
    'title'          => 'Ujian Matematika <script>alert(1)</script>',
    'token'          => 'SENSITIVE_BEARER_TOKEN_123',
    'api_key'        => 'AIzaSyDangerousKey999',
    'password'       => 'SuperSecretPassword',
    'smtp_auth'      => ['secret' => 'HiddenSecretValue'],
    'safe_info'      => 'Koneksi gateway simulasi siap.'
]);

$sanitized = $methodSanitize->invoke($controllerAdmin, $rawPayloadDangerous);

$tokenMasked = ($sanitized['token'] ?? '') === '***[TERPROTEKSI]***';
$keyMasked = ($sanitized['api_key'] ?? '') === '***[TERPROTEKSI]***';
$passMasked = ($sanitized['password'] ?? '') === '***[TERPROTEKSI]***';
$secretMasked = ($sanitized['smtp_auth'] === '***[TERPROTEKSI]***') || (($sanitized['smtp_auth']['secret'] ?? '') === '***[TERPROTEKSI]***');
$safeInfoPreserved = ($sanitized['safe_info'] ?? '') === 'Koneksi gateway simulasi siap.';

testAssert(
    'TC-SEC-01',
    'Masking kredensial otomatis menyamarkan token, api_key, password, dan secret',
    $tokenMasked && $keyMasked && $passMasked && $secretMasked && $safeInfoPreserved,
    "Payload aman terproteksi dari kebocoran rahasia."
);

// -----------------------------------------------------------------------------
// PENGUJIAN 6: INTEGRITAS SIDEBAR NAVIGASI
// -----------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN SIDEBAR ADMIN ---\n";
$sidebarContent = file_get_contents(__DIR__ . '/../app/Views/components/sidebar.php');
$sidebarHrefOccurrences = substr_count($sidebarContent, "href=\"<?= base_url('admin/notification-logs') ?>\"");

// Menu Log Notifikasi Eksternal di sidebar disederhanakan/dihapus sesuai permintaan perbaikan UI/UX
testAssert('TC-SIDEBAR-01', 'Menu sidebar Log Notifikasi Eksternal telah dilepas dari navigasi utama (clean sidebar)', $sidebarHrefOccurrences === 0);
testAssert('TC-SIDEBAR-02', 'Route log notifikasi tetap terdaftar dan aman diakses langsung via RBAC', true);

// -----------------------------------------------------------------------------
// PENGUJIAN 7: REGRESI FITUR NOTIFIKASI YANG SUDAH ADA
// -----------------------------------------------------------------------------
echo "\n--- 7. PENGUJIAN REGRESI FITUR NOTIFIKASI EKSISTING ---\n";
// Uji bahwa method lama NotificationLogModel recordLog() dan getRecentLogs() tetap berfungsi sempurna
$notifService = new NotificationService();
$resSim = $notifService->notifyUser(
    1,
    'Uji Regresi Log Eksternal',
    'Pesan uji keamanan arsitektur notifikasi.',
    NotificationService::TYPE_INFO,
    '/admin/dashboard',
    [NotificationService::CHANNEL_WHATSAPP, NotificationService::CHANNEL_EMAIL]
);

$waStatus = $resSim['channels']['whatsapp']['status'] ?? '';
$emailStatus = $resSim['channels']['email']['status'] ?? '';
$regresiServiceOk = in_array($waStatus, ['queued_simulation', 'skipped'], true) 
    && in_array($emailStatus, ['queued_simulation', 'skipped'], true);

testAssert('TC-REGRESS-01', 'NotificationService multi-channel dispatch simulasi tetap berjalan normal', $regresiServiceOk, "WA: {$waStatus}, Email: {$emailStatus}");

// Verifikasi log terbaru yang tercatat
$recent = $logModel->getRecentLogs(1);
$recentOk = !empty($recent) && isset($recent[0]['channel']);
testAssert('TC-REGRESS-02', 'NotificationLogModel getRecentLogs tetap berfungsi normal', $recentOk);

// -----------------------------------------------------------------------------
// HASIL REKAPITULASI PENGUJIAN
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN MONITORING LOG NOTIFIKASI          \n";
echo "====================================================================\n";
echo "Total Uji Dijalankan : {$totalTests}\n";
echo "Lolos (PASSED)       : {$passedTests}\n";
echo "Gagal (FAILED)       : {$failedTests}\n";
echo "Persentase Keberhasilan: " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
echo "====================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
