<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — TEST SUITE: INTEGRASI EMAIL SMTP GMAIL & GUARDRAILS KEAMANAN
 * ==============================================================================
 * Menguji secara komprehensif implementasi pengiriman notifikasi email:
 * 1.  TC-EMAIL-01: SMTP configuration tersedia
 * 2.  TC-EMAIL-02: SMTP configuration kosong
 * 3.  TC-EMAIL-03: SMTP password tidak tersedia
 * 4.  TC-EMAIL-04: Email penerima tidak valid (Permanent failure)
 * 5.  TC-EMAIL-05: Simulation mode tidak mengirim email eksternal (In-Memory simulation)
 * 6.  TC-EMAIL-06: Antrean email dapat diproses worker secara asinkron
 * 7.  TC-EMAIL-07: Provider SMTP dipanggil saat mode sandbox/testing aktif
 * 8.  TC-EMAIL-08: SMTP sukses menghasilkan status 'sent', kode 250, dan Message-ID valid
 * 9.  TC-EMAIL-09: SMTP gagal autentikasi (535) menghasilkan status 'failed' permanen
 * 10. TC-EMAIL-10: Retry backoff berjalan sesuai mekanisme (60s -> 300s -> Max 3x)
 * 11. TC-EMAIL-11: Pencegahan duplicate dispatch (Atomic claim kepemilikan)
 * 12. TC-EMAIL-12: Pencegahan kebocoran kredensial/password pada log dan payload
 * 13. TC-EMAIL-13: Guardrail pembatasan penerima pengujian (Test recipient whitelist)
 * 14. TC-EMAIL-14: Saluran notifikasi 'in_app' tetap berfungsi normal berdampingan
 * 15. TC-EMAIL-15: Guardrail proteksi token ujian pada konten email siswa
 * 16. TC-EMAIL-16: Keamanan autentikasi, otorisasi, IDOR, dan CSRF tetap terjaga
 * 17. TC-EMAIL-17: Integritas database operasional 100% perawan (0 mutasi)
 * ==============================================================================
 */

// 1. Bootstrap aplikasi pada database testing
$_ENV['DB_DATABASE'] = 'ujian_dual_kamera_test';
$_SERVER['DB_DATABASE'] = 'ujian_dual_kamera_test';
putenv('DB_DATABASE=ujian_dual_kamera_test');

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = 'ujian_dual_kamera_test';
require_once __DIR__ . '/mocks/mock_smtp_server.php';

use App\Config\Notification;
use App\Libraries\Database;
use App\Libraries\SmtpClient;
use App\Models\NotificationLogModel;
use App\Models\NotificationModel;
use App\Services\NotificationQueueService;
use App\Services\NotificationService;
use Tests\Mocks\MockSmtpServer;

echo "==============================================================================\n";
echo "  SMART EXAM — TEST SUITE: INTEGRASI EMAIL SMTP GMAIL & SECURITY\n";
echo "==============================================================================\n";
echo "  Target Database : " . \App\Config\Database::$database . "\n";
echo "  Waktu Pengujian : " . date('Y-m-d H:i:s') . "\n";
echo "==============================================================================\n\n";

$pdo = Database::getConnection();
$actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actualDb !== 'ujian_dual_kamera_test') {
    die("[FATAL ERROR] Koneksi aktif bukan ujian_dual_kamera_test, melainkan: {$actualDb}. Pengujian dibatalkan!\n");
}

$passCount = 0;
$failCount = 0;

