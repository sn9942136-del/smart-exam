<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION NAVBAR INTEGRATION (TAHAP 3A)
 * ==============================================================================
 * Menguji integrasi badge unread count dan dropdown notifikasi pada navbar:
 * 1. Navbar siswa menampilkan unread count yang akurat
 * 2. Navbar guru menampilkan unread count yang akurat
 * 3. Navbar admin menampilkan unread count yang akurat
 * 4. Dropdown mengembalikan maksimal 5 notifikasi terbaru
 * 5. Dropdown menampilkan empty state saat tidak ada notifikasi
 * 6. Badge unread disembunyikan (d-none) saat unread count = 0
 * 7. Badge dan status notifikasi diperbarui setelah markAsRead berhasil
 * 8. Mark all as read memperbarui badge unread count menjadi 0
 * 9. Request gagal tidak merusak integritas badge count
 * 10. Akses endpoint notifikasi tanpa login ditolak (HTTP 401)
 * 11. Request mutasi tanpa CSRF token ditolak (HTTP 419)
 * 12. Proteksi IDOR: Pengguna tidak dapat membaca notifikasi pengguna lain
 * 13. Proteksi XSS: Title dan message tidak mengeksekusi script/HTML (Safe TextContent)
 * 14. Token Protection Guardrail: Token ujian tidak bocor pada tampilan siswa
 * 15. Validasi sintaks dan keamanan JavaScript NotificationNav
 * 16. Log sistem bebas dari error PHP baru
 * 17. Integritas komponen navbar pada seluruh layout role (Siswa, Guru, Admin)
 * 18. Kepatuhan desain responsif (Bootstrap 5, mobile friendly, CSS boundary)
 * ==============================================================================
 */

// Konfigurasi database pengujian terisolasi
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Controllers\NotificationController;
use App\Filters\AuthFilter;
use App\Filters\CsrfFilter;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Models\NotificationModel;
use App\Services\NotificationService;

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION NAVBAR INTEGRATION (TAHAP 3A)          \n";
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

// Akun Pengujian
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

function loginAs(array $user): void {
    Session::set('user', $user);
    Session::set('_last_activity', time());
    Session::generateCsrfToken();
}

// Bersihkan notifikasi pengujian sebelumnya dari database test
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

$notifModel = new NotificationModel();
$notifService = new NotificationService($notifModel);

// --------------------------------------------------------------------------
// UJI 1: NAVBAR SISWA MENAMPILKAN UNREAD COUNT
// --------------------------------------------------------------------------
echo "--- 1. PENGUJIAN UNREAD COUNT PER ROLE ---\n";

// Buat 3 notifikasi unread untuk Siswa (User ID: 4)
$notifModel->createNotification(['user_id' => 4, 'title' => 'Tugas Matematika', 'message' => 'Segera kerjakan latihan', 'type' => 'info']);
$notifModel->createNotification(['user_id' => 4, 'title' => 'Jadwal PAS', 'message' => 'Jadwal PAS telah dirilis', 'type' => 'warning']);
$notifModel->createNotification(['user_id' => 4, 'title' => 'Verifikasi Kamera', 'message' => 'Kalibrasi webcam berhasil', 'type' => 'success']);

loginAs($studentUser1);
$ctrl = new NotificationController();
$resCountSiswa = $ctrl->getUnreadCount();
$bodyCountSiswa = json_decode($resCountSiswa->getContent(), true);

$tc01_ok = $resCountSiswa->getStatusCode() === 200
    && $bodyCountSiswa['success'] === true
    && $bodyCountSiswa['unread_count'] === 3;

report('TC-NAV-01', 'Navbar Siswa mengambil unread count (3 notifikasi belum dibaca)', $tc01_ok, "Unread count siswa: {$bodyCountSiswa['unread_count']}");

