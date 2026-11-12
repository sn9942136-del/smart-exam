<?php
declare(strict_types=1);

$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamSessionModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\ExamSessionService;
use App\Services\NotificationService;

echo "====================================================================\n";
echo "   TEST SUITE: NOTIFIKASI SUBMIT UJIAN DEDUPLIKASI & IDEMPOTENSI    \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Terkoneksi: {$currentDb}\n";
if ($currentDb !== 'ujian_dual_kamera_test') {
    die("FATAL: Bukan database test!\n");
}

$notifModel = new NotificationModel();
$notifService = new NotificationService($notifModel);
$sessionService = new ExamSessionService(null, null, null, null, null, null, null, null, $notifService, $notifModel);

$pass = 0;
$fail = 0;

function runTestCase(string $title, callable $fn, &$pass, &$fail): void {
    try {
        $ok = $fn();
        if ($ok === true) {
            echo " [PASS] {$title}\n";
            $pass++;
        } else {
            echo " [FAIL] {$title}: assertion returned false\n";
            $fail++;
        }
    } catch (\Throwable $e) {
        echo " [FAIL] {$title}: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
        $fail++;
    }
}

// Setup data pengujian terisolasi
$teacherId1 = 3; // Guru Budi
$teacherId2 = 50; // Guru Dheandra
$studentId1 = 4; // Ahmad Fauzi
$studentId2 = 12; // Siswa Kedua

// Bersihkan notifikasi uji coba khusus test suite ini
$testTitleExam1 = "Ujian Biologi Deduplikasi Test " . time();
$testTitleExam2 = "Ujian Fisika Deduplikasi Test " . time();

