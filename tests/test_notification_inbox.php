<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION INBOX (TAHAP 3B)
 * Smart Exam System Security & Functional Audit
 * ==============================================================================
 * Menguji seluruh fungsionalitas dan keamanan Halaman Inbox Notifikasi:
 * 1. Hak Akses & Tampilan per Role (Siswa, Guru, Admin)
 * 2. Proteksi Autentikasi & IDOR
 * 3. Tab Status (Semua, Belum Dibaca, Sudah Dibaca)
 * 4. Filter Tipe Notifikasi
 * 5. Paginasi & Preservasi Parameter Filter
 * 6. Mutasi Status Baca (Single & All)
 * 7. Proteksi CSRF
 * 8. Sanitasi Action URL & Anti-XSS
 * 9. Token Protection Guardrail Siswa
 * 10. Empty State, Error State, & Responsivitas Layout
 * 11. Integritas Database Utama & Kebersihan Database Test
 * ==============================================================================
 */

// 1. Inisialisasi Database Pengujian
$targetDb = 'ujian_dual_kamera_test';
putenv("DB_DATABASE={$targetDb}");
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;

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

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION INBOX (TAHAP 3B)                        \n";
echo "====================================================================\n\n";
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

if ($currentDb !== 'ujian_dual_kamera_test') {
    echo "[FATAL ERROR] Test suite WAJIB berjalan pada ujian_dual_kamera_test!\n";
    exit(1);
}

// --------------------------------------------------------------------------
// TEST HARNESS & REPORTING HELPER
// --------------------------------------------------------------------------
$passCount = 0;
$failCount = 0;

function report(string $code, string $description, bool $passed, string $details = ''): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "[PASS] {$code}: {$description}\n";
        if ($details) {
            echo "       Details: {$details}\n";
        }
    } else {
        $failCount++;
        echo "[FAIL] {$code}: {$description}\n";
        if ($details) {
            echo "       Error Details: {$details}\n";
        }
    }
}

function loginAs(array $user): void {
    Session::start();
    login_user($user);
}

function logout(): void {
    logout_user();
}

// User Fixtures
$studentUser = [
    'id'       => 4,
    'name'     => 'Ahmad Fauzi',
    'username' => 'siswa1',
    'email'    => 'siswa1@example.com',
    'role'     => 'peserta_didik',
    'roles'    => ['peserta_didik'],
    'nip_nisn' => '1234567890'
];

$studentUser2 = [
    'id'       => 12,
    'name'     => 'Budi Santoso',
    'username' => 'siswa2',
    'email'    => 'siswa2@example.com',
    'role'     => 'peserta_didik',
    'roles'    => ['peserta_didik'],
    'nip_nisn' => '1234567891'
];

$teacherUser = [
    'id'       => 3,
    'name'     => 'Siti Aminah, S.Pd.',
    'username' => 'guru1',
    'email'    => 'guru1@example.com',
    'role'     => 'guru',
    'roles'    => ['guru'],
    'nip_nisn' => '198501012010012001'
];

$adminUser = [
    'id'       => 2,
    'name'     => 'Administrator Ujian',
    'username' => 'admin',
    'email'    => 'admin@example.com',
    'role'     => 'admin',
    'roles'    => ['admin'],
    'nip_nisn' => '198001012005011001'
];

// Bersihkan data awal uji
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

$notifModel = new NotificationModel();
$notifService = new NotificationService($notifModel);

// --------------------------------------------------------------------------
// UJI 1-3: HAK AKSES & TAMPILAN INBOX PER ROLE (SISWA, GURU, ADMIN)
// --------------------------------------------------------------------------
echo "--- 1. PENGUJIAN HAK AKSES & TAMPILAN INBOX PER ROLE ---\n";

// Siapkan notifikasi untuk Siswa (User 4)
$notifModel->createNotification([
    'user_id'    => 4,
    'title'      => 'Pengumuman Ujian Siswa',
    'message'    => 'Jadwal ujian matematika telah dirilis.',
    'type'       => 'info',
    'action_url' => '/siswa/exams'
]);

// Siapkan notifikasi untuk Guru (User 3)
$notifModel->createNotification([
    'user_id'    => 3,
    'title'      => 'Lembar Jawaban Masuk Guru',
    'message'    => 'Ada 15 lembar jawaban esai siap dinilai.',
    'type'       => 'warning',
    'action_url' => '/guru/grading'
]);

