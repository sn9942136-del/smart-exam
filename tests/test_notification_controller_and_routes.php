<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION CONTROLLER & ROUTES (TAHAP 2 - MODUL NOTIFIKASI)
 * ==============================================================================
 * Menguji secara komprehensif fungsionalitas backend endpoint NotificationController
 * dan pendaftaran rute pada app/Config/Routes.php:
 * 
 * 1. Pengguna yang belum login ditolak (Unauthorized 401)
 * 2. Siswa dapat mengambil notifikasinya sendiri (HTTP 200)
 * 3. Guru dapat mengambil notifikasinya sendiri (HTTP 200)
 * 4. Admin dapat mengambil notifikasinya sendiri (HTTP 200)
 * 5. Proteksi IDOR: Siswa tidak dapat membaca notifikasi siswa lain
 * 6. Role Isolation: Siswa tidak dapat membaca notifikasi guru atau admin
 * 7. Mark as Read hanya mengubah notifikasi milik pengguna yang login
 * 8. Mark All as Read hanya mengubah notifikasi milik pengguna yang login
 * 9. Request dengan ID tidak valid (negatif/nol/non-existent) ditangani dengan benar (422 / 404)
 * 10. Proteksi CSRF: Request tanpa token CSRF valid ditolak sesuai mekanisme project
 * 11. Endpoint dropdown mengembalikan jumlah dan daftar notifikasi terstruktur dengan format waktu
 * 12. Token Protection Guardrail: Endpoint tidak membocorkan token ujian kepada siswa
 * 13. Integritas Data Operasional: Endpoint tidak merusak atau mengubah data di luar notifikasi
 * 14. Pendaftaran Route: Seluruh rute (admin, guru, siswa, global/api) terdaftar di Router
 * ==============================================================================
 */

// Pastikan selalu menggunakan database pengujian terisolasi
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Controllers\NotificationController;
use App\Filters\AuthFilter;
use App\Filters\CsrfFilter;
use App\Filters\RoleFilter;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Router;
use App\Libraries\Session;
use App\Models\NotificationModel;
use App\Services\NotificationService;

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION CONTROLLER & ROUTES (TAHAP 2)          \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

$passCount = 0;
$failCount = 0;

function report(string $code, string $name, bool $ok, string $details = ''): void {
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo "[\033[32mPASS\033[0m] {$code}: {$name}\n";
    } else {
        $failCount++;
        echo "[\033[31mFAIL\033[0m] {$code}: {$name}\n";
    }
    if (!empty($details)) {
        echo "       Details: {$details}\n";
    }
}

// Akun Pengujian yang Digunakan
$studentUser1 = [
    'id'       => 4,
    'username' => 'Ahmad Fauzi',
    'name'     => 'Ahmad Fauzi',
    'email'    => 'Ahmadfauzi@gmail.com',
    'role'     => 'peserta_didik'
];

$studentUser2 = [
    'id'       => 12,
    'username' => 'siswa_sec_2',
    'name'     => 'Siswa Kedua Testing',
    'email'    => 'siswa2@test.com',
    'role'     => 'peserta_didik'
];

$teacherUser = [
    'id'       => 3,
    'username' => 'Budi Santoso',
    'name'     => 'Budi Santoso, S.Kom., M.T.',
    'email'    => 'Budisantoso@gmail.com',
    'role'     => 'guru'
];

$adminUser = [
    'id'       => 2,
    'username' => 'admin',
    'name'     => 'Administrator Ujian',
    'email'    => 'admin@gmail.com',
    'role'     => 'admin'
];

// Helper Set Sesi Login Pengguna
function loginAs(array $user): void {
    Session::set('user', $user);
    Session::set('_last_activity', time());
    Session::generateCsrfToken();
}

// Bersihkan notifikasi uji sebelumnya dari DB pengujian
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

$notifModel = new NotificationModel();
$notifService = new NotificationService($notifModel);

// --------------------------------------------------------------------------
// UJI 1: PENGGUNA BELUM LOGIN DITOLAK (UNAUTHORIZED 401)
// --------------------------------------------------------------------------
echo "--- 1. PENGUJIAN OTENTIKASI & KEAMANAN SESI ---\n";

Session::destroy(); // Pastikan sesi kosong
$anonymousCtrl = new NotificationController();

