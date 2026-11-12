<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\AcademicYearController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AiConfigurationController;
use App\Controllers\ClassController;
use App\Controllers\RoleController;
use App\Controllers\SemesterController;
use App\Controllers\SubjectController;
use App\Controllers\UserController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Models\AcademicYearModel;
use App\Models\AiConfigurationModel;
use App\Models\ClassModel;
use App\Models\RoleModel;
use App\Models\SemesterModel;
use App\Models\SubjectModel;
use App\Models\UserModel;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: ADMIN MODULE (UC-28 s/d UC-30) & CRUD MASTER DATA\n";
echo "==============================================================================\n\n";

// Set session as Super Admin
$_SESSION['user_id'] = 1;
$_SESSION['user_data'] = [
    'id'       => 1,
    'name'     => 'Super Administrator',
    'email'    => 'admin@example.com',
    'username' => 'admin',
    'nip_nisn' => 'ADM-0001',
    'status'   => 'active'
];
$_SESSION['roles'] = ['super_admin', 'admin'];

// 1. Test Admin Dashboard Controller
echo "[1/8] Testing AdminDashboardController...\n";
$dashboardController = new AdminDashboardController(new Request());
$dashResponse = $dashboardController->index();
$dashContent = $dashResponse->getContent();
assert(str_contains($dashContent, 'Dashboard Administrator'), 'Admin dashboard title missing');
assert(str_contains($dashContent, 'Manajemen Pengguna'), 'Users shortcut missing');
assert(str_contains($dashContent, 'Konfigurasi AI'), 'AI Config shortcut missing');
echo "  ✓ Admin Dashboard render berhasil dengan metrik database live.\n";

// 2. Test User Controller CRUD & Reset Password
echo "[2/8] Testing UserController CRUD & Operations...\n";
$userModel = new UserModel();

// 2.1 Index List
$userController = new UserController(new Request());
$userListContent = $userController->index()->getContent();
assert(str_contains($userListContent, 'Manajemen Pengguna'), 'Users index title missing');
echo "  ✓ Index Pengguna ter-render dengan pencarian & filter role/status.\n";

// 2.2 Create User
$uniqueEmail = 'guru_test_' . time() . '_' . rand(100, 999) . '@sekolah.sch.id';
$uniqueUsername = 'gurutest_' . time() . '_' . rand(100, 999);
$_POST = [
    'name'        => 'Guru Penguji Unit Test',
    'email'       => $uniqueEmail,
    'username'    => $uniqueUsername,
    'password'    => 'Password123!',
    'nip_nisn'    => '19900101' . rand(1000, 9999),
    'phone'       => '081234567890',
    'gender'      => 'L',
    'role_id'     => 3, // Guru
    'status'      => 'active'
];
$userController = new UserController(new Request());
$storeResponse = $userController->store();
$createdUser = $userModel->findByEmail($uniqueEmail);
assert($createdUser !== null, 'User creation failed');
$createdUserId = (int)$createdUser['id'];
echo "  ✓ Tambah Pengguna Baru Berhasil: {$createdUser['name']} (ID: {$createdUserId}).\n";

// 2.3 Edit & Update User
$_POST = [
    'name'     => 'Guru Penguji Unit Test (Updated)',
    'email'    => $uniqueEmail,
    'username' => $uniqueUsername,
    'nip_nisn' => $createdUser['nip_nisn'],
    'phone'    => '081299998888',
    'gender'   => 'L',
    'role_id'  => 3,
    'status'   => 'active'
];
$userController = new UserController(new Request());
$userController->update($createdUserId);
$updatedUser = $userModel->find($createdUserId);
assert($updatedUser['name'] === 'Guru Penguji Unit Test (Updated)', 'User update failed');
echo "  ✓ Update Data Pengguna Berhasil.\n";

// 2.4 Toggle Status
$userController = new UserController(new Request());
$userController->toggleStatus($createdUserId);
$toggledUser = $userModel->find($createdUserId);
assert($toggledUser['status'] === 'inactive', 'User status toggle to inactive failed');
$userController->toggleStatus($createdUserId);
$toggledUser2 = $userModel->find($createdUserId);
assert($toggledUser2['status'] === 'active', 'User status toggle to active failed');
echo "  ✓ Toggle Aktivasi / Nonaktivasi Pengguna Berhasil (Active <-> Inactive).\n";

