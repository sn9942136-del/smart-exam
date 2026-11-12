<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$db = \App\Libraries\Database::getConnection();

echo "=== CHECKING CURRENT USERS IN DATABASE ===\n";
$stmt = $db->query("SELECT u.id, u.name, u.email, u.username, u.password, u.status, r.name as role_name FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$passwordsToTest = [
    'Siswa123!',
    'Guru123!',
    'Admin123!',
    'SuperAdmin123!',
    'password',
    'siswa123',
    'guru123',
    'admin123',
    '123456',
    '12345678'
];

foreach ($users as $u) {
    $matched = 'NO MATCH';
    foreach ($passwordsToTest as $p) {
        if (password_verify($p, $u['password'])) {
            $matched = $p;
            break;
        }
    }
    echo "ID: {$u['id']} | Name: {$u['name']} | Email: {$u['email']} | User: {$u['username']} | Role: {$u['role_name']} | Status: {$u['status']} | Password: [{$matched}]\n";
}
