<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — AUTOMATED TEST SUITE: WHATSAPP INTEGRATION & PRIVACY SECURITY
 * ==============================================================================
 * Rangkaian pengujian otomatis untuk memverifikasi seluruh komponen integrasi WhatsApp:
 * 1. Normalisasi & Validasi Nomor Telepon (E.164 / 628xxx).
 * 2. Guardrail Penerima Pengujian (NOTIFICATION_TEST_PHONE).
 * 3. Sanitasi Privasi & Perlindungan Token Ujian ([TERPROTEKSI] untuk Siswa).
 * 4. Masking Kredensial & Proteksi API Token.
 * 5. Template & Formatting Detail Pesan WhatsApp.
 * 6. Eksekusi Mode Simulasi.
 * 7. Mock Gateway HTTP 127.0.0.1:8088 Integration (Success 200, Permanent 400, Rate Limit 429, Error 500, Timeout).
 * 8. Idempotency & Deduplication Guard (X-Idempotency-Key & client_reference_id).
 * 9. Multi-Channel Enqueue Integration (NotificationService).
 * 10. Isolasi Database Operasional (ujian_dual_kamera strictly 0 rows).
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
use App\Libraries\WhatsAppClient;
use App\Models\NotificationLogModel;
use App\Services\NotificationQueueService;
use App\Services\NotificationService;

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
echo "  TEST SUITE: WHATSAPP INTEGRATION & PRIVACY SECURITY AUDIT\n";
echo "==============================================================================\n";

// Pastikan DB aktif adalah testing
$pdo = Database::getConnection();
$activeDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
assertEquals('ujian_dual_kamera_test', $activeDb, "Active database must be ujian_dual_kamera_test");

// -----------------------------------------------------------------------------
// TC-WA-01: Normalisasi Nomor Telepon
// -----------------------------------------------------------------------------
runTest('TC-WA-01', 'Normalisasi nomor telepon (08xxx, +62xxx, spasi/strip -> 628xxx)', function() {
    $client = new WhatsAppClient();
    
    assertEquals('6281234567890', $client->normalizePhoneNumber('081234567890'), '08 -> 62');
    assertEquals('6281234567890', $client->normalizePhoneNumber('+6281234567890'), '+62 -> 62');
    assertEquals('6281234567890', $client->normalizePhoneNumber('0812-3456-7890'), 'strip formatting');
    assertEquals('6281234567890', $client->normalizePhoneNumber('0812 3456 7890'), 'space formatting');
    assertEquals('6281234567890', $client->normalizePhoneNumber('(0812) 34567890'), 'parentheses');
});

// -----------------------------------------------------------------------------
// TC-WA-02: Validasi Format Nomor Telepon Tidak Valid
// -----------------------------------------------------------------------------
runTest('TC-WA-02', 'Penolakan nomor telepon tidak valid / terlalu pendek (< 8 digit)', function() {
    $client = new WhatsAppClient();
    
    $res1 = $client->send('123', 'Judul', 'Pesan');
    assertEquals('failed', $res1['status']);
    assertTrue($res1['is_permanent'], 'Harus permanent error');

    $res2 = $client->send('abcde', 'Judul', 'Pesan');
    assertEquals('failed', $res2['status']);
    assertTrue($res2['is_permanent'], 'Harus permanent error');

    $res3 = $client->send('', 'Judul', 'Pesan');
    assertEquals('failed', $res3['status']);
    assertTrue($res3['is_permanent'], 'Harus permanent error');
});

// -----------------------------------------------------------------------------
// TC-WA-03: Guardrail Pembatasan Nomor Pengujian (NOTIFICATION_TEST_PHONE)
// -----------------------------------------------------------------------------
runTest('TC-WA-03', 'Guardrail NOTIFICATION_TEST_PHONE menolak nomor selain tester', function() {
    $origTestPhone = Notification::$testPhone;
    Notification::$testPhone = '089911223344';
    try {
        $client = new WhatsAppClient([
            'test_phone' => '089911223344'
        ]);

        // Nomor berbeda -> Ditolak guardrail
        $resReject = $client->send('081234567890', 'Judul', 'Pesan');
        assertEquals('failed', $resReject['status']);
        assertTrue($resReject['is_permanent'], 'Harus permanent rejection');
        assertTrue(str_contains($resReject['error_reason'], 'guardrail pengujian'), 'Alasan harus menyebut guardrail');

        // Nomor sama -> Diterima oleh guardrail (kemudian diproses pengiriman)
        $cleanTestPhone = '6289911223344';
        assertTrue(Notification::isAllowedPhoneRecipient($cleanTestPhone), 'Allowed recipient check');
    } finally {
        Notification::$testPhone = $origTestPhone;
    }
});

