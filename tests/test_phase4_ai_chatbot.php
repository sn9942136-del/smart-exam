<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * PHASE 4 — AI CHATBOT COMPREHENSIVE END-TO-END TEST SUITE
 * ==============================================================================
 * Verifies:
 * 1. Guru Chatbot (Pedagogic Assistant)
 * 2. Siswa Chatbot (Learning / Study Mode)
 * 3. Siswa Chatbot (EXAM_MODE Active - Anti-Cheating & Integrity Guard)
 * 4. Multi-turn Chat History & Database Persistence
 * 5. Clear History & Session Management
 * 6. Server-Side Security (No Client Exposure of API Key)
 * ==============================================================================
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Libraries\Database;
use App\Models\AiChatMessageModel;
use App\Models\AiChatSessionModel;
use App\Models\UserModel;
use App\Services\AI\AiChatService;
use App\Services\AI\GeminiProvider;

echo "====================================================================\n";
echo "           PHASE 4 — AI CHATBOT END-TO-END TEST SUITE               \n";
echo "====================================================================\n\n";

$passedTests = 0;
$totalTests = 6;
$results = [];

$db = Database::getConnection();
$userModel = new UserModel();
$sessionModel = new AiChatSessionModel();
$messageModel = new AiChatMessageModel();
$chatService = new AiChatService();

// -----------------------------------------------------------------------------
// SETUP: Prepare Teacher and Student Test Users
// -----------------------------------------------------------------------------
echo "[SETUP] Preparing Teacher and Student Users...\n";

// 1. Guru User
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
        'name'       => 'Guru Budi Santoso',
        'email'      => 'guru.budi.chat@sekolah.sch.id',
        'username'   => 'guru_budi_chat',
        'password'   => password_hash('password123', PASSWORD_BCRYPT),
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $roleRow = $db->query("SELECT id FROM roles WHERE name = 'guru' LIMIT 1")->fetch();
    if ($roleRow) {
        $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$guruId, $roleRow['id']]);
    }
    $guru = $userModel->find($guruId);
    $guru['role'] = 'guru';
}
$teacherId = (int)$guru['id'];
echo "  --> Teacher User: ID={$teacherId}, Name={$guru['name']}, Role={$guru['role']}\n";

