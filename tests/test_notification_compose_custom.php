<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — AUTOMATED TEST SUITE: CUSTOM NOTIFICATION COMPOSER & EDIT CONTACT
 * ==============================================================================
 * Pengujian komprehensif untuk fitur pengiriman notifikasi kustom admin:
 * 1. Validasi Sintaks Seluruh File MVC Terkait.
 * 2. Registrasi Route Admin (/admin/notifications/compose, /send-custom, /users-list).
 * 3. Hak Akses RBAC (Siswa & Guru diblokir, Admin & Super Admin diizinkan).
 * 4. Pemuatan Data Pengguna Terdaftar pada Form Compose.
 * 5. Validasi Input (User ID, Channels, Judul, Pesan, Format Email, Format Phone).
 * 6. Pengiriman Notifikasi Kustom dengan Override Email & No WhatsApp Mandiri.
 * 7. Fitur Sinkronisasi Profil (update_profile = true).
 * 8. Sanitasi Privasi & Perlindungan Token Siswa ([TERPROTEKSI]).
 * 9. Isolasi Database Operasional (ujian_dual_kamera strictly 0 rows).
 * ==============================================================================
 */

// 1. Inisialisasi Environment Testing
$_ENV['DB_DATABASE'] = 'ujian_dual_kamera_test';
$_SERVER['DB_DATABASE'] = 'ujian_dual_kamera_test';
putenv('DB_DATABASE=ujian_dual_kamera_test');

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = 'ujian_dual_kamera_test';

use App\Controllers\NotificationLogController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Models\NotificationLogModel;
use App\Models\UserModel;
use App\Services\NotificationService;

$passedTests = 0;
$failedTests = 0;

function testAssert(string $testId, string $description, bool $condition, string $details = ''): void
{
    global $passedTests, $failedTests;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$testId}: {$description}\n";
        if (!empty($details)) {
            echo "         INFO: {$details}\n";
        }
    } else {
        $failedTests++;
        echo " ![FAIL] {$testId}: {$description}\n";
        if (!empty($details)) {
            echo "         ERROR: {$details}\n";
        }
    }
}

echo "==============================================================================\n";
echo "  TEST SUITE: CUSTOM NOTIFICATION COMPOSER & EDIT CONTACT AUDIT\n";
echo "==============================================================================\n";
echo "  Target Database : " . \App\Config\Database::$database . "\n";
echo "  Waktu Pengujian : " . date('Y-m-d H:i:s') . "\n";
echo "==============================================================================\n\n";

// -----------------------------------------------------------------------------
// SEKSI 1: SINTAKS PHP SELURUH FILE TERKAIT
// -----------------------------------------------------------------------------
echo "--- SEKSI 1: VALIDASI SINTAKS SELURUH KOMPONEN ---\n";
$filesToLint = [
    'app/Controllers/NotificationLogController.php',
    'app/Views/admin/notifications/compose.php',
    'app/Views/admin/notifications/logs.php',
    'app/Config/Routes.php',
    'app/Views/components/sidebar.php',
    'app/Services/NotificationService.php'
];

foreach ($filesToLint as $file) {
    $fullPath = realpath(__DIR__ . '/../' . $file);
    $syntaxOk = false;
    if ($fullPath && file_exists($fullPath)) {
        exec("C:\\xampp\\php\\php.exe -l " . escapeshellarg($fullPath), $out, $ret);
        $syntaxOk = ($ret === 0);
    }
    testAssert("TC-COMP-SYNTAX-{$file}", "Sintaks file {$file} valid", $syntaxOk);
}

// -----------------------------------------------------------------------------
// SEKSI 2: REGISTRASI ROUTE ADMIN
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 2: REGISTRASI ROUTE ADMIN ---\n";
$routesContent = file_get_contents(__DIR__ . '/../app/Config/Routes.php');
$composeRegistered = str_contains($routesContent, "Router::get('/notifications/compose', [NotificationLogController::class, 'compose']);");
$sendCustomRegistered = str_contains($routesContent, "Router::post('/notifications/send-custom', [NotificationLogController::class, 'sendCustom'], ['filter' => 'csrf']);");
$usersListRegistered = str_contains($routesContent, "Router::get('/notifications/users-list', [NotificationLogController::class, 'getUsersList']);");

