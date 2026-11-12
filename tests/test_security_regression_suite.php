<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * COMPREHENSIVE SECURITY REGRESSION TEST SUITE
 * ==============================================================================
 * Verifies all security hardening rules:
 *
 * AUTHORIZATION:
 * 1. Siswa tidak dapat mengakses admin
 * 2. Siswa tidak dapat mengakses guru
 * 3. Guru tidak dapat mengakses admin
 * 4. Role tidak sesuai -> 403 Forbidden
 *
 * CSRF:
 * 5. POST tanpa CSRF -> reject (419 / redirect)
 * 6. POST valid CSRF -> success
 *
 * EXAM:
 * 7. Expired session -> answer rejected
 * 8. Submitted session -> answer rejected
 * 9. Wrong student session -> rejected
 * 10. Wrong question -> rejected
 * 11. Wrong option -> rejected
 * 12. Double submit -> idempotent
 *
 * SECURITY:
 * 13. Destructive GET tidak mengubah database
 * 14. Malicious HTML tidak menjadi executable script (XSS sanitizer)
 * 15. Login brute force dibatasi (RateLimitFilter)
 * 16. OTP brute force dibatasi (RateLimitFilter)
 *
 * PROCTORING:
 * 17. Invalid proctoring event rejected
 * 18. Invalid session rejected
 * 19. Timestamp invalid rejected
 * ==============================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\App;
use App\Filters\CsrfFilter;
use App\Filters\RateLimitFilter;
use App\Filters\RoleFilter;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\UserModel;
use App\Services\AiProctoringService;
use App\Services\ExamAnswerService;
use App\Services\ExamSessionService;
use App\Services\ExamTimerService;
use App\Services\ViolationDetectionService;

Session::start();

echo "==============================================================================\n";
echo "           COMPREHENSIVE SECURITY REGRESSION TEST SUITE                       \n";
echo "==============================================================================\n\n";

$passCount = 0;
$failCount = 0;
$errors = [];

function testCase(int $num, string $section, string $description, callable $fn): void {
    global $passCount, $failCount, $errors;
    try {
        $fn();
        echo "  ✓ [PASS] #{$num} [{$section}] {$description}\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo "  ✗ [FAIL] #{$num} [{$section}] {$description}\n";
        echo "           Error: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failCount++;
        $errors[] = [
            'num'         => $num,
            'section'     => $section,
            'description' => $description,
            'error'       => $e->getMessage(),
            'file'        => $e->getFile() . ':' . $e->getLine()
        ];
    }
}

// ----------------------------------------------------------------------
// SETUP DATA TEST
// ----------------------------------------------------------------------
$db = Database::getConnection();
$userModel = new UserModel();
$examModel = new ExamModel();
$sessionModel = new ExamSessionModel();
$answerModel = new ExamAnswerModel();
$participantModel = new ExamParticipantModel();
$examQuestionModel = new ExamQuestionModel();
$resultModel = new ExamResultModel();

$timerService = new ExamTimerService();
$answerService = new ExamAnswerService();
$sessionService = new ExamSessionService();
$proctoringService = new AiProctoringService();
$violationService = new ViolationDetectionService();

// Ambil Akun untuk Testing
$adminUser = $userModel->findByEmail('admin@example.com');
if (!$adminUser) {
    $row = $db->query("SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.name IN ('super_admin', 'admin') LIMIT 1")->fetch();
    if ($row) {
        $adminUser = $row;
    } else {
        $aId = $userModel->insert([
            'name'      => 'Admin Testing',
            'username'  => 'admin_test',
            'email'     => 'admin@example.com',
            'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
            'status'    => 'active',
            'created_at'=> date('Y-m-d H:i:s'),
            'updated_at'=> date('Y-m-d H:i:s')
        ]);
        $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, 1, NOW())")->execute([':uid' => $aId]);
        $adminUser = $userModel->find($aId);
    }
}