// --------------------------------------------------------------------------
// UJI 2: NAVBAR GURU MENAMPILKAN UNREAD COUNT
// --------------------------------------------------------------------------
// Buat 2 notifikasi unread untuk Guru (User ID: 3)
$notifModel->createNotification(['user_id' => 3, 'title' => 'Jawaban Masuk', 'message' => 'Siswa menyelesaikan ujian', 'type' => 'info']);
$notifModel->createNotification(['user_id' => 3, 'title' => 'Penilaian Esai', 'message' => '5 lembar jawaban menunggu grading', 'type' => 'warning']);

loginAs($teacherUser);
$resCountGuru = $ctrl->getUnreadCount();
$bodyCountGuru = json_decode($resCountGuru->getContent(), true);

$tc02_ok = $resCountGuru->getStatusCode() === 200
    && $bodyCountGuru['success'] === true
    && $bodyCountGuru['unread_count'] === 2;

report('TC-NAV-02', 'Navbar Guru mengambil unread count (2 notifikasi belum dibaca)', $tc02_ok, "Unread count guru: {$bodyCountGuru['unread_count']}");

// --------------------------------------------------------------------------
// UJI 3: NAVBAR ADMIN MENAMPILKAN UNREAD COUNT
// --------------------------------------------------------------------------
// Buat 1 notifikasi unread untuk Admin (User ID: 2)
$notifModel->createNotification(['user_id' => 2, 'title' => 'Peringatan Sistem', 'message' => 'Penggunaan penyimpanan mencapai 80%', 'type' => 'danger']);

loginAs($adminUser);
$resCountAdmin = $ctrl->getUnreadCount();
$bodyCountAdmin = json_decode($resCountAdmin->getContent(), true);

$tc03_ok = $resCountAdmin->getStatusCode() === 200
    && $bodyCountAdmin['success'] === true
    && $bodyCountAdmin['unread_count'] === 1;

report('TC-NAV-03', 'Navbar Admin mengambil unread count (1 notifikasi belum dibaca)', $tc03_ok, "Unread count admin: {$bodyCountAdmin['unread_count']}");

// --------------------------------------------------------------------------
// UJI 4: DROPDOWN MENAMPILKAN MAKSIMAL 5 NOTIFIKASI
// --------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN BATASAN DATA & EMPTY STATE DROPDOWN ---\n";

// Tambah lagi 4 notifikasi untuk siswa (total jadi 7 notifikasi)
for ($i = 1; $i <= 4; $i++) {
    $notifModel->createNotification(['user_id' => 4, 'title' => "Pengumuman Tambahan {$i}", 'message' => "Detail pengumuman ke-{$i}"]);
}

loginAs($studentUser1);
$reqLimit = new Request(['limit' => '5'], ['REQUEST_METHOD' => 'GET'], [], ['limit' => '5']);
$ctrlWithReq = new NotificationController($reqLimit);
$resDrop = $ctrlWithReq->getDropdownData();
$bodyDrop = json_decode($resDrop->getContent(), true);

$tc04_ok = $resDrop->getStatusCode() === 200
    && $bodyDrop['success'] === true
    && count($bodyDrop['notifications']) === 5
    && $bodyDrop['unread_count'] === 7;

report('TC-NAV-04', 'Dropdown membatasi tampilan maksimal 5 notifikasi terbaru saat total notifikasi > 5', $tc04_ok, "Jumlah data dropdown: " . count($bodyDrop['notifications']) . ", Total unread di DB: {$bodyDrop['unread_count']}");

// --------------------------------------------------------------------------
// UJI 5: DROPDOWN MENAMPILKAN EMPTY STATE KETIKA TIDAK ADA NOTIFIKASI
// --------------------------------------------------------------------------
// Siswa 2 (User ID: 12) memiliki 0 notifikasi
loginAs($studentUser2);
$resDropEmpty = $ctrl->getDropdownData();
$bodyDropEmpty = json_decode($resDropEmpty->getContent(), true);