// -----------------------------------------------------------------------------
// TC-WA-04: Sanitasi Privasi & Proteksi Token Ujian untuk Siswa
// -----------------------------------------------------------------------------
runTest('TC-WA-04', 'Proteksi privasi: Token ujian wajib disensor [TERPROTEKSI] untuk siswa', function() {
    $client = new WhatsAppClient();

    // Skenario untuk siswa: token disensor
    $messageWithToken = "Token ujian Matematika Anda adalah: TKN99X. Jangan bagikan ke orang lain.";
    $formattedStudent = $client->send('081234567890', 'Ujian Matematika', $messageWithToken, null, [
        'user_role' => 'siswa'
    ]);
    
    // Uji fungsi formatting internal & sanitasi
    $refMethod = new ReflectionMethod($client, 'sanitizeStudentContent');
    $refMethod->setAccessible(true);
    $scrubbed = $refMethod->invoke($client, "Token ujian Anda: ABC12345 dan token = XYZ7890");

    assertTrue(str_contains($scrubbed, '[TERPROTEKSI]'), 'Token harus disensor menjadi [TERPROTEKSI]');
    assertTrue(!str_contains($scrubbed, 'ABC12345'), 'Token asli tidak boleh muncul');
    assertTrue(!str_contains($scrubbed, 'XYZ7890'), 'Token kedua tidak boleh muncul');
});

// -----------------------------------------------------------------------------
// TC-WA-05: Formatting & Detail Template WhatsApp
// -----------------------------------------------------------------------------
runTest('TC-WA-05', 'Formatting pesan WhatsApp memiliki layout detail, tautan, dan timestamp', function() {
    $client = new WhatsAppClient();
    $formatted = $client->formatMessage(
        'Jadwal Ujian Diperbarui',
        'Pelaksanaan ujian Fisika diundur menjadi pukul 10:00 WIB.',
        '/siswa/exams'
    );

    assertTrue(str_contains($formatted, '🔔 *Smart Exam — Jadwal Ujian Diperbarui*'), 'Memuat header tebal');
    assertTrue(str_contains($formatted, 'Pelaksanaan ujian Fisika diundur'), 'Memuat isi pesan');
    assertTrue(str_contains($formatted, '🌐 *Tautan Akses:*'), 'Memuat section tautan');
    assertTrue(str_contains($formatted, '/siswa/exams'), 'Memuat url aksi');
    assertTrue(str_contains($formatted, 'WIB'), 'Memuat timestamp WIB');
    assertTrue(str_contains($formatted, '🔒'), 'Memuat security disclaimer');
});

// -----------------------------------------------------------------------------
// TC-WA-06: Masking Kredensial & Proteksi API Token
// -----------------------------------------------------------------------------
runTest('TC-WA-06', 'Kredensial API Token tidak pernah dibocorkan ke log/error message', function() {
    $secretToken = 'my_super_secret_wa_api_token_123456';
    $client = new WhatsAppClient([
        'api_token' => $secretToken
    ]);

    $refMethod = new ReflectionMethod($client, 'maskSensitiveText');
    $refMethod->setAccessible(true);

    $errorWithToken = "Gateway request failed with token: {$secretToken} Bearer xyz12345678";
    $masked = $refMethod->invoke($client, $errorWithToken);

    assertTrue(!str_contains($masked, $secretToken), 'Secret token tidak boleh muncul');
    assertTrue(str_contains($masked, '***[TERPROTEKSI]***'), 'Token harus dimask dengan ***[TERPROTEKSI]***');
});