$guruUser = $userModel->findByEmail('guru@sekolah.sch.id');
if (!$guruUser) {
    $row = $db->query("SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.name = 'guru' LIMIT 1")->fetch();
    if ($row) {
        $guruUser = $row;
    } else {
        $gId = $userModel->insert([
            'name'      => 'Guru Testing',
            'username'  => 'guru_test',
            'email'     => 'guru@sekolah.sch.id',
            'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
            'status'    => 'active',
            'created_at'=> date('Y-m-d H:i:s'),
            'updated_at'=> date('Y-m-d H:i:s')
        ]);
        $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, 3, NOW())")->execute([':uid' => $gId]);
        $guruUser = $userModel->find($gId);
    }
}

$siswaUser1 = $userModel->findByEmail('siswa@sekolah.sch.id');
if (!$siswaUser1) {
    $existingUser1 = $db->query("SELECT * FROM users WHERE email = 'siswa@sekolah.sch.id'")->fetch();
    if ($existingUser1) {
        $db->prepare("UPDATE users SET deleted_at = NULL, status = 'active' WHERE id = :id")->execute([':id' => $existingUser1['id']]);
        $siswaUser1 = $userModel->find((int)$existingUser1['id']);
    } else {
        $row = $db->query("SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.name IN ('siswa', 'peserta_didik') AND u.email != 'siswa2@sekolah.sch.id' LIMIT 1")->fetch();
        if ($row) {
            $siswaUser1 = $row;
        } else {
            $s1Id = $userModel->insert([
                'name'      => 'Siswa Testing Pertama',
                'username'  => 'siswa_tester_1',
                'email'     => 'siswa@sekolah.sch.id',
                'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
                'status'    => 'active',
                'created_at'=> date('Y-m-d H:i:s'),
                'updated_at'=> date('Y-m-d H:i:s')
            ]);
            $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, 4, NOW())")->execute([':uid' => $s1Id]);
            $siswaUser1 = $userModel->find($s1Id);
        }
    }
}

$siswaUser2 = $userModel->findByEmail('siswa2@sekolah.sch.id');
if (!$siswaUser2) {
    $existingUser2 = $db->query("SELECT * FROM users WHERE email = 'siswa2@sekolah.sch.id'")->fetch();
    if ($existingUser2) {
        $db->prepare("UPDATE users SET deleted_at = NULL, status = 'active' WHERE id = :id")->execute([':id' => $existingUser2['id']]);
        $siswaUser2 = $userModel->find((int)$existingUser2['id']);
    } else {
        $s2Id = $userModel->insert([
            'name'      => 'Siswa Kedua Testing',
            'username'  => 'siswa_sec_2',
            'email'     => 'siswa2@sekolah.sch.id',
            'password'  => password_hash('Password123!', PASSWORD_BCRYPT),
            'status'    => 'active',
            'created_at'=> date('Y-m-d H:i:s'),
            'updated_at'=> date('Y-m-d H:i:s')
        ]);
        $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, 4, NOW())")->execute([':uid' => $s2Id]);
        $siswaUser2 = $userModel->find($s2Id);
    }
}

// Ambil minimal 2 Ujian yang memiliki butir soal
$allExams = $examModel->findAll();
$exam1 = null;
$exam2 = null;
$questions1 = [];
$questions2 = [];

foreach ($allExams as $e) {
    $qs = $examQuestionModel->getQuestionsByExamId((int)$e['id']);
    if (!empty($qs)) {
        if ($exam1 === null) {
            $exam1 = $e;
            $questions1 = $qs;
        } elseif ($exam2 === null) {
            $exam2 = $e;
            $questions2 = $qs;
            break;
        }
    }
}

if (!$exam1 || empty($questions1)) {
    throw new RuntimeException("Setup Gagal: Minimal harus ada 1 ujian yang memiliki butir soal.");
}
if (!$exam2 || empty($questions2)) {
    $exam2 = $exam1;
    $questions2 = $questions1;
}

$exam1Id = (int)$exam1['id'];
$exam2Id = (int)$exam2['id'];
$q1 = $questions1[0];
$q1Id = (int)$q1['question_id'];
$opt1Id = !empty($q1['options']) ? (int)$q1['options'][0]['id'] : 1;

// ----------------------------------------------------------------------
// SECTION 1: AUTHORIZATION (Tests 1-4)
// ----------------------------------------------------------------------
echo "--- SECTION 1: AUTHORIZATION HARDENING ---\n";