$tc05_ok = $resDropEmpty->getStatusCode() === 200
    && $bodyDropEmpty['success'] === true
    && count($bodyDropEmpty['notifications']) === 0
    && $bodyDropEmpty['unread_count'] === 0;

report('TC-NAV-05', 'Dropdown mengembalikan array kosong untuk memicu empty state UI saat tidak ada notifikasi', $tc05_ok, "Count: " . count($bodyDropEmpty['notifications']) . ", Unread: {$bodyDropEmpty['unread_count']}");

// --------------------------------------------------------------------------
// UJI 6: BADGE HILANG ATAU BERNILAI 0 JIKA TIDAK ADA UNREAD
// --------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN LOGIKA BADGE & MUTASI BACA ---\n";

$resCountEmpty = $ctrl->getUnreadCount();
$bodyCountEmpty = json_decode($resCountEmpty->getContent(), true);

// Periksa kode navbar.php: logika updateBadge() menyembunyikan badge jika unreadCount <= 0 (d-none)
$navbarFile = file_get_contents(__DIR__ . '/../app/Views/components/navbar.php');
$hasDNoneLogic = str_contains($navbarFile, "badgeEl.classList.add('d-none')")
    && str_contains($navbarFile, "badgeEl.classList.remove('d-none')")
    && str_contains($navbarFile, "notifBadge");

$tc06_ok = $bodyCountEmpty['unread_count'] === 0 && $hasDNoneLogic;
report('TC-NAV-06', 'Logika badge otomatis menambahkan class d-none ketika unread count bernilai 0', $tc06_ok, "Unread count: 0 & d-none logic terverifikasi di navbar.php");

// --------------------------------------------------------------------------
// UJI 7: BADGE BERUBAH SETELAH NOTIFIKASI BERHASIL DITANDAI DIBACA
// --------------------------------------------------------------------------
loginAs($studentUser1);
$initialUnread = $notifModel->getUnreadCount(4); // 7

// Ambil salah satu notifikasi unread
$latestNotif = $notifModel->getLatestForDropdown(4, 1);
$targetId = (int) $latestNotif[0]['id'];

$resMarkOne = $ctrl->markAsRead($targetId);
$bodyMarkOne = json_decode($resMarkOne->getContent(), true);

$afterUnread = $notifModel->getUnreadCount(4);

$tc07_ok = $resMarkOne->getStatusCode() === 200
    && $bodyMarkOne['success'] === true
    && $bodyMarkOne['unread_count'] === ($initialUnread - 1)
    && $afterUnread === ($initialUnread - 1);

report('TC-NAV-07', 'Badge unread count berkurang secara tepat setelah notifikasi spesifik ditandai dibaca', $tc07_ok, "Awal: {$initialUnread} -> Sesudah: {$afterUnread}");

// --------------------------------------------------------------------------
// UJI 8: MARK ALL READ MEMPERBARUI BADGE MENJADI 0
// --------------------------------------------------------------------------
$resMarkAll = $ctrl->markAllAsRead();
$bodyMarkAll = json_decode($resMarkAll->getContent(), true);
$finalUnread = $notifModel->getUnreadCount(4);

$tc08_ok = $resMarkAll->getStatusCode() === 200
    && $bodyMarkAll['success'] === true
    && $bodyMarkAll['unread_count'] === 0
    && $finalUnread === 0;

report('TC-NAV-08', 'Mark all read mereset seluruh notifikasi siswa dan mengembalikan unread_count = 0', $tc08_ok, "Affected: {$bodyMarkAll['affected_count']}, Unread sekarang: {$finalUnread}");

// --------------------------------------------------------------------------
// UJI 9: REQUEST GAGAL TIDAK MENYEBABKAN BADGE MENJADI SALAH
// --------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN KEGAGALAN REQUEST & KEAMANAN OTENTIKASI ---\n";

