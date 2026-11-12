<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamSessionModel;
use App\Models\UserModel;
use App\Services\ExamAnswerService;
use App\Services\ExamSessionService;
use App\Services\ExamTimerService;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: SERVER-SIDE EXAM TIMER & DEADLINE HARDENING\n";
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

// ----------------------------------------------------------------------
// SETUP: Siswa, Ujian & Soal Uji
// ----------------------------------------------------------------------
$db = Database::getConnection();
$userModel = new UserModel();
$examModel = new ExamModel();
$sessionModel = new ExamSessionModel();
$participantModel = new ExamParticipantModel();
$examQuestionModel = new ExamQuestionModel();

$timerService = new ExamTimerService();
$answerService = new ExamAnswerService();
$sessionService = new ExamSessionService();

// Ambil / siapkan Student 1 & Student 2
$student1 = $userModel->findByEmail('siswa@sekolah.sch.id') ?? $userModel->find(4);
if (!$student1) {
    throw new RuntimeException("Student 1 tidak ditemukan.");
}
$student1Id = (int)$student1['id'];

$student2 = $userModel->findByEmail('siswa2@sekolah.sch.id');
if (!$student2) {
    $st2Id = $userModel->insert([
        'role_id'   => 4,
        'name'      => 'Siswa Kedua (Tester)',
        'username'  => 'siswa_tester_2',
        'email'     => 'siswa2@sekolah.sch.id',
        'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
        'status'    => 'active',
        'created_at'=> date('Y-m-d H:i:s'),
        'updated_at'=> date('Y-m-d H:i:s')
    ]);
    $student2 = $userModel->find($st2Id);
}
$student2Id = (int)$student2['id'];

// Ambil atau siapkan ujian aktif dengan durasi 60 menit yang memiliki butir soal
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
$durationMinutes = (int)($exam['duration_minutes'] ?? 60);

$firstQuestion = $questions[0];
$questionId = (int)$firstQuestion['question_id'];
$firstOptionId = !empty($firstQuestion['options']) ? (int)$firstQuestion['options'][0]['id'] : 1;

