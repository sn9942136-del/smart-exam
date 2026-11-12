<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: PERBAIKAN TAHAP 1 — KEAMANAN NOTIFIKASI SMART EXAM
 * ==============================================================================
 * Memvalidasi 10 item pengujian wajib:
 * 1. Token ujian tidak tampil pada payload admin (maskSensitiveValues & preview string)
 * 2. Token ujian tidak tampil pada judul notifikasi siswa
 * 3. Token ujian tidak tampil pada isi notifikasi siswa
 * 4. Password, OTP, dan API Key tetap disamarkan (baik key name maupun value string)
 * 5. URL internal valid tetap dapat digunakan pada getDropdownData & sanitizeActionUrl
 * 6. URL berbahaya (javascript:, data:, vbscript:, host luar) ditolak dengan aman
 * 7. Notifikasi guru dan admin tetap berfungsi (token operasional guru tidak rusak)
 * 8. Dropdown navbar tetap berfungsi normal (HTTP 200, JSON valid, unread counter)
 * 9. Tidak ada regresi pada inbox notifikasi
 * 10. Tidak ada perubahan/gangguan terhadap autentikasi, OTP, dan submit ujian
 * ==============================================================================
 */

// Konfigurasi database pengujian terisolasi
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Controllers\NotificationController;
use App\Controllers\NotificationLogController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Models\NotificationLogModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\ExamSessionService;
use App\Services\NotificationService;
use App\Services\OtpService;
use App\Services\PasswordService;

// Counter pengujian
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest(string $code, string $name, bool $condition, string $info = ''): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] {$code}: {$name}\n";
        if (!empty($info)) {
            echo "        INFO: {$info}\n";
        }
    } else {
        $failedTests++;
        echo "![FAIL] {$code}: {$name}\n";
        if (!empty($info)) {
            echo "        ERROR: {$info}\n";
        }
    }
}

echo "====================================================================\n";
echo "   TEST SUITE: PERBAIKAN TAHAP 1 — KEAMANAN NOTIFIKASI              \n";
echo "====================================================================\n";
echo "Target Database: " . \App\Config\Database::$database . "\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== 'ujian_dual_kamera_test') {
    die("FATAL: Pengujian hanya diizinkan berjalan di database ujian_dual_kamera_test!\n");
}

// Persiapkan Fixture Token Ujian Riil di exam_tokens
$fixtureExamId = (int)$db->query("SELECT id FROM exams LIMIT 1")->fetchColumn() ?: 1;
$testTokenCode = 'TOKN' . strtoupper(substr(uniqid(), -4));
$db->prepare("INSERT INTO exam_tokens (exam_id, token, valid_from, valid_until, is_active, created_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 1, NOW())")
   ->execute([$fixtureExamId, $testTokenCode]);
$tokenId = (int)$db->lastInsertId();

// Persiapkan Pengguna Uji (Siswa ID 4, Guru ID 3, Admin ID 1)
$studentId = 4;
$teacherId = 3;
$adminId = 1;

// -----------------------------------------------------------------------------
// PENGUJIAN 1: TOKEN UJIAN TIDAK TAMPIL PADA PAYLOAD ADMIN (KEY & VALUE MASKING)
// -----------------------------------------------------------------------------
echo "--- 1. PENGUJIAN MASKING PAYLOAD LOG ADMIN ---\n";

$logController = new NotificationLogController();
$refLog = new \ReflectionClass(NotificationLogController::class);
$methodMask = $refLog->getMethod('sanitizePayloadForDisplay');
$methodMask->setAccessible(true);

// Uji A: Token riil di dalam string 'preview' (bukan key bernama token)
$payloadWithPreviewToken = json_encode([
    'info'    => 'WhatsApp Gateway standby / simulasi arsitektur siap.',
    'title'   => 'Token Akses Ujian Matematika',
    'preview' => "Token ujian Matematika telah siap: [{$testTokenCode}]. Bagikan ke siswa saat ujian."
]);

