<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: LIGHT MODE & DARK MODE FEATURE (SMART EXAM)
 * ==============================================================================
 * Menguji fitur tema tampilan:
 * 1. Skema dan inisialisasi tabel user_preferences.
 * 2. Penyimpanan preferensi tema per-user di database (isolasi akun).
 * 3. Helper theme_mode() dan is_dark_mode().
 * 4. Controller ProfileController::updateTheme() (AJAX & Form).
 * 5. Proteksi input (hanya 'dark' atau 'light').
 * 6. Integrasi layout Admin, Guru, Siswa, Main, dan Auth.
 * 7. Isolasi ketat ruang ujian CBT (layouts/exam.php).
 * 8. Komponen segmented control di navbar dropdown dan profil.
 * ==============================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\UserPreferenceModel;
use App\Controllers\ProfileController;

echo "==============================================================================\n";
echo "  TEST SUITE: LIGHT MODE & DARK MODE (SMART EXAM)\n";
echo "==============================================================================\n\n";

$passed = 0;
$failed = 0;

function assertThemeTest(string $desc, bool $condition, ?string $details = null): void
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

// --------------------------------------------------------------------------
// 1. Inisialisasi Model & Pembuatan Skema Database
// --------------------------------------------------------------------------
echo "--- 1. Database Schema & Model Initialization ---\n";

UserPreferenceModel::ensureTableExists();

$tableCheck = $db->query("SHOW TABLES LIKE 'user_preferences'")->fetch();
assertThemeTest("Tabel user_preferences berhasil dibuat otomatis", !empty($tableCheck));

$cols = $db->query("DESCRIBE user_preferences")->fetchAll(PDO::FETCH_COLUMN);
assertThemeTest("Tabel user_preferences memiliki kolom user_id", in_array('user_id', $cols, true));
assertThemeTest("Tabel user_preferences memiliki kolom preference_key", in_array('preference_key', $cols, true));
assertThemeTest("Tabel user_preferences memiliki kolom preference_value", in_array('preference_value', $cols, true));

// --------------------------------------------------------------------------
// 2. Isolasi Tema Antar Pengguna Berbeda
// --------------------------------------------------------------------------
echo "\n--- 2. Per-User Theme Isolation & Persistence ---\n";

$userAdminId = 1;
$userGuruId = 3;
$userSiswaId = 4;

// Set Admin -> Dark, Siswa -> Light
UserPreferenceModel::setThemeForUser($userAdminId, 'dark');
UserPreferenceModel::setThemeForUser($userSiswaId, 'light');

$adminTheme = UserPreferenceModel::getThemeForUser($userAdminId);
$siswaTheme = UserPreferenceModel::getThemeForUser($userSiswaId);
$guruThemeDefault = UserPreferenceModel::getThemeForUser($userGuruId); // default

assertThemeTest("Admin memiliki preferensi tema 'dark'", $adminTheme === 'dark');
assertThemeTest("Siswa memiliki preferensi tema 'light'", $siswaTheme === 'light');
assertThemeTest("Tema Admin dan Siswa tidak saling bertukar (terisolasi per-akun)", $adminTheme !== $siswaTheme);
assertThemeTest("User tanpa pengaturan eksplisit default ke 'light'", in_array($guruThemeDefault, ['light', 'dark'], true));

// --------------------------------------------------------------------------
// 3. Helper Functions theme_mode() & is_dark_mode()
// --------------------------------------------------------------------------
echo "\n--- 3. Helper theme_mode() & is_dark_mode() ---\n";

// Simulasikan session Admin (Dark)
$_SESSION['user'] = ['id' => $userAdminId, 'name' => 'Admin Test', 'theme' => 'dark'];
$_SESSION['theme'] = 'dark';
assertThemeTest("theme_mode() mengembalikan 'dark' untuk session Admin", theme_mode() === 'dark');
assertThemeTest("is_dark_mode() mengembalikan true saat dark mode aktif", is_dark_mode() === true);

// Simulasikan session Siswa (Light)
$_SESSION['user'] = ['id' => $userSiswaId, 'name' => 'Siswa Test', 'theme' => 'light'];
$_SESSION['theme'] = 'light';
assertThemeTest("theme_mode() mengembalikan 'light' untuk session Siswa", theme_mode() === 'light');
assertThemeTest("is_dark_mode() mengembalikan false saat light mode aktif", is_dark_mode() === false);

// --------------------------------------------------------------------------
// 4. Controller Endpoint /profile/theme (AJAX API)
// --------------------------------------------------------------------------
echo "\n--- 4. Controller ProfileController::updateTheme() ---\n";

// Login sebagai Guru (ID 3)
$_SESSION['user'] = [
    'id'       => $userGuruId,
    'name'     => 'Budi Guru',
    'email'    => 'budi@sekolah.sch.id',
    'username' => 'budiguru',
    'role'     => 'guru',
    'theme'    => 'light',
];

// 4.1 Kirim permintaan update tema ke 'dark' via AJAX
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
$_POST = ['theme' => 'dark'];

$controller = new ProfileController();
$response = $controller->updateTheme();

assertThemeTest("updateTheme mengembalikan status HTTP 200", $response->getStatusCode() === 200);

$json = json_decode($response->getContent(), true);
assertThemeTest("Respons JSON memuat success = true", ($json['success'] ?? false) === true);
assertThemeTest("Respons JSON memuat theme = 'dark'", ($json['theme'] ?? '') === 'dark');

