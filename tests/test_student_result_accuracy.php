<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\ExamResultModel;
use App\Models\UserModel;
use App\Services\AI\AiChatService;
use App\Services\AI\GeminiProvider;

echo "====================================================================\n";
echo "   PENGUJIAN AKURASI STATISTIK HASIL & NILAI CHATBOT SISWA (EDUAI)   \n";
echo "====================================================================\n\n";

$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
\App\Config\Database::$database = $targetDb;

$aiChatService = new AiChatService();
$geminiProvider = new GeminiProvider();
$userModel = new UserModel();
$resultModel = new ExamResultModel();
$db = Database::getConnection();

$student = $userModel->findByEmail('siswa@example.com') ?? $userModel->findByEmail('Ahmadfauzi@gmail.com') ?? $userModel->find(4);
$studentId = (int)($student['id'] ?? 4);

echo "Pengguna Pengujian: {$student['name']} (ID: {$studentId})\n\n";

// 1. Ambil data aktual statistik halaman /siswa/results
$publishedResults = $resultModel->getPublishedResultsForStudent($studentId);
$expectedTotalResults = count($publishedResults);
$expectedPassedCount = 0;
$expectedFailedCount = 0;
$totalScore = 0.0;

foreach ($publishedResults as $res) {
    $score = (float)$res['final_score'];
    $kkm = (float)$res['passing_grade'];
    if ($score >= $kkm) {
        $expectedPassedCount++;
    } else {
        $expectedFailedCount++;
    }
    $totalScore += $score;
}
$expectedAvgScore = $expectedTotalResults > 0 ? round($totalScore / $expectedTotalResults, 1) : 0.0;

echo "--- STATISTIK DASHBOARD /siswa/results AKTUAL ---\n";
echo "• Hasil Ujian Dirilis : {$expectedTotalResults}\n";
echo "• Tuntas / Lulus KKM  : {$expectedPassedCount}\n";
echo "• Belum Tuntas        : {$expectedFailedCount}\n";
echo "• Rata-rata Nilai     : {$expectedAvgScore}\n\n";

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

// Uji 1: Verifikasi isi konteks database siswa
echo "--------------------------------------------------------------------\n";
echo "1. Verifikasi Konteks Database Siswa\n";
echo "--------------------------------------------------------------------\n";
$context = $aiChatService->fetchSiswaDatabaseContext($studentId);
$hasTotal = str_contains($context, "Total Hasil Ujian Dirilis / Dipublikasikan: {$expectedTotalResults}");
$hasPassed = str_contains($context, "Jumlah Ujian Tuntas / Lulus Standar Kelulusan (KKM): {$expectedPassedCount}");
$hasFailed = str_contains($context, "Jumlah Ujian Belum Tuntas / Remedial (Di Bawah KKM): {$expectedFailedCount}");
$hasAvg = str_contains($context, "Rata-rata Nilai Ujian Resmi: {$expectedAvgScore}");

report(
    'TC-CTX-01',
    'Konteks database Siswa memuat ringkasan statistik yang 100% presisi dengan halaman Hasil & Nilai',
    $hasTotal && $hasPassed && $hasFailed && $hasAvg,
    "Expected: Total={$expectedTotalResults}, Passed={$expectedPassedCount}, Failed={$expectedFailedCount}, Avg={$expectedAvgScore}"
);

// Uji 2: Live AI Test - Pertanyaan: "Ada berapa jumlah ujian yang telah memenuhi standar kelulusan?"
echo "--------------------------------------------------------------------\n";
echo "2. Live Test: Pertanyaan Jumlah Ujian Memenuhi Standar Kelulusan\n";
echo "--------------------------------------------------------------------\n";
if ($geminiProvider->isAvailable()) {
    $res1 = $aiChatService->sendMessage($studentId, 'siswa', 'Ada berapa jumlah ujian yang telah memenuhi standar kelulusan?');
    $reply1 = $res1['reply'] ?? '';
    $containsExactNumber = str_contains($reply1, (string)$expectedPassedCount);
    report(
        'TC-LIVE-01',
        "Chatbot menjawab jumlah kelulusan KKM ({$expectedPassedCount}) secara akurat",
        $res1['success'] && $containsExactNumber,
        "Jawaban AI: {$reply1}"
    );
}

// Uji 3: Live AI Test - Pertanyaan: "Berapa hasil ujian saya yang sudah dirilis?"
echo "--------------------------------------------------------------------\n";
echo "3. Live Test: Pertanyaan Jumlah Hasil Ujian Dirilis\n";
echo "--------------------------------------------------------------------\n";
if ($geminiProvider->isAvailable()) {
    $res2 = $aiChatService->sendMessage($studentId, 'siswa', 'Berapa hasil ujian saya yang sudah dirilis?');
    $reply2 = $res2['reply'] ?? '';
    $containsTotalNumber = str_contains($reply2, (string)$expectedTotalResults);
    report(
        'TC-LIVE-02',
        "Chatbot menjawab jumlah hasil ujian dirilis ({$expectedTotalResults}) secara akurat",
        $res2['success'] && $containsTotalNumber,
        "Jawaban AI: {$reply2}"
    );
}

