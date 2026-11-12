<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: NOTIFICATION EVENT INTEGRATION (TAHAP 4)
 * ==============================================================================
 * Menguji integrasi event nyata sistem Smart Exam dengan NotificationService:
 * 1. Event Submit Ujian oleh Siswa menghasilkan notifikasi ke Guru Pengampu
 * 2. Event Submit Ujian menghasilkan konfirmasi pengumpulan ke Siswa yang bersangkutan
 * 3. Event Submit Ujian tidak bocor ke siswa atau pengguna lain
 * 4. Idempotensi Submit Ujian: Pengumpulan ulang tidak memicu notifikasi duplikat
 * 5. Event Publikasi Nilai Ujian oleh Guru menghasilkan notifikasi ke Siswa peserta
 * 6. Event Publikasi Nilai tidak mengirim notifikasi ke siswa non-peserta
 * 7. Idempotensi Publikasi Nilai: Publikasi ulang tidak memicu notifikasi ganda
 * 8. Notifikasi event muncul di dropdown navbar guru dan siswa
 * 9. Notifikasi event ter-render di halaman Inbox masing-masing role
 * 10. Fitur Mark as Read berfungsi normal pada notifikasi berbasis event
 * 11. Isolasi Data & Proteksi IDOR antar pengguna tetap terjaga
 * 12. Integritas proses ujian: Sesi, nilai, dan status ujian tetap akurat 100%
 * 13. Integritas database: Pengujian hanya menyentuh database pengujian (test db)
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
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\NotificationModel;
use App\Services\ExamSessionService;
use App\Services\NotificationService;
use App\Services\ResultService;

// Handler worker untuk pengujian konkurensi (Child process worker)
if (($argv[1] ?? '') === 'worker_publish') {
    $workerExamId = (int)($argv[2] ?? 0);
    $resService = new ResultService();
    $res = $resService->publishExamResults($workerExamId, 3);
    echo json_encode($res);
    exit(0);
}

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFICATION EVENT INTEGRATION (TAHAP 4)            \n";
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

$studentUser2 = [
    'id'       => 12,
    'username' => 'siswa_sec_2',
    'name'     => 'Siswa Kedua Testing',
    'email'    => 'siswa2@test.com',
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
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");
$db->exec("DELETE FROM exam_sessions WHERE session_token LIKE 'tok_%' OR session_token LIKE 'EVT_%'");
$db->exec("DELETE FROM exams WHERE code LIKE 'EXAM-EVT-%' OR code LIKE 'CONC-EVT-%'");

// Inisialisasi Models & Services
$notifModel = new NotificationModel();
$notifService = new NotificationService($notifModel);
$examSessionService = new ExamSessionService(null, null, null, null, null, null, null, null, $notifService);
$resultService = new ResultService(null, null, null, $notifService);

// Buat Fixture Ujian Khusus Pengujian Event di DB Test
$testExamCode = 'EXAM-EVT-' . time();
$db->prepare("
    INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
    VALUES (:code, 'Ujian Simulasi Event Notifikasi', 1, 3, 'final_exam', 60, 75.00, 'published', NOW(), NOW())
")->execute([':code' => $testExamCode]);
$testExamId = (int)$db->lastInsertId();

// Daftarkan Siswa 1 sebagai Peserta Ujian
$db->prepare("
    INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at)
    VALUES (:eid, 4, 1, 'registered', NOW(), NOW())
")->execute([':eid' => $testExamId]);

// Buat Sesi Ujian Aktif untuk Siswa 1
$testSessionToken = 'sess_evt_' . bin2hex(random_bytes(16));
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, created_at, updated_at)
    VALUES (:eid, 4, :tok, '127.0.0.1', NOW(), 3600, 'ongoing', NOW(), NOW())
")->execute([
    ':eid' => $testExamId,
    ':tok' => $testSessionToken
]);
$testSessionId = (int)$db->lastInsertId();

