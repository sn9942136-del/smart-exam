<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Config/Routes.php';

use App\Controllers\AiChatReportController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Models\UserModel;
use App\Services\AiChatReportService;

echo "==============================================================================\n";
echo "  TEST SUITE: LAPORAN CHATBOT AI DASHBOARD ADMIN (SMART EXAM)\n";
echo "==============================================================================\n\n";

$passedCount = 0;
$totalTests = 0;

function assertReport(bool $condition, string $message): void {
    global $passedCount, $totalTests;
    $totalTests++;
    if ($condition) {
        $passedCount++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
        throw new \Exception("Assertion Gagal: {$message}");
    }
}

$userModel = new UserModel();

function loginTestUser(UserModel $userModel, string $email, string $defaultRole): void {
    $u = $userModel->findByEmail($email);
    if (!$u) {
        throw new \Exception("User {$email} tidak ditemukan.");
    }
    $roles = $userModel->getRoles($u['id']);
    $u['role'] = !empty($roles) ? $roles[0]['name'] : $defaultRole;
    $u['roles'] = !empty($roles) ? array_column($roles, 'name') : [$defaultRole];
    login_user($u);
}

$adminUser = $userModel->findByEmail('admin@gmail.com') ?: $userModel->findByEmail('superadmin@gmail.com');
$guruUser = $userModel->findByEmail('Budisantoso@gmail.com');
$siswaUser = $userModel->findByEmail('sn9942136@gmail.com');

$db = Database::getConnection();

// --- 1. Audit Database Schema & Existing Tables ---
echo "--- 1. Audit Database & Existing Chat Tables ---\n";
$tables = $db->query("SHOW TABLES LIKE 'ai_chat_%'")->fetchAll(\PDO::FETCH_COLUMN);
assertReport(in_array('ai_chat_sessions', $tables, true), "Tabel ai_chat_sessions terverifikasi ada di database");
assertReport(in_array('ai_chat_messages', $tables, true), "Tabel ai_chat_messages terverifikasi ada di database");

$sessionCols = $db->query("DESCRIBE ai_chat_sessions")->fetchAll(\PDO::FETCH_COLUMN);
assertReport(in_array('user_id', $sessionCols, true), "ai_chat_sessions memiliki kolom user_id");
assertReport(in_array('session_uuid', $sessionCols, true), "ai_chat_sessions memiliki kolom session_uuid");
assertReport(in_array('role_context', $sessionCols, true), "ai_chat_sessions memiliki kolom role_context");

$msgCols = $db->query("DESCRIBE ai_chat_messages")->fetchAll(\PDO::FETCH_COLUMN);
assertReport(in_array('session_id', $msgCols, true), "ai_chat_messages memiliki kolom session_id");
assertReport(in_array('sender', $msgCols, true), "ai_chat_messages memiliki kolom sender");
assertReport(in_array('message', $msgCols, true), "ai_chat_messages memiliki kolom message");
assertReport(in_array('is_error', $msgCols, true), "ai_chat_messages memiliki kolom is_error");

// --- 2. Service Statistics Verification (100% dari Database Aktual) ---
echo "\n--- 2. Service Statistics (Data Riil Database) ---\n";
$service = new AiChatReportService();
$stats = $service->getStatistics();

$dbTotalSessions = (int)$db->query("SELECT COUNT(*) FROM ai_chat_sessions")->fetchColumn();
$dbTotalUserMsg = (int)$db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE sender='user'")->fetchColumn();
$dbTotalBotMsg = (int)$db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE sender='assistant'")->fetchColumn();
$dbTotalUsers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM ai_chat_sessions")->fetchColumn();
$dbErrSessions = (int)$db->query("SELECT COUNT(DISTINCT session_id) FROM ai_chat_messages WHERE is_error=1")->fetchColumn();

assertReport($stats['total_sessions'] === $dbTotalSessions, "Statistik Total Percakapan cocok dengan database ({$stats['total_sessions']})");
assertReport($stats['total_user_messages'] === $dbTotalUserMsg, "Statistik Pesan Pengguna cocok dengan database ({$stats['total_user_messages']})");
assertReport($stats['total_bot_messages'] === $dbTotalBotMsg, "Statistik Respons Chatbot cocok dengan database ({$stats['total_bot_messages']})");
assertReport($stats['total_users'] === $dbTotalUsers, "Statistik Pengguna Unik cocok dengan database ({$stats['total_users']})");
assertReport($stats['error_sessions'] === $dbErrSessions, "Statistik Percakapan Error cocok dengan database ({$stats['error_sessions']})");
assertReport(is_array($stats['role_context_usage']), "Distribusi role_context terformat sebagai array");

