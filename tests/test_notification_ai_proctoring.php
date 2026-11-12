<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION AI PROCTORING (STAGE 6)
 * ==============================================================================
 * Menguji integrasi event AI Proctoring dengan sistem notifikasi Smart Exam:
 * 1. MULTIPLE_FACES menghasilkan notifikasi ke guru pengampu
 * 2. PHONE_DETECTED menghasilkan notifikasi ke guru pengampu
 * 3. CAMERA_DISCONNECTED menghasilkan notifikasi ke guru pengampu
 * 4. TAB_SWITCH di bawah threshold (<3) tidak menghasilkan notifikasi
 * 5. TAB_SWITCH mencapai threshold (>=3) menghasilkan notifikasi
 * 6. Cooldown deduplication (300 detik) mencegah duplikasi event yang sama
 * 7. Event berbeda (MULTIPLE_FACES vs PHONE_DETECTED) tidak saling menekan
 * 8. Guru lain tidak menerima notifikasi
 * 9. Siswa tidak menerima notifikasi AI proctoring di inbox
 * 10. Sesi submitted tidak memicu notifikasi
 * 11. Sesi terminated tidak memicu notifikasi
 * 12. Invalid session / exam ditolak
 * 13. Kegagalan notifikasi tidak membatalkan penyimpanan ai_violations
 * 14. Action URL valid (/guru/exams/monitoring/{examId})
 * 15. Sanitasi konten anti-bocor token
 * 16. Audit log tercatat di notification_logs
 * 17. Event non-notifikasi (LOOK_AWAY, NO_FACE, dll) tidak memicu notifikasi
 * 18. Concurrency safety: Dua worker paralel tidak menghasilkan notifikasi ganda
 * 19. Notifikasi baru diizinkan setelah cooldown 300 detik kedaluwarsa
 * ==============================================================================
 */

// Konfigurasi database pengujian terisolasi
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Models\AiViolationModel;
use App\Models\AiViolationTypeModel;
use App\Models\AiWarningModel;
use App\Models\ExamModel;
use App\Models\ExamSessionModel;
use App\Models\NotificationLogModel;
use App\Models\NotificationModel;
use App\Models\ProctoringSessionModel;
use App\Models\UserModel;
use App\Services\NotificationService;
use App\Services\ViolationDetectionService;

// Child worker untuk pengujian konkurensi CLI
if (($argv[1] ?? '') === 'worker_record_violation') {
    $procSessionId = (int)($argv[2] ?? 0);
    $vCode = (string)($argv[3] ?? 'MULTIPLE_FACES');
    $service = new ViolationDetectionService();
    $res = $service->recordViolation($procSessionId, $vCode, 0.95);
    echo json_encode($res);
    exit(0);
}

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION AI PROCTORING INTEGRATION (STAGE 6)     \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

if ($currentDb !== 'ujian_dual_kamera_test') {
    echo "[\033[31mCRITICAL ERROR\033[0m] Pengujian TIDAK diizinkan berjalan di database selain ujian_dual_kamera_test!\n";
    exit(1);
}

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

// -----------------------------------------------------------------------------
// SETUP FIXTURES
// -----------------------------------------------------------------------------
echo "[SETUP] Menyiapkan fixture data untuk pengujian...\n";

