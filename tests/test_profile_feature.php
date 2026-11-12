<?php

declare(strict_types=1);

/**
 * Test Suite: Fitur Profil Saya & Edit Profil (Smart Exam)
 * Memvalidasi fungsi Profil Saya, Edit Profil, Avatar Upload, IDOR Protection,
 * dan dukungan seluruh role (Admin, Guru, Peserta Didik).
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\UserModel;
use App\Controllers\ProfileController;

echo "==============================================================================\n";
echo "  TEST SUITE: PROFIL SAYA & EDIT PROFIL (SMART EXAM)\n";
echo "==============================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, ?string $details = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] " . $desc . "\n";
    } else {
        $failed++;
        echo " [FAIL] " . $desc . ($details ? " -> " . $details : "") . "\n";
    }
}

Session::start();
$db = Database::getConnection();

// Setup mock session helper
function setMockSessionUser(array $user): void
{
    $_SESSION = [];
    $_SESSION['user'] = [
        'id'          => $user['id'],
        'name'        => $user['name'],
        'email'       => $user['email'],
        'username'    => $user['username'],
        'role'        => $user['role_name'] ?? 'peserta_didik',
        'roles'       => [$user['role_name'] ?? 'peserta_didik'],
        'permissions' => [],
        'nip_nisn'    => $user['nip_nisn'] ?? null,
        'avatar'      => $user['avatar'] ?? null,
    ];
}

// --------------------------------------------------------------------------
// 1. Audit Database User Records for All 3 Roles
// --------------------------------------------------------------------------
echo "--- 1. Database Role & User Verification ---\n";

$superAdmin = $db->query("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('super_admin', 'admin') 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$guru = $db->query("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name = 'guru' 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$siswa = $db->query("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('peserta_didik', 'siswa') 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assertTest("Akun Admin ditemukan", !empty($superAdmin), "ID: " . ($superAdmin['id'] ?? 'none'));
assertTest("Akun Guru ditemukan", !empty($guru), "ID: " . ($guru['id'] ?? 'none'));
assertTest("Akun Siswa ditemukan", !empty($siswa), "ID: " . ($siswa['id'] ?? 'none'));

// --------------------------------------------------------------------------
// 2. Test Profil Saya untuk Role Super Admin / Admin
// --------------------------------------------------------------------------
echo "\n--- 2. Profil Saya: Super Admin / Admin ---\n";
setMockSessionUser($superAdmin);

$controller = new ProfileController();
$response = $controller->index();
$bodyAdmin = $response->getContent();

assertTest("Profil Admin mengembalikan status HTTP 200", $response->getStatusCode() === 200);
assertTest("Profil Admin memuat nama pengguna", str_contains($bodyAdmin, htmlspecialchars($superAdmin['name'])));
assertTest("Profil Admin memuat email pengguna", str_contains($bodyAdmin, htmlspecialchars($superAdmin['email'])));
assertTest("Profil Admin memuat badge Administrator", str_contains($bodyAdmin, 'Administrator') || str_contains($bodyAdmin, 'Super Admin'));

// --------------------------------------------------------------------------
// 3. Test Profil Saya untuk Role Guru
// --------------------------------------------------------------------------
echo "\n--- 3. Profil Saya: Guru ---\n";
setMockSessionUser($guru);

$controller = new ProfileController();
$response = $controller->index();
$bodyGuru = $response->getContent();

assertTest("Profil Guru mengembalikan status HTTP 200", $response->getStatusCode() === 200);
assertTest("Profil Guru memuat nama guru", str_contains($bodyGuru, htmlspecialchars($guru['name'])));
assertTest("Profil Guru memuat section Informasi Tugas Pengajar", str_contains($bodyGuru, 'Informasi Tugas Pengajar'));
assertTest("Profil Guru memuat NIP", str_contains($bodyGuru, 'NIP'));

// --------------------------------------------------------------------------
// 4. Test Profil Saya untuk Role Peserta Didik
// --------------------------------------------------------------------------
echo "\n--- 4. Profil Saya: Peserta Didik / Siswa ---\n";
setMockSessionUser($siswa);

$controller = new ProfileController();
$response = $controller->index();
$bodySiswa = $response->getContent();

assertTest("Profil Siswa mengembalikan status HTTP 200", $response->getStatusCode() === 200);
assertTest("Profil Siswa memuat nama siswa", str_contains($bodySiswa, htmlspecialchars($siswa['name'])));
assertTest("Profil Siswa memuat section Informasi Akademik Peserta Didik", str_contains($bodySiswa, 'Informasi Akademik Peserta Didik'));
assertTest("Profil Siswa memuat label NISN", str_contains($bodySiswa, 'NISN'));

// --------------------------------------------------------------------------
// 5. Test Halaman Edit Profil
// --------------------------------------------------------------------------
echo "\n--- 5. Halaman Edit Profil ---\n";
setMockSessionUser($siswa);

$controller = new ProfileController();
$response = $controller->edit();
$bodyEdit = $response->getContent();

assertTest("Halaman Edit Profil mengembalikan HTTP 200", $response->getStatusCode() === 200);
assertTest("Form Edit memuat field nama", str_contains($bodyEdit, 'name="name"'));
assertTest("Form Edit memuat field username", str_contains($bodyEdit, 'name="username"'));
assertTest("Form Edit memuat pilihan jenis kelamin", str_contains($bodyEdit, 'name="gender"'));
assertTest("Email ditampilkan terproteksi/read-only", str_contains($bodyEdit, 'Saluran Notifikasi & Kontak Terverifikasi'));
assertTest("Nomor WhatsApp ditampilkan terproteksi/read-only", str_contains($bodyEdit, 'Terhubung dengan Fonnte API'));

// --------------------------------------------------------------------------
// 6. Test Pembaruan Profil (Update Profile)
// --------------------------------------------------------------------------
echo "\n--- 6. Pembaruan Profil (Profile Update) ---\n";
$origName = $siswa['name'];
$origUsername = $siswa['username'];
$origGender = $siswa['gender'] ?? 'L';

$testName = "Ahmad Fauzi Updated Test";
$testUsername = "ahmad_fauzi_test_" . time();
$testGender = ($origGender === 'L') ? 'P' : 'L';

$_POST = [
    'name'     => $testName,
    'username' => $testUsername,
    'gender'   => $testGender,
];
$_FILES = [];

$controller = new ProfileController();
$response = $controller->update();

// Verifikasi di database
$userModel = new UserModel();
$updated = $userModel->find($siswa['id']);

assertTest("Nama berhasil diperbarui di database", ($updated['name'] ?? '') === $testName);
assertTest("Username berhasil diperbarui di database", ($updated['username'] ?? '') === $testUsername);
assertTest("Gender berhasil diperbarui di database", ($updated['gender'] ?? '') === $testGender);

// Verifikasi sesi login tersinkronkan
$sess = Session::get('user');
assertTest("Sesi login pengguna tersinkronkan dengan nama baru", ($sess['name'] ?? '') === $testName);
assertTest("Sesi login pengguna tersinkronkan dengan username baru", ($sess['username'] ?? '') === $testUsername);

// Restore data awal
$userModel->update($siswa['id'], [
    'name'     => $origName,
    'username' => $origUsername,
    'gender'   => $origGender,
]);
setMockSessionUser($siswa);

// --------------------------------------------------------------------------
// 7. Test Pencegahan Duplikasi Username
// --------------------------------------------------------------------------
echo "\n--- 7. Validasi Duplikasi Username ---\n";
setMockSessionUser($siswa);

// Coba gunakan username milik Guru
$_POST = [
    'name'     => $siswa['name'],
    'username' => $guru['username'],
    'gender'   => 'L',
];

$controller = new ProfileController();
$response = $controller->update();

$flash = implode(' ', Session::getFlash('danger'));
assertTest("Pembaruan ditolak jika username sudah digunakan akun lain", !empty($flash) && str_contains($flash, 'Username tersebut sudah digunakan'));

// --------------------------------------------------------------------------
// 8. Test Keamanan IDOR (Insecure Direct Object Reference)
// --------------------------------------------------------------------------
echo "\n--- 8. Keamanan & IDOR Protection ---\n";

// Sesi diset sebagai Siswa ID X
setMockSessionUser($siswa);

// Coba inject 'id' atau 'user_id' lain dalam request POST
$_POST = [
    'id'       => $superAdmin['id'],
    'user_id'  => $superAdmin['id'],
    'name'     => 'Hacked Admin Name',
    'username' => 'hacked_admin',
];

$controller = new ProfileController();
$controller->update();

// Pastikan profil Admin TIDAK berubah
$checkAdmin = $userModel->find($superAdmin['id']);
assertTest("IDOR Protection: Input 'id' atau 'user_id' asing diabaikan, identitas murni dari sesi", ($checkAdmin['name'] ?? '') === $superAdmin['name']);

// --------------------------------------------------------------------------
// 9. Test Upload Avatar & Proteksi MIME/Ukuran
// --------------------------------------------------------------------------
echo "\n--- 9. Fitur Upload Foto Profil & Keamanan Berkas ---\n";

// 9.1 Test file palsu non-gambar (PHP script disamarkan jadi .jpg)
$fakeScriptPath = sys_get_temp_dir() . '/fake_image_' . time() . '.jpg';
file_put_contents($fakeScriptPath, "<?php phpinfo(); ?>");

$_FILES['avatar'] = [
    'name'     => 'shell.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $fakeScriptPath,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($fakeScriptPath),
];
$_POST = [
    'name'     => $siswa['name'],
    'username' => $siswa['username'],
    'gender'   => 'L',
];

$controller = new ProfileController();
$controller->update();
$flashMime = implode(' ', Session::getFlash('danger'));
assertTest("Upload ditolak jika berkas script disamarkan sebagai gambar (MIME check)", !empty($flashMime) && (str_contains($flashMime, 'bukan merupakan gambar valid') || str_contains($flashMime, 'rusak')));

@unlink($fakeScriptPath);

// 9.2 Test upload gambar valid (1x1 pixel valid PNG)
$validPngPath = sys_get_temp_dir() . '/test_avatar_' . time() . '.png';
$pngData = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==");
file_put_contents($validPngPath, $pngData);

$_FILES['avatar'] = [
    'name'     => 'profile_picture.png',
    'type'     => 'image/png',
    'tmp_name' => $validPngPath,
    'error'    => UPLOAD_ERR_OK,
    'size'     => strlen($pngData),
];

$controller = new ProfileController();
$controller->update();

$afterUpload = $userModel->find($siswa['id']);
$avatarPath = $afterUpload['avatar'] ?? '';
assertTest("Foto profil valid berhasil diunggah", !empty($avatarPath) && str_starts_with($avatarPath, 'uploads/avatars/avatar_'));

if (!empty($avatarPath)) {
    $fullUploadedPath = FCPATH . $avatarPath;
    assertTest("File fisik avatar tersimpan di direktori uploads/avatars/", file_exists($fullUploadedPath));
}

// 9.3 Test Hapus Foto Profil (Delete Avatar)
$controller->deleteAvatar();
$afterDelete = $userModel->find($siswa['id']);
assertTest("Foto profil kustom berhasil dihapus dari database", empty($afterDelete['avatar']));

// 9.4 Verifikasi .htaccess pada direktori uploads/avatars/
$htaccess = FCPATH . 'uploads/avatars/.htaccess';
assertTest("File .htaccess keamanan ada di folder uploads/avatars/", file_exists($htaccess));
if (file_exists($htaccess)) {
    $htContent = file_get_contents($htaccess);
    assertTest(".htaccess mematikan engine PHP & memblokir eksekusi script", str_contains($htContent, 'php_flag engine off') && str_contains($htContent, 'Options -Indexes -ExecCGI'));
}

// --------------------------------------------------------------------------
// 10. Test Helper user_avatar() dan Integrasi Navigasi
// --------------------------------------------------------------------------
echo "\n--- 10. Helper & Integrasi Navigasi ---\n";

assertTest("Helper user_avatar(null) mengembalikan null", user_avatar(null) === null);
assertTest("Helper user_avatar('uploads/avatars/x.png') mengembalikan URL lengkap", str_contains(user_avatar('uploads/avatars/x.png') ?? '', 'uploads/avatars/x.png'));

// Cek navbar component
$navbarFile = file_get_contents(__DIR__ . '/../app/Views/components/navbar.php');
assertTest("Navbar memuat link 'Profil Saya' (/profile)", str_contains($navbarFile, "base_url('profile')"));
assertTest("Navbar memuat link 'Edit Profil' (/profile/edit)", str_contains($navbarFile, "base_url('profile/edit')"));
assertTest("Navbar mempertahankan link 'Halaman Depan'", str_contains($navbarFile, 'Halaman Depan'));
assertTest("Navbar mempertahankan 'Uji Coba Kamera'", str_contains($navbarFile, 'Uji Coba Kamera'));
assertTest("Navbar mempertahankan 'Keluar'", str_contains($navbarFile, 'Keluar'));

// Cek sidebar component
$sidebarFile = file_get_contents(__DIR__ . '/../app/Views/components/sidebar.php');
assertTest("Sidebar footer memuat tautan ke 'base_url(\'profile\')'", str_contains($sidebarFile, "base_url('profile')"));
assertTest("Sidebar footer memuat avatar / inisial dinamis", str_contains($sidebarFile, 'user_avatar()'));

// --------------------------------------------------------------------------
// Hasil Akhir
// --------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "  HASIL AKHIR: $passed PASSED, $failed FAILED\n";
echo "==============================================================================\n";

if ($failed > 0) {
    exit(1);
}
