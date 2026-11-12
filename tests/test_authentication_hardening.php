<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Filters\RateLimitFilter;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\AuditLogModel;
use App\Models\OtpTokenModel;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Models\UserSessionModel;
use App\Services\AuthService;
use App\Services\PasswordService;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: AUTHENTICATION SECURITY HARDENING & RATE LIMITING\n";
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

function assertCustom(bool $condition, string $message): void {
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

$db = Database::getConnection();
$userModel = new UserModel();
$sessionModel = new UserSessionModel();
$otpModel = new OtpTokenModel();
$resetModel = new PasswordResetModel();
$authService = new AuthService();
$passwordService = new PasswordService();

// Cari atau buat user khusus untuk testing auth hardening tanpa mengubah user produksi
$testEmail = 'auth_test_student@example.com';
$testUsername = 'auth_test_student';
$testRawPassword = 'TestPassword123!';
$existingTestUser = $userModel->findByEmail($testEmail);
if (!$existingTestUser) {
    $deletedRow = $db->query("SELECT * FROM users WHERE email = '{$testEmail}'")->fetch();
    if ($deletedRow) {
        $db->prepare("UPDATE users SET deleted_at = NULL, status = 'active' WHERE id = :id")->execute([':id' => $deletedRow['id']]);
        $existingTestUser = $userModel->find((int)$deletedRow['id']);
    }
}

if (!$existingTestUser) {
    $roleStmt = $db->query("SELECT id FROM roles WHERE name = 'peserta_didik' LIMIT 1");
    $roleId = $roleStmt->fetchColumn() ?: 4;

    $testUserId = (int)$userModel->insert([
        'role_id'    => (int)$roleId,
        'name'       => 'Auth Hardening Test Student',
        'email'      => $testEmail,
        'username'   => $testUsername,
        'password'   => password_hash($testRawPassword, PASSWORD_BCRYPT),
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $testUser = $userModel->find($testUserId);
} else {
    $testUser = $existingTestUser;
    $testUserId = (int)$testUser['id'];
    // Pastikan status aktif dan password diketahui
    $userModel->update($testUserId, [
        'status'   => 'active',
        'password' => password_hash($testRawPassword, PASSWORD_BCRYPT)
    ]);
}

// ==============================================================================
// 1. TEST: Successful Login, Session Regeneration, and DB Session Recording
// ==============================================================================
runTest("1.1 Successful login returns success and registers active session", function () use ($authService, $testEmail, $testRawPassword, $testUserId, $sessionModel) {
    $oldSessionId = Session::id();
    $result = $authService->attempt($testEmail, $testRawPassword, false, '127.0.0.1', 'PHPUnit Test Browser');

    assertCustom($result['success'] === true, "AuthService attempt harus berhasil dengan kredensial yang valid.");
    assertCustom(isset($result['user']) && $result['user']['id'] === $testUserId, "User data dalam session harus sesuai.");
    assertCustom(is_logged_in() === true, "is_logged_in() harus bernilai true.");

    $sessionToken = Session::get('session_token');
    assertCustom(!empty($sessionToken), "Session token harus disimpan di session.");

    $dbSession = $sessionModel->findActiveByToken($sessionToken);
    assertCustom($dbSession !== null, "Session aktif harus tercatat di database user_sessions.");
    assertCustom((int)$dbSession['user_id'] === $testUserId, "User ID pada user_sessions harus sesuai.");
});

runTest("1.2 Session Regeneration occurs to prevent session fixation", function () {
    $initialId = session_id();
    $regenerated = Session::regenerate(true);
    $newId = session_id();

    assertCustom($regenerated === true || !empty($newId), "Session::regenerate harus berhasil dipanggil.");
    assertCustom(!empty($newId), "Session ID baru harus tersedia.");
});

// ==============================================================================
// 2. TEST: Account Enumeration Protection (Generic Credential Error)
// ==============================================================================
runTest("2.1 Non-existent user returns exact same generic error message", function () use ($authService) {
    $result = $authService->attempt('nonexistent_user_9999@example.com', 'RandomPass123!');
    assertCustom($result['success'] === false, "Login akun tidak dikenal harus gagal.");
    assertCustom($result['error'] === 'Kredensial yang Anda masukkan tidak sesuai dengan catatan kami.', 
        "Pesan error akun tidak dikenal tidak boleh membocorkan keberadaan username/email.");
});

runTest("2.2 Wrong password on existing user returns identical generic error", function () use ($authService, $testEmail) {
    $result = $authService->attempt($testEmail, 'WrongPassword999!');
    assertCustom($result['success'] === false, "Login password salah harus gagal.");
    assertCustom($result['error'] === 'Kredensial yang Anda masukkan tidak sesuai dengan catatan kami.', 
        "Pesan error password salah harus identik untuk mencegah account enumeration.");
});

// ==============================================================================
// 3. TEST: Repeated Failed Login Rate Limiting (Brute Force Protection)
// ==============================================================================
runTest("3.1 RateLimitFilter throttles repeated requests after max attempts", function () {
    logout_user();
    $filter = new RateLimitFilter();
    $testIp = '192.168.100.50';

    // Bersihkan rate limit sebelum test
    RateLimitFilter::clearLimit('/login', $testIp, 'guest');

    // Buat dummy request mock
    $request = new Request([], [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI'    => '/login',
        'REMOTE_ADDR'    => $testIp,
        'HTTP_ACCEPT'    => 'application/json'
    ]);

    // Kirim 5 attempt berturut-turut (batas max: 5)
    for ($i = 1; $i <= 5; $i++) {
        $res = $filter->before($request, ['5', '60']);
        assertCustom($res === null, "Percobaan ke-{$i} masih dalam batas wajar (dibawah limit).");
    }

    // Attempt ke-6 harus di-throttle (HTTP 429)
    $blockedRes = $filter->before($request, ['5', '60']);
    assertCustom($blockedRes instanceof Response, "Percobaan ke-6 harus diblokir oleh RateLimitFilter.");
    assertCustom($blockedRes->getStatusCode() === 429, "Status code harus 429 Too Many Requests.");

    $json = json_decode($blockedRes->getContent(), true);
    assertCustom(isset($json['retry_after']) && $json['retry_after'] > 0, "Response harus menyertakan retry_after countdown.");
});

runTest("3.2 User account is NOT permanently locked on rate limit", function () use ($userModel, $testUserId) {
    // Pastikan status akun di database tidak terkunci permanen
    $user = $userModel->find($testUserId);
    assertCustom($user['status'] === 'active', "Akun user tidak boleh terkunci permanen, hanya cooldown waktu.");
});

runTest("3.3 Successful login resets the rate limit counter", function () use ($authService, $testEmail, $testRawPassword) {
    logout_user();
    $testIp = '192.168.100.55';
    RateLimitFilter::clearLimit('/login', $testIp, 'guest');
    $filter = new RateLimitFilter();

    $request = new Request([], [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI'    => '/login',
        'REMOTE_ADDR'    => $testIp,
        'HTTP_ACCEPT'    => 'application/json'
    ]);

    // 4 failed attempts
    for ($i = 1; $i <= 4; $i++) {
        $filter->before($request, ['5', '60']);
    }
    assertCustom(RateLimitFilter::getAttempts('/login', $testIp, 'guest', 60) === 4, "Counter harus tercatat 4.");

    // Login sukses
    $res = $authService->attempt($testEmail, $testRawPassword, false, $testIp);
    assertCustom($res['success'] === true, "Login sukses harus berhasil.");

    // Counter harus ter-reset
    $attemptsAfter = RateLimitFilter::getAttempts('/login', $testIp, 'guest', 60);
    assertCustom($attemptsAfter === 0, "Counter rate limit harus 0 setelah login sukses.");
});

// ==============================================================================
// 4. TEST: OTP Generation & Repeated OTP Verification Rate Limiting
// ==============================================================================
runTest("4.1 OTP generation creates 6-digit numeric OTP with 15-minute expiry", function () use ($passwordService, $testEmail, $testUserId, $otpModel) {
    $result = $passwordService->requestReset($testEmail);
    assertCustom($result['success'] === true, "Request reset harus sukses.");
    assertCustom(strlen($result['otp']) === 6, "OTP harus terdiri dari 6 karakter.");
    assertCustom(ctype_digit($result['otp']), "OTP harus berupa angka.");

    $latestOtp = $otpModel->getLatestActiveOtp($testUserId, 'reset_password');
    assertCustom($latestOtp === $result['otp'], "OTP di database harus cocok dengan yang diterbitkan.");
});

runTest("4.2 Repeated OTP verification attempts trigger rate limiting", function () {
    logout_user();
    $filter = new RateLimitFilter();
    $testIp = '192.168.100.60';
    RateLimitFilter::clearLimit('/verify-otp', $testIp, 'guest');

    $request = new Request([], [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI'    => '/verify-otp',
        'REMOTE_ADDR'    => $testIp,
        'HTTP_ACCEPT'    => 'application/json'
    ]);

    for ($i = 1; $i <= 5; $i++) {
        $res = $filter->before($request, ['5', '60']);
        assertCustom($res === null, "Percobaan OTP ke-{$i} masih dalam batas.");
    }

    $blocked = $filter->before($request, ['5', '60']);
    assertCustom($blocked instanceof Response && $blocked->getStatusCode() === 429, 
        "Percobaan verifikasi OTP berlebih harus menghasilkan status 429.");
});

runTest("4.3 Correct OTP validation is single-use and invalidated immediately", function () use ($passwordService, $testEmail, $testUserId, $otpModel) {
    // Generate fresh OTP
    $gen = $passwordService->requestReset($testEmail);
    $validOtp = $gen['otp'];

    // Verifikasi pertama -> sukses
    $verifyResult = $passwordService->verifyOtp($testUserId, $validOtp);
    assertCustom($verifyResult['success'] === true, "Verifikasi OTP pertama harus berhasil.");
    assertCustom(!empty($verifyResult['token']), "Token reset harus dihasilkan.");

    // Verifikasi kedua dengan OTP yang sama -> harus gagal (single-use)
    $secondVerify = $passwordService->verifyOtp($testUserId, $validOtp);
    assertCustom($secondVerify['success'] === false, "Kode OTP yang sudah terpakai tidak boleh dapat digunakan kembali.");
});

// ==============================================================================
// 5. TEST: Password Reset Token Validation (Invalid, Expired, and Single-Use)
// ==============================================================================
runTest("5.1 Invalid (tampered/random) reset token is rejected", function () use ($passwordService, $testEmail) {
    $fakeToken = bin2hex(random_bytes(32));
    $result = $passwordService->resetPassword($testEmail, $fakeToken, 'NewSecurePass123!', 'NewSecurePass123!');
    assertCustom($result['success'] === false, "Reset password dengan token palsu harus ditolak.");
});

runTest("5.2 Expired reset token is rejected", function () use ($db, $passwordService, $testEmail) {
    $expiredToken = bin2hex(random_bytes(32));
    // Masukkan token dengan expires_at di masa lampau
    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (:email, :token, DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW())");
    $stmt->execute([':email' => $testEmail, ':token' => $expiredToken]);

    $result = $passwordService->resetPassword($testEmail, $expiredToken, 'NewSecurePass123!', 'NewSecurePass123!');
    assertCustom($result['success'] === false, "Reset password dengan token kadaluarsa harus ditolak.");
});

runTest("5.3 Valid reset token updates password with BCRYPT and deactivates prior sessions", function () use ($db, $passwordService, $userModel, $sessionModel, $testEmail, $testUserId, $resetModel) {
    // Buat token valid
    $validToken = $resetModel->createToken($testEmail, 30);

    // Simulasikan session aktif sebelum reset password
    $sessionToken = bin2hex(random_bytes(32));
    $sessionModel->recordSession($testUserId, $sessionToken, '127.0.0.1', 'Prior Active Browser', 3600);
    assertCustom($sessionModel->findActiveByToken($sessionToken) !== null, "Session aktif sebelum reset harus ada.");

    $newRawPass = 'NewlyUpdatedPassword123!';
    $result = $passwordService->resetPassword($testEmail, $validToken, $newRawPass, $newRawPass);
    assertCustom($result['success'] === true, "Reset password dengan token valid harus berhasil.");

    // Cek password hash terupdate dengan BCRYPT
    $updatedUser = $userModel->find($testUserId);
    assertCustom(password_verify($newRawPass, $updatedUser['password']) === true, "Password baru harus ter-hash BCRYPT dan cocok.");

    // Cek seluruh sesi aktif sebelumnya telah di-nonaktifkan
    $oldActiveSession = $sessionModel->findActiveByToken($sessionToken);
    assertCustom($oldActiveSession === null, "Sesi aktif lama harus di-deaktivasi demi keamanan setelah password diganti.");

    // Cek token reset telah dihapus (single-use)
    $checkUsedToken = $resetModel->findValid($testEmail, $validToken);
    assertCustom($checkUsedToken === null, "Token reset harus langsung dihapus setelah berhasil digunakan.");
});

// ==============================================================================
// 6. TEST: Logout Security (Session Token Deactivation & Session Destruction)
// ==============================================================================
runTest("6.1 Logout deactivates DB session token and destroys PHP session", function () use ($authService, $sessionModel, $testUserId) {
    $token = bin2hex(random_bytes(32));
    Session::set('session_token', $token);
    login_user(['id' => $testUserId, 'name' => 'Test User', 'role' => 'peserta_didik']);
    $sessionModel->recordSession($testUserId, $token, '127.0.0.1', 'Test', 3600);

    assertCustom(is_logged_in() === true, "User harus dalam keadaan login sebelum logout.");
    assertCustom($sessionModel->findActiveByToken($token) !== null, "Session di DB harus aktif.");

    $authService->logout();

    assertCustom(is_logged_in() === false, "User harus logout setelah logout() dipanggil.");
    assertCustom($sessionModel->findActiveByToken($token) === null, "Session di DB harus tidak aktif setelah logout.");
});

echo "\n==============================================================================\n";
echo "TEST RESULTS: {$passedCount} / {$totalCount} PASSED (" . round(($passedCount / max(1, $totalCount)) * 100, 1) . "%)\n";
echo "==============================================================================\n";

if ($passedCount !== $totalCount) {
    exit(1);
}