// Pastikan student 1 terdaftar
$db->exec("INSERT IGNORE INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
           VALUES ({$examId}, {$student1Id}, 1, 'in_progress', NOW(), NOW())");
$db->exec("INSERT IGNORE INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
           VALUES ({$examId}, {$student2Id}, 1, 'in_progress', NOW(), NOW())");

echo "[Setup OK] Student 1 ID: {$student1Id}, Student 2 ID: {$student2Id}, Exam ID: {$examId}, Question ID: {$questionId}\n\n";

// ----------------------------------------------------------------------
// TEST 1: Ongoing + deadline masih tersedia → save answer berhasil
// ----------------------------------------------------------------------
echo "[TEST 1] Menguji penyimpanan jawaban saat sesi ongoing dan waktu masih tersedia...\n";
$token1 = bin2hex(random_bytes(32));
$now = date('Y-m-d H:i:s');
$sess1Id = (int)$sessionModel->insert([
    'exam_id'           => $examId,
    'student_id'        => $student1Id,
    'session_token'     => $token1,
    'ip_address'        => '127.0.0.1',
    'start_time'        => $now,
    'remaining_seconds' => $durationMinutes * 60,
    'status'            => 'ongoing',
    'integrity_score'   => 100.0,
    'created_at'        => $now,
    'updated_at'        => $now
]);

runTest("TEST 1: Ongoing + deadline valid -> Save answer berhasil", function () use ($answerService, $token1, $student1Id, $questionId, $firstOptionId) {
    $result = $answerService->saveAnswer(
        $token1,
        $student1Id,
        $questionId,
        $firstOptionId,
        null,
        0
    );

    if (empty($result['success'])) {
        throw new Exception("Gagal menyimpan jawaban: " . ($result['error'] ?? 'Unknown error'));
    }
    if (empty($result['counts'])) {
        throw new Exception("Counts ringkasan jawaban tidak dikembalikan.");
    }
    if (!isset($result['remaining_seconds']) || $result['remaining_seconds'] <= 0) {
        throw new Exception("Remaining seconds server tidak valid.");
    }
});

// ----------------------------------------------------------------------
// TEST 2: Ongoing + deadline sudah lewat → save answer ditolak (expired)
// ----------------------------------------------------------------------
echo "\n[TEST 2] Menguji penolakan save answer saat deadline server telah lewat...\n";
// Set start_time sesi ke 3 jam yang lalu (sehingga deadline 60 menit sudah terlewati)
$pastStartTime = date('Y-m-d H:i:s', time() - 10800);
$db->exec("UPDATE exam_sessions SET start_time = '{$pastStartTime}' WHERE id = {$sess1Id}");

runTest("TEST 2: Ongoing + deadline sudah lewat -> Save answer ditolak (code: EXPIRED)", function () use ($answerService, $token1, $student1Id, $questionId, $firstOptionId) {
    $result = $answerService->saveAnswer(
        $token1,
        $student1Id,
        $questionId,
        $firstOptionId,
        null,
        0
    );

    if (!empty($result['success'])) {
        throw new Exception("Server menerima jawaban meskipun deadline telah berakhir!");
    }
    if (($result['code'] ?? '') !== 'EXPIRED' || empty($result['is_expired'])) {
        throw new Exception("Error code yang diharapkan 'EXPIRED', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// TEST 3: Deadline sudah lewat → submit ditangani server secara konsisten
// ----------------------------------------------------------------------
echo "\n[TEST 3] Menguji submit ujian saat deadline telah terlewat (Auto-Submit / Late Submit)...\n";
runTest("TEST 3: Deadline sudah lewat -> Submit diproses server secara konsisten", function () use ($sessionService, $token1, $student1Id, $sess1Id, $db) {
    $result = $sessionService->submitExam($token1, $student1Id);

    if (empty($result['success'])) {
        throw new Exception("Submit gagal saat deadline lewat: " . ($result['error'] ?? ''));
    }
    if (empty($result['result_id'])) {
        throw new Exception("Result ID tidak dibuat saat submit.");
    }

    // Periksa status sesi di database harus sudah 'submitted'
    $sessStmt = $db->query("SELECT status, submit_time, remaining_seconds FROM exam_sessions WHERE id = {$sess1Id}");
    $sessRow = $sessStmt->fetch();
    if ($sessRow['status'] !== 'submitted') {
        throw new Exception("Status sesi di DB bukan 'submitted', melainkan: " . $sessRow['status']);
    }
    if ((int)$sessRow['remaining_seconds'] !== 0) {
        throw new Exception("Remaining seconds sesi harus 0 setelah disubmit.");
    }
});

// ----------------------------------------------------------------------
// TEST 4: Session sudah submitted → tidak dapat save answer lagi
// ----------------------------------------------------------------------
echo "\n[TEST 4] Menguji proteksi save answer pada sesi yang telah submitted...\n";
runTest("TEST 4: Sesi submitted -> Save answer ditolak (code: ALREADY_SUBMITTED)", function () use ($answerService, $token1, $student1Id, $questionId, $firstOptionId) {
    $result = $answerService->saveAnswer(
        $token1,
        $student1Id,
        $questionId,
        $firstOptionId,
        null,
        0
    );

    if (!empty($result['success'])) {
        throw new Exception("Server mengizinkan save answer pada sesi yang sudah disubmit!");
    }
    if (($result['code'] ?? '') !== 'ALREADY_SUBMITTED') {
        throw new Exception("Error code yang diharapkan 'ALREADY_SUBMITTED', didapat: " . ($result['code'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// TEST 5: Session milik student lain → ditolak
// ----------------------------------------------------------------------
echo "\n[TEST 5] Menguji otorisasi kepemilikan sesi (Student Isolation)...\n";
$token2 = bin2hex(random_bytes(32));
$sess2Id = (int)$sessionModel->insert([
    'exam_id'           => $examId,
    'student_id'        => $student1Id, // Milik student 1
    'session_token'     => $token2,
    'ip_address'        => '127.0.0.1',
    'start_time'        => date('Y-m-d H:i:s'),
    'remaining_seconds' => $durationMinutes * 60,
    'status'            => 'ongoing',
    'integrity_score'   => 100.0,
    'created_at'        => date('Y-m-d H:i:s'),
    'updated_at'        => date('Y-m-d H:i:s')
]);

runTest("TEST 5.1: Student 2 mencoba save answer ke sesi Student 1 -> Ditolak (UNAUTHORIZED)", function () use ($answerService, $token2, $student2Id, $questionId, $firstOptionId) {
    $result = $answerService->saveAnswer(
        $token2,
        $student2Id, // Student 2 mencoba mengakses
        $questionId,
        $firstOptionId,
        null,
        0
    );

    if (!empty($result['success'])) {
        throw new Exception("Student 2 berhasil menyimpan jawaban ke sesi Student 1!");
    }
    if (($result['code'] ?? '') !== 'UNAUTHORIZED') {
        throw new Exception("Error code yang diharapkan 'UNAUTHORIZED', didapat: " . ($result['code'] ?? ''));
    }
});

runTest("TEST 5.2: Student 2 mencoba submit sesi Student 1 -> Ditolak", function () use ($sessionService, $token2, $student2Id) {
    $result = $sessionService->submitExam($token2, $student2Id);

    if (!empty($result['success'])) {
        throw new Exception("Student 2 berhasil submit sesi Student 1!");
    }
    if (!str_contains($result['error'] ?? '', 'peserta lain')) {
        throw new Exception("Pesan error otorisasi tidak sesuai: " . ($result['error'] ?? ''));
    }
});

// ----------------------------------------------------------------------
// TEST 6: Request manipulasi remaining_seconds dari client → tidak dapat memperpanjang waktu
// ----------------------------------------------------------------------
echo "\n[TEST 6] Menguji manipulasi waktu client (Anti-Tampering remaining_seconds)...\n";
runTest("TEST 6: Manipulasi parameter remaining_seconds di request client diabaikan", function () use ($timerService, $token2, $student1Id, $durationMinutes) {
    // Client mengirim payload tiruan dengan sisa waktu palsu 999999 detik
    $_POST['remaining_seconds'] = 999999;
    $_GET['remaining_seconds'] = 999999;

    $syncResult = $timerService->syncTimer($token2, $student1Id);

    if (!$syncResult['success']) {
        throw new Exception("Sync timer gagal: " . ($syncResult['error'] ?? ''));
    }

    $expectedMaxSeconds = $durationMinutes * 60;
    if ($syncResult['remaining_seconds'] > $expectedMaxSeconds) {
        throw new Exception("Server termanipulasi oleh client! Remaining seconds: " . $syncResult['remaining_seconds']);
    }

    // Periksa bahwa kalkulasi server strictly <= durasi ujian
    assert($syncResult['remaining_seconds'] <= $expectedMaxSeconds, "Waktu tidak boleh melebihi durasi ujian asli.");
    assert($syncResult['remaining_seconds'] >= $expectedMaxSeconds - 10, "Waktu server harus akurat dalam rentang toleransi 10 detik.");
});

// ----------------------------------------------------------------------
// TEST 7: Refresh halaman tidak mengubah deadline server
// ----------------------------------------------------------------------
echo "\n[TEST 7] Menguji persistensi deadline server saat refresh halaman...\n";
runTest("TEST 7: Refresh halaman mempertahankan start_time dan deadline absolut", function () use ($timerService, $sessionService, $token2, $student1Id, $db, $sess2Id) {
    // Ambil start_time awal di DB
    $initStmt = $db->query("SELECT start_time FROM exam_sessions WHERE id = {$sess2Id}");
    $initialStartTime = $initStmt->fetchColumn();

    // Simulasikan 3x refresh halaman berurutan (getExamRoomData & syncTimer)
    $roomData1 = $sessionService->getExamRoomData($token2, $student1Id);
    $sync1 = $timerService->syncTimer($token2, $student1Id);

    // Sleep 1 detik untuk memverifikasi waktu server terus berjalan
    sleep(1);

    $roomData2 = $sessionService->getExamRoomData($token2, $student1Id);
    $sync2 = $timerService->syncTimer($token2, $student1Id);

    // Periksa start_time tidak boleh berubah
    $afterStmt = $db->query("SELECT start_time FROM exam_sessions WHERE id = {$sess2Id}");
    $afterStartTime = $afterStmt->fetchColumn();

    if ($initialStartTime !== $afterStartTime) {
        throw new Exception("Start time berubah setelah refresh halaman! Awal: {$initialStartTime}, Sesudah: {$afterStartTime}");
    }

    // Remaining seconds pada sync2 harus lebih kecil atau sama dengan sync1
    if ($sync2['remaining_seconds'] > $sync1['remaining_seconds']) {
        throw new Exception("Remaining seconds bertambah setelah refresh halaman! sync1: {$sync1['remaining_seconds']}, sync2: {$sync2['remaining_seconds']}");
    }
});

// Cleanup test session 2
$db->exec("DELETE FROM exam_answers WHERE exam_session_id IN ({$sess1Id}, {$sess2Id})");
$db->exec("DELETE FROM exam_results WHERE exam_session_id IN ({$sess1Id}, {$sess2Id})");
$db->exec("DELETE FROM exam_sessions WHERE id IN ({$sess1Id}, {$sess2Id})");

echo "\n==============================================================================\n";
echo "HASIL PENGUJIAN: {$passedCount} / {$totalCount} Test Case BERHASIL (" . round(($passedCount / $totalCount) * 100) . "% PASSED)\n";
echo "==============================================================================\n";
