<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\UserModel;
use App\Services\ExamAnswerService;
use App\Services\ExamSessionService;
use App\Services\ExamTimerService;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: EXAM SESSION RECOVERY & RECONNECTION (UC-14)\n";
echo "==============================================================================\n\n";

$passedCount = 0;
$totalCount = 0;

function runTest(string $title, callable $fn): void {
    global $passedCount, $totalCount;
    $totalCount++;
    try {
        $fn();
        echo "  ✓ [PASS] {$title}\n";
        $passedCount++;
    } catch (\Throwable $e) {
        echo "  ✗ [FAIL] {$title}: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
    }
}

$db = Database::getConnection();
$userModel = new UserModel();
$examModel = new ExamModel();
$sessionModel = new ExamSessionModel();
$answerModel = new ExamAnswerModel();
$participantModel = new ExamParticipantModel();
$examQuestionModel = new ExamQuestionModel();
$resultModel = new ExamResultModel();

$timerService = new ExamTimerService();
$answerService = new ExamAnswerService();
$sessionService = new ExamSessionService();

// ----------------------------------------------------------------------
// SETUP: Siswa 1, Siswa 2, dan Ujian Uji
// ----------------------------------------------------------------------
$student1 = $userModel->findByEmail('siswa@sekolah.sch.id') ?? $userModel->find(4);
if (!$student1) {
    throw new RuntimeException("Student 1 tidak ditemukan.");
}
$student1Id = (int)$student1['id'];

$student2 = $userModel->findByEmail('siswa2@sekolah.sch.id');
if (!$student2) {
    $st2Id = $userModel->insert([
        'role_id'   => 4,
        'name'      => 'Siswa Kedua (Recovery Tester)',
        'username'  => 'siswa_recovery_2',
        'email'     => 'siswa2@sekolah.sch.id',
        'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
        'status'    => 'active',
        'created_at'=> date('Y-m-d H:i:s'),
        'updated_at'=> date('Y-m-d H:i:s')
    ]);
    $student2 = $userModel->find($st2Id);
}
$student2Id = (int)$student2['id'];

// Ambil ujian aktif yang memiliki butir soal
$allExams = $examModel->findAll();
$exam = null;
$questions = [];
foreach ($allExams as $e) {
    $qs = $examQuestionModel->getQuestionsByExamId((int)$e['id']);
    if (!empty($qs)) {
        $exam = $e;
        $questions = $qs;
        break;
    }
}

if (!$exam || empty($questions)) {
    throw new RuntimeException("Tidak ada ujian yang memiliki butir soal di database.");
}

$examId = (int)$exam['id'];
$firstQuestion = $questions[0];
$questionId = (int)$firstQuestion['question_id'];
$firstOptionId = !empty($firstQuestion['options']) ? (int)$firstQuestion['options'][0]['id'] : 1;

// Bersihkan data sesi sebelumnya untuk student 1 & 2 pada ujian ini
$db->exec("DELETE FROM exam_results WHERE exam_id = {$examId} AND student_id IN ({$student1Id}, {$student2Id})");
$db->exec("DELETE FROM exam_answers WHERE exam_session_id IN (SELECT id FROM exam_sessions WHERE exam_id = {$examId} AND student_id IN ({$student1Id}, {$student2Id}))");
$db->exec("DELETE FROM exam_sessions WHERE exam_id = {$examId} AND student_id IN ({$student1Id}, {$student2Id})");

// Pastikan jadwal ujian aktif selama pengetesan recovery
$origSched = $db->query("SELECT * FROM exam_schedules WHERE exam_id = {$examId} AND class_id = 1 LIMIT 1")->fetch();
$db->exec("UPDATE exam_schedules SET end_time = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE exam_id = {$examId} AND class_id = 1");