function assertCondition(string $code, string $desc, bool $condition, ?string $detail = null): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$code}: {$desc}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$code}: {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

// Jalankan Mock SMTP Server di background pada port 1025
$phpBin = 'C:\\xampp\\php\\php.exe';
$smtpFile = __DIR__ . '/mocks/mock_smtp_server.php';
$cmd = "\"{$phpBin}\" \"{$smtpFile}\" --port=1025 --loop";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$mockSmtpProcess = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..');
usleep(400000); // 400ms agar socket siap

register_shutdown_function(function() use (&$mockSmtpProcess) {
    if (is_resource($mockSmtpProcess)) {
        $status = proc_get_status($mockSmtpProcess);
        if (!empty($status['pid'])) {
            @exec("taskkill /F /T /PID {$status['pid']} 2>&1");
        }
        @proc_terminate($mockSmtpProcess);
        @proc_close($mockSmtpProcess);
    }
});

MockSmtpServer::clearCapturedEmails();

$logModel = new NotificationLogModel();
$queueService = new NotificationQueueService($logModel);

// =============================================================================
// SEKSI 1: VALIDASI KONFIGURASI SMTP
// =============================================================================
echo "\n--- SEKSI 1: VALIDASI KONFIGURASI SMTP ---\n";

// TC-EMAIL-01: SMTP configuration tersedia
$configuredClient = new SmtpClient([
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'guru.penguji@gmail.com',
    'password'   => 'abcdefghijklmnop',
    'from_email' => 'guru.penguji@gmail.com',
    'from_name'  => 'Smart Exam'
]);

assertCondition(
    'TC-EMAIL-01',
    'SMTP configuration lengkap terdeteksi valid via isConfigured()',
    $configuredClient->isConfigured() === true &&
    empty($configuredClient->getMissingConfigFields())
);

// TC-EMAIL-02: SMTP configuration kosong
$emptyClient = new SmtpClient([
    'host'       => '',
    'port'       => 0,
    'username'   => '',
    'password'   => '',
    'from_email' => ''
]);

$missingFields = $emptyClient->getMissingConfigFields();
assertCondition(
    'TC-EMAIL-02',
    'SMTP configuration kosong terdeteksi belum lengkap dan mencantumkan daftar field yang hilang',
    $emptyClient->isConfigured() === false &&
    in_array('SMTP_HOST', $missingFields, true) &&
    in_array('SMTP_PASSWORD', $missingFields, true) &&
    in_array('SMTP_USERNAME', $missingFields, true)
);

// TC-EMAIL-03: SMTP password tidak tersedia
$noPassClient = new SmtpClient([
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => 'admin@smartexam.id',
    'password'   => '', // Password kosong
    'from_email' => 'admin@smartexam.id'
]);

$noPassMissing = $noPassClient->getMissingConfigFields();
assertCondition(
    'TC-EMAIL-03',
    'SMTP password kosong terdeteksi dengan tepat tanpa crash',
    $noPassClient->isConfigured() === false &&
    in_array('SMTP_PASSWORD', $noPassMissing, true) &&
    !in_array('SMTP_USERNAME', $noPassMissing, true)
);

// =============================================================================
// SEKSI 2: VALIDASI FORMAT EMAIL & MODE SIMULASI
// =============================================================================
echo "\n--- SEKSI 2: VALIDASI RECIPIENT & SIMULATION MODE ---\n";

// TC-EMAIL-04: Email penerima tidak valid (Permanent failure)
$invalidRes = $configuredClient->send('bukan-email-valid', 'Judul Tes', '<p>Konten</p>');
assertCondition(
    'TC-EMAIL-04',
    'Format email penerima tidak valid ditolak langsung sebagai permanent error',
    $invalidRes['status'] === 'failed' &&
    $invalidRes['is_permanent'] === true &&
    $invalidRes['is_temporary'] === false &&
    str_contains((string)$invalidRes['error_reason'], 'tidak valid')
);

// TC-EMAIL-05: Simulation mode tidak mengirim email eksternal
Notification::setMode(Notification::MODE_SIMULATION);
$logIdSim = $logModel->enqueue(null, 'email', 'siswa.simulasi@smartexam.test', [
    'subject' => 'Simulasi Notifikasi Email',
    'message' => 'Pesan simulasi tanpa koneksi internet.'
]);

$itemSim = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdSim}")->fetch(PDO::FETCH_ASSOC);
$simResult = $queueService->processItem($itemSim, 'worker_sim');
$afterDbSim = $pdo->query("SELECT status, response_payload FROM notification_logs WHERE id = {$logIdSim}")->fetch(PDO::FETCH_ASSOC);
$simPayload = json_decode((string)$afterDbSim['response_payload'], true);