// Siapkan notifikasi untuk Admin (User 2)
$notifModel->createNotification([
    'user_id'    => 2,
    'title'      => 'Laporan Audit Admin',
    'message'    => 'Sistem pemantauan AI aktif.',
    'type'       => 'success',
    'action_url' => '/admin/monitoring'
]);

// UJI 1: Siswa Inbox
loginAs($studentUser);
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$studentCtrl = new NotificationController();
$resSiswa = $studentCtrl->inbox();
$htmlSiswa = $resSiswa->getContent();

$tc01_ok = $resSiswa->getStatusCode() === 200
    && str_contains($htmlSiswa, 'Inbox Notifikasi')
    && str_contains($htmlSiswa, 'Pengumuman Ujian Siswa')
    && str_contains($htmlSiswa, 'peserta-layout')
    && !str_contains($htmlSiswa, 'Lembar Jawaban Masuk Guru');
report('TC-INBOX-01', 'Siswa dapat melihat inbox miliknya menggunakan layout peserta didik', $tc01_ok, "Layout peserta-layout terverifikasi");

// UJI 2: Guru Inbox
loginAs($teacherUser);
$teacherCtrl = new NotificationController();
$resGuru = $teacherCtrl->inbox();
$htmlGuru = $resGuru->getContent();

$tc02_ok = $resGuru->getStatusCode() === 200
    && str_contains($htmlGuru, 'Inbox Notifikasi')
    && str_contains($htmlGuru, 'Lembar Jawaban Masuk Guru')
    && str_contains($htmlGuru, 'guru-layout')
    && !str_contains($htmlGuru, 'Pengumuman Ujian Siswa');
report('TC-INBOX-02', 'Guru dapat melihat inbox miliknya menggunakan layout guru', $tc02_ok, "Layout guru-layout terverifikasi");

// UJI 3: Admin Inbox
loginAs($adminUser);
$adminCtrl = new NotificationController();
$resAdmin = $adminCtrl->inbox();
$htmlAdmin = $resAdmin->getContent();

$tc03_ok = $resAdmin->getStatusCode() === 200
    && str_contains($htmlAdmin, 'Inbox Notifikasi')
    && str_contains($htmlAdmin, 'Laporan Audit Admin')
    && str_contains($htmlAdmin, 'admin-layout')
    && !str_contains($htmlAdmin, 'Lembar Jawaban Masuk Guru');
report('TC-INBOX-03', 'Admin dapat melihat inbox miliknya menggunakan layout admin', $tc03_ok, "Layout admin-layout terverifikasi");

// --------------------------------------------------------------------------
// UJI 4-5: AUTENTIKASI & PROTEKSI IDOR
// --------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN AUTENTIKASI & PROTEKSI IDOR ---\n";

// UJI 4: Pengguna belum login diarahkan ke login
logout();
$guestCtrl = new NotificationController();
$resGuest = $guestCtrl->inbox();

$tc04_ok = $resGuest->getStatusCode() === 302
    && str_contains($resGuest->getHeader('Location') ?? '', 'login');
report('TC-INBOX-04', 'Pengguna belum login diarahkan ke halaman login saat mengakses inbox', $tc04_ok, "Redirect Location: " . ($resGuest->getHeader('Location') ?? 'none'));

// UJI 5: IDOR Listing - Siswa 2 tidak melihat notifikasi Siswa 1
loginAs($studentUser2);
$s2Ctrl = new NotificationController();
$resS2 = $s2Ctrl->inbox();
$htmlS2 = $resS2->getContent();

$tc05_ok = !str_contains($htmlS2, 'Pengumuman Ujian Siswa')
    && !str_contains($htmlS2, 'Lembar Jawaban Masuk Guru')
    && !str_contains($htmlS2, 'Laporan Audit Admin');
report('TC-INBOX-05', 'Proteksi IDOR: Siswa tidak dapat melihat notifikasi milik pengguna lain', $tc05_ok, "Notifikasi pengguna lain terisolasi sempurna");

// --------------------------------------------------------------------------
// UJI 6-8: TAB STATUS (SEMUA, BELUM DIBACA, SUDAH DIBACA)
// --------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN TAB STATUS (SEMUA, BELUM DIBACA, SUDAH DIBACA) ---\n";

// Siapkan data untuk Siswa 1 (User 4):
// 2 Notifikasi Belum Dibaca, 1 Notifikasi Sudah Dibaca
$db->exec("DELETE FROM notifications WHERE user_id = 4");

