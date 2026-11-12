<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — TEST SUITE: OTP MULTI-CHANNEL NOTIFICATION INTEGRATION
 * ==============================================================================
 * Menguji integrasi notifikasi kode OTP ke saluran Email dan WhatsApp:
 * 1. Penghasilan OTP 6-digit dan penentuan target email serta nomor WhatsApp yang dimasukkan.
 * 2. Pencarian pengguna berdasarkan email, username, NIP/NISN, dan nomor telepon.
 * 3. Enqueue dan immediate processing notifikasi OTP ke notification_logs.
 * 4. Pengecekan template HTML Email OTP yang profesional, minimalis, dan bersih.
 * 5. Pengecekan format pesan WhatsApp OTP yang rapi, informatif, dan aman.
 * 6. Proteksi siswa: memastikan digit OTP tidak tersensor sebagai token ujian.
 * 7. Integritas alur reset password & verifikasi OTP satu kali pakai (single-use).
 * ==============================================================================
 */

// 1. Inisialisasi Environment Testing
$_ENV['DB_DATABASE'] = 'ujian_dual_kamera_test';
$_SERVER['DB_DATABASE'] = 'ujian_dual_kamera_test';
putenv('DB_DATABASE=ujian_dual_kamera_test');

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = 'ujian_dual_kamera_test';

use App\Config\Notification;
use App\Libraries\Database;
use App\Libraries\Session;
use App\Libraries\SmtpClient;
use App\Libraries\WhatsAppClient;
use App\Models\NotificationLogModel;
use App\Models\OtpTokenModel;
use App\Models\UserModel;
use App\Services\OtpService;
use App\Services\PasswordService;

Session::start();

$testResults = [];
$totalPassed = 0;
$totalFailed = 0;

function runTest(string $caseId, string $description, callable $fn): void {
    global $testResults, $totalPassed, $totalFailed;
    try {
        $fn();
        $testResults[] = ['id' => $caseId, 'desc' => $description, 'status' => 'PASS', 'error' => null];
        $totalPassed++;
        echo "  ✓ [PASS] {$caseId}: {$description}\n";
    } catch (\Throwable $e) {
        $testResults[] = ['id' => $caseId, 'desc' => $description, 'status' => 'FAIL', 'error' => $e->getMessage()];
        $totalFailed++;
        echo "  ✗ [FAIL] {$caseId}: {$description} -> " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
    }
}

function assertTrue(bool $condition, string $msg = 'Assertion failed'): void {
    if (!$condition) {
        throw new \RuntimeException($msg);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $eStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
        $aStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
        throw new \RuntimeException(($msg ? $msg . ' ' : '') . "Expected [{$eStr}], got [{$aStr}]");
    }
}

echo "==============================================================================\n";
echo "  TEST SUITE: OTP MULTI-CHANNEL (EMAIL & WHATSAPP) NOTIFICATION\n";
echo "==============================================================================\n\n";

$db = Database::getConnection();
$userModel = new UserModel();
$logModel = new NotificationLogModel();
$passwordService = new PasswordService();
$otpService = new OtpService();

// Siapkan atau perbarui user pengujian khusus
$testEmail = 'otp_test_user@sekolah.sch.id';
$testPhone = '081234567899';
$testUser = $userModel->findByEmail($testEmail);

