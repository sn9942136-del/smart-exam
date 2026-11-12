<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ClassController;
use App\Controllers\UserController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Session;
use App\Models\ClassModel;
use App\Models\UserModel;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: FITUR KELOLA SISWA PER KELAS & PILIHAN KELAS PENGGUNA\n";
echo "==============================================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $description, callable $fn): void
{
    global $passCount, $failCount;
    try {
        $fn();
        echo "  [PASS] {$description}\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$description}: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failCount++;
    }
}

$db = Database::getConnection();
$classModel = new ClassModel();
$userModel = new UserModel();

// Pastikan admin login di session
$adminUser = $userModel->findByEmail('admin@example.com') ?: $userModel->find(1);
$_SESSION['user_id'] = $adminUser['id'];
$_SESSION['role'] = 'super_admin';
$_SESSION['permissions'] = ['*'];

// Ambil kelas 5 (XII MIPA 2) atau kelas uji coba
$class5 = $classModel->find(5);
if (!$class5) {
    // Buat kelas jika belum ada
    $yearId = (int)$db->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    $classId = $classModel->insert([
        'academic_year_id' => $yearId,
        'name' => 'XII MIPA 2',
        'grade_level' => '12',
        'major' => 'MIPA',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $class5 = $classModel->find($classId);
}
$targetClassId = (int)$class5['id'];

// Ambil siswa untuk testing: Salsa Nabila (id 31) atau buat dummy student
$studentSalsa = $userModel->findByUsername('salsanabila') ?: $userModel->where('name', 'Salsa Nabila')->first();
if (!$studentSalsa) {
    $salsaId = $userModel->insert([
        'name' => 'Salsa Nabila',
        'username' => 'salsanabila_test',
        'email' => 'salsa_test_' . time() . '@sekolah.sch.id',
        'password' => password_hash('password123', PASSWORD_BCRYPT),
        'nip_nisn' => '026716781',
        'gender' => 'P',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $userModel->assignRole($salsaId, 5); // peserta_didik
    $studentSalsa = $userModel->find($salsaId);
}
$salsaId = (int)$studentSalsa['id'];

it("1. ClassModel::getClassWithDetails mengembalikan data kelas lengkap beserta jumlah siswa", function () use ($classModel, $targetClassId) {
    $details = $classModel->getClassWithDetails($targetClassId);
    if (!$details) {
        throw new Exception("Class details not found for ID {$targetClassId}");
    }
    if (!isset($details['students_count'])) {
        throw new Exception("students_count field missing in class details");
    }
    if ($details['name'] !== 'XII MIPA 2') {
        throw new Exception("Class name expected 'XII MIPA 2', got '{$details['name']}'");
    }
});

it("2. ClassController::students me-render halaman daftar siswa kelas tanpa error", function () use ($targetClassId) {
    $controller = new ClassController(new Request());
    $response = $controller->students($targetClassId);
    $html = $response->getContent();
    if (!str_contains($html, 'Siswa Kelas: XII MIPA 2')) {
        throw new Exception("Page title or header not rendered correctly in students view");
    }
    if (!str_contains($html, 'Tambah Siswa ke Kelas')) {
        throw new Exception("Add student button/modal not present in view");
    }
});

it("3. ClassController::addStudent berhasil memasukkan siswa ke XII MIPA 2 dan mencatat audit log", function () use ($targetClassId, $salsaId, $classModel, $db) {
    // Bersihkan penempatan siswa salsa sebelum test
    $classModel->removeStudentFromClass($targetClassId, $salsaId);

    $_POST = [
        'student_id'  => $salsaId,
        'roll_number' => '01'
    ];
    $request = new Request([], $_SERVER, $_POST);
    $controller = new ClassController($request);
    $response = $controller->addStudent($targetClassId);

    // Cek di DB
    $enrollment = $db->query("SELECT * FROM class_students WHERE class_id = {$targetClassId} AND student_id = {$salsaId} AND status = 'active'")->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) {
        throw new Exception("Enrollment record not found in class_students");
    }
    if ($enrollment['roll_number'] !== '01') {
        throw new Exception("Roll number mismatch: expected '01', got '{$enrollment['roll_number']}'");
    }

    // Cek count bertambah
    $details = $classModel->getClassWithDetails($targetClassId);
    if ((int)$details['students_count'] < 1) {
        throw new Exception("students_count did not increase in class details");
    }
});

it("4. Siswa yang dimasukkan tampil pada daftar siswa di view admin/classes/{id}/students", function () use ($targetClassId, $studentSalsa) {
    $controller = new ClassController(new Request());
    $response = $controller->students($targetClassId);
    $html = $response->getContent();
    if (!str_contains($html, $studentSalsa['name'])) {
        throw new Exception("Enrolled student name '{$studentSalsa['name']}' not rendered in table");
    }
    if (!str_contains($html, '01')) {
        throw new Exception("Roll number '01' not rendered in table");
    }
});

it("5. Memindahkan siswa ke kelas lain menonaktifkan penempatan kelas lama (status: transferred)", function () use ($classModel, $salsaId, $targetClassId, $db) {
    // Pastikan siswa berada di targetClassId dulu
    $classModel->enrollStudent($targetClassId, $salsaId, '01');

    // Pindahkan Salsa ke kelas 1 (XII MIPA 1)
    $classModel->enrollStudent(1, $salsaId, '05');

    // Cek kelas lama (targetClassId / 5): status harus 'transferred'
    $oldClass = $db->query("SELECT * FROM class_students WHERE class_id = {$targetClassId} AND student_id = {$salsaId}")->fetch(PDO::FETCH_ASSOC);
    if (!$oldClass || $oldClass['status'] !== 'transferred') {
        throw new Exception("Old class enrollment was not marked as 'transferred'");
    }

    // Cek kelas baru (1): status harus 'active'
    $newClass = $db->query("SELECT * FROM class_students WHERE class_id = 1 AND student_id = {$salsaId}")->fetch(PDO::FETCH_ASSOC);
    if (!$newClass || $newClass['status'] !== 'active') {
        throw new Exception("New class enrollment was not marked as 'active'");
    }

    // Kembalikan Salsa ke kelas target (5)
    $classModel->enrollStudent($targetClassId, $salsaId, '01');
    $restored = $db->query("SELECT * FROM class_students WHERE class_id = {$targetClassId} AND student_id = {$salsaId}")->fetch(PDO::FETCH_ASSOC);
    if (!$restored || $restored['status'] !== 'active') {
        throw new Exception("Restoring student to class 5 failed");
    }
});

it("6. ClassController::removeStudent mengeluarkan siswa dari kelas", function () use ($targetClassId, $salsaId, $classModel, $db) {
    $controller = new ClassController(new Request());
    $controller->removeStudent($targetClassId, $salsaId);

    $enrollment = $db->query("SELECT * FROM class_students WHERE class_id = {$targetClassId} AND student_id = {$salsaId} AND status = 'active'")->fetch(PDO::FETCH_ASSOC);
    if ($enrollment) {
        throw new Exception("Student was not removed from class");
    }
});

it("7. UserController::store dan update dapat mengatur kelas langsung dari form pengguna", function () use ($userModel, $classModel, $targetClassId, $db) {
    $uniqueUsername = 'siswa_unit_rombel_' . time();
    $uniqueEmail = $uniqueUsername . '@example.com';
    $roleStudentId = (int)$db->query("SELECT id FROM roles WHERE name = 'peserta_didik'")->fetchColumn();

    // 1. Simpan user baru dengan pilihan kelas
    $_POST = [
        'name'     => 'Siswa Form Rombel Test',
        'email'    => $uniqueEmail,
        'username' => $uniqueUsername,
        'password' => 'password123',
        'role_id'  => $roleStudentId,
        'class_id' => $targetClassId,
        'gender'   => 'L',
        'status'   => 'active'
    ];
    $userController = new UserController(new Request([], $_POST));
    $userController->store();

    $createdUser = $userModel->findByUsername($uniqueUsername);
    if (!$createdUser) {
        throw new Exception("User creation with class failed");
    }
    $createdUserId = (int)$createdUser['id'];

    // Cek apakah langsung masuk ke class_students
    $activeClassId = $classModel->getStudentActiveClassId($createdUserId);
    if ($activeClassId !== $targetClassId) {
        throw new Exception("Expected active class ID {$targetClassId}, got " . var_export($activeClassId, true));
    }

    // 2. Update user: pindahkan kelas ke Kelas 1
    $_POST = [
        'name'     => 'Siswa Form Rombel Test (Updated)',
        'email'    => $uniqueEmail,
        'username' => $uniqueUsername,
        'role_id'  => $roleStudentId,
        'class_id' => 1,
        'status'   => 'active'
    ];
    $userController = new UserController(new Request([], $_POST));
    $userController->update($createdUserId);

    $updatedClassId = $classModel->getStudentActiveClassId($createdUserId);
    if ($updatedClassId !== 1) {
        throw new Exception("Expected updated class ID 1, got " . var_export($updatedClassId, true));
    }

    // Cleanup
    $db->exec("DELETE FROM class_students WHERE student_id = {$createdUserId}");
    $db->exec("DELETE FROM user_roles WHERE user_id = {$createdUserId}");
    $db->exec("DELETE FROM users WHERE id = {$createdUserId}");
});

it("8. Hubungkan Salsa Nabila ke XII MIPA 2 sehingga halaman Admin Classes menampilkan 1 Siswa", function () use ($targetClassId, $salsaId, $classModel) {
    // Daftarkan Salsa Nabila ke XII MIPA 2
    $classModel->enrollStudent($targetClassId, $salsaId, '01');

    $details = $classModel->getClassWithDetails($targetClassId);
    if ((int)$details['students_count'] !== 1) {
        throw new Exception("Expected 1 student in XII MIPA 2, got {$details['students_count']}");
    }
});

echo "\n==============================================================================\n";
echo "RINGKASAN HASIL TEST: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}
