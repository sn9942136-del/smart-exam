<?php

declare(strict_types=1);

$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\AuditLogModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\UserModel;
use App\Services\ExamAnswerService;
use App\Services\ExamSessionService;
use App\Services\ExamTimerService;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: EXAM SUBMIT REVIEW & HARDENING REGRESSION SUITE\n";
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
$resultModel = new ExamResultModel();
$examQuestionModel = new ExamQuestionModel();

$answerService = new ExamAnswerService();
$sessionService = new ExamSessionService();
$timerService = new ExamTimerService();

// ----------------------------------------------------------------------
// SETUP: Siapkan Siswa A, Siswa B, dan Paket Ujian
// ----------------------------------------------------------------------
$studentA = $userModel->findByEmail('siswa@sekolah.sch.id') ?? $userModel->find(4);
$studentB = $userModel->findByEmail('siswa2@sekolah.sch.id') ?? $userModel->find(5);
if (!$studentB) {
    $stBId = $userModel->insert([
        'role_id'   => 4,
        'name'      => 'Siswa B (Submit Tester)',
        'username'  => 'siswa_b_submit',
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

// Cari ujian dengan butir soal
$allExams = $examModel->findAll();
$exam = null;
$questions = [];
foreach ($allExams as $e) {
    $qs = $examQuestionModel->getQuestionsByExamId((int)$e['id']);
    if (!empty($qs) && count($qs[0]['options'] ?? []) > 0) {
        $exam = $e;
        $questions = $qs;
        break;
    }
}
if (!$exam) {
    throw new RuntimeException("Tidak ada ujian valid dengan butir soal di database.");
}

$examId = (int)$exam['id'];
$question1 = $questions[0];
$question1Id = (int)$question1['question_id'];
$correctOptionId = 0;
foreach ($question1['options'] as $opt) {
    if ((int)$opt['is_correct'] === 1) {
        $correctOptionId = (int)$opt['id'];
        break;
    }
}
if ($correctOptionId === 0) {
    $correctOptionId = (int)$question1['options'][0]['id'];
}

echo "[SETUP] Student A: {$studentAId}, Student B: {$studentBId}, Exam: #{$examId}, Question: #{$question1Id}\n\n";

// Helper membuat sesi uji
function createTestSession(int $examId, int $studentId, string $status = 'ongoing', int $remainingSeconds = 3600): array {
    global $sessionModel, $db;
    $token = 'token_sub_' . bin2hex(random_bytes(16));
    $now = date('Y-m-d H:i:s');
    $id = (int)$sessionModel->insert([
        'exam_id'           => $examId,
        'student_id'        => $studentId,
        'session_token'     => $token,
        'ip_address'        => '127.0.0.1',
        'start_time'        => $now,
        'remaining_seconds' => $remainingSeconds,
        'status'            => $status,
        'integrity_score'   => 100.0,
        'created_at'        => $now,
        'updated_at'        => $now
    ]);

    $db->exec("INSERT IGNORE INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
               VALUES ({$examId}, {$studentId}, 1, 'in_progress', NOW(), NOW())");

    return ['id' => $id, 'token' => $token];
}

// ----------------------------------------------------------------------
// TEST 1: Double Submit / Duplicate Request (Idempotency & Race Safety)
// ----------------------------------------------------------------------
echo "[TEST 1] Menguji Double Submit / Duplicate Request (Idempotency)...\n";
$testSess1 = createTestSession($examId, $studentAId);
$token1 = $testSess1['token'];
$sess1Id = $testSess1['id'];

// Jawab 1 soal
$answerService->saveAnswer($token1, $studentAId, $question1Id, $correctOptionId, null, 0);

runTest("1.1 First Submit -> Success (already_submitted: false)", function () use ($sessionService, $token1, $studentAId, &$firstResult) {
    $firstResult = $sessionService->submitExam($token1, $studentAId);

    if (empty($firstResult['success'])) {
        throw new Exception("Submit pertama gagal: " . ($firstResult['error'] ?? ''));
    }
    if (!empty($firstResult['already_submitted'])) {
        throw new Exception("Submit pertama harusnya already_submitted = false");
    }
    if (empty($firstResult['result_id'])) {
        throw new Exception("Result ID tidak dikembalikan.");
    }
});

runTest("1.2 Immediate Second Submit (Double Click) -> Idempotent Success (already_submitted: true)", function () use ($sessionService, $token1, $studentAId, &$firstResult) {
    $secondResult = $sessionService->submitExam($token1, $studentAId);

    if (empty($secondResult['success'])) {
        throw new Exception("Double submit kedua gagal: " . ($secondResult['error'] ?? ''));
    }
    if (empty($secondResult['already_submitted'])) {
        throw new Exception("Double submit kedua harus mengembalikan already_submitted = true");
    }
    if ($secondResult['result_id'] !== $firstResult['result_id']) {
        throw new Exception("Result ID pada submit kedua berubah! Awal: {$firstResult['result_id']}, Kedua: {$secondResult['result_id']}");
    }
    if ($secondResult['final_score'] !== $firstResult['final_score']) {
        throw new Exception("Final score berubah saat double submit! Awal: {$firstResult['final_score']}, Kedua: {$secondResult['final_score']}");
    }
});

// ----------------------------------------------------------------------
// TEST 2: Unique Constraint & Single Result Guarantee
// ----------------------------------------------------------------------
echo "\n[TEST 2] Memeriksa bahwa tidak ada duplicate result dan audit log duplikat...\n";
runTest("2.1 Tepat 1 baris exam_results untuk sesi tersebut", function () use ($db, $sess1Id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM exam_results WHERE exam_session_id = :sid");
    $stmt->execute([':sid' => $sess1Id]);
    $count = (int)$stmt->fetchColumn();

    if ($count !== 1) {
        throw new Exception("Jumlah record exam_results untuk sesi {$sess1Id} bukan 1, melainkan: {$count}");
    }
});

runTest("2.2 Audit log EXAM_SUBMIT hanya tercatat 1 kali (tidak dobel)", function () use ($db, $sess1Id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'EXAM_SUBMIT' AND target_id = :tid");
    $stmt->execute([':tid' => (string)$sess1Id]);
    $logCount = (int)$stmt->fetchColumn();

    if ($logCount !== 1) {
        throw new Exception("Jumlah audit log EXAM_SUBMIT untuk sesi {$sess1Id} bukan 1, melainkan: {$logCount}");
    }
});

// ----------------------------------------------------------------------
// TEST 3: Submit setelah Submitted (Browser Retry / Re-Submit)
// ----------------------------------------------------------------------
echo "\n[TEST 3] Menguji submit ulang pada sesi yang telah berstatus submitted...\n";
runTest("3. Submit ulang sesi yang sudah submitted mempertahankan nilai tanpa corrupt", function () use ($sessionService, $token1, $studentAId, &$firstResult, $db, $sess1Id) {
    // Ambil nilai sebelum re-submit
    $beforeStmt = $db->prepare("SELECT final_score, updated_at FROM exam_results WHERE exam_session_id = :sid");
    $beforeStmt->execute([':sid' => $sess1Id]);
    $before = $beforeStmt->fetch();

    $retryRes = $sessionService->submitExam($token1, $studentAId);

    // Ambil nilai sesudah re-submit
    $afterStmt = $db->prepare("SELECT final_score, updated_at FROM exam_results WHERE exam_session_id = :sid");
    $afterStmt->execute([':sid' => $sess1Id]);
    $after = $afterStmt->fetch();

    if (empty($retryRes['success']) || empty($retryRes['already_submitted'])) {
        throw new Exception("Re-submit gagal atau tidak idempotent.");
    }
    if ($before['final_score'] !== $after['final_score']) {
        throw new Exception("Nilai akhir berubah saat re-submit!");
    }
});

// ----------------------------------------------------------------------
// TEST 4: Submit setelah Deadline (Auto-Submit / Server Expiration Handling)
// ----------------------------------------------------------------------
echo "\n[TEST 4] Menguji submit ujian pada sesi yang deadline-nya telah lewat...\n";
$testSess4 = createTestSession($examId, $studentAId);
$token4 = $testSess4['token'];
$sess4Id = $testSess4['id'];

// Jawab 1 soal sebelum expired
$answerService->saveAnswer($token4, $studentAId, $question1Id, $correctOptionId, null, 0);

// Simulasikan waktu sesi lewat 2 jam
$pastTime = date('Y-m-d H:i:s', time() - 7200);
$db->exec("UPDATE exam_sessions SET start_time = '{$pastTime}', remaining_seconds = 0 WHERE id = {$sess4Id}");

runTest("4. Submit sesi setelah deadline tetap berhasil memfinalisasi dan menilai jawaban", function () use ($sessionService, $token4, $studentAId, $db, $sess4Id) {
    $expiredSubmitRes = $sessionService->submitExam($token4, $studentAId);

    if (empty($expiredSubmitRes['success'])) {
        throw new Exception("Submit sesi expired gagal: " . ($expiredSubmitRes['error'] ?? ''));
    }
    if (empty($expiredSubmitRes['result_id'])) {
        throw new Exception("Result ID tidak dibuat untuk submit sesi expired.");
    }

    // Cek status sesi di DB
    $status = $db->query("SELECT status FROM exam_sessions WHERE id = {$sess4Id}")->fetchColumn();
    if ($status !== 'submitted') {
        throw new Exception("Status sesi di DB bukan 'submitted', melainkan: {$status}");
    }
});

// ----------------------------------------------------------------------
// TEST 5: Submit dengan Sesi Invalid / Unauthorized / Terminated
// ----------------------------------------------------------------------
echo "\n[TEST 5] Menguji penolakan submit pada sesi invalid, milik user lain, atau terminated...\n";

runTest("5.1 Token Sesi Palsu / Tidak Ditemukan -> REJECT", function () use ($sessionService, $studentAId) {
    $res = $sessionService->submitExam('fake_session_token_xyz_99999', $studentAId);
    if (!empty($res['success'])) {
        throw new Exception("Server mengizinkan submit dengan token palsu!");
    }
    if (!str_contains($res['error'] ?? '', 'tidak ditemukan')) {
        throw new Exception("Pesan error tidak sesuai: " . ($res['error'] ?? ''));
    }
});

$testSess5 = createTestSession($examId, $studentAId);
$token5 = $testSess5['token'];
$sess5Id = $testSess5['id'];

runTest("5.2 Sesi Milik Student A disubmit oleh Student B -> REJECT (Akses ditolak)", function () use ($sessionService, $token5, $studentBId) {
    $res = $sessionService->submitExam($token5, $studentBId);
    if (!empty($res['success'])) {
        throw new Exception("Student B berhasil mensubmit sesi Student A!");
    }
    if (!str_contains($res['error'] ?? '', 'peserta lain')) {
        throw new Exception("Pesan error otorisasi tidak sesuai: " . ($res['error'] ?? ''));
    }
});

// Set status sesi ke 'terminated'
$db->exec("UPDATE exam_sessions SET status = 'terminated' WHERE id = {$sess5Id}");

runTest("5.3 Sesi yang telah Terminated / Didiskualifikasi -> REJECT", function () use ($sessionService, $token5, $studentAId) {
    $res = $sessionService->submitExam($token5, $studentAId);
    if (!empty($res['success'])) {
        throw new Exception("Server mengizinkan submit pada sesi yang terminated!");
    }
    if (!str_contains($res['error'] ?? '', 'dihentikan') && !str_contains($res['error'] ?? '', 'diterminasi')) {
        throw new Exception("Pesan error terminasi tidak sesuai: " . ($res['error'] ?? ''));
    }
});

// Cleanup test data
$db->exec("DELETE FROM exam_answers WHERE exam_session_id IN ({$sess1Id}, {$sess4Id}, {$sess5Id})");
$db->exec("DELETE FROM exam_results WHERE exam_session_id IN ({$sess1Id}, {$sess4Id}, {$sess5Id})");
$db->exec("DELETE FROM exam_sessions WHERE id IN ({$sess1Id}, {$sess4Id}, {$sess5Id})");
$db->exec("DELETE FROM notifications WHERE user_id IN ({$studentAId}, {$studentBId}) AND message LIKE '%Penilaian Akhir Semester%'");

echo "\n==============================================================================\n";
echo "HASIL PENGUJIAN: {$passedCount} / {$totalCount} Test Case BERHASIL (" . round(($passedCount / $totalCount) * 100) . "% PASSED)\n";
echo "==============================================================================\n";