testAssert('TC-COMP-ROUTE-01', 'Route GET /admin/notifications/compose terdaftar di Routes.php', $composeRegistered);
testAssert('TC-COMP-ROUTE-02', 'Route POST /admin/notifications/send-custom terdaftar dengan filter csrf', $sendCustomRegistered);
testAssert('TC-COMP-ROUTE-03', 'Route GET /admin/notifications/users-list terdaftar di Routes.php', $usersListRegistered);

// -----------------------------------------------------------------------------
// SEKSI 3: HAK AKSES RBAC (ADMIN VS NON-ADMIN)
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 3: HAK AKSES RBAC (ADMIN VS NON-ADMIN) ---\n";
Session::start();

// 3a. Pengujian Akses Siswa -> Wajib Ditolak (302 ke unauthorized)
Session::set('user', [
    'id'       => 9991,
    'name'     => 'Siswa Testing',
    'email'    => 'siswa@test.sch.id',
    'role'     => 'peserta_didik',
    'roles'    => ['peserta_didik']
]);
$controller = new NotificationLogController();
$respSiswa = $controller->compose();
$siswaBlocked = ($respSiswa->getStatusCode() === 302) && str_contains((string)($respSiswa->getHeader('Location') ?? ''), 'unauthorized');
testAssert('TC-COMP-RBAC-01', 'Siswa / Peserta Didik diblokir dari halaman compose notifikasi', $siswaBlocked);

// 3b. Pengujian Akses Guru -> Wajib Ditolak (302 ke unauthorized)
Session::set('user', [
    'id'       => 9992,
    'name'     => 'Guru Testing',
    'email'    => 'guru@test.sch.id',
    'role'     => 'guru',
    'roles'    => ['guru']
]);
$respGuru = $controller->compose();
$guruBlocked = ($respGuru->getStatusCode() === 302) && str_contains((string)($respGuru->getHeader('Location') ?? ''), 'unauthorized');
testAssert('TC-COMP-RBAC-02', 'Guru / Pengawas diblokir dari halaman compose notifikasi', $guruBlocked);

// 3c. Pengujian Akses Admin -> Diizinkan (HTTP 200)
Session::set('user', [
    'id'       => 1,
    'name'     => 'Admin Utama',
    'email'    => 'admin@test.sch.id',
    'role'     => 'admin',
    'roles'    => ['admin', 'super_admin']
]);
$respAdmin = $controller->compose();
$adminAllowed = ($respAdmin->getStatusCode() === 200);
$bodyAdmin = $respAdmin->getContent();
$hasForm = str_contains($bodyAdmin, 'customNotificationForm');
$hasUserSelect = str_contains($bodyAdmin, 'id="userSelect"');
$hasRecipientEmail = str_contains($bodyAdmin, 'id="recipientEmail"');
$hasRecipientPhone = str_contains($bodyAdmin, 'id="recipientPhone"');
testAssert('TC-COMP-RBAC-03', 'Admin dapat mengakses halaman compose dan memuat formulir lengkap', $adminAllowed && $hasForm && $hasUserSelect && $hasRecipientEmail && $hasRecipientPhone);

// -----------------------------------------------------------------------------
// SEKSI 4: PEMUATAN DATA PENGGUNA TERDAFTAR
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 4: DATA PENGGUNA TERDAFTAR & USERS LIST ---\n";
$reqUsers = new Request([], ['REQUEST_METHOD' => 'GET']);
$controllerUsers = new NotificationLogController($reqUsers);
$respUsersList = $controllerUsers->getUsersList();
$jsonUsers = json_decode($respUsersList->getContent(), true);
$usersListSuccess = !empty($jsonUsers['success']) && is_array($jsonUsers['users']) && count($jsonUsers['users']) > 0;
testAssert('TC-COMP-DATA-01', 'getUsersList mengembalikan daftar pengguna terdaftar aktif', $usersListSuccess, "Total pengguna ditemukan: " . count($jsonUsers['users'] ?? []));