// Coba mark as read ID negatif atau ID milik orang lain
$unreadBeforeError = $notifModel->getUnreadCount(4);
$resBadMark = $ctrl->markAsRead(-999);

$unreadAfterError = $notifModel->getUnreadCount(4);

$tc09_ok = $resBadMark->getStatusCode() === 422
    && $unreadBeforeError === $unreadAfterError;

report('TC-NAV-09', 'Request dengan ID tidak valid ditolak (422) tanpa mengubah nilai badge di sistem', $tc09_ok, "Unread sebelum: {$unreadBeforeError}, sesudah: {$unreadAfterError}");

// --------------------------------------------------------------------------
// UJI 10: REQUEST TANPA LOGIN DITOLAK
// --------------------------------------------------------------------------
Session::destroy();
$anonCtrl = new NotificationController();

$resAnonDrop = $anonCtrl->getDropdownData();
$resAnonCount = $anonCtrl->getUnreadCount();
$resAnonMark = $anonCtrl->markAsRead(1);

$tc10_ok = $resAnonDrop->getStatusCode() === 401
    && $resAnonCount->getStatusCode() === 401
    && $resAnonMark->getStatusCode() === 401;

report('TC-NAV-10', 'Pengguna yang belum login ditolak dengan HTTP 401 pada seluruh endpoint navbar notifikasi', $tc10_ok, "Status code: 401 Unauthorized");

// --------------------------------------------------------------------------
// UJI 11: REQUEST MUTASI TANPA CSRF DITOLAK
// --------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN PROTEKSI CSRF & ISOLASI DATA ---\n";

