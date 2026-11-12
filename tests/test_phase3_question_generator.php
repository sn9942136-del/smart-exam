<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * PHASE 3 — AI QUESTION GENERATOR END-TO-END TEST SUITE
 * ==============================================================================
 * Test Parameters (Mandatory):
 * - Mata Pelajaran: Matematika
 * - Kelas: 8
 * - Topik: SPLDV (Sistem Persamaan Linear Dua Variabel)
 * - Bentuk: Pilihan Ganda (multiple_choice)
 * - Jumlah: 3
 * - Kesulitan: Sedang (medium)
 * - Bahasa: Indonesia (id)
 * ==============================================================================
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Libraries\Database;
use App\Models\AiQuestionGenerationModel;
use App\Models\AuditLogModel;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use App\Services\AI\AiQuestionService;
use App\Services\AI\GeminiProvider;

echo "====================================================================\n";
echo "      PHASE 3 — AI QUESTION GENERATOR END-TO-END TEST SUITE        \n";
echo "====================================================================\n\n";

$passedTests = 0;
$totalTests = 6;
$results = [];

$db = Database::getConnection();

// -----------------------------------------------------------------------------
// STEP 0: Find or Create Test Environment (Teacher & Question Bank)
// -----------------------------------------------------------------------------
echo "[SETUP] Preparing Teacher and Question Bank for Matematika...\n";

$userModel = new UserModel();
$subjectModel = new SubjectModel();
$bankModel = new QuestionBankModel();
$generationModel = new AiQuestionGenerationModel();
$questionModel = new QuestionModel();
$optionModel = new QuestionOptionModel();

