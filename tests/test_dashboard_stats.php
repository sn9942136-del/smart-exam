<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\UserModel;
use App\Models\RoleModel;
use App\Controllers\AdminDashboardController;
use App\Libraries\Auth;

echo "=== TEST DASHBOARD STATS & BASEMODEL NULL HANDLING ===" . PHP_EOL;

$db = Database::getConnection();

// 1. Direct query
$directCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
echo "1. Direct SQL (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL): {$directCount}" . PHP_EOL;

// 2. UserModel / BaseModel count with ['deleted_at' => null]
$userModel = new UserModel();
$modelCount = $userModel->count(['deleted_at' => null]);
echo "2. BaseModel count(['deleted_at' => null]): {$modelCount}" . PHP_EOL;

if ($directCount === $modelCount && $modelCount > 0) {
    echo " [PASS] BaseModel::count(['deleted_at' => null]) correctly produces IS NULL and matches SQL count!" . PHP_EOL;
} else {
    echo " [FAIL] Mismatch or 0: Direct={$directCount}, Model={$modelCount}" . PHP_EOL;
    exit(1);
}

// 3. Admin dashboard controller output
$admin = $userModel->findByEmail('admin@gmail.com') ?: $userModel->findByEmail('superadmin@gmail.com');
$roles = $userModel->getRoles($admin['id']);
$admin['role'] = !empty($roles) ? $roles[0]['name'] : 'admin';
$admin['roles'] = !empty($roles) ? array_column($roles, 'name') : ['admin'];
login_user($admin);

$controller = new AdminDashboardController();
$response = $controller->index();
$body = $response->getContent();

// Verify total users rendered
preg_match('/Total Pengguna<\/span>[\s\S]*?<h3[^>]*>(\d+)<\/h3>/', $body, $matches);
$renderedTotal = isset($matches[1]) ? (int)$matches[1] : null;

echo "3. Rendered Total Pengguna on Admin Dashboard: {$renderedTotal}" . PHP_EOL;

if ($renderedTotal === $directCount) {
    echo " [PASS] Dashboard view renders exact user count ({$renderedTotal})!" . PHP_EOL;
} else {
    echo " [FAIL] Dashboard view rendered ({$renderedTotal}) does not match direct count ({$directCount})!" . PHP_EOL;
    exit(1);
}

// Check role breakdown
preg_match('/(\d+)\s+Guru/', $body, $guruMatches);
preg_match('/(\d+)\s+Siswa/', $body, $siswaMatches);
preg_match('/(\d+)\s+Admin/', $body, $adminMatches);

$guruCount = isset($guruMatches[1]) ? (int)$guruMatches[1] : 0;
$siswaCount = isset($siswaMatches[1]) ? (int)$siswaMatches[1] : 0;
$adminCount = isset($adminMatches[1]) ? (int)$adminMatches[1] : 0;

echo "4. Role breakdown on dashboard: {$guruCount} Guru, {$siswaCount} Siswa, {$adminCount} Admin" . PHP_EOL;

if ($guruCount > 0 && $siswaCount > 0) {
    echo " [PASS] Role breakdown correctly displayed!" . PHP_EOL;
} else {
    echo " [FAIL] Role breakdown missing!" . PHP_EOL;
    exit(1);
}

echo "=== ALL DASHBOARD STATS TESTS PASSED ===" . PHP_EOL;