// -----------------------------------------------------------------------------
// SEKSI 5: VALIDASI INPUT FORM SEND-CUSTOM
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 5: VALIDASI INPUT FORM SEND-CUSTOM ---\n";

// 5a. Validasi user_id kosong
$reqNoUser = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id' => 0,
    'title'   => 'Judul Uji',
    'message' => 'Pesan Uji',
    'channels'=> ['in_app']
]);
$ctrlNoUser = new NotificationLogController($reqNoUser);
$respNoUser = $ctrlNoUser->sendCustom();
$flashNoUser = implode(' ', Session::getFlash('danger'));
testAssert('TC-COMP-VAL-01', 'Validasi menolak form tanpa user_id', ($respNoUser->getStatusCode() === 302) && str_contains($flashNoUser, 'pilih pengguna'));

// 5b. Validasi saluran kosong
$reqNoChannels = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id' => 4,
    'title'   => 'Judul Uji',
    'message' => 'Pesan Uji',
    'channels'=> []
]);
$ctrlNoChannels = new NotificationLogController($reqNoChannels);
$respNoChannels = $ctrlNoChannels->sendCustom();
$flashNoChannels = implode(' ', Session::getFlash('danger'));
testAssert('TC-COMP-VAL-02', 'Validasi menolak form tanpa pilihan saluran pengiriman', ($respNoChannels->getStatusCode() === 302) && str_contains($flashNoChannels, 'minimal satu saluran'));

// 5c. Validasi email tidak valid
$reqBadEmail = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id'         => 4,
    'recipient_email' => 'bukan-email-valid',
    'title'           => 'Judul Uji',
    'message'         => 'Pesan Uji',
    'channels'        => ['email']
]);
$ctrlBadEmail = new NotificationLogController($reqBadEmail);
$respBadEmail = $ctrlBadEmail->sendCustom();
$flashBadEmail = implode(' ', Session::getFlash('danger'));
testAssert('TC-COMP-VAL-03', 'Validasi menolak format email yang tidak valid', ($respBadEmail->getStatusCode() === 302) && str_contains($flashBadEmail, 'alamat email'));

// 5d. Validasi nomor WhatsApp tidak valid
$reqBadPhone = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id'         => 4,
    'recipient_phone' => '123',
    'title'           => 'Judul Uji',
    'message'         => 'Pesan Uji',
    'channels'        => ['whatsapp']
]);
$ctrlBadPhone = new NotificationLogController($reqBadPhone);
$respBadPhone = $ctrlBadPhone->sendCustom();
$flashBadPhone = implode(' ', Session::getFlash('danger'));
testAssert('TC-COMP-VAL-04', 'Validasi menolak format nomor WhatsApp tidak valid / terlalu pendek', ($respBadPhone->getStatusCode() === 302) && str_contains($flashBadPhone, 'WhatsApp tidak valid'));

// -----------------------------------------------------------------------------
// SEKSI 6: PENGIRIMAN NOTIFIKASI DENGAN EDIT EMAIL & NOMOR MANDIRI
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 6: DISPATCH DENGAN OVERRIDE EMAIL & NOMOR MANDIRI ---\n";
// Ambil akun pengguna aktif untuk pengujian (Ahmad Fauzi / user_id 4)
$customEmail = 'tester_custom_' . time() . '@sekolah.sch.id';
$customPhone = '0812' . rand(10000000, 99999999);
$customTitle = 'Pemberitahuan Ujian Mandiri ' . time();
$customMessage = 'Halo, ini adalah pesan notifikasi kustom dengan email dan nomor yang diedit sendiri.';

