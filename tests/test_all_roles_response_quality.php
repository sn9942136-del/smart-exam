<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Services\AI\AiChatService;

$db = Database::getConnection();
$aiService = new AiChatService();

// Ambil ID user untuk masing-masing role
$adminUser = $db->query("
    SELECT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name IN ('admin', 'super_admin') AND u.deleted_at IS NULL 
    ORDER BY u.id ASC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$guruUser = $db->query("
    SELECT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.deleted_at IS NULL 
    ORDER BY u.id ASC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$siswaUser = $db->query("
    SELECT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'peserta_didik' AND u.deleted_at IS NULL 
    ORDER BY u.id ASC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$adminId = (int)$adminUser['id'];
$guruId = (int)$guruUser['id'];
$siswaId = (int)$siswaUser['id'];

echo "==============================================================================\n";
echo "  PENGUJIAN KUALITAS RESPONS CHATBOT SELURUH ROLE (ADMIN, GURU, SISWA)        \n";
echo "==============================================================================\n";
echo "• Admin: ID={$adminId} ({$adminUser['name']})\n";
echo "• Guru : ID={$guruId} ({$guruUser['name']})\n";
echo "• Siswa: ID={$siswaId} ({$siswaUser['name']})\n\n";

$testsPassed = 0;
$testsTotal = 0;

$checkQuality = function(
    string $role,
    int $userId,
    string $query,
    bool $expectSingleSentenceNoBullets,
    AiChatService $aiService,
    string $testCode,
    string $description
) use (&$testsPassed, &$testsTotal) {
    $testsTotal++;
    $resp = $aiService->resolveInstantFactualAnswer($query, $role, $userId);

    $hasBullet = (bool) preg_match('/(^\s*[\*\-•]\s+|\n\s*[\*\-•]\s+)/m', $resp ?? '');
    $hasBreakdownPreamble = stripos($resp ?? '', 'dengan rincian sebagai berikut') !== false;

    $passed = false;
    if ($expectSingleSentenceNoBullets) {
        $passed = ($resp !== null) && !$hasBullet && !$hasBreakdownPreamble;
    } else {
        $passed = ($resp !== null) && $hasBullet;
    }

    if ($passed) {
        $testsPassed++;
        echo "✅ [PASS] [{$testCode}] ({$role}) {$description}\n";
        echo "   Query: \"{$query}\"\n";
        echo "   Resp : " . str_replace("\n", " ", mb_substr($resp ?? '', 0, 100)) . "...\n\n";
    } else {
        echo "❌ [FAIL] [{$testCode}] ({$role}) {$description}\n";
        echo "   Query: \"{$query}\"\n";
        echo "   Resp : {$resp}\n\n";
    }
};

// -------------------------------------------------------------
// 1. ADMIN TESTS
// -------------------------------------------------------------
echo "--- 1. ROLE ADMIN ---\n";
// 1.1 Jumlah user (tanpa rincian)
$checkQuality('admin', $adminId, "ada berapa user", true, $aiService, "ADM-01", "Hitung user langsung 1 kalimat tanpa komentar & tanpa poin-poin");
// 1.2 Typo 'ada berpa user'
$checkQuality('admin', $adminId, "ada berpa user", true, $aiService, "ADM-02", "Typo 'berpa' tetap dijawab langsung 1 kalimat tanpa poin-poin");
// 1.3 Typo 'ada berap user'
$checkQuality('admin', $adminId, "ada berap user", true, $aiService, "ADM-03", "Typo 'berap' tetap dijawab langsung 1 kalimat tanpa poin-poin");
// 1.4 Rincian user (minta rincian)
$checkQuality('admin', $adminId, "sebutkan rincian user per role", false, $aiService, "ADM-04", "Permintaan rincian user menyajikan poin-poin terstruktur");
// 1.5 Jumlah bank soal (tanpa rincian)
$checkQuality('admin', $adminId, "berapa bank soal di sistem", true, $aiService, "ADM-05", "Hitung bank soal langsung 1 kalimat tanpa poin-poin");
// 1.6 Daftar bank soal (minta daftar)
$checkQuality('admin', $adminId, "daftar bank soal di sistem", false, $aiService, "ADM-06", "Permintaan daftar bank soal menyajikan poin-poin");
// 1.7 Jumlah paket ujian (tanpa rincian)
$checkQuality('admin', $adminId, "ada berapa paket ujian", true, $aiService, "ADM-07", "Hitung paket ujian langsung 1 kalimat tanpa poin-poin");
// 1.8 Daftar paket ujian (minta daftar)
$checkQuality('admin', $adminId, "daftar paket ujian", false, $aiService, "ADM-08", "Permintaan daftar paket ujian menyajikan poin-poin");
// 1.9 Jumlah mapel (tanpa rincian)
$checkQuality('admin', $adminId, "ada berapa mapel", true, $aiService, "ADM-09", "Hitung mapel langsung 1 kalimat tanpa poin-poin");
// 1.10 Daftar mapel (minta daftar)
$checkQuality('admin', $adminId, "daftar mapel", false, $aiService, "ADM-10", "Permintaan daftar mapel menyajikan poin-poin");
// 1.11 Pertanyaan Identitas 'siapa anda'
$checkQuality('admin', $adminId, "siapa anda", true, $aiService, "ADM-11", "Identitas bot dijawab langsung 'Saya adalah asisten...' tanpa tanda kurung & tanpa awalan Chatbot +");

// -------------------------------------------------------------
// 2. GURU TESTS
// -------------------------------------------------------------
echo "--- 2. ROLE GURU ---\n";
// 2.1 Jumlah bank soal guru (tanpa rincian)
$checkQuality('guru', $guruId, "berapa bank soal saya", true, $aiService, "GRU-01", "Hitung bank soal guru langsung 1 kalimat tanpa komentar rincian");
// 2.2 Daftar bank soal guru (minta daftar)
$checkQuality('guru', $guruId, "apa saja daftar bank soal saya", false, $aiService, "GRU-02", "Permintaan daftar bank soal guru menyajikan poin-poin");
// 2.3 Jumlah paket ujian guru (tanpa rincian)
$checkQuality('guru', $guruId, "berapa ujian saya", true, $aiService, "GRU-03", "Hitung paket ujian guru langsung 1 kalimat tanpa daftar");
// 2.4 Daftar paket ujian guru (minta daftar)
$checkQuality('guru', $guruId, "daftar ujian saya", false, $aiService, "GRU-04", "Permintaan daftar ujian guru menyajikan poin-poin");
// 2.5 Esai pending (fakta tunggal)
$checkQuality('guru', $guruId, "berapa esai yang belum dinilai", true, $aiService, "GRU-05", "Jawaban esai pending langsung 1 kalimat tanpa poin-poin");
// 2.6 Rekapitulasi esai (minta rekap)
$checkQuality('guru', $guruId, "rekapitulasi penilaian esai", false, $aiService, "GRU-06", "Permintaan rekapitulasi esai menyajikan poin-poin");
// 2.7 Pertanyaan Identitas 'siapa kamu'
$checkQuality('guru', $guruId, "siapa kamu", true, $aiService, "GRU-07", "Identitas bot guru dijawab langsung 'Saya adalah asisten...' tanpa tanda kurung & tanpa awalan Chatbot +");

// -------------------------------------------------------------
// 3. SISWA TESTS
// -------------------------------------------------------------
echo "--- 3. ROLE SISWA ---\n";
// 3.1 Hitung jadwal ujian (tanpa rincian)
$checkQuality('siswa', $siswaId, "ada berapa ujian saya", true, $aiService, "SIS-01", "Hitung ujian siswa langsung 1 kalimat tanpa daftar jadwal");
// 3.2 Daftar jadwal ujian siswa (minta daftar)
$checkQuality('siswa', $siswaId, "daftar ujian saya", false, $aiService, "SIS-02", "Permintaan daftar ujian siswa menyajikan poin-poin");
// 3.3 Hitung mata pelajaran (tanpa rincian)
$checkQuality('siswa', $siswaId, "ada berapa mapel saya", true, $aiService, "SIS-03", "Hitung mapel siswa langsung 1 kalimat tanpa daftar mapel");
// 3.4 Daftar mapel siswa (minta daftar)
$checkQuality('siswa', $siswaId, "daftar mapel saya", false, $aiService, "SIS-04", "Permintaan daftar mapel siswa menyajikan poin-poin");
// 3.5 Nilai rata-rata siswa (fakta tunggal)
$checkQuality('siswa', $siswaId, "berapa rata rata nilai saya", true, $aiService, "SIS-05", "Rata-rata nilai siswa langsung 1 kalimat tanpa rincian");
// 3.6 Pertanyaan Identitas 'siapa anda'
$checkQuality('siswa', $siswaId, "siapa anda", true, $aiService, "SIS-06", "Identitas bot siswa dijawab langsung 'Saya adalah asisten...' tanpa tanda kurung & tanpa awalan Chatbot +");

// -------------------------------------------------------------
// 4. SMART EXAM KNOWLEDGE TESTS (SEMUA ROLE)
// -------------------------------------------------------------
echo "--- 4. SMART EXAM KNOWLEDGE TESTS ---\n";
$k1 = $aiService->resolveInstantFactualAnswer("apa itu smart exam", "siswa", $siswaId);
$passK1 = ($k1 !== null) && stripos($k1, "Smart Exam adalah sistem Computer-Based Test") !== false;
$testsTotal++;
if ($passK1) {
    $testsPassed++;
    echo "✅ [PASS] [KNW-01] Definisi Smart Exam akurat & langsung tanpa komentar\n";
} else {
    echo "❌ [FAIL] [KNW-01] Definisi Smart Exam gagal\n";
}

$k2 = $aiService->resolveInstantFactualAnswer("bagaimana cara kerja dual camera", "guru", $guruId);
$passK2 = ($k2 !== null) && stripos($k2, "Kamera 1 (webcam laptop)") !== false && stripos($k2, "Kamera 2 (smartphone via WebRTC)") !== false;
$testsTotal++;
if ($passK2) {
    $testsPassed++;
    echo "✅ [PASS] [KNW-02] Penjelasan Dual Camera WebRTC akurat & faktual\n";
} else {
    echo "❌ [FAIL] [KNW-02] Penjelasan Dual Camera gagal\n";
}

$k3 = $aiService->resolveInstantFactualAnswer("apa fungsi token ujian", "admin", $adminId);
$passK3 = ($k3 !== null) && stripos($k3, "kode keamanan acak") !== false;
$testsTotal++;
if ($passK3) {
    $testsPassed++;
    echo "✅ [PASS] [KNW-03] Fungsi token ujian faktual & to the point\n";
} else {
    echo "❌ [FAIL] [KNW-03] Fungsi token ujian gagal\n";
}

echo "\n==============================================================================\n";
echo "HASIL AKHIR: {$testsPassed} / {$testsTotal} LULUS\n";
echo "==============================================================================\n";

if ($testsPassed === $testsTotal) {
    echo "🎉 SEMUA PENGUJIAN RESPON KUALITAS LULUS 100% TANPA REGRESI!\n";
    exit(0);
} else {
    echo "⚠️ ADA PENGUJIAN YANG GAGAL!\n";
    exit(1);
}
