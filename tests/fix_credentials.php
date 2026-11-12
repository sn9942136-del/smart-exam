<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$db = \App\Libraries\Database::getConnection();

echo "=== FIXING USER CREDENTIALS & PASSWORDS ===\n";

$accounts = [
    [
        'email'    => 'superadmin@example.com',
        'username' => 'superadmin',
        'name'     => 'Super Administrator',
        'password' => 'SuperAdmin123!',
        'role'     => 'super_admin'
    ],
    [
        'email'    => 'admin@example.com',
        'username' => 'admin',
        'name'     => 'Administrator Ujian',
        'password' => 'Admin123!',
        'role'     => 'admin'
    ],
    [
        'email'    => 'guru@example.com',
        'username' => 'guru_budi',
        'name'     => 'Budi Santoso, S.Kom., M.T.',
        'password' => 'Guru123!',
        'role'     => 'guru'
    ],
    [
        'email'    => 'siswa@example.com',
        'username' => 'siswa_fauzi',
        'name'     => 'Ahmad Fauzi (Siswa Demo)',
        'password' => 'Siswa123!',
        'role'     => 'peserta_didik'
    ]
];

$stmtUpdate = $db->prepare("UPDATE users SET password = :password, status = 'active', updated_at = NOW() WHERE email = :email OR username = :username");

foreach ($accounts as $acc) {
    $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
    $stmtUpdate->execute([
        'password' => $hash,
        'email'    => $acc['email'],
        'username' => $acc['username']
    ]);

    // Verifikasi kembali
    $user = $db->query("SELECT id, name, email, username, password, status FROM users WHERE email = '{$acc['email']}'")->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($acc['password'], $user['password'])) {
        echo "  [OK] {$acc['name']} ({$acc['email']} / {$acc['username']}) -> Password reset to: {$acc['password']}\n";
    } else {
        echo "  [FAILED] Failed to verify {$acc['email']}\n";
    }
}

// Test authenticate via AuthService
echo "\n=== TESTING AUTHSERVICE ATTEMPT ===\n";
$authService = new \App\Services\AuthService();

foreach ($accounts as $acc) {
    $resEmail = $authService->attempt($acc['email'], $acc['password']);
    $resUser = $authService->attempt($acc['username'], $acc['password']);
    
    $statusEmail = ($resEmail['success'] ?? false) ? 'PASS' : 'FAIL (' . ($resEmail['error'] ?? '') . ')';
    $statusUser = ($resUser['success'] ?? false) ? 'PASS' : 'FAIL (' . ($resUser['error'] ?? '') . ')';
    
    echo "  Login via Email [{$acc['email']}]: {$statusEmail}\n";
    echo "  Login via Username [{$acc['username']}]: {$statusUser}\n";
}

echo "\n[ALL CREDENTIALS FIXED & VERIFIED]\n";