$csrfFilter = new CsrfFilter();
$reqPostNoCsrf = new Request([], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/notifications/mark-read/1',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);
$csrfRes = $csrfFilter->before($reqPostNoCsrf);

$tc11_ok = $csrfRes !== null && $csrfRes->getStatusCode() === 419;
report('TC-NAV-11', 'Request mutasi (POST) tanpa CSRF token ditolak oleh CsrfFilter dengan HTTP 419', $tc11_ok, "Status code: 419 Authentication Timeout");

// --------------------------------------------------------------------------
// UJI 12: NOTIFIKASI PENGGUNA LAIN TIDAK DAPAT DIAKSES (IDOR)
// --------------------------------------------------------------------------
// Buat notifikasi rahasia untuk Admin
$adminNotifId = $notifModel->createNotification([
    'user_id' => 2,
    'title'   => 'Laporan Audit Rahasia',
    'message' => 'Hanya untuk Super Admin & Admin'
]);

// Siswa 1 mencoba mark as read notifikasi admin
loginAs($studentUser1);
$studentCtrl = new NotificationController();
$resCrossMark = $studentCtrl->markAsRead((int)$adminNotifId);

// Pastikan di database notifikasi admin belum terbaca
$adminNotifDb = $db->query("SELECT is_read FROM notifications WHERE id = {$adminNotifId}")->fetchColumn();

$tc12_ok = $resCrossMark->getStatusCode() === 404
    && (int)$adminNotifDb === 0;

report('TC-NAV-12', 'Proteksi IDOR: Siswa ditolak (404) saat mencoba memanipulasi notifikasi milik Admin', $tc12_ok, "Status DB notifikasi admin tetap is_read = 0");

// --------------------------------------------------------------------------
// UJI 13: TITLE/MESSAGE TIDAK MENGEKSEKUSI HTML/JAVASCRIPT (SAFE TEXTCONTENT)
// --------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN ANTI-XSS & KEAMANAN TOKEN ---\n";

// Injeksi payload XSS ke notifikasi
$xssTitle = '<script>alert("xss")</script>';
$xssMessage = '<img src=x onerror=alert(1)> Pesan Normal';
$xssNotifId = $notifModel->createNotification([
    'user_id' => 4,
    'title'   => $xssTitle,
    'message' => $xssMessage
]);

$resXssDrop = $studentCtrl->getDropdownData();
$bodyXssDrop = json_decode($resXssDrop->getContent(), true);

// Periksa controller output: di-escape dengan e()
$foundItem = null;
foreach ($bodyXssDrop['notifications'] as $n) {
    if ((int)$n['id'] === (int)$xssNotifId) {
        $foundItem = $n;
        break;
    }
}

// Periksa navbar.php: JavaScript menggunakan textContent, BUKAN innerHTML untuk title dan message
$hasTextContentTitle = str_contains($navbarFile, 'titleEl.textContent = item.title');
$hasTextContentMsg   = str_contains($navbarFile, 'msgEl.textContent = item.message');

$tc13_ok = $foundItem !== null
    && !str_contains($foundItem['title'], '<script>') // sudah di-escape e()
    && $hasTextContentTitle
    && $hasTextContentMsg;

report('TC-NAV-13', 'Proteksi XSS: Output di-escape pada Controller dan dirender dengan textContent aman di DOM navbar', $tc13_ok, "Render DOM menggunakan textContent (Anti-XSS)");

// --------------------------------------------------------------------------
// UJI 14: TOKEN UJIAN TIDAK BOCOR PADA TAMPILAN SISWA
// --------------------------------------------------------------------------
$sensitiveToken = 'PASMAT1';
$notifService->notifyUser(
    4,
    'Pengumuman Ujian',
    "Token ujian Anda: {$sensitiveToken}. Silakan login.",
    'warning'
);

$resSecDrop = $studentCtrl->getDropdownData();
$rawSecOutput = $resSecDrop->getContent();

$tc14_ok = !str_contains($rawSecOutput, $sensitiveToken)
    && str_contains($rawSecOutput, '[TERPROTEKSI]');

report('TC-NAV-14', 'Token Protection Guardrail: Token ujian tidak bocor dan disensor otomatis pada tampilan siswa', $tc14_ok, "Token '{$sensitiveToken}' berhasil disensor menjadi [TERPROTEKSI]");

// --------------------------------------------------------------------------
// UJI 15: VALIDASI SINTAKS & KEAMANAN JAVASCRIPT NOTIFICATIONNAV
// --------------------------------------------------------------------------
echo "\n--- 7. PENGUJIAN INTEGRITAS JAVASCRIPT & KONSISTENSI NAVBAR ---\n";

// Ekstraksi blok JavaScript dari navbar.php
preg_match('/<script>(.*?)<\/script>/s', $navbarFile, $scriptMatch);
$jsContent = $scriptMatch[1] ?? '';

// Periksa fitur keamanan wajib di dalam JavaScript
$hasSanitizeActionUrl = str_contains($jsContent, 'sanitizeActionUrl');
$hasCsrfTokenHeader   = str_contains($jsContent, "'X-CSRF-TOKEN': csrfToken");
$hasNoInnerHtmlForMsg = !str_contains($jsContent, 'innerHTML = item.message');
$hasBadgeCountLimit   = str_contains($jsContent, "currentUnread > 99 ? '99+'");

$tc15_ok = !empty($jsContent)
    && $hasSanitizeActionUrl
    && $hasCsrfTokenHeader
    && $hasNoInnerHtmlForMsg
    && $hasBadgeCountLimit;

report('TC-NAV-15', 'Validasi modul JavaScript: Mengandung sanitasi URL, CSRF header, anti-XSS, dan batas tampilan 99+', $tc15_ok, "Modul NotificationNav lolos validasi keamanan");

// --------------------------------------------------------------------------
// UJI 16: LOG SISTEM BEBAS DARI ERROR PHP BARU
// --------------------------------------------------------------------------
$logFile = __DIR__ . '/../storage/logs/app-' . date('Y-m-d') . '.log';
$logOk = true;
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    // Pastikan tidak ada fatal error pada baris terakhir
    $recentLog = substr($logContent, -2000);
    if (str_contains($recentLog, 'PHP Fatal error') || str_contains($recentLog, 'PHP Parse error')) {
        $logOk = false;
    }
}

