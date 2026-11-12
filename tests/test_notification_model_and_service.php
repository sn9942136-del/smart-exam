<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION MODEL & SERVICE (TAHAP 1 - MODUL NOTIFIKASI)
 * ==============================================================================
 * Menguji secara komprehensif fungsionalitas backend NotificationModel,
 * NotificationLogModel, dan NotificationService:
 * 1. NotificationModel CRUD (create, read, unread count, mark read, mark all, delete)
 * 2. Keamanan IDOR: User lain tidak bisa membaca/mengubah/menghapus notifikasi user lain
 * 3. NotificationLogModel: Pencatatan riwayat audit multi-channel (WA & Email)
 * 4. NotificationService Distribution:
 *    - notifyUser: Pengiriman individual
 *    - notifyClass: Pengiriman batch rombel kelas
 *    - notifyTeacher: Pengiriman guru
 *    - notifyAdmins: Pengiriman seluruh admin
 * 5. Token Protection Guardrail: Siswa tidak menerima token ujian
 * 6. Kesiapan Arsitektur WhatsApp & Email: Simulasi aman tanpa koneksi eksternal
 * ==============================================================================
 */

// Gunakan target database pengujian
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Models\NotificationLogModel;
use App\Models\NotificationModel;
use App\Services\NotificationService;

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION MODEL & SERVICE (TAHAP 1)               \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

$notifModel = new NotificationModel();
$logModel = new NotificationLogModel();
$notifService = new NotificationService($notifModel, $logModel);

$passCount = 0;
$failCount = 0;

function report(string $code, string $name, bool $ok, string $details = ''): void {
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo "[\033[32mPASS\033[0m] {$code}: {$name}\n";
    } else {
        $failCount++;
        echo "[\033[31mFAIL\033[0m] {$code}: {$name}\n";
    }
    if (!empty($details)) {
        echo "       Details: {$details}\n";
    }
}

// Persiapan ID Pengguna Pengujian:
// Student: ID 4 (Ahmad Fauzi, XII MIPA 1)
// Teacher: ID 3 (Budi Santoso)
// Admin: ID 2 (Administrator Ujian)
$testStudentId = 4;
$testTeacherId = 3;
$testAdminId = 2;

// Bersihkan notifikasi uji sebelumnya jika ada
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 3, 2, 9999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

// --------------------------------------------------------------------------
// BAGIAN 1: PENGUJIAN NOTIFICATION MODEL CRUD
// --------------------------------------------------------------------------
echo "--- 1. PENGUJIAN NOTIFICATION MODEL ---\n";

// TC-NOTIF-01: Create Notification
$notifId = $notifModel->createNotification([
    'user_id'    => $testStudentId,
    'title'      => 'Pengumuman Uji Coba Model',
    'message'    => 'Ini adalah pesan tes notifikasi model.',
    'type'       => 'info',
    'action_url' => '/siswa/dashboard'
]);

$tc01_ok = is_numeric($notifId) && (int)$notifId > 0;
report('TC-NOTIF-01', 'NotificationModel berhasil membuat record notifikasi baru', $tc01_ok, "ID Notifikasi: {$notifId}");

// TC-NOTIF-02: Get User Notifications & Unread Count
$list = $notifModel->getUserNotifications($testStudentId, 5, 0);
$unreadCount = $notifModel->getUnreadCount($testStudentId);

$tc02_ok = count($list) >= 1 
    && (int)$list[0]['id'] === (int)$notifId 
    && $list[0]['is_read'] === 0 
    && $unreadCount >= 1;

report('TC-NOTIF-02', 'NotificationModel mengambil daftar notifikasi dan unread count tepat', $tc02_ok, "Total unread: {$unreadCount}");

// TC-NOTIF-03: Mark As Read Scoped to User
$marked = $notifModel->markAsRead((int)$notifId, $testStudentId);
$unreadAfterMark = $notifModel->getUnreadCount($testStudentId);
$itemAfterMark = $notifModel->find((int)$notifId);

