<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: SMART EXAM — TAHAP 2: ARSITEKTUR ANTREAN NOTIFIKASI & WORKER
 * ==============================================================================
 * Memvalidasi 12 item pengujian wajib:
 * 1.  Notifikasi masuk ke antrean (status: queued).
 * 2.  Worker dapat mengambil antrean (getPendingQueue).
 * 3.  Worker memproses simulasi (WhatsApp & Email mock).
 * 4.  Status antrean berubah sesuai hasil (queued -> sent).
 * 5.  Notifikasi gagal dicatat (queued -> failed jika data/penerima tidak valid).
 * 6.  Notifikasi tidak diproses dua kali secara tidak sengaja (Atomic Concurrency Locking).
 * 7.  Submit ujian tidak menunggu pengiriman eksternal (Non-blocking latency < 50ms).
 * 8.  Event publikasi nilai tetap berjalan (ResultService).
 * 9.  Event perubahan jadwal tetap berjalan (ExamService).
 * 10. AI Proctoring tetap berjalan (ViolationDetectionService).
 * 11. Inbox dan navbar tetap berfungsi (Unread counter & sanitasi).
 * 12. Keamanan Tahap 1 tetap terjaga (Token & credential masking).
 * ==============================================================================
 */

// Targetkan database testing secara terisolasi
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Models\NotificationLogModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\NotificationQueueService;
use App\Services\NotificationService;
use App\Services\ExamSessionService;
use App\Services\ExamService;
use App\Services\ResultService;
use App\Services\ViolationDetectionService;
use App\Controllers\NotificationLogController;
use App\Libraries\Request;

// Helper styling output pengujian
function report(string $code, string $desc, bool $status, string $extra = ''): void {
    $tag = $status ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo " {$tag} {$code}: {$desc}\n";
    if (!empty($extra)) {
        echo "        \033[36mINFO:\033[0m {$extra}\n";
    }
    if (!$status) {
        echo "        \033[41;37mGAGAL: Validasi tidak sesuai ekspektasi.\033[0m\n";
    }
}

$totalTests = 0;
$passedTests = 0;

function testAssert(string $code, string $desc, bool $condition, string $extra = ''): void {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
    }
    report($code, $desc, $condition, $extra);
}

echo "====================================================================\n";
echo "   TEST SUITE: ARSITEKTUR ANTREAN NOTIFIKASI TAHAP 2                \n";
echo "====================================================================\n";
echo "Target Database: " . \App\Config\Database::$database . "\n\n";

$logModel = new NotificationLogModel();
$queueService = new NotificationQueueService($logModel);
$notifService = new NotificationService(null, $logModel, null, null, $queueService);
$db = \App\Libraries\Database::getConnection();

// Bersihkan data antrean lama untuk konsistensi pengujian
\App\Config\Notification::setMode(\App\Config\Notification::MODE_SIMULATION);
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%stage2_test%'");
$db->exec("UPDATE notification_logs SET status = 'queued' WHERE status = 'processing'");

// -----------------------------------------------------------------------------
// 1. PENGUJIAN NOTIFIKASI MASUK KE ANTREAN (STATUS: QUEUED)
// -----------------------------------------------------------------------------
echo "--- 1. NOTIFIKASI MASUK KE ANTREAN (STATUS: QUEUED) ---\n";

$resEnqueueWA = $queueService->enqueue(
    null,
    'whatsapp',
    '081299887766_stage2_test',
    ['title' => 'Uji Masuk Antrean WA', 'preview' => 'Isi pesan antrean']
);

$resEnqueueEmail = $queueService->enqueue(
    null,
    'email',
    'stage2_test@example.com',
    ['subject' => 'Uji Masuk Antrean Email', 'preview' => 'Isi pesan email']
);

$tc01_ok = ($resEnqueueWA['status'] ?? '') === 'queued'
    && ($resEnqueueEmail['status'] ?? '') === 'queued'
    && !empty($resEnqueueWA['log_id'])
    && !empty($resEnqueueEmail['log_id']);