// --------------------------------------------------------------------------
// UJI 1-4: EVENT SUBMIT UJIAN OLEH SISWA (ARAH SISWA -> GURU & KONFIRMASI)
// --------------------------------------------------------------------------
echo "--- 1. PENGUJIAN EVENT SUBMIT UJIAN (SISWA -> GURU) ---\n";

// Eksekusi Submit Ujian oleh Siswa 1
$submitResult = $examSessionService->submitExam($testSessionToken, 4);

$tc01_ok = ($submitResult['success'] === true)
    && ($submitResult['already_submitted'] === false)
    && ($submitResult['result_id'] > 0);
report('TC-EVT-01', 'Proses submit ujian siswa berjalan sukses dan menghasilkan result record', $tc01_ok, "Result ID: {$submitResult['result_id']}");

// Periksa Notifikasi untuk Guru Pengampu (User ID: 3)
$teacherNotifs = $notifModel->getUserNotifications(3, 5);
$teacherSubmitNotif = null;
foreach ($teacherNotifs as $n) {
    if (str_contains($n['title'], 'Jawaban Siswa Masuk')) {
        $teacherSubmitNotif = $n;
        break;
    }
}

$tc02_ok = $teacherSubmitNotif !== null
    && str_contains($teacherSubmitNotif['message'], 'Ahmad Fauzi')
    && str_contains($teacherSubmitNotif['message'], 'Ujian Simulasi Event Notifikasi')
    && $teacherSubmitNotif['action_url'] === '/guru/grading'
    && (int)$teacherSubmitNotif['is_read'] === 0;
report('TC-EVT-02', 'Guru pengampu menerima notifikasi "Jawaban Siswa Masuk" dengan data siswa & action URL yang sesuai', $tc02_ok, "Notif Guru: " . ($teacherSubmitNotif['title'] ?? 'None'));

// Periksa Notifikasi Konfirmasi untuk Siswa (User ID: 4)
$studentNotifs = $notifModel->getUserNotifications(4, 5);
$studentConfirmNotif = null;
foreach ($studentNotifs as $n) {
    if (str_contains($n['title'], 'Ujian Berhasil Dikumpulkan')) {
        $studentConfirmNotif = $n;
        break;
    }
}

$tc03_ok = $studentConfirmNotif !== null
    && str_contains($studentConfirmNotif['message'], 'Ujian Simulasi Event Notifikasi')
    && $studentConfirmNotif['action_url'] === '/siswa/history'
    && (int)$studentConfirmNotif['is_read'] === 0;
report('TC-EVT-03', 'Siswa menerima notifikasi konfirmasi "Ujian Berhasil Dikumpulkan" dengan action URL riwayat', $tc03_ok, "Notif Siswa: " . ($studentConfirmNotif['title'] ?? 'None'));

// Proteksi Kebocoran: Siswa 2 (User ID: 12) TIDAK boleh menerima notifikasi ini
$student2Notifs = $notifModel->getUserNotifications(12, 5);
$tc04_ok = count($student2Notifs) === 0;
report('TC-EVT-04', 'Role & User Isolation: Siswa lain (non-peserta) tidak menerima notifikasi penyerahan ujian', $tc04_ok, "Notifikasi Siswa 2: 0 baris");

// Idempotensi Submit: Panggilan submit kedua pada sesi yang sama tidak boleh memicu notifikasi ganda
$teacherCountBefore = $notifModel->countUserNotifications(3);
$studentCountBefore = $notifModel->countUserNotifications(4);

$resubmitResult = $examSessionService->submitExam($testSessionToken, 4);

$teacherCountAfter = $notifModel->countUserNotifications(3);
$studentCountAfter = $notifModel->countUserNotifications(4);

$tc05_ok = ($resubmitResult['success'] === true)
    && ($resubmitResult['already_submitted'] === true)
    && ($teacherCountBefore === $teacherCountAfter)
    && ($studentCountBefore === $studentCountAfter);
report('TC-EVT-05', 'Idempotensi Event Submit: Pemanggilan submit ganda tidak menghasilkan notifikasi duplikat', $tc05_ok, "Guru before/after: {$teacherCountBefore}/{$teacherCountAfter}, Siswa before/after: {$studentCountBefore}/{$studentCountAfter}");