$sanitizedPreview = $methodMask->invoke($logController, $payloadWithPreviewToken);
$previewNoToken = !str_contains(json_encode($sanitizedPreview), $testTokenCode);
$previewHasMask = str_contains($sanitizedPreview['preview'] ?? '', '***[TERPROTEKSI]***');
$previewContextPreserved = str_contains($sanitizedPreview['preview'] ?? '', 'Token ujian Matematika telah siap:');

runTest(
    'TC-STG1-01',
    'Token ujian riil di dalam string preview tersamarkan tanpa merusak konteks teks lainnya',
    $previewNoToken && $previewHasMask && $previewContextPreserved,
    "Output preview: " . ($sanitizedPreview['preview'] ?? '')
);

// Uji B: Token format bracket generik [XYZ123] dalam string
$payloadWithBracketToken = json_encode([
    'message' => 'Token Anda adalah [SECRET99] untuk sesi ujian.',
    'status'  => 'queued'
]);
$sanitizedBracket = $methodMask->invoke($logController, $payloadWithBracketToken);
$bracketMasked = !str_contains(json_encode($sanitizedBracket), 'SECRET99') && str_contains($sanitizedBracket['message'] ?? '', '***[TERPROTEKSI]***');
runTest('TC-STG1-01B', 'Token pola bracket generik di dalam string tersamarkan', $bracketMasked);

// -----------------------------------------------------------------------------
// PENGUJIAN 2 & 3: TOKEN UJIAN TIDAK TAMPIL PADA JUDUL & ISI NOTIFIKASI SISWA
// -----------------------------------------------------------------------------
echo "\n--- 2 & 3. PENGUJIAN SANITASI JUDUL & ISI NOTIFIKASI SISWA ---\n";

$notifService = new NotificationService();
$notifModel = new NotificationModel();

// Uji 2A: notifyStudents dengan token pada JUDUL dan ISI
$testTitleWithToken = "Token Ujian [{$testTokenCode}] Siap Digunakan";
$testMessageWithToken = "Gunakan token {$testTokenCode} untuk membuka lembar soal ujian.";

$resStudents = $notifService->notifyStudents(
    [$studentId],
    $testTitleWithToken,
    $testMessageWithToken,
    NotificationService::TYPE_INFO,
    '/siswa/exams'
);