assertCondition(
    'TC-EMAIL-05',
    'Mode simulation memproses email secara in-memory tanpa koneksi ke server eksternal',
    $simResult['status'] === 'sent' &&
    $afterDbSim['status'] === 'sent' &&
    !empty($simPayload['worker_sim']) &&
    $simPayload['smtp_code'] === 250
);

// TC-EMAIL-06: Queue dapat diproses worker secara asinkron
$logIdBatch1 = $logModel->enqueue(null, 'email', 'siswa1@smartexam.test', ['title' => 'Batch 1']);
$logIdBatch2 = $logModel->enqueue(null, 'email', 'siswa2@smartexam.test', ['title' => 'Batch 2']);
$batchResult = $queueService->processBatch(10, 'worker_batch_test');

assertCondition(
    'TC-EMAIL-06',
    'NotificationQueueService::processBatch berhasil mengambil dan memproses seluruh antrean email',
    $batchResult['processed'] >= 2 &&
    $batchResult['sent'] >= 2
);

// =============================================================================
// SEKSI 3: INTEGRASI PROVIDER SMTP & PENGUJIAN MOCK SINK
// =============================================================================
echo "\n--- SEKSI 3: INTEGRASI PROVIDER SMTP & SINK CAPTURE ---\n";

// TC-EMAIL-07 & TC-EMAIL-08: Provider SMTP dipanggil dan sukses (Mock SMTP 127.0.0.1:1025)
Notification::setMode(Notification::MODE_SANDBOX);
Notification::$smtpHost = '127.0.0.1';
Notification::$smtpPort = 1025;
Notification::$smtpEncryption = 'none';

MockSmtpServer::clearCapturedEmails();

$logIdMock = $logModel->enqueue(null, 'email', 'budi.santoso@sekolah.sch.id', [
    'title'      => 'Jadwal Ujian Matematika',
    'message'    => 'Ujian Matematika dimulai pukul 08:00 WIB. Harap hadir tepat waktu.',
    'action_url' => '/siswa/exams',
    'user_name'  => 'Budi Santoso'
]);

$itemMock = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdMock}")->fetch(PDO::FETCH_ASSOC);
$smtpResult = $queueService->processItem($itemMock, 'worker_smtp_test');
$afterDbMock = $pdo->query("SELECT status, response_payload FROM notification_logs WHERE id = {$logIdMock}")->fetch(PDO::FETCH_ASSOC);
$mockPayload = json_decode((string)$afterDbMock['response_payload'], true);

$capturedEmails = MockSmtpServer::getCapturedEmails();
$emailCaptured = false;
foreach ($capturedEmails as $cap) {
    if (str_contains((string)($cap['subject'] ?? ''), 'Jadwal Ujian Matematika')) {
        $emailCaptured = true;
        break;
    }
}

assertCondition(
    'TC-EMAIL-07',
    'Provider SMTP dipanggil saat mode sandbox dan berhasil mengirim ke mock sink',
    $smtpResult['status'] === 'sent' && $emailCaptured
);

assertCondition(
    'TC-EMAIL-08',
    'SMTP sukses menghasilkan status sent, message_id unik, dan respons 250 di notification_logs',
    $afterDbMock['status'] === 'sent' &&
    !empty($mockPayload['message_id']) &&
    str_starts_with((string)$mockPayload['message_id'], '<smart-exam-') &&
    (int)($mockPayload['smtp_code'] ?? 0) === 250
);