// 1. Guru Pengampu Utama & Guru Lain
$teachers = $db->query("
    SELECT u.id, u.name, u.email 
    FROM users u
    INNER JOIN user_roles ur ON ur.user_id = u.id
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE r.name = 'guru' AND u.status = 'active' AND u.deleted_at IS NULL
    ORDER BY u.id ASC
    LIMIT 2
")->fetchAll();

if (count($teachers) < 2) {
    die("ERROR: Dibutuhkan minimal 2 akun guru aktif pada database testing.\n");
}

$primaryTeacherId = (int)$teachers[0]['id'];
$otherTeacherId   = (int)$teachers[1]['id'];

// 2. Siswa Peserta Ujian
$students = $db->query("
    SELECT u.id, u.name, u.username
    FROM users u
    INNER JOIN user_roles ur ON ur.user_id = u.id
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE r.name IN ('siswa', 'peserta_didik') AND u.status = 'active' AND u.deleted_at IS NULL
    ORDER BY u.id ASC
    LIMIT 2
")->fetchAll();

if (count($students) < 2) {
    die("ERROR: Dibutuhkan minimal 2 akun siswa aktif pada database testing.\n");
}

$studentAId = (int)$students[0]['id'];
$studentBId = (int)$students[1]['id'];

// 3. Subject Ujian
$subjectId = (int)$db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();
if ($subjectId <= 0) {
    $db->prepare("INSERT INTO subjects (name, code) VALUES ('Matematika Uji AI', 'MAT-AI')")->execute();
    $subjectId = (int)$db->lastInsertId();
}

// 4. Bersihkan notifikasi guru pengampu dan log lama di DB testing untuk isolasi
$db->prepare("DELETE FROM notifications WHERE user_id IN (?, ?)")->execute([$primaryTeacherId, $otherTeacherId]);
$db->prepare("DELETE FROM notifications WHERE user_id IN (?, ?)")->execute([$studentAId, $studentBId]);

// 5. Buat Exam Test Utama
$examCode = 'EXAM-AIP-' . substr(uniqid(), -6);
$db->prepare("
    INSERT INTO exams (subject_id, teacher_id, title, code, type, duration_minutes, passing_grade, is_dual_cam_required, status, created_at, updated_at)
    VALUES (?, ?, 'Ujian Simulasi AI Proctoring', ?, 'daily_test', 60, 75.0, 1, 'published', NOW(), NOW())
")->execute([$subjectId, $primaryTeacherId, $examCode]);
$examId = (int)$db->lastInsertId();

// 6. Buat Sesi Ujian Aktif Siswa A
$sessionTokenA = 'sess_aip_' . bin2hex(random_bytes(8));
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, integrity_score, cam1_status, cam2_status, created_at, updated_at)
    VALUES (?, ?, ?, '127.0.0.1', NOW(), 3600, 'ongoing', 100.0, 'connected', 'connected', NOW(), NOW())
")->execute([$examId, $studentAId, $sessionTokenA]);
$examSessionAId = (int)$db->lastInsertId();

// 7. Buat Sesi Proctoring Siswa A
$db->prepare("
    INSERT INTO proctoring_sessions (exam_session_id, student_id, status, started_at, created_at, updated_at)
    VALUES (?, ?, 'active', NOW(), NOW(), NOW())
")->execute([$examSessionAId, $studentAId]);
$procSessionAId = (int)$db->lastInsertId();

echo "  -> Exam ID: {$examId} (Teacher: {$primaryTeacherId})\n";
echo "  -> Exam Session A: {$examSessionAId}, Proc Session A: {$procSessionAId} (Student: {$studentAId})\n\n";

$violationService = new ViolationDetectionService();
$notificationModel = new NotificationModel();
$notificationLogModel = new NotificationLogModel();

// =============================================================================
// TEST 1: MULTIPLE_FACES Menghasilkan Notifikasi ke Guru Pengampu
// =============================================================================
echo "[TEST SUITE 1] Critical Events: MULTIPLE_FACES, PHONE_DETECTED, CAMERA_DISCONNECTED\n";

$resMultiFace = $violationService->recordViolation($procSessionAId, 'MULTIPLE_FACES', 0.95);
$notifMulti = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND title = 'Indikasi Pelanggaran AI Proctoring' 
      AND message LIKE '%Terdeteksi Lebih dari 1 Wajah%'
    ORDER BY id DESC LIMIT 1
")->fetch();

report(
    'TC-AIP-01',
    'MULTIPLE_FACES menghasilkan notifikasi ke guru pengampu',
    !empty($resMultiFace['success']) && !empty($resMultiFace['violation_id']) && !empty($notifMulti),
    $notifMulti ? "Notif ID: {$notifMulti['id']}, Type: {$notifMulti['type']}, Action URL: {$notifMulti['action_url']}" : 'Notifikasi tidak ditemukan'
);

// =============================================================================
// TEST 2: PHONE_DETECTED Menghasilkan Notifikasi ke Guru Pengampu
// =============================================================================
$resPhone = $violationService->recordViolation($procSessionAId, 'PHONE_DETECTED', 0.92);
$notifPhone = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND title = 'Indikasi Pelanggaran AI Proctoring' 
      AND message LIKE '%Smartphone Terdeteksi di Area Meja%'
    ORDER BY id DESC LIMIT 1
")->fetch();

report(
    'TC-AIP-02',
    'PHONE_DETECTED menghasilkan notifikasi ke guru pengampu',
    !empty($resPhone['success']) && !empty($resPhone['violation_id']) && !empty($notifPhone),
    $notifPhone ? "Notif ID: {$notifPhone['id']}, Action URL: {$notifPhone['action_url']}" : 'Notifikasi tidak ditemukan'
);

// =============================================================================
// TEST 3: CAMERA_DISCONNECTED Menghasilkan Notifikasi ke Guru Pengampu
// =============================================================================
$resCamDisc = $violationService->recordViolation($procSessionAId, 'CAMERA_DISCONNECTED', 0.90);
$notifCam = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND title = 'Indikasi Pelanggaran AI Proctoring' 
      AND message LIKE '%Koneksi Kamera Utama/Ruangan Terputus%'
    ORDER BY id DESC LIMIT 1
")->fetch();

report(
    'TC-AIP-03',
    'CAMERA_DISCONNECTED menghasilkan notifikasi ke guru pengampu',
    !empty($resCamDisc['success']) && !empty($resCamDisc['violation_id']) && !empty($notifCam),
    $notifCam ? "Notif ID: {$notifCam['id']}, Action URL: {$notifCam['action_url']}" : 'Notifikasi tidak ditemukan'
);

// =============================================================================
// TEST 4 & 5: TAB_SWITCH Threshold (<3 No Notif, >=3 Notif)
// =============================================================================
echo "\n[TEST SUITE 2] Repeated Events: TAB_SWITCH Threshold\n";

// Sesi baru untuk isolasi pengujian TAB_SWITCH
$sessionTokenTab = 'sess_tab_' . bin2hex(random_bytes(8));
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, integrity_score, cam1_status, cam2_status, created_at, updated_at)
    VALUES (?, ?, ?, '127.0.0.1', NOW(), 3600, 'ongoing', 100.0, 'connected', 'connected', NOW(), NOW())
")->execute([$examId, $studentBId, $sessionTokenTab]);
$examSessionTabId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO proctoring_sessions (exam_session_id, student_id, status, started_at, created_at, updated_at)
    VALUES (?, ?, 'active', NOW(), NOW(), NOW())
")->execute([$examSessionTabId, $studentBId]);
$procSessionTabId = (int)$db->lastInsertId();

// TAB_SWITCH ke-1
$resTab1 = $violationService->recordViolation($procSessionTabId, 'TAB_SWITCH', 0.95);
// Cooldown database bypass: update created_at agar tidak kena rate limit internal 3 detik
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionTabId}");

// TAB_SWITCH ke-2
$resTab2 = $violationService->recordViolation($procSessionTabId, 'TAB_SWITCH', 0.95);
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionTabId}");