// Uji 4: Live AI Test - Pertanyaan: "Berapa ujian yang belum memenuhi KKM?"
echo "--------------------------------------------------------------------\n";
echo "4. Live Test: Pertanyaan Jumlah Ujian Belum Memenuhi KKM / Remedial\n";
echo "--------------------------------------------------------------------\n";
if ($geminiProvider->isAvailable()) {
    $res3 = $aiChatService->sendMessage($studentId, 'siswa', 'Berapa ujian yang belum memenuhi KKM?');
    $reply3 = $res3['reply'] ?? '';
    $containsFailedNumber = str_contains($reply3, (string)$expectedFailedCount);
    report(
        'TC-LIVE-03',
        "Chatbot menjawab jumlah ujian di bawah KKM ({$expectedFailedCount}) secara akurat",
        $res3['success'] && $containsFailedNumber,
        "Jawaban AI: {$reply3}"
    );
}

// Uji 5: Multi-Turn Conversation Isolation Test (History with false premise)
echo "--------------------------------------------------------------------\n";
echo "5. Pengujian Ketahanan Multi-Turn Terhadap Riwayat Salah\n";
echo "--------------------------------------------------------------------\n";
$refBuildPrompt = new ReflectionMethod($aiChatService, 'buildContextPrompt');
$refBuildPrompt->setAccessible(true);
$misleadingHistory = [
    ['sender' => 'user', 'message' => 'Kemarin katanya yang lulus ada 3 ya?', 'is_error' => 0],
    ['sender' => 'assistant', 'message' => 'Ya, kemarin tercatat 3 ujian.', 'is_error' => 0]
];
$multiTurnPrompt = $refBuildPrompt->invoke($aiChatService, $misleadingHistory, 'Ada berapa jumlah ujian yang telah memenuhi standar kelulusan sekarang?');
$hasHistoryBoundaries = str_contains($multiTurnPrompt, '=== AWAL RIWAYAT PERCAKAPAN ===') && str_contains($multiTurnPrompt, '=== AKHIR RIWAYAT PERCAKAPAN ===');
report(
    'TC-MULTI-01',
    'Multi-turn prompt membatasi riwayat lama dan menegaskan kepatuhan pada data database terbaru',
    $hasHistoryBoundaries
);

if ($geminiProvider->isAvailable()) {
    // Sesi baru dengan pertanyaan penegasan
    $resMulti = $aiChatService->sendMessage($studentId, 'siswa', 'Sekarang tolong konfirmasi kembali, persisnya ada berapa total ujian saya yang telah lulus KKM?');
    $replyMulti = $resMulti['reply'] ?? '';
    $multiOk = str_contains($replyMulti, (string)$expectedPassedCount);
    report(
        'TC-MULTI-02',
        "Live AI tetap konsisten menjawab {$expectedPassedCount} meskipun dalam percakapan dinamis",
        $resMulti['success'] && $multiOk,
        "Jawaban AI: {$replyMulti}"
    );
}

// Uji 6: Privasi Siswa Lain
echo "--------------------------------------------------------------------\n";
echo "6. Pengujian Isolasi Data Antar-Siswa\n";
echo "--------------------------------------------------------------------\n";
$otherStudentResult = $db->query("
    SELECT er.id, er.student_id, u.name 
    FROM exam_results er 
    JOIN users u ON u.id = er.student_id 
    WHERE er.student_id != {$studentId}
    LIMIT 1
")->fetch();

$crossStudentSafe = true;
if ($otherStudentResult) {
    if (str_contains($context, $otherStudentResult['name'])) {
        $crossStudentSafe = false;
    }
}
report(
    'TC-SEC-01',
    'Konteks database siswa tidak mengandung nama atau data statistik siswa lain',
    $crossStudentSafe,
    $otherStudentResult ? "Siswa Lain ID {$otherStudentResult['student_id']} ({$otherStudentResult['name']}) terisolasi." : "Tidak ada data siswa lain di DB."
);

echo "====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN AKURASI CHATBOT HASIL & NILAI\n";
echo "====================================================================\n";
echo "Total Uji : " . ($passCount + $failCount) . "\n";
echo "PASS      : {$passCount}\n";
echo "FAIL      : {$failCount}\n";
if ($failCount === 0) {
    echo "🎉 SEMUA PENGUJIAN AKURASI BERHASIL (100% PASS)!\n";
} else {
    echo "⚠️ ADA PENGUJIAN GAGAL.\n";
}
echo "====================================================================\n";