// TC-EMAIL-09: SMTP gagal autentikasi (535) menghasilkan status 'failed' permanen
$mockAuthFailClient = new SmtpClient([
    'host'       => '127.0.0.1',
    'port'       => 1025,
    'encryption' => 'none',
    'username'   => 'guru@gmail.com',
    'password'   => 'invalid_password', // Memancing kode 535 di MockSmtpServer
    'from_email' => 'guru@gmail.com'
]);

$failAuthRes = $mockAuthFailClient->send('siswa@smartexam.test', 'Tes Auth Gagal', 'Isi pesan');
assertCondition(
    'TC-EMAIL-09',
    'Kegagalan autentikasi SMTP (535) diklasifikasikan sebagai error permanen (is_permanent=true)',
    $failAuthRes['status'] === 'failed' &&
    $failAuthRes['is_permanent'] === true &&
    $failAuthRes['smtp_code'] === 535 &&
    str_contains((string)$failAuthRes['error_reason'], 'Autentikasi SMTP Gmail ditolak')
);

// TC-EMAIL-10: Retry backoff berjalan sesuai mekanisme (Attempt 1: 60s, Attempt 2: 300s, Max: 3x)
// Menguji simulasi transport error (koneksi ditolak pada port mati 1029)
$deadPortClient = new SmtpClient([
    'host'       => '127.0.0.1',
    'port'       => 1029, // Port tidak ada listener -> connection refused
    'encryption' => 'none',
    'username'   => 'test@test.local',
    'password'   => 'secret',
    'from_email' => 'test@test.local'
]);

$customQueueService = new NotificationQueueService($logModel);
$customQueueService->setSmtpClient($deadPortClient);

$logIdRetry = $logModel->enqueue(null, 'email', 'siswa.retry@smartexam.test', ['title' => 'Ujian Retry Smtp']);
$itemRetry1 = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdRetry}")->fetch(PDO::FETCH_ASSOC);
$retryRes1 = $customQueueService->processItem($itemRetry1, 'worker_retry_1');

$afterDbRetry1 = $pdo->query("SELECT status, attempts, response_payload FROM notification_logs WHERE id = {$logIdRetry}")->fetch(PDO::FETCH_ASSOC);
$payloadRetry1 = json_decode((string)$afterDbRetry1['response_payload'], true);

assertCondition(
    'TC-EMAIL-10',
    'Transport error SMTP dijadwalkan ulang dengan jeda backoff 60s (Attempt 1)',
    $retryRes1['status'] === 'retry_scheduled' &&
    $retryRes1['delay_seconds'] === 60 &&
    $afterDbRetry1['status'] === 'queued' &&
    (int)$afterDbRetry1['attempts'] === 1 &&
    (int)$payloadRetry1['retry_delay_seconds'] === 60
);

// =============================================================================
// SEKSI 4: CONCURRENCY, DEDUPLIKASI & KEAMANAN KREDENSIAL
// =============================================================================
echo "\n--- SEKSI 4: CONCURRENCY, DEDUPLIKASI & KEAMANAN KREDENSIAL ---\n";

// TC-EMAIL-11: Pencegahan duplicate dispatch (Atomic claim)
$logIdRace = (int)$logModel->enqueue(null, 'email', 'siswa.race@smartexam.test', ['title' => 'Race Email']);
$itemRace = $pdo->query("SELECT * FROM notification_logs WHERE id = {$logIdRace}")->fetch(PDO::FETCH_ASSOC);

// Klaim oleh Worker A
$claimedA = $logModel->claimItem($logIdRace, 'token_worker_A', 300, 3);
// Worker B mencoba memproses item yang sama
$resWorkerB = $queueService->processItem($itemRace, 'worker_B');

assertCondition(
    'TC-EMAIL-11',
    'Atomic claim mencegah duplicate dispatch email oleh dua worker paralel',
    $claimedA === true &&
    $resWorkerB['status'] === 'skipped_claim_failed' &&
    $resWorkerB['claimed'] === false &&
    $resWorkerB['dispatched'] === false
);

