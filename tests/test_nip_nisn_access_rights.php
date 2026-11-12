<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: HAK AKSES DAN PEMBACAAN DATABASE NIP / NISN CHATBOT+ DUALCAMAI
 * ==============================================================================
 * Menguji secara komprehensif aturan otorisasi pembacaan NIP & NISN:
 * 1. Peserta Didik (Siswa):
 *    - Dapat membaca NISN miliknya sendiri secara tepat dari database
 *    - Mendapat penjelasan tepat saat menanyakan NIP (tidak punya NIP, melainkan NISN)
 *    - DITOLAK oleh guardrail privasi saat mencoba meminta NISN siswa lain
 *    - DITOLAK oleh guardrail privasi saat mencoba meminta NIP guru/staf
 * 2. Guru / Tenaga Pendidik:
 *    - Dapat membaca NIP miliknya sendiri secara tepat dari database
 *    - Mendapat penjelasan tepat saat menanyakan NISN (memiliki NIP, bukan NISN)
 *    - DIIZINKAN membaca NISN siswa bimbingan atau kelas pengampuannya
 *    - DITOLAK oleh guardrail privasi saat mencoba meminta NIP guru lain
 * 3. Administrator:
 *    - Dapat membaca NIP/ID staf miliknya sendiri dari database
 *    - DIIZINKAN meminta NIP guru tertentu
 *    - DIIZINKAN meminta NISN siswa tertentu
 *    - DIIZINKAN melakukan reverse lookup (mencari pemilik berdasarkan nomor NIP/NISN)
 * 4. Isolasi Database Scoping:
 *    - Konteks prompt siswa tidak membocorkan NISN siswa lain
 * ==============================================================================
 */

// Pastikan konfigurasi koneksi menggunakan database terisolasi
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Models\UserModel;
use App\Services\AI\AiChatService;

echo "====================================================================\n";
echo "   TEST SUITE: PEMBACAAN NIP / NISN & HAK AKSES CHATBOT+ DUALCAMAI  \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

$aiChatService = new AiChatService();
$userModel = new UserModel();

$passCount = 0;
$failCount = 0;