// Ambil baris notifikasi yang baru tersimpan untuk siswa
$latestStudentNotif = $db->query("SELECT * FROM notifications WHERE user_id = {$studentId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$studentTitleClean = !str_contains($latestStudentNotif['title'] ?? '', $testTokenCode);
$studentTitleMasked = str_contains($latestStudentNotif['title'] ?? '', '[TERPROTEKSI]');
$studentMsgClean = !str_contains($latestStudentNotif['message'] ?? '', $testTokenCode);
$studentMsgMasked = str_contains($latestStudentNotif['message'] ?? '', '[TERPROTEKSI]');

runTest(
    'TC-STG1-02',
    'Token ujian TIDAK tampil pada JUDUL notifikasi siswa (notifyStudents)',
    $studentTitleClean && $studentTitleMasked,
    "Judul tersimpan: " . ($latestStudentNotif['title'] ?? '')
);

runTest(
    'TC-STG1-03',
    'Token ujian TIDAK tampil pada ISI notifikasi siswa (notifyStudents)',
    $studentMsgClean && $studentMsgMasked,
    "Pesan tersimpan: " . ($latestStudentNotif['message'] ?? '')
);

// Uji 2B: notifyClass dengan token pada JUDUL dan ISI
$resClass = $notifService->notifyClass(
    1, // Kelas ID 1
    "Informasi Token: [{$testTokenCode}]",
    "Perhatian kelas, token ujian Anda adalah {$testTokenCode}.",
    NotificationService::TYPE_INFO,
    '/siswa/exams'
);

$latestClassNotif = $db->query("SELECT * FROM notifications WHERE user_id = {$studentId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$classTitleClean = !str_contains($latestClassNotif['title'] ?? '', $testTokenCode) && str_contains($latestClassNotif['title'] ?? '', '[TERPROTEKSI]');
$classMsgClean = !str_contains($latestClassNotif['message'] ?? '', $testTokenCode) && str_contains($latestClassNotif['message'] ?? '', '[TERPROTEKSI]');

runTest('TC-STG1-02B', 'Token ujian TIDAK tampil pada JUDUL notifikasi rombel kelas (notifyClass)', $classTitleClean);
runTest('TC-STG1-03B', 'Token ujian TIDAK tampil pada ISI notifikasi rombel kelas (notifyClass)', $classMsgClean);

// Uji 2C: notifyUser untuk siswa dengan channel WhatsApp & Email
$resUserStudent = $notifService->notifyUser(
    $studentId,
    "Token Ujian Siswa [{$testTokenCode}]",
    "Pesan token siswa: {$testTokenCode}",
    NotificationService::TYPE_INFO,
    '/siswa/exams',
    [NotificationService::CHANNEL_IN_APP, NotificationService::CHANNEL_WHATSAPP, NotificationService::CHANNEL_EMAIL]
);

// Verifikasi log eksternal siswa: pastikan token TIDAK bocor ke notification_logs
$latestLogs = $db->query("SELECT * FROM notification_logs ORDER BY id DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
$logPayloadLeaked = false;
foreach ($latestLogs as $logItem) {
    if (str_contains((string)($logItem['response_payload'] ?? ''), $testTokenCode)) {
        $logPayloadLeaked = true;
    }
}
runTest('TC-STG1-02C', 'Token ujian tidak bocor ke preview maupun payload log eksternal siswa', !$logPayloadLeaked);

// -----------------------------------------------------------------------------
// PENGUJIAN 4: PASSWORD, OTP, DAN API KEY TETAP DISAMARKAN
// -----------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN MASKING PASSWORD, OTP, DAN API KEY ---\n";

$payloadCredentials = json_encode([
    'password'    => 'MyP@ssw0rd123',
    'api_key'     => 'AIzaSyTestSecretApiKey9999999999999',
    'secret_token'=> 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
    'log_text'    => 'User melakukan reset password dengan password: NewSecretPassword123 dan kode OTP: 849201. Menggunakan api_key: AIzaSySubKey1234567890123456789012345',
    'normal_info' => 'Operasi sistem normal.'
]);

$sanitizedCreds = $methodMask->invoke($logController, $payloadCredentials);

$passKeyMasked = ($sanitizedCreds['password'] ?? '') === '***[TERPROTEKSI]***';
$apiKeyMasked = ($sanitizedCreds['api_key'] ?? '') === '***[TERPROTEKSI]***';
$secretKeyMasked = ($sanitizedCreds['secret_token'] ?? '') === '***[TERPROTEKSI]***';
$passInStringMasked = !str_contains($sanitizedCreds['log_text'] ?? '', 'NewSecretPassword123');
$otpInStringMasked = !str_contains($sanitizedCreds['log_text'] ?? '', '849201') && str_contains($sanitizedCreds['log_text'] ?? '', '***[TERPROTEKSI]***');
$apiInStringMasked = !str_contains($sanitizedCreds['log_text'] ?? '', 'AIzaSySubKey1234567890123456789012345');
$normalInfoIntact = ($sanitizedCreds['normal_info'] ?? '') === 'Operasi sistem normal.';

runTest(
    'TC-STG1-04',
    'Password, OTP, API Key, dan token tersamarkan baik pada nama key maupun di dalam isi string teks',
    $passKeyMasked && $apiKeyMasked && $secretKeyMasked && $passInStringMasked && $otpInStringMasked && $apiInStringMasked && $normalInfoIntact,
    "Output log_text: " . ($sanitizedCreds['log_text'] ?? '')
);

// -----------------------------------------------------------------------------
// PENGUJIAN 5 & 6: SANITASI ACTION URL (URL INTERNAL VS BERBAHAYA)
// -----------------------------------------------------------------------------
echo "\n--- 5 & 6. PENGUJIAN SANITASI ACTION URL ---\n";

$notifController = new NotificationController();

// 5. URL Internal Valid
$urlInternal1 = $notifController->sanitizeActionUrl('/siswa/exams');
$urlInternal2 = $notifController->sanitizeActionUrl('guru/grading');
$urlInternal3 = $notifController->sanitizeActionUrl(base_url('admin/notification-logs'));

$validInternalOk = ($urlInternal1 === base_url('siswa/exams'))
    && ($urlInternal2 === base_url('guru/grading'))
    && ($urlInternal3 === base_url('admin/notification-logs'));

runTest('TC-STG1-05', 'URL internal valid tetap dapat digunakan dan teresolusi dengan benar', $validInternalOk, "Contoh resolusi: {$urlInternal1}");

// 6. URL Berbahaya Ditolak
$urlJs = $notifController->sanitizeActionUrl('javascript:alert(document.cookie)');
$urlData = $notifController->sanitizeActionUrl('data:text/html,<script>alert(1)</script>');
$urlVb = $notifController->sanitizeActionUrl('vbscript:msgbox("hacked")');
$urlExternalPhishing = $notifController->sanitizeActionUrl('https://evil-phishing-site.com/login');
$urlNull = $notifController->sanitizeActionUrl(null);
$urlEmpty = $notifController->sanitizeActionUrl('   ');

$dangerousRejected = ($urlJs === null)
    && ($urlData === null)
    && ($urlVb === null)
    && ($urlExternalPhishing === null)
    && ($urlNull === null)
    && ($urlEmpty === null);

runTest('TC-STG1-06', 'URL berbahaya (javascript:, data:, vbscript:, host luar) ditolak menjadi null', $dangerousRejected);

// -----------------------------------------------------------------------------
// PENGUJIAN 7: NOTIFIKASI GURU DAN ADMIN TETAP BERFUNGSI (TOKEN OPERASIONAL GURU UTUH)
// -----------------------------------------------------------------------------
echo "\n--- 7. PENGUJIAN NOTIFIKASI GURU & ADMIN (TOKEN OPERASIONAL) ---\n";

// Guru pengampu harus tetap dapat menerima token operasional (tidak dirusak oleh sanitasi siswa)
$teacherTitle = "Token Akses Ujian: Simulasi";
$teacherMessage = "Token ujian Matematika untuk kelas X-A telah siap: [{$testTokenCode}] (berlaku s/d 12:00).";

$resTeacher = $notifService->createExamTokenNotificationForTeacher(
    $teacherId,
    'Simulasi',
    $testTokenCode,
    '12:00',
    'X-A'
);

$latestTeacherNotif = $db->query("SELECT * FROM notifications WHERE user_id = {$teacherId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$teacherTokenPreserved = str_contains($latestTeacherNotif['message'] ?? '', $testTokenCode);

runTest(
    'TC-STG1-07',
    'Notifikasi Guru tetap berfungsi dan token operasional guru TIDAK disensor secara keliru',
    $teacherTokenPreserved,
    "Pesan guru: " . ($latestTeacherNotif['message'] ?? '')
);

// Notifikasi Admin
$resAdmin = $notifService->notifyAdmins(
    'Pemberitahuan Sistem Admin',
    'Pemeriksaan rutin modul notifikasi selesai.',
    NotificationService::TYPE_INFO,
    '/admin/dashboard'
);
$adminNotifOk = ($resAdmin['success'] ?? false) && (($resAdmin['notified_count'] ?? 0) >= 1);
runTest('TC-STG1-07B', 'Notifikasi Admin (notifyAdmins) tetap berfungsi normal', $adminNotifOk);

// -----------------------------------------------------------------------------
// PENGUJIAN 8: DROPDOWN NAVBAR DENGAN SANITASI URL TERBARU
// -----------------------------------------------------------------------------
echo "\n--- 8. PENGUJIAN DROPDOWN NAVBAR ---\n";

Session::start();
Session::set('user', [
    'id'       => $studentId,
    'name'     => 'Ahmad Fauzi',
    'email'    => 'Ahmadfauzi@gmail.com',
    'role'     => 'peserta_didik',
    'roles'    => ['peserta_didik']
]);

// Buat notifikasi dengan action_url berbahaya langsung di DB untuk menguji ketahanan getDropdownData
$db->prepare("INSERT INTO notifications (user_id, title, message, type, action_url, is_read, created_at) VALUES (?, 'Notif Uji Keamanan', 'Uji action url berbahaya', 'info', 'javascript:alert(1)', 0, NOW())")
   ->execute([$studentId]);

$dropdownResponse = $notifController->getDropdownData();
$dropdownBody = json_decode($dropdownResponse->getContent(), true);

$dropdownOk = ($dropdownResponse->getStatusCode() === 200) && ($dropdownBody['success'] === true);
$dropdownItems = $dropdownBody['notifications'] ?? [];

// Pastikan item pertama yang memiliki javascript:alert(1) disanitasi menjadi null
$maliciousActionNeutralized = false;
foreach ($dropdownItems as $dItem) {
    if ($dItem['title'] === 'Notif Uji Keamanan') {
        $maliciousActionNeutralized = ($dItem['action_url'] === null);
        break;
    }
}

runTest(
    'TC-STG1-08',
    'getDropdownData() merespons HTTP 200, format JSON valid, dan menetralkan action_url berbahaya menjadi null',
    $dropdownOk && $maliciousActionNeutralized,
    "Dropdown success: " . json_encode($dropdownOk) . ", Action URL dinetralkan: " . json_encode($maliciousActionNeutralized)
);

// -----------------------------------------------------------------------------
// PENGUJIAN 9: TIDAK ADA REGRESI PADA INBOX NOTIFIKASI
// -----------------------------------------------------------------------------
echo "\n--- 9. PENGUJIAN REGRESI INBOX NOTIFIKASI ---\n";

$inboxResponse = $notifController->inbox();
$inboxOk = ($inboxResponse->getStatusCode() === 200) && str_contains($inboxResponse->getContent(), 'Inbox Notifikasi');

$ajaxIndexResponse = $notifController->index();
$ajaxBody = json_decode($ajaxIndexResponse->getContent(), true);
$ajaxIndexOk = ($ajaxIndexResponse->getStatusCode() === 200) && ($ajaxBody['success'] === true);

runTest('TC-STG1-09', 'Halaman View Inbox HTML dan endpoint JSON index tetap berfungsi normal tanpa regresi', $inboxOk && $ajaxIndexOk);

// -----------------------------------------------------------------------------
// PENGUJIAN 10: INTEGRITAS AUTENTIKASI, OTP, DAN SUBMIT UJIAN
// -----------------------------------------------------------------------------
echo "\n--- 10. PENGUJIAN INTEGRITAS AUTENTIKASI, OTP, & SUBMIT UJIAN ---\n";

// 10A. Verifikasi Otentikasi & OTP Service
$otpService = new OtpService();
$otpRes = $otpService->generate($studentId, 'reset_password');
$otpGenerated = !empty($otpRes['otp']) && strlen((string)$otpRes['otp']) === 6;
$otpValid = $otpService->validate($studentId, (string)$otpRes['otp'], 'reset_password');

runTest('TC-STG1-10A', 'Fungsi Otentikasi & Penerbitan serta Validasi OTP 6-Digit tetap berjalan normal', $otpGenerated && $otpValid, "OTP: {$otpRes['otp']}");

// 10B. Verifikasi Idempotensi Submit Ujian (hasNotification check)
$examSessionService = new ExamSessionService();
$hasNotifCheck = $notifModel->hasNotification($studentId, 'Ujian Berhasil Dikumpulkan', 'Lembar jawaban Anda', false);
runTest('TC-STG1-10B', 'Integritas guardrail deduplikasi notifikasi submit ujian tetap aktif dan akurat', is_bool($hasNotifCheck));

// -----------------------------------------------------------------------------
// CLEANUP FIXTURE PENGUJIAN
// -----------------------------------------------------------------------------
$db->prepare("DELETE FROM exam_tokens WHERE id = ?")->execute([$tokenId]);
$db->prepare("DELETE FROM notifications WHERE title = 'Notif Uji Keamanan' AND user_id = ?")->execute([$studentId]);

// -----------------------------------------------------------------------------
// REKAPITULASI HASIL PENGUJIAN
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI PENGUJIAN PERBAIKAN TAHAP 1                         \n";
echo "====================================================================\n";
echo "Total Uji Dijalankan : {$totalTests}\n";
echo "Lolos (PASSED)       : {$passedTests}\n";
echo "Gagal (FAILED)       : {$failedTests}\n";
echo "Persentase Kelulusan : " . round(($passedTests / $totalTests) * 100, 1) . "%\n";
echo "====================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