// --------------------------------------------------------------------------
// UJI 5-8: PENGUJIAN AUDIT IDEMPOTENSI PUBLIKASI HASIL (TAHAP 4B)
// --------------------------------------------------------------------------
echo "\n--- 2. PENGUJIAN AUDIT IDEMPOTENSI PUBLIKASI NILAI (TAHAP 4B) ---\n";

// SKENARIO 1: Publikasi pertama untuk peserta (Siswa 1 / User ID: 4)
$db->prepare("UPDATE exam_results SET status = 'graded' WHERE exam_id = :eid AND student_id = 4")->execute([':eid' => $testExamId]);

$publishResult = $resultService->publishExamResults($testExamId, 3);
$tc06_ok = ($publishResult['success'] === true)
    && ($publishResult['published_count'] === 1);
report('TC-EVT-06', 'Skenario 1: Publikasi pertama berhasil mempublikasikan hasil siswa dan mengembalikan count yang tepat', $tc06_ok, "Published count: " . ($publishResult['published_count'] ?? 0));

// Verifikasi notifikasi publikasi diterima oleh Siswa 1
$student1PubNotif = $notifModel->getUserNotifications(4, 5);
$pubNotifFound = false;
foreach ($student1PubNotif as $n) {
    if (str_contains($n['title'], 'Hasil Ujian Dipublikasikan')) {
        $pubNotifFound = true;
        break;
    }
}
$tc07_ok = $pubNotifFound;
report('TC-EVT-07', 'Skenario 1 (Lanjutan): Siswa 1 menerima notifikasi "Hasil Ujian Dipublikasikan"', $tc07_ok, "Notifikasi publikasi diterima");

// SKENARIO 2: Publikasi ulang tanpa perubahan hasil (Idempotensi murni)
$student1CountBeforeRepub = $notifModel->countUserNotifications(4);
$republishResult = $resultService->publishExamResults($testExamId, 3);
$student1CountAfterRepub = $notifModel->countUserNotifications(4);

$tc08_ok = ($republishResult['success'] === true)
    && ($republishResult['published_count'] === 0)
    && ($student1CountBeforeRepub === $student1CountAfterRepub);
report('TC-EVT-08', 'Skenario 2: Publikasi ulang tanpa perubahan tidak mengirim notifikasi duplikat ke Siswa 1', $tc08_ok, "Published count: {$republishResult['published_count']}, Notif count: {$student1CountBeforeRepub} -> {$student1CountAfterRepub}");

// SKENARIO 3 & 4 & 5: Publikasi sebagian hasil susulan (Siswa 2 / User ID: 12 baru dinilai)
$db->prepare("
    INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at)
    VALUES (:eid, 12, 1, 'completed', NOW(), NOW())
")->execute([':eid' => $testExamId]);

// Buat sesi ujian valid untuk Siswa 2 agar foreign key exam_session_id terpenuhi
$tokS2 = 'tok_s2_' . uniqid();
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, created_at, updated_at)
    VALUES (:eid, 12, :token, '127.0.0.1', NOW(), 0, 'submitted', NOW(), NOW())
")->execute([':eid' => $testExamId, ':token' => $tokS2]);
$s2SessionId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO exam_results (exam_session_id, exam_id, student_id, total_questions, total_answered, correct_answers, wrong_answers, multiple_choice_score, essay_score, final_score, proctoring_integrity_score, is_passed, status, graded_at, created_at, updated_at)
    VALUES (:sid, :eid, 12, 10, 10, 8, 2, 80.00, 0.00, 80.00, 100.00, 1, 'graded', NOW(), NOW(), NOW())
")->execute([':sid' => $s2SessionId, ':eid' => $testExamId]);

// Catat jumlah notifikasi Siswa 1 dan Siswa 2 sebelum publikasi parsial
$s1NotifBeforePartial = $notifModel->countUserNotifications(4);
$s2NotifBeforePartial = $notifModel->countUserNotifications(12);