// Verifikasi database terupdate
$savedInDb = UserPreferenceModel::getThemeForUser($userGuruId);
assertThemeTest("Preferensi Guru berhasil disimpan ke database sebagai 'dark'", $savedInDb === 'dark');

// Verifikasi session terupdate
assertThemeTest("Sesi pengguna terupdate dengan theme 'dark'", ($_SESSION['user']['theme'] ?? '') === 'dark');

// 4.2 Uji Validasi Input: Penolakan nilai tema tidak sah
$_POST = ['theme' => 'hacked_neon_theme'];
$controllerBad = new ProfileController();
$responseBad = $controllerBad->updateTheme();
assertThemeTest("Input tema tidak sah ditolak dengan HTTP 400", $responseBad->getStatusCode() === 400);

// Kembalikan tema Guru ke light
UserPreferenceModel::setThemeForUser($userGuruId, 'light');

// --------------------------------------------------------------------------
// 5. Audit Layouts & Anti-Flicker Integration
// --------------------------------------------------------------------------
echo "\n--- 5. Layout Architecture & Anti-Flicker Script ---\n";

$layoutsToCheck = [
    'layouts/admin.php'   => file_get_contents(__DIR__ . '/../app/Views/layouts/admin.php'),
    'layouts/guru.php'    => file_get_contents(__DIR__ . '/../app/Views/layouts/guru.php'),
    'layouts/peserta.php' => file_get_contents(__DIR__ . '/../app/Views/layouts/peserta.php'),
    'layouts/main.php'    => file_get_contents(__DIR__ . '/../app/Views/layouts/main.php'),
    'layouts/auth.php'    => file_get_contents(__DIR__ . '/../app/Views/layouts/auth.php'),
];

foreach ($layoutsToCheck as $file => $content) {
    assertThemeTest("{$file} memuat data-bs-theme=\"<?= theme_mode() ?>\"", str_contains($content, 'data-bs-theme="<?= theme_mode() ?>"'));
    assertThemeTest("{$file} memuat script anti-flicker", str_contains($content, 'Anti-flicker Instant Theme Script'));
    assertThemeTest("{$file} memuat meta user-id untuk isolasi client", str_contains($content, 'name="user-id"'));
}

// --------------------------------------------------------------------------
// 6. Proteksi Ruang Ujian CBT (layouts/exam.php)
// --------------------------------------------------------------------------
echo "\n--- 6. Exam Room Protection & Non-Interference ---\n";

$examLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/exam.php');
assertThemeTest("layouts/exam.php mempertahankan body class='exam-mode'", str_contains($examLayout, 'class="exam-mode'));
assertThemeTest("layouts/exam.php TIDAK dimodifikasi dengan tema baru", !str_contains($examLayout, 'Anti-flicker Instant Theme Script'));

// Cek CSS main.css memastikan aturan dark mode mengecualikan .exam-mode
$mainCss = file_get_contents(__DIR__ . '/../public/assets/css/main.css');
assertThemeTest("main.css mengecualikan .exam-mode dari dark mode override", str_contains($mainCss, ':not(.exam-mode)'));

// --------------------------------------------------------------------------
// 7. Navbar & UI Toggle Controls
// --------------------------------------------------------------------------
echo "\n--- 7. UI Controls & Profile Integration ---\n";

$navbarFile = file_get_contents(__DIR__ . '/../app/Views/components/navbar.php');
assertThemeTest("Navbar memuat segmented control pilihan tema", str_contains($navbarFile, 'theme-segmented-control'));
assertThemeTest("Navbar memuat tombol Tema Terang", str_contains($navbarFile, "App.setTheme('light'"));
assertThemeTest("Navbar memuat tombol Tema Gelap", str_contains($navbarFile, "App.setTheme('dark'"));
assertThemeTest("Navbar memuat icon & label ☀️ Terang", str_contains($navbarFile, 'Terang'));
assertThemeTest("Navbar memuat icon & label 🌙 Gelap", str_contains($navbarFile, 'Gelap'));

$editProfileFile = file_get_contents(__DIR__ . '/../app/Views/profile/edit.php');
assertThemeTest("Halaman Edit Profil memuat kotak preferensi tema", str_contains($editProfileFile, 'theme-preference-box'));

$profileIndexFile = file_get_contents(__DIR__ . '/../app/Views/profile/index.php');
assertThemeTest("Halaman Profil Saya menampilkan status tema", str_contains($profileIndexFile, 'Tema Tampilan'));

// --------------------------------------------------------------------------
// 8. Client-side Core JS (app.js)
// --------------------------------------------------------------------------
echo "\n--- 8. Client-Side Core JS (app.js) ---\n";

$appJs = file_get_contents(__DIR__ . '/../public/assets/js/app.js');
assertThemeTest("app.js mengekspos method setTheme", str_contains($appJs, 'setTheme,') || str_contains($appJs, 'setTheme:'));
assertThemeTest("app.js mengekspos method toggleTheme", str_contains($appJs, 'toggleTheme,') || str_contains($appJs, 'toggleTheme:'));
assertThemeTest("app.js mengekspos method getTheme", str_contains($appJs, 'getTheme'));
assertThemeTest("app.js menyimpan tema ke localStorage dengan namespace user ID", str_contains($appJs, 'smart_exam_theme_'));

// --------------------------------------------------------------------------
// Hasil Akhir
// --------------------------------------------------------------------------
echo "\n==============================================================================\n";
echo "  HASIL AKHIR: $passed PASSED, $failed FAILED\n";
echo "==============================================================================\n";

if ($failed > 0) {
    exit(1);
}