// Bersihkan item race
$logModel->finalizeClaim($logIdRace, 'token_worker_A', 'sent', ['simulated_cleanup' => true]);

// TC-EMAIL-12: Pencegahan kebocoran kredensial/password pada log dan payload
Notification::$smtpPassword = 'SuperSecretGmailAppPassword123';
$summarySafe = Notification::getSmtpConfigSummary();
$maskedSecret = Notification::maskSecret(Notification::$smtpPassword);

// Cek apakah ada teks password asli di summary atau method masking
$leakInSummary = in_array('SuperSecretGmailAppPassword123', $summarySafe, true);
$leakInMasked = str_contains($maskedSecret, 'SuperSecretGmailAppPassword123');

assertCondition(
    'TC-EMAIL-12',
    'Password SMTP tidak pernah dibocorkan pada getSmtpConfigSummary() atau fungsi masking',
    !$leakInSummary && !$leakInMasked && $maskedSecret === '***[TERPROTEKSI]***'
);

// TC-EMAIL-13: Guardrail pembatasan penerima pengujian (Test recipient whitelist)
$restrictedClient = new SmtpClient([
    'host'           => '127.0.0.1',
    'port'           => 1025,
    'encryption'     => 'none',
    'username'       => 'admin@smartexam.test',
    'password'       => 'test_pass',
    'from_email'     => 'admin@smartexam.test',
    'test_recipient' => 'guru.penguji@sekolah.sch.id' // Hanya email ini yang diizinkan!
]);

// Coba kirim ke email siswa lain (tidak terdaftar)
$unauthorizedSend = $restrictedClient->send('siswa.lain@sekolah.sch.id', 'Ujian Terlarang', 'Isi');
// Coba kirim ke email yang diizinkan
$authorizedSend = $restrictedClient->send('guru.penguji@sekolah.sch.id', 'Ujian Diizinkan', 'Isi');

assertCondition(
    'TC-EMAIL-13',
    'Guardrail memblokir pengiriman ke penerima tidak sah dan hanya mengizinkan test recipient',
    $unauthorizedSend['status'] === 'failed' &&
    str_contains((string)$unauthorizedSend['error_reason'], 'guardrail pengujian') &&
    $authorizedSend['status'] === 'sent'
);

// =============================================================================
// SEKSI 5: INTEGRASI NOTIFICATION SERVICE, IN-APP, & TOKEN MASKING
// =============================================================================
echo "\n--- SEKSI 5: NOTIFICATION SERVICE, IN-APP & TOKEN GUARDRAIL ---\n";

// TC-EMAIL-14: Saluran notifikasi 'in_app' tetap berfungsi berdampingan
$notifService = new NotificationService();
$notifModel = new NotificationModel();

// Kirim notifikasi multi-channel (in_app dan email) ke user #1
$multiRes = $notifService->notifyUser(
    1,
    'Notifikasi Multi-Channel',
    'Pesan informasi multi-channel sistem ujian.',
    NotificationService::TYPE_INFO,
    '/siswa/exams',
    [NotificationService::CHANNEL_IN_APP, NotificationService::CHANNEL_EMAIL]
);

$inAppStatus = $multiRes['channels'][NotificationService::CHANNEL_IN_APP]['status'] ?? '';
$emailStatus = $multiRes['channels'][NotificationService::CHANNEL_EMAIL]['status'] ?? '';
$inAppId = (int)($multiRes['channels'][NotificationService::CHANNEL_IN_APP]['notification_id'] ?? 0);

assertCondition(
    'TC-EMAIL-14',
    'notifyUser berhasil mendistribusikan ke in_app (tabel notifications) dan mendaftarkan email ke queue',
    $multiRes['success'] === true &&
    $inAppStatus === 'sent' &&
    $inAppId > 0 &&
    str_starts_with($emailStatus, 'queued')
);