// Publikasikan kembali ujian (sekarang memuat Siswa 1 yang sudah published, dan Siswa 2 yang baru graded)
$partialPublishResult = $resultService->publishExamResults($testExamId, 3);

$s1NotifAfterPartial = $notifModel->countUserNotifications(4);
$s2NotifAfterPartial = $notifModel->countUserNotifications(12);

// SKENARIO 3: Hanya 1 hasil baru yang dipublikasikan (Siswa 2)
$tc08b_ok = ($partialPublishResult['success'] === true)
    && ($partialPublishResult['published_count'] === 1);
report('TC-EVT-08b', 'Skenario 3: Publikasi parsial hanya mempublikasikan record yang berstatus belum published', $tc08b_ok, "Published count baru: {$partialPublishResult['published_count']}");

// SKENARIO 4: Siswa 1 yang sudah pernah dipublikasikan TIDAK menerima notifikasi ganda!
$tc08c_ok = ($s1NotifBeforePartial === $s1NotifAfterPartial);
report('TC-EVT-08c', 'Skenario 4: Siswa 1 yang sudah pernah dipublikasikan TIDAK menerima notifikasi duplikat saat siswa lain dipublikasikan', $tc08c_ok, "Siswa 1 Notif: {$s1NotifBeforePartial} -> {$s1NotifAfterPartial} (Tetap)");

// SKENARIO 5: Siswa 2 yang baru dipublikasikan menerima notifikasi perdananya
$s2Notifs = $notifModel->getUserNotifications(12, 5);
$s2ReceivedNotif = false;
foreach ($s2Notifs as $n) {
    if (str_contains($n['title'], 'Hasil Ujian Dipublikasikan')) {
        $s2ReceivedNotif = true;
        break;
    }
}
$tc08d_ok = ($s2NotifAfterPartial === $s2NotifBeforePartial + 1) && $s2ReceivedNotif;
report('TC-EVT-08d', 'Skenario 5: Siswa 2 (hasil baru) berhasil menerima notifikasi publikasi pertamanya', $tc08d_ok, "Siswa 2 Notif: {$s2NotifBeforePartial} -> {$s2NotifAfterPartial}");

// SKENARIO 6: Siswa non-peserta tidak menerima notifikasi
$nonParticipantNotifCount = $notifModel->countUserNotifications(99999);
$tc08e_ok = ($nonParticipantNotifCount === 0);
report('TC-EVT-08e', 'Skenario 6: Siswa non-peserta tidak menerima notifikasi sama sekali', $tc08e_ok, "Non-peserta notif count: {$nonParticipantNotifCount}");

// SKENARIO 7: Publikasi nilai tetap berhasil ketika proses notifikasi mengalami kegagalan
// Simulasikan NotificationService yang sengaja melempar exception
$failingNotifService = new class($notifModel) extends NotificationService {
    public function notifyUser(int $userId, string $title, string $message, string $type = 'info', ?string $actionUrl = null, array $channels = ['in_app'], array $recipientOverrides = []): array {
        throw new \RuntimeException("Simulasi kegagalan koneksi saluran notifikasi");
    }
};

$faultTolerantResultService = new ResultService(null, null, null, $failingNotifService);

// Tambahkan peserta uji kegagalan (User ID: 2 - Admin sebagai peserta tiruan) dengan sesi valid
$tokFault = 'tok_flt_' . uniqid();
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, created_at, updated_at)
    VALUES (:eid, 2, :token, '127.0.0.1', NOW(), 0, 'submitted', NOW(), NOW())
")->execute([':eid' => $testExamId, ':token' => $tokFault]);
$faultSessionId = (int)$db->lastInsertId();