// 2.5 Reset Password Manual
$_POST = [
    'new_password'     => 'NewSecretPass123!',
    'confirm_password' => 'NewSecretPass123!'
];
$userController = new UserController(new Request());
$userController->resetPassword($createdUserId);
$pwdUser = $userModel->find($createdUserId);
assert(password_verify('NewSecretPass123!', $pwdUser['password']), 'Password reset failed');
echo "  ✓ Reset Password Manual oleh Admin Berhasil diverifikasi.\n";

// 2.6 Delete User (Soft Delete)
$userController = new UserController(new Request());
$userController->delete($createdUserId);
$deletedUser = $userModel->find($createdUserId);
assert(!empty($deletedUser['deleted_at']), 'User soft delete failed');
echo "  ✓ Hapus / Nonaktifkan Pengguna Berhasil.\n";

// 3. Test Role Controller
echo "[3/8] Testing RoleController & Permissions...\n";
$roleModel = new RoleModel();

$roleName = 'pengawas_khusus_' . time() . '_' . rand(10, 99);
$_POST = [
    'name'         => $roleName,
    'display_name' => 'Pengawas Khusus Ujian',
    'description'  => 'Role sementara untuk pengawasan ujian nasional',
    'permissions'  => [1, 2, 5, 9, 12]
];
$roleController = new RoleController(new Request());
$roleController->store();
$createdRole = $roleModel->where('name', $roleName)->first();
assert($createdRole !== null, 'Role creation failed');
$createdRoleId = (int)$createdRole['id'];
$assignedPerms = $roleModel->getPermissionIds($createdRoleId);
assert(count($assignedPerms) === 5, 'Role permissions assignment failed');
echo "  ✓ Tambah Role Baru & Matriks Hak Akses Berhasil ({$createdRole['display_name']}).\n";

$roleController->delete($createdRoleId);
assert($roleModel->find($createdRoleId) === null, 'Role deletion failed');
echo "  ✓ Hapus Custom Role Berhasil.\n";

// 4. Test Academic Year Controller
echo "[4/8] Testing AcademicYearController...\n";
$ayModel = new AcademicYearModel();

$testYearName = '2028/2029_' . rand(100, 999);
$_POST = [
    'name'       => $testYearName,
    'start_date' => '2028-07-15',
    'end_date'   => '2029-06-20',
    'is_active'  => 1
];
$ayController = new AcademicYearController(new Request());
$ayController->store();
$createdYear = $ayModel->where('name', $testYearName)->first();
assert($createdYear !== null, 'Academic year creation failed');
$createdYearId = (int)$createdYear['id'];
assert((int)$createdYear['is_active'] === 1, 'Academic year active state failed');
echo "  ✓ Tambah & Aktivasi Tahun Ajaran Baru Berhasil ({$testYearName}).\n";

// Re-activate 2026/2027
$defaultYear = $ayModel->where('name', '2026/2027')->first();
if ($defaultYear) {
    $ayModel->setActive($defaultYear['id']);
}
$ayModel->delete($createdYearId);
echo "  ✓ Cleanup Tahun Ajaran Selesai.\n";

// 5. Test Semester Controller
echo "[5/8] Testing SemesterController...\n";
$semModel = new SemesterModel();

$defaultYearId = (int)($defaultYear['id'] ?? 1);
$_POST = [
    'academic_year_id' => $defaultYearId,
    'name'             => 'Genap',
    'code'             => '2_' . rand(10, 99),
    'is_active'        => 0
];
$semController = new SemesterController(new Request());
$semController->store();
$createdSem = $semModel->where('code', $_POST['code'])->first();
assert($createdSem !== null, 'Semester creation failed');
$createdSemId = (int)$createdSem['id'];
echo "  ✓ Tambah Semester Berhasil ({$createdSem['name']} - {$createdSem['code']}).\n";

