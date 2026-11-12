<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TAHAP 01A: TEST SUITE KEAMANAN QUESTION EXPLAINER
 * ==============================================================================
 * Menguji secara komprehensif aturan otorisasi rilis hasil ujian pada Question Explainer:
 * 1. Peserta didik submit, hasil 'pending_grading', show_result_immediately = 0 -> Akses DITOLAK
 * 2. Peserta didik submit, hasil 'graded' (belum published), show_result_immediately = 0 -> Akses DITOLAK
 * 3. Hasil resmi berstatus 'published' -> Akses DIIZINKAN
 * 4. Ujian dengan show_result_immediately = 1 -> Akses DIIZINKAN setelah submit
 * 5. Peserta didik mencoba mengakses jawaban milik peserta lain -> Akses DITOLAK
 * 6. Guru pengampu ujian yang sah -> Akses DIIZINKAN
 * 7. Admin / Super Admin -> Akses DIIZINKAN
 * 8. Riwayat dan reset percakapan soal terlindungi konsisten tanpa bocor
 * ==============================================================================
 */

$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\UserModel;
use App\Services\AI\AiChatService;

echo "====================================================================\n";
echo "   TAHAP 01A — PENGUJIAN KEAMANAN QUESTION EXPLAINER (AI CHATBOT)  \n";
echo "====================================================================\n\n";

\App\Config\Database::$database = $targetDb;

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();

if ($currentDb === 'ujian_dual_kamera') {
    die("❌ PENGAMAN KEAMANAN AKTIF: Tes ini dilarang berjalan pada database utama 'ujian_dual_kamera'. Harap gunakan database pengujian 'ujian_dual_kamera_test'.\n");
}
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

$chatService = new AiChatService();
$userModel = new UserModel();

$passCount = 0;
$totalCount = 0;