$reqCustom = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id'         => 4,
    'recipient_email' => $customEmail,
    'recipient_phone' => $customPhone,
    'title'           => $customTitle,
    'message'         => $customMessage,
    'type'            => 'info',
    'action_url'      => '/siswa/exams',
    'channels'        => ['in_app', 'whatsapp', 'email'],
    'update_profile'  => 0 // Jangan update profil permanen pada test ini
]);

$ctrlCustom = new NotificationLogController($reqCustom);
$respCustom = $ctrlCustom->sendCustom();

// Verifikasi respons
$successFlash = implode(' ', Session::getFlash('success'));
$isRedirectSuccess = ($respCustom->getStatusCode() === 302) && !empty($successFlash);
testAssert('TC-COMP-DISPATCH-01', 'Pengiriman custom notification berhasil dan redirect dengan flash success', $isRedirectSuccess, "Flash: {$successFlash}");

// Verifikasi record log eksternal menggunakan custom email & custom phone
$db = Database::getConnection();
$stmtLogWa = $db->prepare("SELECT * FROM notification_logs WHERE channel = 'whatsapp' ORDER BY id DESC LIMIT 1");
$stmtLogWa->execute();
$lastWaLog = $stmtLogWa->fetch(PDO::FETCH_ASSOC);

$stmtLogEmail = $db->prepare("SELECT * FROM notification_logs WHERE channel = 'email' ORDER BY id DESC LIMIT 1");
$stmtLogEmail->execute();
$lastEmailLog = $stmtLogEmail->fetch(PDO::FETCH_ASSOC);

$waRecipientMatched = ($lastWaLog && ($lastWaLog['recipient'] === $customPhone || $lastWaLog['recipient'] === \App\Config\Notification::normalizePhoneNumber($customPhone)));
$emailRecipientMatched = ($lastEmailLog && $lastEmailLog['recipient'] === $customEmail);

testAssert('TC-COMP-DISPATCH-02', 'Nomor WhatsApp di log mencatat nomor hasil editan manual admin', $waRecipientMatched, "Nomor tercatat: " . ($lastWaLog['recipient'] ?? 'N/A'));
testAssert('TC-COMP-DISPATCH-03', 'Alamat Email di log mencatat alamat email hasil editan manual admin', $emailRecipientMatched, "Email tercatat: " . ($lastEmailLog['recipient'] ?? 'N/A'));

// -----------------------------------------------------------------------------
// SEKSI 7: SINKRONISASI UPDATE PROFIL DATABASE (update_profile = 1)
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 7: SINKRONISASI UPDATE PROFIL PENGGUNA ---\n";
// Buat user dummy sementara pada testing DB untuk menguji fitur update profil
$dummyEmailInitial = 'profile_test_init_' . time() . '@test.sch.id';
$stmtCreateDummy = $db->prepare("
    INSERT INTO users (name, username, email, password, phone, status, created_at, updated_at)
    VALUES ('Dummy User Test', :username, :email, :password, '081200009999', 'active', NOW(), NOW())
");
$dummyUsername = 'dummy_user_' . time();
$dummyPassword = password_hash('password123', PASSWORD_BCRYPT);
$stmtCreateDummy->execute([':username' => $dummyUsername, ':email' => $dummyEmailInitial, ':password' => $dummyPassword]);
$dummyUserId = (int)$db->lastInsertId();

$newUpdatedEmail = 'profile_test_updated_' . time() . '@test.sch.id';
$newUpdatedPhone = '081399887766';

$reqUpdateProfile = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id'         => $dummyUserId,
    'recipient_email' => $newUpdatedEmail,
    'recipient_phone' => $newUpdatedPhone,
    'title'           => 'Pemberitahuan Profil Terbarui',
    'message'         => 'Kontak akun Anda telah diperbarui oleh Administrator.',
    'channels'        => ['in_app', 'email'],
    'update_profile'  => 1 // Aktifkan opsi simpan ke profil database
]);

$ctrlUpdateProfile = new NotificationLogController($reqUpdateProfile);
$respUpdate = $ctrlUpdateProfile->sendCustom();