$notifTabEarly = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Berpindah Tab / Jendela Aplikasi%' 
      AND message LIKE '%{$students[1]['name']}%'
")->fetchColumn();

report(
    'TC-AIP-04',
    'TAB_SWITCH ke-1 dan ke-2 tidak menghasilkan notifikasi guru (< 3 threshold)',
    (int)$notifTabEarly === 0 && empty($resTab1['notification']) && empty($resTab2['notification']),
    "Notifikasi terkirim: {$notifTabEarly} (Expected: 0)"
);

// TAB_SWITCH ke-3 (mencapai threshold >= 3)
$resTab3 = $violationService->recordViolation($procSessionTabId, 'TAB_SWITCH', 0.95);
$notifTabReached = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Berpindah Tab / Jendela Aplikasi%' 
      AND message LIKE '%{$students[1]['name']}%'
    ORDER BY id DESC LIMIT 1
")->fetch();

report(
    'TC-AIP-05',
    'TAB_SWITCH ke-3 menghasilkan notifikasi ke guru pengampu (>= 3 threshold)',
    !empty($notifTabReached) && !empty($resTab3['notification']['channels']['in_app']['notification_id']),
    $notifTabReached ? "Notif ID: {$notifTabReached['id']}, Type: {$notifTabReached['type']}" : 'Notifikasi tidak terkirim'
);

// =============================================================================
// TEST 6: Cooldown Deduplication (300 Detik)
// =============================================================================
echo "\n[TEST SUITE 3] Deduplication & Cooldown\n";

// Panggil lagi MULTIPLE_FACES pada sesi A yang baru saja menghasilkan notif
// Reset internal 3s rate-limit di ai_violations agar method lolos ke tahap notifikasi
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionAId} AND violation_type_id = (SELECT id FROM ai_violation_types WHERE code = 'MULTIPLE_FACES')");

