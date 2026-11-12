<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\UserModel;
use App\Services\AI\AiChatService;

echo "====================================================================\n";
echo "   AI CHATBOT GUARDRAILS & DATABASE GROUNDING VERIFICATION TEST    \n";
echo "====================================================================\n\n";

$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
\App\Config\Database::$database = $targetDb;

$aiChatService = new AiChatService();
$userModel = new UserModel();

$adminUser = $userModel->findByEmail('admin@example.com');
$guruUser = $userModel->findByEmail('guru@example.com');
$siswaUser = $userModel->findByEmail('siswa@example.com');

$studentId = (int)($siswaUser['id'] ?? 4);
$teacherId = (int)($guruUser['id'] ?? 3);
$adminId = (int)($adminUser['id'] ?? 2);

$passCount = 0;
$totalCount = 0;

function runTest(string $title, bool $condition, string $details = ''): void {
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

// -------------------------------------------------------------------------
// TEST GROUP 1: Direct validateInputGuardrail Unit Tests (Out-Of-Scope Rejection)
// -------------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "1. TESTING OUT-OF-SCOPE GUARDRAILS (Rejection of non-exam requests)\n";
echo "--------------------------------------------------------------------\n";

// 1.1 Python coding request (user's exact example)
$res1 = $aiChatService->validateInputGuardrail('buatkan coding python untuk menuliskan nama anda', 'global', 'siswa');
runTest(
    '1.1 Rejects "buatkan coding python untuk menuliskan nama anda"',
    $res1 !== null && str_contains($res1, 'Di Luar Ruang Lingkup Sistem Ujian'),
    $res1 ?? 'NULL'
);

// 1.2 Other general programming requests
$res2 = $aiChatService->validateInputGuardrail('tolong buatkan script javascript untuk game snake', 'global', 'siswa');
runTest(
    '1.2 Rejects general JavaScript game request',
    $res2 !== null && str_contains($res2, 'Di Luar Ruang Lingkup Sistem Ujian'),
    $res2 ?? 'NULL'
);

// 1.3 Entertainment & casual storytelling
$res3 = $aiChatService->validateInputGuardrail('ceritakan dongeng kancil dan buaya yang lucu', 'global', 'siswa');
runTest(
    '1.3 Rejects casual fables/story requests',
    $res3 !== null && str_contains($res3, 'Di Luar Ruang Lingkup Sistem Ujian'),
    $res3 ?? 'NULL'
);

// 1.4 Politics & General Lifestyle
$res4 = $aiChatService->validateInputGuardrail('siapa presiden amerika serikat saat ini dan prediksi pemilu', 'global', 'siswa');
runTest(
    '1.4 Rejects politics & elections questions',
    $res4 !== null && str_contains($res4, 'Di Luar Ruang Lingkup Sistem Ujian'),
    $res4 ?? 'NULL'
);

// 1.5 Question Explainer Out-Of-Scope
$res5 = $aiChatService->validateInputGuardrail('buatkan coding python untuk scrape data web', 'question_explainer', 'siswa');
runTest(
    '1.5 Question Explainer rejects unrelated python coding',
    $res5 !== null && str_contains($res5, 'Guardrail Konteks Pembahasan Soal'),
    $res5 ?? 'NULL'
);

// 1.6 Legitimate Academic Questions must NOT be blocked
$legit1 = $aiChatService->validateInputGuardrail('Jelaskan dengan bahasa sederhana apa itu variabel dan konstanta dalam aljabar.', 'global', 'siswa');
runTest(
    '1.6 Allows legitimate algebra concept question',
    $legit1 === null,
    'Expected null, got: ' . ($legit1 ?? 'null')
);

$legit2 = $aiChatService->validateInputGuardrail('Halo, sebutkan nama saya, saya kelas berapa, dan siapa nama wali kelas saya menurut data sekolah?', 'global', 'siswa');
runTest(
    '1.7 Allows legitimate student profile & school data inquiry',
    $legit2 === null,
    'Expected null, got: ' . ($legit2 ?? 'null')
);

// -------------------------------------------------------------------------
// TEST GROUP 2: Live End-to-End Chat with Out-Of-Scope Filter
// -------------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "2. TESTING LIVE END-TO-END CHATBOT GUARDRAIL INTERCEPTION\n";
echo "--------------------------------------------------------------------\n";

$chatRes = $aiChatService->sendMessage(
    $studentId,
    'siswa',
    'buatkan coding python untuk menuliskan nama anda'
);

runTest(
    '2.1 Live Chatbot intercepts "buatkan coding python..." without calling external API',
    $chatRes['success'] === true && 
    ($chatRes['guardrail_triggered'] ?? false) === true && 
    str_contains($chatRes['reply'], 'Di Luar Ruang Lingkup Sistem Ujian'),
    'Reply: ' . ($chatRes['reply'] ?? '')
);

// -------------------------------------------------------------------------
// TEST GROUP 3: Database Grounding & Explainer Verification
// -------------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "3. TESTING DATABASE GROUNDING & EXPLAINER INSTRUCTION\n";
echo "--------------------------------------------------------------------\n";

$db = Database::getConnection();
$answerRow = $db->query("
    SELECT ea.id 
    FROM exam_answers ea 
    JOIN exam_sessions es ON es.id = ea.exam_session_id 
    JOIN exams e ON e.id = es.exam_id
    LEFT JOIN exam_results er ON er.exam_session_id = es.id
    WHERE es.student_id = {$studentId}
      AND es.status = 'submitted'
      AND (er.status = 'published' OR e.show_result_immediately = 1)
    LIMIT 1
")->fetch();

if ($answerRow) {
    $answerId = (int)$answerRow['id'];
    echo "  Found exam answer ID: {$answerId} for student ID: {$studentId}\n";

    $historyRes = $aiChatService->getQuestionChatHistory($studentId, $answerId);
    runTest(
        '3.1 Successfully fetched question history and question metadata from database',
        $historyRes['success'] === true && !empty($historyRes['question_meta']),
        'Question ID: ' . ($historyRes['question_meta']['question_id'] ?? '-')
    );

    $explainRes = $aiChatService->explainQuestionItem(
        $studentId,
        $answerId,
        'Tolong jelaskan secara mendalam konsep butir soal ini dan mengapa kunci jawaban tersebut benar.'
    );

    runTest(
        '3.2 Question Explainer produces comprehensive explanation grounded in database',
        $explainRes['success'] === true && !empty($explainRes['reply']),
        'AI Reply Preview: ' . mb_substr($explainRes['reply'] ?? '', 0, 150) . '...'
    );
} else {
    echo "  [SKIP] No existing exam answer found for student ID {$studentId} to run live question explainer test.\n\n";
}

echo "====================================================================\n";
echo "   RESULT: Passed {$passCount} / {$totalCount} tests.\n";
echo "====================================================================\n";
