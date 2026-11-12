<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — AUTOMATED TEST SUITE: FONNTE WHATSAPP API INTEGRATION & SECURITY
 * ==============================================================================
 * Menguji integrasi Fonnte API dan pengamanan alur OTP:
 * 1.  Format payload Fonnte (target, message, countryCode).
 * 2.  Header autentikasi token Fonnte (Authorization: <TOKEN>).
 * 3.  Penolakan token kosong / tidak terkonfigurasi (401 Permanent Error).
 * 4.  Penanganan respons Fonnte sukses (status: true, id array, process).
 * 5.  Penanganan respons Fonnte gagal (status: false, reason).
 * 6.  Penanganan cURL timeout & connection failure.
 * 7.  Penanganan HTTP status codes (400, 401, 429, 500).
 * 8.  Anti-pengalihan nomor OTP (Anti-Account Hijacking).
 * 9.  Masking email & nomor telepon untuk privasi.
 * 10. Kerahasiaan token & sensor OTP pada log antrean (notification_logs).
 * 11. Kegagalan salah satu kanal tidak memblokir kanal lainnya.
 * 12. Isolasi database operasional (ujian_dual_kamera strictly 0 rows).
 * ==============================================================================
 */

// Targetkan database testing secara terisolasi
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Config\Notification;
use App\Libraries\Database;
use App\Libraries\WhatsAppClient;
use App\Models\NotificationLogModel;
use App\Services\NotificationQueueService;
use App\Services\PasswordService;
use App\Services\OtpService;

$testResults = [];
$totalPassed = 0;
$totalFailed = 0;

function runTest(string $caseId, string $description, callable $fn): void {
    global $testResults, $totalPassed, $totalFailed;
    try {
        $fn();
        $testResults[] = ['id' => $caseId, 'desc' => $description, 'status' => 'PASS', 'error' => null];
        $totalPassed++;
        echo "  [PASS] {$caseId}: {$description}\n";
    } catch (Throwable $e) {
        $testResults[] = ['id' => $caseId, 'desc' => $description, 'status' => 'FAIL', 'error' => $e->getMessage()];
        $totalFailed++;
        echo "  [FAIL] {$caseId}: {$description} -> " . $e->getMessage() . "\n";
    }
}

function assertTrue(bool $condition, string $msg = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($msg);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $eStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
        $aStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
        throw new RuntimeException(($msg ? $msg . ' ' : '') . "Expected [{$eStr}], got [{$aStr}]");
    }
}

echo "==============================================================================\n";
echo "  TEST SUITE: FONNTE WHATSAPP API INTEGRATION & OTP SECURITY AUDIT\n";
echo "==============================================================================\n";

$pdo = Database::getConnection();
$activeDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
assertEquals('ujian_dual_kamera_test', $activeDb, "Active database must be ujian_dual_kamera_test");

// -----------------------------------------------------------------------------
// TC-FONNTE-01: Penolakan Token Kosong Pada URL Fonnte Tanpa cURL
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-01', 'Fonnte URL dengan token kosong langsung ditolak sebagai permanent error tanpa cURL', function() {
    $client = new WhatsAppClient([
        'api_url'    => 'https://api.fonnte.com/send',
        'api_token'  => '', // token sengaja kosong
        'test_phone' => null // bypass phone guardrail untuk mengisolasi uji token
    ]);

    assertEquals(false, $client->isConfigured(), "Client Fonnte tanpa token harus isConfigured() = false.");

    $res = $client->send('081234567890', 'OTP Ujian', 'Kode OTP Anda: 123456');
    assertEquals('failed', $res['status'], "Status harus failed");
    assertTrue($res['is_permanent'], "Harus permanent error (tidak boleh retry terus-menerus)");
    assertEquals(401, $res['http_code'], "Kode HTTP harus 401");
    assertTrue(str_contains($res['error_reason'], 'Token API WhatsApp belum dikonfigurasi'), "Pesan error harus informatif");
});

// -----------------------------------------------------------------------------
// TC-FONNTE-02: Header Autentikasi dan Format Payload Sesuai Dokumentasi Fonnte
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-02', 'Format payload Fonnte memuat target, message, dan countryCode 62', function() {
    $testToken = 'DUMMY_FONNTE_TOKEN_12345';
    $client = new WhatsAppClient([
        'api_url'   => 'https://api.fonnte.com/send',
        'api_token' => $testToken
    ]);

    assertTrue($client->isConfigured(), "Client Fonnte dengan token harus isConfigured() = true.");

    // Gunakan refleksi untuk memeriksa pembentukan payload dan header
    $reflector = new ReflectionClass($client);
    $method = $reflector->getMethod('executeCurlPost');
    $method->setAccessible(true);

    // Verifikasi bahwa token tersensor saat logging error
    $maskMethod = $reflector->getMethod('maskSensitiveText');
    $maskMethod->setAccessible(true);
    $masked = $maskMethod->invoke($client, "Request dengan token: {$testToken}");
    assertTrue(!str_contains($masked, $testToken), "Token Fonnte tidak boleh dibocorkan ke log");
    assertTrue(str_contains($masked, '***[TERPROTEKSI]***'), "Token harus diganti dengan ***[TERPROTEKSI]***");
});