$countBeforeMulti = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Terdeteksi Lebih dari 1 Wajah%' 
      AND message LIKE '%{$students[0]['name']}%'
")->fetchColumn();

$resDupMulti = $violationService->recordViolation($procSessionAId, 'MULTIPLE_FACES', 0.95);

$countAfterMulti = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Terdeteksi Lebih dari 1 Wajah%' 
      AND message LIKE '%{$students[0]['name']}%'
")->fetchColumn();

report(
    'TC-AIP-06',
    'Event yang sama berulang dalam kurun 300 detik tidak menghasilkan duplikasi',
    $countBeforeMulti === $countAfterMulti && !empty($resDupMulti['notification']['rate_limited']),
    "Count Sebelum: {$countBeforeMulti}, Count Sesudah: {$countAfterMulti}, Rate limited: " . (!empty($resDupMulti['notification']['rate_limited']) ? 'true' : 'false')
);

// =============================================================================
// TEST 7: Event Berbeda Tidak Keliru Dianggap Duplikat
// =============================================================================
$multiCount = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$primaryTeacherId} AND message LIKE '%Terdeteksi Lebih dari 1 Wajah%'")->fetchColumn();
$phoneCount = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$primaryTeacherId} AND message LIKE '%Smartphone Terdeteksi di Area Meja%'")->fetchColumn();

report(
    'TC-AIP-07',
    'Event berbeda (MULTIPLE_FACES vs PHONE_DETECTED) masing-masing menghasilkan notifikasi',
    $multiCount >= 1 && $phoneCount >= 1,
    "MULTIPLE_FACES count: {$multiCount}, PHONE_DETECTED count: {$phoneCount}"
);

// =============================================================================
// TEST 8: Guru Lain Tidak Menerima Notifikasi
// =============================================================================
echo "\n[TEST SUITE 4] Recipient & Role Scoping\n";

$otherTeacherNotifCount = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = {$otherTeacherId} 
      AND title = 'Indikasi Pelanggaran AI Proctoring'
")->fetchColumn();

report(
    'TC-AIP-08',
    'Guru lain tidak menerima notifikasi dari ujian yang bukan diampunya',
    $otherTeacherNotifCount === 0,
    "Notifikasi diterima guru lain: {$otherTeacherNotifCount} (Expected: 0)"
);

// =============================================================================
// TEST 9: Siswa Tidak Menerima Notifikasi di Inbox
// =============================================================================
$studentNotifCount = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id IN ({$studentAId}, {$studentBId}) 
      AND title = 'Indikasi Pelanggaran AI Proctoring'
")->fetchColumn();

report(
    'TC-AIP-09',
    'Siswa tidak menerima notifikasi AI Proctoring di inbox mereka',
    $studentNotifCount === 0,
    "Notifikasi diterima siswa: {$studentNotifCount} (Expected: 0)"
);

// =============================================================================
// TEST 10 & 11: Validasi Status Sesi (Submitted / Terminated)
// =============================================================================
echo "\n[TEST SUITE 5] Sesi Status Guard\n";

// Buat sesi ujian submitted
$tokenSub = 'sess_sub_' . bin2hex(random_bytes(8));
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, integrity_score, cam1_status, cam2_status, created_at, updated_at)
    VALUES (?, ?, ?, '127.0.0.1', NOW(), 0, 'submitted', 95.0, 'disconnected', 'disconnected', NOW(), NOW())
")->execute([$examId, $studentAId, $tokenSub]);
$examSessionSubId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO proctoring_sessions (exam_session_id, student_id, status, started_at, created_at, updated_at)
    VALUES (?, ?, 'finished', NOW(), NOW(), NOW())
")->execute([$examSessionSubId, $studentAId]);
$procSessionSubId = (int)$db->lastInsertId();

$resSubmitted = $violationService->recordViolation($procSessionSubId, 'MULTIPLE_FACES', 0.95);

report(
    'TC-AIP-10',
    'Sesi ujian berstatus submitted menolak pencatatan pelanggaran dan tidak mengirim notifikasi',
    empty($resSubmitted['success']) && ($resSubmitted['code'] ?? '') === 'EXAM_SESSION_INACTIVE',
    "Response Code: " . ($resSubmitted['code'] ?? 'none')
);