testCase(1, "AUTH", "Siswa tidak dapat mengakses modul admin", function() use ($siswaUser1) {
    // Set sesi login sebagai Siswa
    Session::set('user', [
        'id'    => (int)$siswaUser1['id'],
        'name'  => $siswaUser1['name'],
        'role'  => 'peserta_didik',
        'roles' => ['peserta_didik', 'siswa']
    ]);

    $roleFilter = new RoleFilter();
    $request = new Request([], [
        'REQUEST_URI'    => '/admin/dashboard',
        'REQUEST_METHOD' => 'GET'
    ]);

    $response = $roleFilter->before($request, ['super_admin', 'admin']);

    if (!$response) {
        throw new RuntimeException("Siswa diizinkan mengakses rute admin!");
    }
    if ($response->getStatusCode() !== 302 || !str_contains($response->getHeader('Location') ?? '', 'unauthorized')) {
        throw new RuntimeException("Respon bukan pengalihan ke /unauthorized.");
    }
});

testCase(2, "AUTH", "Siswa tidak dapat mengakses modul guru", function() use ($siswaUser1) {
    // Set sesi login sebagai Siswa
    Session::set('user', [
        'id'    => (int)$siswaUser1['id'],
        'name'  => $siswaUser1['name'],
        'role'  => 'peserta_didik',
        'roles' => ['peserta_didik', 'siswa']
    ]);

    $roleFilter = new RoleFilter();
    $request = new Request([], [
        'REQUEST_URI'    => '/guru/dashboard',
        'REQUEST_METHOD' => 'GET'
    ]);

    $response = $roleFilter->before($request, ['guru']);

    if (!$response) {
        throw new RuntimeException("Siswa diizinkan mengakses rute guru!");
    }
    if ($response->getStatusCode() !== 302 || !str_contains($response->getHeader('Location') ?? '', 'unauthorized')) {
        throw new RuntimeException("Respon bukan pengalihan ke /unauthorized.");
    }
});

testCase(3, "AUTH", "Guru tidak dapat mengakses modul admin", function() use ($guruUser) {
    // Set sesi login sebagai Guru
    Session::set('user', [
        'id'    => (int)$guruUser['id'],
        'name'  => $guruUser['name'],
        'role'  => 'guru',
        'roles' => ['guru']
    ]);

    $roleFilter = new RoleFilter();
    $request = new Request([], [
        'REQUEST_URI'    => '/admin/users',
        'REQUEST_METHOD' => 'GET'
    ]);

    $response = $roleFilter->before($request, ['super_admin', 'admin']);

    if (!$response) {
        throw new RuntimeException("Guru diizinkan mengakses rute admin!");
    }
    if ($response->getStatusCode() !== 302 || !str_contains($response->getHeader('Location') ?? '', 'unauthorized')) {
        throw new RuntimeException("Respon bukan pengalihan ke /unauthorized.");
    }
});

testCase(4, "AUTH", "Role tidak sesuai pada AJAX request menghasilkan status HTTP 403 Forbidden", function() use ($siswaUser1) {
    // Set sesi login sebagai Siswa
    Session::set('user', [
        'id'    => (int)$siswaUser1['id'],
        'name'  => $siswaUser1['name'],
        'role'  => 'peserta_didik',
        'roles' => ['peserta_didik', 'siswa']
    ]);

    $roleFilter = new RoleFilter();
    $request = new Request([], [
        'REQUEST_URI'           => '/admin/users/delete/1',
        'REQUEST_METHOD'        => 'POST',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
    ]);

    $response = $roleFilter->before($request, ['super_admin', 'admin']);

    if (!$response) {
        throw new RuntimeException("AJAX request tanpa role sesuai tidak ditolak!");
    }
    if ($response->getStatusCode() !== 403) {
        throw new RuntimeException("Status code yang dikembalikan bukan 403, melainkan: " . $response->getStatusCode());
    }
});

// ----------------------------------------------------------------------
// SECTION 2: CSRF PROTECTION (Tests 5-6)
// ----------------------------------------------------------------------
echo "\n--- SECTION 2: CSRF PROTECTION ---\n";

