<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamSessionModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\UserModel;
use App\Services\ExamAnswerService;
use App\Services\ExamSessionService;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: EXAM ANSWER DATA OWNERSHIP & INTEGRITY VALIDATION\n";
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
$questionModel = new QuestionModel();
$optionModel = new QuestionOptionModel();
$examQuestionModel = new ExamQuestionModel();

$answerService = new ExamAnswerService();
$sessionService = new ExamSessionService();

// ----------------------------------------------------------------------
// SETUP: Siapkan 2 Siswa, 2 Paket Ujian terpisah beserta butir soal & opsi
// ----------------------------------------------------------------------
$studentA = $userModel->findByEmail('siswa@sekolah.sch.id') ?? $userModel->find(4);
$studentB = $userModel->findByEmail('siswa2@sekolah.sch.id');
if (!$studentB) {
    $stBId = $userModel->insert([
        'role_id'   => 4,
        'name'      => 'Siswa B (Ownership Test)',
        'username'  => 'siswa_b_owner',
        'email'     => 'siswa2@sekolah.sch.id',
        'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
        'status'    => 'active',
        'created_at'=> date('Y-m-d H:i:s'),
        'updated_at'=> date('Y-m-d H:i:s')
    ]);
    $studentB = $userModel->find($stBId);
}
$studentAId = (int)$studentA['id'];
$studentBId = (int)$studentB['id'];

// Cari 2 ujian berbeda yang memiliki butir soal terpisah
$allExams = $examModel->findAll();
$exam1 = null;
$exam2 = null;
$questionsExam1 = [];
$questionsExam2 = [];

foreach ($allExams as $e) {
    $qs = $examQuestionModel->getQuestionsByExamId((int)$e['id']);
    if (!empty($qs) && count($qs[0]['options'] ?? []) > 0) {
        if (!$exam1) {
            $exam1 = $e;
            $questionsExam1 = $qs;
        } elseif (!$exam2 && (int)$e['id'] !== (int)$exam1['id']) {
            $exam2 = $e;
            $questionsExam2 = $qs;
            break;
        }
    }
}

// Jika belum ada 2 ujian dengan butir soal terpisah di database, buat butir soal terisolasi untuk tes
if (!$exam1 || !$exam2) {
    // Siapkan bank soal & soal ad-hoc
    $qBankId = 1;
    $qAId = (int)$questionModel->insert([
        'question_bank_id' => $qBankId,
        'type'             => 'multiple_choice',
        'content'          => 'Soal Uji Kepemilikan Exam 1 (A)?',
        'weight'           => 1.0,
        'difficulty'       => 'easy',
        'order_number'     => 1,
        'created_at'       => date('Y-m-d H:i:s'),
        'updated_at'       => date('Y-m-d H:i:s')
    ]);
    $optA1Id = (int)$optionModel->insert([
        'question_id' => $qAId,
        'option_key'  => 'A',
        'content'     => 'Opsi A1 Benar',
        'is_correct'  => 1,
        'created_at'  => date('Y-m-d H:i:s')
    ]);
    $optA2Id = (int)$optionModel->insert([
        'question_id' => $qAId,
        'option_key'  => 'B',
        'content'     => 'Opsi A2 Salah',
        'is_correct'  => 0,
        'created_at'  => date('Y-m-d H:i:s')
    ]);

    $qBId = (int)$questionModel->insert([
        'question_bank_id' => $qBankId,
        'type'             => 'multiple_choice',
        'content'          => 'Soal Uji Kepemilikan Exam 2 (B)?',
        'weight'           => 1.0,
        'difficulty'       => 'easy',
        'order_number'     => 2,
        'created_at'       => date('Y-m-d H:i:s'),
        'updated_at'       => date('Y-m-d H:i:s')
    ]);
    $optB1Id = (int)$optionModel->insert([
        'question_id' => $qBId,
        'option_key'  => 'A',
        'content'     => 'Opsi B1 Benar',
        'is_correct'  => 1,
        'created_at'  => date('Y-m-d H:i:s')
    ]);

    $exam1Id = $exam1 ? (int)$exam1['id'] : 1;
    $exam2Id = $exam2 ? (int)$exam2['id'] : 2;

    $db->exec("INSERT INTO exam_questions (exam_id, question_id, order_number, score_weight, created_at) VALUES ({$exam1Id}, {$qAId}, 1, 1.0, NOW())");
    $db->exec("INSERT INTO exam_questions (exam_id, question_id, order_number, score_weight, created_at) VALUES ({$exam2Id}, {$qBId}, 1, 1.0, NOW())");

    $question1Id = $qAId;
    $option1Id = $optA1Id;
    $question2Id = $qBId;
    $option2Id = $optB1Id;
} else {
    $exam1Id = (int)$exam1['id'];
    $exam2Id = (int)$exam2['id'];

    $question1 = $questionsExam1[0];
    $question1Id = (int)$question1['question_id'];
    $option1Id = (int)$question1['options'][0]['id'];

    $question2 = $questionsExam2[0];
    $question2Id = (int)$question2['question_id'];
    $option2Id = (int)$question2['options'][0]['id'];
}