// Buat sesi ujian terminated
$tokenTerm = 'sess_term_' . bin2hex(random_bytes(8));
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, integrity_score, cam1_status, cam2_status, created_at, updated_at)
    VALUES (?, ?, ?, '127.0.0.1', NOW(), 0, 'terminated', 50.0, 'disconnected', 'disconnected', NOW(), NOW())
")->execute([$examId, $studentAId, $tokenTerm]);
$examSessionTermId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO proctoring_sessions (exam_session_id, student_id, status, started_at, created_at, updated_at)
    VALUES (?, ?, 'interrupted', NOW(), NOW(), NOW())
")->execute([$examSessionTermId, $studentAId]);
$procSessionTermId = (int)$db->lastInsertId();

$resTerminated = $violationService->recordViolation($procSessionTermId, 'PHONE_DETECTED', 0.95);

report(
    'TC-AIP-11',
    'Sesi ujian berstatus terminated menolak pencatatan pelanggaran dan tidak mengirim notifikasi',
    empty($resTerminated['success']) && ($resTerminated['code'] ?? '') === 'EXAM_SESSION_INACTIVE',
    "Response Code: " . ($resTerminated['code'] ?? 'none')
);

// =============================================================================
// TEST 12: Invalid Session Ditolak
// =============================================================================
echo "\n[TEST SUITE 6] Robustness & Fault Tolerance\n";

$resInvalid = $violationService->recordViolation(99999999, 'MULTIPLE_FACES', 0.95);

report(
    'TC-AIP-12',
    'Proctoring session ID tidak valid ditolak dengan kode INVALID_SESSION',
    empty($resInvalid['success']) && ($resInvalid['code'] ?? '') === 'INVALID_SESSION',
    "Response: " . json_encode($resInvalid)
);

// =============================================================================
// TEST 13: Notification Failure Tidak Membatalkan Penyimpanan Pelanggaran
// =============================================================================
// Mocking NotificationService yang melempar exception menggunakan Dependency Injection
$failingNotifService = new class extends NotificationService {
    public function createAiProctoringAlertNotificationForTeacher(
        int $teacherId,
        string $studentName,
        string $examTitle,
        string $violationName,
        int $examId,
        string $severity = 'critical'
    ): array {
        throw new \RuntimeException('Simulated Notification Network Outage');
    }
};

$serviceWithFailingNotif = new ViolationDetectionService(
    null,
    null,
    null,
    null,
    $failingNotifService,
    $notificationModel
);

// Bypass cooldown internal 3s
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionAId}");

$resFault = $serviceWithFailingNotif->recordViolation($procSessionAId, 'CAMERA_DISCONNECTED', 0.90);
$savedViolation = $db->query("SELECT id FROM ai_violations WHERE id = " . (int)($resFault['violation_id'] ?? 0))->fetch();

report(
    'TC-AIP-13',
    'Kegagalan NotificationService tidak membatalkan penyimpanan ai_violations (Fault Isolation)',
    !empty($resFault['success']) && !empty($resFault['violation_id']) && !empty($savedViolation),
    "Violation ID tersimpan di DB: " . ($savedViolation['id'] ?? 'none')
);

// =============================================================================
// TEST 14: Action URL Sesuai Route Aktual
// =============================================================================
echo "\n[TEST SUITE 7] Payload & Format Verification\n";

$sampleNotif = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND title = 'Indikasi Pelanggaran AI Proctoring' 
    ORDER BY id DESC LIMIT 1
")->fetch();

$expectedRoute = "/guru/exams/monitoring/{$examId}";
$actionUrlValid = ($sampleNotif && $sampleNotif['action_url'] === $expectedRoute);

report(
    'TC-AIP-14',
    'Action URL mengarah ke route monitoring guru yang valid (/guru/exams/monitoring/{examId})',
    $actionUrlValid,
    "Expected: {$expectedRoute}, Aktual: " . ($sampleNotif['action_url'] ?? 'none')
);