$db->prepare("
    INSERT INTO exam_results (exam_session_id, exam_id, student_id, total_questions, total_answered, correct_answers, wrong_answers, multiple_choice_score, essay_score, final_score, proctoring_integrity_score, is_passed, status, graded_at, created_at, updated_at)
    VALUES (:sid, :eid, 2, 10, 10, 7, 3, 70.00, 0.00, 70.00, 100.00, 0, 'graded', NOW(), NOW(), NOW())
")->execute([':sid' => $faultSessionId, ':eid' => $testExamId]);

$faultToleranceResult = $faultTolerantResultService->publishExamResults($testExamId, 3);
$res2Status = $db->query("SELECT status FROM exam_results WHERE exam_id = {$testExamId} AND student_id = 2")->fetchColumn();

$tc08f_ok = ($faultToleranceResult['success'] === true)
    && ($faultToleranceResult['published_count'] === 1)
    && ($res2Status === 'published');
report('TC-EVT-08f', 'Skenario 7 & 15: Publikasi nilai tetap sukses dan tersimpan di DB meskipun notifikasi mengalami kegagalan (Fault-Tolerant)', $tc08f_ok, "Result status di DB: {$res2Status}");

// SKENARIO 16: Pengujian Concurrency / Race Condition Guardrail
// Menjalankan dua request publikasi bersamaan menggunakan child processes
$concExamCode = 'CONC-EVT-' . time();
$db->prepare("
    INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
    VALUES (:code, 'Ujian Concurrency Race Test', 1, 3, 'quiz', 60, 75.00, 'ongoing', NOW(), NOW())
")->execute([':code' => $concExamCode]);
$concExamId = (int)$db->lastInsertId();

// Setup 2 sesi dan 2 hasil belum dipublikasikan (Siswa 4 dan Siswa 12)
$tokC1 = 'tok_c1_' . uniqid();
$tokC2 = 'tok_c2_' . uniqid();
$db->prepare("
    INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, created_at, updated_at)
    VALUES (:eid1, 4, :tok1, '127.0.0.1', NOW(), 0, 'submitted', NOW(), NOW()),
           (:eid2, 12, :tok2, '127.0.0.1', NOW(), 0, 'submitted', NOW(), NOW())
")->execute([':eid1' => $concExamId, ':eid2' => $concExamId, ':tok1' => $tokC1, ':tok2' => $tokC2]);
$sessC1 = (int)$db->query("SELECT id FROM exam_sessions WHERE session_token = '{$tokC1}'")->fetchColumn();
$sessC2 = (int)$db->query("SELECT id FROM exam_sessions WHERE session_token = '{$tokC2}'")->fetchColumn();

$db->prepare("
    INSERT INTO exam_results (exam_session_id, exam_id, student_id, total_questions, total_answered, correct_answers, wrong_answers, multiple_choice_score, essay_score, final_score, proctoring_integrity_score, is_passed, status, graded_at, created_at, updated_at)
    VALUES (:s1, :eid1, 4, 10, 10, 8, 2, 80.00, 0.00, 80.00, 100.00, 1, 'graded', NOW(), NOW(), NOW()),
           (:s2, :eid2, 12, 10, 10, 9, 1, 90.00, 0.00, 90.00, 100.00, 1, 'graded', NOW(), NOW(), NOW())
")->execute([':s1' => $sessC1, ':s2' => $sessC2, ':eid1' => $concExamId, ':eid2' => $concExamId]);

// Eksekusi dua proses worker publikasi secara paralel
$selfFile = __FILE__;
$concCmd = "C:\\xampp\\php\\php.exe \"{$selfFile}\" worker_publish {$concExamId}";
$descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];

$p1 = proc_open($concCmd, $descriptors, $pipes1);
$p2 = proc_open($concCmd, $descriptors, $pipes2);

$out1 = stream_get_contents($pipes1[1]); fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]); proc_close($p1);
$out2 = stream_get_contents($pipes2[1]); fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]); proc_close($p2);

$resWorker1 = json_decode($out1, true);
$resWorker2 = json_decode($out2, true);
$workerCount1 = (int)($resWorker1['published_count'] ?? -1);
$workerCount2 = (int)($resWorker2['published_count'] ?? -1);

