<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\UserModel;
use App\Models\RoleModel;
use App\Libraries\Database;

echo "==============================================================================\n";
echo "  TEST SUITE: REVISI DROPDOWN PROFIL & TEMA (SMART EXAM)\n";
echo "==============================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertDropdown(bool $condition, string $label, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$label}\n";
    } else {
        $failCount++;
        echo " [FAIL] {$label}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

// --------------------------------------------------------------------------
// 1. Audit Komponen Bersama & File Navbar
// --------------------------------------------------------------------------
echo "--- 1. Struktur Komponen Bersama & Layout --- \n";

$navbarComponent = file_get_contents(__DIR__ . '/../app/Views/components/navbar.php');
$navbarPartial   = file_get_contents(__DIR__ . '/../app/Views/partials/navbar.php');
$adminLayout     = file_get_contents(__DIR__ . '/../app/Views/layouts/admin.php');
$guruLayout      = file_get_contents(__DIR__ . '/../app/Views/layouts/guru.php');
$pesertaLayout   = file_get_contents(__DIR__ . '/../app/Views/layouts/peserta.php');

assertDropdown(
    str_contains($adminLayout, "components/navbar.php"),
    "Layout Admin menggunakan komponen bersama components/navbar.php"
);
assertDropdown(
    str_contains($guruLayout, "components/navbar.php"),
    "Layout Guru menggunakan komponen bersama components/navbar.php"
);
assertDropdown(
    str_contains($pesertaLayout, "components/navbar.php"),
    "Layout Siswa/Peserta menggunakan komponen bersama components/navbar.php"
);

// --------------------------------------------------------------------------
// 2. Susunan Menu Dropdown Sesuai Spesifikasi Baru
// --------------------------------------------------------------------------
echo "\n--- 2. Susunan Dropdown (Profil & Tampilan) --- \n";

// A. Informasi Pengguna
assertDropdown(
    str_contains($navbarComponent, "user_avatar()") && str_contains($navbarComponent, "user_name()") && str_contains($navbarComponent, "user_email()"),
    "Bagian A: Informasi Pengguna memuat avatar, nama, email dinamis"
);
assertDropdown(
    str_contains($navbarComponent, "role_badge_class()") && str_contains($navbarComponent, "role_label()"),
    "Bagian A: Badge role pengguna ditampilkan secara dinamis"
);

// B. Kelompok Profil & Tampilan
assertDropdown(
    str_contains($navbarComponent, "Profil &amp; Tampilan") || str_contains($navbarComponent, "Profil & Tampilan"),
    "Bagian B: Judul kelompok 'Profil & Tampilan' terpasang"
);
assertDropdown(
    str_contains($navbarComponent, "base_url('profile')") && str_contains($navbarComponent, "Profil Saya"),
    "Bagian B: Menu 'Profil Saya' mengarah ke URL profile"
);
assertDropdown(
    str_contains($navbarComponent, "theme-segmented-control"),
    "Bagian B: Pilihan tema berbentuk segmented control dua mode"
);
assertDropdown(
    str_contains($navbarComponent, "App.setTheme('light'") && str_contains($navbarComponent, "Terang"),
    "Bagian B: Pilihan Terang tersedia dan memicu App.setTheme('light')"
);
assertDropdown(
    str_contains($navbarComponent, "App.setTheme('dark'") && str_contains($navbarComponent, "Gelap"),
    "Bagian B: Pilihan Gelap tersedia dan memicu App.setTheme('dark')"
);
assertDropdown(
    str_contains($navbarComponent, "theme-btn-light") && str_contains($navbarComponent, "theme-btn-dark"),
    "Bagian B: Tombol tema memiliki selector penanda aktif yang terhubung dengan app.js"
);

// C. Menu Navigasi
echo "\n--- 3. Menu Navigasi & Keluar --- \n";
assertDropdown(
    str_contains($navbarComponent, "base_url()") && str_contains($navbarComponent, "Halaman Depan"),
    "Bagian C: Menu navigasi 'Halaman Depan' tersedia"
);
assertDropdown(
    str_contains($navbarComponent, "App.openCameraDiagnostic()") && str_contains($navbarComponent, "Uji Coba Kamera"),
    "Bagian C: Menu 'Uji Coba Kamera' tersedia dan membuka modal diagnostik"
);

// D. Menu Keluar
assertDropdown(
    str_contains($navbarComponent, "base_url('logout')") && str_contains($navbarComponent, "Keluar"),
    "Bagian D: Menu 'Keluar' berada di bawah dengan tautan logout yang aman"
);

// --------------------------------------------------------------------------
// 4. Konsistensi Ikon Sederhana (Clean & Uniform)
// --------------------------------------------------------------------------
echo "\n--- 4. Konsistensi Ikon Sederhana --- \n";
assertDropdown(
    str_contains($navbarComponent, 'bi-person') && str_contains($navbarComponent, 'bi-circle-half'),
    "Ikon kelompok Profil & Tampilan sederhana dan konsisten (bi-person & bi-circle-half)"
);
assertDropdown(
    str_contains($navbarComponent, 'bi-house') && str_contains($navbarComponent, 'bi-camera-video') && str_contains($navbarComponent, 'bi-box-arrow-right'),
    "Ikon navigasi dan logout seragam dan fungsional"
);

// --------------------------------------------------------------------------
// 5. Keselarasan Navbar Partial (Landing/Main Page)
// --------------------------------------------------------------------------
echo "\n--- 5. Keselarasan Dropdown pada partials/navbar.php --- \n";
assertDropdown(
    str_contains($navbarPartial, "Profil &amp; Tampilan") || str_contains($navbarPartial, "Profil & Tampilan"),
    "partials/navbar.php memiliki kelompok 'Profil & Tampilan'"
);
assertDropdown(
    str_contains($navbarPartial, "base_url('profile')") && str_contains($navbarPartial, "Profil Saya"),
    "partials/navbar.php memiliki tautan 'Profil Saya'"
);
assertDropdown(
    str_contains($navbarPartial, "App.setTheme('light'") && str_contains($navbarPartial, "App.setTheme('dark'"),
    "partials/navbar.php memiliki pilihan tema Terang & Gelap"
);
assertDropdown(
    str_contains($navbarPartial, "base_url('logout')"),
    "partials/navbar.php memiliki menu Keluar"
);

// --------------------------------------------------------------------------
// 6. Pengujian Render Dropdown untuk Role Admin, Guru, dan Siswa
// --------------------------------------------------------------------------
echo "\n--- 6. Pengujian Render untuk Admin, Guru, dan Siswa --- \n";

$userModel = new UserModel();

// Test Admin
$admin = $userModel->findByEmail('admin@gmail.com') ?: $userModel->findByEmail('superadmin@gmail.com');
if ($admin) {
    login_user(array_merge($admin, ['role' => 'admin', 'roles' => ['admin']]));
    ob_start();
    include __DIR__ . '/../app/Views/components/navbar.php';
    $adminHtml = ob_get_clean();

    assertDropdown(
        str_contains($adminHtml, e(user_name())),
        "Render Admin: Menampilkan nama admin ({$admin['name']})"
    );
    assertDropdown(
        str_contains($adminHtml, "Profil &amp; Tampilan"),
        "Render Admin: Menampilkan kelompok 'Profil & Tampilan'"
    );
    assertDropdown(
        str_contains($adminHtml, "btnThemeLight") && str_contains($adminHtml, "btnThemeDark"),
        "Render Admin: Menampilkan tombol tema Terang & Gelap"
    );
}

// Test Guru
$guru = $userModel->findByEmail('Budisantoso@gmail.com');
if ($guru) {
    login_user(array_merge($guru, ['role' => 'guru', 'roles' => ['guru']]));
    ob_start();
    include __DIR__ . '/../app/Views/components/navbar.php';
    $guruHtml = ob_get_clean();

    assertDropdown(
        str_contains($guruHtml, e(user_name())),
        "Render Guru: Menampilkan nama guru ({$guru['name']})"
    );
    assertDropdown(
        str_contains($guruHtml, "Profil &amp; Tampilan"),
        "Render Guru: Menampilkan kelompok 'Profil & Tampilan'"
    );
    assertDropdown(
        str_contains($guruHtml, "btnThemeLight") && str_contains($guruHtml, "btnThemeDark"),
        "Render Guru: Menampilkan tombol tema Terang & Gelap"
    );
}

// Test Siswa
$siswa = $userModel->findByEmail('sn9942136@gmail.com');
if ($siswa) {
    login_user(array_merge($siswa, ['role' => 'peserta_didik', 'roles' => ['peserta_didik']]));
    ob_start();
    include __DIR__ . '/../app/Views/components/navbar.php';
    $siswaHtml = ob_get_clean();

    assertDropdown(
        str_contains($siswaHtml, e(user_name())),
        "Render Siswa: Menampilkan nama siswa ({$siswa['name']})"
    );
    assertDropdown(
        str_contains($siswaHtml, "Profil &amp; Tampilan"),
        "Render Siswa: Menampilkan kelompok 'Profil & Tampilan'"
    );
    assertDropdown(
        str_contains($siswaHtml, "btnThemeLight") && str_contains($siswaHtml, "btnThemeDark"),
        "Render Siswa: Menampilkan tombol tema Terang & Gelap"
    );
}

// --------------------------------------------------------------------------
// 7. Verifikasi Integrasi Edit Profil & Halaman Profil
// --------------------------------------------------------------------------
echo "\n--- 7. Verifikasi Halaman Profil & Fitur Edit Profil Tetap Berfungsi --- \n";
$profileView = file_get_contents(__DIR__ . '/../app/Views/profile/index.php');
assertDropdown(
    str_contains($profileView, "profile/edit") || str_contains($profileView, "base_url('profile/edit')"),
    "Halaman Profil Saya menyediakan tombol/akses langsung menuju Edit Profil"
);
$editView = file_get_contents(__DIR__ . '/../app/Views/profile/edit.php');
assertDropdown(
    str_contains($editView, "name=\"name\"") && str_contains($editView, "name=\"username\""),
    "Halaman Edit Profil tetap utuh dengan form perubahan data"
);

echo "\n==============================================================================\n";
echo "  HASIL AKHIR: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}