// =============================================================================
// TEST 15: Payload Bebas Bocoran Token
// =============================================================================
$tokenLeaks = 0;
$allAiNotifs = $db->query("
    SELECT * FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND title = 'Indikasi Pelanggaran AI Proctoring'
")->fetchAll();

foreach ($allAiNotifs as $n) {
    if (str_contains($n['message'], $sessionTokenA) || str_contains($n['message'], $sessionTokenTab)) {
        $tokenLeaks++;
    }
}

report(
    'TC-AIP-15',
    'Seluruh pesan notifikasi AI Proctoring bersih dan tidak membocorkan token ujian',
    $tokenLeaks === 0,
    "Jumlah kebocoran token: {$tokenLeaks}"
);

// =============================================================================
// TEST 16: Audit Log Tercatat di notification_logs
// =============================================================================
$logsCount = (int)$db->query("
    SELECT COUNT(*) FROM notification_logs 
    WHERE recipient = (SELECT email FROM users WHERE id = {$primaryTeacherId})
")->fetchColumn();

report(
    'TC-AIP-16',
    'Audit log pengiriman notifikasi tercatat di tabel notification_logs',
    $logsCount > 0,
    "Total notification_logs tercatat: {$logsCount}"
);

// =============================================================================
// TEST 17: Event Non-Notifikasi Tidak Memicu Notifikasi Guru
// =============================================================================
echo "\n[TEST SUITE 8] Non-Triggering Events Isolation\n";

$notifCountBefore = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$primaryTeacherId}")->fetchColumn();

// Bypass cooldown
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionAId}");

// Uji LOOK_AWAY
$resLookAway = $violationService->recordViolation($procSessionAId, 'LOOK_AWAY', 0.80);
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionAId}");

// Uji NO_FACE
$resNoFace = $violationService->recordViolation($procSessionAId, 'NO_FACE', 0.85);
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionAId}");

// Uji FULLSCREEN_EXIT
$resFsExit = $violationService->recordViolation($procSessionAId, 'FULLSCREEN_EXIT', 0.90);
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionAId}");

// Uji SECONDARY_CAM_LOST
$resSecCam = $violationService->recordViolation($procSessionAId, 'SECONDARY_CAM_LOST', 0.85);

$notifCountAfter = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$primaryTeacherId}")->fetchColumn();

report(
    'TC-AIP-17',
    'Event non-triggering (LOOK_AWAY, NO_FACE, FULLSCREEN_EXIT, SECONDARY_CAM_LOST) tidak memicu notifikasi guru',
    $notifCountBefore === $notifCountAfter 
        && empty($resLookAway['notification']) 
        && empty($resNoFace['notification']) 
        && empty($resFsExit['notification']) 
        && empty($resSecCam['notification']),
    "Notif Sebelum: {$notifCountBefore}, Notif Sesudah: {$notifCountAfter} (Delta: " . ($notifCountAfter - $notifCountBefore) . ")"
);

// =============================================================================
// TEST 18: Concurrency Safety (Dua Worker CLI Paralel)
// =============================================================================
echo "\n[TEST SUITE 9] Concurrency Safety & Cooldown Expiry\n";

// Buat Exam terisolasi untuk tes konkurensi
$examConcCode = 'EXAM-CONC-' . substr(uniqid(), -6);
$db->prepare("
    INSERT INTO exams (subject_id, teacher_id, title, code, type, duration_minutes, passing_grade, is_dual_cam_required, status, created_at, updated_at)
    VALUES (?, ?, 'Ujian Simulasi Konkurensi AI', ?, 'daily_test', 60, 75.0, 1, 'published', NOW(), NOW())
")->execute([$subjectId, $primaryTeacherId, $examConcCode]);
$examConcId = (int)$db->lastInsertId();

// Siapkan sesi baru khusus concurrency
$sessionTokenConc = 'sess_conc_' . bin2hex(random_bytes(8));
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, integrity_score, cam1_status, cam2_status, created_at, updated_at)
    VALUES (?, ?, ?, '127.0.0.1', NOW(), 3600, 'ongoing', 100.0, 'connected', 'connected', NOW(), NOW())
")->execute([$examConcId, $studentAId, $sessionTokenConc]);
$examSessionConcId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO proctoring_sessions (exam_session_id, student_id, status, started_at, created_at, updated_at)
    VALUES (?, ?, 'active', NOW(), NOW(), NOW())
")->execute([$examSessionConcId, $studentAId]);
$procSessionConcId = (int)$db->lastInsertId();

$notifCountBeforeConc = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Ujian Simulasi Konkurensi AI%'
")->fetchColumn();

// Luncurkan 2 sub-proses CLI secara simultan
$phpCli = PHP_BINARY;
$testFile = __FILE__;
$cmd1 = "\"{$phpCli}\" \"{$testFile}\" worker_record_violation {$procSessionConcId} MULTIPLE_FACES";
$cmd2 = "\"{$phpCli}\" \"{$testFile}\" worker_record_violation {$procSessionConcId} MULTIPLE_FACES";