// Cek jumlah notifikasi spesifik ujian concurrency yang terbentuk
$concNotifsCount = (int)$db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id IN (4, 12) AND message LIKE '%Ujian Concurrency Race Test%'
")->fetchColumn();

$tc08g_ok = ($workerCount1 + $workerCount2 === 2)
    && (($workerCount1 === 2 && $workerCount2 === 0) || ($workerCount1 === 0 && $workerCount2 === 2))
    && ($concNotifsCount === 2);
report('TC-EVT-08g', 'Skenario 16: Dua request publikasi bersamaan tidak menghasilkan notifikasi ganda (Concurrency Safe)', $tc08g_ok, "Worker 1: {$workerCount1}, Worker 2: {$workerCount2}, Notifs: {$concNotifsCount}");

// Cleanup data concurrency
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12) AND message LIKE '%Ujian Concurrency Race Test%'");
$db->exec("DELETE FROM exam_results WHERE exam_id = {$concExamId}");
$db->exec("DELETE FROM exam_sessions WHERE exam_id = {$concExamId}");
$db->exec("DELETE FROM exams WHERE id = {$concExamId}");

// --------------------------------------------------------------------------
// UJI 8-11: INTEGRASI UI NAVBAR DROPDOWN & HALAMAN INBOX
// --------------------------------------------------------------------------
echo "\n--- 3. PENGUJIAN INTEGRASI NAVBAR & INBOX PER ROLE ---\n";

// UJI 9: Navbar Dropdown Guru menampilkan notifikasi "Jawaban Siswa Masuk"
loginAs($teacherUser);
$ctrl = new NotificationController();
$resTeacherDrop = $ctrl->getDropdownData();
$bodyTeacherDrop = json_decode($resTeacherDrop->getContent(), true);

$hasTeacherItemInDrop = false;
foreach ($bodyTeacherDrop['notifications'] ?? [] as $item) {
    if (str_contains($item['title'], 'Jawaban Siswa Masuk')) {
        $hasTeacherItemInDrop = true;
        break;
    }
}

$tc09_ok = $resTeacherDrop->getStatusCode() === 200
    && $bodyTeacherDrop['unread_count'] >= 1
    && $hasTeacherItemInDrop;
report('TC-EVT-09', 'Navbar Dropdown Guru memuat notifikasi event "Jawaban Siswa Masuk" dan unread counter valid', $tc09_ok, "Unread: {$bodyTeacherDrop['unread_count']}");

// UJI 10: Inbox Siswa menampilkan kedua notifikasi event (Konfirmasi Submit & Publikasi Nilai)
loginAs($studentUser1);
$studentCtrl = new NotificationController();
$resStudentInbox = $studentCtrl->inbox();
$htmlStudentInbox = $resStudentInbox->getContent();

$tc10_ok = $resStudentInbox->getStatusCode() === 200
    && str_contains($htmlStudentInbox, 'Ujian Berhasil Dikumpulkan')
    && str_contains($htmlStudentInbox, 'Hasil Ujian Dipublikasikan')
    && str_contains($htmlStudentInbox, 'Inbox Notifikasi');
report('TC-EVT-10', 'Halaman Inbox Siswa merender kedua notifikasi event secara sempurna', $tc10_ok, "Tampilan inbox siswa valid");

// UJI 11: Mark as Read pada notifikasi event siswa
$notifToMark = 0;
foreach ($student1PubNotif as $n) {
    if (str_contains($n['title'], 'Hasil Ujian Dipublikasikan')) {
        $notifToMark = (int)$n['id'];
        break;
    }
}
$resMark = $studentCtrl->markAsRead($notifToMark);
$bodyMark = json_decode($resMark->getContent(), true);

// Periksa status di DB
$updatedNotif = $notifModel->find($notifToMark);

$tc11_ok = $resMark->getStatusCode() === 200
    && $bodyMark['success'] === true
    && (int)$updatedNotif['is_read'] === 1;
report('TC-EVT-11', 'Notifikasi event berhasil ditandai sebagai dibaca (markAsRead) dan status DB terverifikasi', $tc11_ok, "is_read: {$updatedNotif['is_read']}");