// 1. Get or Create Guru
$guru = $db->query("
    SELECT u.*, r.name as role 
    FROM users u 
    INNER JOIN user_roles ur ON ur.user_id = u.id 
    INNER JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.status = 'active' AND u.deleted_at IS NULL 
    LIMIT 1
")->fetch();

if (!$guru) {
    $guruId = (int)$userModel->insert([
        'name'       => 'Guru Matematika Test',
        'email'      => 'guru.matematika.test@sekolah.sch.id',
        'username'   => 'guru_matematika_test',
        'password'   => password_hash('password123', PASSWORD_BCRYPT),
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Assign role 'guru'
    $roleRow = $db->query("SELECT id FROM roles WHERE name = 'guru' LIMIT 1")->fetch();
    if ($roleRow) {
        $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$guruId, $roleRow['id']]);
    }
    $guru = $userModel->find($guruId);
    $guru['role'] = 'guru';
}
$teacherId = (int)$guru['id'];
echo "  --> Teacher User: ID={$teacherId}, Name={$guru['name']}, Role={$guru['role']}\n";

// 2. Get or Create Subject Matematika
$subject = $db->query("SELECT * FROM subjects WHERE name LIKE '%Matematika%' LIMIT 1")->fetch();
if (!$subject) {
    $subjId = (int)$subjectModel->insert([
        'code'        => 'MAT-08',
        'name'        => 'Matematika',
        'description' => 'Mata Pelajaran Matematika SMP/MTs',
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s')
    ]);
    $subject = $subjectModel->find($subjId);
}
$subjectId = (int)$subject['id'];
echo "  --> Subject: ID={$subjectId}, Name={$subject['name']}\n";

// 3. Create or Get dedicated Question Bank for test
$bankCode = 'BNK-SPLDV-TEST-' . date('His');
$bankId = (int)$bankModel->insert([
    'subject_id'      => $subjectId,
    'creator_id'      => $teacherId,
    'code'            => $bankCode,
    'title'           => 'Bank Soal Matematika SPLDV Kelas 8 (AI Generated)',
    'description'     => 'Bank soal hasil uji coba otomatis Phase 3 AI Question Generator untuk topik SPLDV',
    'total_questions' => 0,
    'status'          => 'active',
    'created_at'      => date('Y-m-d H:i:s'),
    'updated_at'      => date('Y-m-d H:i:s')
]);
echo "  --> Test Question Bank: ID={$bankId}, Code={$bankCode}, Title='Bank Soal Matematika SPLDV Kelas 8 (AI Generated)'\n\n";

// -----------------------------------------------------------------------------
// TEST 1: Service Architecture & Provider Verification
// -----------------------------------------------------------------------------
echo "[TEST 1] Verifying AiQuestionService & Gemini Provider Architecture...\n";
$service = new AiQuestionService();
$provider = $service->resolveProvider();

if ($provider instanceof GeminiProvider && $provider->isAvailable()) {
    $passedTests++;
    $msg = "[PASS] Test 1: AiQuestionService resolved active GeminiProvider (Model: {$provider->getModel()})";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $msg = "[FAIL] Test 1: AiQuestionService failed to resolve GeminiProvider or GeminiProvider unavailable.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 2: AI Generation Request (Guru -> Gemini -> JSON -> Validation -> Draft)
// -----------------------------------------------------------------------------
echo "[TEST 2] Generating 3 Multiple Choice Questions (Matematika, Kelas 8, SPLDV, Sedang, ID)...\n";
$genParams = [
    'question_bank_id' => $bankId,
    'subject_name'     => 'Matematika',
    'grade_level'      => 'Kelas 8',
    'topic'            => 'SPLDV',
    'question_type'    => 'multiple_choice',
    'question_count'   => 3,
    'difficulty'       => 'medium',
    'language'         => 'id',
    'custom_instructions' => 'Pastikan memuat persamaan linear dua variabel dengan metode eliminasi, substitusi, atau grafik kontekstual.'
];

$startTime = microtime(true);
$genResult = $service->generateQuestions($genParams, $teacherId);
$duration = round(microtime(true) - $startTime, 2);

if ($genResult['success'] && !empty($genResult['generation_id']) && count($genResult['questions'] ?? []) === 3) {
    $generationId = (int)$genResult['generation_id'];
    $passedTests++;
    $msg = "[PASS] Test 2: AI Generation succeeded in {$duration}s. Batch ID: {$generationId}. Generated: 3 questions.";
    $results[] = $msg;
    echo "  --> {$msg}\n";
    foreach ($genResult['questions'] as $qIdx => $q) {
        $num = $qIdx + 1;
        $optCount = count($q['options'] ?? []);
        echo "      Soal #{$num}: " . mb_substr($q['content'], 0, 70) . "... ({$optCount} opsi)\n";
    }
    echo "\n";
} else {
    $errMsg = $genResult['error'] ?? 'Unknown generation failure';
    $msg = "[FAIL] Test 2: AI Generation failed: {$errMsg}";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
    exit(1);
}

// -----------------------------------------------------------------------------
// TEST 3: Validation & Draft Integrity (ai_question_generations Table)
// -----------------------------------------------------------------------------
echo "[TEST 3] Verifying Draft record in database (status = 'pending', question_count = 3)...\n";
$draftRecord = $generationModel->find($generationId);

if (
    $draftRecord &&
    $draftRecord['status'] === 'pending' &&
    (int)$draftRecord['question_count'] === 3 &&
    (int)$draftRecord['user_id'] === $teacherId &&
    (int)$draftRecord['question_bank_id'] === $bankId &&
    $draftRecord['subject_topic'] === 'SPLDV' &&
    $draftRecord['grade_level'] === 'Kelas 8' &&
    $draftRecord['difficulty_level'] === 'medium'
) {
    $passedTests++;
    $msg = "[PASS] Test 3: Draft record verified in database with status='pending' and exact metadata.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $msg = "[FAIL] Test 3: Draft record mismatch or not found.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 4: Teacher Review Workbench (getDraftForReview)
// -----------------------------------------------------------------------------
echo "[TEST 4] Fetching Draft for Teacher Review (UC-21c Workbench)...\n";
$reviewDraft = $service->getDraftForReview($generationId, $teacherId);

$allQuestionsValid = false;
if ($reviewDraft && count($reviewDraft['questions'] ?? []) === 3) {
    $validCount = 0;
    foreach ($reviewDraft['questions'] as $q) {
        if (!empty($q['content']) && count($q['options'] ?? []) >= 4) {
            $hasCorrect = false;
            foreach ($q['options'] as $opt) {
                if (!empty($opt['is_correct'])) {
                    $hasCorrect = true;
                }
            }
            if ($hasCorrect) {
                $validCount++;
            }
        }
    }
    if ($validCount === 3) {
        $allQuestionsValid = true;
    }
}

if ($allQuestionsValid) {
    $passedTests++;
    $msg = "[PASS] Test 4: Review Workbench loaded 3 complete questions with verified options and correct answer keys.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $msg = "[FAIL] Test 4: Review Workbench questions validation failed.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 5: Teacher Approval & Import to Question Bank (Atomic Transaction)
// -----------------------------------------------------------------------------
echo "[TEST 5] Executing Teacher Approval & Import into Question Bank...\n";
$approvedQuestions = $reviewDraft['questions'];

$importResult = $service->importApprovedQuestions($generationId, $approvedQuestions, $teacherId);

if ($importResult['success'] && (int)$importResult['imported_count'] === 3 && (int)$importResult['bank_id'] === $bankId) {
    $passedTests++;
    $msg = "[PASS] Test 5: Teacher approval & import succeeded. 3 questions imported into Bank Soal #{$bankId}.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $errMsg = $importResult['error'] ?? 'Import failed';
    $msg = "[FAIL] Test 5: Import approved questions failed: {$errMsg}";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 6: Post-Import Integrity & Database Consistency Check
// -----------------------------------------------------------------------------
echo "[TEST 6] Verifying Final Database Records, Options, Bank Count & Status...\n";

// A. Check questions in database
$insertedQuestions = $questionModel->getQuestionsByBankId($bankId);
$countQuestions = count($insertedQuestions);

// B. Check total options (3 questions * 4 options = 12 options)
$totalOptions = 0;
foreach ($insertedQuestions as $iq) {
    $totalOptions += count($iq['options'] ?? []);
}

// C. Check Question Bank total_questions
$updatedBank = $bankModel->find($bankId);
$bankTotalQuestions = (int)($updatedBank['total_questions'] ?? 0);

// D. Check Generation Status updated to 'success' and accepted_count = 3
$finalGeneration = $generationModel->find($generationId);
$generationStatus = $finalGeneration['status'] ?? '';
$acceptedCount = (int)($finalGeneration['accepted_count'] ?? 0);

// E. Check Audit Log
$auditLog = $db->query("SELECT * FROM audit_logs WHERE action = 'AI_IMPORT_QUESTIONS' AND target_id = '{$bankId}' ORDER BY id DESC LIMIT 1")->fetch();

echo "  -> Bank Total Questions in DB: {$bankTotalQuestions} (Expected: 3)\n";
echo "  -> Questions fetched: {$countQuestions} (Expected: 3)\n";
echo "  -> Options fetched: {$totalOptions} (Expected: 12)\n";
echo "  -> Generation status: '{$generationStatus}', Accepted: {$acceptedCount}\n";
echo "  -> Audit log recorded: " . ($auditLog ? "YES (ID: {$auditLog['id']})" : "NO") . "\n";

if (
    $countQuestions === 3 &&
    $totalOptions >= 12 &&
    $bankTotalQuestions === 3 &&
    $generationStatus === 'success' &&
    $acceptedCount === 3 &&
    $auditLog !== false
) {
    $passedTests++;
    $msg = "[PASS] Test 6: Complete end-to-end database consistency verified: 3 questions, 12 options, status='success', audit log confirmed.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $msg = "[FAIL] Test 6: Post-import integrity check failed.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "====================================================================\n";
echo "                      PHASE 3 TEST SUMMARY                          \n";
echo "====================================================================\n";
foreach ($results as $res) {
    echo $res . "\n";
}
echo "--------------------------------------------------------------------\n";
echo "TOTAL: {$passedTests} / {$totalTests} TESTS PASSED\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    echo "\n>>> PHASE 3 — AI QUESTION GENERATOR IS FULLY VERIFIED AND COMPLETE! <<<\n\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED IN PHASE 3 <<<\n\n";
    exit(1);
}