// Buat Ujian 1 (Guru 1) dan Ujian 2 (Guru 2)
$db->exec("INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
           VALUES ('EXAM-DEDUP-1', '{$testTitleExam1}', 1, {$teacherId1}, 'final_exam', 60, 75, 'published', NOW(), NOW())");
$examId1 = (int)$db->lastInsertId();

$db->exec("INSERT INTO exams (code, title, subject_id, teacher_id, type, duration_minutes, passing_grade, status, created_at, updated_at)
           VALUES ('EXAM-DEDUP-2', '{$testTitleExam2}', 2, {$teacherId2}, 'final_exam', 60, 75, 'published', NOW(), NOW())");
$examId2 = (int)$db->lastInsertId();

// Helper untuk membuat sesi ujian
function makeSession(int $examId, int $studentId): string {
    global $db;
    $tok = 'tok_dedup_' . bin2hex(random_bytes(10));
    $db->prepare("INSERT INTO exam_sessions (exam_id, student_id, session_token, ip_address, start_time, remaining_seconds, status, created_at, updated_at)
                  VALUES (:eid, :sid, :tok, '127.0.0.1', NOW(), 3600, 'ongoing', NOW(), NOW())")
       ->execute([':eid' => $examId, ':sid' => $studentId, ':tok' => $tok]);
    return $tok;
}

try {
    // -------------------------------------------------------------------------
    // TEST 1: Penyerahan Pertama (Siswa 1 pada Ujian 1) -> Tepat 1 Notifikasi ke Guru
    // -------------------------------------------------------------------------
    runTestCase("1. Penyerahan pertama oleh Siswa 1 memicu tepat 1 notifikasi ke Guru 1", function() use ($sessionService, $notifModel, $examId1, $studentId1, $teacherId1, $testTitleExam1) {
        $tok = makeSession($examId1, $studentId1);
        $res = $sessionService->submitExam($tok, $studentId1);
        
        $notifs = $notifModel->getUserNotifications($teacherId1, 10);
        $found = 0;
        foreach ($notifs as $n) {
            if ($n['title'] === 'Jawaban Siswa Masuk' && str_contains($n['message'], $testTitleExam1)) {
                $found++;
            }
        }
        return $res['success'] === true && $found === 1;
    }, $pass, $fail);

    // -------------------------------------------------------------------------
    // TEST 2: Penyerahan Kedua untuk Siswa & Ujian yang Sama -> TIDAK Duplikat
    // -------------------------------------------------------------------------
    runTestCase("2. Penyerahan kedua (sesi baru) untuk Siswa 1 & Ujian 1 TIDAK membuat notifikasi duplikat", function() use ($sessionService, $notifModel, $examId1, $studentId1, $teacherId1, $testTitleExam1) {
        // Buat sesi kedua untuk siswa 1 pada ujian 1 (misal timeout/remedial/reconnect)
        $tok2 = makeSession($examId1, $studentId1);
        $res = $sessionService->submitExam($tok2, $studentId1);
        
        $notifs = $notifModel->getUserNotifications($teacherId1, 10);
        $found = 0;
        foreach ($notifs as $n) {
            if ($n['title'] === 'Jawaban Siswa Masuk' && str_contains($n['message'], $testTitleExam1)) {
                $found++;
            }
        }
        // Harus TETAP 1 (tidak menjadi 2)
        return $res['success'] === true && $found === 1;
    }, $pass, $fail);

    // -------------------------------------------------------------------------
    // TEST 3: Siswa 2 (Siswa Berbeda) Menyerahkan Ujian yang Sama -> Berhasil Dikirim
    // -------------------------------------------------------------------------
    runTestCase("3. Penyerahan oleh Siswa 2 pada Ujian 1 TETAP menghasilkan notifikasi baru ke Guru 1", function() use ($sessionService, $notifModel, $examId1, $studentId2, $teacherId1, $testTitleExam1) {
        $tok3 = makeSession($examId1, $studentId2);
        $res = $sessionService->submitExam($tok3, $studentId2);
        
        $notifs = $notifModel->getUserNotifications($teacherId1, 10);
        $foundSiswa1 = 0;
        $foundSiswa2 = 0;
        foreach ($notifs as $n) {
            if ($n['title'] === 'Jawaban Siswa Masuk' && str_contains($n['message'], $testTitleExam1)) {
                if (str_contains($n['message'], 'Ahmad Fauzi')) $foundSiswa1++;
                if (str_contains($n['message'], 'Siswa Kedua')) $foundSiswa2++;
            }
        }
        return $foundSiswa1 === 1 && $foundSiswa2 === 1;
    }, $pass, $fail);

    // -------------------------------------------------------------------------
    // TEST 4: Siswa 1 Menyerahkan UJIAN BERBEDA (Ujian 2) -> Berhasil Dikirim ke Guru 2
    // -------------------------------------------------------------------------
    runTestCase("4. Penyerahan Siswa 1 pada Ujian 2 TETAP menghasilkan notifikasi ke Guru 2", function() use ($sessionService, $notifModel, $examId2, $studentId1, $teacherId2, $testTitleExam2) {
        $tok4 = makeSession($examId2, $studentId1);
        $res = $sessionService->submitExam($tok4, $studentId1);
        
        $notifs = $notifModel->getUserNotifications($teacherId2, 10);
        $found = 0;
        foreach ($notifs as $n) {
            if ($n['title'] === 'Jawaban Siswa Masuk' && str_contains($n['message'], $testTitleExam2)) {
                $found++;
            }
        }
        return $found === 1;
    }, $pass, $fail);

    // -------------------------------------------------------------------------
    // TEST 5: Konfirmasi Siswa 1 Tidak Mendapat Duplikat
    // -------------------------------------------------------------------------
    runTestCase("5. Konfirmasi 'Ujian Berhasil Dikumpulkan' ke Siswa 1 tidak duplikat untuk Ujian 1", function() use ($notifModel, $studentId1, $testTitleExam1) {
        $notifs = $notifModel->getUserNotifications($studentId1, 20);
        $found = 0;
        foreach ($notifs as $n) {
            if ($n['title'] === 'Ujian Berhasil Dikumpulkan' && str_contains($n['message'], $testTitleExam1)) {
                $found++;
            }
        }
        return $found === 1;
    }, $pass, $fail);

    // -------------------------------------------------------------------------
    // TEST 6: Isolasi Guru (Guru 2 tidak melihat notifikasi Guru 1 dan sebaliknya)
    // -------------------------------------------------------------------------
    runTestCase("6. Isolasi antar Guru: Notifikasi Guru 1 tidak bocor ke Guru 2", function() use ($notifModel, $teacherId2, $testTitleExam1) {
        $notifs = $notifModel->getUserNotifications($teacherId2, 20);
        foreach ($notifs as $n) {
            if (str_contains($n['message'], $testTitleExam1)) {
                return false; // Bocor
            }
        }
        return true;
    }, $pass, $fail);

} finally {
    // Teardown: Bersihkan fixture pengujian
    $db->exec("DELETE FROM notifications WHERE message LIKE '%{$testTitleExam1}%' OR message LIKE '%{$testTitleExam2}%'");
    $db->exec("DELETE FROM exam_answers WHERE exam_session_id IN (SELECT id FROM exam_sessions WHERE exam_id IN ({$examId1}, {$examId2}))");
    $db->exec("DELETE FROM exam_results WHERE exam_id IN ({$examId1}, {$examId2})");
    $db->exec("DELETE FROM exam_sessions WHERE exam_id IN ({$examId1}, {$examId2})");
    $db->exec("DELETE FROM exams WHERE id IN ({$examId1}, {$examId2})");
}

echo "\n====================================================================\n";
echo "HASIL PENGUJIAN: {$pass} PASS, {$fail} FAIL\n";
echo "====================================================================\n";

if ($fail > 0) {
    exit(1);
}