$tc16_ok = $logOk;
report('TC-NAV-16', 'Pemeriksaan storage log: Tidak ada fatal error atau parse error PHP baru', $tc16_ok, "Log aplikasi bersih dari fatal error");

// --------------------------------------------------------------------------
// UJI 17: NAVBAR TETAP BERFUNGSI PADA SELURUH ROLE (SISWA, GURU, ADMIN)
// --------------------------------------------------------------------------
// Uji rendering view komponen navbar untuk masing-masing role
$roles = [
    'peserta_didik' => $studentUser1,
    'guru'          => $teacherUser,
    'admin'         => $adminUser
];

$allRolesRenderOk = true;
foreach ($roles as $roleKey => $roleUser) {
    loginAs($roleUser);
    ob_start();
    include __DIR__ . '/../app/Views/components/navbar.php';
    $renderedHtml = ob_get_clean();

    if (!str_contains($renderedHtml, 'notificationDropdownWrapper')
        || !str_contains($renderedHtml, 'notifBadge')
        || !str_contains($renderedHtml, 'notifDropdownMenu')
        || !str_contains($renderedHtml, 'NotificationNav')) {
        $allRolesRenderOk = false;
        break;
    }
}

$tc17_ok = $allRolesRenderOk;
report('TC-NAV-17', 'Navbar terintegrasi dan merender elemen notifikasi sempurna untuk seluruh role (Siswa, Guru, Admin)', $tc17_ok, "Siswa, Guru, Admin terverifikasi");

// --------------------------------------------------------------------------
// UJI 18: TAMPILAN RESPONSIVE TETAP BAIK
// --------------------------------------------------------------------------
// Periksa keberadaan atribut responsif Bootstrap
$hasResponsiveWidth   = str_contains($navbarFile, 'max-width: 90vw');
$hasResponsiveScroll  = str_contains($navbarFile, 'max-height: 340px; overflow-y: auto;');
$hasLineClamp         = str_contains($navbarFile, 'notif-line-clamp');

$tc18_ok = $hasResponsiveWidth && $hasResponsiveScroll && $hasLineClamp;
report('TC-NAV-18', 'Kepatuhan UI Responsif: Dropdown mendukung viewport mobile (max-width 90vw) & scrollbar adaptif', $tc18_ok, "Viewport mobile & desktop terproteksi");

// --------------------------------------------------------------------------
// UJI 19-24: PENGUJIAN NAVIGASI DROPDOWN MENU MENUJU INBOX NOTIFIKASI
// --------------------------------------------------------------------------
echo "\n--- 8. PENGUJIAN NAVIGASI DROPDOWN MENU INBOX ---\n";

// Refresh $navbarFile
$navbarFile = file_get_contents(__DIR__ . '/../app/Views/components/navbar.php');

// TC-NAV-19: Tombol "Lihat Semua Notifikasi" tersedia dan memiliki ID yang jelas
$hasBtnText = str_contains($navbarFile, 'Lihat Semua Notifikasi');
$hasBtnId   = str_contains($navbarFile, 'id="viewAllNotifBtn"');
$hasFooter  = str_contains($navbarFile, 'id="notifDropdownFooter"');

$tc19_ok = $hasBtnText && $hasBtnId && $hasFooter;
report('TC-NAV-19', 'Tombol "Lihat Semua Notifikasi" tersedia di footer dropdown dengan ID "viewAllNotifBtn"', $tc19_ok, "Elemen tombol dan container footer terverifikasi");

// TC-NAV-20: Tombol tetap tersedia ketika unread = 0 dan total notifikasi = 0 (empty state)
loginAs($studentUser2); // user 12: 0 notifikasi
ob_start();
include __DIR__ . '/../app/Views/components/navbar.php';
$htmlEmptyUser = ob_get_clean();