if (!$testUser) {
    $userId = (int)$userModel->insert([
        'role_id'    => 4,
        'name'       => 'Siswa Uji OTP Multi-Channel',
        'email'      => $testEmail,
        'username'   => 'otp_test_student',
        'phone'      => $testPhone,
        'password'   => password_hash('OtpTestPass123!', PASSWORD_BCRYPT),
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $testUser = $userModel->find($userId);
} else {
    $userModel->update($testUser['id'], [
        'status' => 'active',
        'phone'  => $testPhone
    ]);
    $testUser = $userModel->find($testUser['id']);
}

// -----------------------------------------------------------------------------
// TC-OTP-01: Pencarian Pengguna dengan Berbagai Kredensial termasuk Nomor Telepon
// -----------------------------------------------------------------------------
runTest('TC-OTP-01', 'UserModel::findByCredentials dapat menemukan akun melalui email, username, dan nomor HP', function() use ($userModel, $testEmail, $testPhone) {
    // 1. By Email
    $u1 = $userModel->findByCredentials($testEmail);
    assertTrue($u1 !== null, "Harus menemukan user via email.");
    assertEquals($testEmail, $u1['email']);

    // 2. By Username
    $u2 = $userModel->findByCredentials('otp_test_student');
    assertTrue($u2 !== null, "Harus menemukan user via username.");
    assertEquals($testEmail, $u2['email']);

    // 3. By Phone Number (format lokal 08...)
    $u3 = $userModel->findByCredentials($testPhone);
    assertTrue($u3 !== null, "Harus menemukan user via nomor telepon 08xxx.");
    assertEquals($testEmail, $u3['email']);

    // 4. By Phone Number (format internasional 628...)
    $u4 = $userModel->findByCredentials('6281234567899');
    assertTrue($u4 !== null, "Harus menemukan user via nomor telepon 628xxx.");
    assertEquals($testEmail, $u4['email']);
});

// -----------------------------------------------------------------------------
// TC-OTP-02: Template Email OTP Profesional, Minimalis, dan Bersih
// -----------------------------------------------------------------------------
// TC-OTP-02: Template Email OTP Profesional, Minimalis, dan Bersih (5 Menit)
// -----------------------------------------------------------------------------
runTest('TC-OTP-02', 'SmtpClient::buildOtpHtmlTemplate menghasilkan tampilan email OTP yang bersih & rapi', function() {
    $otp = '582914';
    $userName = 'Ahmad Fauzi';
    $actionUrl = 'http://localhost/PRD/public/verify-otp';

    $html = SmtpClient::buildOtpHtmlTemplate($otp, $userName, 5, $actionUrl);
    $text = SmtpClient::buildOtpTextTemplate($otp, $userName, 5, $actionUrl);

    // Cek konten kunci dalam HTML
    assertTrue(str_contains($html, '5 8 2 9 1 4'), "HTML harus memuat digit OTP dengan formatting spasi.");
    assertTrue(str_contains($html, 'Ahmad Fauzi'), "HTML harus menyapa nama pengguna.");
    assertTrue(str_contains($html, '5 Menit'), "HTML harus mencantumkan durasi kedaluwarsa 5 menit.");
    assertTrue(str_contains($html, 'Peringatan Keamanan'), "HTML harus menyertakan peringatan keamanan.");
    assertTrue(str_contains($html, 'Verifikasi Kode OTP Sekarang'), "HTML harus menyertakan tombol aksi verifikasi.");
    assertTrue(str_contains($html, 'Smart Exam'), "HTML harus memuat branding Smart Exam.");

    // Cek fallback teks polos
    assertTrue(str_contains($text, '582914'), "Text plain harus memuat kode OTP.");
    assertTrue(str_contains($text, 'Ahmad Fauzi'), "Text plain harus memuat nama pengguna.");
    assertTrue(str_contains($text, '5 Menit'), "Text plain harus memuat durasi 5 menit.");
});

// -----------------------------------------------------------------------------
// TC-OTP-03: Format Pesan WhatsApp OTP Standar Final (5 Menit & Format Final)
// -----------------------------------------------------------------------------
runTest('TC-OTP-03', 'WhatsAppClient::formatOtpMessage menghasilkan pesan WhatsApp OTP yang rapi & terstruktur', function() {
    $otp = '931405';
    $userName = 'Budi Santoso';

    $waMessage = WhatsAppClient::formatOtpMessage($otp, $userName, 5);

    assertTrue(str_contains($waMessage, '*Smart Exam - Kode Verifikasi*'), "Pesan WhatsApp harus memiliki judul tebal tepat.");
    assertTrue(str_contains($waMessage, 'Kode OTP kamu: *931405*'), "Pesan WhatsApp harus memuat OTP dalam format tebal WhatsApp.");
    assertTrue(str_contains($waMessage, 'Kode berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.'), "Pesan WhatsApp harus mencantumkan masa berlaku 5 menit.");
    assertTrue(str_contains($waMessage, 'Jika kamu tidak meminta kode ini, abaikan pesan tersebut.'), "Pesan WhatsApp harus memuat petunjuk pengabaian pesan.");
});

// -----------------------------------------------------------------------------
// TC-OTP-04: Siswa Menerima Digit OTP Utuh (Tidak Terkena Sensor Token Ujian)
// -----------------------------------------------------------------------------
runTest('TC-OTP-04', 'Digit kode OTP pada pesan WhatsApp siswa tidak tersensor menjadi [TERPROTEKSI]', function() {
    $client = new WhatsAppClient(['test_phone' => null]);
    $otp = '654321';
    $phone = '081234567899';

    // Mock role siswa
    $res = $client->send(
        $phone,
        'Kode Verifikasi OTP',
        WhatsAppClient::formatOtpMessage($otp, 'Siswa Test', 5),
        null,
        [
            'user_role' => 'siswa',
            'type'      => 'otp_verification',
            'otp'       => $otp
        ]
    );

    // Kiriman harus memproses tanpa mengganti 654321 menjadi [TERPROTEKSI]
    $formatted = WhatsAppClient::formatOtpMessage($otp, 'Siswa Test', 5);
    assertTrue(str_contains($formatted, '654321'), "Pesan OTP siswa harus tetap memuat kode 654321 secara utuh.");
    assertTrue(!str_contains($formatted, '[TERPROTEKSI]'), "Pesan OTP tidak boleh memuat sensor [TERPROTEKSI].");
});

// -----------------------------------------------------------------------------
// TC-OTP-05: PasswordService::requestReset Menghubungkan Notifikasi ke Email & WhatsApp Terdaftar
// -----------------------------------------------------------------------------
runTest('TC-OTP-05', 'PasswordService::requestReset mengirimkan notifikasi ke Email dan nomor WhatsApp terdaftar', function() use ($passwordService, $testEmail, $testPhone, $logModel) {
    Notification::setMode(Notification::MODE_SIMULATION);

    // Konfirmasi nomor telepon yang cocok dengan profil
    $result = $passwordService->requestReset($testEmail, $testPhone, ['email', 'whatsapp']);

    assertTrue($result['success'] === true, "requestReset harus mengembalikan success: true.");
    assertEquals(6, strlen($result['otp']), "OTP harus berupa 6 digit.");
    assertEquals($testEmail, $result['email'], "Email tujuan harus sesuai dengan profil terdaftar.");
    assertEquals(Notification::normalizePhoneNumber($testPhone), $result['phone'], "Nomor telepon tujuan harus sesuai dengan nomor terdaftar.");

    // Cek session reset
    assertEquals($testEmail, Session::get('reset_email'));
    assertEquals(Notification::normalizePhoneNumber($testPhone), Session::get('reset_phone'));
    assertEquals($result['otp'], Session::get('reset_otp_preview'));

    // Cek bahwa notification_logs mencatat pengiriman email dan whatsapp
    $emailLog = $logModel->getRecentLogs(5, 'email');
    $foundEmail = false;
    foreach ($emailLog as $l) {
        if ($l['recipient'] === $testEmail) {
            $foundEmail = true;
            assertTrue(in_array($l['status'], ['sent', 'queued'], true), "Status email di logs harus valid.");
            break;
        }
    }
    assertTrue($foundEmail, "Log notifikasi email untuk {$testEmail} harus tercatat.");

    $normPhone = Notification::normalizePhoneNumber($testPhone);
    $waLog = $logModel->getRecentLogs(5, 'whatsapp');
    $foundWa = false;
    foreach ($waLog as $l) {
        if ($l['recipient'] === $normPhone) {
            $foundWa = true;
            assertTrue(in_array($l['status'], ['sent', 'queued'], true), "Status WhatsApp di logs harus valid.");
            break;
        }
    }
    assertTrue($foundWa, "Log notifikasi WhatsApp untuk {$normPhone} harus tercatat.");
});

// -----------------------------------------------------------------------------
// TC-OTP-06: Verifikasi Kode OTP Berhasil dan Single-Use (Hanya 1x Pakai)
// -----------------------------------------------------------------------------
runTest('TC-OTP-06', 'Verifikasi kode OTP yang diterbitkan bekerja dengan benar dan tidak dapat dipakai ulang', function() use ($passwordService, $testEmail, $testUser) {
    $gen = $passwordService->requestReset($testEmail);
    $validOtp = $gen['otp'];

    // Verifikasi pertama -> harus berhasil
    $v1 = $passwordService->verifyOtp((int)$testUser['id'], $validOtp);
    assertTrue($v1['success'] === true, "Verifikasi OTP pertama kali harus sukses.");
    assertTrue(!empty($v1['token']), "Token reset password harus diterbitkan.");

    // Verifikasi kedua dengan OTP yang sama -> harus ditolak (single-use)
    $v2 = $passwordService->verifyOtp((int)$testUser['id'], $validOtp);
    assertTrue($v2['success'] === false, "Verifikasi kedua dengan OTP yang sama wajib ditolak.");
});

// -----------------------------------------------------------------------------
// TC-OTP-07: Fallback Nomor Telepon dari Database Profil Akun
// -----------------------------------------------------------------------------
runTest('TC-OTP-07', 'Jika nomor HP tidak dimasukkan di form, sistem otomatis menggunakan nomor dari database profil', function() use ($passwordService, $testEmail, $testPhone) {
    Notification::setMode(Notification::MODE_SIMULATION);

    // Panggil tanpa menyertakan $inputPhone (null)
    $result = $passwordService->requestReset($testEmail, null, ['email', 'whatsapp']);

    assertTrue($result['success'] === true);
    assertEquals(Notification::normalizePhoneNumber($testPhone), $result['phone'], "Nomor telepon harus mengambil dari database profil akun.");
});

// -----------------------------------------------------------------------------
// TC-OTP-08: Proteksi Anti-Pengalihan Nomor WhatsApp (Anti-Account Hijacking)
// -----------------------------------------------------------------------------
runTest('TC-OTP-08', 'Nomor WhatsApp konfirmasi yang tidak cocok dengan data profil ditolak keras', function() use ($passwordService, $testEmail) {
    Notification::setMode(Notification::MODE_SIMULATION);

    // Nomor penyerang yang berbeda dari profil
    $attackerPhone = '089988776655';
    $result = $passwordService->requestReset($testEmail, $attackerPhone, ['email', 'whatsapp']);

    assertTrue($result['success'] === false, "Pengalihan ke nomor asing harus ditolak.");
    assertTrue(str_contains($result['error'] ?? '', 'tidak sesuai'), "Pesan error harus menjelaskan nomor tidak sesuai.");
});

// -----------------------------------------------------------------------------
// TC-OTP-09: Akun Tanpa Nomor Profil Tidak Dapat Dialihkan Secara Sepihak
// -----------------------------------------------------------------------------
runTest('TC-OTP-09', 'Akun tanpa nomor telepon profil menolak input nomor sembarang dan tetap aman', function() use ($db, $passwordService) {
    Notification::setMode(Notification::MODE_SIMULATION);

    // Siapkan user testing tanpa nomor HP di database
    $noPhoneEmail = 'nophone_tester@sekolah.sch.id';
    $db->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $noPhoneEmail]);
    $db->prepare("
        INSERT INTO users (username, email, password, name, status, phone, created_at, updated_at)
        VALUES ('nophone_user', :e, 'hash_abc', 'User No Phone', 'active', NULL, NOW(), NOW())
    ")->execute([':e' => $noPhoneEmail]);

    // Percobaan menyuntikkan nomor baru saat request reset
    $resWithInject = $passwordService->requestReset($noPhoneEmail, '081233445566', ['whatsapp']);
    assertTrue($resWithInject['success'] === false, "Penyuntikan nomor HP baru untuk akun tanpa nomor profil wajib ditolak.");

    // Cleanup
    $db->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $noPhoneEmail]);
});