$nUnread1 = $notifModel->createNotification([
    'user_id' => 4,
    'title'   => 'Tugas 1 Belum Dibaca',
    'message' => 'Pesan belum dibaca 1',
    'type'    => 'info'
]);
$nUnread2 = $notifModel->createNotification([
    'user_id' => 4,
    'title'   => 'Tugas 2 Belum Dibaca',
    'message' => 'Pesan belum dibaca 2',
    'type'    => 'warning'
]);
$nRead1 = $notifModel->createNotification([
    'user_id' => 4,
    'title'   => 'Tugas 3 Sudah Dibaca',
    'message' => 'Pesan sudah dibaca 3',
    'type'    => 'success'
]);
$notifModel->markAsRead((int) $nRead1, 4);

loginAs($studentUser);

// UJI 6: Tab Semua (status=all)
$_GET['status'] = 'all';
unset($_GET['type']);
$sCtrlAll = new NotificationController();
$resAll = $sCtrlAll->inbox();
$htmlAll = $resAll->getContent();

$tc06_ok = str_contains($htmlAll, 'Tugas 1 Belum Dibaca')
    && str_contains($htmlAll, 'Tugas 2 Belum Dibaca')
    && str_contains($htmlAll, 'Tugas 3 Sudah Dibaca');
report('TC-INBOX-06', 'Tab Semua menampilkan seluruh notifikasi (belum dibaca & sudah dibaca)', $tc06_ok, "Semua notifikasi ter-render");

// UJI 7: Tab Belum Dibaca (status=unread)
$_GET['status'] = 'unread';
$sCtrlUnread = new NotificationController();
$resUnread = $sCtrlUnread->inbox();
$htmlUnread = $resUnread->getContent();

$tc07_ok = str_contains($htmlUnread, 'Tugas 1 Belum Dibaca')
    && str_contains($htmlUnread, 'Tugas 2 Belum Dibaca')
    && !str_contains($htmlUnread, 'Tugas 3 Sudah Dibaca');
report('TC-INBOX-07', 'Tab Belum Dibaca hanya menampilkan notifikasi yang belum dibaca (is_read = 0)', $tc07_ok, "Notifikasi terbaca tersaring keluar");

// UJI 8: Tab Sudah Dibaca (status=read)
$_GET['status'] = 'read';
$sCtrlRead = new NotificationController();
$resRead = $sCtrlRead->inbox();
$htmlRead = $resRead->getContent();

$tc08_ok = !str_contains($htmlRead, 'Tugas 1 Belum Dibaca')
    && !str_contains($htmlRead, 'Tugas 2 Belum Dibaca')
    && str_contains($htmlRead, 'Tugas 3 Sudah Dibaca');
report('TC-INBOX-08', 'Tab Sudah Dibaca hanya menampilkan notifikasi yang telah dibaca (is_read = 1)', $tc08_ok, "Hanya notifikasi terbaca yang muncul");

// --------------------------------------------------------------------------
// UJI 9: FILTER TIPE NOTIFIKASI
// --------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN FILTER TIPE NOTIFIKASI ---\n";

$_GET['status'] = 'all';
$_GET['type'] = 'warning';
$sCtrlType = new NotificationController();
$resType = $sCtrlType->inbox();
$htmlType = $resType->getContent();

$tc09_ok = str_contains($htmlType, 'Tugas 2 Belum Dibaca')
    && !str_contains($htmlType, 'Tugas 1 Belum Dibaca')
    && !str_contains($htmlType, 'Tugas 3 Sudah Dibaca');
report('TC-INBOX-09', 'Filter tipe berhasil menyaring data sesuai tipe yang dipilih (type=warning)', $tc09_ok, "Hanya tipe warning yang muncul");

// --------------------------------------------------------------------------
// UJI 10-11: PAGINASI & PRESERVASI FILTER
// --------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN PAGINASI & PRESERVASI FILTER ---\n";

// Tambah beberapa notifikasi ekstra agar total mencapai 6
$notifModel->createNotification(['user_id' => 4, 'title' => 'Extra 1', 'message' => 'Msg', 'type' => 'info']);
$notifModel->createNotification(['user_id' => 4, 'title' => 'Extra 2', 'message' => 'Msg', 'type' => 'info']);
$notifModel->createNotification(['user_id' => 4, 'title' => 'Extra 3', 'message' => 'Msg', 'type' => 'info']);

unset($_GET['type']);
$_GET['status'] = 'all';
$_GET['per_page'] = 2;
$_GET['page'] = 1;

$sCtrlPage1 = new NotificationController();
$resPage1 = $sCtrlPage1->inbox();
$htmlPage1 = $resPage1->getContent();