// Daftarkan student 1 dan student 2 ke exam_participants
$db->prepare("INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
              VALUES (:eid, :sid1, 1, 'not_started', NOW(), NOW())
              ON DUPLICATE KEY UPDATE status = 'not_started'")
   ->execute([':eid' => $examId, ':sid1' => $student1Id]);

$db->prepare("INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
              VALUES (:eid, :sid2, 1, 'not_started', NOW(), NOW())
              ON DUPLICATE KEY UPDATE status = 'not_started'")
   ->execute([':eid' => $examId, ':sid2' => $student2Id]);

// ----------------------------------------------------------------------
// TEST 1: Refresh during exam
// ----------------------------------------------------------------------
runTest("Refresh during exam: server preserves ongoing session, start_time, continuous timer, and saved answers", function() use ($exam, $examId, $student1Id, $questionId, $firstOptionId, $sessionModel, $answerModel, $sessionService) {
    // 1. Inisialisasi sesi awal
    $examDurationMinutes = (int)($exam['duration_minutes'] ?? 60);
    $examDurationSec = $examDurationMinutes * 60;
    $session = $sessionModel->createSession($examId, $student1Id, $examDurationMinutes);
    $sessionToken = $session['session_token'];
    $initialStartTime = $session['start_time'];

    // 2. Simpan jawaban untuk soal 1
    $answerModel->upsertAnswer((int)$session['id'], $questionId, $firstOptionId, null, 0);

    // 3. Simulasi F5 / Refresh browser (GET Room data 1st time)
    $roomData1 = $sessionService->getExamRoomData($sessionToken, $student1Id);
    if (!$roomData1) {
        throw new RuntimeException("getExamRoomData mengembalikan null pada refresh pertama.");
    }

    if ($roomData1['session']['start_time'] !== $initialStartTime) {
        throw new RuntimeException("start_time berubah setelah refresh! Seharusnya tidak reset timer.");
    }

    if (!isset($roomData1['saved_answers'][$questionId]) || (int)$roomData1['saved_answers'][$questionId]['selected_option_id'] !== $firstOptionId) {
        throw new RuntimeException("Jawaban tersimpan hilang atau tidak sesuai setelah refresh.");
    }

    if ((int)$roomData1['counts']['answered'] !== 1) {
        throw new RuntimeException("Jumlah soal terjawab salah setelah refresh.");
    }

    if ($roomData1['remaining_seconds'] <= 0 || $roomData1['remaining_seconds'] > $examDurationSec) {
        throw new RuntimeException("Sisa detik server tidak valid setelah refresh: {$roomData1['remaining_seconds']}");
    }

    // 4. Simulasi refresh kedua setelah jeda singkat
    $roomData2 = $sessionService->getExamRoomData($sessionToken, $student1Id);
    if ($roomData2['session']['start_time'] !== $initialStartTime) {
        throw new RuntimeException("start_time berubah pada refresh kedua.");
    }
    if ((int)$roomData2['saved_answers'][$questionId]['selected_option_id'] !== $firstOptionId) {
        throw new RuntimeException("Jawaban tidak konsisten pada refresh kedua.");
    }
});

// ----------------------------------------------------------------------
// TEST 2: Resume ongoing session
// ----------------------------------------------------------------------
runTest("Resume ongoing session: startOrResumeSession finds existing session, returns resumed=true, and creates NO duplicates", function() use ($examId, $student1Id, $sessionModel, $sessionService) {
    $activeBefore = $sessionModel->findActiveSession($examId, $student1Id);
    if (!$activeBefore) {
        throw new RuntimeException("Sesi aktif tidak ditemukan sebelum resume.");
    }

    $tokenBefore = $activeBefore['session_token'];
    $countBefore = $sessionModel->where('exam_id', $examId)->where('student_id', $student1Id)->count();

    // Student resume (reopen browser / click start / reconnect)
    $resumeResult = $sessionService->startOrResumeSession($examId, $student1Id, 'IRRELEVANT_TOKEN', '127.0.0.1', 'PHPTest');

    if (!$resumeResult['success']) {
        throw new RuntimeException("Resume ongoing session gagal: " . ($resumeResult['error'] ?? 'Unknown error'));
    }

    if (empty($resumeResult['resumed'])) {
        throw new RuntimeException("Expected resumed=true tetapi didapat resumed=false.");
    }

    if ($resumeResult['session_token'] !== $tokenBefore) {
        throw new RuntimeException("Token sesi resume berbeda dari sesi aktif semula.");
    }

    $countAfter = $sessionModel->where('exam_id', $examId)->where('student_id', $student1Id)->count();
    if ($countAfter !== $countBefore) {
        throw new RuntimeException("Sesi duplicate terbuat di database! Count before={$countBefore}, after={$countAfter}");
    }
});

// ----------------------------------------------------------------------
// TEST 3: Submitted session cannot resume
// ----------------------------------------------------------------------
runTest("Submitted session cannot resume: startOrResumeSession and room reject resumed access once submitted", function() use ($examId, $student1Id, $sessionModel, $sessionService) {
    $active = $sessionModel->findActiveSession($examId, $student1Id);
    if (!$active) {
        throw new RuntimeException("Sesi aktif tidak ditemukan.");
    }

    // Submit ujian
    $submitRes = $sessionService->submitExam($active['session_token'], $student1Id);
    if (!$submitRes['success']) {
        throw new RuntimeException("Gagal submit ujian: " . ($submitRes['error'] ?? ''));
    }

    // Coba resume sesi yang sudah disubmit
    $tryResume = $sessionService->startOrResumeSession($examId, $student1Id, 'ANY_TOKEN');
    if ($tryResume['success']) {
        throw new RuntimeException("Sesi yang sudah disubmit berhasil di-resume! Seharusnya ditolak.");
    }

    // Verifikasi status sesi di getExamRoomData
    $roomData = $sessionService->getExamRoomData($active['session_token'], $student1Id);
    if (!$roomData || $roomData['session']['status'] !== 'submitted') {
        throw new RuntimeException("Sesi status tidak submitted.");
    }
});

// ----------------------------------------------------------------------
// TEST 4: Expired session cannot resume
// ----------------------------------------------------------------------
runTest("Expired session cannot resume: startOrResumeSession and timerService reject resume for sessions past deadline", function() use ($examId, $student1Id, $db, $sessionModel, $sessionService, $timerService) {
    // Buat sesi buatan dengan waktu mulai 3 jam lalu (durasi ujian 60 menit -> sudah expired)
    $expiredStartTime = date('Y-m-d H:i:s', time() - 10800); // 3 hours ago
    $expiredToken = bin2hex(random_bytes(32));

    // Reset status partisipan agar bisa dites
    $db->exec("UPDATE exam_participants SET status = 'in_progress' WHERE exam_id = {$examId} AND student_id = {$student1Id}");

    $db->prepare("INSERT INTO exam_sessions 
        (exam_id, student_id, session_token, ip_address, user_agent, start_time, remaining_seconds, status, created_at, updated_at)
        VALUES (:eid, :sid, :token, '127.0.0.1', 'Test', :st, 0, 'ongoing', :ca, :ua)")
       ->execute([
           ':eid'   => $examId,
           ':sid'   => $student1Id,
           ':token' => $expiredToken,
           ':st'    => $expiredStartTime,
           ':ca'    => $expiredStartTime,
           ':ua'    => $expiredStartTime
       ]);

    // 1. Validasi langsung via ExamTimerService
    $timerVal = $timerService->validateActiveExamSession($expiredToken, $student1Id);
    if ($timerVal['valid']) {
        throw new RuntimeException("validateActiveExamSession menyatakan valid untuk sesi yang sudah kedaluwarsa!");
    }
    if ($timerVal['code'] !== 'EXPIRED' || empty($timerVal['is_expired'])) {
        throw new RuntimeException("Expected code=EXPIRED dan is_expired=true.");
    }

    // 2. Coba resume via startOrResumeSession
    $resumeExpired = $sessionService->startOrResumeSession($examId, $student1Id, 'ANY_TOKEN');
    if ($resumeExpired['success']) {
        throw new RuntimeException("startOrResumeSession berhasil me-resume sesi expired! Seharusnya ditolak.");
    }

    if (empty($resumeExpired['error'])) {
        throw new RuntimeException("Expected error message saat resume sesi expired.");
    }
});

// ----------------------------------------------------------------------
// TEST 5: Another student's session cannot resume
// ----------------------------------------------------------------------
runTest("Another student's session cannot resume: unauthorized student cannot access room, sync timer, or submit another student's session", function() use ($examId, $student1Id, $student2Id, $db, $sessionModel, $sessionService, $timerService) {
    // Buat sesi baru yang valid untuk Student 1
    $session1 = $sessionModel->createSession($examId, $student1Id, 60);
    $student1Token = $session1['session_token'];

    // 1. Student 2 mencoba getExamRoomData milik Student 1
    $roomDataOther = $sessionService->getExamRoomData($student1Token, $student2Id);
    if ($roomDataOther !== null) {
        throw new RuntimeException("Student 2 berhasil mengakses getExamRoomData milik Student 1!");
    }

    // 2. Student 2 mencoba sync timer sesi milik Student 1
    $syncOther = $timerService->syncTimer($student1Token, $student2Id);
    if ($syncOther['success']) {
        throw new RuntimeException("Student 2 berhasil sinkronisasi timer milik Student 1!");
    }

    // 3. Student 2 mencoba validasi sesi aktif Student 1
    $valOther = $timerService->validateActiveExamSession($student1Token, $student2Id);
    if ($valOther['valid'] || $valOther['code'] !== 'UNAUTHORIZED') {
        throw new RuntimeException("Expected code=UNAUTHORIZED saat Student 2 memvalidasi sesi Student 1.");
    }

    // 4. Student 2 mencoba submit sesi milik Student 1
    $submitOther = $sessionService->submitExam($student1Token, $student2Id);
    if ($submitOther['success']) {
        throw new RuntimeException("Student 2 berhasil submit sesi milik Student 1! Seharusnya ditolak.");
    }

    // 5. Student 2 memanggil startOrResumeSession - harusnya tidak mendapatkan token Student 1
    $st2Resume = $sessionService->startOrResumeSession($examId, $student2Id, 'TOKEN2');
    if ($st2Resume['success'] && ($st2Resume['session_token'] ?? '') === $student1Token) {
        throw new RuntimeException("Student 2 mendapatkan session_token milik Student 1!");
    }
});

// Kembalikan jadwal ujian ke kondisi semula
if ($origSched) {
    $db->prepare("UPDATE exam_schedules SET end_time = :et WHERE id = :id")->execute([':et' => $origSched['end_time'], ':id' => $origSched['id']]);
}

echo "\n==============================================================================\n";
echo "HASIL PENGUJIAN EXAM SESSION RECOVERY: {$passedCount} / {$totalCount} BERHASIL\n";
echo "==============================================================================\n";

if ($passedCount !== $totalCount) {
    exit(1);
}