$p1 = popen($cmd1, 'r');
$p2 = popen($cmd2, 'r');
$out1 = $p1 ? stream_get_contents($p1) : '';
$out2 = $p2 ? stream_get_contents($p2) : '';
if ($p1) pclose($p1);
if ($p2) pclose($p2);

$notifCountAfterConc = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Ujian Simulasi Konkurensi AI%'
")->fetchColumn();

$deltaConc = $notifCountAfterConc - $notifCountBeforeConc;

report(
    'TC-AIP-18',
    'Dua worker paralel yang melaporkan pelanggaran bersamaan hanya menghasilkan tepat 1 notifikasi',
    $deltaConc === 1,
    "Delta Notifikasi: {$deltaConc} (Expected: 1). Worker 1 Out: " . substr(trim($out1), 0, 80) . " | Worker 2 Out: " . substr(trim($out2), 0, 80)
);

// =============================================================================
// TEST 19: Notifikasi Baru Diizinkan Setelah Cooldown 300 Detik Berakhir
// =============================================================================
// Simulasi waktu lampau (305 detik yang lalu) pada notifikasi sebelumnya
$db->query("
    UPDATE notifications 
    SET created_at = DATE_SUB(NOW(), INTERVAL 305 SECOND) 
    WHERE user_id = {$primaryTeacherId} 
      AND message LIKE '%Ujian Simulasi Konkurensi AI%'
");

// Bypass rate limit internal 3s
$db->query("UPDATE ai_violations SET created_at = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE proctoring_session_id = {$procSessionConcId}");

$resAfterExpiry = $violationService->recordViolation($procSessionConcId, 'MULTIPLE_FACES', 0.95);

report(
    'TC-AIP-19',
    'Event yang sama setelah jendela waktu 300 detik berakhir dapat menghasilkan notifikasi baru',
    !empty($resAfterExpiry['success']) && !empty($resAfterExpiry['notification']['channels']['in_app']['notification_id']) && empty($resAfterExpiry['notification']['rate_limited']),
    "Notifikasi Baru ID: " . ($resAfterExpiry['notification']['channels']['in_app']['notification_id'] ?? 'none')
);

// -----------------------------------------------------------------------------
// CLEANUP & SUMMARY
// -----------------------------------------------------------------------------
echo "\n[CLEANUP] Membersihkan data dummy pengujian dari database testing...\n";
$db->query("DELETE FROM ai_warnings WHERE proctoring_session_id IN ({$procSessionAId}, {$procSessionTabId}, {$procSessionSubId}, {$procSessionTermId}, {$procSessionConcId})");
$db->query("DELETE FROM ai_violations WHERE proctoring_session_id IN ({$procSessionAId}, {$procSessionTabId}, {$procSessionSubId}, {$procSessionTermId}, {$procSessionConcId})");
$db->query("DELETE FROM proctoring_sessions WHERE id IN ({$procSessionAId}, {$procSessionTabId}, {$procSessionSubId}, {$procSessionTermId}, {$procSessionConcId})");
$db->query("DELETE FROM exam_sessions WHERE id IN ({$examSessionAId}, {$examSessionTabId}, {$examSessionSubId}, {$examSessionTermId}, {$examSessionConcId})");
$db->query("DELETE FROM exams WHERE id IN ({$examId}, {$examConcId})");
$db->query("DELETE FROM notifications WHERE user_id = {$primaryTeacherId} AND title = 'Indikasi Pelanggaran AI Proctoring'");

echo "\n====================================================================\n";
echo "                      HASIL PENGUJIAN AKHIR                         \n";
echo "====================================================================\n";
echo "Total Test Diselesaikan : " . ($passCount + $failCount) . "\n";
echo "Jumlah PASS             : \033[32m{$passCount}\033[0m\n";
echo "Jumlah FAIL             : " . ($failCount > 0 ? "\033[31m{$failCount}\033[0m" : "0") . "\n";
echo "Status Pengujian        : " . ($failCount === 0 ? "\033[32mSEMUA TEST BERHASIL (100% PASS)\033[0m" : "\033[31mADA TEST YANG GAGAL\033[0m") . "\n";
echo "====================================================================\n";

exit($failCount > 0 ? 1 : 0);