$tc03_ok = $marked === true 
    && $unreadAfterMark === ($unreadCount - 1) 
    && (int)$itemAfterMark['is_read'] === 1 
    && !empty($itemAfterMark['read_at']);

report('TC-NOTIF-03', 'NotificationModel menandai notifikasi sudah dibaca (is_read = 1 & read_at)', $tc03_ok, "Read at: {$itemAfterMark['read_at']}");

// TC-NOTIF-04: IDOR Protection: User lain tidak bisa menandai notifikasi user lain
$notifUserA = $notifModel->createNotification([
    'user_id' => $testStudentId,
    'title'   => 'Notifikasi Privat Siswa',
    'message' => 'Pesan privat.',
    'type'    => 'info'
]);

// Guru (ID 3) mencoba menandai notifikasi milik Siswa (ID 4)
$idorMarkResult = $notifModel->markAsRead((int)$notifUserA, $testTeacherId);
// Guru (ID 3) mencoba menghapus notifikasi milik Siswa (ID 4)
$idorDeleteResult = $notifModel->deleteForUser((int)$notifUserA, $testTeacherId);

$itemStillExists = $notifModel->find((int)$notifUserA);

$tc04_ok = $idorMarkResult === false 
    && $idorDeleteResult === false 
    && $itemStillExists !== null 
    && (int)$itemStillExists['is_read'] === 0;

report('TC-NOTIF-04', 'Proteksi IDOR: Pengguna lain dilarang mengubah/menghapus notifikasi milik pengguna lain', $tc04_ok, "Tolak akses silang user berhasil.");

// TC-NOTIF-05: Batch Notifications Creation
$batchIds = [$testStudentId, $testTeacherId, $testAdminId];
$batchCreated = $notifModel->createBatchNotifications(
    $batchIds,
    'Pemberitahuan Sistem Serentak',
    'Server akan melakukan pemeliharaan rutin pada akhir pekan.',
    'warning',
    '/dashboard'
);

$tc05_ok = $batchCreated === 3;
report('TC-NOTIF-05', 'NotificationModel batch insert berhasil membuat notifikasi serentak', $tc05_ok, "Total terbuat: {$batchCreated} baris.");

// TC-NOTIF-06: Mark All As Read
$markedAll = $notifModel->markAllAsRead($testStudentId);
$unreadFinal = $notifModel->getUnreadCount($testStudentId);

$tc06_ok = $markedAll >= 1 && $unreadFinal === 0;
report('TC-NOTIF-06', 'NotificationModel markAllAsRead berhasil menandai seluruh notifikasi user', $tc06_ok, "Unread akhir: {$unreadFinal}");

// --------------------------------------------------------------------------
// BAGIAN 2: PENGUJIAN NOTIFICATION LOG MODEL (MULTI-CHANNEL)
// --------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN NOTIFICATION LOG MODEL ---\n";

// TC-LOG-01: Record Log Multi-Channel
$logId = $logModel->recordLog(
    (int)$notifId,
    'whatsapp',
    '081234567890',
    'queued',
    ['simulated' => true, 'gateway' => 'standby']
);

$tcLog01_ok = is_numeric($logId) && (int)$logId > 0;
$logRecord = $logModel->find((int)$logId);

$tcLog01_ok = $tcLog01_ok 
    && $logRecord !== null 
    && $logRecord['channel'] === 'whatsapp' 
    && $logRecord['status'] === 'queued';

report('TC-LOG-01', 'NotificationLogModel berhasil mencatat riwayat dispatch multi-channel', $tcLog01_ok, "Channel: {$logRecord['channel']}, Status: {$logRecord['status']}");

// TC-LOG-02: Get Logs by Notification ID
$logs = $logModel->getLogsByNotificationId((int)$notifId);
$tcLog02_ok = count($logs) >= 1 && (int)$logs[0]['id'] === (int)$logId;
report('TC-LOG-02', 'NotificationLogModel mengambil log audit berdasarkan notification_id', $tcLog02_ok, "Total log terkait: " . count($logs));

