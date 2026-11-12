<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\ClassModel;
use App\Services\AI\AiChatService;

echo "====================================================================\n";
echo "   PENGUJIAN AKURASI CHATBOT ADMIN — DATA WALI KELAS DUALCAMAI      \n";
echo "====================================================================\n\n";

$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
\App\Config\Database::$database = $targetDb;

$db = Database::getConnection();
$aiService = new AiChatService();
$classModel = new ClassModel();

$totalTests = 0;
$passedTests = 0;

function assertCondition(bool $condition, string $testCode, string $description, string $evidence = '') {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "✅ [PASS] [{$testCode}] {$description}\n";
        if ($evidence) echo "   Bukti: {$evidence}\n";
    } else {
        echo "❌ [FAIL] [{$testCode}] {$description}\n";
        if ($evidence) echo "   Bukti: {$evidence}\n";
    }
}

// --------------------------------------------------------------------
// 1. Verifikasi Data Grounding dari Database Aktual
// --------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "1. Verifikasi Data Halaman /admin/classes & Database\n";
echo "--------------------------------------------------------------------\n";

$classes = $db->query("
    SELECT 
        c.id, c.name as class_name, c.grade_level, c.major,
        ay.name as academic_year_name,
        u.name as homeroom_teacher_name
    FROM classes c
    LEFT JOIN academic_years ay ON ay.id = c.academic_year_id
    LEFT JOIN users u ON u.id = c.homeroom_teacher_id
    WHERE c.name = 'XII MIPA 1'
    LIMIT 1
")->fetch();

$expectedClassName = $classes['class_name'] ?? 'XII MIPA 1';
$expectedTeacherName = $classes['homeroom_teacher_name'] ?? 'Budi Santoso';
$expectedYear = $classes['academic_year_name'] ?? '2026/2027';

echo "• Data Aktual Database: Kelas {$expectedClassName}, Tahun Ajaran {$expectedYear}, Wali Kelas: {$expectedTeacherName}\n\n";

$adminContext = $aiService->fetchAdminDatabaseContext(1);

assertCondition(
    strpos($adminContext, "Kelas XII MIPA 1") !== false &&
    strpos($adminContext, "Budi Santoso") !== false &&
    strpos($adminContext, "Tahun Ajaran 2026/2027") !== false,
    "TC-CTX-CLASS-01",
    "Konteks Admin memuat rincian nama kelas, tahun ajaran, dan nama wali kelas secara presisi",
    "Admin Context matches database: Kelas={$expectedClassName}, Wali={$expectedTeacherName}, TA={$expectedYear}"
);

// --------------------------------------------------------------------
// 2. Live Test 1: 'Siapa nama wali kelas 12 mipa 1?'
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "2. Live Test: 'Siapa nama wali kelas 12 mipa 1?'\n";
echo "--------------------------------------------------------------------\n";

$q1 = "Siapa nama wali kelas 12 mipa 1?";
$res1 = $aiService->sendMessage(1, 'admin', $q1);
$reply1 = $res1['reply'] ?? '';

assertCondition(
    $res1['success'] === true &&
    stripos($reply1, 'Budi Santoso') !== false &&
    (stripos($reply1, 'XII MIPA 1') !== false || stripos($reply1, '12 MIPA 1') !== false || stripos($reply1, '12 mipa 1') !== false),
    "TC-LIVE-CLASS-01",
    "Chatbot menjawab nama wali kelas (Budi Santoso) untuk variasi penulisan angka '12 mipa 1'",
    "Jawaban AI: {$reply1}"
);

// --------------------------------------------------------------------
// 3. Live Test 2: 'Siapa wali kelas XII MIPA 1?'
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "3. Live Test: 'Siapa wali kelas XII MIPA 1?'\n";
echo "--------------------------------------------------------------------\n";

$q2 = "Siapa wali kelas XII MIPA 1?";
$res2 = $aiService->sendMessage(1, 'admin', $q2);
$reply2 = $res2['reply'] ?? '';

assertCondition(
    $res2['success'] === true &&
    stripos($reply2, 'Budi Santoso') !== false,
    "TC-LIVE-CLASS-02",
    "Chatbot menjawab nama wali kelas (Budi Santoso) untuk penulisan angka Romawi 'XII MIPA 1'",
    "Jawaban AI: {$reply2}"
);

// --------------------------------------------------------------------
// 4. Live Test 3: 'Siapa guru yang menjadi wali kelas XII MIPA 1?'
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "4. Live Test: 'Siapa guru yang menjadi wali kelas XII MIPA 1?'\n";
echo "--------------------------------------------------------------------\n";

$q3 = "Siapa guru yang menjadi wali kelas XII MIPA 1?";
$res3 = $aiService->sendMessage(1, 'admin', $q3);
$reply3 = $res3['reply'] ?? '';

assertCondition(
    $res3['success'] === true &&
    stripos($reply3, 'Budi Santoso') !== false,
    "TC-LIVE-CLASS-03",
    "Chatbot menjawab guru wali kelas dengan tepat",
    "Jawaban AI: {$reply3}"
);

// --------------------------------------------------------------------
// 5. Live Test 4: 'Siapa wali kelas untuk rombel XII MIPA 1 pada tahun ajaran 2026/2027?'
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "5. Live Test: 'Siapa wali kelas untuk rombel XII MIPA 1 pada tahun ajaran 2026/2027?'\n";
echo "--------------------------------------------------------------------\n";

$q4 = "Siapa wali kelas untuk rombel XII MIPA 1 pada tahun ajaran 2026/2027?";
$res4 = $aiService->sendMessage(1, 'admin', $q4);
$reply4 = $res4['reply'] ?? '';

assertCondition(
    $res4['success'] === true &&
    stripos($reply4, 'Budi Santoso') !== false &&
    (stripos($reply4, '2026/2027') !== false || stripos($reply4, 'XII MIPA 1') !== false),
    "TC-LIVE-CLASS-04",
    "Chatbot menyertakan tahun ajaran dan nama wali kelas secara tepat",
    "Jawaban AI: {$reply4}"
);

// --------------------------------------------------------------------
// 6. Live Test 5: Pertanyaan Kelas yang Tidak Terdaftar di Sistem
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "6. Live Test: Pertanyaan Kelas yang Tidak Terdaftar\n";
echo "--------------------------------------------------------------------\n";

$q5 = "Siapa nama wali kelas untuk rombel X Bahasa 3?";
$res5 = $aiService->sendMessage(1, 'admin', $q5);
$reply5 = $res5['reply'] ?? '';

assertCondition(
    $res5['success'] === true &&
    (stripos($reply5, 'tidak ditemukan') !== false || stripos($reply5, 'tidak terdaftar') !== false || stripos($reply5, 'belum terdaftar') !== false || stripos($reply5, 'tidak tercatat') !== false || stripos($reply5, 'tidak ada') !== false),
    "TC-LIVE-CLASS-05",
    "Chatbot secara jujur menyampaikan bahwa kelas fiktif / tidak terdaftar tidak ditemukan di sistem (anti-halusinasi)",
    "Jawaban AI: {$reply5}"
);

// --------------------------------------------------------------------
// REKAPITULASI
// --------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN AKURASI WALI KELAS ADMIN CHATBOT    \n";
echo "====================================================================\n";
echo "Total Uji : {$totalTests}\n";
echo "PASS      : {$passedTests}\n";
echo "FAIL      : " . ($totalTests - $passedTests) . "\n";
if ($passedTests === $totalTests) {
    echo "🎉 SEMUA PENGUJIAN AKURASI WALI KELAS BERHASIL (100% PASS)!\n";
}
echo "====================================================================\n";