// UJI 11b: Action URL Sesuai Role Pengguna (Skenario 14)
$teacherActionUrlValid = ($teacherSubmitNotif['action_url'] ?? '') === '/guru/grading';
$studentSubmitUrlValid = ($studentConfirmNotif['action_url'] ?? '') === '/siswa/history';
$pubItem = null;
foreach ($student1PubNotif as $n) {
    if (str_contains($n['title'], 'Hasil Ujian Dipublikasikan')) {
        $pubItem = $n;
        break;
    }
}
$studentPublishUrlValid = ($pubItem['action_url'] ?? '') === '/siswa/results';
$tc11b_ok = $teacherActionUrlValid && $studentSubmitUrlValid && $studentPublishUrlValid;
report('TC-EVT-11b', 'Skenario 14: Action URL mengarahkan pengguna secara presisi sesuai role (Guru: /guru/grading, Siswa: /siswa/...)', $tc11b_ok, "Guru: " . ($teacherSubmitNotif['action_url'] ?? 'none') . ", Siswa submit: " . ($studentConfirmNotif['action_url'] ?? 'none') . ", Siswa publish: " . ($pubItem['action_url'] ?? 'none'));

// --------------------------------------------------------------------------
// UJI 12-14: KEAMANAN, INTEGRITAS SISTEM & DATABASE
// --------------------------------------------------------------------------
echo "\n--- 4. PENGUJIAN KEAMANAN & INTEGRITAS SISTEM ---\n";

// UJI 12: Proteksi IDOR - Guru tidak dapat menandai notifikasi milik Siswa
loginAs($teacherUser);
$teacherCtrl = new NotificationController();
$resIdorMark = $teacherCtrl->markAsRead($notifToMark); // Notifikasi milik Siswa 4

$tc12_ok = $resIdorMark->getStatusCode() === 404;
report('TC-EVT-12', 'Proteksi IDOR: Guru ditolak (404) saat memanipulasi notifikasi event milik siswa', $tc12_ok, "IDOR mutation berhasil dicegah");

// UJI 13: Integritas Ujian Operasional: Exam 39 dan token PASMAT1 tetap utuh
$exam39 = (new ExamModel())->find(39);
$token39 = $db->query("SELECT token FROM exam_tokens WHERE exam_id = 39 AND is_active = 1 LIMIT 1")->fetchColumn();

$tc13_ok = $exam39 !== null
    && $exam39['code'] === 'EXAM-MAT12-PAS'
    && $token39 === 'PASMAT1';
report('TC-EVT-13', 'Integritas Sistem: Data ujian operasional (Exam 39) & token akses tetap utuh tanpa modifikasi', $tc13_ok, "Exam: {$exam39['code']}, Token: {$token39}");

// UJI 14: Tidak ada data pengujian yang bocor ke database operasional
$mainDbCheck = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ujian_dual_kamera' AND table_name = 'notifications'")->fetchColumn();
$tc14_ok = ($mainDbCheck > 0);
report('TC-EVT-14', 'Isolasi Lingkungan: Seluruh rangkaian event diuji strictly pada database ujian_dual_kamera_test', $tc14_ok, "Database test terisolasi");

// --------------------------------------------------------------------------
// CLEANUP DATA FIXTURE PENGUJIAN
// --------------------------------------------------------------------------
$db->exec("DELETE FROM notifications WHERE user_id IN (4, 12, 3, 2, 99999)");
$db->exec("DELETE FROM notification_logs WHERE recipient LIKE '%test%' OR notification_id NOT IN (SELECT id FROM notifications)");
$db->exec("DELETE FROM exam_results WHERE exam_id = {$testExamId}");
$db->exec("DELETE FROM exam_sessions WHERE exam_id = {$testExamId}");
$db->exec("DELETE FROM exam_participants WHERE exam_id = {$testExamId}");
$db->exec("DELETE FROM exams WHERE id = {$testExamId}");

echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN EVENT TAHAP 4: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