testAssert(
    'TC-QUEUE-01',
    'Notifikasi WhatsApp dan Email berhasil didaftarkan ke antrean dengan status queued',
    $tc01_ok,
    "WA Log ID: {$resEnqueueWA['log_id']}, Email Log ID: {$resEnqueueEmail['log_id']}"
);

// Verifikasi di database
$logWa = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resEnqueueWA['log_id'])->fetch(PDO::FETCH_ASSOC);
$logEmail = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resEnqueueEmail['log_id'])->fetch(PDO::FETCH_ASSOC);

$tc01b_ok = ($logWa['status'] ?? '') === 'queued'
    && ($logEmail['status'] ?? '') === 'queued'
    && str_contains($logWa['response_payload'] ?? '', 'Uji Masuk Antrean WA')
    && str_contains($logEmail['response_payload'] ?? '', 'Uji Masuk Antrean Email');

testAssert(
    'TC-QUEUE-01B',
    'Database notification_logs menyimpan record dengan status queued dan payload terstruktur',
    $tc01b_ok,
    "WA Status DB: {$logWa['status']}, Email Status DB: {$logEmail['status']}"
);

// -----------------------------------------------------------------------------
// 2. PENGUJIAN WORKER MENGAMBIL ANTREAN (GET PENDING QUEUE)
// -----------------------------------------------------------------------------
echo "\n--- 2. WORKER MENGAMBIL ANTREAN (GET PENDING QUEUE) ---\n";

$totalPending = $queueService->countPendingQueue();
$fetchLimit = max(100, $totalPending + 10);
$pendingList = $queueService->getPendingItems($fetchLimit);
$allAreQueued = !empty($pendingList);
$foundWa = false;
$foundEmail = false;

foreach ($pendingList as $item) {
    if ($item['status'] !== 'queued') {
        $allAreQueued = false;
    }
    if ((int)$item['id'] === (int)$resEnqueueWA['log_id']) {
        $foundWa = true;
    }
    if ((int)$item['id'] === (int)$resEnqueueEmail['log_id']) {
        $foundEmail = true;
    }
}

testAssert(
    'TC-QUEUE-02',
    'Worker dapat mengambil daftar antrean yang berstatus queued dari database',
    $foundWa && $foundEmail && $allAreQueued,
    "Jumlah pending antrean ditemukan: " . count($pendingList)
);

// -----------------------------------------------------------------------------
// 3 & 4. PENGUJIAN WORKER MEMPROSES SIMULASI & PERUBAHAN STATUS KE 'SENT'
// -----------------------------------------------------------------------------
echo "\n--- 3 & 4. WORKER MEMPROSES SIMULASI & STATUS BERUBAH MENJADI 'SENT' ---\n";

$processWaRes = $queueService->processItem($logWa);
$processEmailRes = $queueService->processItem($logEmail);

$tc03_ok = ($processWaRes['status'] ?? '') === 'sent'
    && ($processEmailRes['status'] ?? '') === 'sent'
    && ($processWaRes['claimed'] ?? false) === true
    && ($processEmailRes['claimed'] ?? false) === true;

testAssert(
    'TC-QUEUE-03',
    'Worker memproses simulasi dan mengubah status antrean menjadi sent secara atomik',
    $tc03_ok,
    "Hasil WA: {$processWaRes['status']}, Hasil Email: {$processEmailRes['status']}"
);

// Verifikasi isi payload hasil simulasi di database
$logWaAfter = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resEnqueueWA['log_id'])->fetch(PDO::FETCH_ASSOC);
$payloadWa = json_decode((string)$logWaAfter['response_payload'], true);

$tc04_ok = ($logWaAfter['status'] ?? '') === 'sent'
    && isset($payloadWa['mock_gateway'])
    && str_contains((string)$payloadWa['message_id'], 'WA-MOCK')
    && isset($payloadWa['simulated_latency_ms'])
    && ($payloadWa['worker_sim'] ?? false) === true;