$tc20_ok = str_contains($htmlEmptyUser, 'id="viewAllNotifBtn"')
    && str_contains($htmlEmptyUser, 'Lihat Semua Notifikasi')
    && str_contains($htmlEmptyUser, 'id="notifEmptyState"');
report('TC-NAV-20', 'Tombol "Lihat Semua Notifikasi" tetap tersedia saat unread = 0 dan total notifikasi = 0 (empty state)', $tc20_ok, "Tombol tetap render saat empty state");

// TC-NAV-21: Link mengarah ke URL inbox yang benar berbasis Base URL tanpa suffix /public
$rawBase = base_url();
$cleanBase = preg_replace('#/public/?$#i', '', $rawBase);
$expectedInboxUrl = rtrim($cleanBase, '/') . '/notifications';

$tc21_ok = str_contains($htmlEmptyUser, 'href="' . $expectedInboxUrl . '"')
    && !str_contains($expectedInboxUrl, '/public/notifications')
    && str_ends_with($expectedInboxUrl, '/notifications');
report('TC-NAV-21', 'Link tombol "Lihat Semua Notifikasi" mengarah ke URL inbox yang valid tanpa suffix /public', $tc21_ok, "URL Target: {$expectedInboxUrl}");

// TC-NAV-22: Tombol tetap tersedia ketika notifikasi tersedia (populated list)
loginAs($studentUser1); // user 4: memiliki notifikasi
ob_start();
include __DIR__ . '/../app/Views/components/navbar.php';
$htmlPopulatedUser = ob_get_clean();

$tc22_ok = str_contains($htmlPopulatedUser, 'id="viewAllNotifBtn"')
    && str_contains($htmlPopulatedUser, 'Lihat Semua Notifikasi')
    && str_contains($htmlPopulatedUser, 'id="notifItemsList"');
report('TC-NAV-22', 'Dropdown menampilkan daftar notifikasi sekaligus mempertahankan tombol "Lihat Semua Notifikasi"', $tc22_ok, "Daftar notifikasi & footer tombol berdampingan");

// TC-NAV-23: Tombol "Lihat Semua Notifikasi" ter-render konsisten untuk seluruh role (Admin, Guru, Siswa)
$allRolesHaveInboxBtn = true;
foreach ($roles as $roleKey => $roleUser) {
    loginAs($roleUser);
    ob_start();
    include __DIR__ . '/../app/Views/components/navbar.php';
    $renderedRoleHtml = ob_get_clean();

    if (!str_contains($renderedRoleHtml, 'id="viewAllNotifBtn"')
        || !str_contains($renderedRoleHtml, 'Lihat Semua Notifikasi')
        || !str_contains($renderedRoleHtml, $expectedInboxUrl)) {
        $allRolesHaveInboxBtn = false;
        break;
    }
}
$tc23_ok = $allRolesHaveInboxBtn;
report('TC-NAV-23', 'Tombol "Lihat Semua Notifikasi" ter-render konsisten dan valid untuk seluruh role (Admin, Guru, Siswa)', $tc23_ok, "Seluruh role memiliki link navigasi inbox yang valid");

// TC-NAV-24: Verifikasi tabel rute tidak memiliki route inbox duplikat
$routesContent = file_get_contents(__DIR__ . '/../app/Config/Routes.php');
$duplicateGlobalRoutes = substr_count($routesContent, "\$routes->get('/notifications'");
$tc24_ok = $duplicateGlobalRoutes <= 1;
report('TC-NAV-24', 'Tidak ada route inbox duplikat yang terdaftar di konfigurasi rute', $tc24_ok, "Pemeriksaan rute duplikat lolos");

// --------------------------------------------------------------------------
// CLEANUP DATABASE PENGUJIAN
// --------------------------------------------------------------------------
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN NAVBAR TAHAP 3A: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
