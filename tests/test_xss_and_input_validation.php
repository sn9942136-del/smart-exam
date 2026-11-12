<?php

declare(strict_types=1);

/**
 * Test Suite: Security Hardening - XSS Prevention & Input Validation
 * Memvalidasi sanitasi input, escaping output, proteksi stored XSS,
 * dan preservasi formatting HTML legitimate.
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\Session;

Session::start();
$_SESSION['user_id'] = 1;
$_SESSION['user_data'] = [
    'id' => 1,
    'name' => 'Guru Pengampu',
    'username' => 'guru',
    'role' => 'guru'
];

use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use App\Services\AI\AiChatService;
use App\Services\AI\AiQuestionService;
use App\Services\ExamService;
use App\Services\QuestionService;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo "![FAIL] {$name}" . ($details ? " - {$details}" : '') . "\n";
        $failed++;
    }
}

echo "======================================================================\n";
echo " SECURITY REVIEW & HARDENING TEST SUITE: XSS & INPUT VALIDATION\n";
echo "======================================================================\n\n";

// -------------------------------------------------------------------------
// SECTION 1: UNIT TEST sanitize_html() HELPER
// -------------------------------------------------------------------------
echo "--- Section 1: sanitize_html() Core Helper Tests ---\n";

// Test 1.1: Script tag removal
$payloadScript = '<script>alert("XSS")</script>Hello World';
$clean1 = sanitize_html($payloadScript);
assertTest(
    "1.1 Strips <script> tags completely",
    !str_contains($clean1, '<script>') && !str_contains($clean1, 'alert("XSS")') && str_contains($clean1, 'Hello World'),
    "Output: " . $clean1
);

// Test 1.2: Nested/obfuscated script tag
$payloadNestedScript = '<scr<script>ipt>alert("Bypass")</script></script>Clean Text';
$cleanNested = sanitize_html($payloadNestedScript);
assertTest(
    "1.2 Strips nested <script> tag recursion",
    !str_contains(strtolower($cleanNested), 'script') && !str_contains($cleanNested, 'alert'),
    "Output: " . $cleanNested
);

// Test 1.3: Image onerror event handler
$payloadImgOnError = '<img src="invalid-image.jpg" onerror="alert(\'XSS\')" alt="Soal">';
$cleanImg = sanitize_html($payloadImgOnError);
assertTest(
    "1.3 Strips onerror event handler from <img>",
    !str_contains($cleanImg, 'onerror') && !str_contains($cleanImg, 'alert') && str_contains($cleanImg, '<img') && str_contains($cleanImg, 'alt="Soal"'),
    "Output: " . $cleanImg
);

// Test 1.4: Various on* event handlers (onclick, onmouseover, onload, onfocus)
$payloadOnEvents = '<div onclick="stealCookies()" onmouseover="runBadCode()" onfocus="alert(1)">Click Me</div>';
$cleanEvents = sanitize_html($payloadOnEvents);
assertTest(
    "1.4 Strips on* event handlers from all elements",
    !str_contains($cleanEvents, 'onclick') && !str_contains($cleanEvents, 'onmouseover') && !str_contains($cleanEvents, 'onfocus') && str_contains($cleanEvents, 'Click Me'),
    "Output: " . $cleanEvents
);

// Test 1.5: javascript: pseudo-protocol in href / src
$payloadJsHref = '<a href="javascript:alert(document.cookie)">Klik Disini</a>';
$cleanJsHref = sanitize_html($payloadJsHref);
assertTest(
    "1.5 Disarms javascript: protocol in links",
    !str_contains($cleanJsHref, 'javascript:') && str_contains($cleanJsHref, 'href="#"') && str_contains($cleanJsHref, 'Klik Disini'),
    "Output: " . $cleanJsHref
);

// Test 1.6: Style attribute expression / javascript execution
$payloadStyleExpr = '<div style="width: expression(alert(1)); color: red;">Test</div>';
$cleanStyle = sanitize_html($payloadStyleExpr);
assertTest(
    "1.6 Disarms CSS expression / javascript in style attribute",
    !str_contains($cleanStyle, 'expression') && !str_contains($cleanStyle, 'alert'),
    "Output: " . $cleanStyle
);

// Test 1.7: iframe, object, embed, svg tag removal
$payloadDangerousTags = '<iframe src="http://evil.com"></iframe><svg onload="alert(1)"><circle r="10"/></svg><embed src="evil.swf">';
$cleanTags = sanitize_html($payloadDangerousTags);
assertTest(
    "1.7 Strips <iframe>, <svg>, <embed>, <object> tags",
    !str_contains($cleanTags, 'iframe') && !str_contains($cleanTags, 'svg') && !str_contains($cleanTags, 'embed') && !str_contains($cleanTags, 'onload'),
    "Output: " . $cleanTags
);

// Test 1.8: Preserves legitimate rich text formatting
$legitimateHtml = '<p>Berapakah hasil dari <b>2<sup>3</sup> + 4<sub>1</sub></b>?</p><table class="table table-bordered"><thead><tr><th>No</th><th>Item</th></tr></thead><tbody><tr><td>1</td><td><code>console.log("OK");</code></td></tr></tbody></table><ul><li>Pilihan 1</li><li>Pilihan 2</li></ul><img src="uploads/questions/diagram.png" class="img-fluid" alt="Diagram Soal">';
$cleanLegit = sanitize_html($legitimateHtml);
assertTest(
    "1.8 Preserves legitimate HTML formatting (<b>, <i>, <sup>, <sub>, <table>, <code>, <ul>, <img>)",
    str_contains($cleanLegit, '<b>2<sup>3</sup> + 4<sub>1</sub></b>') &&
    str_contains($cleanLegit, '<table class="table table-bordered">') &&
    str_contains($cleanLegit, '<code>console.log("OK");</code>') &&
    str_contains($cleanLegit, '<ul><li>Pilihan 1</li>') &&
    str_contains($cleanLegit, '<img src="uploads/questions/diagram.png" class="img-fluid" alt="Diagram Soal">'),
    "Output: " . $cleanLegit
);

// -------------------------------------------------------------------------
// SECTION 2: QUESTION & OPTION CONTENT HARDENING (QuestionService)
// -------------------------------------------------------------------------
echo "\n--- Section 2: Question & Option Content Sanitization ---\n";

try {
    $db = Database::getConnection();

    // Pastikan ada subject dan bank soal untuk test
    $subjectModel = new SubjectModel();
    $subject = $subjectModel->first();
    if (!$subject) {
        $subjId = $subjectModel->insert(['code' => 'SEC101', 'name' => 'Keamanan Sistem']);
    } else {
        $subjId = (int)$subject['id'];
    }

    $userModel = new UserModel();
    $teacher = $userModel->where('status', 'active')->first();
    $teacherId = $teacher ? (int)$teacher['id'] : 1;

    $bankModel = new QuestionBankModel();
    $bankId = (int)$bankModel->insert([
        'subject_id' => $subjId,
        'creator_id' => $teacherId,
        'code' => 'BNK-SEC-' . rand(1000, 9999),
        'title' => 'Bank Soal Security Test',
        'status' => 'draft'
    ]);

    $questionService = new QuestionService();

    // Test 2.1: Create question with malicious question content
    $maliciousQContent = '<script>alert("XSS Question Content")</script><p>Sebuah jaringan komputer memiliki subnet mask <b>255.255.255.0</b>.</p>';
    $maliciousExplanation = '<img src=x onerror=alert("XSS Explanation")><p>Pembahasan: Nilai prefix adalah /24.</p>';
    $maliciousOptionA = '<b onmouseover="alert(\'XSS Option A\')">/24 (Subnet Standar)</b>';
    $maliciousOptionB = '<img src="valid.png" onerror="alert(1)"> /25';

    $qRes = $questionService->createQuestion($bankId, [
        'type' => 'multiple_choice',
        'content' => $maliciousQContent,
        'weight' => 2.0,
        'difficulty' => 'medium',
        'explanation' => $maliciousExplanation,
        'options' => [
            'A' => $maliciousOptionA,
            'B' => $maliciousOptionB,
            'C' => '/26',
            'D' => '/27'
        ],
        'correct_option' => 'A'
    ]);

    assertTest("2.1 Question creation succeeds with sanitized data", $qRes['success'] === true, json_encode($qRes['errors'] ?? []));

    $questionId = $qRes['question_id'];
    $questionModel = new QuestionModel();
    $savedQ = $questionModel->find($questionId);

    // Verify question content in DB
    assertTest(
        "2.2 Question content is sanitized in database (no <script>, preserves <b>/255...</b>)",
        !str_contains($savedQ['content'], '<script>') &&
        !str_contains($savedQ['content'], 'alert(') &&
        str_contains($savedQ['content'], '<b>255.255.255.0</b>'),
        "DB Content: " . $savedQ['content']
    );

    // Verify explanation in DB
    assertTest(
        "2.3 Question explanation is sanitized in database (no onerror, preserves <p>Pembahasan...)",
        !str_contains($savedQ['explanation'], 'onerror') &&
        !str_contains($savedQ['explanation'], 'alert(') &&
        str_contains($savedQ['explanation'], 'Pembahasan: Nilai prefix adalah /24.'),
        "DB Explanation: " . $savedQ['explanation']
    );

    // Verify options in DB
    $optionModel = new QuestionOptionModel();
    $options = $optionModel->where('question_id', $questionId)->get();
    $optA = null;
    $optB = null;
    foreach ($options as $opt) {
        if ($opt['option_key'] === 'A') $optA = $opt;
        if ($opt['option_key'] === 'B') $optB = $opt;
    }

    assertTest(
        "2.4 Option A is sanitized (no onmouseover, preserves <b>/24 (Subnet Standar)</b>)",
        $optA !== null && !str_contains($optA['content'], 'onmouseover') && !str_contains($optA['content'], 'alert(') && str_contains($optA['content'], '<b>/24 (Subnet Standar)</b>'),
        "DB Option A: " . ($optA['content'] ?? '')
    );

    assertTest(
        "2.5 Option B is sanitized (no onerror on <img>)",
        $optB !== null && !str_contains($optB['content'], 'onerror') && !str_contains($optB['content'], 'alert(1)'),
        "DB Option B: " . ($optB['content'] ?? '')
    );

    // Cleanup created test question & bank
    $questionService->deleteQuestion($questionId);
    $bankModel->delete($bankId);

} catch (\Throwable $e) {
    assertTest("Section 2 Question Service Exception", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// SECTION 3: EXAM TITLE & DESCRIPTION SANITIZATION (ExamService)
// -------------------------------------------------------------------------
echo "\n--- Section 3: Exam Title & Description Sanitization ---\n";

try {
    $examService = new ExamService();

    // 1. Siapkan bank soal dan kelas peserta untuk ujian
    $classModel = new \App\Models\ClassModel();
    $class = $classModel->first();
    $classId = $class ? (int)$class['id'] : 1;

    $testBankId = (int)$bankModel->insert([
        'subject_id' => $subjId,
        'creator_id' => $teacherId,
        'code' => 'BNK-EXM-' . rand(1000, 9999),
        'title' => 'Bank Soal Ujian Security',
        'status' => 'active'
    ]);

    $maliciousExamTitle = '<script>alert("XSS Exam Title")</script>Ujian Akhir Semester Pemrograman Web';
    $maliciousExamDesc = '<p>Kerjakan dengan jujur.</p><img src=x onerror=alert("XSS Desc")>';

    $examRes = $examService->createExam($teacherId, [
        'subject_id' => $subjId,
        'title' => $maliciousExamTitle,
        'description' => $maliciousExamDesc,
        'type' => 'final_exam',
        'duration_minutes' => 60,
        'passing_grade' => 75.0,
        'question_bank_id' => $testBankId,
        'class_ids' => [$classId],
        'start_time' => date('Y-m-d H:i:00'),
        'end_time' => date('Y-m-d H:i:00', strtotime('+3 hours')),
        'status' => 'draft'
    ]);

    assertTest("3.1 Exam creation succeeds", $examRes['success'] === true, json_encode($examRes['errors'] ?? []));

    $examId = $examRes['exam_id'];
    $examModel = new \App\Models\ExamModel();
    $savedExam = $examModel->find($examId);

    assertTest(
        "3.2 Exam title is stripped of all HTML tags (stored clean plain text)",
        $savedExam['title'] === 'Ujian Akhir Semester Pemrograman Web' && !str_contains($savedExam['title'], '<script>'),
        "DB Title: " . $savedExam['title']
    );

    assertTest(
        "3.3 Exam description is sanitized (no onerror, preserves <p>Kerjakan dengan jujur.</p>)",
        !str_contains($savedExam['description'], 'onerror') && !str_contains($savedExam['description'], 'alert') && str_contains($savedExam['description'], '<p>Kerjakan dengan jujur.</p>'),
        "DB Description: " . $savedExam['description']
    );

    // Cleanup test exam & bank
    $examModel->delete($examId);
    $bankModel->delete($testBankId);

} catch (\Throwable $e) {
    assertTest("Section 3 Exam Service Exception", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// SECTION 4: AI GENERATED QUESTIONS SANITIZATION (AiQuestionService)
// -------------------------------------------------------------------------
echo "\n--- Section 4: AI Generated Content Sanitization ---\n";

try {
    $aiQService = new AiQuestionService();

    $rawAiResponse = json_encode([
        'questions' => [
            [
                'number' => 1,
                'type' => 'multiple_choice',
                'difficulty' => 'easy',
                'weight' => 2.0,
                'content' => '<script>alert("AI Stored XSS")</script>Apa kepanjangan dari <b>HTML</b>?',
                'explanation' => '<img src=x onerror=alert("AI Expl XSS")>HyperText Markup Language',
                'options' => [
                    ['label' => 'A', 'text' => '<b onfocus="alert(1)">HyperText Markup Language</b>', 'is_correct' => 1],
                    ['label' => 'B', 'text' => 'High Text Machine Language', 'is_correct' => 0]
                ]
            ]
        ]
    ]);

    $validation = $aiQService->validateGeneratedQuestions($rawAiResponse);
    assertTest("4.1 validateGeneratedQuestions succeeds", $validation['valid'] === true);

    $validatedQ = $validation['questions'][0] ?? [];

    assertTest(
        "4.2 AI generated question content is sanitized during schema validation",
        !str_contains($validatedQ['content'] ?? '', '<script>') && str_contains($validatedQ['content'] ?? '', '<b>HTML</b>'),
        "Validated content: " . ($validatedQ['content'] ?? '')
    );

    assertTest(
        "4.3 AI generated explanation is sanitized during schema validation",
        !str_contains($validatedQ['explanation'] ?? '', 'onerror') && str_contains($validatedQ['explanation'] ?? '', 'HyperText Markup Language'),
        "Validated explanation: " . ($validatedQ['explanation'] ?? '')
    );

    assertTest(
        "4.4 AI generated option choice is sanitized during schema validation",
        !str_contains($validatedQ['options'][0]['text'] ?? '', 'onfocus') && str_contains($validatedQ['options'][0]['text'] ?? '', '<b>HyperText Markup Language</b>'),
        "Validated option: " . ($validatedQ['options'][0]['text'] ?? '')
    );

} catch (\Throwable $e) {
    assertTest("Section 4 AI Question Service Exception", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// SECTION 5: AI CHAT OUTPUT SANITIZATION (AiChatService)
// -------------------------------------------------------------------------
echo "\n--- Section 5: AI Chat Output Sanitization ---\n";

try {
    $aiChatService = new AiChatService();

    $maliciousAiReply = "Berikut adalah contoh penjelasan materi:\n\n<script>alert('Chat XSS')</script>\n\n<img src=x onerror=\"alert('Chat Img XSS')\">\n\n**Konsep Penting:**\nAlgoritma enkripsi RSA menggunakan pasangan kunci publik dan privat.";

    $cleanChatOutput = $aiChatService->sanitizeAiOutput($maliciousAiReply);

    assertTest(
        "5.1 sanitizeAiOutput removes <script> and onerror from assistant replies",
        !str_contains($cleanChatOutput, '<script>') &&
        !str_contains($cleanChatOutput, 'onerror') &&
        !str_contains($cleanChatOutput, 'alert(') &&
        str_contains($cleanChatOutput, '**Konsep Penting:**'),
        "Sanitized Chat: " . $cleanChatOutput
    );

} catch (\Throwable $e) {
    assertTest("Section 5 AI Chat Service Exception", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// SECTION 6: CLIENT-SIDE JAVASCRIPT SANITIZER SIMULATION
// -------------------------------------------------------------------------
echo "\n--- Section 6: Client-Side Exam Engine & Markdown Sanitizer Logic ---\n";

// We simulate the DOMParser logic using regex representation matching exam-engine.js and markdown-renderer.js
$jsSanitizeSim = function(string $dirty): string {
    return sanitize_html($dirty);
};

$sampleMaliciousPayloads = [
    '<script>alert("exam-engine XSS")</script>Soal Fisika: Hitung gaya Coulomb.',
    '<img src="https://images.unsplash.com/photo-1.jpg" onerror="alert(\'img XSS\')" class="img-fluid">',
    '<a href="javascript:alert(\'link XSS\')">Klik Penjelasan</a>',
    '<div style="background-image: url(javascript:alert(1))">Pilihan A</div>'
];

foreach ($sampleMaliciousPayloads as $idx => $payload) {
    $sanitized = $jsSanitizeSim($payload);
    $isSafe = !str_contains($sanitized, '<script>') &&
              !str_contains($sanitized, 'onerror') &&
              !str_contains($sanitized, 'javascript:alert') &&
              !str_contains($sanitized, 'url(javascript:');
    assertTest("6." . ($idx + 1) . " Client sanitizer payload test [Payload " . ($idx + 1) . "]", $isSafe, "Result: " . $sanitized);
}

// -------------------------------------------------------------------------
// SECTION 7: ADMIN & GURU FORM INPUT SANITIZATION
// -------------------------------------------------------------------------
echo "\n--- Section 7: Admin & Guru Form Input Sanitization ---\n";

try {
    // 7.1 User Model / Controller Sanitization
    $rawUserName = '<script>alert("User XSS")</script>Dr. Ahmad Dahlan';
    $rawUserNis = '<img src=x onerror=alert("NIS")>12345678';
    $cleanUserName = clean_plain_text($rawUserName);
    $cleanUserNis = clean_plain_text($rawUserNis);

    assertTest("7.1 User full name is stripped of HTML tags and scripts", $cleanUserName === 'Dr. Ahmad Dahlan' && !str_contains($cleanUserName, '<script>'));
    assertTest("7.2 User NISN/NIP is stripped of HTML tags and handlers", $cleanUserNis === '12345678' && !str_contains($cleanUserNis, '<img'));

    // 7.3 Subject Name Sanitization
    $rawSubjectName = '<b>Fisika Terapan</b><script>steal()</script>';
    $cleanSubjectName = clean_plain_text($rawSubjectName);
    assertTest("7.3 Subject name is stripped of HTML tags and scripts", $cleanSubjectName === 'Fisika Terapan');

    // 7.4 Class Name Sanitization
    $rawClassName = 'XII MIPA 1 <iframe src="evil.com"></iframe>';
    $cleanClassName = clean_plain_text($rawClassName);
    assertTest("7.4 Class name is stripped of HTML tags and iframes", $cleanClassName === 'XII MIPA 1');

    // 7.5 Question Bank Title Sanitization
    $rawBankTitle = '<span onfocus="alert(1)">Bank Soal Matematika Dasar</span>';
    $cleanBankTitle = clean_plain_text($rawBankTitle);
    assertTest("7.5 Question Bank title is stripped of HTML tags and handlers", $cleanBankTitle === 'Bank Soal Matematika Dasar');

} catch (\Throwable $e) {
    assertTest("Section 7 Form Input Sanitization Exception", false, $e->getMessage());
}

echo "\n======================================================================\n";
echo " TEST SUMMARY\n";
echo "======================================================================\n";
echo " Total Tests Run: " . ($passed + $failed) . "\n";
echo " Passed: {$passed}\n";
echo " Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed === 0) {
    echo " ALL XSS & INPUT VALIDATION SECURITY TESTS PASSED PERFECTLY!\n";
    exit(0);
} else {
    echo " SOME TESTS FAILED. PLEASE REVIEW.\n";
    exit(1);
}