// --- 3. Chart Data Generation & Timeline Logic ---
echo "\n--- 3. Chart Data Generation (Aktivitas Harian, Role, Rasio) ---\n";
$chart7 = $service->getChartData('7_days');
assertReport(count($chart7['labels']) === 7, "Grafik 7 hari menghasilkan tepat 7 data titik tanggal");
assertReport(isset($chart7['user_vs_bot']['user']) && isset($chart7['user_vs_bot']['assistant']), "Grafik perbandingan pesan user vs respons bot tersedia");
assertReport(is_array($chart7['role_breakdown']), "Grafik distribusi role tersedia");

$chart30 = $service->getChartData('30_days');
assertReport(count($chart30['labels']) === 30, "Grafik 30 hari menghasilkan tepat 30 data titik tanggal");

// --- 4. Filtering, Searching, & Pagination ---
echo "\n--- 4. Filtering, Searching, & Pagination Query ---\n";
$allSessions = $service->getSessions([], 1, 10);
assertReport($allSessions['total_rows'] === $dbTotalSessions, "Pagination total rows cocok ({$allSessions['total_rows']})");
assertReport(count($allSessions['data']) <= 10, "Pagination per_page 10 dipatuhi");
assertReport(isset($allSessions['data'][0]['status_badge']), "Setiap baris sesi memiliki status_badge yang diformat");

// Test search filter
if (!empty($allSessions['data'][0]['user_name'])) {
    $searchKeyword = substr($allSessions['data'][0]['user_name'], 0, 4);
    $searchRes = $service->getSessions(['search' => $searchKeyword], 1, 10);
    assertReport($searchRes['total_rows'] >= 1, "Pencarian keyword '{$searchKeyword}' berhasil menemukan minimal 1 baris");
}

// Test status filter error
$errorRes = $service->getSessions(['status' => 'error'], 1, 10);
assertReport($errorRes['total_rows'] === $dbErrSessions, "Filter status 'error' menghasilkan baris yang sesuai ({$errorRes['total_rows']})");

// --- 5. Detail Percakapan & Chat Bubbles ---
echo "\n--- 5. Detail Percakapan (Urutan Kronologis & Read-Only) ---\n";
$sampleSessionId = (int)$db->query("SELECT session_id FROM ai_chat_messages GROUP BY session_id HAVING COUNT(*) >= 2 LIMIT 1")->fetchColumn();
if ($sampleSessionId > 0) {
    $detail = $service->getSessionDetail($sampleSessionId);
    assertReport($detail !== null, "Detail sesi #{$sampleSessionId} berhasil diambil");
    assertReport(isset($detail['user_name']) && isset($detail['user_email']), "Detail memuat identitas pengguna");
    assertReport(!empty($detail['messages']), "Detail memuat array pesan percakapan");
    
    // Verifikasi urutan kronologis ASC
    $isAsc = true;
    for ($i = 0; $i < count($detail['messages']) - 1; $i++) {
        if ($detail['messages'][$i]['id'] > $detail['messages'][$i + 1]['id']) {
            $isAsc = false;
            break;
        }
    }
    assertReport($isAsc, "Pesan-pesan terurut secara kronologis ASC (lama ke baru)");
}

// --- 6. Ekspor Excel & PDF ---
echo "\n--- 6. Ekspor Laporan (Excel & PDF) ---\n";
$excelData = $service->exportExcel([]);
assertReport(strlen($excelData) > 5000, "Ekspor Excel menghasilkan berkas biner XLSX valid (" . strlen($excelData) . " bytes)");
assertReport(str_starts_with($excelData, "PK\x03\x04"), "Format biner XLSX memiliki magic bytes ZIP/OOXML yang sah");

$pdfData = $service->exportPdf([]);
assertReport(strlen($pdfData) > 10000, "Ekspor PDF menghasilkan berkas biner PDF valid (" . strlen($pdfData) . " bytes)");
assertReport(str_starts_with($pdfData, "%PDF"), "Format biner PDF memiliki magic bytes %PDF yang sah");

// --- 7. Controller Endpoints & Role Authorization ---
echo "\n--- 7. Controller Endpoints & Role Authorization ---\n";

// A. Akses Admin Sah
loginTestUser($userModel, 'admin@gmail.com', 'admin');
$reqAdmin = new Request([], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/admin/ai-chat-reports'
]);
$controller = new AiChatReportController($reqAdmin);
$respAdmin = $controller->index();
assertReport($respAdmin->getStatusCode() === 200, "Admin dapat mengakses index Laporan Chatbot (HTTP 200)");