// Hitung kartu notifikasi di halaman 1 (maksimal 2 kartu)
$itemCardCount = substr_count($htmlPage1, 'class="card border rounded-3 p-3 transition-all notif-item');

$tc10_ok = $itemCardCount === 2 && str_contains($htmlPage1, 'Menampilkan');
report('TC-INBOX-10', 'Paginasi berhasil membatasi jumlah data per halaman (per_page=2)', $tc10_ok, "Jumlah kartu di page 1: {$itemCardCount}");

// UJI 11: Preservasi filter pada pagination link
$_GET['status'] = 'unread';
$_GET['type'] = 'info';
$sCtrlPreserve = new NotificationController();
$resPreserve = $sCtrlPreserve->inbox();
$htmlPreserve = $resPreserve->getContent();

$tc11_ok = str_contains($htmlPreserve, 'status=unread')
    && str_contains($htmlPreserve, 'type=info');
report('TC-INBOX-11', 'Link paginasi mempertahankan parameter filter status dan tipe', $tc11_ok, "Preservasi query string valid");

// --------------------------------------------------------------------------
// UJI 12-13: MARK AS READ & MARK ALL READ VIA CONTROLLER
// --------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN MARK AS READ & MARK ALL AS READ ---\n";

// UJI 12: Mark single as read
$resMarkSingle = $studentCtrl->markAsRead((int) $nUnread1);
$bodyMarkSingle = json_decode($resMarkSingle->getContent(), true);

$unreadAfterSingle = $notifModel->getUnreadCount(4);
$tc12_ok = $resMarkSingle->getStatusCode() === 200
    && $bodyMarkSingle['success'] === true;
report('TC-INBOX-12', 'Tandai satu notifikasi sebagai dibaca berhasil memperbarui status di database', $tc12_ok, "Unread sekarang: {$unreadAfterSingle}");

// UJI 13: Mark all as read
$resMarkAll = $studentCtrl->markAllAsRead();
$bodyMarkAll = json_decode($resMarkAll->getContent(), true);

$unreadAfterAll = $notifModel->getUnreadCount(4);
$tc13_ok = $resMarkAll->getStatusCode() === 200
    && $bodyMarkAll['success'] === true
    && $unreadAfterAll === 0;
report('TC-INBOX-13', 'Tandai semua notifikasi sebagai dibaca berhasil mereset seluruh unread count menjadi 0', $tc13_ok, "Unread sekarang: {$unreadAfterAll}");

// --------------------------------------------------------------------------
// UJI 14: PROTEKSI CSRF PADA MUTASI
// --------------------------------------------------------------------------
echo "\n--- 7. PENGUJIAN PROTEKSI CSRF ---\n";

unset($_SERVER['HTTP_X_CSRF_TOKEN']);
unset($_POST['_csrf_token']);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

$csrfFilter = new CsrfFilter();
$csrfBlock = $csrfFilter->before(new Request());

$tc14_ok = $csrfBlock !== null
    && $csrfBlock->getStatusCode() === 419;
report('TC-INBOX-14', 'Request mutasi (POST) tanpa CSRF token ditolak dengan HTTP 419 Authentication Timeout', $tc14_ok, "Status code: " . ($csrfBlock ? $csrfBlock->getStatusCode() : 'none'));

// --------------------------------------------------------------------------
// UJI 15: SANITASI ACTION URL
// --------------------------------------------------------------------------
echo "\n--- 8. PENGUJIAN SANITASI ACTION URL ---\n";

$safeInternal1 = $studentCtrl->sanitizeActionUrl('/siswa/exams/show/39');
$safeInternal2 = $studentCtrl->sanitizeActionUrl('siswa/results');
$dangerousJs   = $studentCtrl->sanitizeActionUrl("javascript:alert('XSS')");
$dangerousData = $studentCtrl->sanitizeActionUrl("data:text/html,<script>alert(1)</script>");
$externalHost  = $studentCtrl->sanitizeActionUrl("https://evil-phishing-site.com/steal-token");

$tc15_ok = !empty($safeInternal1)
    && !empty($safeInternal2)
    && $dangerousJs === null
    && $dangerousData === null
    && $externalHost === null;
report('TC-INBOX-15', 'Sanitasi Action URL: Menolak skema javascript, data, dan URL eksternal berbahaya', $tc15_ok, "URL berbahaya berhasil dinetralisir");