testCase(5, "CSRF", "POST tanpa CSRF token yang valid ditolak (HTTP 419 / redirect)", function() {
    $csrfFilter = new CsrfFilter();
    App::$csrfProtection = true;

    // 1. Uji AJAX Request tanpa CSRF token
    $ajaxRequest = new Request([], [
        'REQUEST_URI'           => '/admin/users/store',
        'REQUEST_METHOD'        => 'POST',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
    ]);

    $ajaxResponse = $csrfFilter->before($ajaxRequest);
    if (!$ajaxResponse || $ajaxResponse->getStatusCode() !== 419) {
        throw new RuntimeException("AJAX POST tanpa CSRF tidak menghasilkan response 419!");
    }

    // 2. Uji Form Request dengan token salah
    $formRequest = new Request([], [
        'REQUEST_URI'    => '/admin/users/store',
        'REQUEST_METHOD' => 'POST',
        'HTTP_REFERER'   => 'http://localhost/PRD/public/admin/users'
    ], [
        'csrf_token'     => 'INVALID_TOKEN_XYZ'
    ]);

    $formResponse = $csrfFilter->before($formRequest);
    if (!$formResponse || $formResponse->getStatusCode() !== 302) {
        throw new RuntimeException("Form POST dengan token salah tidak dialihkan!");
    }
});

testCase(6, "CSRF", "POST dengan valid CSRF token diizinkan melanjutkan request", function() {
    $csrfFilter = new CsrfFilter();
    App::$csrfProtection = true;

    $validToken = Session::generateCsrfToken();

    $request = new Request([], [
        'REQUEST_URI'       => '/admin/users/store',
        'REQUEST_METHOD'    => 'POST',
        'HTTP_X_CSRF_TOKEN' => $validToken
    ]);

    $response = $csrfFilter->before($request);
    if ($response !== null) {
        throw new RuntimeException("Request dengan valid CSRF token justru ditolak!");
    }
});

// ----------------------------------------------------------------------
// SECTION 3: EXAM ENGINE & DATA INTEGRITY (Tests 7-12)
// ----------------------------------------------------------------------
echo "\n--- SECTION 3: EXAM ENGINE & DATA INTEGRITY ---\n";

$studentId1 = (int)$siswaUser1['id'];
$studentId2 = (int)$siswaUser2['id'];

// Bersihkan data sesi pengujian
$db->exec("DELETE FROM exam_results WHERE exam_id IN ({$exam1Id}, {$exam2Id}) AND student_id IN ({$studentId1}, {$studentId2})");
$db->exec("DELETE FROM exam_answers WHERE exam_session_id IN (SELECT id FROM exam_sessions WHERE exam_id IN ({$exam1Id}, {$exam2Id}) AND student_id IN ({$studentId1}, {$studentId2}))");
$db->exec("DELETE FROM exam_sessions WHERE exam_id IN ({$exam1Id}, {$exam2Id}) AND student_id IN ({$studentId1}, {$studentId2})");

$origSched1 = $db->query("SELECT * FROM exam_schedules WHERE exam_id = {$exam1Id} AND class_id = 1 LIMIT 1")->fetch();
$db->exec("UPDATE exam_schedules SET end_time = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE exam_id = {$exam1Id} AND class_id = 1");

