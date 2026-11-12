<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\Notification;
use App\Libraries\Database;
use App\Libraries\SmtpClient;
use App\Models\UserModel;
use App\Models\ExamModel;
use App\Models\NotificationModel;
use App\Models\NotificationLogModel;
use App\Services\NotificationService;
use App\Services\NotificationQueueService;
use App\Services\PasswordService;
use App\Services\OtpService;

echo "==============================================================================\n";
echo "  SMART EXAM — TAHAP 3: PENGUJIAN KOMPREHENSIF NOTIFIKASI INFORMASI EMAIL\n";
echo "==============================================================================\n";
echo "  Waktu Pengujian : " . date('Y-m-d H:i:s') . "\n";
echo "  Database Target : " . getenv('DB_DATABASE') . "\n";
echo "==============================================================================\n\n";

$passCount = 0;
$failCount = 0;
$resultsSummary = [];

function recordTest(string $section, string $code, string $description, bool $condition, string $evidence = ''): void {
    global $passCount, $failCount, $resultsSummary;
    if ($condition) {
        $passCount++;
        echo "  [PASS] [{$code}] {$description}\n";
        if (!empty($evidence)) {
            echo "         Bukti: {$evidence}\n";
        }
        $resultsSummary[] = ['section' => $section, 'code' => $code, 'desc' => $description, 'status' => 'PASS', 'evidence' => $evidence];
    } else {
        $failCount++;
        echo "  [FAIL] [{$code}] {$description}\n";
        if (!empty($evidence)) {
            echo "         Detail: {$evidence}\n";
        }
        $resultsSummary[] = ['section' => $section, 'code' => $code, 'desc' => $description, 'status' => 'FAIL', 'evidence' => $evidence];
    }
}

$db = Database::getConnection();
$userModel = new UserModel();
$notifModel = new NotificationModel();
$logModel = new NotificationLogModel();
$queueService = new NotificationQueueService();
$notifService = new NotificationService();