// Periksa data akun di tabel users
$stmtCheckUser = $db->prepare("SELECT email, phone FROM users WHERE id = :id");
$stmtCheckUser->execute([':id' => $dummyUserId]);
$updatedUser = $stmtCheckUser->fetch(PDO::FETCH_ASSOC);

$profileEmailUpdated = ($updatedUser && $updatedUser['email'] === $newUpdatedEmail);
$profilePhoneUpdated = ($updatedUser && $updatedUser['phone'] === $newUpdatedPhone);

testAssert('TC-COMP-PROFILE-01', 'Opsi update_profile = 1 memperbarui email pengguna di tabel users', $profileEmailUpdated, "Email di DB: " . ($updatedUser['email'] ?? 'N/A'));
testAssert('TC-COMP-PROFILE-02', 'Opsi update_profile = 1 memperbarui nomor WhatsApp di tabel users', $profilePhoneUpdated, "Phone di DB: " . ($updatedUser['phone'] ?? 'N/A'));

// Bersihkan user dummy dari testing database
$db->exec("DELETE FROM users WHERE id = {$dummyUserId}");

// -----------------------------------------------------------------------------
// SEKSI 8: SANITASI PRIVASI & GUARDRAIL TOKEN UJIAN SISWA
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 8: SANITASI PRIVASI SISWA ---\n";
// Siswa tidak boleh menerima token ujian riil
$testToken = 'SEC_TKN_' . rand(1000, 9999);
$msgWithToken = "Halo Siswa, token ujian Anda: {$testToken}. Harap simpan.";

$reqStudentToken = new Request([], ['REQUEST_METHOD' => 'POST'], [
    'user_id'         => 4, // Siswa Ahmad Fauzi
    'recipient_email' => 'siswa_token_test@test.sch.id',
    'recipient_phone' => '081233445566',
    'title'           => 'Token Ujian Uji Coba',
    'message'         => $msgWithToken,
    'channels'        => ['in_app', 'whatsapp'],
    'update_profile'  => 0
]);

$ctrlStudentToken = new NotificationLogController($reqStudentToken);
$ctrlStudentToken->sendCustom();

// Periksa pesan notifikasi in-app
$stmtNotif = $db->prepare("SELECT message FROM notifications WHERE user_id = 4 ORDER BY id DESC LIMIT 1");
$stmtNotif->execute();
$inAppMsg = (string)$stmtNotif->fetchColumn();

$tokenScrubbed = !str_contains($inAppMsg, $testToken) && str_contains($inAppMsg, 'TERPROTEKSI');
testAssert('TC-COMP-PRIVACY-01', 'Token ujian otomatis disensor [TERPROTEKSI] pada notifikasi siswa', $tokenScrubbed, "Pesan in-app: {$inAppMsg}");

// -----------------------------------------------------------------------------
// SEKSI 9: ISOLASI DATABASE OPERASIONAL
// -----------------------------------------------------------------------------
echo "\n--- SEKSI 9: AUDIT DATABASE OPERASIONAL ---\n";
$dbHost = \App\Config\Database::$host;
$dbUser = \App\Config\Database::$username;
$dbPass = \App\Config\Database::$password;
$dbPort = \App\Config\Database::$port;

$pdoOp = new PDO("mysql:host={$dbHost};port={$dbPort};dbname=ujian_dual_kamera;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$totalOpLogs = (int)$pdoOp->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();
testAssert('TC-COMP-DB-01', 'Database operasional (ujian_dual_kamera) STRICTLY tidak tersentuh (0 logs)', $totalOpLogs === 0, "Total baris notification_logs di database operasional: {$totalOpLogs}");

// -----------------------------------------------------------------------------
// RINGKASAN HASIL TEST
// -----------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "  HASIL AKHIR TEST SUITE CUSTOM NOTIFICATION: {$passedTests} PASS / {$failedTests} FAIL\n";
echo "==============================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