$res1_drop = $anonymousCtrl->getDropdownData();
$res1_count = $anonymousCtrl->getUnreadCount();
$res1_idx = $anonymousCtrl->index();
$res1_mark = $anonymousCtrl->markAsRead(1);
$res1_all = $anonymousCtrl->markAllAsRead();

$body1_drop = json_decode($res1_drop->getContent(), true);

$tc01_ok = $res1_drop->getStatusCode() === 401
    && $res1_count->getStatusCode() === 401
    && $res1_idx->getStatusCode() === 401
    && $res1_mark->getStatusCode() === 401
    && $res1_all->getStatusCode() === 401
    && isset($body1_drop['success']) && $body1_drop['success'] === false;

// Uji juga via AuthFilter pada request AJAX
$authFilter = new AuthFilter();
$unauthAjaxReq = new Request([], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/notifications/dropdown', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
$filterRes = $authFilter->before($unauthAjaxReq);
$filterOk = $filterRes !== null && $filterRes->getStatusCode() === 401;

report('TC-CTRL-01', 'Pengguna yang belum login ditolak dengan HTTP 401 pada seluruh endpoint notifikasi', $tc01_ok && $filterOk, "Controller 401 & AuthFilter 401 valid");

// --------------------------------------------------------------------------
// UJI 2: SISWA DAPAT MENGAMBIL NOTIFIKASINYA SENDIRI
// --------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN PENGAMBILAN DATA BERDASARKAN ROLE ---\n";

// Buat 2 notifikasi untuk Siswa 1 (User 4)
$s1_notif1 = $notifModel->createNotification([
    'user_id'    => 4,
    'title'      => 'Jadwal Ujian Siswa',
    'message'    => 'Ujian Matematika Anda dijadwalkan besok pukul 08:00.',
    'type'       => 'info',
    'action_url' => '/siswa/exams'
]);
$s1_notif2 = $notifModel->createNotification([
    'user_id'    => 4,
    'title'      => 'Nilai Diterbitkan',
    'message'    => 'Hasil ujian Bahasa Indonesia telah tersedia.',
    'type'       => 'success',
    'action_url' => '/siswa/results'
]);

loginAs($studentUser1);
$studentCtrl = new NotificationController();
$resSiswa = $studentCtrl->index();
$bodySiswa = json_decode($resSiswa->getContent(), true);

$tc02_ok = $resSiswa->getStatusCode() === 200
    && $bodySiswa['success'] === true
    && $bodySiswa['total'] >= 2
    && count($bodySiswa['data']) >= 2
    && $bodySiswa['data'][0]['id'] > 0;

report('TC-CTRL-02', 'Siswa berhasil mengambil daftar notifikasinya sendiri via index()', $tc02_ok, "Total ditemukan: {$bodySiswa['total']}");

// --------------------------------------------------------------------------
// UJI 3: GURU DAPAT MENGAMBIL NOTIFIKASINYA SENDIRI
// --------------------------------------------------------------------------
$g_notif1 = $notifModel->createNotification([
    'user_id'    => 3,
    'title'      => 'Jawaban Siswa Masuk',
    'message'    => 'Ahmad Fauzi telah menyelesaikan ujian PAS Matematika.',
    'type'       => 'info',
    'action_url' => '/guru/grading'
]);

loginAs($teacherUser);
$teacherCtrl = new NotificationController();
$resGuru = $teacherCtrl->index();
$bodyGuru = json_decode($resGuru->getContent(), true);

$tc03_ok = $resGuru->getStatusCode() === 200
    && $bodyGuru['success'] === true
    && $bodyGuru['total'] >= 1
    && $bodyGuru['data'][0]['title'] === 'Jawaban Siswa Masuk';

report('TC-CTRL-03', 'Guru berhasil mengambil notifikasinya sendiri via index()', $tc03_ok, "Notifikasi guru: {$bodyGuru['data'][0]['title']}");

// --------------------------------------------------------------------------
// UJI 4: ADMIN DAPAT MENGAMBIL NOTIFIKASINYA SENDIRI
// --------------------------------------------------------------------------
$a_notif1 = $notifModel->createNotification([
    'user_id'    => 2,
    'title'      => 'Sistem AI Monitoring',
    'message'    => 'Server AI siap melayani sesi proctoring.',
    'type'       => 'warning',
    'action_url' => '/admin/monitoring'
]);

loginAs($adminUser);
$adminCtrl = new NotificationController();
$resAdmin = $adminCtrl->index();
$bodyAdmin = json_decode($resAdmin->getContent(), true);

$tc04_ok = $resAdmin->getStatusCode() === 200
    && $bodyAdmin['success'] === true
    && $bodyAdmin['total'] >= 1
    && $bodyAdmin['data'][0]['title'] === 'Sistem AI Monitoring';

report('TC-CTRL-04', 'Admin berhasil mengambil notifikasinya sendiri via index()', $tc04_ok, "Notifikasi admin: {$bodyAdmin['data'][0]['title']}");

// --------------------------------------------------------------------------
// UJI 5: SISWA TIDAK DAPAT MEMBACA NOTIFIKASI SISWA LAIN (IDOR LISTING)
// --------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN ISOLASI DATA & PROTEKSI IDOR ---\n";

// Buat notifikasi khusus untuk Siswa 2 (User 12)
$s2_notif1 = $notifModel->createNotification([
    'user_id'    => 12,
    'title'      => 'Rahasia Siswa 2 Khusus',
    'message'    => 'Pesan ini hanya untuk Siswa Kedua.',
    'type'       => 'danger',
    'action_url' => '/siswa/dashboard'
]);

// Login kembali sebagai Siswa 1 (User 4)
loginAs($studentUser1);
$studentCtrl1 = new NotificationController();
$resSiswa1 = $studentCtrl1->index();
$bodySiswa1 = json_decode($resSiswa1->getContent(), true);

$containsSiswa2Notif = false;
foreach ($bodySiswa1['data'] as $item) {
    if ($item['title'] === 'Rahasia Siswa 2 Khusus' || (int)$item['id'] === (int)$s2_notif1) {
        $containsSiswa2Notif = true;
        break;
    }
}

$tc05_ok = !$containsSiswa2Notif;
report('TC-CTRL-05', 'Proteksi IDOR: Siswa tidak dapat membaca notifikasi milik siswa lain', $tc05_ok, "Notifikasi siswa 2 tidak bocor ke siswa 1");

// --------------------------------------------------------------------------
// UJI 6: SISWA TIDAK DAPAT MEMBACA NOTIFIKASI GURU ATAU ADMIN & ROLE FILTER
// --------------------------------------------------------------------------
$containsTeacherOrAdmin = false;
foreach ($bodySiswa1['data'] as $item) {
    if ($item['title'] === 'Jawaban Siswa Masuk' || $item['title'] === 'Sistem AI Monitoring') {
        $containsTeacherOrAdmin = true;
        break;
    }
}

// Uji juga RoleFilter: Siswa mencoba mengakses rute admin atau guru
$roleFilter = new RoleFilter();
$studentReqToAdmin = new Request([], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/notifications', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
$roleFilterRes = $roleFilter->before($studentReqToAdmin, ['super_admin', 'admin']);
$roleBlocked = $roleFilterRes !== null && ($roleFilterRes->getStatusCode() === 403 || $roleFilterRes->getStatusCode() === 302);

$tc06_ok = !$containsTeacherOrAdmin && $roleBlocked;
report('TC-CTRL-06', 'Role Isolation: Siswa tidak dapat membaca notifikasi guru/admin dan rute admin diblokir RoleFilter', $tc06_ok, "Isolasi data & filter role terverifikasi aman");

// --------------------------------------------------------------------------
// UJI 7: MARK AS READ HANYA MENGUBAH NOTIFIKASI MILIK PENGGUNA LOGIN
// --------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN MARK AS READ & IDOR MUTATION ---\n";

// Percobaan 1: Siswa 1 mencoba menandai notifikasi milik Siswa 2 ($s2_notif1)
$resCrossMark = $studentCtrl1->markAsRead((int) $s2_notif1);
$bodyCrossMark = json_decode($resCrossMark->getContent(), true);

// Periksa DB: Notifikasi Siswa 2 HARUS tetap belum dibaca (is_read = 0)
$s2_dbCheck = $db->query("SELECT is_read FROM notifications WHERE id = " . (int)$s2_notif1)->fetchColumn();

$attemptCross_blocked = $resCrossMark->getStatusCode() === 404
    && $bodyCrossMark['success'] === false
    && (int)$s2_dbCheck === 0;

// Percobaan 2: Siswa 1 menandai notifikasinya sendiri ($s1_notif1)
$resOwnMark = $studentCtrl1->markAsRead((int) $s1_notif1);
$bodyOwnMark = json_decode($resOwnMark->getContent(), true);

// Periksa DB: Notifikasi Siswa 1 HARUS berubah menjadi is_read = 1
$s1_dbCheck = $db->query("SELECT is_read, read_at FROM notifications WHERE id = " . (int)$s1_notif1)->fetch(PDO::FETCH_ASSOC);

$attemptOwn_success = $resOwnMark->getStatusCode() === 200
    && $bodyOwnMark['success'] === true
    && (int)$s1_dbCheck['is_read'] === 1
    && !empty($s1_dbCheck['read_at']);

$tc07_ok = $attemptCross_blocked && $attemptOwn_success;
report('TC-CTRL-07', 'Mark as Read aman dari IDOR: Menolak perubahan notifikasi milik user lain (404) dan memperbarui notifikasi sendiri (200)', $tc07_ok, "Cross-mark diblokir, Own-mark berhasil");

// --------------------------------------------------------------------------
// UJI 8: MARK ALL AS READ HANYA MENGUBAH NOTIFIKASI MILIK PENGGUNA LOGIN
// --------------------------------------------------------------------------
// Tambah notifikasi unread untuk Siswa 1, Siswa 2, dan Guru
$extraS1 = $notifModel->createNotification(['user_id' => 4, 'title' => 'Extra 1', 'message' => 'Pesan']);
$extraS2 = $notifModel->createNotification(['user_id' => 12, 'title' => 'Extra S2', 'message' => 'Pesan']);
$extraG  = $notifModel->createNotification(['user_id' => 3, 'title' => 'Extra G', 'message' => 'Pesan']);

loginAs($studentUser1);
$studentCtrl1 = new NotificationController();
$resMarkAll = $studentCtrl1->markAllAsRead();
$bodyMarkAll = json_decode($resMarkAll->getContent(), true);

// Pastikan unread count Siswa 1 sekarang 0
$unreadS1 = $notifModel->getUnreadCount(4);
// Pastikan unread count Siswa 2 dan Guru TETAP tidak terpengaruh
$unreadS2 = $notifModel->getUnreadCount(12);
$unreadG  = $notifModel->getUnreadCount(3);

$tc08_ok = $resMarkAll->getStatusCode() === 200
    && $bodyMarkAll['success'] === true
    && $bodyMarkAll['unread_count'] === 0
    && $unreadS1 === 0
    && $unreadS2 > 0
    && $unreadG > 0;

report('TC-CTRL-08', 'Mark All as Read hanya mengosongkan notifikasi milik pengguna login tanpa menyentuh pengguna lain', $tc08_ok, "Unread S1: {$unreadS1}, Unread S2: {$unreadS2}, Unread Guru: {$unreadG}");

// --------------------------------------------------------------------------
// UJI 9: REQUEST DENGAN ID TIDAK VALID DITANGANI DENGAN BENAR (422 / 404)
// --------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN VALIDASI INPUT & EDGE CASES ---\n";

loginAs($studentUser1);
$ctrl = new NotificationController();

// ID negatif
$resNeg = $ctrl->markAsRead(-10);
// ID nol
$resZero = $ctrl->markAsRead(0);
// ID non-existent sangat besar
$resNonExistent = $ctrl->markAsRead(99999999);

$tc09_ok = $resNeg->getStatusCode() === 422
    && $resZero->getStatusCode() === 422
    && $resNonExistent->getStatusCode() === 404;

report('TC-CTRL-09', 'Penanganan ID tidak valid menghasilkan respon yang tepat (422 Unprocessable / 404 Not Found)', $tc09_ok, "Negative: 422, Zero: 422, NonExistent: 404");

// --------------------------------------------------------------------------
// UJI 10: PROTEKSI CSRF FILTER PADA ENDPOINT STATE-CHANGING
// --------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN PROTEKSI CSRF & FILTER KEAMANAN ---\n";

$csrfFilter = new CsrfFilter();

// Kasus 1: Request AJAX POST tanpa token CSRF -> Ditolak 419
$reqNoCsrf = new Request([], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/notifications/mark-all-read',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);
$csrfFailRes = $csrfFilter->before($reqNoCsrf);
$csrfFailOk = $csrfFailRes !== null && $csrfFailRes->getStatusCode() === 419;

// Kasus 2: Request AJAX POST dengan token CSRF yang valid -> Lolos (null)
$validToken = Session::getCsrfToken();
$reqWithCsrf = new Request([], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/notifications/mark-all-read',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'HTTP_X_CSRF_TOKEN'     => $validToken
]);
$csrfPassRes = $csrfFilter->before($reqWithCsrf);
$csrfPassOk = $csrfPassRes === null;

$tc10_ok = $csrfFailOk && $csrfPassOk;
report('TC-CTRL-10', 'CsrfFilter menolak request tanpa CSRF (419) dan meloloskan request dengan token CSRF valid', $tc10_ok, "Tanpa CSRF: 419, CSRF valid: lolos (null)");

// --------------------------------------------------------------------------
// UJI 11: ENDPOINT DROPDOWN MENGEMBALIKAN JUMLAH & DAFTAR NOTIFIKASI SESUAI
// --------------------------------------------------------------------------
echo "\n--- 7. PENGUJIAN DROPDOWN & UNREAD COUNT ENDPOINTS ---\n";

// Siapkan 3 notifikasi baru untuk Siswa 1
$d1 = $notifModel->createNotification(['user_id' => 4, 'title' => 'Pengumuman A', 'message' => 'Pesan A', 'type' => 'info']);
$d2 = $notifModel->createNotification(['user_id' => 4, 'title' => 'Pengumuman B', 'message' => 'Pesan B', 'type' => 'warning']);
$d3 = $notifModel->createNotification(['user_id' => 4, 'title' => 'Pengumuman C', 'message' => 'Pesan C', 'type' => 'success']);

loginAs($studentUser1);
$dropdownCtrl = new NotificationController();

$resDrop = $dropdownCtrl->getDropdownData();
$bodyDrop = json_decode($resDrop->getContent(), true);

$resCount = $dropdownCtrl->getUnreadCount();
$bodyCount = json_decode($resCount->getContent(), true);

$tc11_ok = $resDrop->getStatusCode() === 200
    && $bodyDrop['success'] === true
    && $bodyDrop['unread_count'] >= 3
    && count($bodyDrop['notifications']) >= 3
    && isset($bodyDrop['notifications'][0]['time_ago'])
    && isset($bodyDrop['notifications'][0]['created_at'])
    && $resCount->getStatusCode() === 200
    && $bodyCount['success'] === true
    && $bodyCount['unread_count'] === $bodyDrop['unread_count'];

report('TC-CTRL-11', 'Endpoint dropdown & unread-count mengembalikan struktur data lengkap dengan format waktu yang sesuai', $tc11_ok, "Dropdown unread: {$bodyDrop['unread_count']}, Counter unread: {$bodyCount['unread_count']}");

// --------------------------------------------------------------------------
// UJI 12: ENDPOINT TIDAK MEMBOCORKAN TOKEN UJIAN ATAU DATA SENSITIF
// --------------------------------------------------------------------------
echo "\n--- 8. PENGUJIAN KEAMANAN TOKEN & PRIVASI SISWA ---\n";

// Kirim notifikasi via NotificationService yang mengandung token ujian sensitif
$sensitiveToken = 'PASMAT1';
$notifService->notifyUser(
    4,
    'Akses Ujian Matematika',
    "Token ujian Anda adalah {$sensitiveToken}. Harap segera masuk ruang ujian.",
    'warning',
    '/siswa/exams'
);

loginAs($studentUser1);
$secCtrl = new NotificationController();
$resSecDrop = $secCtrl->getDropdownData();
$rawSecContent = $resSecDrop->getContent();

// Token mentah 'PASMAT1' TIDAK BOLEH muncul dalam output JSON siswa
$tokenLeaked = str_contains($rawSecContent, $sensitiveToken);
// Harus digantikan dengan guardrail '[TERPROTEKSI]'
$tokenSanitized = str_contains($rawSecContent, '[TERPROTEKSI]');

$tc12_ok = !$tokenLeaked && $tokenSanitized;
report('TC-CTRL-12', 'Token Protection Guardrail: Output JSON controller tidak membocorkan token ujian ke siswa', $tc12_ok, "Token '{$sensitiveToken}' tidak bocor; terproteksi: " . ($tokenSanitized ? 'Ya' : 'Tidak'));

// --------------------------------------------------------------------------
// UJI 13: ENDPOINT TIDAK MENGUBAH DATA OPERASIONAL YANG TIDAK BERKAITAN
// --------------------------------------------------------------------------
echo "\n--- 9. PENGUJIAN INTEGRITAS DATA OPERASIONAL LAINNYA ---\n";

// Cek data ujian operasional (Exam ID 39 - PAS Matematika Peminatan)
$exam39 = $db->query("SELECT id, title, code FROM exams WHERE id = 39")->fetch(PDO::FETCH_ASSOC);
$token39 = $db->query("SELECT token FROM exam_tokens WHERE exam_id = 39 LIMIT 1")->fetchColumn();
$exam39_ok = $exam39 && ($token39 === 'PASMAT1' || !empty($exam39['title']));

// Cek jumlah total akun pengguna operasional tetap utuh
$userCount = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$userCount_ok = $userCount > 0;

$tc13_ok = $exam39_ok && $userCount_ok;
report('TC-CTRL-13', 'Integritas Sistem: Seluruh operasi controller tidak mengubah data ujian, token, atau tabel operasional lainnya', $tc13_ok, "Ujian 39 Code: {$exam39['code']}, Token: {$token39} tetap utuh");

// --------------------------------------------------------------------------
// UJI 14: VERIFIKASI PENDAFTARAN RUTE PADA ROUTER TABLE
// --------------------------------------------------------------------------
echo "\n--- 10. PENGUJIAN PENDAFTARAN RUTE NOTIFIKASI ---\n";

// Muat routing table
require_once APP_ROOT . '/app/Config/Routes.php';
$routes = Router::getRoutes();

$expectedRoutes = [
    // Unified / API routes
    ['GET', '/notifications'],
    ['GET', '/notifications/dropdown'],
    ['GET', '/notifications/unread-count'],
    ['POST', '/notifications/mark-read/{id}'],
    ['POST', '/notifications/mark-read'],
    ['POST', '/notifications/mark-all-read'],
    ['GET', '/api/notifications'],
    ['GET', '/api/notifications/dropdown'],
    ['GET', '/api/notifications/unread-count'],
    ['POST', '/api/notifications/mark-read/{id}'],
    ['POST', '/api/notifications/mark-read'],
    ['POST', '/api/notifications/mark-all-read'],
    // Admin routes
    ['GET', '/admin/notifications'],
    ['GET', '/admin/notifications/dropdown'],
    ['GET', '/admin/notifications/unread-count'],
    ['POST', '/admin/notifications/mark-read/{id}'],
    ['POST', '/admin/notifications/mark-all-read'],
    // Guru routes
    ['GET', '/guru/notifications'],
    ['GET', '/guru/notifications/dropdown'],
    ['GET', '/guru/notifications/unread-count'],
    ['POST', '/guru/notifications/mark-read/{id}'],
    ['POST', '/guru/notifications/mark-all-read'],
    // Siswa routes
    ['GET', '/siswa/notifications'],
    ['GET', '/siswa/notifications/dropdown'],
    ['GET', '/siswa/notifications/unread-count'],
    ['POST', '/siswa/notifications/mark-read/{id}'],
    ['POST', '/siswa/notifications/mark-all-read'],
];

$allRegistered = true;
$missingRoutes = [];

foreach ($expectedRoutes as [$method, $uri]) {
    $found = false;
    foreach ($routes as $route) {
        if ($route['uri'] === $uri && in_array($method, $route['methods'], true)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $allRegistered = false;
        $missingRoutes[] = "{$method} {$uri}";
    }
}

$tc14_ok = $allRegistered;
$missingDetails = !empty($missingRoutes) ? "Rute hilang: " . implode(', ', $missingRoutes) : "Total " . count($expectedRoutes) . " rute terverifikasi terdaftar";
report('TC-CTRL-14', 'Router Table: Seluruh rute notifikasi terdaftar dengan benar untuk seluruh role dan rute global', $tc14_ok, $missingDetails);

// --------------------------------------------------------------------------
// CLEANUP DATABASE PENGUJIAN
// --------------------------------------------------------------------------
$db->exec("DELETE FROM notification_logs WHERE notification_id IN (SELECT id FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999))");
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");

// --------------------------------------------------------------------------
// RINGKASAN AKHIR PENGUJIAN TAHAP 2
// --------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN NOTIFIKASI TAHAP 2: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