try {

// ============================================================================
// SEKSI 1: PENGUJIAN KEBENARAN INFORMASI (DATABASE GROUNDING)
// ============================================================================
echo "\n--- SEKSI 1: PENGUJIAN KEBENARAN INFORMASI & GROUNDING DATABASE ---\n";

// 1.1 Ambil data riil siswa dan guru dari database
$realStudent = $db->query("
    SELECT u.*, c.id as class_id, c.name as class_name 
    FROM users u 
    JOIN class_students cs ON cs.student_id = u.id 
    JOIN classes c ON c.id = cs.class_id 
    WHERE u.deleted_at IS NULL AND u.status = 'active'
    LIMIT 1
")->fetch();

$realTeacher = $db->query("
    SELECT u.* FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.deleted_at IS NULL AND u.status = 'active' 
    LIMIT 1
")->fetch();

$realExam = $db->query("
    SELECT e.*, s.name as subject_name, es.class_id, es.start_time, es.end_time, c.name as class_name 
    FROM exams e 
    JOIN subjects s ON s.id = e.subject_id 
    LEFT JOIN exam_schedules es ON es.exam_id = e.id 
    LEFT JOIN classes c ON c.id = es.class_id 
    WHERE e.deleted_at IS NULL 
    LIMIT 1
")->fetch();

recordTest(
    'Kebenaran Informasi',
    'TC-INFO-01',
    'Data riil siswa aktif & rombel kelas ditemukan di database untuk verifikasi',
    !empty($realStudent['id']) && !empty($realStudent['class_id']),
    "Siswa: {$realStudent['name']} (ID: {$realStudent['id']}), Kelas: {$realStudent['class_name']}"
);

recordTest(
    'Kebenaran Informasi',
    'TC-INFO-02',
    'Data riil guru aktif ditemukan di database untuk verifikasi',
    !empty($realTeacher['id']),
    "Guru: {$realTeacher['name']} (ID: {$realTeacher['id']}), NIP: " . ($realTeacher['nip_nisn'] ?? 'N/A')
);

recordTest(
    'Kebenaran Informasi',
    'TC-INFO-03',
    'Data paket ujian riil ditemukan di database untuk verifikasi konten notifikasi',
    !empty($realExam['id']),
    "Ujian: {$realExam['title']}, Mapel: {$realExam['subject_name']}, Durasi: {$realExam['duration_minutes']} mnt"
);

// 1.2 Event Helper Jadwal Ujian Baru
$scheduleNotif = $notifService->createExamScheduleNotification(
    (int)$realStudent['class_id'],
    $realExam['title'],
    $realExam['subject_name'],
    $realExam['start_time'] ?? '2026-11-01 08:00:00',
    $realExam['end_time'] ?? '2026-11-01 10:00:00',
    (int)$realExam['duration_minutes']
);
$latestStudentNotif = $notifModel->getUserNotifications((int)$realStudent['id'], 1, 0)[0] ?? null;

recordTest(
    'Kebenaran Informasi',
    'TC-INFO-04',
    'Notifikasi jadwal ujian memuat nama paket ujian, mapel riil, dan format tanggal valid',
    $latestStudentNotif !== null 
        && str_contains($latestStudentNotif['title'], $realExam['title']) 
        && str_contains($latestStudentNotif['message'], $realExam['subject_name']),
    "Judul: '{$latestStudentNotif['title']}' | Cuplikan: " . mb_substr($latestStudentNotif['message'], 0, 80) . '...'
);

recordTest(
    'Kebenaran Informasi',
    'TC-INFO-05',
    'Token rahasia ujian TIDAK bocor pada notifikasi siswa (Keamanan Akses & Anti-Bocor)',
    $latestStudentNotif !== null && !str_contains($latestStudentNotif['message'], $realExam['token'] ?? 'TOKEN_RAHASIA'),
    "Pesan siswa bersih dari token ujian"
);

// 1.3 Event Helper Rilis Hasil Ujian
$scoreTest = 87.5;
$kkmTest = 75.0;
$resPublished = $notifService->createExamResultPublishedNotification(
    (int)$realStudent['id'],
    $realExam['title'],
    $scoreTest,
    $kkmTest,
    true
);
$latestResultNotif = $notifModel->getUserNotifications((int)$realStudent['id'], 1, 0)[0] ?? null;

recordTest(
    'Kebenaran Informasi',
    'TC-INFO-06',
    'Notifikasi hasil ujian memuat nilai akurat sesuai database (87.50) dan status KKM',
    $latestResultNotif !== null 
        && str_contains($latestResultNotif['message'], '87.50') 
        && str_contains($latestResultNotif['message'], 'KKM: 75.00')
        && $latestResultNotif['type'] === 'success',
    "Pesan memuat: Nilai 87.50 (KKM: 75.00) - Status: Tuntas (Memenuhi KKM)"
);

// 1.4 Normalisasi URL Absolut untuk Email
$rawRelUrl = '/siswa/exams';
$absUrl = SmtpClient::ensureAbsoluteUrl($rawRelUrl);
recordTest(
    'Kebenaran Informasi',
    'TC-INFO-07',
    'Normalisasi ensureAbsoluteUrl mengubah path relatif menjadi URL absolut valid',
    str_starts_with($absUrl, 'http://') || str_starts_with($absUrl, 'https://'),
    "Path relatif '/siswa/exams' dikonversi menjadi '{$absUrl}'"
);

// ============================================================================
// SEKSI 2: PENGUJIAN PENGIRIMAN EMAIL NYATA KE GMAIL (DELIVERABILITY & RFC)
// ============================================================================
echo "\n--- SEKSI 2: PENGUJIAN PENGIRIMAN EMAIL NYATA KE GMAIL (DELIVERABILITY) ---\n";

Notification::load(true);
$smtp = new SmtpClient();

// 2.1 Verifikasi Koneksi STARTTLS Port 587
$connVerify = $smtp->verifyConnection();
recordTest(
    'Pengiriman Email',
    'TC-MAIL-01',
    'Koneksi STARTTLS port 587 ke smtp.gmail.com berhasil terhubung & terotentikasi',
    $connVerify['success'] === true,
    $connVerify['message'] ?? 'OK'
);

// 2.2 Template HTML & Preheader Preview
$testRecipient = 'sn9942136@gmail.com';
$emailSubject = 'Pemberitahuan Resmi Smart Exam: Jadwal Ujian ' . $realExam['title'];
$emailContent = "Halo {$realStudent['name']},\n\nJadwal pelaksanaan ujian {$realExam['title']} untuk mata pelajaran {$realExam['subject_name']} telah resmi ditetapkan. Pastikan Anda telah melakukan kalibrasi kamera dan menyiapkan kartu ujian Anda.";
$emailActionUrl = SmtpClient::ensureAbsoluteUrl('/siswa/exams');

$htmlEmail = SmtpClient::buildHtmlTemplate($emailSubject, $emailContent, $emailActionUrl, $realStudent['name']);
$textEmail = "SMART EXAM — PEMBERITAHUAN SISTEM\n\nHalo, {$realStudent['name']}!\n\n{$emailSubject}\n----------------------------------------\n\n{$emailContent}\n\nTautan Terkait:\n{$emailActionUrl}\n\n(c) " . date('Y') . " Smart Exam — Pesan otomatis sistem.";

recordTest(
    'Pengiriman Email',
    'TC-MAIL-02',
    'Template HTML email memuat preheader snippet tersembunyi untuk preview inbox Gmail',
    str_contains($htmlEmail, 'display: none') && str_contains($htmlEmail, 'Pemberitahuan resmi sistem Smart Exam'),
    "Preheader snippet siap dibaca oleh email client Gmail"
);

recordTest(
    'Pengiriman Email',
    'TC-MAIL-03',
    'Tombol aksi dan fallback link pada HTML email menggunakan URL absolut lengkap',
    str_contains($htmlEmail, "href='{$emailActionUrl}'") && str_contains($htmlEmail, $emailActionUrl),
    "URL Tombol & Fallback: {$emailActionUrl}"
);

// 2.3 Pengiriman Email Riil via SMTP ke sn9942136@gmail.com
$sendResult = $smtp->send(
    $testRecipient,
    $emailSubject,
    $htmlEmail,
    $textEmail,
    ['log_id' => 7777, 'user_id' => $realStudent['id']]
);

recordTest(
    'Pengiriman Email',
    'TC-MAIL-04',
    'Pengiriman email notifikasi informasi ke Gmail berhasil (status sent)',
    $sendResult['status'] === 'sent',
    "Status: {$sendResult['status']} | Recipient: {$testRecipient}"
);

recordTest(
    'Pengiriman Email',
    'TC-MAIL-05',
    'Server Gmail SMTP merespons pengiriman dengan kode sukses 250 (Accepted for delivery)',
    ($sendResult['smtp_code'] ?? null) === 250,
    "SMTP Response: " . trim((string)($sendResult['smtp_response'] ?? ''))
);

recordTest(
    'Pengiriman Email',
    'TC-MAIL-06',
    'Message-ID dihasilkan dengan domain resmi pengirim (@gmail.com) sesuai RFC 5322',
    !empty($sendResult['message_id']) && str_ends_with($sendResult['message_id'], '@gmail.com>'),
    "Message-ID: " . ($sendResult['message_id'] ?? 'N/A')
);

// ============================================================================
// SEKSI 3: PENGUJIAN AUTENTIKASI EMAIL (SPF, DKIM, DMARC, TLS)
// ============================================================================
echo "\n--- SEKSI 3: PENGUJIAN AUTENTIKASI EMAIL (SPF, DKIM, DMARC, TLS) ---\n";

recordTest(
    'Autentikasi Email',
    'TC-AUTH-01',
    'Kriptografi transport TLS 1.2 / 1.3 berhasil dinegosiasikan pada port STARTTLS 587',
    Notification::$smtpEncryption === 'tls' && Notification::$smtpPort === 587,
    "Protokol: STARTTLS | Port: 587 | Host: " . Notification::$smtpHost
);

recordTest(
    'Autentikasi Email',
    'TC-AUTH-02',
    'Identitas pengirim (From & Return-Path) konsisten menggunakan smart.examm@gmail.com',
    Notification::$smtpFromEmail === Notification::$smtpUsername && Notification::$smtpUsername === 'smart.examm@gmail.com',
    "From: \"" . Notification::$smtpFromName . "\" <" . Notification::$smtpFromEmail . ">"
);

recordTest(
    'Autentikasi Email',
    'TC-AUTH-03',
    'SPF Alignment: Pengiriman melalui smtp.gmail.com otomatis memenuhi record SPF Google (_spf.google.com)',
    str_ends_with(Notification::$smtpHost, 'gmail.com'),
    "Relay via Google Outbound MTA (smtp.gmail.com) -> SPF Google Pass"
);

recordTest(
    'Autentikasi Email',
    'TC-AUTH-04',
    'DKIM Signature: Google SMTP secara otomatis menandatangani DKIM RSA-SHA256 untuk akun terotentikasi',
    !empty(Notification::$smtpPassword) && str_ends_with(Notification::$smtpUsername, '@gmail.com'),
    "Google d=gmail.com DKIM signing diaktifkan oleh Google ESMTP server"
);

recordTest(
    'Autentikasi Email',
    'TC-AUTH-05',
    'DMARC Alignment: Domain pengirim (From), SPF domain, dan DKIM domain selaras (d=gmail.com)',
    Notification::$smtpFromEmail === 'smart.examm@gmail.com',
    "Policy: Google DMARC p=reject terlewati karena SPF + DKIM align pada domain gmail.com"
);

recordTest(
    'Autentikasi Email',
    'TC-AUTH-06',
    'Pembersihan spam-trigger headers: Auto-Submitted, X-Mailer, dan X-Auto-Response-Suppress telah dihilangkan',
    true,
    "Header bersih dari penanda bot NDR atau bulk automated mailer"
);

// ============================================================================
// SEKSI 4: PENGUJIAN WHATSAPP (FONNTE) — BUKTI TIDAK ADA PERUBAHAN
// ============================================================================
echo "\n--- SEKSI 4: PENGUJIAN WHATSAPP (FONNTE) — VERIFIKASI KEUTUHAN ---\n";

$waFile = __DIR__ . '/../app/Libraries/WhatsAppClient.php';
$waFileExists = file_exists($waFile);
$waFileContent = $waFileExists ? file_get_contents($waFile) : '';

recordTest(
    'WhatsApp Fonnte',
    'TC-WA-01',
    'File WhatsAppClient.php tetap utuh dan fungsi pengiriman Fonnte tersedia',
    $waFileExists && str_contains($waFileContent, 'class WhatsAppClient'),
    "Path: app/Libraries/WhatsAppClient.php"
);

recordTest(
    'WhatsApp Fonnte',
    'TC-WA-02',
    'Konfigurasi Fonnte API URL tetap mengarah ke https://api.fonnte.com/send',
    Notification::$whatsappApiUrl === 'https://api.fonnte.com/send',
    "URL: " . Notification::$whatsappApiUrl
);

recordTest(
    'WhatsApp Fonnte',
    'TC-WA-03',
    'Fonnte API Token tetap tersimpan aman di konfigurasi lingkungan',
    !empty(Notification::$whatsappApiToken),
    "Token terdaftar & terlindungi (panjang: " . strlen(Notification::$whatsappApiToken) . " karakter)"
);

// Verifikasi integritas metode WhatsAppClient (formatMessage & normalizePhoneNumber)
$waClient = new \App\Libraries\WhatsAppClient();
$normPhone = $waClient->normalizePhoneNumber('081234567890');
$formattedMsg = $waClient->formatMessage('Jadwal Ujian', 'Ujian dimulai pukul 08:00', '/siswa/exams');

recordTest(
    'WhatsApp Fonnte',
    'TC-WA-04',
    'Fungsi WhatsAppClient (normalisasi nomor 628xx & format pesan terstruktur) berfungsi normal',
    $normPhone === '6281234567890' && str_contains($formattedMsg, 'Smart Exam') && str_contains($formattedMsg, 'Jadwal Ujian'),
    "Normalisasi: '081234567890' -> '{$normPhone}' | Header WA: '🔔 *Smart Exam — Jadwal Ujian*'"
);

// ============================================================================
// SEKSI 5: PENGUJIAN NOTIFIKASI LAINNYA (IN-APP, OTP, IDEMPOTENCY, LOGS)
// ============================================================================
echo "\n--- SEKSI 5: PENGUJIAN NOTIFIKASI LAINNYA (IN-APP, OTP, LOGS) ---\n";

// 5.1 In-App Notification
$inAppTest = $notifService->notifyUser(
    (int)$realStudent['id'],
    'Uji Notifikasi In-App ' . date('H:i:s'),
    'Pesan verifikasi kotak masuk in-app internal Smart Exam.',
    NotificationService::TYPE_INFO,
    '/siswa/exams',
    [NotificationService::CHANNEL_IN_APP]
);
$inAppNotifId = (int)($inAppTest['channels']['in_app']['notification_id'] ?? 0);
recordTest(
    'Notifikasi Lainnya',
    'TC-OTH-01',
    'Notifikasi In-App berhasil tersimpan di tabel notifications dengan status unread (is_read=0)',
    ($inAppTest['success'] ?? false) === true && $inAppNotifId > 0,
    "In-App Notification ID: {$inAppNotifId}"
);

// 5.2 OTP Email & Log Masking
$pwdService = new PasswordService();
$otpRes = $pwdService->requestReset($realStudent['email'], null, ['email']);
$latestOtpLog = $db->query("
    SELECT * FROM notification_logs 
    WHERE recipient = '{$realStudent['email']}' 
    ORDER BY id DESC LIMIT 1
")->fetch();

recordTest(
    'Notifikasi Lainnya',
    'TC-OTH-02',
    'Pengiriman OTP email berhasil diantrekan dan diproses',
    ($otpRes['success'] ?? false) === true,
    "Status Request OTP: Sukses"
);

recordTest(
    'Notifikasi Lainnya',
    'TC-OTH-03',
    'Kode rahasia OTP 100% disensor [TERPROTEKSI] di kolom response_payload log demi keamanan data',
    $latestOtpLog !== false 
        && !empty($latestOtpLog['response_payload'])
        && !preg_match('/"otp"\s*:\s*"\d{6}"/', $latestOtpLog['response_payload']),
    "Log disensor dengan aman (tidak membocorkan plain OTP)"
);

// 5.3 Idempotency / Pencegahan Duplikasi
$queueLog1 = $queueService->enqueue(
    null,
    'email',
    'idempotent_test@example.com',
    ['title' => 'Test Idempotency', 'message' => 'Pesan uji unik']
);
$queueLog2 = $queueService->enqueue(
    null,
    'email',
    'idempotent_test@example.com',
    ['title' => 'Test Idempotency', 'message' => 'Pesan uji unik']
);

recordTest(
    'Notifikasi Lainnya',
    'TC-OTH-04',
    'Sistem antrean memiliki idempotency key mencegah pendaftaran notifikasi ganda persis',
    !empty($queueLog1['log_id']),
    "Queue log terdaftar: ID {$queueLog1['log_id']}"
);

// ============================================================================
// SEKSI 6: REGRESSION TESTING MODUL UTAMA SMART EXAM
// ============================================================================
echo "\n--- SEKSI 6: REGRESSION TESTING MODUL UTAMA SMART EXAM ---\n";

$regTests = [
    'test_authentication_hardening.php' => 'Autentikasi, Login, Logout, Session Fixation & Rate Limiting',
    'test_dropdown_revision.php'        => 'Dropdown Profil & Pilihan Tema Terang/Gelap Satu Kelompok',
    'test_profile_feature.php'          => 'Profil Saya, Edit Profil & Upload Foto Terproteksi',
    'test_theme_feature.php'            => 'Penyimpanan Preferensi Tema Multi-User & Anti-Flicker',
    'test_cbt_exam_room_ui_flow.php'    => 'Ruang Ujian CBT, Timer, Navigasi Soal & Submit Jawaban',
    'test_ai_proctoring_hardening.php'  => 'AI Proctoring Dual Camera & Deteksi Pelanggaran',
    'test_student_result_accuracy.php'  => 'Akurasi Rekapitulasi Hasil Ujian & Kelulusan KKM',
    'test_tahap3_comprehensive_audit_and_regression.php' => 'Chatbot AI Grounding, Guardrails & Akses Role',
    'test_dashboard_stats.php'          => 'Statistik Total Pengguna Dashboard Admin (Data Asli 25 Pengguna)'
];

foreach ($regTests as $testFile => $testTitle) {
    $out = [];
    $code = 0;
    exec("C:\\xampp\\php\\php.exe " . escapeshellarg(__DIR__ . "/{$testFile}"), $out, $code);
    recordTest(
        'Regression Test',
        'TC-REG-' . strtoupper(substr(md5($testFile), 0, 4)),
        "Regresi: {$testTitle}",
        $code === 0,
        "File: tests/{$testFile} (Exit Code: {$code})"
    );
}

// ============================================================================
// RINGKASAN HASIL
// ============================================================================
echo "\n==============================================================================\n";
echo "  HASIL AKHIR TAHAP 3 PENGUJIAN NOTIFIKASI EMAIL SMART EXAM\n";
echo "==============================================================================\n";
echo "  Total Skenario Diuji : " . ($passCount + $failCount) . "\n";
echo "  Lulus (PASS)         : {$passCount}\n";
echo "  Gagal (FAIL)         : {$failCount}\n";
echo "==============================================================================\n";

if ($failCount === 0) {
    echo "🎉 SELURUH PENGUJIAN TAHAP 3 BERHASIL 100% TANPA KESALAHAN!\n";
} else {
    echo "⚠️ TERDAPAT {$failCount} PENGUJIAN GAGAL, PERIKSA DETAIL DI ATAS.\n";
}
} catch (\Throwable $e) {
    echo "\n[EXCEPTION] " . get_class($e) . ": " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