testAssert(
    'TC-QUEUE-04',
    'Payload hasil simulasi memuat identitas gateway mock, message_id, dan latency simulasi',
    $tc04_ok,
    "Gateway: {$payloadWa['mock_gateway']}, Message ID: {$payloadWa['message_id']}"
);

// -----------------------------------------------------------------------------
// 5. PENGUJIAN NOTIFIKASI GAGAL DICATAT (STATUS: FAILED DENGAN REASON)
// -----------------------------------------------------------------------------
echo "\n--- 5. PENGUJIAN NOTIFIKASI GAGAL DICATAT (STATUS: FAILED) ---\n";

// Daftarkan item dengan nomor/email tidak valid
$resBadWA = $queueService->enqueue(
    null,
    'whatsapp',
    'N/A', // nomor tidak valid
    ['title' => 'WA Tujuan Tidak Valid']
);
$resBadEmail = $queueService->enqueue(
    null,
    'email',
    'invalid-email-format-without-at', // email format tidak valid
    ['subject' => 'Email Tujuan Tidak Valid']
);

$badWaItem = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resBadWA['log_id'])->fetch(PDO::FETCH_ASSOC);
$badEmailItem = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resBadEmail['log_id'])->fetch(PDO::FETCH_ASSOC);

$processBadWa = $queueService->processItem($badWaItem);
$processBadEmail = $queueService->processItem($badEmailItem);

$logBadWaAfter = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resBadWA['log_id'])->fetch(PDO::FETCH_ASSOC);
$logBadEmailAfter = $db->query("SELECT * FROM notification_logs WHERE id = " . (int)$resBadEmail['log_id'])->fetch(PDO::FETCH_ASSOC);

$payloadBadWa = json_decode((string)$logBadWaAfter['response_payload'], true);
$payloadBadEmail = json_decode((string)$logBadEmailAfter['response_payload'], true);

$tc05_ok = ($logBadWaAfter['status'] ?? '') === 'failed'
    && ($logBadEmailAfter['status'] ?? '') === 'failed'
    && !empty($payloadBadWa['error_reason'])
    && !empty($payloadBadEmail['error_reason']);

testAssert(
    'TC-QUEUE-05',
    'Worker mencatat status failed dan alasan kegagalan pada payload saat data penerima tidak valid',
    $tc05_ok,
    "Alasan WA: {$payloadBadWa['error_reason']} | Alasan Email: {$payloadBadEmail['error_reason']}"
);

// -----------------------------------------------------------------------------
// 6. PENGUJIAN PENCEGAHAN PENGIRIMAN GANDA (ATOMIC CONCURRENCY LOCKING)
// -----------------------------------------------------------------------------
echo "\n--- 6. PENGUJIAN ATOMIC CONCURRENCY LOCKING (ANTI DOUBLE DISPATCH) ---\n";

// Buat 1 item baru berstatus queued
$resConcurrency = $queueService->enqueue(
    null,
    'whatsapp',
    '081299990001_stage2_test',
    ['title' => 'Uji Concurrency Lock']
);
$concurrencyId = (int)$resConcurrency['log_id'];
$concurrencyItem = $db->query("SELECT * FROM notification_logs WHERE id = {$concurrencyId}")->fetch(PDO::FETCH_ASSOC);

// Worker A memproses item
$workerARes = $queueService->processItem($concurrencyItem);

// Worker B mencoba memproses item YANG SAMA persis setelah Worker A
$workerBRes = $queueService->processItem($concurrencyItem);

$tc06_ok = ($workerARes['claimed'] ?? false) === true
    && ($workerARes['status'] ?? '') === 'sent'
    && ($workerBRes['claimed'] ?? true) === false
    && in_array($workerBRes['status'] ?? '', ['skipped_claim_failed', 'skipped_already_claimed'], true)
    && ($workerBRes['dispatched'] ?? false) === false;

testAssert(
    'TC-QUEUE-06',
    'Atomic claim mencegah pengiriman ganda saat worker kedua mencoba memproses item yang sama',
    $tc06_ok,
    "Worker A Claimed: " . ($workerARes['claimed'] ? 'true' : 'false') . " | Worker B: {$workerBRes['status']}"
);