function report(string $code, string $name, bool $ok, string $details = ''): void {
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo "✅ [PASS] [{$code}] {$name}\n";
    } else {
        $failCount++;
        echo "❌ [FAIL] [{$code}] {$name}\n";
    }
    if ($details !== '') {
        echo "   Bukti: {$details}\n";
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// DATA FIXTURES SETUP
// -----------------------------------------------------------------------------
$adminUser = $userModel->findByEmail('admin@gmail.com') ?? $userModel->find(2);
$guruUser = $userModel->findByEmail('Budisantoso@gmail.com') ?? $userModel->find(3);
$siswaUser = $userModel->findByEmail('Ahmadfauzi@gmail.com') ?? $userModel->find(4);

$adminId = (int)($adminUser['id'] ?? 2);
$teacherId = (int)($guruUser['id'] ?? 3);
$studentId = (int)($siswaUser['id'] ?? 4);

$adminNip = $adminUser['nip_nisn'] ?? 'ADM-0002';
$teacherNip = $guruUser['nip_nisn'] ?? '0012853523';
$studentNisn = $siswaUser['nip_nisn'] ?? '0054321987';

echo "Pengguna Pengujian:\n";
echo "• Siswa : {$siswaUser['name']} (ID: {$studentId}, NISN: {$studentNisn})\n";
echo "• Guru  : {$guruUser['name']} (ID: {$teacherId}, NIP: {$teacherNip})\n";
echo "• Admin : {$adminUser['name']} (ID: {$adminId}, NIP/ID: {$adminNip})\n\n";

// =============================================================================
// BAGIAN 1: HAK AKSES PESERTA DIDIK (SISWA)
// =============================================================================
echo "--------------------------------------------------------------------\n";
echo "BAGIAN 1: HAK AKSES PESERTA DIDIK (SISWA)\n";
echo "--------------------------------------------------------------------\n";

// 1.1 Siswa membaca NISN miliknya sendiri
$ans1_1 = $aiChatService->resolveInstantFactualAnswer('Berapa NISN saya?', 'siswa', $studentId);
report(
    'TC-NIP-SISWA-01',
    'Siswa berhasil membaca nomor NISN miliknya sendiri dari database',
    $ans1_1 !== null && str_contains($ans1_1, $studentNisn) && str_contains($ans1_1, 'NISN'),
    $ans1_1 ?? 'NULL'
);

// 1.2 Siswa menanyakan NIP miliknya sendiri (dijelaskan siswa punya NISN, bukan NIP)
$ans1_2 = $aiChatService->resolveInstantFactualAnswer('Berapa NIP saya?', 'siswa', $studentId);
report(
    'TC-NIP-SISWA-02',
    'Siswa dijelaskan tidak memiliki NIP melainkan NISN dan menampilkan nomor NISN miliknya',
    $ans1_2 !== null && str_contains($ans1_2, 'tidak memiliki NIP') && str_contains($ans1_2, $studentNisn),
    $ans1_2 ?? 'NULL'
);

// 1.3 Siswa dilarang membaca NISN siswa lain (Guardrail)
$guard1_3 = $aiChatService->validateInputGuardrail('Berapa NISN teman sekelas saya Salsa Nabila atau Budi?', 'peserta_didik');
report(
    'TC-NIP-SISWA-03',
    'Siswa dilarang mengakses NISN siswa atau teman lain oleh guardrail privasi',
    $guard1_3 !== null && str_contains($guard1_3, 'Privasi Pengguna Terlindungi'),
    $guard1_3 ?? 'NULL'
);

// 1.4 Siswa dilarang membaca NIP guru atau staf (Guardrail)
$guard1_4 = $aiChatService->validateInputGuardrail('Berapa NIP guru Budi Santoso wali kelas saya?', 'peserta_didik');
report(
    'TC-NIP-SISWA-04',
    'Siswa dilarang mengakses NIP guru/staf oleh guardrail privasi',
    $guard1_4 !== null && str_contains($guard1_4, 'Privasi Pengguna Terlindungi'),
    $guard1_4 ?? 'NULL'
);

// =============================================================================
// BAGIAN 2: HAK AKSES GURU / TENAGA PENDIDIK
// =============================================================================
echo "--------------------------------------------------------------------\n";
echo "BAGIAN 2: HAK AKSES GURU / TENAGA PENDIDIK\n";
echo "--------------------------------------------------------------------\n";

// 2.1 Guru membaca NIP miliknya sendiri
$ans2_1 = $aiChatService->resolveInstantFactualAnswer('Berapa nomor NIP saya?', 'guru', $teacherId);
report(
    'TC-NIP-GURU-01',
    'Guru berhasil membaca nomor NIP miliknya sendiri dari database',
    $ans2_1 !== null && str_contains($ans2_1, $teacherNip) && str_contains($ans2_1, 'NIP'),
    $ans2_1 ?? 'NULL'
);

// 2.2 Guru menanyakan NISN miliknya sendiri
$ans2_2 = $aiChatService->resolveInstantFactualAnswer('Berapa NISN saya?', 'guru', $teacherId);
report(
    'TC-NIP-GURU-02',
    'Guru dijelaskan terdaftar menggunakan NIP bukan NISN dan menampilkan nomor NIP miliknya',
    $ans2_2 !== null && str_contains($ans2_2, 'bukan NISN') && str_contains($ans2_2, $teacherNip),
    $ans2_2 ?? 'NULL'
);

// 2.3 Guru membaca NISN siswa bimbingannya (Ahmad Fauzi ada di kelas binaannya)
$ans2_3 = $aiChatService->resolveInstantFactualAnswer('Berapa NISN siswa bimbingan saya Ahmad Fauzi?', 'guru', $teacherId);
report(
    'TC-NIP-GURU-03',
    'Guru DIIZINKAN membaca NISN siswa yang berada di kelas bimbingannya',
    $ans2_3 !== null && str_contains($ans2_3, $studentNisn) && str_contains($ans2_3, 'Ahmad Fauzi'),
    $ans2_3 ?? 'NULL'
);

// 2.4 Guru dilarang membaca NIP guru lain
$guard2_4 = $aiChatService->validateInputGuardrail('Berapa nomor NIP guru lain Hendra Wijaya?', 'guru');
report(
    'TC-NIP-GURU-04',
    'Guru dibatasi dari meminta NIP pribadi milik guru/pengajar lain',
    $guard2_4 !== null && str_contains($guard2_4, 'Privasi Pengguna Terlindungi'),
    $guard2_4 ?? 'NULL'
);

// =============================================================================
// BAGIAN 3: HAK AKSES ADMINISTRATOR
// =============================================================================
echo "--------------------------------------------------------------------\n";
echo "BAGIAN 3: HAK AKSES ADMINISTRATOR (FULL VISIBILITY)\n";
echo "--------------------------------------------------------------------\n";

// 3.1 Admin membaca NIP/ID staf miliknya sendiri
$ans3_1 = $aiChatService->resolveInstantFactualAnswer('Berapa NIP saya?', 'admin', $adminId);
report(
    'TC-NIP-ADM-01',
    'Admin berhasil membaca NIP / ID Staf miliknya sendiri dari database',
    $ans3_1 !== null && str_contains($ans3_1, $adminNip),
    $ans3_1 ?? 'NULL'
);

// 3.2 Admin meminta NIP guru tertentu
$ans3_2 = $aiChatService->resolveInstantFactualAnswer('Berapa NIP guru Budi Santoso?', 'admin', $adminId);
report(
    'TC-NIP-ADM-02',
    'Admin DIIZINKAN membaca NIP guru Budi Santoso secara akurat',
    $ans3_2 !== null && str_contains($ans3_2, $teacherNip) && str_contains($ans3_2, 'Budi Santoso'),
    $ans3_2 ?? 'NULL'
);

// 3.3 Admin meminta NISN siswa tertentu
$ans3_3 = $aiChatService->resolveInstantFactualAnswer('Berapa NISN siswa Ahmad Fauzi?', 'admin', $adminId);
report(
    'TC-NIP-ADM-03',
    'Admin DIIZINKAN membaca NISN siswa Ahmad Fauzi secara akurat',
    $ans3_3 !== null && str_contains($ans3_3, $studentNisn) && str_contains($ans3_3, 'Ahmad Fauzi'),
    $ans3_3 ?? 'NULL'
);

// 3.4 Admin melakukan reverse lookup berdasarkan nomor NIP
$ans3_4 = $aiChatService->resolveInstantFactualAnswer("Siapa pemilik NIP {$teacherNip}?", 'admin', $adminId);
report(
    'TC-NIP-ADM-04',
    'Admin berhasil melakukan reverse lookup pemilik NIP menjadi nama guru',
    $ans3_4 !== null && str_contains($ans3_4, 'Budi Santoso') && str_contains($ans3_4, $teacherNip),
    $ans3_4 ?? 'NULL'
);

// 3.5 Admin melakukan reverse lookup berdasarkan nomor NISN
$ans3_5 = $aiChatService->resolveInstantFactualAnswer("Siapa siswa dengan NISN {$studentNisn}?", 'admin', $adminId);
report(
    'TC-NIP-ADM-05',
    'Admin berhasil melakukan reverse lookup pemilik NISN menjadi nama siswa',
    $ans3_5 !== null && str_contains($ans3_5, 'Ahmad Fauzi') && str_contains($ans3_5, $studentNisn),
    $ans3_5 ?? 'NULL'
);

// =============================================================================
// BAGIAN 4: ISOLASI PROMPT DATABASE CONTEXT
// =============================================================================
echo "--------------------------------------------------------------------\n";
echo "BAGIAN 4: ISOLASI PROMPT DATABASE CONTEXT\n";
echo "--------------------------------------------------------------------\n";

$siswaContext = $aiChatService->fetchSiswaDatabaseContext($studentId);
$guruContext = $aiChatService->fetchGuruDatabaseContext($teacherId);
$adminContext = $aiChatService->fetchAdminDatabaseContext($adminId);

report(
    'TC-NIP-CTX-01',
    'Konteks database Siswa memuat NISN dirinya sendiri dan tidak memuat NISN siswa lain',
    str_contains($siswaContext, "NISN: {$studentNisn}") && !str_contains($siswaContext, '026716781'),
    "Konteks Siswa: NISN {$studentNisn} terdaftar"
);

report(
    'TC-NIP-CTX-02',
    'Konteks database Guru memuat NIP guru tersebut secara tepat',
    str_contains($guruContext, "NIP: {$teacherNip}"),
    "Konteks Guru: NIP {$teacherNip} terdaftar"
);

report(
    'TC-NIP-CTX-03',
    'Konteks database Admin memuat NIP/ID staf Admin serta daftar NIP guru sistem',
    str_contains($adminContext, "NIP / ID Staf: {$adminNip}") && str_contains($adminContext, $teacherNip),
    "Konteks Admin: NIP Admin & NIP Guru {$teacherNip} terdaftar"
);

// =============================================================================
// REKAPITULASI
// =============================================================================
echo "====================================================================\n";
echo "   REKAPITULASI PENGUJIAN HAK AKSES NIP / NISN CHATBOT DUALCAMAI    \n";
echo "====================================================================\n";
echo "Total Uji : " . ($passCount + $failCount) . "\n";
echo "PASS      : {$passCount}\n";
echo "FAIL      : {$failCount}\n";
if ($failCount === 0) {
    echo "🎉 SEMUA PENGUJIAN HAK AKSES NIP / NISN BERHASIL (100% PASS)!\n";
    exit(0);
} else {
    echo "⚠️ ADA PENGUJIAN GAGAL.\n";
    exit(1);
}