// --------------------------------------------------------------------------
// BAGIAN 3: PENGUJIAN NOTIFICATION SERVICE
// --------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN NOTIFICATION SERVICE ---\n";

// TC-SVC-01: notifyUser Individual
$resUser = $notifService->notifyUser(
    $testStudentId,
    'Pengingat Evaluasi Mandiri',
    'Silakan pelajari kembali materi bab 3 sebelum ujian.',
    NotificationService::TYPE_INFO,
    '/siswa/dashboard'
);

$tcSvc01_ok = ($resUser['success'] ?? false) === true 
    && ($resUser['channels']['in_app']['status'] ?? '') === 'sent';

report('TC-SVC-01', 'NotificationService notifyUser berhasil mengirim notifikasi in-app', $tcSvc01_ok, "User ID: {$testStudentId}");

// TC-SVC-02: notifyClass untuk Rombel XII MIPA 1 (Class ID 1)
$resClass = $notifService->notifyClass(
    1,
    'Jadwal Ujian Matematika Peminatan',
    'Ujian akan dilaksanakan pada 16 Okt 2026.',
    NotificationService::TYPE_INFO,
    '/siswa/exams'
);

$tcSvc02_ok = ($resClass['success'] ?? false) === true 
    && ($resClass['notified_count'] ?? 0) >= 1;

report('TC-SVC-02', 'NotificationService notifyClass mendistribusikan notifikasi ke seluruh siswa kelas', $tcSvc02_ok, "Siswa ternotifikasi: {$resClass['notified_count']}");

// TC-SVC-03: Token Protection Guardrail (Token TIDAK BOLEH Bocor ke Siswa)
$leakAttemptMessage = "Ujian PAS Matematika dimulai. Token akses ujian Anda adalah PASMAT1. Jangan lupa gunakan kamera depan.";
$resSecure = $notifService->notifyUser(
    $testStudentId,
    'Ujian PAS Matematika',
    $leakAttemptMessage,
    NotificationService::TYPE_INFO
);

// Ambil pesan notifikasi yang tersimpan di database
$latestStudentNotif = $notifModel->getUserNotifications($testStudentId, 1, 0)[0] ?? null;
$tcSvc03_ok = $latestStudentNotif !== null 
    && !str_contains($latestStudentNotif['message'], 'PASMAT1')
    && str_contains($latestStudentNotif['message'], '[TERPROTEKSI]');

report('TC-SVC-03', 'Token Protection Guardrail: Token ujian disensor otomatis saat dikirim ke siswa', $tcSvc03_ok, "Isi pesan tersimpan: {$latestStudentNotif['message']}");

// TC-SVC-04: Guru Menerima Token Sah
$teacherTokenMessage = "Token ujian Penilaian Akhir Semester untuk kelas XII MIPA 1 adalah: PASMAT1 (berlaku s/d 23 Okt 2026).";
$resTeacher = $notifService->notifyTeacher(
    $testTeacherId,
    'Token Akses Ujian Guru',
    $teacherTokenMessage,
    NotificationService::TYPE_INFO
);

$latestTeacherNotif = $notifModel->getUserNotifications($testTeacherId, 1, 0)[0] ?? null;
$tcSvc04_ok = $latestTeacherNotif !== null 
    && str_contains($latestTeacherNotif['message'], 'PASMAT1');

report('TC-SVC-04', 'Hak Akses Guru: Guru pengampu diizinkan menerima token akses ujian', $tcSvc04_ok, "Token PASMAT1 berhasil diterima guru.");

// TC-SVC-05: notifyAdmins untuk Seluruh Admin
$resAdmin = $notifService->notifyAdmins(
    'Audit Operasional Ujian',
    'PAS Matematika Peminatan XII sedang berlangsung pada 3 rombel.',
    NotificationService::TYPE_INFO,
    '/admin/dashboard'
);

$tcSvc05_ok = ($resAdmin['success'] ?? false) === true 
    && ($resAdmin['notified_count'] ?? 0) >= 1;