$respChart = $controller->chartData();
assertReport($respChart->getStatusCode() === 200, "Admin dapat mengakses AJAX chartData (HTTP 200)");

if ($sampleSessionId > 0) {
    $respDetailAjax = $controller->detailAjax($sampleSessionId);
    assertReport($respDetailAjax->getStatusCode() === 200, "Admin dapat mengakses AJAX detailAjax (HTTP 200)");

    $respDetail = $controller->detail($sampleSessionId);
    assertReport($respDetail->getStatusCode() === 200, "Admin dapat mengakses full-page detail (HTTP 200)");
}

$respXlsx = $controller->exportExcel();
assertReport($respXlsx->getStatusCode() === 200, "Admin dapat mengunduh berkas Excel (HTTP 200)");
assertReport(str_contains($respXlsx->getHeaders()['Content-Type'] ?? '', 'spreadsheetml.sheet'), "Header Content-Type Excel terpasang");

$respPdf = $controller->exportPdf();
assertReport($respPdf->getStatusCode() === 200, "Admin dapat mengunduh berkas PDF (HTTP 200)");
assertReport(($respPdf->getHeaders()['Content-Type'] ?? '') === 'application/pdf', "Header Content-Type PDF terpasang");

// B. Akses Guru DITOLAK
loginTestUser($userModel, 'Budisantoso@gmail.com', 'guru');
$reqGuru = new Request([], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/admin/ai-chat-reports'
]);
$guruController = new AiChatReportController($reqGuru);
$respGuru = $guruController->index();
assertReport($respGuru->getStatusCode() === 302, "Guru DITOLAK saat membuka index Laporan Chatbot (Redirect 302)");

$respGuruChart = $guruController->chartData();
assertReport($respGuruChart->getStatusCode() === 403, "Guru DITOLAK saat memanggil AJAX chartData (HTTP 403 Forbidden)");

// C. Akses Siswa DITOLAK
loginTestUser($userModel, 'sn9942136@gmail.com', 'peserta_didik');
$reqSiswa = new Request([], [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/admin/ai-chat-reports'
]);
$siswaController = new AiChatReportController($reqSiswa);
$respSiswa = $siswaController->index();
assertReport($respSiswa->getStatusCode() === 302, "Siswa DITOLAK saat membuka index Laporan Chatbot (Redirect 302)");

$respSiswaChart = $siswaController->chartData();
assertReport($respSiswaChart->getStatusCode() === 403, "Siswa DITOLAK saat memanggil AJAX chartData (HTTP 403 Forbidden)");

// --- 8. Sidebar & Routing Verification ---
echo "\n--- 8. Sidebar Navigation & Routing Integration ---\n";
$sidebarContent = file_get_contents(__DIR__ . '/../app/Views/components/sidebar.php');
assertReport(str_contains($sidebarContent, 'admin/ai-chat-reports'), "Sidebar admin memuat tautan ke 'admin/ai-chat-reports'");
assertReport(str_contains($sidebarContent, 'Laporan Chatbot'), "Sidebar admin memuat teks label 'Laporan Chatbot'");

$routesContent = file_get_contents(__DIR__ . '/../app/Config/Routes.php');
assertReport(str_contains($routesContent, 'ai-chat-reports'), "Routes.php mendaftarkan rute ai-chat-reports");
assertReport(str_contains($routesContent, 'AiChatReportController'), "Routes.php menggunakan AiChatReportController");

// --- 9. Keamanan & Sanitasi Sensitif ---
echo "\n--- 9. Keamanan & Sanitasi Data Sensitif ---\n";
$sensitiveRaw = "Ini kunci rahasia saya: AQ.DummyTestKey1234567890123456789012345678901234567890 dan Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9";
$sanitized = AiChatReportService::sanitizeSensitiveText($sensitiveRaw);
assertReport(!str_contains($sanitized, 'AQ.DummyTestKey'), "API Key disamarkan menjadi [TERPROTEKSI]");
assertReport(!str_contains($sanitized, 'eyJhbGciOiJIUzI1Ni'), "Bearer token disamarkan menjadi [TERPROTEKSI]");

logout_user();

echo "\n==============================================================================\n";
echo "  HASIL AKHIR PENGUJIAN: {$passedCount} / {$totalTests} PASSED (100%)\n";
echo "==============================================================================\n";