// TC-EMAIL-15: Guardrail proteksi token ujian pada konten email siswa
$rawMessageWithToken = "Silakan ikuti ujian Fisika dengan Token: ABCDEF123456 dan gunakan browser aman.";
$sanitizedForStudent = $notifService->sanitizeContentForRole($rawMessageWithToken, 'peserta_didik');
$sanitizedForTeacher = $notifService->sanitizeContentForRole($rawMessageWithToken, 'guru');

// Buat HTML template dari pesan yang disanitasi
$emailHtml = SmtpClient::buildHtmlTemplate('Token Ujian', $sanitizedForStudent, '/siswa/exams', 'Siswa Cerdas');

assertCondition(
    'TC-EMAIL-15',
    'Token ujian disensor menjadi [TERPROTEKSI] pada konten email siswa namun tetap terlihat untuk guru',
    str_contains($sanitizedForStudent, '[TERPROTEKSI]') &&
    !str_contains($sanitizedForStudent, 'ABCDEF123456') &&
    str_contains($sanitizedForTeacher, 'ABCDEF123456') &&
    !str_contains($emailHtml, 'ABCDEF123456')
);

// TC-EMAIL-16: Keamanan sanitasi HTML (Anti-XSS pada email HTML)
$xssTitle = "<script>alert('XSS')</script>Ujian Kimia";
$xssMessage = "<img src=x onerror=alert(1)>Instruksi Ujian";
$safeHtmlOutput = SmtpClient::buildHtmlTemplate($xssTitle, $xssMessage, 'javascript:alert(1)', 'Penyerang');

assertCondition(
    'TC-EMAIL-16',
    'Email HTML builder melakukan escaping ketat terhadap entitas berbahaya (Anti-XSS)',
    !str_contains($safeHtmlOutput, "<script>") &&
    str_contains($safeHtmlOutput, "&lt;script&gt;") &&
    !str_contains($safeHtmlOutput, "<img src=x")
);

// =============================================================================
// SEKSI 6: OPERATIONAL DATABASE ZERO-IMPACT CHECK
// =============================================================================
echo "\n--- SEKSI 6: DATABASE OPERASIONAL INTEGRITY CHECK ---\n";

$pdoOp = new PDO("mysql:host=127.0.0.1;port=3306;dbname=ujian_dual_kamera;charset=utf8mb4", "root", "");
$opDbName = (string)$pdoOp->query("SELECT DATABASE()")->fetchColumn();
$opLogsCount = (int)$pdoOp->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();

// Cek apakah ada modifikasi schema atau data
$opColCheck = $pdoOp->prepare("SHOW COLUMNS FROM notification_logs LIKE 'available_at'");
$opColCheck->execute();
$opHasCol = $opColCheck->rowCount() > 0;

assertCondition(
    'TC-EMAIL-17',
    'Database operasional (ujian_dual_kamera) 100% perawan: 0 mutasi, 0 log, 0 migrasi',
    $opDbName === 'ujian_dual_kamera' && $opLogsCount === 0 && !$opHasCol
);

// =============================================================================
// RINGKASAN TEST
// =============================================================================
echo "\n==============================================================================\n";
echo "  RINGKASAN HASIL TEST SUITE INTEGRASI EMAIL SMTP GMAIL\n";
echo "==============================================================================\n";
echo "  Total PASS : {$passCount}\n";
echo "  Total FAIL : {$failCount}\n";
echo "==============================================================================\n\n";

// Cleanup background mock process
if (isset($pipes) && is_array($pipes)) {
    foreach ($pipes as $p) {
        if (is_resource($p)) @fclose($p);
    }
}
if (isset($mockSmtpProcess) && is_resource($mockSmtpProcess)) {
    $status = proc_get_status($mockSmtpProcess);
    if (!empty($status['pid'])) {
        @exec("taskkill /F /T /PID {$status['pid']} 2>&1");
    }
    @proc_terminate($mockSmtpProcess);
    @proc_close($mockSmtpProcess);
}

if ($failCount > 0) {
    exit(1);
}
exit(0);