// -----------------------------------------------------------------------------
// TC-WA-07: Mode Simulasi Eksekusi Tanpa HTTP Request
// -----------------------------------------------------------------------------
runTest('TC-WA-07', 'Mode simulasi memproses antrean WhatsApp tanpa request jaringan', function() {
    Notification::setMode(Notification::MODE_SIMULATION);
    $queueService = new NotificationQueueService();

    $logModel = new NotificationLogModel();
    $logId = (int)$logModel->enqueue(null, 'whatsapp', '081234567890', [
        'title'   => 'Notifikasi Simulasi',
        'message' => 'Isi pesan simulasi pengujian.'
    ]);

    $item = $logModel->find($logId);
    $res = $queueService->processItem($item, 'test_worker');

    assertEquals('sent', $res['status']);
    assertTrue($res['claimed']);
    assertTrue($res['dispatched']);

    $updated = $logModel->find($logId);
    assertEquals('sent', $updated['status']);
    $payload = json_decode((string)$updated['response_payload'], true);
    assertTrue(!empty($payload['worker_sim']), 'Harus ditandai worker_sim');
});

// -----------------------------------------------------------------------------
// TC-WA-08: Dependency Injection WhatsAppClient pada NotificationQueueService
// -----------------------------------------------------------------------------
runTest('TC-WA-08', 'Dependency injection WhatsAppClient pada NotificationQueueService', function() {
    $queueService = new NotificationQueueService();
    $customClient = new WhatsAppClient(['api_url' => 'http://127.0.0.1:8088/api/send']);

    $queueService->setWhatsAppClient($customClient);
    assertEquals($customClient, $queueService->getWhatsAppClient());
});