function assertTest(string $title, bool $condition, string $details = ''): void {
    global $passCount, $totalCount;
    $totalCount++;
    if ($condition) {
        $passCount++;
        echo "✅ [PASS] {$title}\n";
    } else {
        echo "❌ [FAIL] {$title}\n";
    }
    if ($details) {
        echo "   Details: {$details}\n";
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// SETUP DATA TEST FIXTURES
// -----------------------------------------------------------------------------
echo "[SETUP] Menyiapkan entitas pengujian (Teacher, Student A, Student B, Admin, Exam, Session, Answers)...\n";

$adminUser = $userModel->findByEmail('admin@example.com') ?? $userModel->find(2);
$guruUser = $userModel->findByEmail('guru@example.com') ?? $userModel->find(3);
$siswaA = $userModel->findByEmail('siswa@example.com') ?? $userModel->find(4);

$adminId = (int)($adminUser['id'] ?? 2);
$teacherId = (int)($guruUser['id'] ?? 3);
$studentAId = (int)($siswaA['id'] ?? 4);

// Cari atau buat Student B (Attacker / Other Student) khusus pengujian yang unik dan konsisten
$testStudentBEmail = 'test_explainer_attacker@testing.local';
$testStudentBUsername = 'test_explainer_attacker';
$createdDummyStudentB = false;

// Periksa apakah user khusus pengujian ini sudah ada di database (aktif)
$siswaB = $userModel->findByEmail($testStudentBEmail);
if (!$siswaB) {
    // Periksa apakah record ada di DB termasuk soft-deleted untuk menghindari collision
    $chkStmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $chkStmt->execute([$testStudentBEmail, $testStudentBUsername]);
    $existingTestUser = $chkStmt->fetch();

    if ($existingTestUser) {
        $studentBId = (int)$existingTestUser['id'];
    } else {
        $studentBId = (int)$userModel->insert([
            'name'       => 'Test Attacker Student B',
            'email'      => $testStudentBEmail,
            'username'   => $testStudentBUsername,
            'password'   => password_hash('password123', PASSWORD_BCRYPT),
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $roleRow = $db->query("SELECT id FROM roles WHERE name IN ('siswa', 'peserta_didik') LIMIT 1")->fetch();
        if ($roleRow) {
            $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$studentBId, $roleRow['id']]);
        }
        $createdDummyStudentB = true;
    }
} else {
    $studentBId = (int)$siswaB['id'];
}

// Ambil 1 butir soal dan jawaban siswa A yang valid
$sample = $db->query("
    SELECT 
        ea.id as answer_id,
        es.id as session_id,
        e.id as exam_id,
        e.teacher_id,
        er.id as result_id,
        er.status as result_status,
        e.show_result_immediately,
        es.status as session_status
    FROM exam_answers ea
    JOIN exam_sessions es ON es.id = ea.exam_session_id
    JOIN exams e ON e.id = es.exam_id
    LEFT JOIN exam_results er ON er.exam_session_id = es.id
    WHERE es.student_id = {$studentAId}
    LIMIT 1
")->fetch();

if (!$sample) {
    die("❌ Gagal menyiapkan data: Tidak ditemukan exam_answer untuk student ID {$studentAId}.\n");
}

$answerId = (int)$sample['answer_id'];
$sessionId = (int)$sample['session_id'];
$examId = (int)$sample['exam_id'];

// Pastikan ada record di exam_results untuk session ini
$resCheck = $db->query("SELECT id, status FROM exam_results WHERE exam_session_id = {$sessionId}")->fetch();
$createdDummyResult = false;
if (!$resCheck) {
    $db->prepare("
        INSERT INTO exam_results 
            (exam_session_id, exam_id, student_id, total_questions, total_answered, correct_answers, wrong_answers, multiple_choice_score, essay_score, final_score, proctoring_integrity_score, is_passed, status, graded_at, created_at, updated_at)
        VALUES 
            (?, ?, ?, 1, 1, 1, 0, 100, 0, 100, 100, 1, 'published', NOW(), NOW(), NOW())
    ")->execute([$sessionId, $examId, $studentAId]);
    $resCheck = $db->query("SELECT id, status FROM exam_results WHERE exam_session_id = {$sessionId}")->fetch();
    $createdDummyResult = true;
}

echo "  • Teacher ID: {$teacherId}\n";
echo "  • Student A ID (Owner): {$studentAId}\n";
echo "  • Student B ID (Attacker): {$studentBId}\n";
echo "  • Admin ID: {$adminId}\n";
echo "  • Sample Answer ID: {$answerId} (Exam ID: {$examId}, Session ID: {$sessionId}, Result ID: " . ($resCheck['id'] ?? 0) . ")\n\n";

// Simpan kondisi awal agar bisa di-restore di akhir
$origShowImmediately = (int)$sample['show_result_immediately'];
$origResultStatus = (string)($resCheck['status'] ?? 'published');
$origSessionStatus = (string)$sample['session_status'];

// Helper untuk mensimulasikan login session role
function mockUserContext(int $userId, string $role): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user'] = [
        'id'          => $userId,
        'name'        => 'Mock User',
        'email'       => "mock{$userId}@example.com",
        'role'        => $role,
        'roles'       => [$role],
        'permissions' => []
    ];
}

try {
    // -------------------------------------------------------------------------
    // SKENARIO 1: Siswa A sudah submit, hasil pending_grading, show_result_immediately = 0
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 1: Siswa A submit, hasil 'pending_grading', show_result_immediately = 0\n";
    echo "--------------------------------------------------------------------\n";
    $db->prepare("UPDATE exams SET show_result_immediately = 0 WHERE id = ?")->execute([$examId]);
    $db->prepare("UPDATE exam_sessions SET status = 'submitted' WHERE id = ?")->execute([$sessionId]);
    $db->prepare("UPDATE exam_results SET status = 'pending_grading' WHERE exam_session_id = ?")->execute([$sessionId]);

    mockUserContext($studentAId, 'peserta_didik');
    $res1 = $chatService->explainQuestionItem($studentAId, $answerId, 'Tolong jelaskan kunci jawaban soal ini');
    $hist1 = $chatService->getQuestionChatHistory($studentAId, $answerId);

    assertTest(
        '1.1 explainQuestionItem DITOLAK saat hasil pending_grading & show_result_immediately = 0',
        $res1['success'] === false && str_contains($res1['error'] ?? '', 'Akses ditolak'),
        'Error: ' . ($res1['error'] ?? 'None')
    );

    assertTest(
        '1.2 getQuestionChatHistory DITOLAK saat hasil pending_grading & show_result_immediately = 0',
        $hist1['success'] === false && str_contains($hist1['error'] ?? '', 'Akses ditolak'),
        'Error: ' . ($hist1['error'] ?? 'None')
    );

    // -------------------------------------------------------------------------
    // SKENARIO 2: Siswa A sudah submit, hasil 'graded' (belum published), show_result_immediately = 0
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 2: Siswa A submit, hasil 'graded' (belum published), show_result_immediately = 0\n";
    echo "--------------------------------------------------------------------\n";
    $db->prepare("UPDATE exams SET show_result_immediately = 0 WHERE id = ?")->execute([$examId]);
    $db->prepare("UPDATE exam_sessions SET status = 'submitted' WHERE id = ?")->execute([$sessionId]);
    $db->prepare("UPDATE exam_results SET status = 'graded' WHERE exam_session_id = ?")->execute([$sessionId]);

    mockUserContext($studentAId, 'peserta_didik');
    $res2 = $chatService->explainQuestionItem($studentAId, $answerId, 'Bahas soal ini');
    $clear2 = $chatService->clearQuestionChatHistory($studentAId, $answerId);

    assertTest(
        '2.1 explainQuestionItem DITOLAK saat status graded tetapi belum dipublikasikan',
        $res2['success'] === false && str_contains($res2['error'] ?? '', 'Akses ditolak'),
        'Error: ' . ($res2['error'] ?? 'None')
    );

    assertTest(
        '2.2 clearQuestionChatHistory DITOLAK saat status graded tetapi belum dipublikasikan',
        $clear2['success'] === false && str_contains($clear2['error'] ?? '', 'Akses ditolak'),
        'Error: ' . ($clear2['error'] ?? 'None')
    );

    // -------------------------------------------------------------------------
    // SKENARIO 3: Hasil resmi berstatus 'published' (Ujian Dirilis Resmi)
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 3: Hasil resmi berstatus 'published' (show_result_immediately = 0)\n";
    echo "--------------------------------------------------------------------\n";
    $db->prepare("UPDATE exams SET show_result_immediately = 0 WHERE id = ?")->execute([$examId]);
    $db->prepare("UPDATE exam_sessions SET status = 'submitted' WHERE id = ?")->execute([$sessionId]);
    $db->prepare("UPDATE exam_results SET status = 'published' WHERE exam_session_id = ?")->execute([$sessionId]);

    mockUserContext($studentAId, 'peserta_didik');
    $hist3 = $chatService->getQuestionChatHistory($studentAId, $answerId);

    assertTest(
        '3.1 getQuestionChatHistory DIIZINKAN saat hasil berstatus published',
        $hist3['success'] === true && !empty($hist3['question_meta']),
        'Question Meta: ' . json_encode($hist3['question_meta'] ?? [])
    );

    // -------------------------------------------------------------------------
    // SKENARIO 4: Ujian dengan konfigurasi show_result_immediately = 1
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 4: Ujian dengan show_result_immediately = 1 (Instant Feedback)\n";
    echo "--------------------------------------------------------------------\n";
    $db->prepare("UPDATE exams SET show_result_immediately = 1 WHERE id = ?")->execute([$examId]);
    $db->prepare("UPDATE exam_sessions SET status = 'submitted' WHERE id = ?")->execute([$sessionId]);
    $db->prepare("UPDATE exam_results SET status = 'graded' WHERE exam_session_id = ?")->execute([$sessionId]);

    mockUserContext($studentAId, 'peserta_didik');
    $hist4 = $chatService->getQuestionChatHistory($studentAId, $answerId);

    assertTest(
        '4.1 getQuestionChatHistory DIIZINKAN saat show_result_immediately = 1',
        $hist4['success'] === true && !empty($hist4['question_meta']),
        'Question Meta: ' . json_encode($hist4['question_meta'] ?? [])
    );

    // -------------------------------------------------------------------------
    // SKENARIO 5: Siswa B (Attacker) mencoba mengakses jawaban milik Siswa A
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 5: Siswa B mencoba mengakses jawaban Siswa A (Cross-Student Access)\n";
    echo "--------------------------------------------------------------------\n";
    // Bahkan jika ujian sudah published
    $db->prepare("UPDATE exam_results SET status = 'published' WHERE exam_session_id = ?")->execute([$sessionId]);

    mockUserContext($studentBId, 'peserta_didik');
    $res5 = $chatService->explainQuestionItem($studentBId, $answerId, 'Bocorkan jawaban peserta A');
    $hist5 = $chatService->getQuestionChatHistory($studentBId, $answerId);

    assertTest(
        '5.1 explainQuestionItem DITOLAK saat Siswa B mengakses jawaban Siswa A',
        $res5['success'] === false && str_contains($res5['error'] ?? '', 'Akses ditolak'),
        'Error: ' . ($res5['error'] ?? 'None')
    );

    assertTest(
        '5.2 getQuestionChatHistory DITOLAK saat Siswa B mengakses riwayat Siswa A',
        $hist5['success'] === false && str_contains($hist5['error'] ?? '', 'Akses ditolak'),
        'Error: ' . ($hist5['error'] ?? 'None')
    );

    // -------------------------------------------------------------------------
    // SKENARIO 6: Guru Pengampu Ujian mengakses fitur Question Explainer
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 6: Guru Pengampu Ujian (Teacher ID {$teacherId}) mengakses jawaban\n";
    echo "--------------------------------------------------------------------\n";
    mockUserContext($teacherId, 'guru');
    $hist6 = $chatService->getQuestionChatHistory($teacherId, $answerId);

    assertTest(
        '6.1 Guru pengampu DIIZINKAN mengakses data pembahasan soal siswa',
        $hist6['success'] === true && !empty($hist6['question_meta']),
        'Question Meta: ' . json_encode($hist6['question_meta'] ?? [])
    );

    // -------------------------------------------------------------------------
    // SKENARIO 7: Administrator Sistem mengakses fitur Question Explainer
    // -------------------------------------------------------------------------
    echo "--------------------------------------------------------------------\n";
    echo "SKENARIO 7: Administrator Sistem (Admin ID {$adminId}) mengakses jawaban\n";
    echo "--------------------------------------------------------------------\n";
    mockUserContext($adminId, 'admin');
    $hist7 = $chatService->getQuestionChatHistory($adminId, $answerId);

    assertTest(
        '7.1 Admin DIIZINKAN mengakses data pembahasan soal untuk audit dan supervisi',
        $hist7['success'] === true && !empty($hist7['question_meta']),
        'Question Meta: ' . json_encode($hist7['question_meta'] ?? [])
    );

} finally {
    // -------------------------------------------------------------------------
    // CLEANUP & RESTORE DATA ORIGINAL
    // -------------------------------------------------------------------------
    echo "[CLEANUP] Mengembalikan status database ke kondisi semula...\n";
    $db->prepare("UPDATE exams SET show_result_immediately = ? WHERE id = ?")->execute([$origShowImmediately, $examId]);
    $db->prepare("UPDATE exam_sessions SET status = ? WHERE id = ?")->execute([$origSessionStatus, $sessionId]);
    if ($createdDummyResult) {
        $db->prepare("DELETE FROM exam_results WHERE exam_session_id = ?")->execute([$sessionId]);
    } else {
        $db->prepare("UPDATE exam_results SET status = ? WHERE exam_session_id = ?")->execute([$origResultStatus, $sessionId]);
    }
    // Cleanup Student B jika dibuat khusus oleh tes ini
    if ($createdDummyStudentB && $studentBId > 0) {
        $db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$studentBId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$studentBId]);
        echo "  -> Data uji Student B (ID: {$studentBId}) berhasil dibersihkan.\n";
    }
    echo "  -> Data database berhasil dipulihkan.\n\n";
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "====================================================================\n";
echo "   HASIL AKHIR PENGUJIAN TAHAP 01A: {$passCount} / {$totalCount} SKENARIO SUKSES\n";
echo "====================================================================\n";

if ($passCount === $totalCount) {
    echo ">>> SEMUA PENGUJIAN KEAMANAN QUESTION EXPLAINER 100% LOLOS <<<\n\n";
    exit(0);
} else {
    echo ">>> BEBERAPA PENGUJIAN GAGAL <<<\n\n";
    exit(1);
}