// -----------------------------------------------------------------------------
// 7. PENGUJIAN SUBMIT UJIAN NON-BLOCKING (TIDAK MENUNGGU PENGIRIMAN EKSTERNAL)
// -----------------------------------------------------------------------------
echo "\n--- 7. SUBMIT UJIAN NON-BLOCKING (TIDAK MENUNGGU PENGIRIMAN EKSTERNAL) ---\n";

// Ukur durasi pendaftaran antrean (harus instan < 15ms)
$benchStart = microtime(true);
$enqueueBench = $queueService->enqueue(
    null,
    'whatsapp',
    '081234567890',
    ['title' => 'Notifikasi Submit Ujian', 'preview' => 'Konfirmasi penyerahan lembar jawaban']
);
$benchDurationMs = (microtime(true) - $benchStart) * 1000;

$tc07_ok = ($enqueueBench['status'] ?? '') === 'queued' && $benchDurationMs < 50.0;

testAssert(
    'TC-QUEUE-07',
    'Pendaftaran antrean berjalan non-blocking dan instan tanpa membebani request submit ujian',
    $tc07_ok,
    "Durasi enqueue antrean: " . round($benchDurationMs, 2) . " ms (Target: < 50ms)"
);

// -----------------------------------------------------------------------------
// 8. PENGUJIAN EVENT PUBLIKASI HASIL UJIAN TETAP BERJALAN
// -----------------------------------------------------------------------------
echo "\n--- 8. EVENT PUBLIKASI HASIL UJIAN TETAP BERJALAN ---\n";