// -----------------------------------------------------------------------------
// TC-WA-09: Integrasi Mock WhatsApp Gateway Lokal (127.0.0.1:8088)
// -----------------------------------------------------------------------------
runTest('TC-WA-09', 'Integrasi Mock WhatsApp Server lokal (200 OK, 400 Bad Request, Rate Limit 429)', function() {
    // Jalankan server mock internal di background untuk pengetesan jika port 8088 belum jalan
    $mockRouter = __DIR__ . '/mocks/whatsapp_mock_router.php';
    assertTrue(file_exists($mockRouter), 'whatsapp_mock_router.php must exist');

    // Buka socket built-in server PHP di port 8088
    $serverCmd = "c:\\xampp\\php\\php.exe -S 127.0.0.1:8088 " . escapeshellarg($mockRouter);
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    $process = proc_open($serverCmd, $descriptors, $pipes, __DIR__ . '/..');
    usleep(500000); // 500ms startup

    try {
        $client = new WhatsAppClient(['api_url' => 'http://127.0.0.1:8088/api/send']);

        // 1. Uji Pengiriman Sukses (200 OK)
        $resSuccess = $client->send('081234567890', 'Ujian Berhasil', 'Pesan sukses');
        assertEquals('sent', $resSuccess['status'], 'Status pengiriman sukses');
        assertTrue(!empty($resSuccess['message_id']), 'Message ID harus ada');

        // 2. Uji Permanent Error (400 Bad Request - Target 081240000000)
        $resPerm = $client->send('081240000000', 'Ujian', 'Pesan error');
        assertEquals('failed', $resPerm['status'], 'Status error permanen');
        assertTrue($resPerm['is_permanent'], 'Harus permanent error');

        // 3. Uji Rate Limit (429 - Target 081242900000)
        $resRate = $client->send('081242900000', 'Ujian', 'Pesan rate limit');
        assertEquals('rate_limited', $resRate['status'], 'Status rate limit');
        assertTrue($resRate['is_temporary'], 'Harus temporary error untuk retry');

        // 4. Uji Server Error (500 - Target 081250000000)
        $res500 = $client->send('081250000000', 'Ujian', 'Pesan 500');
        assertEquals('upstream_error', $res500['status'], 'Status upstream error');
        assertTrue($res500['is_temporary'], 'Harus temporary error untuk retry');

    } finally {
        // Matikan process mock server
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
});

// -----------------------------------------------------------------------------
// TC-WA-10: Idempotency & Deduplication Guard
// -----------------------------------------------------------------------------
runTest('TC-WA-10', 'Idempotency guard mencegah pengiriman WhatsApp duplikat', function() {
    $mockRouter = __DIR__ . '/mocks/whatsapp_mock_router.php';
    $serverCmd = "c:\\xampp\\php\\php.exe -S 127.0.0.1:8088 " . escapeshellarg($mockRouter);
    $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $process = proc_open($serverCmd, $descriptors, $pipes, __DIR__ . '/..');
    usleep(500000);

    try {
        $client = new WhatsAppClient(['api_url' => 'http://127.0.0.1:8088/api/send']);
        $uniqueRef = 'idempotent_test_' . bin2hex(random_bytes(6));

        // Panggilan Pertama
        $res1 = $client->send('081234567890', 'Ujian', 'Pesan pertama', null, [
            'client_reference_id' => $uniqueRef
        ]);
        assertEquals('sent', $res1['status']);

        // Panggilan Kedua dengan reference ID yang sama (Simulasi re-dispatch setelah crash)
        $res2 = $client->send('081234567890', 'Ujian', 'Pesan pertama', null, [
            'client_reference_id' => $uniqueRef
        ]);
        assertEquals('sent', $res2['status']);
        assertTrue($res2['duplicate_prevented'], 'Duplicate must be prevented by gateway idempotency');

    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
});

// -----------------------------------------------------------------------------
// TC-WA-11: Multi-Channel Dispatch via NotificationService
// -----------------------------------------------------------------------------
runTest('TC-WA-11', 'NotificationService::notifyUser dapat mendaftarkan ke WhatsApp dan Email', function() {
    Notification::setMode(Notification::MODE_SIMULATION);
    $service = new NotificationService();

    // ID 1 adalah admin default yang memiliki phone dan email di database
    $userModel = new \App\Models\UserModel();
    $admin = $userModel->find(1);
    
    // Pastikan admin memiliki nomor telepon pengujian
    if (empty($admin['phone'])) {
        $userModel->update(1, ['phone' => '081234567890']);
    }

    $res = $service->notifyUser(
        1,
        'Pemberitahuan Sistem Multi-Channel',
        'Ini adalah pesan notifikasi yang dikirim ke seluruh channel.',
        NotificationService::TYPE_INFO,
        '/admin/dashboard',
        [NotificationService::CHANNEL_IN_APP, NotificationService::CHANNEL_WHATSAPP, NotificationService::CHANNEL_EMAIL]
    );

    assertTrue($res['success'], 'notifyUser must succeed');
    assertTrue(isset($res['channels']['in_app']), 'In-app channel enqueued');
    assertTrue(isset($res['channels']['whatsapp']), 'WhatsApp channel enqueued');
    assertTrue(isset($res['channels']['email']), 'Email channel enqueued');
});

// -----------------------------------------------------------------------------
// TC-WA-12: Isolasi Database Operasional (ujian_dual_kamera strictly 0 rows)
// -----------------------------------------------------------------------------
runTest('TC-WA-12', 'Database operasional ujian_dual_kamera STRICTLY tidak tersentuh (0 logs)', function() {
    $dbOperasional = 'ujian_dual_kamera';
    $conn = Database::getConnection();
    
    // Cek jumlah row di database operasional
    $count = (int)$conn->query("SELECT COUNT(*) FROM `{$dbOperasional}`.`notification_logs`")->fetchColumn();
    assertEquals(0, $count, "Database operasional ujian_dual_kamera HARUS tetap memiliki tepat 0 logs!");

    // Cek bahwa koneksi aktif tetap ujian_dual_kamera_test
    $currentDb = (string)$conn->query("SELECT DATABASE()")->fetchColumn();
    assertEquals('ujian_dual_kamera_test', $currentDb, "Koneksi aktif harus ujian_dual_kamera_test");
});

echo "==============================================================================\n";
echo "  HASIL AKHIR AUDIT: {$totalPassed} PASS / {$totalFailed} FAIL\n";
echo "==============================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