report('TC-SVC-05', 'NotificationService notifyAdmins mendistribusikan notifikasi ke seluruh admin', $tcSvc05_ok, "Admin ternotifikasi: {$resAdmin['notified_count']}");

// TC-SVC-06: Kesiapan Arsitektur WhatsApp & Email (Simulasi Tanpa Kirim Riil)
$resMulti = $notifService->notifyUser(
    $testTeacherId,
    'Uji Arsitektur Multi-Channel',
    'Pemberitahuan uji multi-channel Smart Exam.',
    NotificationService::TYPE_INFO,
    '/guru/dashboard',
    [NotificationService::CHANNEL_IN_APP, NotificationService::CHANNEL_WHATSAPP, NotificationService::CHANNEL_EMAIL]
);

$waStatus = $resMulti['channels']['whatsapp']['status'] ?? '';
$emailStatus = $resMulti['channels']['email']['status'] ?? '';

$tcSvc06_ok = ($resMulti['success'] ?? false) === true
    && in_array($waStatus, ['queued_simulation', 'skipped'], true)
    && in_array($emailStatus, ['queued_simulation', 'skipped'], true);

// Verifikasi log tercatat di notification_logs
$recentLogs = $logModel->getRecentLogs(2);
$tcSvc06_log_ok = count($recentLogs) >= 1;

report(
    'TC-SVC-06',
    'Kesiapan Arsitektur Multi-Channel: WhatsApp & Email tercatat aman di log tanpa koneksi eksternal',
    $tcSvc06_ok && $tcSvc06_log_ok,
    "WA Status: {$waStatus}, Email Status: {$emailStatus}"
);

// TC-SVC-07: Event Helper Rilis Nilai Siswa
$resResult = $notifService->createExamResultPublishedNotification(
    $testStudentId,
    'Penilaian Akhir Semester (PAS) Matematika Peminatan XII',
    88.5,
    75.0,
    true
);

$latestResultNotif = $notifModel->getUserNotifications($testStudentId, 1, 0)[0] ?? null;
$tcSvc07_ok = ($resResult['success'] ?? false) === true 
    && $latestResultNotif !== null 
    && str_contains($latestResultNotif['title'], 'Hasil Ujian Dirilis')
    && str_contains($latestResultNotif['message'], '88.50')
    && $latestResultNotif['type'] === 'success'
    && $latestResultNotif['action_url'] === '/siswa/results';

report('TC-SVC-07', 'Event Helper createExamResultPublishedNotification menghasilkan notifikasi hasil terstruktur', $tcSvc07_ok, "Tipe: {$latestResultNotif['type']}, Action: {$latestResultNotif['action_url']}");

// TC-SVC-08: Event Helper Siswa Submit Ujian dengan Esai untuk Guru
$resSubmit = $notifService->createStudentSubmissionNotificationForTeacher(
    $testTeacherId,
    'Ahmad Fauzi',
    'Penilaian Akhir Semester (PAS) Matematika Peminatan XII',
    'XII MIPA 1',
    true // has essay
);

$latestSubmitNotif = $notifModel->getUserNotifications($testTeacherId, 1, 0)[0] ?? null;
$tcSvc08_ok = ($resSubmit['success'] ?? false) === true 
    && $latestSubmitNotif !== null 
    && str_contains($latestSubmitNotif['message'], 'Ahmad Fauzi')
    && str_contains($latestSubmitNotif['message'], 'koreksi dan penilaian manual')
    && $latestSubmitNotif['type'] === 'warning'
    && $latestSubmitNotif['action_url'] === '/guru/grading';

report('TC-SVC-08', 'Event Helper createStudentSubmissionNotificationForTeacher mengarahkan guru ke halaman grading esai', $tcSvc08_ok, "Action: {$latestSubmitNotif['action_url']}");

// --------------------------------------------------------------------------
// BERSIHKAN DATA PENGUJIAN
// --------------------------------------------------------------------------
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 3, 2, 9999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");

// --------------------------------------------------------------------------
// REKAPITULASI HASIL PENGUJIAN
// --------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN MODUL NOTIFIKASI TAHAP 1: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