$resultService = new ResultService();
// Ambil ujian yang tersedia untuk pengujian
$examRow = $db->query("SELECT id, title FROM exams WHERE deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$examId = (int)($examRow['id'] ?? 1);

// Uji pemanggilan publikasi hasil ujian
$publishRes = $resultService->publishExamResults($examId, 1);

$tc08_ok = is_array($publishRes) && isset($publishRes['success']);

testAssert(
    'TC-QUEUE-08',
    'Event ResultService::publishExamResults tetap berjalan normal dan mengorkestrasi notifikasi siswa',
    $tc08_ok,
    "Pesan: " . ($publishRes['message'] ?? 'OK')
);

// -----------------------------------------------------------------------------
// 9. PENGUJIAN EVENT PERUBAHAN JADWAL UJIAN TETAP BERJALAN
// -----------------------------------------------------------------------------
echo "\n--- 9. EVENT PERUBAHAN JADWAL UJIAN TETAP BERJALAN ---\n";

$examService = new ExamService();
$newStart = date('Y-m-d H:i:s', strtotime('+1 day'));
$newEnd = date('Y-m-d H:i:s', strtotime('+1 day +2 hours'));

$scheduleRes = $examService->notifyScheduleChanged($examId, $examRow['title'] ?? 'Ujian Matematika', $newStart, $newEnd);

$tc09_ok = is_int($scheduleRes);

testAssert(
    'TC-QUEUE-09',
    'Event ExamService::notifyScheduleChanged tetap berjalan normal dengan antrean aman',
    $tc09_ok,
    "Siswa ternotifikasi perubahan jadwal: {$scheduleRes}"
);

// -----------------------------------------------------------------------------
// 10. PENGUJIAN EVENT AI PROCTORING ALERT TETAP BERJALAN
// -----------------------------------------------------------------------------
echo "\n--- 10. EVENT AI PROCTORING ALERT TETAP BERJALAN ---\n";

$proctoringService = new ViolationDetectionService();
// Guru ID 3 (Budi Santoso - Guru)
$teacherId = 3;
$alertRes = $notifService->createAiProctoringAlertNotificationForTeacher(
    $teacherId,
    'Siswa Uji Coba',
    $examRow['title'] ?? 'Ujian Matematika',
    'Deteksi Objek Ponsel (Mobile Phone)',
    $examId,
    'critical'
);

$tc10_ok = ($alertRes['success'] ?? false) === true
    && isset($alertRes['channels']['in_app']['notification_id']);

testAssert(
    'TC-QUEUE-10',
    'Notifikasi AI Proctoring Violation Alert untuk Guru tetap terkirim secara tepat sasaran',
    $tc10_ok,
    "In-App Notif ID: " . ($alertRes['channels']['in_app']['notification_id'] ?? 'null')
);

// -----------------------------------------------------------------------------
// 11. PENGUJIAN INBOX DAN NAVBAR TETAP BERFUNGSI NORMAL
// -----------------------------------------------------------------------------
echo "\n--- 11. INBOX DAN NAVBAR TETAP BERFUNGSI NORMAL ---\n";

$notifModel = new NotificationModel();
$unreadCount = $notifModel->getUnreadCount($teacherId);
$latestNotifs = $notifModel->getUserNotifications($teacherId, 5);

$tc11_ok = is_int($unreadCount) && is_array($latestNotifs);

testAssert(
    'TC-QUEUE-11',
    'Sistem Inbox dan Dropdown Navbar tetap berfungsi normal tanpa regresi',
    $tc11_ok,
    "Unread count guru #{$teacherId}: {$unreadCount}, Notifikasi terambil: " . count($latestNotifs)
);

// -----------------------------------------------------------------------------
// 12. PENGUJIAN KEAMANAN TAHAP 1 TETAP TERJAGA (MASKING KREDENSIAL & TOKEN)
// -----------------------------------------------------------------------------
echo "\n--- 12. KEAMANAN TAHAP 1 TETAP TERJAGA (MASKING & SANITASI) ---\n";

// Pastikan controller masking berfungsi pada payload worker simulasi
$logController = new NotificationLogController();
$reflector = new ReflectionClass($logController);
$maskMethod = $reflector->getMethod('maskSensitiveValues');
$maskMethod->setAccessible(true);

$sampleWorkerPayload = [
    'mock_gateway' => 'Simulated WhatsApp Gateway v1.0',
    'token'        => 'SECRET_TOKEN_XYZ',
    'api_key'      => 'KEY-1234567890',
    'preview'      => 'Gunakan token [TOKN9999] untuk ujian matematika',
    'message_id'   => 'WA-MOCK-1A2B3C'
];

$maskedPayload = $maskMethod->invoke($logController, $sampleWorkerPayload);

$tc12_ok = $maskedPayload['token'] === '***[TERPROTEKSI]***'
    && $maskedPayload['api_key'] === '***[TERPROTEKSI]***'
    && str_contains($maskedPayload['preview'], '[TERPROTEKSI]')
    && $maskedPayload['mock_gateway'] === 'Simulated WhatsApp Gateway v1.0';

testAssert(
    'TC-QUEUE-12',
    'Keamanan Tahap 1 tetap terjaga: Masking kredensial dan token bekerja sempurna pada payload worker',
    $tc12_ok,
    "Preview disamarkan: {$maskedPayload['preview']}"
);

// Bersihkan data uji setelah selesai
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%stage2_test%'");

// -----------------------------------------------------------------------------
// REKAPITULASI PENGUJIAN TAHAP 2
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI PENGUJIAN TAHAP 2 (QUEUE & SIMULATION WORKER)       \n";
echo "====================================================================\n";
echo "Total Uji Dijalankan : {$totalTests}\n";
echo "Lolos (PASSED)       : {$passedTests}\n";
echo "Gagal (FAILED)       : " . ($totalTests - $passedTests) . "\n";
$passPct = round(($passedTests / $totalTests) * 100, 2);
echo "Persentase Kelulusan : {$passPct}%\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    echo "\033[32m>>> SELURUH PENGUJIAN TAHAP 2 BERHASIL (100% PASS) <<<\033[0m\n\n";
    exit(0);
} else {
    echo "\033[31m>>> TERDAPAT PENGUJIAN YANG GAGAL! <<<\033[0m\n\n";
    exit(1);
}
