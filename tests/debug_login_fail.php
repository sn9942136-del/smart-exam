<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$authService = new \App\Services\AuthService();
$db = \App\Libraries\Database::getConnection();

$guruUser = $db->query("SELECT * FROM users WHERE email = 'guru@example.com'")->fetch(PDO::FETCH_ASSOC);
echo "Guru DB record:\n" . print_r($guruUser, true) . PHP_EOL;

$guruVerify = password_verify('Guru123!', $guruUser['password'] ?? '');
echo "Direct password_verify('Guru123!'): " . ($guruVerify ? 'TRUE' : 'FALSE') . PHP_EOL;

$resGuru = $authService->attempt('guru@example.com', 'Guru123!');
echo "AuthService Guru result:\n" . print_r($resGuru, true) . PHP_EOL;

$siswaUser = $db->query("SELECT * FROM users WHERE email = 'siswa@example.com'")->fetch(PDO::FETCH_ASSOC);
echo "Siswa DB record:\n" . print_r($siswaUser, true) . PHP_EOL;

$siswaVerify = password_verify('Siswa123!', $siswaUser['password'] ?? '');
echo "Direct password_verify('Siswa123!'): " . ($siswaVerify ? 'TRUE' : 'FALSE') . PHP_EOL;

$resSiswa = $authService->attempt('siswa@example.com', 'Siswa123!');
echo "AuthService Siswa result:\n" . print_r($resSiswa, true) . PHP_EOL;