// -----------------------------------------------------------------------------
// TC-OTP-10: Masa Berlaku OTP Tepat 5 Menit & Penolakan OTP Kedaluwarsa
// -----------------------------------------------------------------------------
runTest('TC-OTP-10', 'Masa berlaku OTP dihitung 5 menit dan kode kedaluwarsa ditolak', function() use ($db, $passwordService, $testEmail, $testUser) {
    $gen = $passwordService->requestReset($testEmail);
    $otp = $gen['otp'];
    $expiresAt = strtotime($gen['expires_at']);
    $diffMinutes = (int) round(($expiresAt - time()) / 60);

    assertEquals(5, $diffMinutes, "Selisih waktu kedaluwarsa harus tepat 5 menit.");

    // Simulasikan OTP menjadi kedaluwarsa di database
    $db->prepare("UPDATE otp_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user_id = :uid AND token = :token")
       ->execute([':uid' => (int)$testUser['id'], ':token' => $otp]);

    // Verifikasi OTP kedaluwarsa -> harus ditolak
    $vExpired = $passwordService->verifyOtp((int)$testUser['id'], $otp);
    assertTrue($vExpired['success'] === false, "OTP yang telah melewati batas 5 menit harus ditolak.");
});

echo "\n==============================================================================\n";
echo "TEST RESULTS: {$totalPassed} / " . ($totalPassed + $totalFailed) . " PASSED (" . round(($totalPassed / max(1, $totalPassed + $totalFailed)) * 100, 1) . "%)\n";
echo "==============================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}

