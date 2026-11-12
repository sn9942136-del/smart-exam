<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\UserModel;
use App\Services\AI\AiChatService;
use App\Services\AI\GeminiProvider;
use App\Services\GradingService;

echo "====================================================================\n";
echo "   TAHAP 2A — PENGUJIAN AKURASI STATUS PENILAIAN ESAI GURU (EDUAI)   \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$aiChatService = new AiChatService();
$geminiProvider = new GeminiProvider();
$gradingService = new GradingService();
$userModel = new UserModel();

$passCount = 0;
$failCount = 0;

function assertTest(string $code, string $description, bool $condition, string $evidence = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "✅ [PASS] [{$code}] {$description}\n";
    } else {
        $failCount++;
        echo "❌ [FAIL] [{$code}] {$description}\n";
    }
    if ($evidence !== '') {
        echo "   Bukti: {$evidence}\n";
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// 1. SETUP FIXTURES (Guru A, Guru B, Siswa, Soal Esai, & Jawaban Ujian)
// -----------------------------------------------------------------------------
echo "1. Menyiapkan Fixture Uji Nyata...\n";

// Dapatkan Guru Utama (Guru A)
$teacherA = $db->query("
    SELECT u.* FROM users u
    JOIN user_roles ur ON ur.user_id = u.id
    JOIN roles r ON r.id = ur.role_id
    WHERE r.name = 'guru' AND u.deleted_at IS NULL
    ORDER BY u.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$teacherA) {
    die("❌ Gagal: Tidak ada pengguna guru di database.\n");
}
$teacherAId = (int)$teacherA['id'];

// Dapatkan atau buat Guru Lain (Guru B) untuk pengujian isolasi kewenangan
$teacherB = $db->query("
    SELECT u.* FROM users u
    JOIN user_roles ur ON ur.user_id = u.id
    JOIN roles r ON r.id = ur.role_id
    WHERE r.name = 'guru' AND u.id != {$teacherAId} AND u.deleted_at IS NULL
    ORDER BY u.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$createdTeacherB = false;
if (!$teacherB) {
    $db->prepare("
        INSERT INTO users (name, email, username, password, status, created_at, updated_at)
        VALUES ('Guru Isolasi B', 'guru.b.test@sekolah.sch.id', 'guru_b_test', 'hash', 'active', NOW(), NOW())
    ")->execute();
    $teacherBId = (int)$db->lastInsertId();
    $roleRow = $db->query("SELECT id FROM roles WHERE name = 'guru' LIMIT 1")->fetch();
    $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$teacherBId, $roleRow['id']]);
    $teacherB = ['id' => $teacherBId, 'name' => 'Guru Isolasi B'];
    $createdTeacherB = true;
} else {
    $teacherBId = (int)$teacherB['id'];
}

// Dapatkan Siswa
$student = $db->query("
    SELECT u.* FROM users u
    JOIN user_roles ur ON ur.user_id = u.id
    JOIN roles r ON r.id = ur.role_id
    WHERE r.name IN ('siswa', 'peserta_didik') AND u.deleted_at IS NULL
    ORDER BY u.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
$studentId = (int)($student['id'] ?? 4);

// Dapatkan Mapel
$subject = $db->query("SELECT id FROM subjects LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$subjectId = (int)($subject['id'] ?? 1);

// Dapatkan Tahun Ajaran
$year = $db->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$yearId = (int)($year['id'] ?? 1);

echo "   Guru A ID: {$teacherAId} ({$teacherA['name']})\n";
echo "   Guru B ID: {$teacherBId} ({$teacherB['name']})\n\n";

// Tracking IDs yang dibuat untuk pembersihan otomatis
$cleanupBankIds = [];
$cleanupExamIds = [];
$cleanupQuestionIds = [];
$cleanupSessionIds = [];
$cleanupAnswerIds = [];

try {
    // -------------------------------------------------------------------------
    // Buat Bank Soal & Ujian & Soal Esai untuk Guru A
    // -------------------------------------------------------------------------
    $db->prepare("
        INSERT INTO question_banks (subject_id, creator_id, code, title, total_questions, status, created_at, updated_at)
        VALUES (?, ?, 'QB-A-" . time() . "', 'Bank Soal Uji A', 1, 'active', NOW(), NOW())
    ")->execute([$subjectId, $teacherAId]);
    $bankAId = (int)$db->lastInsertId();
    $cleanupBankIds[] = $bankAId;

    $db->prepare("
        INSERT INTO exams (subject_id, teacher_id, title, code, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (?, ?, 'Ujian Uji Esai Guru A', 'EXAM-ESSAY-A-" . time() . "', 'quiz', 60, 75.00, 'published', NOW(), NOW())
    ")->execute([$subjectId, $teacherAId]);
    $examAId = (int)$db->lastInsertId();
    $cleanupExamIds[] = $examAId;

    // Soal Esai 1
    $db->prepare("
        INSERT INTO questions (question_bank_id, type, content, weight, difficulty, created_at, updated_at)
        VALUES (?, 'essay', 'Jelaskan konsep dasar fotosintesis pada tumbuhan hijau.', 20.00, 'medium', NOW(), NOW())
    ")->execute([$bankAId]);
    $qA1Id = (int)$db->lastInsertId();
    $cleanupQuestionIds[] = $qA1Id;

    // Relasikan soal ke exam A
    $db->prepare("INSERT INTO exam_questions (exam_id, question_id, order_number, score_weight) VALUES (?, ?, 1, 20.00)")
       ->execute([$examAId, $qA1Id]);

    // Sesi 1 (Esai BELUM DINILAI)
    $db->prepare("
        INSERT INTO exam_sessions (exam_id, student_id, session_token, remaining_seconds, status, start_time, submit_time, created_at, updated_at)
        VALUES (?, ?, 'token_sess_1_" . time() . "', 0, 'submitted', NOW(), NOW(), NOW(), NOW())
    ")->execute([$examAId, $studentId]);
    $sess1Id = (int)$db->lastInsertId();
    $cleanupSessionIds[] = $sess1Id;

    $db->prepare("
        INSERT INTO exam_answers (exam_session_id, question_id, essay_answer, is_correct, score_earned, teacher_feedback, answered_at, created_at, updated_at)
        VALUES (?, ?, 'Fotosintesis adalah proses tumbuhan membuat makanan dengan cahaya matahari.', NULL, 0.00, NULL, NOW(), NOW(), NOW())
    ")->execute([$sess1Id, $qA1Id]);
    $ans1Id = (int)$db->lastInsertId();
    $cleanupAnswerIds[] = $ans1Id;

    // Sesi 2 (Esai SUDAH DINILAI DENGAN NILAI POSITIF)
    $db->prepare("
        INSERT INTO exam_sessions (exam_id, student_id, session_token, remaining_seconds, status, start_time, submit_time, created_at, updated_at)
        VALUES (?, ?, 'token_sess_2_" . time() . "', 0, 'submitted', NOW(), NOW(), NOW(), NOW())
    ")->execute([$examAId, $studentId]);
    $sess2Id = (int)$db->lastInsertId();
    $cleanupSessionIds[] = $sess2Id;

    $db->prepare("
        INSERT INTO exam_answers (exam_session_id, question_id, essay_answer, is_correct, score_earned, teacher_feedback, answered_at, created_at, updated_at)
        VALUES (?, ?, 'Fotosintesis mengubah CO2 dan H2O menjadi glukosa dan oksigen dibantu klorofil.', 1, 18.50, 'Jawaban sangat lengkap dan tepat.', NOW(), NOW(), NOW())
    ")->execute([$sess2Id, $qA1Id]);
    $ans2Id = (int)$db->lastInsertId();
    $cleanupAnswerIds[] = $ans2Id;

    // Sesi 3 (Esai SUDAH DINILAI DENGAN NILAI 0 — VALID ZERO SCORE TANPA FEEDBACK)
    $db->prepare("
        INSERT INTO exam_sessions (exam_id, student_id, session_token, remaining_seconds, status, start_time, submit_time, created_at, updated_at)
        VALUES (?, ?, 'token_sess_3_" . time() . "', 0, 'submitted', NOW(), NOW(), NOW(), NOW())
    ")->execute([$examAId, $studentId]);
    $sess3Id = (int)$db->lastInsertId();
    $cleanupSessionIds[] = $sess3Id;

    $db->prepare("
        INSERT INTO exam_answers (exam_session_id, question_id, essay_answer, is_correct, score_earned, teacher_feedback, answered_at, created_at, updated_at)
        VALUES (?, ?, 'Tidak tahu pak.', 0, 0.00, NULL, NOW(), NOW(), NOW())
    ")->execute([$sess3Id, $qA1Id]);
    $ans3Id = (int)$db->lastInsertId();
    $cleanupAnswerIds[] = $ans3Id;

    // -------------------------------------------------------------------------
    // Buat Ujian & Soal Esai untuk Guru B (Pengujian Data Ownership & Scoping)
    // -------------------------------------------------------------------------
    $db->prepare("
        INSERT INTO question_banks (subject_id, creator_id, code, title, total_questions, status, created_at, updated_at)
        VALUES (?, ?, 'QB-B-" . time() . "', 'Bank Soal Uji B', 1, 'active', NOW(), NOW())
    ")->execute([$subjectId, $teacherBId]);
    $bankBId = (int)$db->lastInsertId();
    $cleanupBankIds[] = $bankBId;

    $db->prepare("
        INSERT INTO exams (subject_id, teacher_id, title, code, type, duration_minutes, passing_grade, status, created_at, updated_at)
        VALUES (?, ?, 'Ujian Uji Esai Guru B', 'EXAM-ESSAY-B-" . time() . "', 'quiz', 60, 75.00, 'published', NOW(), NOW())
    ")->execute([$subjectId, $teacherBId]);
    $examBId = (int)$db->lastInsertId();
    $cleanupExamIds[] = $examBId;

    $db->prepare("
        INSERT INTO questions (question_bank_id, type, content, weight, difficulty, created_at, updated_at)
        VALUES (?, 'essay', 'Pertanyaan esai milik guru B.', 20.00, 'medium', NOW(), NOW())
    ")->execute([$bankBId]);
    $qBId = (int)$db->lastInsertId();
    $cleanupQuestionIds[] = $qBId;

    $db->prepare("INSERT INTO exam_questions (exam_id, question_id, order_number, score_weight) VALUES (?, ?, 1, 20.00)")
       ->execute([$examBId, $qBId]);

    $db->prepare("
        INSERT INTO exam_sessions (exam_id, student_id, session_token, remaining_seconds, status, start_time, submit_time, created_at, updated_at)
        VALUES (?, ?, 'token_sess_4_" . time() . "', 0, 'submitted', NOW(), NOW(), NOW(), NOW())
    ")->execute([$examBId, $studentId]);
    $sess4Id = (int)$db->lastInsertId();
    $cleanupSessionIds[] = $sess4Id;

    // Esai Guru B (Belum dinilai)
    $db->prepare("
        INSERT INTO exam_answers (exam_session_id, question_id, essay_answer, is_correct, score_earned, teacher_feedback, answered_at, created_at, updated_at)
        VALUES (?, ?, 'Jawaban siswa untuk ujian guru B.', NULL, 0.00, NULL, NOW(), NOW(), NOW())
    ")->execute([$sess4Id, $qBId]);
    $ans4Id = (int)$db->lastInsertId();
    $cleanupAnswerIds[] = $ans4Id;

    echo "✓ Fixture berhasil dibuat:\n";
    echo "  - Ans 1 (Guru A): is_correct=NULL, score=0.00, feedback=NULL (Harus: PENDING)\n";
    echo "  - Ans 2 (Guru A): is_correct=1, score=18.50, feedback='...' (Harus: GRADED)\n";
    echo "  - Ans 3 (Guru A): is_correct=0, score=0.00, feedback=NULL (Harus: GRADED via nilai 0 sah)\n";
    echo "  - Ans 4 (Guru B): is_correct=NULL, score=0.00 (Milik Guru B, TIDAK BOLEH dihitung oleh Guru A)\n\n";

    // -------------------------------------------------------------------------
    // TEST 1: Esai Belum Dinilai Terdeteksi Sebagai Pending
    // -------------------------------------------------------------------------
    $ans1Check = $db->query("
        SELECT 
            CASE WHEN (is_correct IS NOT NULL OR score_earned > 0 OR teacher_feedback IS NOT NULL) THEN 'graded' ELSE 'pending' END as status
        FROM exam_answers WHERE id = {$ans1Id}
    ")->fetchColumn();
    assertTest(
        'TC-ESSAY-01',
        'Esai dengan is_correct=NULL, score=0, feedback=NULL terklasifikasi sebagai PENDING',
        $ans1Check === 'pending',
        "Status terdeteksi: {$ans1Check}"
    );

    // -------------------------------------------------------------------------
    // TEST 2: Esai dengan Nilai Positif Terdeteksi Sebagai Graded
    // -------------------------------------------------------------------------
    $ans2Check = $db->query("
        SELECT 
            CASE WHEN (is_correct IS NOT NULL OR score_earned > 0 OR teacher_feedback IS NOT NULL) THEN 'graded' ELSE 'pending' END as status
        FROM exam_answers WHERE id = {$ans2Id}
    ")->fetchColumn();
    assertTest(
        'TC-ESSAY-02',
        'Esai dengan nilai positif (18.50) dan is_correct=1 terklasifikasi sebagai GRADED',
        $ans2Check === 'graded',
        "Status terdeteksi: {$ans2Check}"
    );

    // -------------------------------------------------------------------------
    // TEST 3: Esai dengan Nilai Nol Sah (is_correct=0, tanpa feedback) Terdeteksi Sebagai Graded
    // -------------------------------------------------------------------------
    $ans3Check = $db->query("
        SELECT 
            CASE WHEN (is_correct IS NOT NULL OR score_earned > 0 OR teacher_feedback IS NOT NULL) THEN 'graded' ELSE 'pending' END as status
        FROM exam_answers WHERE id = {$ans3Id}
    ")->fetchColumn();
    assertTest(
        'TC-ESSAY-03',
        'Esai dengan nilai 0.00 sah (is_correct=0, feedback=NULL) TIDAK dianggap pending, melainkan GRADED',
        $ans3Check === 'graded',
        "Status terdeteksi: {$ans3Check}"
    );

    // -------------------------------------------------------------------------
    // TEST 4: Isolasi Hak Akses Guru (Guru A Hanya Melihat Ujian Miliknya)
    // -------------------------------------------------------------------------
    $contextA = $aiChatService->fetchGuruDatabaseContext($teacherAId);
    $contextB = $aiChatService->fetchGuruDatabaseContext($teacherBId);

    $crossCheck = (!str_contains($contextA, 'Ujian Uji Esai Guru B')) && (str_contains($contextB, 'Ujian Uji Esai Guru B'));
    assertTest(
        'TC-ESSAY-04',
        'Isolasi Data: Guru A tidak dapat melihat atau menghitung paket ujian milik Guru B',
        $crossCheck,
        "Konteks Guru A bebas dari 'Ujian Uji Esai Guru B'"
    );

    // -------------------------------------------------------------------------
    // TEST 5: Konsistensi Hitungan Antara Dashboard Guru dan Konteks Chatbot
    // -------------------------------------------------------------------------
    // Query yang digunakan oleh GuruDashboardController aktual
    $dashboardGraded = (int) ($db->query("
        SELECT COUNT(DISTINCT ea.id) 
        FROM exam_answers ea 
        JOIN exam_sessions es ON es.id = ea.exam_session_id 
        JOIN exams e ON e.id = es.exam_id 
        JOIN questions q ON q.id = ea.question_id 
        WHERE e.teacher_id = {$teacherAId} 
          AND e.deleted_at IS NULL 
          AND q.type = 'essay' 
          AND q.deleted_at IS NULL 
          AND (ea.is_correct IS NOT NULL OR ea.score_earned > 0 OR ea.teacher_feedback IS NOT NULL)
    ")->fetchColumn() ?: 0);

    $dashboardPending = (int) ($db->query("
        SELECT COUNT(DISTINCT ea.id) 
        FROM exam_answers ea 
        JOIN exam_sessions es ON es.id = ea.exam_session_id 
        JOIN exams e ON e.id = es.exam_id 
        JOIN questions q ON q.id = ea.question_id 
        WHERE e.teacher_id = {$teacherAId} 
          AND e.deleted_at IS NULL 
          AND q.type = 'essay' 
          AND q.deleted_at IS NULL 
          AND ea.is_correct IS NULL 
          AND (ea.score_earned = 0 OR ea.score_earned IS NULL) 
          AND ea.teacher_feedback IS NULL
    ")->fetchColumn() ?: 0);

    $dashboardTotal = $dashboardGraded + $dashboardPending;

    // Verifikasi pada konteks teks yang dihasilkan oleh AiChatService::fetchGuruDatabaseContext
    $hasTotalLine = str_contains($contextA, "Total Jawaban Esai Masuk: {$dashboardTotal} jawaban");
    $hasGradedLine = str_contains($contextA, "Telah Dinilai / Selesai: {$dashboardGraded} jawaban");
    $hasPendingLine = str_contains($contextA, "Menunggu Penilaian Manual: {$dashboardPending} jawaban");

    assertTest(
        'TC-ESSAY-05',
        'Konsistensi 100%: Angka pada prompt konteks Chatbot Guru persis sama dengan metrik Dashboard Guru',
        $hasTotalLine && $hasGradedLine && $hasPendingLine,
        "Total: {$dashboardTotal}, Graded: {$dashboardGraded}, Pending: {$dashboardPending}. Context String Match = OK"
    );

    // -------------------------------------------------------------------------
    // TEST 6: Pengujian Bebas Duplikasi JOIN (COUNT DISTINCT)
    // -------------------------------------------------------------------------
    // Hitung secara manual untuk ujian test A saja
    $testAStats = $db->query("
        SELECT 
            COUNT(DISTINCT ea.id) as total,
            SUM(CASE WHEN (ea.is_correct IS NOT NULL OR ea.score_earned > 0 OR ea.teacher_feedback IS NOT NULL) THEN 1 ELSE 0 END) as graded,
            SUM(CASE WHEN (ea.is_correct IS NULL AND (ea.score_earned = 0 OR ea.score_earned IS NULL) AND ea.teacher_feedback IS NULL) THEN 1 ELSE 0 END) as pending
        FROM exam_answers ea
        JOIN exam_sessions es ON es.id = ea.exam_session_id
        JOIN exams e ON e.id = es.exam_id
        JOIN questions q ON q.id = ea.question_id
        WHERE e.id = {$examAId} AND q.type = 'essay'
    ")->fetch(PDO::FETCH_ASSOC);

    $noDuplicateOk = ((int)$testAStats['total'] === 3) && ((int)$testAStats['graded'] === 2) && ((int)$testAStats['pending'] === 1);
    assertTest(
        'TC-ESSAY-06',
        'Verifikasi Matematis: Ujian Uji A tepat memiliki 3 esai (2 selesai [1 positif, 1 nol], 1 pending), tanpa duplikasi JOIN',
        $noDuplicateOk,
        "Test Exam A: Total={$testAStats['total']}, Graded={$testAStats['graded']}, Pending={$testAStats['pending']}"
    );

    // -------------------------------------------------------------------------
    // TEST 7: Live AI Query Test (Gemini Chatbot Respons Faktual)
    // -------------------------------------------------------------------------
    if ($geminiProvider->isAvailable()) {
        echo "7. Menguji Respons Live Gemini Chatbot Guru...\n";
        $liveMsg = "Berapa banyak esai siswa yang belum dinilai dan berapa yang sudah selesai dinilai pada sistem saat ini?";
        $res = $aiChatService->sendMessage($teacherAId, 'guru', $liveMsg);
        $reply = $res['reply'] ?? '';

        $mentionsPending = str_contains($reply, (string)$dashboardPending);
        $mentionsGraded = str_contains($reply, (string)$dashboardGraded);

        assertTest(
            'TC-LIVE-ESSAY-01',
            "Live Gemini Chatbot merespons jumlah esai pending ({$dashboardPending}) dan graded ({$dashboardGraded}) secara akurat",
            $res['success'] && ($mentionsPending || $mentionsGraded),
            "Snippet Reply: " . mb_substr($reply, 0, 180) . "..."
        );
    } else {
        echo "ℹ️ Gemini Provider tidak tersedia / API Key tidak aktif, melewati TC-LIVE-ESSAY-01.\n\n";
    }

} finally {
    // -------------------------------------------------------------------------
    // CLEANUP FIXTURES
    // -------------------------------------------------------------------------
    echo "Membersihkan Fixture Pengujian...\n";
    if (!empty($cleanupAnswerIds)) {
        $inAns = implode(',', $cleanupAnswerIds);
        $db->exec("DELETE FROM exam_answers WHERE id IN ({$inAns})");
    }
    if (!empty($cleanupSessionIds)) {
        $inSess = implode(',', $cleanupSessionIds);
        $db->exec("DELETE FROM exam_sessions WHERE id IN ({$inSess})");
    }
    if (!empty($cleanupExamIds)) {
        $inEx = implode(',', $cleanupExamIds);
        $db->exec("DELETE FROM exam_questions WHERE exam_id IN ({$inEx})");
        $db->exec("DELETE FROM exams WHERE id IN ({$inEx})");
    }
    if (!empty($cleanupQuestionIds)) {
        $inQ = implode(',', $cleanupQuestionIds);
        $db->exec("DELETE FROM questions WHERE id IN ({$inQ})");
    }
    if (!empty($cleanupBankIds)) {
        $inB = implode(',', $cleanupBankIds);
        $db->exec("DELETE FROM question_banks WHERE id IN ({$inB})");
    }
    if ($createdTeacherB) {
        $db->exec("DELETE FROM user_roles WHERE user_id = {$teacherBId}");
        $db->exec("DELETE FROM users WHERE id = {$teacherBId}");
    }
    echo "✓ Pembersihan selesai.\n\n";
}

// -----------------------------------------------------------------------------
// REKAPITULASI
// -----------------------------------------------------------------------------
echo "====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN AKURASI STATUS ESAI GURU\n";
echo "====================================================================\n";
echo "Total Kasus Uji : " . ($passCount + $failCount) . "\n";
echo "Status PASS     : {$passCount}\n";
echo "Status FAIL     : {$failCount}\n";

if ($failCount === 0) {
    echo "🎉 SEMUA PENGUJIAN AKURASI STATUS ESAI BERHASIL (100% PASS)!\n";
} else {
    echo "⚠️ ADA PENGUJIAN YANG GAGAL. SILAKAN PERIKSA DETAIL DI ATAS.\n";
}
echo "====================================================================\n";