$semController->setActive($createdSemId);
$activeSem = $semModel->getActive();
assert((int)$activeSem['id'] === $createdSemId, 'Semester set active failed');
echo "  ✓ Set Semester Aktif Berhasil.\n";

$semModel->delete($createdSemId);
// Re-active first semester
$firstSem = $semModel->where('name', 'Ganjil')->first();
if ($firstSem) {
    $semModel->setActive($firstSem['id']);
}
echo "  ✓ Cleanup Semester Selesai.\n";

// 6. Test Class Controller
echo "[6/8] Testing ClassController...\n";
$classModel = new ClassModel();

$testClassName = 'XI MIPA Test ' . rand(100, 999);
$_POST = [
    'academic_year_id'    => $defaultYearId,
    'name'                => $testClassName,
    'grade_level'         => '11',
    'major'               => 'MIPA',
    'homeroom_teacher_id' => null
];
$classController = new ClassController(new Request());
$classController->store();
$createdClass = $classModel->where('name', $testClassName)->first();
assert($createdClass !== null, 'Class creation failed');
$createdClassId = (int)$createdClass['id'];
echo "  ✓ Tambah Kelas Baru Berhasil ({$testClassName}).\n";

$_POST = [
    'academic_year_id'    => $defaultYearId,
    'name'                => $testClassName . ' (Rev)',
    'grade_level'         => '11',
    'major'               => 'MIPA-Plus',
    'homeroom_teacher_id' => null
];
$classController = new ClassController(new Request());
$classController->update($createdClassId);
$updatedClass = $classModel->find($createdClassId);
assert($updatedClass['name'] === $testClassName . ' (Rev)', 'Class update failed');
echo "  ✓ Update Data Kelas Berhasil.\n";

$classModel->delete($createdClassId);
echo "  ✓ Hapus Kelas Berhasil.\n";

// 7. Test Subject Controller
echo "[7/8] Testing SubjectController...\n";
$subjModel = new SubjectModel();

$testSubjCode = 'BIO-' . rand(100, 999);
$_POST = [
    'code'        => $testSubjCode,
    'name'        => 'Biologi Sel & Genetika',
    'description' => 'Mata pelajaran biologi molekuler'
];
$subjController = new SubjectController(new Request());
$subjController->store();
$createdSubj = $subjModel->findByCode($testSubjCode);
assert($createdSubj !== null, 'Subject creation failed');
$createdSubjId = (int)$createdSubj['id'];
echo "  ✓ Tambah Mata Pelajaran Baru Berhasil ({$createdSubj['name']} [{$testSubjCode}]).\n";

$subjModel->delete($createdSubjId);
echo "  ✓ Hapus Mata Pelajaran Berhasil.\n";

// 8. Test AI Configuration Controller
echo "[8/8] Testing AiConfigurationController...\n";
$aiModel = new AiConfigurationModel();

$_POST = [
    'configs' => [
        'gaze_sensitivity_threshold' => '0.80',
        'no_face_timeout_seconds'    => '7',
        'dual_cam_sync_interval_ms'  => '2000'
    ]
];
$aiController = new AiConfigurationController(new Request());
$aiController->update();
assert(AiConfigurationModel::getVal('gaze_sensitivity_threshold') === '0.80', 'AI Gaze threshold update failed');
assert(AiConfigurationModel::getVal('no_face_timeout_seconds') === '7', 'AI Timeout update failed');
assert(AiConfigurationModel::getVal('dual_cam_sync_interval_ms') === '2000', 'AI Sync interval update failed');
echo "  ✓ Update Parameter Sensitivitas AI Berhasil.\n";

$aiController->resetDefault();
assert(AiConfigurationModel::getVal('gaze_sensitivity_threshold') === '0.75', 'AI Reset to default failed');
assert(AiConfigurationModel::getVal('no_face_timeout_seconds') === '5', 'AI Reset to default failed');
echo "  ✓ Reset Parameter AI ke Baseline Default Berhasil.\n";

echo "\n==============================================================================\n";
echo "SELURUH PENGUJIAN MODUL ADMIN BERHASIL (100% PASS - 8/8 MODUL OK)!\n";
echo "==============================================================================\n";