$db->prepare("INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at) 
              VALUES (:eid, :sid, 1, 'in_progress', NOW(), NOW())
              ON DUPLICATE KEY UPDATE status = 'in_progress'")
   ->execute([':eid' => $exam1Id, ':sid' => $studentId1]);

testCase(7, "EXAM", "Expired session: penyimpanan jawaban ditolak server", function() use ($exam1Id, $studentId1, $q1Id, $opt1Id, $db, $answerService) {
    // Buat sesi buatan yang sudah lampau (3 jam lalu)
    $expiredStartTime = date('Y-m-d H:i:s', time() - 10800);
    $expiredToken = bin2hex(random_bytes(32));

    $db->prepare("INSERT INTO exam_sessions 
        (exam_id, student_id, session_token, ip_address, user_agent, start_time, remaining_seconds, status, created_at, updated_at)
        VALUES (:eid, :sid, :token, '127.0.0.1', 'Test', :st, 0, 'ongoing', :ca, :ua)")
       ->execute([
           ':eid'   => $exam1Id,
           ':sid'   => $studentId1,
           ':token' => $expiredToken,
           ':st'    => $expiredStartTime,
           ':ca'    => $expiredStartTime,
           ':ua'    => $expiredStartTime
       ]);

    $saveRes = $answerService->saveAnswer($expiredToken, $studentId1, $q1Id, $opt1Id, null, 0);

    if ($saveRes['success']) {
        throw new RuntimeException("Penyimpanan jawaban pada sesi expired berhasil! Seharusnya ditolak.");
    }
    if ($saveRes['code'] !== 'EXPIRED') {
        throw new RuntimeException("Expected code=EXPIRED, didapat: " . ($saveRes['code'] ?? ''));
    }
});

testCase(8, "EXAM", "Submitted session: penyimpanan jawaban ditolak server", function() use ($exam1Id, $studentId1, $q1Id, $opt1Id, $sessionModel, $sessionService, $answerService) {
    // Buat sesi baru dan submit
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $token = $session['session_token'];

    $submitRes = $sessionService->submitExam($token, $studentId1);
    if (!$submitRes['success']) {
        throw new RuntimeException("Gagal submit ujian untuk setup test 8.");
    }

    $saveRes = $answerService->saveAnswer($token, $studentId1, $q1Id, $opt1Id, null, 0);
    if ($saveRes['success']) {
        throw new RuntimeException("Penyimpanan jawaban pada sesi submitted berhasil! Seharusnya ditolak.");
    }
    if ($saveRes['code'] !== 'ALREADY_SUBMITTED') {
        throw new RuntimeException("Expected code=ALREADY_SUBMITTED, didapat: " . ($saveRes['code'] ?? ''));
    }
});

testCase(9, "EXAM", "Wrong student session: akses ke sesi milik siswa lain ditolak", function() use ($exam1Id, $studentId1, $studentId2, $q1Id, $opt1Id, $sessionModel, $answerService, $sessionService) {
    // Sesi milik Student 1
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $student1Token = $session['session_token'];

    // Student 2 mencoba menyimpan jawaban ke sesi Student 1
    $saveRes = $answerService->saveAnswer($student1Token, $studentId2, $q1Id, $opt1Id, null, 0);
    if ($saveRes['success'] || $saveRes['code'] !== 'UNAUTHORIZED') {
        throw new RuntimeException("Student 2 berhasil menyimpan jawaban ke sesi Student 1!");
    }

    // Student 2 mencoba submit sesi Student 1
    $submitRes = $sessionService->submitExam($student1Token, $studentId2);
    if ($submitRes['success']) {
        throw new RuntimeException("Student 2 berhasil submit sesi Student 1!");
    }
});

testCase(10, "EXAM", "Wrong question: pengiriman question_id dari ujian lain ditolak", function() use ($exam1Id, $studentId1, $sessionModel, $answerService, $db) {
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $token = $session['session_token'];

    // Ambil question_id yang dipastikan BUKAN milik exam1
    $stmt = $db->prepare("SELECT id FROM questions WHERE id NOT IN (SELECT question_id FROM exam_questions WHERE exam_id = :eid) AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':eid' => $exam1Id]);
    $foreignQId = (int)$stmt->fetchColumn();
    if ($foreignQId <= 0) {
        $foreignQId = 9999999;
    }

    $saveRes = $answerService->saveAnswer($token, $studentId1, $foreignQId, 1, null, 0);
    if ($saveRes['success']) {
        throw new RuntimeException("Pengiriman soal dari ujian lain berhasil disimpan! Seharusnya ditolak.");
    }
    if ($saveRes['code'] !== 'INVALID_QUESTION') {
        throw new RuntimeException("Expected code=INVALID_QUESTION, didapat: " . ($saveRes['code'] ?? ''));
    }
});

testCase(11, "EXAM", "Wrong option: pengiriman option_id milik soal lain ditolak", function() use ($exam1Id, $studentId1, $q1Id, $sessionModel, $answerService, $db) {
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $token = $session['session_token'];

    // Cari option_id yang bukan milik $q1Id
    $stmt = $db->prepare("SELECT id FROM question_options WHERE question_id != :qid LIMIT 1");
    $stmt->execute([':qid' => $q1Id]);
    $foreignOptId = (int)($stmt->fetchColumn() ?: 999999);

    $saveRes = $answerService->saveAnswer($token, $studentId1, $q1Id, $foreignOptId, null, 0);
    if ($saveRes['success']) {
        throw new RuntimeException("Pilihan jawaban dari soal lain berhasil disimpan! Seharusnya ditolak.");
    }
    if ($saveRes['code'] !== 'INVALID_OPTION') {
        throw new RuntimeException("Expected code=INVALID_OPTION, didapat: " . ($saveRes['code'] ?? ''));
    }
});

testCase(12, "EXAM", "Double submit: operasi submit bersifat idempotent dan aman terhadap double click", function() use ($exam1Id, $studentId1, $sessionModel, $sessionService, $db) {
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $token = $session['session_token'];
    $sessionId = (int)$session['id'];

    // Submit pertama
    $firstSubmit = $sessionService->submitExam($token, $studentId1);
    if (!$firstSubmit['success'] || !empty($firstSubmit['already_submitted'])) {
        throw new RuntimeException("Submit pertama gagal atau salah status: " . ($firstSubmit['error'] ?? ''));
    }

    $firstResultId = $firstSubmit['result_id'] ?? 0;

    // Submit kedua (Double click simulation)
    $secondSubmit = $sessionService->submitExam($token, $studentId1);
    if (!$secondSubmit['success'] || empty($secondSubmit['already_submitted'])) {
        throw new RuntimeException("Submit kedua tidak idempotent: " . json_encode($secondSubmit));
    }

    if (($secondSubmit['result_id'] ?? 0) !== $firstResultId) {
        throw new RuntimeException("Result ID pada submit kedua berubah!");
    }

    // Pastikan tepat 1 baris hasil ujian untuk sesi ini
    $stmt = $db->prepare("SELECT COUNT(*) FROM exam_results WHERE exam_session_id = :sid");
    $stmt->execute([':sid' => $sessionId]);
    $resultCount = (int)$stmt->fetchColumn();
    if ($resultCount !== 1) {
        throw new RuntimeException("Ditemukan duplikasi baris exam_results untuk session {$sessionId}: {$resultCount}");
    }
});

// ----------------------------------------------------------------------
// SECTION 4: GENERAL SECURITY HARDENING (Tests 13-16)
// ----------------------------------------------------------------------
echo "\n--- SECTION 4: GENERAL SECURITY HARDENING ---\n";

testCase(13, "SECURITY", "Destructive operations tidak dapat dipicu melalui HTTP GET", function() {
    $routesFile = file_get_contents(__DIR__ . '/../app/Config/Routes.php');

    // Pola route destruktif yang tidak boleh menggunakan Router::get
    $destructivePatterns = [
        "Router::get('/users/delete",
        "Router::get('/roles/delete",
        "Router::get('/exams/delete",
        "Router::get('/academic-years/delete",
        "Router::get('/semesters/delete",
        "Router::get('/classes/delete",
        "Router::get('/subjects/delete",
        "Router::get('/exams/monitoring/terminate",
        "Router::get('/exams/monitoring/reset-session",
        "Router::get('/users/toggle-status"
    ];

    foreach ($destructivePatterns as $pattern) {
        if (str_contains($routesFile, $pattern)) {
            throw new RuntimeException("Ditemukan rute destruktif menggunakan GET: {$pattern}");
        }
    }
});

testCase(14, "SECURITY", "Malicious HTML payloads dinetralisir dan tidak dapat menjadi executable script (XSS)", function() {
    $payloads = [
        '<script>alert("XSS")</script>',
        '<img src=x onerror=alert(document.cookie)>',
        '<a href="javascript:alert(1)">Klik Saya</a>',
        '<div style="behavior:url(xss.htc)">Div</div>',
        '<iframe src="https://evil.com"></iframe>'
    ];

    foreach ($payloads as $p) {
        $sanitized = sanitize_html($p);
        if (str_contains($sanitized, '<script>') ||
            str_contains($sanitized, 'onerror=') ||
            str_contains($sanitized, 'javascript:') ||
            str_contains($sanitized, '<iframe')) {
            throw new RuntimeException("Sanitizer gagal menetralkan payload: {$p} -> Hasil: {$sanitized}");
        }
    }

    // Format yang diizinkan harus dipertahankan
    $safeHtml = '<p>Soal nomor <b>1</b>: Rumus air adalah H<sub>2</sub>O dan luasnya 10<sup>2</sup> cm.</p>';
    $sanitizedSafe = sanitize_html($safeHtml);
    if (!str_contains($sanitizedSafe, '<b>1</b>') || !str_contains($sanitizedSafe, '<sub>2</sub>')) {
        throw new RuntimeException("Sanitizer merusak HTML formatting yang valid!");
    }
});

testCase(15, "SECURITY", "Login brute force dibatasi secara otomatis (RateLimitFilter)", function() {
    // Pastikan user adalah guest
    Session::remove('user');

    $rateFilter = new RateLimitFilter();
    $loginPath = '/login';
    RateLimitFilter::clearLimit($loginPath, '127.0.0.1', 'guest');

    $request = new Request([], [
        'REQUEST_URI'           => $loginPath,
        'REQUEST_METHOD'        => 'POST',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'REMOTE_ADDR'           => '127.0.0.1'
    ]);

    // 5 request pertama diizinkan (limit: 5,60)
    for ($i = 1; $i <= 5; $i++) {
        $res = $rateFilter->before($request, ['5', '60']);
        if ($res !== null) {
            throw new RuntimeException("Request ke-{$i} diblokir padahal kuota 5 belum habis.");
        }
    }

    // Request ke-6 harus diblokir dengan HTTP 429
    $blockedRes = $rateFilter->before($request, ['5', '60']);
    if (!$blockedRes || $blockedRes->getStatusCode() !== 429) {
        throw new RuntimeException("Request ke-6 tidak diblokir dengan HTTP 429!");
    }

    RateLimitFilter::clearLimit($loginPath, '127.0.0.1', 'guest');
});

testCase(16, "SECURITY", "OTP verification brute force dibatasi secara otomatis (RateLimitFilter)", function() {
    // Pastikan user adalah guest
    Session::remove('user');

    $rateFilter = new RateLimitFilter();
    $otpPath = '/verify-otp';
    RateLimitFilter::clearLimit($otpPath, '127.0.0.1', 'guest');

    $request = new Request([], [
        'REQUEST_URI'           => $otpPath,
        'REQUEST_METHOD'        => 'POST',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'REMOTE_ADDR'           => '127.0.0.1'
    ]);

    // 5 request pertama diizinkan (limit: 5,60)
    for ($i = 1; $i <= 5; $i++) {
        $res = $rateFilter->before($request, ['5', '60']);
        if ($res !== null) {
            throw new RuntimeException("Percobaan OTP ke-{$i} diblokir padahal limit 5 belum habis.");
        }
    }

    // Percobaan ke-6 harus diblokir
    $blockedRes = $rateFilter->before($request, ['5', '60']);
    if (!$blockedRes || $blockedRes->getStatusCode() !== 429) {
        throw new RuntimeException("Percobaan OTP ke-6 tidak diblokir dengan HTTP 429!");
    }

    RateLimitFilter::clearLimit($otpPath, '127.0.0.1', 'guest');
});

// ----------------------------------------------------------------------
// SECTION 5: AI PROCTORING & TELEMETRY HARDENING (Tests 17-19)
// ----------------------------------------------------------------------
echo "\n--- SECTION 5: AI PROCTORING & TELEMETRY ---\n";

testCase(17, "PROCTORING", "Invalid proctoring violation type code ditolak server", function() use ($exam1Id, $studentId1, $sessionModel, $proctoringService, $violationService) {
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $procRes = $proctoringService->startOrGetProctoringSession((int)$session['id'], $studentId1);
    $procSessionId = (int)$procRes['session']['id'];

    $fakeViolationRes = $violationService->recordViolation(
        $procSessionId,
        'FAKE_UNREGISTERED_VIOLATION_TYPE_CODE',
        0.99
    );

    if ($fakeViolationRes['success']) {
        throw new RuntimeException("Tipe pelanggaran tidak valid berhasil dicatat! Seharusnya ditolak.");
    }
    if ($fakeViolationRes['code'] !== 'INVALID_VIOLATION_TYPE') {
        throw new RuntimeException("Expected code=INVALID_VIOLATION_TYPE, didapat: " . ($fakeViolationRes['code'] ?? ''));
    }
});

testCase(18, "PROCTORING", "Non-existent proctoring session ID ditolak server", function() use ($violationService, $proctoringService) {
    $invalidSessionId = 99999999;

    // 1. Uji Violation Service
    $res1 = $violationService->recordViolation($invalidSessionId, 'NO_FACE', 0.95);
    if ($res1['success'] || $res1['code'] !== 'INVALID_SESSION') {
        throw new RuntimeException("ViolationService tidak menolak session ID tidak valid.");
    }

    // 2. Uji Telemetry Log
    $res2 = $proctoringService->logTelemetry($invalidSessionId, ['head_pose_yaw' => 10.0]);
    if ($res2['success'] || $res2['code'] !== 'INVALID_SESSION') {
        throw new RuntimeException("ProctoringService::logTelemetry tidak menolak session ID tidak valid.");
    }
});

testCase(19, "PROCTORING", "Malformed & invalid timestamp dinormalisasi menjadi integer Unix ms yang valid", function() use ($exam1Id, $studentId1, $sessionModel, $proctoringService, $db) {
    $session = $sessionModel->createSession($exam1Id, $studentId1, 60);
    $procRes = $proctoringService->startOrGetProctoringSession((int)$session['id'], $studentId1);
    $procSessionId = (int)$procRes['session']['id'];

    // Kirim telemetri dengan timestamp malformed (string/negatif)
    $telemetryPayload = [
        'timestamp' => 'INVALID_NON_NUMERIC_TIMESTAMP',
        'head_pose_yaw' => 5.5,
        'detected_face_count' => 1
    ];

    $logRes = $proctoringService->logTelemetry($procSessionId, $telemetryPayload);

    // Ambil log terakhir dari database
    $stmt = $db->prepare("SELECT frame_timestamp FROM ai_analysis_logs WHERE proctoring_session_id = :psid ORDER BY id DESC LIMIT 1");
    $stmt->execute([':psid' => $procSessionId]);
    $storedTimestamp = (int)$stmt->fetchColumn();

    // Pastikan tersimpan sebagai integer Unix ms timestamp valid (> 1.000.000.000)
    if ($storedTimestamp <= 1000000000) {
        throw new RuntimeException("frame_timestamp tidak dinormalisasi menjadi valid Unix ms: {$storedTimestamp}");
    }
});

// ----------------------------------------------------------------------
// RESTORE DATA
// ----------------------------------------------------------------------
if (!empty($origSched1) && isset($origSched1['end_time'])) {
    $db->prepare("UPDATE exam_schedules SET end_time = :et WHERE id = :id")
       ->execute([':et' => $origSched1['end_time'], ':id' => $origSched1['id']]);
}

// ----------------------------------------------------------------------
// LAPORAN AKHIR
// ----------------------------------------------------------------------
$totalExecuted = $passCount + $failCount;
echo "\n==============================================================================\n";
echo "RINGKASAN HASIL PENGUJIAN SECURITY REGRESSION:\n";
echo "  - Total Pengujian : {$totalExecuted}\n";
echo "  - Berhasil (PASS) : {$passCount}\n";
echo "  - Gagal (FAIL)    : {$failCount}\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    echo "\nDAFTAR TEST GAGAL:\n";
    foreach ($errors as $err) {
        echo "  [#{$err['num']} - {$err['section']}] {$err['description']}\n";
        echo "    Error: {$err['error']}\n";
        echo "    Lokasi: {$err['file']}\n\n";
    }
    exit(1);
} else {
    echo "\n>>> SELURUH 19 POIN SECURITY REGRESSION TEST SUITE BERHASIL 100% <<<\n";
    exit(0);
}