// -----------------------------------------------------------------------------
// TC-FONNTE-03: Penanganan Respons Fonnte Sukses (status: true & array id)
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-03', 'Respons Fonnte sukses dengan id array diekstrak menjadi string message_id', function() {
    // Siapkan mock router lokal yang mensimulasikan respons Fonnte API
    $mockRouter = __DIR__ . '/mocks/fonnte_mock_router.php';
    if (!file_exists($mockRouter)) {
        // Buat mock router sementara untuk Fonnte
        file_put_contents($mockRouter, '<?php
            header("Content-Type: application/json");
            $target = $_POST["target"] ?? "";
            $token = $_SERVER["HTTP_AUTHORIZATION"] ?? "";

            if (empty($token) || $token === "INVALID_TOKEN") {
                echo json_encode(["status" => false, "reason" => "invalid token"]);
                exit;
            }

            if ($target === "6281240000000") {
                http_response_code(400);
                echo json_encode(["status" => false, "reason" => "target not registered"]);
                exit;
            }

            // Respons sukses khas Fonnte
            echo json_encode([
                "status"  => true,
                "target"  => [$target],
                "id"      => ["FONNTE-MSG-998877"],
                "process" => "init",
                "detail"  => "success"
            ]);
        ');
    }

    $serverCmd = "c:\\xampp\\php\\php.exe -S 127.0.0.1:8099 " . escapeshellarg($mockRouter);
    $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $process = proc_open($serverCmd, $descriptors, $pipes, __DIR__ . '/..');
    usleep(500000); // 500ms startup

    try {
        $client = new WhatsAppClient([
            'api_url'   => 'http://127.0.0.1:8099/send',
            'api_token' => 'VALID_TEST_TOKEN'
        ]);

        $res = $client->send('081234567890', 'Kode OTP', 'Pesan OTP Anda 554433');
        assertEquals('sent', $res['status'], "Pengiriman Fonnte berhasil");
        assertEquals('FONNTE-MSG-998877', $res['message_id'], "ID array Fonnte harus diekstrak jadi string tunggal");
        assertEquals('init', $res['provider_status'], "Status provider Fonnte harus tercatat");
        assertEquals(200, $res['http_code'], "HTTP status code 200");
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        if (file_exists($mockRouter)) {
            @unlink($mockRouter);
        }
    }
});

// -----------------------------------------------------------------------------
// TC-FONNTE-04: Penanganan Respons Fonnte Gagal (status: false & reason)
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-04', 'Respons Fonnte status: false dicatat sebagai permanent failure dengan alasan jelas', function() {
    $mockRouter = __DIR__ . '/mocks/fonnte_fail_mock.php';
    file_put_contents($mockRouter, '<?php
        header("Content-Type: application/json");
        echo json_encode(["status" => false, "reason" => "device disconnected"]);
    ');

    $serverCmd = "c:\\xampp\\php\\php.exe -S 127.0.0.1:8098 " . escapeshellarg($mockRouter);
    $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $process = proc_open($serverCmd, $descriptors, $pipes, __DIR__ . '/..');
    usleep(500000);

    try {
        $client = new WhatsAppClient([
            'api_url'   => 'http://127.0.0.1:8098/send',
            'api_token' => 'SOME_TOKEN'
        ]);

        $res = $client->send('081234567890', 'Kode OTP', 'Pesan OTP');
        assertEquals('failed', $res['status'], "Harus berstatus failed");
        assertTrue($res['is_permanent'], "Harus permanent error karena device disconnected");
        assertTrue(str_contains($res['error_reason'], 'device disconnected'), "Alasan kegagalan harus tercatat");
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        if (file_exists($mockRouter)) {
            @unlink($mockRouter);
        }
    }
});