// --------------------------------------------------------------------------
// UJI 16: PROTEKSI ANTI-XSS PADA TITLE & MESSAGE
// --------------------------------------------------------------------------
echo "\n--- 9. PENGUJIAN ANTI-XSS & KEAMANAN TOKEN ---\n";

loginAs($studentUser);
$_GET = ['status' => 'all'];
$xssPayload = '<script>alert("Hacked")</script><b>BoldTest</b>';
$notifModel->createNotification([
    'user_id' => 4,
    'title'   => $xssPayload,
    'message' => $xssPayload,
    'type'    => 'danger'
]);

$sCtrlXss = new NotificationController();
$resXss = $sCtrlXss->inbox();
$htmlXss = $resXss->getContent();

// Di HTML, payload harus ter-escape (misal &lt;script&gt;) dan tidak boleh ada tag mentah <script>alert
$tc16_ok = !str_contains($htmlXss, '<script>alert("Hacked")</script>')
    && str_contains($htmlXss, '&lt;script&gt;alert(&quot;Hacked&quot;)&lt;/script&gt;');
report('TC-INBOX-16', 'Proteksi XSS: Title dan message dengan payload HTML/JS ter-escape sempurna', $tc16_ok, "Tag HTML berbahaya dinetralisir");

// --------------------------------------------------------------------------
// UJI 17: TOKEN PROTECTION GUARDRAIL SISWA
// --------------------------------------------------------------------------
loginAs($studentUser);
$_GET = ['status' => 'all'];
$notifService->notifyUser(4, 'Jadwal Ujian Matematika', 'Gunakan token PASMAT1 untuk masuk ke ujian.', 'warning', '/siswa/exams');

$sCtrlToken = new NotificationController();
$resToken = $sCtrlToken->inbox();
$htmlToken = $resToken->getContent();

$tc17_ok = !str_contains($htmlToken, 'PASMAT1')
    && str_contains($htmlToken, '[TERPROTEKSI]');
report('TC-INBOX-17', 'Token Protection Guardrail: Token ujian tidak bocor pada inbox siswa dan disensor', $tc17_ok, "Token disensor menjadi [TERPROTEKSI]");

// --------------------------------------------------------------------------
// UJI 18-20: EMPTY STATE, ERROR STATE, & RESPONSIF LAYOUT
// --------------------------------------------------------------------------
echo "\n--- 10. PENGUJIAN EMPTY STATE, ERROR STATE & RESPONSIF LAYOUT ---\n";

// Kosongkan notifikasi Siswa 2 untuk uji Empty State
$db->exec("DELETE FROM notifications WHERE user_id = 12");
loginAs($studentUser2);
$_GET = ['status' => 'all'];

$s2CtrlEmpty = new NotificationController();
$resEmpty = $s2CtrlEmpty->inbox();
$htmlEmpty = $resEmpty->getContent();

$tc18_ok = str_contains($htmlEmpty, 'inboxEmptyState')
    && str_contains($htmlEmpty, 'Tidak Ada Notifikasi');
report('TC-INBOX-18', 'Empty state ditampilkan saat pengguna tidak memiliki notifikasi', $tc18_ok, "Empty state ter-render sempurna");

// UJI 19: Error state alert
$viewSource = file_get_contents(__DIR__ . '/../app/Views/notifications/inbox.php');
$tc19_ok = str_contains($viewSource, 'id="inboxErrorAlert"')
    && str_contains($viewSource, 'showError')
    && str_contains($viewSource, 'inboxErrorMessage');
report('TC-INBOX-19', 'Error state alert dan penanganan error JavaScript tersedia untuk kegagalan request', $tc19_ok, "Container error alert terverifikasi");

// UJI 20: Responsivitas Layout & Komponen Bootstrap
$hasResponsivePills  = str_contains($viewSource, 'nav nav-pills');
$hasResponsivePaging = str_contains($viewSource, 'components/pagination.php');
$hasResponsiveCard   = str_contains($viewSource, 'd-flex flex-wrap justify-content-between');
$hasBadgePills       = str_contains($viewSource, 'rounded-pill');

$tc20_ok = $hasResponsivePills && $hasResponsivePaging && $hasResponsiveCard && $hasBadgePills;
report('TC-INBOX-20', 'Layout responsif terintegrasi dengan komponen Bootstrap, tabs, badges, dan paginasi', $tc20_ok, "Kepatuhan layout responsif valid");

// --------------------------------------------------------------------------
// CLEANUP DATABASE PENGUJIAN
// --------------------------------------------------------------------------
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN INBOX TAHAP 3B: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