// Pastikan student A terdaftar di exam 1
$db->exec("INSERT IGNORE INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
           VALUES ({$exam1Id}, {$studentAId}, 1, 'in_progress', NOW(), NOW())");

// Buat Sesi Ujian Aktif untuk Student A pada Exam 1
$tokenA = 'token_owner_test_' . bin2hex(random_bytes(16));
$now = date('Y-m-d H:i:s');
$sessAId = (int)$sessionModel->insert([
    'exam_id'           => $exam1Id,
    'student_id'        => $studentAId,
    'session_token'     => $tokenA,
    'ip_address'        => '127.0.0.1',
    'start_time'        => $now,
    'remaining_seconds' => 3600,
    'status'            => 'ongoing',
    'integrity_score'   => 100.0,
    'created_at'        => $now,
    'updated_at'        => $now
]);

echo "[SETUP COMPLETE] Exam 1: #{$exam1Id} (Q: #{$question1Id}, Opt: #{$option1Id}), Exam 2: #{$exam2Id} (Q: #{$question2Id}, Opt: #{$option2Id})\n\n";

// ----------------------------------------------------------------------
// 1. Valid session + valid question + valid option → SUCCESS
// ----------------------------------------------------------------------
runTest("1. Valid Session + Valid Question + Valid Option -> SUCCESS", function () use ($answerService, $tokenA, $studentAId, $question1Id, $option1Id) {
    $result = $answerService->saveAnswer($tokenA, $studentAId, $question1Id, $option1Id, null, 0);

    if (empty($result['success'])) {
        throw new Exception("Gagal menyimpan jawaban sah: " . ($result['error'] ?? ''));
    }
    if ($result['question_id'] !== $question1Id) {
        throw new Exception("Question ID tidak cocok.");
    }
});

// ----------------------------------------------------------------------
// 2. Valid session + question exam lain → REJECT (INVALID_QUESTION)
// ----------------------------------------------------------------------
runTest("2. Valid Session + Question dari Exam Lain -> REJECT (INVALID_QUESTION)", function () use ($answerService, $tokenA, $studentAId, $question2Id, $option2Id) {
    $result = $answerService->saveAnswer($tokenA, $studentAId, $question2Id, $option2Id, null, 0);

    if (!empty($result['success'])) {
        throw new Exception("Server menerima jawaban untuk soal dari ujian lain!");
    }
    if (($result['code'] ?? '') !== 'INVALID_QUESTION') {
        throw new Exception("Error code yang diharapkan 'INVALID_QUESTION', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// 3. Valid question + option question lain → REJECT (INVALID_OPTION)
// ----------------------------------------------------------------------
runTest("3. Valid Question + Option dari Question Lain -> REJECT (INVALID_OPTION)", function () use ($answerService, $tokenA, $studentAId, $question1Id, $option2Id) {
    // Mencoba memasangkan question 1 dengan option milik question 2
    $result = $answerService->saveAnswer($tokenA, $studentAId, $question1Id, $option2Id, null, 0);

    if (!empty($result['success'])) {
        throw new Exception("Server menerima option yang bukan milik butir soal tersebut!");
    }
    if (($result['code'] ?? '') !== 'INVALID_OPTION') {
        throw new Exception("Error code yang diharapkan 'INVALID_OPTION', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// 4. Session Student A + Student B → REJECT (UNAUTHORIZED)
// ----------------------------------------------------------------------
runTest("4. Sesi Student A diakses oleh Student B -> REJECT (UNAUTHORIZED)", function () use ($answerService, $tokenA, $studentBId, $question1Id, $option1Id) {
    $result = $answerService->saveAnswer($tokenA, $studentBId, $question1Id, $option1Id, null, 0);

    if (!empty($result['success'])) {
        throw new Exception("Student B berhasil menyimpan jawaban ke sesi Student A!");
    }
    if (($result['code'] ?? '') !== 'UNAUTHORIZED') {
        throw new Exception("Error code yang diharapkan 'UNAUTHORIZED', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// 5. Invalid session token → REJECT (SESSION_NOT_FOUND)
// ----------------------------------------------------------------------
runTest("5. Token Sesi Palsu/Tidak Valid -> REJECT (SESSION_NOT_FOUND)", function () use ($answerService, $studentAId, $question1Id, $option1Id) {
    $fakeToken = 'fake_non_existent_token_99999999999999';
    $result = $answerService->saveAnswer($fakeToken, $studentAId, $question1Id, $option1Id, null, 0);

    if (!empty($result['success'])) {
        throw new Exception("Server menerima request dengan token sesi palsu!");
    }
    if (($result['code'] ?? '') !== 'SESSION_NOT_FOUND') {
        throw new Exception("Error code yang diharapkan 'SESSION_NOT_FOUND', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// 6. Session submitted → REJECT (ALREADY_SUBMITTED)
// ----------------------------------------------------------------------
runTest("6. Sesi telah Submitted -> REJECT (ALREADY_SUBMITTED)", function () use ($answerService, $sessionService, $tokenA, $studentAId, $question1Id, $option1Id) {
    // Submit sesi A
    $submitRes = $sessionService->submitExam($tokenA, $studentAId);
    if (empty($submitRes['success'])) {
        throw new Exception("Gagal submit sesi untuk setup test 6: " . ($submitRes['error'] ?? ''));
    }

    // Coba save answer setelah submit
    $result = $answerService->saveAnswer($tokenA, $studentAId, $question1Id, $option1Id, null, 0);

    if (!empty($result['success'])) {
        throw new Exception("Server mengizinkan simpan jawaban setelah sesi disubmit!");
    }
    if (($result['code'] ?? '') !== 'ALREADY_SUBMITTED') {
        throw new Exception("Error code yang diharapkan 'ALREADY_SUBMITTED', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// 7. Expired session → REJECT (EXPIRED)
// ----------------------------------------------------------------------
runTest("7. Sesi telah Expired (Batas Waktu Lewat) -> REJECT (EXPIRED)", function () use ($answerService, $sessionModel, $studentAId, $exam1Id, $question1Id, $option1Id, $db) {
    // Buat sesi baru yang sudah expired (start_time 3 jam lalu)
    $expiredToken = 'token_expired_test_' . bin2hex(random_bytes(16));
    $pastTime = date('Y-m-d H:i:s', time() - 10800);
    $expSessId = (int)$sessionModel->insert([
        'exam_id'           => $exam1Id,
        'student_id'        => $studentAId,
        'session_token'     => $expiredToken,
        'ip_address'        => '127.0.0.1',
        'start_time'        => $pastTime,
        'remaining_seconds' => 0,
        'status'            => 'ongoing',
        'integrity_score'   => 100.0,
        'created_at'        => $pastTime,
        'updated_at'        => $pastTime
    ]);

    $result = $answerService->saveAnswer($expiredToken, $studentAId, $question1Id, $option1Id, null, 0);

    // Cleanup
    $db->exec("DELETE FROM exam_sessions WHERE id = {$expSessId}");

    if (!empty($result['success'])) {
        throw new Exception("Server menerima simpan jawaban pada sesi yang expired!");
    }
    if (($result['code'] ?? '') !== 'EXPIRED' || empty($result['is_expired'])) {
        throw new Exception("Error code yang diharapkan 'EXPIRED', didapat: " . ($result['code'] ?? ''));
    }
});

// Cleanup test records
$db->exec("DELETE FROM exam_answers WHERE exam_session_id = {$sessAId}");
$db->exec("DELETE FROM exam_results WHERE exam_session_id = {$sessAId}");
$db->exec("DELETE FROM exam_sessions WHERE id = {$sessAId}");

echo "\n==============================================================================\n";
echo "HASIL PENGUJIAN: {$passedCount} / {$totalCount} Test Case BERHASIL (" . round(($passedCount / $totalCount) * 100) . "% PASSED)\n";
echo "==============================================================================\n";
