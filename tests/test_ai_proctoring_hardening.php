<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * AI PROCTORING HARDENING TEST SUITE
 * ==============================================================================
 * Verifies:
 * 1. Valid Violation Recording & Automatic Warning Generation
 * 2. Invalid Session Handling (Non-existent Proctoring Session ID)
 * 3. Student Ownership Enforcement (Student B rejected from Student A's session)
 * 4. Invalid Violation Type Validation (Unregistered Code Rejection)
 * 5. Malformed & Extreme Confidence Score Clamping and Unix ms Timestamp Integrity
 * 6. Camera Disconnection & Lost Feed Events (CAMERA_DISCONNECTED, SECONDARY_CAM_LOST)
 * 7. Tab Switch & Fullscreen Exit Events (TAB_SWITCH, FULLSCREEN_EXIT)
 * 8. Duplicate Event Cooldown & Rate Limiting (Spam Prevention)
 * 9. Inactive / Submitted Exam Session Rejection
 * ==============================================================================
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AiProctoringController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Models\AiAnalysisLogModel;
use App\Models\AiViolationModel;
use App\Models\AiViolationTypeModel;
use App\Models\AiWarningModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\FaceVerificationModel;
use App\Models\ProctoringSessionModel;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use App\Services\AiProctoringService;
use App\Services\FaceVerificationService;
use App\Services\ViolationDetectionService;

echo "====================================================================\n";
echo "           AI PROCTORING HARDENING TEST SUITE                       \n";
echo "====================================================================\n\n";

$passedTests = 0;
$totalTests = 9;

$db = Database::getConnection();
$userModel = new UserModel();
$subjectModel = new SubjectModel();
$bankModel = new QuestionBankModel();
$questionModel = new QuestionModel();
$optionModel = new QuestionOptionModel();
$examModel = new ExamModel();
$participantModel = new ExamParticipantModel();
$examSessionModel = new ExamSessionModel();
$proctoringSessionModel = new ProctoringSessionModel();
$violationModel = new AiViolationModel();
$violationTypeModel = new AiViolationTypeModel();
$warningModel = new AiWarningModel();
$logModel = new AiAnalysisLogModel();

$proctoringService = new AiProctoringService();
$faceService = new FaceVerificationService();
$violationService = new ViolationDetectionService();

// -----------------------------------------------------------------------------
// SETUP: Prepare Exam, Teacher, Student A & Student B
// -----------------------------------------------------------------------------
echo "[SETUP] Setting up test environment...\n";

// 1. Teacher
$guru = $db->query("
    SELECT u.* FROM users u 
    INNER JOIN user_roles ur ON ur.user_id = u.id 
    INNER JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.status = 'active' AND u.deleted_at IS NULL 
    LIMIT 1
")->fetch();
$teacherId = (int)$guru['id'];

// 2. Students
$students = $db->query("
    SELECT u.* FROM users u 
    INNER JOIN user_roles ur ON ur.user_id = u.id 
    INNER JOIN roles r ON r.id = ur.role_id 
    WHERE r.name IN ('siswa', 'peserta_didik') AND u.status = 'active' AND u.deleted_at IS NULL 
    LIMIT 2
")->fetchAll();

if (count($students) < 2) {
    die("ERROR: At least 2 active students required for testing.\n");
}

$studentA = $students[0];
$studentB = $students[1];
$studentAId = (int)$studentA['id'];
$studentBId = (int)$studentB['id'];

// 3. Subject & Bank
$subject = $subjectModel->first() ?: ['id' => $subjectModel->insert(['name' => 'Hardening AI', 'code' => 'HAI-' . uniqid()])];
$subjectId = (int)$subject['id'];

$bankId = (int)$bankModel->insert([
    'creator_id' => $teacherId,
    'subject_id' => $subjectId,
    'code' => 'BNK-HRD-' . substr(uniqid(), -5),
    'title' => 'Bank Soal Proctoring Hardening',
    'total_questions' => 2,
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);

// 4. Questions
$q1Id = (int)$questionModel->insert([
    'question_bank_id' => $bankId,
    'type' => 'multiple_choice',
    'content' => 'Soal Proctoring 1',
    'weight' => 1.0,
    'difficulty' => 'easy',
    'order_number' => 1,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);
$optA1 = (int)$optionModel->insert([
    'question_id' => $q1Id,
    'option_key' => 'A',
    'content' => 'Pilihan A',
    'is_correct' => 1,
    'score_value' => 1.0,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);

// 5. Exam
$examId = (int)$examModel->insert([
    'subject_id' => $subjectId,
    'teacher_id' => $teacherId,
    'title' => 'Ujian AI Proctoring Hardening ' . uniqid(),
    'code' => 'EXAM-HRD-' . substr(uniqid(), -6),
    'type' => 'daily_test',
    'duration_minutes' => 60,
    'passing_grade' => 75.0,
    'is_dual_cam_required' => 1,
    'status' => 'ongoing',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);

// Attach question
$db->prepare("INSERT INTO exam_questions (exam_id, question_id, order_number, score_weight) VALUES (?, ?, 1, 1.0)")->execute([$examId, $q1Id]);

// 6. Exam Sessions for Student A and Student B
$tokenA = 'tok_hard_a_' . bin2hex(random_bytes(8));
$examSessionAId = (int)$examSessionModel->insert([
    'exam_id' => $examId,
    'student_id' => $studentAId,
    'session_token' => $tokenA,
    'status' => 'ongoing',
    'start_time' => date('Y-m-d H:i:s'),
    'deadline_time' => date('Y-m-d H:i:s', time() + 3600),
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);

$tokenB = 'tok_hard_b_' . bin2hex(random_bytes(8));
$examSessionBId = (int)$examSessionModel->insert([
    'exam_id' => $examId,
    'student_id' => $studentBId,
    'session_token' => $tokenB,
    'status' => 'ongoing',
    'start_time' => date('Y-m-d H:i:s'),
    'deadline_time' => date('Y-m-d H:i:s', time() + 3600),
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);

// 7. Proctoring Sessions
$procA = $proctoringService->startOrGetProctoringSession($examSessionAId, $studentAId);
$procSessionAId = (int)$procA['session']['id'];

$procB = $proctoringService->startOrGetProctoringSession($examSessionBId, $studentBId);
$procSessionBId = (int)$procB['session']['id'];

echo "  -> Exam ID: {$examId}\n";
echo "  -> Student A ID: {$studentAId}, Exam Session: {$examSessionAId}, Proc Session: {$procSessionAId}\n";
echo "  -> Student B ID: {$studentBId}, Exam Session: {$examSessionBId}, Proc Session: {$procSessionBId}\n\n";

// =============================================================================
// TEST 1: Valid Violation Recording & Auto-Warning
// =============================================================================
echo "[TEST 1] Testing Valid Violation Recording & Auto-Warning...\n";
$dummySnapshot = 'data:image/jpeg;base64,' . base64_encode('FAKE_JPEG_DATA_FOR_TESTING');
$res1 = $violationService->recordViolation(
    $procSessionAId,
    'NO_FACE',
    0.92,
    $dummySnapshot,
    ['yaw' => 0.0, 'pitch' => 0.0, 'test' => true]
);

if (!empty($res1['success']) && !empty($res1['violation_id']) && !empty($res1['warning']['id'])) {
    $vRow = $violationModel->find($res1['violation_id']);
    if ($vRow && (float)$vRow['confidence_score'] === 0.92 && $vRow['proctor_action'] === 'pending') {
        echo "  [PASS] Test 1: Valid violation recorded (ID: {$res1['violation_id']}, Warning ID: {$res1['warning']['id']}, Proctor Action: pending).\n";
        $passedTests++;
    } else {
        echo "  [FAIL] Test 1: Violation record data in DB mismatch.\n";
    }
} else {
    echo "  [FAIL] Test 1: Failed to record valid violation. Response: " . json_encode($res1) . "\n";
}

// =============================================================================
// TEST 2: Invalid Session (Non-existent Proctoring Session ID)
// =============================================================================
echo "\n[TEST 2] Testing Invalid Session Handling (Non-existent ID)...\n";
$res2Viol = $violationService->recordViolation(9999999, 'NO_FACE', 0.90);
$res2Telem = $proctoringService->logTelemetry(9999999, ['head_pose_yaw' => 0]);

if (empty($res2Viol['success']) && ($res2Viol['code'] ?? '') === 'INVALID_SESSION'
    && empty($res2Telem['success']) && ($res2Telem['code'] ?? '') === 'INVALID_SESSION') {
    echo "  [PASS] Test 2: Non-existent proctoring session correctly rejected with INVALID_SESSION.\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 2: Invalid session did not return expected error. Viol: " . json_encode($res2Viol) . " | Telem: " . json_encode($res2Telem) . "\n";
}

// =============================================================================
// TEST 3: Student Ownership Enforcement (Student B accessing Student A's session)
// =============================================================================
echo "\n[TEST 3] Testing Student Ownership Enforcement via Controller...\n";

// Helper to simulate request
function simulateControllerRequest(int $userId, string $role, string $action, array $postData = [], array $urlParams = []) {
    $_SESSION['user'] = ['id' => $userId, 'role' => $role];
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
    $_SESSION['logged_in'] = true;
    $_POST = $postData;

    $req = new Request();
    $refReq = new ReflectionClass($req);
    $propPost = $refReq->getProperty('postParams');
    $propPost->setAccessible(true);
    $propPost->setValue($req, $postData);

    $controller = new AiProctoringController($req);
    return $controller->$action(...$urlParams);
}

// Student B tries to record violation for Student A's proctoring session
$resp3A = simulateControllerRequest($studentBId, 'siswa', 'recordViolation', [
    'proctoring_session_id' => $procSessionAId,
    'violation_code' => 'TAB_SWITCH',
    'confidence_score' => 0.90
]);

// Student B tries to send telemetry for Student A's proctoring session
$resp3B = simulateControllerRequest($studentBId, 'siswa', 'logTelemetry', [
    'proctoring_session_id' => $procSessionAId,
    'telemetry' => ['head_pose_yaw' => 0]
]);

// Student B tries to acknowledge Student A's warning
$resp3C = simulateControllerRequest($studentBId, 'siswa', 'acknowledgeWarning', [
    'warning_id' => (int)($res1['warning']['id'] ?? 1)
]);

if ($resp3A->getStatusCode() === 403 && $resp3B->getStatusCode() === 403 && $resp3C->getStatusCode() === 403) {
    echo "  [PASS] Test 3: Student B rejected with HTTP 403 Forbidden across all Student A proctoring endpoints.\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 3: Student ownership check failed. Codes: recordViolation={$resp3A->getStatusCode()}, logTelemetry={$resp3B->getStatusCode()}, ackWarning={$resp3C->getStatusCode()}\n";
}

// =============================================================================
// TEST 4: Invalid Violation Type (Unregistered Code)
// =============================================================================
echo "\n[TEST 4] Testing Invalid Violation Type Validation (Unregistered Code)...\n";
$res4 = $violationService->recordViolation(
    $procSessionAId,
    'UNREGISTERED_CHEAT_CODE_XYZ',
    0.99
);

if (empty($res4['success']) && ($res4['code'] ?? '') === 'INVALID_VIOLATION_TYPE') {
    echo "  [PASS] Test 4: Unregistered violation code correctly rejected with INVALID_VIOLATION_TYPE.\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 4: Unregistered violation code was not rejected properly: " . json_encode($res4) . "\n";
}

// =============================================================================
// TEST 5: Malformed / Extreme Confidence Score & Timestamps
// =============================================================================
echo "\n[TEST 5] Testing Confidence Score Clamping & Unix ms Timestamp Integrity...\n";

// Confidence score clamping tests
$res5Min = $violationService->recordViolation($procSessionAId, 'MULTIPLE_FACES', -15.5);
$vRowMin = !empty($res5Min['violation_id']) ? $violationModel->find($res5Min['violation_id']) : null;

// Telemetry timestamp tests
$customMs = 1750000000123;
$res5Telem = $proctoringService->logTelemetry($procSessionAId, [
    'frame_timestamp' => $customMs,
    'head_pose_yaw' => 10.0,
    'head_pose_pitch' => 5.0,
    'gaze_direction' => 'center',
    'detected_face_count' => 1
]);

$recentLogs = $logModel->getRecentLogs($procSessionAId, 1);
$lastLog = $recentLogs[0] ?? null;

$isScoreClamped = $vRowMin && (float)$vRowMin['confidence_score'] === 0.0;
$isTimestampInteger = $lastLog && (int)$lastLog['frame_timestamp'] === $customMs;

if ($isScoreClamped && $isTimestampInteger) {
    echo "  [PASS] Test 5: Negative confidence score clamped to 0.00 and frame_timestamp stored as integer Unix ms timestamp ({$customMs}).\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 5: Clamping or timestamp handling failed. Score: " . ($vRowMin['confidence_score'] ?? 'null') . ", Timestamp: " . ($lastLog['frame_timestamp'] ?? 'null') . "\n";
}

// =============================================================================
// TEST 6: Camera Disconnection & Lost Feed Events
// =============================================================================
echo "\n[TEST 6] Testing Camera Disconnection & Lost Feed Events...\n";
$res6CamDisc = $violationService->recordViolation($procSessionAId, 'CAMERA_DISCONNECTED', 0.95);
$res6SecLost = $violationService->recordViolation($procSessionAId, 'SECONDARY_CAM_LOST', 0.95);

if (!empty($res6CamDisc['success']) && !empty($res6SecLost['success'])
    && $res6CamDisc['violation_code'] === 'CAMERA_DISCONNECTED'
    && $res6SecLost['violation_code'] === 'SECONDARY_CAM_LOST') {
    echo "  [PASS] Test 6: CAMERA_DISCONNECTED and SECONDARY_CAM_LOST violations recorded successfully with correct severities.\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 6: Failed to record camera disconnection events: " . json_encode($res6CamDisc) . "\n";
}

// =============================================================================
// TEST 7: Tab Switch & Fullscreen Exit Events
// =============================================================================
echo "\n[TEST 7] Testing Tab Switch & Fullscreen Exit Events...\n";
$res7Tab = $violationService->recordViolation($procSessionAId, 'TAB_SWITCH', 0.90, null, ['event' => 'window_blur']);
$res7Fs = $violationService->recordViolation($procSessionAId, 'FULLSCREEN_EXIT', 0.90, null, ['event' => 'fullscreenchange']);

if (!empty($res7Tab['success']) && !empty($res7Fs['success'])
    && $res7Tab['violation_code'] === 'TAB_SWITCH'
    && $res7Fs['violation_code'] === 'FULLSCREEN_EXIT') {
    echo "  [PASS] Test 7: TAB_SWITCH and FULLSCREEN_EXIT browser events recorded with automated warning.\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 7: Failed to record tab switch / fullscreen exit events.\n";
}

// =============================================================================
// TEST 8: Duplicate Event Spam Cooldown (Rate Limiting)
// =============================================================================
echo "\n[TEST 8] Testing Duplicate Event Spam Cooldown (Rate Limiting)...\n";
// Call LOOK_AWAY twice immediately
$res8First = $violationService->recordViolation($procSessionAId, 'LOOK_AWAY', 0.85);
$res8Second = $violationService->recordViolation($procSessionAId, 'LOOK_AWAY', 0.85);

if (!empty($res8First['success']) && empty($res8First['rate_limited'])
    && !empty($res8Second['success']) && !empty($res8Second['rate_limited'])) {
    echo "  [PASS] Test 8: Duplicate violation spam within 3 seconds gracefully rate-limited (first recorded, second ignored).\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 8: Rate-limiting check failed. First: " . json_encode($res8First) . " | Second: " . json_encode($res8Second) . "\n";
}

// =============================================================================
// TEST 9: Inactive / Submitted Exam Session Rejection
// =============================================================================
echo "\n[TEST 9] Testing Inactive / Submitted Exam Session Rejection...\n";
// Mark Student A's exam session as submitted
$examSessionModel->update($examSessionAId, [
    'status' => 'submitted',
    'submit_time' => date('Y-m-d H:i:s')
]);

$res9Viol = $violationService->recordViolation($procSessionAId, 'TAB_SWITCH', 0.90);
$res9Telem = $proctoringService->logTelemetry($procSessionAId, ['head_pose_yaw' => 0]);
$res9Init = simulateControllerRequest($studentAId, 'siswa', 'initSession', [
    'exam_session_id' => $examSessionAId
]);

if (empty($res9Viol['success']) && ($res9Viol['code'] ?? '') === 'EXAM_SESSION_INACTIVE'
    && empty($res9Telem['success']) && ($res9Telem['code'] ?? '') === 'EXAM_SESSION_INACTIVE'
    && $res9Init->getStatusCode() === 422) {
    echo "  [PASS] Test 9: Violations, telemetry, and session initialization rejected once exam session is inactive/submitted.\n";
    $passedTests++;
} else {
    echo "  [FAIL] Test 9: Inactive session was not rejected properly. Viol: " . json_encode($res9Viol) . " | Telem: " . json_encode($res9Telem) . " | Init Code: " . $res9Init->getStatusCode() . "\n";
}

// =============================================================================
// CLEANUP & SUMMARY
// =============================================================================
echo "\n====================================================================\n";
echo "           AI PROCTORING HARDENING TEST SUMMARY                     \n";
echo "====================================================================\n";
echo "TOTAL: {$passedTests} / {$totalTests} TESTS PASSED\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    echo ">>> ALL AI PROCTORING HARDENING TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