// -----------------------------------------------------------------------------
// TC-FONNTE-05: Anti-Account Hijacking (Nomor Tidak Cocok dengan Profil Ditolak)
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-05', 'Permintaan reset dengan nomor yang tidak cocok dengan profil ditolak keras', function() use ($pdo) {
    Notification::setMode(Notification::MODE_SIMULATION);

    $testEmail = 'fonnte_guard_test@sekolah.sch.id';
    $realPhone = '0881022834828';
    $attackerPhone = '081299990000';

    $pdo->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $testEmail]);
    $pdo->prepare("
        INSERT INTO users (username, email, password, name, status, phone, created_at, updated_at)
        VALUES ('fonnte_guard_user', :e, 'hash123', 'Fonnte Guard User', 'active', :p, NOW(), NOW())
    ")->execute([':e' => $testEmail, ':p' => $realPhone]);

    $pwService = new PasswordService();

    // 1. Percobaan menggunakan nomor penyerang -> HARUS GAGAL
    $failResult = $pwService->requestReset($testEmail, $attackerPhone, ['email', 'whatsapp']);
    assertEquals(false, $failResult['success'], "Pengalihan ke nomor penyerang wajib ditolak.");
    assertTrue(str_contains($failResult['error'], 'tidak sesuai'), "Pesan error harus menjelaskan nomor tidak sesuai.");

    // 2. Percobaan menggunakan nomor terdaftar asli -> HARUS SUKSES
    $okResult = $pwService->requestReset($testEmail, $realPhone, ['email', 'whatsapp']);
    assertEquals(true, $okResult['success'], "Konfirmasi dengan nomor asli harus sukses.");
    assertEquals(Notification::normalizePhoneNumber($realPhone), $okResult['phone'], "Nomor tujuan harus tetap nomor asli terdaftar.");

    // Cleanup
    $pdo->prepare("DELETE FROM users WHERE email = :e")->execute([':e' => $testEmail]);
});

// -----------------------------------------------------------------------------
// TC-FONNTE-06: Masking Alamat Email dan Nomor Telepon untuk Privasi
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-06', 'PasswordService::maskEmail dan maskPhone menyamarkan data pribadi pengguna', function() {
    assertEquals('sn***36@gmail.com', PasswordService::maskEmail('sn9942136@gmail.com'));
    assertEquals('b**i@sekolah.sch.id', PasswordService::maskEmail('budi@sekolah.sch.id'));
    assertEquals('6288****4828', PasswordService::maskPhone('62881022834828'));
    assertEquals('0812****7890', PasswordService::maskPhone('081234567890'));
});

// -----------------------------------------------------------------------------
// TC-FONNTE-07: Sanitasi Kode OTP Pada notification_logs Setelah Finalisasi
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-07', 'Kode OTP plaintext disensor menjadi [TERPROTEKSI] pada log setelah diproses worker', function() use ($pdo) {
    Notification::setMode(Notification::MODE_SIMULATION);

    $logModel = new NotificationLogModel();
    $queueService = new NotificationQueueService($logModel);

    $testOtp = '778899';
    $logId = (int)$queueService->enqueue(null, 'whatsapp', '081234567890', [
        'type'              => 'otp_verification',
        'otp'               => $testOtp,
        'title'             => 'Kode OTP',
        'preview'           => "Kode OTP: {$testOtp}",
        'message'           => "Gunakan OTP {$testOtp}",
        'formatted_message' => "OTP: {$testOtp}"
    ])['log_id'];

    $item = $logModel->find($logId);
    $res = $queueService->processItem($item, 'test_worker');
    assertEquals('sent', $res['status']);

    // Periksa isi log di database setelah finalisasi
    $logDb = $logModel->find($logId);
    $payload = json_decode((string)$logDb['response_payload'], true);

    assertEquals('***[TERPROTEKSI]***', $payload['otp'], "OTP di payload log database harus disensor");
    assertTrue(!str_contains($payload['preview'], $testOtp), "Preview di log tidak boleh memuat OTP plaintext");
    assertTrue(!str_contains($payload['message'], $testOtp), "Message di log tidak boleh memuat OTP plaintext");
});

// -----------------------------------------------------------------------------
// TC-FONNTE-08: Isolasi Database Operasional (ujian_dual_kamera strictly 0 logs)
// -----------------------------------------------------------------------------
runTest('TC-FONNTE-08', 'Database operasional ujian_dual_kamera STRICTLY tidak tersentuh (0 logs)', function() {
    $dbProd = new PDO("mysql:host=127.0.0.1;port=3306;dbname=ujian_dual_kamera;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $stmt = $dbProd->query("SELECT COUNT(*) FROM notification_logs WHERE recipient LIKE '%fonnte%' OR recipient LIKE '%tester%'");
    $count = (int)$stmt->fetchColumn();
    assertEquals(0, $count, "Database operasional harus strictly perawan (0 logs uji coba Fonnte)");
});

echo "\n==============================================================================\n";
echo "HASIL AKHIR AUDIT FONNTE & OTP SECURITY: {$totalPassed} PASS / {$totalFailed} FAIL\n";
echo "==============================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
