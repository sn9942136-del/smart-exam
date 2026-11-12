<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION EXAM EVENTS (TAHAP 5)
 * ==============================================================================
 * Menguji integrasi event nyata sistem Smart Exam dengan NotificationService:
 * EVENT 1 — Publikasi Ujian Baru:
 *   - Notifikasi ke siswa peserta terdaftar saat status berubah ke 'published'
 *   - Siswa non-peserta tidak menerima notifikasi
 *   - Guru / Admin tidak menerima notifikasi event ini
 *   - Idempotensi: publikasi ulang tidak memicu notifikasi ganda
 *   - Publikasi gagal tidak memicu notifikasi
 *   - Action URL mengarah ke /siswa/exams
 *   - Integrasi Navbar & Inbox siswa
 * 
 * EVENT 2 — Perubahan Jadwal Ujian:
 *   - Initial schedule creation tidak memicu notifikasi perubahan jadwal
 *   - Perubahan jadwal valid pada ujian aktif mengirim notifikasi ke peserta
 *   - Jadwal yang disimpan ulang tanpa perubahan (no-op) tidak memicu notifikasi
 *   - Perubahan gagal tidak memicu notifikasi
 *   - Siswa non-peserta tidak menerima notifikasi
 *   - Idempotensi: pengulangan tidak menghasilkan duplikasi
 *   - Action URL mengarah ke /siswa/exams
 * 
 * KEAMANAN, KONKURENSI & INTEGRITAS SISTEM:
 *   - Concurrency safety (dua worker publikasi/jadwal bersamaan)
 *   - Fault tolerance (kegagalan notifikasi tidak membatalkan status / jadwal)
 *   - Isolasi database (strictly ujian_dual_kamera_test)
 *   - Integritas database operasional (ujian_dual_kamera bersih)
 * ==============================================================================
 */

// Konfigurasi database pengujian terisolasi
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Controllers\NotificationController;
use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamScheduleModel;
use App\Models\NotificationModel;
use App\Services\ExamService;
use App\Services\NotificationService;

// Handler child worker untuk simulasi konkurensi publikasi
if (($argv[1] ?? '') === 'worker_publish_exam') {
    $workerExamId = (int)($argv[2] ?? 0);
    $examService = new ExamService();
    $ok = $examService->changeStatus($workerExamId, 'published');
    echo json_encode(['success' => $ok]);
    exit(0);
}

// Handler child worker untuk simulasi konkurensi update jadwal
if (($argv[1] ?? '') === 'worker_update_schedule') {
    $workerExamId = (int)($argv[2] ?? 0);
    $workerClasses = explode(',', (string)($argv[3] ?? '1'));
    $workerStart = (string)($argv[4] ?? date('Y-m-d 08:00:00'));
    $workerEnd = (string)($argv[5] ?? date('Y-m-d 10:00:00'));
    $examService = new ExamService();
    $res = $examService->updateSchedule($workerExamId, $workerClasses, $workerStart, $workerEnd, 15);
    echo json_encode($res);
    exit(0);
}

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION EXAM EVENTS (TAHAP 5)                  \n";
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

function loginAs(array $user): void {
    Session::start();
    login_user($user);
}

// User Fixtures
$studentUser1 = [
    'id'       => 4,
    'username' => 'Ahmad Fauzi',
    'name'     => 'Ahmad Fauzi',
    'email'    => 'Ahmadfauzi@gmail.com',
    'role'     => 'peserta_didik'
];

$nonParticipantStudent = [
    'id'       => 53,
    'username' => 'Nihlah Auliya',
    'name'     => 'Nihlah Auliya',
    'email'    => 'nihlah@test.com',
    'role'     => 'peserta_didik'
];

$teacherUser = [
    'id'       => 3,
    'username' => 'Budi Santoso',
    'name'     => 'Budi Santoso, S.Kom., M.T.',
    'email'    => 'Budisantoso@gmail.com',
    'role'     => 'guru'
];