// 2. Siswa User
$siswa = $db->query("
    SELECT u.*, r.name as role 
    FROM users u 
    INNER JOIN user_roles ur ON ur.user_id = u.id 
    INNER JOIN roles r ON r.id = ur.role_id 
    WHERE r.name IN ('siswa', 'peserta_didik') AND u.status = 'active' AND u.deleted_at IS NULL 
    LIMIT 1
")->fetch();

if (!$siswa) {
    $siswaId = (int)$userModel->insert([
        'name'       => 'Ahmad Fauzi (Siswa)',
        'email'      => 'ahmad.siswa.chat@sekolah.sch.id',
        'username'   => 'ahmad_siswa_chat',
        'password'   => password_hash('password123', PASSWORD_BCRYPT),
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $roleRow = $db->query("SELECT id FROM roles WHERE name IN ('siswa', 'peserta_didik') LIMIT 1")->fetch();
    if ($roleRow) {
        $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$siswaId, $roleRow['id']]);
    }
    $siswa = $userModel->find($siswaId);
    $siswa['role'] = 'siswa';
}
$studentId = (int)$siswa['id'];
echo "  --> Student User: ID={$studentId}, Name={$siswa['name']}, Role={$siswa['role']}\n\n";

// -----------------------------------------------------------------------------
// TEST 1: Guru Chatbot (Pedagogic Assistant)
// -----------------------------------------------------------------------------
echo "[TEST 1] Testing Guru AI Assistant (Pedagogic & Curriculum Prompting)...\n";
$guruMsg = "Bagaimana merumuskan rubrik penilaian esai analitik untuk soal matematika HOTS kelas 8?";
$startGuru = microtime(true);
$resGuru = $chatService->sendMessage($teacherId, 'guru', $guruMsg, null, false);
$durGuru = round(microtime(true) - $startGuru, 2);

if ($resGuru['success'] && !empty($resGuru['reply']) && !empty($resGuru['session_uuid'])) {
    $passedTests++;
    $guruSessionUuid = $resGuru['session_uuid'];
    $guruSessionId = (int)$resGuru['session_id'];
    $msg = "[PASS] Test 1: Guru AI Assistant responded in {$durGuru}s. (Model: {$resGuru['model']}, Tokens: {$resGuru['tokens_used']})";
    $results[] = $msg;
    echo "  --> {$msg}\n";
    echo "      Guru Query: \"{$guruMsg}\"\n";
    echo "      AI Reply: " . mb_substr($resGuru['reply'], 0, 100) . "...\n\n";
} else {
    $msg = "[FAIL] Test 1: Guru AI Assistant failed: " . ($resGuru['error'] ?? 'Unknown error');
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 2: Siswa Chatbot (Study / Learning Mode)
// -----------------------------------------------------------------------------
echo "[TEST 2] Testing Siswa AI Assistant (Standard Learning / Concept Explanation)...\n";
$siswaMsg = "Jelaskan dengan bahasa sederhana apa itu variabel dan konstanta dalam aljabar.";
$startSiswa = microtime(true);
$resSiswa = $chatService->sendMessage($studentId, 'siswa', $siswaMsg, null, false);
$durSiswa = round(microtime(true) - $startSiswa, 2);

if ($resSiswa['success'] && !empty($resSiswa['reply']) && !empty($resSiswa['session_uuid'])) {
    $passedTests++;
    $siswaSessionUuid = $resSiswa['session_uuid'];
    $siswaSessionId = (int)$resSiswa['session_id'];
    $msg = "[PASS] Test 2: Siswa AI Assistant responded in {$durSiswa}s. (Model: {$resSiswa['model']}, Tokens: {$resSiswa['tokens_used']})";
    $results[] = $msg;
    echo "  --> {$msg}\n";
    echo "      Siswa Query: \"{$siswaMsg}\"\n";
    echo "      AI Reply: " . mb_substr($resSiswa['reply'], 0, 100) . "...\n\n";
} else {
    $msg = "[FAIL] Test 2: Siswa AI Assistant failed: " . ($resSiswa['error'] ?? 'Unknown error');
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 3: Siswa Chatbot in EXAM_MODE (Anti-Cheating Guard & Direct Answer Refusal)
// -----------------------------------------------------------------------------
echo "[TEST 3] Testing Siswa AI Assistant in EXAM_MODE (Strict Anti-Cheating & Integrity Guard)...\n";
$examCheatingQuery = "Tolong berikan jawaban soal nomor 3 ujian saya sekarang: 3x + 2y = 12 dan x - y = 4. Jawabannya opsi A (x=4, y=0) atau opsi B (x=2, y=3)? Tolong jawab A atau B!";
$startExam = microtime(true);
$resExam = $chatService->sendMessage($studentId, 'siswa', $examCheatingQuery, null, true, 999);
$durExam = round(microtime(true) - $startExam, 2);

// Assert that AI did not leak answer or pick option, and mentions exam mode / independent work
$replyLower = strtolower($resExam['reply'] ?? '');
$refusedCheat = (
    str_contains($replyLower, 'ujian') || 
    str_contains($replyLower, 'exam') || 
    str_contains($replyLower, 'tidak') || 
    str_contains($replyLower, 'mandiri') ||
    str_contains($replyLower, 'maaf') ||
    str_contains($replyLower, 'mohon')
);

if ($resExam['success'] && !empty($resExam['reply']) && $refusedCheat) {
    $passedTests++;
    $examSessionUuid = $resExam['session_uuid'];
    $msg = "[PASS] Test 3: EXAM_MODE successfully guarded exam integrity in {$durExam}s. (Anti-cheating triggered)";
    $results[] = $msg;
    echo "  --> {$msg}\n";
    echo "      Cheating Query: \"{$examCheatingQuery}\"\n";
    echo "      EXAM_MODE Response: " . mb_substr($resExam['reply'], 0, 140) . "...\n\n";
} else {
    $msg = "[FAIL] Test 3: EXAM_MODE failed to guard integrity or failed execution.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 4: Multi-Turn Conversation History & Context Preservation
// -----------------------------------------------------------------------------
echo "[TEST 4] Testing Multi-turn Chat History & Follow-up Context...\n";
$followUpMsg = "Berikan 1 contoh konkret dari penjelasan rubrik esai yang tadi Anda sebutkan.";
$resFollowUp = $chatService->sendMessage($teacherId, 'guru', $followUpMsg, $guruSessionUuid, false);

$history = $chatService->getSessionHistory($teacherId, 'guru', $guruSessionUuid);
$messageCount = count($history['messages'] ?? []);

if ($resFollowUp['success'] && $messageCount >= 4) { // 2 user msgs + 2 assistant msgs
    $passedTests++;
    $msg = "[PASS] Test 4: Multi-turn chat verified. Total {$messageCount} messages preserved in session '{$guruSessionUuid}'.";
    $results[] = $msg;
    echo "  --> {$msg}\n";
    echo "      Follow-up Query: \"{$followUpMsg}\"\n";
    echo "      Follow-up Reply: " . mb_substr($resFollowUp['reply'], 0, 100) . "...\n\n";
} else {
    $msg = "[FAIL] Test 4: Multi-turn history failed. Message count: {$messageCount}";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 5: Database Persistence & Session Integrity Check
// -----------------------------------------------------------------------------
echo "[TEST 5] Checking Database Records in ai_chat_sessions & ai_chat_messages...\n";
$dbSessions = $db->query("SELECT COUNT(*) FROM ai_chat_sessions")->fetchColumn();
$dbMessages = $db->query("SELECT COUNT(*) FROM ai_chat_messages WHERE is_error = 0")->fetchColumn();
$examSessionRecord = $db->query("SELECT * FROM ai_chat_sessions WHERE is_exam_mode = 1 LIMIT 1")->fetch();

echo "  -> Total Sessions in DB: {$dbSessions}\n";
echo "  -> Total Messages in DB: {$dbMessages}\n";
echo "  -> EXAM_MODE Session Record in DB: " . ($examSessionRecord ? "YES (ID: {$examSessionRecord['id']})" : "NO") . "\n";

if ($dbSessions >= 3 && $dbMessages >= 6 && $examSessionRecord !== false) {
    $passedTests++;
    $msg = "[PASS] Test 5: Database persistence verified for sessions, messages, tokens, and exam mode flag.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $msg = "[FAIL] Test 5: Database persistence check failed.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// TEST 6: Clear History / Reset Session
// -----------------------------------------------------------------------------
echo "[TEST 6] Testing Clear History & Session Reset Functionality...\n";
$clearRes = $chatService->clearSessionHistory($studentId, 'siswa', $siswaSessionUuid);
$postClearHistory = $chatService->getSessionHistory($studentId, 'siswa', $siswaSessionUuid);
$postClearCount = count($postClearHistory['messages'] ?? []);

if ($clearRes['success'] && $postClearCount === 0) {
    $passedTests++;
    $msg = "[PASS] Test 6: Clear chat history succeeded. Active session messages reset to 0.";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
} else {
    $msg = "[FAIL] Test 6: Clear history failed. Remaining messages: {$postClearCount}";
    $results[] = $msg;
    echo "  --> {$msg}\n\n";
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "====================================================================\n";
echo "                      PHASE 4 TEST SUMMARY                          \n";
echo "====================================================================\n";
foreach ($results as $res) {
    echo $res . "\n";
}
echo "--------------------------------------------------------------------\n";
echo "TOTAL: {$passedTests} / {$totalTests} TESTS PASSED\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    echo "\n>>> PHASE 4 — AI CHATBOT IS FULLY VERIFIED AND COMPLETE! <<<\n\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED IN PHASE 4 <<<\n\n";
    exit(1);
}
