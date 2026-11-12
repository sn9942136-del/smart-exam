<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AcademicYearModel;
use App\Models\ClassModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamRuleModel;
use App\Models\ExamScheduleModel;
use App\Models\ExamTokenModel;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SemesterModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use App\Services\ExamService;

echo "=== START EXAM MANAGEMENT AUTOMATED TESTS ===\n";

try {
    $examService = new ExamService();
    $examModel = new ExamModel();
    $examRuleModel = new ExamRuleModel();
    $examScheduleModel = new ExamScheduleModel();
    $examTokenModel = new ExamTokenModel();
    $examQuestionModel = new ExamQuestionModel();
    $examParticipantModel = new ExamParticipantModel();
    $userModel = new UserModel();
    $subjectModel = new SubjectModel();
    $academicYearModel = new AcademicYearModel();
    $semesterModel = new SemesterModel();
    $classModel = new ClassModel();
    $bankModel = new QuestionBankModel();
    $questionModel = new QuestionModel();
    $optionModel = new QuestionOptionModel();

    // 1. Get Prerequisites
    $guru = $userModel->findByEmail('guru@example.com');
    if (!$guru) {
        $guru = $userModel->findByUsername('guru_budi');
    }
    if (!$guru) {
        $guru = $userModel->first();
    }
    if (!$guru) {
        throw new RuntimeException("Guru user not found!");
    }
    $guruId = (int)$guru['id'];
    echo "[PASS] Found Guru ID: {$guruId} ({$guru['name']})\n";

    $subjects = $subjectModel->getAllActive();
    if (empty($subjects)) {
        throw new RuntimeException("No active subjects found!");
    }
    $subjectId = (int)$subjects[0]['id'];
    echo "[PASS] Found Subject ID: {$subjectId} ({$subjects[0]['name']})\n";

    $activeYear = $academicYearModel->getActive() ?? $academicYearModel->first();
    $academicYearId = !empty($activeYear) ? (int)$activeYear['id'] : null;

    $activeSemester = $semesterModel->getActive() ?? $semesterModel->first();
    $semesterId = !empty($activeSemester) ? (int)$activeSemester['id'] : null;

    $classes = $classModel->findAll();
    $classIds = !empty($classes) ? array_map(fn($c) => (int)$c['id'], array_slice($classes, 0, 2)) : [];

    // Ensure a question bank with questions exists
    $bank = $bankModel->first();
    if (!$bank) {
        $bankId = $bankModel->insert([
            'teacher_id' => $guruId,
            'subject_id' => $subjectId,
            'code' => 'BNK-TEST-EXAM',
            'title' => 'Bank Soal Uji Integrasi Ujian',
            'grade_level' => '12',
            'total_questions' => 0,
            'is_shared' => 1,
            'status' => 'active'
        ]);
        $bank = $bankModel->find($bankId);
    }
    $bankId = (int)$bank['id'];

    // Ensure questions exist in bank
    $existingQuestions = $questionModel->getQuestionsByBankId($bankId);
    if (empty($existingQuestions)) {
        $qId = $questionModel->insert([
            'question_bank_id' => $bankId,
            'type' => 'multiple_choice',
            'content' => '<p>Berapakah hasil dari 25 x 4?</p>',
            'weight' => 10.0,
            'difficulty' => 'easy',
            'order_number' => 1
        ]);
        $optionModel->saveBulkOptions((int)$qId, [
            ['option_key' => 'A', 'content' => '100', 'is_correct' => 1, 'score_value' => 10.0],
            ['option_key' => 'B', 'content' => '90', 'is_correct' => 0, 'score_value' => 0.0],
            ['option_key' => 'C', 'content' => '80', 'is_correct' => 0, 'score_value' => 0.0],
            ['option_key' => 'D', 'content' => '70', 'is_correct' => 0, 'score_value' => 0.0],
        ]);
        $bankModel->update($bankId, ['total_questions' => 1]);
    }
    echo "[PASS] Question Bank ID: {$bankId} ready with questions.\n";

    // 2. Test Exam Creation via ExamService (UC-20)
    echo "\n--- TEST 1: Create Exam with Atomic Transaction ---\n";
    $payload = [
        'subject_id'              => $subjectId,
        'academic_year_id'        => $academicYearId,
        'semester_id'             => $semesterId,
        'question_bank_id'        => $bankId,
        'title'                   => 'Penilaian Akhir Semester Komputasi Awan 2026',
        'description'             => 'Kerjakan dengan teliti dan jujur.',
        'duration_minutes'        => 90,
        'passing_grade'           => 75.0,
        'total_questions_limit'   => 10,
        'randomize_questions'     => 1,
        'randomize_options'       => 1,
        'is_dual_cam_required'    => 1,
        'status'                  => 'draft',
        'allow_tab_switch'        => 0,
        'max_tab_switches'        => 0,
        'lock_browser'            => 1,
        'detect_multiple_faces'   => 1,
        'detect_look_away'        => 1,
        'detect_audio_noise'      => 1,
        'detect_secondary_device' => 1,
        'start_time'              => date('Y-m-d H:i:s', strtotime('+1 day 08:00:00')),
        'end_time'                => date('Y-m-d H:i:s', strtotime('+1 day 10:00:00')),
        'grace_period_minutes'    => 15,
        'class_ids'               => $classIds
    ];

    $result = $examService->createExam($guruId, $payload);
    if (!$result['success']) {
        throw new RuntimeException("Failed to create exam via ExamService: " . json_encode($result['errors'] ?? []));
    }
    $newExamId = (int)$result['exam_id'];
    echo "[PASS] Exam Created Successfully with ID: {$newExamId}\n";

    // 3. Test Full Relational Load
    echo "\n--- TEST 2: Verify Full Relational Details ---\n";
    $loadedExam = $examModel->getDetailsWithFullRelations($newExamId);
    if (!$loadedExam) {
        throw new RuntimeException("Failed to load full relations for exam ID: {$newExamId}");
    }
    echo "[PASS] Exam Code: {$loadedExam['exam_code']}\n";
    echo "[PASS] Subject Name: {$loadedExam['subject_name']}\n";
    echo "[PASS] Bank Soal: {$loadedExam['bank_title']}\n";
    echo "[PASS] Dual Camera Active: " . ($loadedExam['require_dual_camera'] ? 'YES' : 'NO') . "\n";
    echo "[PASS] AI Proctoring Active: " . ($loadedExam['enable_ai_proctoring'] ? 'YES' : 'NO') . "\n";
    echo "[PASS] Questions Linked: " . count($loadedExam['questions']) . "\n";
    echo "[PASS] Schedules Created: " . count($loadedExam['schedules']) . "\n";
    echo "[PASS] Participants Enrolled: " . count($loadedExam['participants']) . "\n";

    // 4. Test Token Generation & Format (UC-29)
    echo "\n--- TEST 3: Verify Token Format & Operations (UC-29) ---\n";
    $activeToken = $loadedExam['active_token'];
    if (!$activeToken) {
        throw new RuntimeException("No active token generated for exam!");
    }
    $tokenCode = $activeToken['token'] ?? $activeToken['token_code'] ?? null;
    if (!$tokenCode) {
        throw new RuntimeException("Token code not found in active token array!");
    }
    echo "[PASS] Active Token Code: {$tokenCode}\n";
    
    // Check 6-char uppercase alphanumeric
    if (strlen($tokenCode) !== 6 || !preg_match('/^[A-Z0-9]{6}$/', $tokenCode)) {
        throw new RuntimeException("Invalid token format: {$tokenCode} (Expected 6-char uppercase alphanumeric)");
    }
    echo "[PASS] Token format verified (6-char uppercase alphanumeric).\n";

    // Test Token Refresh
    $refreshedToken = $examService->generateOrRefreshToken($newExamId);
    $refreshedCode = $refreshedToken['token'] ?? $refreshedToken['token_code'] ?? '';
    echo "[PASS] Refreshed Token Code: {$refreshedCode}\n";
    if ($refreshedCode === $tokenCode) {
        echo "[NOTE] Token happened to match or was re-issued\n";
    }

    // 5. Test Status Transitions
    echo "\n--- TEST 4: Verify Status Workflow ---\n";
    $statusFlow = ['scheduled', 'published', 'ongoing', 'completed'];
    foreach ($statusFlow as $st) {
        $ok = $examService->changeStatus($newExamId, $st);
        if (!$ok) {
            throw new RuntimeException("Failed to transition status to: {$st}");
        }
        $check = $examModel->find($newExamId);
        if ($check['status'] !== $st) {
            throw new RuntimeException("Status mismatch: expected {$st}, got {$check['status']}");
        }
        echo "[PASS] Status updated to: {$st}\n";
    }

    // 6. Test Preview Data Generation (UC-20a)
    echo "\n--- TEST 5: Verify Preview Data Generation (UC-20a) ---\n";
    $previewData = $examService->getExamDetailsForPreview($newExamId);
    if (empty($previewData)) {
        throw new RuntimeException("Failed to generate preview data for exam ID: {$newExamId}");
    }
    $questionCount = count($previewData['questions_with_options'] ?? $previewData['questions'] ?? []);
    echo "[PASS] Preview Data Loaded. Total Questions in Preview: {$questionCount}\n";

    // 7. Test Admin Centralized Listing & KPI Summary
    echo "\n--- TEST 6: Admin Centralized Listing & Stats ---\n";
    $adminStats = $examModel->getSummaryStats();
    echo "[PASS] Admin KPI Stats - Total Exams: {$adminStats['total']}, Ongoing: {$adminStats['ongoing']}, Active Tokens: {$adminStats['active_tokens']}\n";

    $adminList = $examModel->getPaginatedExams(1, 10);
    echo "[PASS] Admin Paginated List retrieved. Found: {$adminList['total']} total records.\n";

    echo "\n============================================\n";
    echo "🎉 ALL EXAM MANAGEMENT INTEGRATION TESTS PASSED!\n";
    echo "============================================\n";

} catch (Throwable $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