// Bersihkan data notifikasi pengujian sebelum test
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 14, 53, 55, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");
$db->exec("DELETE FROM exams WHERE code LIKE 'EXAM-T5-%' OR code LIKE 'CONC-T5-%'");

$notifModel = new NotificationModel();
$notifService = new NotificationService($notifModel);
$examService = new ExamService(null, null, null, null, null, null, null, null, null, $notifService);

try {
    // =========================================================================
    // BAGIAN 1: EVENT 1 — PUBLIKASI UJIAN BARU
    // =========================================================================
    echo "--- 1. PENGUJIAN EVENT PUBLIKASI UJIAN BARU ---\n";

    // Buat Ujian Draft Baru untuk Pengujian Publikasi
    $examCode1 = 'EXAM-T5-PUB-' . time();
    $examTitle1 = 'Ujian Pemrograman Web Lanjut Tahap 5';
    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, :title, 1, 3, 'final_exam', 90, 75.00, 'draft', NOW(), NOW())
    ")->execute([':code' => $examCode1, ':title' => $examTitle1]);
    $examId1 = (int)$db->lastInsertId();

    // Daftarkan Siswa 1 ke Ujian ini (Kelas 1)
    $db->prepare("
        INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, grace_period_minutes, status, created_at, updated_at)
        VALUES (:eid, 1, DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 3 HOUR), 15, 'scheduled', NOW(), NOW())
    ")->execute([':eid' => $examId1]);

    $db->prepare("
        INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at)
        VALUES (:eid, 4, 1, 'not_started', NOW(), NOW())
    ")->execute([':eid' => $examId1]);

    // TC-PUB-01: Publikasi pertama kali dari 'draft' ke 'published' berhasil mengubah status dan mengirim notifikasi ke peserta
    $pubResult = $examService->changeStatus($examId1, 'published');
    $exam1Check = (new ExamModel())->find($examId1);
    report(
        'TC-PUB-01',
        'Publikasi pertama mengubah status ujian menjadi published secara sukses',
        $pubResult === true && ($exam1Check['status'] ?? '') === 'published',
        "Status di DB: {$exam1Check['status']}"
    );

    // Cek Notifikasi Siswa 1
    $s1Notifs = $notifModel->getUserNotifications(4, 10);
    $pubNotifS1 = null;
    foreach ($s1Notifs as $n) {
        if ($n['title'] === 'Ujian Baru Tersedia' && str_contains($n['message'], $examTitle1)) {
            $pubNotifS1 = $n;
            break;
        }
    }

    report(
        'TC-PUB-02',
        'Siswa peserta terdaftar (Siswa 1) menerima notifikasi "Ujian Baru Tersedia"',
        $pubNotifS1 !== null,
        "Notif: " . ($pubNotifS1['title'] ?? 'NONE') . " - " . ($pubNotifS1['message'] ?? 'NONE')
    );

    // TC-PUB-03: Action URL mengarah ke /siswa/exams
    report(
        'TC-PUB-03',
        'Action URL notifikasi mengarah ke halaman ujian siswa (/siswa/exams)',
        isset($pubNotifS1['action_url']) && $pubNotifS1['action_url'] === '/siswa/exams',
        "Action URL: " . ($pubNotifS1['action_url'] ?? 'NULL')
    );

    // TC-PUB-04: Siswa non-peserta (Nihlah Auliya / ID 53) TIDAK menerima notifikasi
    $nonPartNotifs = $notifModel->getUserNotifications(53, 10);
    $hasNonPartReceived = false;
    foreach ($nonPartNotifs as $n) {
        if ($n['title'] === 'Ujian Baru Tersedia' && str_contains($n['message'], $examTitle1)) {
            $hasNonPartReceived = true;
            break;
        }
    }
    report(
        'TC-PUB-04',
        'Siswa non-peserta (ID 53 di Kelas 10) tidak menerima notifikasi publikasi ujian Kelas 1',
        !$hasNonPartReceived && count($nonPartNotifs) === 0,
        "Total notif Siswa Non-Peserta: " . count($nonPartNotifs)
    );

    // TC-PUB-05: Guru pengampu dan Administrator TIDAK menerima notifikasi event ini
    $teacherNotifs = $notifModel->getUserNotifications(3, 10);
    $hasTeacherReceived = false;
    foreach ($teacherNotifs as $n) {
        if ($n['title'] === 'Ujian Baru Tersedia' && str_contains($n['message'], $examTitle1)) {
            $hasTeacherReceived = true;
            break;
        }
    }
    report(
        'TC-PUB-05',
        'Guru pengampu tidak menerima notifikasi publikasi ujian (role-scoping siswa saja)',
        !$hasTeacherReceived,
        "Guru notif count: " . count($teacherNotifs)
    );

    // TC-PUB-06: Idempotensi Publikasi Ulang (Memanggil changeStatus('published') saat sudah published)
    $s1CountBefore = count($notifModel->getUserNotifications(4, 10));
    $rePubResult = $examService->changeStatus($examId1, 'published');
    $s1CountAfter = count($notifModel->getUserNotifications(4, 10));

    report(
        'TC-PUB-06',
        'Idempotensi: Publikasi ulang ujian yang sudah published tidak menghasilkan notifikasi duplikat',
        $rePubResult === true && $s1CountBefore === $s1CountAfter,
        "Notif count sebelum: {$s1CountBefore}, setelah: {$s1CountAfter}"
    );

    // TC-PUB-07: Publikasi gagal (ID ujian tidak valid) tidak menghasilkan notifikasi
    $s1CountBeforeFail = count($notifModel->getUserNotifications(4, 10));
    $failPubResult = $examService->changeStatus(999999, 'published');
    $s1CountAfterFail = count($notifModel->getUserNotifications(4, 10));

    report(
        'TC-PUB-07',
        'Publikasi gagal (ID non-existent) tidak menghasilkan notifikasi ke siapa pun',
        $failPubResult === false && $s1CountBeforeFail === $s1CountAfterFail,
        "Status return: " . ($failPubResult ? 'true' : 'false')
    );

    // TC-PUB-08: Notifikasi tampil di Navbar Dropdown dan Inbox Siswa
    loginAs($studentUser1);
    $controller = new NotificationController();
    $dropdownRes = $controller->getDropdownData();
    $dropdownData = json_decode($dropdownRes->getContent(), true);
    $dropdownHasNotif = false;
    foreach ($dropdownData['notifications'] ?? [] as $d) {
        if ($d['title'] === 'Ujian Baru Tersedia' && str_contains($d['message'], $examTitle1)) {
            $dropdownHasNotif = true;
            break;
        }
    }

    $inboxRes = $controller->inbox();
    $inboxHtml = $inboxRes->getContent();
    $inboxHasNotif = str_contains($inboxHtml, 'Ujian Baru Tersedia') && str_contains($inboxHtml, $examTitle1);

    report(
        'TC-PUB-08',
        'Notifikasi publikasi ujian muncul di Navbar Dropdown & Inbox siswa dengan unread count valid',
        $dropdownHasNotif && $inboxHasNotif && ($dropdownData['unread_count'] ?? 0) >= 1,
        "Dropdown unread: " . ($dropdownData['unread_count'] ?? 0) . ", Inbox rendered: " . ($inboxHasNotif ? 'YES' : 'NO')
    );

    // =========================================================================
    // BAGIAN 2: EVENT 2 — PERUBAHAN JADWAL UJIAN
    // =========================================================================
    echo "\n--- 2. PENGUJIAN EVENT PERUBAHAN JADWAL UJIAN ---\n";

    // TC-SCH-01: Initial schedule creation saat ujian dibuat tidak boleh mengirim notifikasi perubahan jadwal
    $examCode2 = 'EXAM-T5-SCH-' . time();
    $initialStartTime = date('Y-m-d 08:00:00', strtotime('+1 day'));
    $initialEndTime = date('Y-m-d 10:00:00', strtotime('+1 day'));
    
    // Hitung notif sebelum createExam
    $s1CountBeforeCreate = count($notifModel->getUserNotifications(4, 20));
    $createExamResult = $examService->createExam(3, [
        'subject_id'              => 1,
        'title'                   => 'Ujian Matematika Diskrit Tahap 5',
        'code'                    => $examCode2,
        'type'                    => 'daily_test',
        'duration_minutes'        => 60,
        'passing_grade'           => 75,
        'question_bank_id'        => 1,
        'class_ids'               => [1],
        'start_time'              => $initialStartTime,
        'end_time'                => $initialEndTime,
        'grace_period_minutes'    => 15,
        'status'                  => 'published' // Dibuat langsung published
    ]);
    $examId2 = (int)($createExamResult['exam_id'] ?? 0);
    $s1CountAfterCreate = count($notifModel->getUserNotifications(4, 20));

    // Siswa 1 harus menerima 'Ujian Baru Tersedia', tapi TIDAK menerima 'Perubahan Jadwal Ujian'
    $s1NotifsAfterCreate = $notifModel->getUserNotifications(4, 20);
    $hasSchChangedOnCreate = false;
    foreach ($s1NotifsAfterCreate as $n) {
        if (str_starts_with($n['title'], 'Perubahan Jadwal Ujian') && str_contains($n['message'], 'Ujian Matematika Diskrit Tahap 5')) {
            $hasSchChangedOnCreate = true;
            break;
        }
    }

    report(
        'TC-SCH-01',
        'Pembuatan jadwal awal saat ujian baru dibuat TIDAK memicu notifikasi perubahan jadwal',
        $examId2 > 0 && !$hasSchChangedOnCreate,
        "Exam ID: {$examId2}, Has Sch Changed Notif: " . ($hasSchChangedOnCreate ? 'YES' : 'NO')
    );

    // TC-SCH-02: Perubahan jadwal yang valid mengirim notifikasi ke siswa peserta
    $newStartTime = date('Y-m-d 13:00:00', strtotime('+1 day'));
    $newEndTime = date('Y-m-d 15:00:00', strtotime('+1 day'));
    $schUpdateResult = $examService->updateSchedule($examId2, [1], $newStartTime, $newEndTime, 20);

    report(
        'TC-SCH-02',
        'Perubahan jadwal yang valid berhasil disimpan dan mengembalikan status changed = true',
        $schUpdateResult['success'] === true && $schUpdateResult['changed'] === true,
        "Changed: " . ($schUpdateResult['changed'] ? 'true' : 'false') . ", Notified: " . ($schUpdateResult['notified_count'] ?? 0)
    );

    // Verifikasi Notifikasi diterima Siswa 1
    $s1NotifsAfterUpdate = $notifModel->getUserNotifications(4, 10);
    $schNotifS1 = null;
    foreach ($s1NotifsAfterUpdate as $n) {
        if (str_starts_with($n['title'], 'Perubahan Jadwal Ujian') && str_contains($n['message'], 'Ujian Matematika Diskrit Tahap 5')) {
            $schNotifS1 = $n;
            break;
        }
    }

    report(
        'TC-SCH-03',
        'Siswa peserta menerima notifikasi perubahan jadwal dengan rincian waktu baru dan action URL /siswa/exams',
        $schNotifS1 !== null && $schNotifS1['action_url'] === '/siswa/exams',
        "Notif: " . ($schNotifS1['title'] ?? 'NONE') . " | URL: " . ($schNotifS1['action_url'] ?? 'NONE')
    );

    // TC-SCH-04: Idempotensi No-Op: Menyimpan jadwal yang identik TIDAK mengirim notifikasi
    $s1CountBeforeNoop = count($notifModel->getUserNotifications(4, 10));
    $noopResult = $examService->updateSchedule($examId2, [1], $newStartTime, $newEndTime, 20);
    $s1CountAfterNoop = count($notifModel->getUserNotifications(4, 10));

    report(
        'TC-SCH-04',
        'Menyimpan kembali jadwal yang identik (No-Op) tidak mengirim notifikasi apa pun (changed = false)',
        $noopResult['success'] === true && $noopResult['changed'] === false && $s1CountBeforeNoop === $s1CountAfterNoop,
        "Changed: " . ($noopResult['changed'] ? 'true' : 'false') . ", Notif before/after: {$s1CountBeforeNoop}/{$s1CountAfterNoop}"
    );

    // TC-SCH-05: Siswa non-peserta TIDAK menerima notifikasi perubahan jadwal
    $nonPartNotifsAfterSch = $notifModel->getUserNotifications(53, 10);
    $hasNonPartReceivedSch = false;
    foreach ($nonPartNotifsAfterSch as $n) {
        if (str_starts_with($n['title'], 'Perubahan Jadwal Ujian')) {
            $hasNonPartReceivedSch = true;
            break;
        }
    }

    report(
        'TC-SCH-05',
        'Siswa non-peserta (ID 53 di Kelas 10) tidak menerima notifikasi perubahan jadwal Kelas 1',
        !$hasNonPartReceivedSch && count($nonPartNotifsAfterSch) === 0,
        "Total notif Siswa Non-Peserta: " . count($nonPartNotifsAfterSch)
    );

    // TC-SCH-06: Perubahan jadwal gagal (waktu mulai >= waktu selesai) tidak mengubah data & tidak mengirim notifikasi
    $s1CountBeforeInvalid = count($notifModel->getUserNotifications(4, 10));
    $invalidResult = $examService->updateSchedule($examId2, [1], '2026-10-25 15:00:00', '2026-10-25 12:00:00', 15);
    $s1CountAfterInvalid = count($notifModel->getUserNotifications(4, 10));

    report(
        'TC-SCH-06',
        'Perubahan jadwal tidak valid (start >= end) ditolak dan tidak mengirim notifikasi',
        $invalidResult['success'] === false && $s1CountBeforeInvalid === $s1CountAfterInvalid,
        "Error message: " . ($invalidResult['message'] ?? 'NONE')
    );

    // TC-SCH-07: Integritas Status & Sesi: Perubahan jadwal tidak mengubah status ujian
    $exam2AfterCheck = (new ExamModel())->find($examId2);
    report(
        'TC-SCH-07',
        'Perubahan jadwal tidak merusak atau mengubah status ujian di database',
        ($exam2AfterCheck['status'] ?? '') === 'published',
        "Status saat ini: {$exam2AfterCheck['status']}"
    );

    // =========================================================================
    // BAGIAN 3: PENGUJIAN KEAMANAN, KONKURENSI & FAULT TOLERANCE
    // =========================================================================
    echo "\n--- 3. PENGUJIAN KEAMANAN, KONKURENSI & FAULT TOLERANCE ---\n";

    // TC-CONC-01: Simulasi dua worker publikasi bersamaan
    $concExamCode = 'CONC-T5-PUB-' . time();
    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, 'Ujian Uji Konkurensi Tahap 5', 1, 3, 'daily_test', 60, 75.00, 'draft', NOW(), NOW())
    ")->execute([':code' => $concExamCode]);
    $concExamId = (int)$db->lastInsertId();

    $db->prepare("
        INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, grace_period_minutes, status, created_at, updated_at)
        VALUES (:eid, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY), 15, 'scheduled', NOW(), NOW())
    ")->execute([':eid' => $concExamId]);

    $db->prepare("
        INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at)
        VALUES (:eid, 4, 1, 'not_started', NOW(), NOW())
    ")->execute([':eid' => $concExamId]);

    $phpCli = 'C:\\xampp\\php\\php.exe';
    $cmd1 = "{$phpCli} " . escapeshellarg(__FILE__) . " worker_publish_exam {$concExamId}";
    $cmd2 = "{$phpCli} " . escapeshellarg(__FILE__) . " worker_publish_exam {$concExamId}";

    $p1 = popen($cmd1, 'r');
    $p2 = popen($cmd2, 'r');
    $out1 = stream_get_contents($p1);
    $out2 = stream_get_contents($p2);
    pclose($p1);
    pclose($p2);

    // Hitung total notifikasi publikasi untuk concExam
    $concNotifs = $db->query("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = 4 AND title = 'Ujian Baru Tersedia' AND message LIKE '%Ujian Uji Konkurensi Tahap 5%'
    ")->fetchColumn();

    report(
        'TC-CONC-01',
        'Dua worker publikasi bersamaan (Concurrency Safe) menghasilkan tepat 1 notifikasi ke siswa',
        (int)$concNotifs === 1,
        "Total notifikasi yang tersimpan: {$concNotifs}"
    );

    // TC-FAULT-01: Fault tolerance: Kegagalan NotificationService tidak membatalkan update status ujian
    // Buat mock NotificationService yang melempar exception
    $failingNotifService = new class extends NotificationService {
        public function notifyUser(int $userId, string $title, string $message, string $type = self::TYPE_INFO, ?string $actionUrl = null, array $channels = [self::CHANNEL_IN_APP], array $recipientOverrides = []): array {
            throw new \RuntimeException("Simulasi kegagalan saluran notifikasi eksternal/internal.");
        }
    };

    $faultExamCode = 'CONC-T5-FLT-' . time();
    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, 'Ujian Uji Fault Tolerance Tahap 5', 1, 3, 'daily_test', 60, 75.00, 'draft', NOW(), NOW())
    ")->execute([':code' => $faultExamCode]);
    $faultExamId = (int)$db->lastInsertId();

    $faultExamService = new ExamService(null, null, null, null, null, null, null, null, null, $failingNotifService);
    $faultPubResult = $faultExamService->changeStatus($faultExamId, 'published');
    $faultExamCheck = (new ExamModel())->find($faultExamId);

    report(
        'TC-FAULT-01',
        'Fault-Tolerant: Status ujian tetap berhasil berubah menjadi published meski service notifikasi mengalami crash',
        $faultPubResult === true && ($faultExamCheck['status'] ?? '') === 'published',
        "Status di DB: {$faultExamCheck['status']}"
    );

    // TC-CONC-02: Simulasi dua worker perubahan jadwal bersamaan (Schedule Concurrency Safe)
    $concSchExamCode = 'CONC-T5-SCH-' . time();
    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, 'Ujian Uji Konkurensi Jadwal Tahap 5', 1, 3, 'daily_test', 60, 75.00, 'published', NOW(), NOW())
    ")->execute([':code' => $concSchExamCode]);
    $concSchExamId = (int)$db->lastInsertId();

    $db->prepare("
        INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, grace_period_minutes, status, created_at, updated_at)
        VALUES (:eid, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY), 15, 'scheduled', NOW(), NOW())
    ")->execute([':eid' => $concSchExamId]);

    $db->prepare("
        INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at)
        VALUES (:eid, 4, 1, 'not_started', NOW(), NOW())
    ")->execute([':eid' => $concSchExamId]);

    // Bersihkan notifikasi user 4 untuk concSchExam
    $db->prepare("DELETE FROM notifications WHERE user_id = 4 AND title LIKE '%Ujian Uji Konkurensi Jadwal%'")->execute();

    $newConcStart = date('Y-m-d 08:30:00', strtotime('+2 days'));
    $newConcEnd = date('Y-m-d 10:30:00', strtotime('+2 days'));

    $cmdSch1 = "{$phpCli} " . escapeshellarg(__FILE__) . " worker_update_schedule {$concSchExamId} 1 \"{$newConcStart}\" \"{$newConcEnd}\"";
    $cmdSch2 = "{$phpCli} " . escapeshellarg(__FILE__) . " worker_update_schedule {$concSchExamId} 1 \"{$newConcStart}\" \"{$newConcEnd}\"";

    $pSch1 = popen($cmdSch1, 'r');
    $pSch2 = popen($cmdSch2, 'r');
    $outSch1 = stream_get_contents($pSch1);
    $outSch2 = stream_get_contents($pSch2);
    pclose($pSch1);
    pclose($pSch2);

    // Hitung total notifikasi perubahan jadwal untuk concSchExam
    $concSchNotifs = (int)$db->query("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = 4 AND title LIKE '%Ujian Uji Konkurensi Jadwal Tahap 5%'
    ")->fetchColumn();

    report(
        'TC-CONC-02',
        'Dua worker update jadwal bersamaan (Schedule Concurrency Safe) menghasilkan tepat 1 notifikasi ke siswa',
        $concSchNotifs === 1,
        "Total notifikasi yang tersimpan: {$concSchNotifs}"
    );

    // =========================================================================
    // BAGIAN 3B: PENGUJIAN ANTI FALSE DEDUPLICATION (IDEMPOTENSI AUDIT TAHAP 5)
    // =========================================================================
    echo "\n--- 3B. PENGUJIAN ANTI FALSE DEDUPLICATION ---\n";

    // TC-IDEM-01: Perubahan jadwal yang sah kembali ke jam sebelumnya TIDAK keliru dianggap duplikat
    $antiDedupCode = 'EXAM-T5-REV-' . time();
    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, 'Ujian Revisi Jadwal Khusus', 1, 3, 'daily_test', 60, 75.00, 'published', NOW(), NOW())
    ")->execute([':code' => $antiDedupCode]);
    $antiDedupExamId = (int)$db->lastInsertId();

    $timeSlotA_start = date('Y-m-d 08:00:00', strtotime('+3 days'));
    $timeSlotA_end = date('Y-m-d 10:00:00', strtotime('+3 days'));
    $timeSlotB_start = date('Y-m-d 13:00:00', strtotime('+3 days'));
    $timeSlotB_end = date('Y-m-d 15:00:00', strtotime('+3 days'));

    // 1. Initial schedule
    $examService->updateSchedule($antiDedupExamId, [1], $timeSlotA_start, $timeSlotA_end, 15);
    // 2. Change 1: Pindah ke Slot B (Jam 13:00) -> Siswa dapat notifikasi
    $resSlotB = $examService->updateSchedule($antiDedupExamId, [1], $timeSlotB_start, $timeSlotB_end, 15);
    // Simulasikan waktu berlalu (melewati 300 detik deduplication window)
    $db->prepare("UPDATE notifications SET created_at = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE user_id = 4 AND title LIKE '%Ujian Revisi Jadwal Khusus%'")->execute();

    // 3. Change 2: Guru memindahkan kembali ke Slot A (Jam 08:00) secara sah
    $resSlotABack = $examService->updateSchedule($antiDedupExamId, [1], $timeSlotA_start, $timeSlotA_end, 15);

    $totalRevisions = (int)$db->query("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = 4 AND title LIKE '%Ujian Revisi Jadwal Khusus%'
    ")->fetchColumn();

    report(
        'TC-IDEM-01',
        'Revisi jadwal sah yang kembali ke waktu sebelumnya TIDAK keliru dianggap duplikat (Anti-False Dedup)',
        $resSlotABack['changed'] === true && $resSlotABack['notified_count'] > 0 && $totalRevisions === 2,
        "Total notifikasi revisi sah diterima siswa: {$totalRevisions}"
    );

    // TC-IDEM-02: Dua ujian berbeda dengan judul serupa tidak saling menekan (No False Title Collision)
    $similarExamCodeA = 'EXAM-T5-SIMA-' . time();
    $similarExamCodeB = 'EXAM-T5-SIMB-' . time();

    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, 'Ujian Kimia Organik', 1, 3, 'daily_test', 60, 75.00, 'draft', NOW(), NOW())
    ")->execute([':code' => $similarExamCodeA]);
    $simExamIdA = (int)$db->lastInsertId();

    $db->prepare("
        INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (:code, 'Ujian Kimia Organik Lanjutan', 1, 3, 'daily_test', 60, 75.00, 'draft', NOW(), NOW())
    ")->execute([':code' => $similarExamCodeB]);
    $simExamIdB = (int)$db->lastInsertId();

    $db->prepare("INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, grace_period_minutes, status) VALUES (:eid, 1, NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 15, 'scheduled')")->execute([':eid' => $simExamIdA]);
    $db->prepare("INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, grace_period_minutes, status) VALUES (:eid, 1, NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 15, 'scheduled')")->execute([':eid' => $simExamIdB]);

    $examService->changeStatus($simExamIdA, 'published');
    $examService->changeStatus($simExamIdB, 'published');

    $notifSimA = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = 4 AND title = 'Ujian Baru Tersedia' AND message = 'Ujian Ujian Kimia Organik telah dipublikasikan dan tersedia untuk dikerjakan.'")->fetchColumn();
    $notifSimB = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = 4 AND title = 'Ujian Baru Tersedia' AND message = 'Ujian Ujian Kimia Organik Lanjutan telah dipublikasikan dan tersedia untuk dikerjakan.'")->fetchColumn();

    report(
        'TC-IDEM-02',
        'Dua ujian dengan judul saling beririsan tidak saling memblokir notifikasi (Exact Matching)',
        $notifSimA === 1 && $notifSimB === 1,
        "Notif Exam A: {$notifSimA}, Notif Exam B: {$notifSimB}"
    );


    // =========================================================================
    // BAGIAN 4: VERIFIKASI ISOLASI & INTEGRITAS DATABASE OPERASIONAL
    // =========================================================================
    echo "\n--- 4. VERIFIKASI INTEGRITAS DATABASE OPERASIONAL ---\n";

    // TC-DB-01: Verifikasi lingkungan pengujian tetap terisolasi
    report(
        'TC-DB-01',
        'Seluruh pengujian dijalankan strictly pada database pengujian (ujian_dual_kamera_test)',
        $currentDb === 'ujian_dual_kamera_test',
        "Active DB: {$currentDb}"
    );

    // TC-DB-02: Verifikasi database operasional ujian_dual_kamera utuh tanpa kontaminasi
    $opDb = new PDO('mysql:host=127.0.0.1;dbname=ujian_dual_kamera;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $opTestNotifs = (int)$opDb->query("
        SELECT COUNT(*) FROM notifications 
        WHERE title LIKE '%Tahap 5%' 
           OR title LIKE '%Uji Konkurensi%' 
           OR title LIKE '%Revisi Jadwal Khusus%' 
           OR title LIKE '%Kimia Organik%'
           OR message LIKE '%Tahap 5%'
           OR message LIKE '%Uji Konkurensi%'
           OR message LIKE '%Revisi Jadwal Khusus%'
           OR message LIKE '%Kimia Organik%'
    ")->fetchColumn();
    $opExam39 = $opDb->query("SELECT id, title, code, status FROM exams WHERE id = 39")->fetch();
    $opToken = $opDb->query("SELECT token FROM exam_tokens WHERE exam_id = 39 AND is_active = 1 LIMIT 1")->fetchColumn();

    report(
        'TC-DB-02',
        'Integritas Database Operasional: Database ujian_dual_kamera 100% bersih tanpa data uji coba',
        $opTestNotifs === 0 && $opExam39 !== false && ($opExam39['code'] ?? '') === 'EXAM-MAT12-PAS' && $opToken === 'PASMAT1',
        "Op Test Notif count: {$opTestNotifs}, Exam 39 code: " . ($opExam39['code'] ?? 'NOT FOUND') . ", Token: {$opToken}"
    );

} finally {
    // Teardown: Bersihkan fixture pengujian dari database test
    $db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 14, 53, 55, 3, 2, 99999)");
    $db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");
    $db->exec("DELETE FROM exams WHERE code LIKE 'EXAM-T5-%' OR code LIKE 'CONC-T5-%'");
}

echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN TAHAP 5: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
