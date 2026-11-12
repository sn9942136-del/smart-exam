<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Notification;
use App\Libraries\Database;
use App\Libraries\SmtpClient;
use App\Models\UserModel;
use App\Models\NotificationLogModel;
use App\Services\OtpService;
use App\Services\PasswordService;

echo "==============================================================================\n";
echo "  TEST SUITE: OPTIMALISASI EMAIL DELIVERABILITY & RFC COMPLIANCE\n";
echo "==============================================================================\n\n";

$pass = 0;
$fail = 0;

function assertEmailTest(bool $condition, string $label, string $detail = ''): void {
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo " [PASS] {$label}\n";
    } else {
        $fail++;
        echo " [FAIL] {$label}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

function assertEquals(mixed $expected, mixed $actual, string $label): void {
    assertEmailTest($expected === $actual, $label, "Diharapkan '{$expected}', didapatkan '{$actual}'");
}

// --------------------------------------------------------------------------
// 1. Audit Konfigurasi & Koneksi Gmail SMTP
// --------------------------------------------------------------------------
echo "--- 1. Koneksi & Handshake Gmail SMTP ---\n";
Notification::load(true);
$smtp = new SmtpClient();

assertEmailTest($smtp->isConfigured(), "Konfigurasi Gmail SMTP lengkap dan terisi");
assertEquals(587, Notification::$smtpPort, "Port SMTP menggunakan port STARTTLS standar (587)");
assertEquals('tls', Notification::$smtpEncryption, "Enkripsi SMTP menggunakan TLS (STARTTLS)");

$verify = $smtp->verifyConnection();
assertEmailTest($verify['success'], "Handshake STARTTLS dan autentikasi Gmail SMTP berhasil", $verify['message'] ?? '');

// --------------------------------------------------------------------------
// 2. Deliverability Headers & Struktur MIME RFC 5322 & RFC 3834
// --------------------------------------------------------------------------
echo "\n--- 2. Header Deliverability & Kepatuhan Standar RFC ---\n";

// Tangkap raw email yang dibuat oleh SmtpClient melalui reflection
$refClass = new ReflectionClass(SmtpClient::class);
$fromAddrProp = $refClass->getProperty('fromEmail');
$fromAddrProp->setAccessible(true);
$fromEmail = $fromAddrProp->getValue($smtp);

$htmlTemplate = SmtpClient::buildOtpHtmlTemplate('789123', 'Siswa Uji Coba', 5, 'https://smartexam.example.com/verify-otp');
$textTemplate = SmtpClient::buildOtpTextTemplate('789123', 'Siswa Uji Coba', 5, 'https://smartexam.example.com/verify-otp');

// Validasi Preheader Preview Text untuk Gmail
assertEmailTest(
    str_contains($htmlTemplate, 'display: none') && str_contains($htmlTemplate, 'Kode verifikasi Smart Exam Anda'),
    "HTML memuat hidden preheader snippet untuk Gmail Inbox preview"
);
assertEmailTest(
    str_contains($htmlTemplate, 'color-scheme') && str_contains($htmlTemplate, 'light dark'),
    "HTML memuat meta tag color-scheme untuk rendering mobile & desktop yang konsisten"
);

// Validasi Text Plain Fallback
assertEmailTest(
    str_contains($textTemplate, '789123') && str_contains($textTemplate, '5 Menit'),
    "Text plain memuat informasi lengkap dan simetris dengan versi HTML"
);

// --------------------------------------------------------------------------
// 3. Pengujian Pengiriman Email Nyata ke Test Recipient
// --------------------------------------------------------------------------
echo "\n--- 3. Pengujian Pengiriman Email Nyata ke Gmail ---\n";
$testRecipient = 'sn9942136@gmail.com';

$sendResult = $smtp->send(
    $testRecipient,
    'Uji Coba Deliverability Smart Exam - ' . date('H:i:s'),
    $htmlTemplate,
    $textTemplate,
    ['log_id' => 9999]
);

assertEmailTest(
    $sendResult['status'] === 'sent',
    "Email pengujian deliverability berhasil dikirim ke {$testRecipient}",
    $sendResult['error_reason'] ?? ''
);
assertEmailTest(
    ($sendResult['smtp_code'] ?? null) === 250,
    "Server Gmail SMTP merespons pengiriman dengan kode sukses 250 OK"
);
assertEmailTest(
    !empty($sendResult['message_id']) && str_contains($sendResult['message_id'], 'gmail.com'),
    "Message-ID memuat domain pengirim gmail.com yang sah ({$sendResult['message_id']})"
);

// --------------------------------------------------------------------------
// 4. Pengujian Alur OTP via PasswordService (End-to-End)
// --------------------------------------------------------------------------
echo "\n--- 4. Alur OTP End-to-End & Keamanan Log ---\n";

$userModel = new UserModel();
$testUser = $userModel->findByEmail($testRecipient);
if (!$testUser) {
    // Gunakan akun siswa pertama yang terdaftar
    $testUser = $userModel->first(['role_id' => 4]);
}

if ($testUser) {
    $passwordService = new PasswordService();
    $resetRes = $passwordService->requestReset($testUser['email'], null, ['email']);
    
    assertEmailTest($resetRes['success'], "Request reset password berhasil mengantrekan dan mengirim OTP");
    assertEmailTest(!empty($resetRes['otp']), "Kode OTP 6-digit berhasil dibuat");
    
    // Verifikasi log database: pastikan kode OTP TIDAK bocor dalam notification_logs
    $db = Database::getConnection();
    $lastLogStmt = $db->query("SELECT * FROM notification_logs WHERE channel = 'email' ORDER BY id DESC LIMIT 1");
    $lastLog = $lastLogStmt->fetch(PDO::FETCH_ASSOC);
    
    assertEmailTest(
        $lastLog && $lastLog['status'] === 'sent',
        "Notification log mencatat status pengiriman sebagai 'sent'"
    );
    assertEmailTest(
        $lastLog && str_contains($lastLog['response_payload'], '***[TERPROTEKSI]***'),
        "Notification log menyensor nilai rahasia OTP menjadi ***[TERPROTEKSI]***"
    );
    assertEmailTest(
        $lastLog && !str_contains($lastLog['response_payload'], $resetRes['otp']),
        "Kode OTP mentah sama sekali TIDAK tersimpan di kolom response_payload log"
    );
}

echo "\n==============================================================================\n";
echo "  HASIL AKHIR: {$pass} PASSED, {$fail} FAILED\n";
echo "==============================================================================\n";

if ($fail > 0) {
    exit(1);
}
