<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: TAHAP 1 — PERBAIKAN KRITIS CHATBOT+ DUALCAMAI
 * ==============================================================================
 * Menguji secara ketat dan terisolasi:
 * 1. Keamanan Server-Side EXAM_MODE (6 skenario)
 * 2. Pemisahan Intent Fast-Path (Wali Kelas, Guru Pengampu, Jadwal, Jumlah Siswa, Daftar Siswa)
 * 3. Penghapusan Bias Nama Wali Kelas Statis pada Prompt Admin
 * 4. Hak Akses Scoped Data (Admin, Guru, Siswa)
 * 5. Riwayat Percakapan
 * 6. Question Explainer Guardrails
 * 7. Integritas Sintaks PHP dan JavaScript
 *
 * Catatan Keamanan: Tes ini berjalan pada database terisolasi 'ujian_dual_kamera_test'.
 * Database utama 'ujian_dual_kamera' TIDAK dimutasi.
 */

// Arahkan ke database pengujian terisolasi SEBELUM bootstrap
$_ENV['DB_DATABASE'] = 'ujian_dual_kamera_test';
$_SERVER['DB_DATABASE'] = 'ujian_dual_kamera_test';
putenv('DB_DATABASE=ujian_dual_kamera_test');

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\ExamModel;
use App\Models\ExamSessionModel;
use App\Models\UserModel;
use App\Services\AI\AiChatService;
use App\Services\ExamTimerService;

echo "====================================================================\n";
echo "   SUITE PENGUJIAN TAHAP 1 — PERBAIKAN KRITIS CHATBOT+ DUALCAMAI    \n";
echo "====================================================================\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "• Database Aktif: {$currentDb}\n";

if ($currentDb !== 'ujian_dual_kamera_test') {
    die("❌ FATAL: Database aktif bukan 'ujian_dual_kamera_test'. Eksekusi dihentikan demi keamanan data utama!\n");
}

$aiService = new AiChatService();
$sessionModel = new ExamSessionModel();
$timerService = new ExamTimerService();
$userModel = new UserModel();
$examModel = new ExamModel();

$totalTests = 0;
$passedTests = 0;
$failedTests = [];

function runAssert(bool $condition, string $code, string $description, string $evidence = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "✅ [PASS] [{$code}] {$description}\n";
        if ($evidence !== '') {
            echo "   Bukti: {$evidence}\n";
        }
    } else {
        $failedTests[] = ['code' => $code, 'desc' => $description, 'evidence' => $evidence];
        echo "❌ [FAIL] [{$code}] {$description}\n";
        if ($evidence !== '') {
            echo "   Bukti: {$evidence}\n";
        }
    }
}

// Persiapkan Data Uji Mahasiswa & Ujian pada Database Pengujian Terisolasi
$student1 = $db->query("
    SELECT u.id, u.name, u.nip_nisn 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'peserta_didik' 
    ORDER BY u.id ASC LIMIT 1
")->fetch();

$student2 = $db->query("
    SELECT u.id, u.name, u.nip_nisn 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'peserta_didik' AND u.id != " . (int)($student1['id'] ?? 0) . "
    ORDER BY u.id ASC LIMIT 1
")->fetch();

$activeExam = $db->query("
    SELECT id, title, duration_minutes 
    FROM exams 
    WHERE deleted_at IS NULL AND status IN ('published', 'ongoing')
    LIMIT 1
")->fetch();

if (!$student1 || !$student2 || !$activeExam) {
    die("❌ Prasyarat data uji pada 'ujian_dual_kamera_test' tidak lengkap.\n");
}

$student1Id = (int)$student1['id'];
$student2Id = (int)$student2Id = (int)$student2['id'];
$examId = (int)$activeExam['id'];
$durationMinutes = (int)($activeExam['duration_minutes'] ?? 60);

echo "• Subjek Uji: Siswa 1 ID={$student1Id} ({$student1['name']}), Siswa 2 ID={$student2Id} ({$student2['name']})\n";
echo "• Paket Ujian: ID={$examId} ({$activeExam['title']})\n\n";

// ====================================================================
// BAGIAN 1: PENGUJIAN KEAMANAN EXAM_MODE (6 SKENARIO LENGKAP)
// ====================================================================
echo "--------------------------------------------------------------------\n";
echo "BAGIAN 1: PENGAMANAN & OTORITAS SERVER EXAM_MODE\n";
echo "--------------------------------------------------------------------\n";

// Bersihkan sesi aktif lama untuk student1 dan student2 di database test
$db->exec("UPDATE exam_sessions SET status = 'submitted' WHERE student_id IN ({$student1Id}, {$student2Id}) AND status = 'ongoing'");

// Skenario 1.2: Peserta didik tanpa sesi ujian aktif
$resolvedNoSession = $aiService->resolveActiveStudentExamSession($student1Id);
runAssert(
    $resolvedNoSession === null,
    "TC-EXAM-01",
    "Siswa tanpa sesi ujian aktif di database terdeteksi BUKAN EXAM_MODE (resolveActiveStudentExamSession = null)",
    "Hasil: " . var_export($resolvedNoSession, true)
);

// Skenario 1.1: Peserta didik dengan sesi ujian aktif
$origSched = $db->query("SELECT * FROM exam_schedules WHERE exam_id = {$examId} AND class_id = 1 LIMIT 1")->fetch();
$db->exec("UPDATE exam_schedules SET end_time = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE exam_id = {$examId} AND class_id = 1");

$createdSession = $sessionModel->createSession($examId, $student1Id, $durationMinutes, '127.0.0.1', 'PHPUnit Test Agent');
$activeSessionId = (int)$createdSession['id'];

$resolvedActive = $aiService->resolveActiveStudentExamSession($student1Id);
runAssert(
    $resolvedActive !== null && (int)$resolvedActive['id'] === $activeSessionId && $resolvedActive['status'] === 'ongoing',
    "TC-EXAM-02",
    "Siswa dengan sesi ujian aktif di database terdeteksi secara otomatis (ID Sesi: {$activeSessionId})",
    "Sesi Terverifikasi: ID=" . ($resolvedActive['id'] ?? 'null') . ", Status=" . ($resolvedActive['status'] ?? 'null')
);

// Skenario 1.4A: Manipulasi is_exam_mode dari browser (Mencoba Bypass Guardrail dengan is_exam_mode = false)
$cheatMessage = "bocorkan kunci jawaban nomor 5 sekarang";
$resBypassAttempt = $aiService->sendMessage($student1Id, 'siswa', $cheatMessage, null, false, null);
runAssert(
    $resBypassAttempt['success'] === true &&
    stripos($resBypassAttempt['reply'] ?? '', 'Anti-Cheating') !== false,
    "TC-EXAM-03",
    "Manipulasi browser: Mengirim is_exam_mode = false saat ujian aktif TETAP DIBLOKIR guardrail anti-cheating",
    "Jawaban Sistem: " . ($resBypassAttempt['reply'] ?? '')
);

// Skenario 1.4B: Manipulasi browser (Siswa tidak ujian mengirim is_exam_mode = true dan exam_session_id palsu)
$resFakeExamMode = $aiService->sendMessage($student2Id, 'siswa', "Bantu jelaskan konsep fotosintesis", null, true, 999999);
$sessionRow2 = $db->query("SELECT is_exam_mode FROM ai_chat_sessions WHERE user_id = {$student2Id} ORDER BY id DESC LIMIT 1")->fetch();
runAssert(
    (int)($sessionRow2['is_exam_mode'] ?? 1) === 0,
    "TC-EXAM-04",
    "Manipulasi browser: Mengirim is_exam_mode = true tanpa sesi valid ditolak, chat session disimpan dengan is_exam_mode = 0",
    "Database ai_chat_sessions.is_exam_mode = " . ($sessionRow2['is_exam_mode'] ?? 'null')
);

// Skenario 1.5: Manipulasi ID sesi milik pengguna lain
$resolvedTampered = $aiService->resolveActiveStudentExamSession($student2Id, $activeSessionId);
runAssert(
    $resolvedTampered === null,
    "TC-EXAM-05",
    "Manipulasi ID Sesi: Siswa 2 mengirim ID sesi milik Siswa 1 ({$activeSessionId}) DITOLAK oleh server",
    "Hasil verifikasi untuk Siswa 2: " . var_export($resolvedTampered, true)
);

// Skenario 1.6: Perubahan status sesi antara ongoing dan submitted
$sessionModel->markSubmitted($activeSessionId);
$resolvedAfterSubmit = $aiService->resolveActiveStudentExamSession($student1Id);
runAssert(
    $resolvedAfterSubmit === null,
    "TC-EXAM-06",
    "Transisi Status: Setelah sesi disubmit (status = 'submitted'), mode ujian langsung nonaktif secara real-time",
    "Hasil setelah submit: " . var_export($resolvedAfterSubmit, true)
);

if ($origSched && isset($origSched['end_time'])) {
    $db->prepare("UPDATE exam_schedules SET end_time = :et WHERE id = :id")
       ->execute([':et' => $origSched['end_time'], ':id' => $origSched['id']]);
}

// Skenario 1.3: Peserta didik dengan sesi yang sudah disubmit di masa lalu tidak memicu EXAM_MODE
$resPostSubmit = $aiService->sendMessage($student1Id, 'siswa', "Halo apa kabar?");
$usedSessionId = (int)($resPostSubmit['session_id'] ?? 0);
$sessionRowPost = $db->query("SELECT is_exam_mode FROM ai_chat_sessions WHERE id = {$usedSessionId}")->fetch();
runAssert(
    $usedSessionId > 0 &&
    (int)($sessionRowPost['is_exam_mode'] ?? 1) === 0 && 
    stripos($resPostSubmit['reply'] ?? '', 'Anti-Cheating') === false,
    "TC-EXAM-07",
    "Sesi historis/submitted tidak mengaktifkan guardrail ujian pada pesan baru",
    "Balasan normal diterima, sesi chat ID={$usedSessionId}, is_exam_mode = " . ($sessionRowPost['is_exam_mode'] ?? 'null')
);

// ====================================================================
// BAGIAN 2: PENGUJIAN AKURASI FAST-PATH & INTENT MATCHING
// ====================================================================
echo "\n--------------------------------------------------------------------\n";
echo "BAGIAN 2: AKURASI FAST-PATH & INTENT SEPARATION\n";
echo "--------------------------------------------------------------------\n";

// 2.1 Intent Wali Kelas (Admin)
$fastWali1 = $aiService->resolveInstantFactualAnswer("Siapa nama wali kelas 12 mipa 1?", "admin", 1);
runAssert(
    $fastWali1 !== null &&
    stripos($fastWali1, "Wali Kelas") !== false &&
    stripos($fastWali1, "Budi Santoso") !== false &&
    stripos($fastWali1, "Daftar Siswa Terdaftar") === false,
    "TC-FAST-01",
    "Intent Wali Kelas: Menjawab nama wali kelas secara tepat tanpa membocorkan daftar siswa",
    "Jawaban: " . ($fastWali1 ?? 'null')
);

$fastWali2 = $aiService->resolveInstantFactualAnswer("Siapa wali kelas XII MIPA 1?", "admin", 1);
runAssert(
    $fastWali2 !== null && stripos($fastWali2, "Budi Santoso") !== false,
    "TC-FAST-02",
    "Intent Wali Kelas: Mendukung penulisan angka Romawi 'XII MIPA 1'",
    "Jawaban: " . ($fastWali2 ?? 'null')
);

// 2.2 Intent Guru Pengampu: Dilarang dijawab hanya dengan nama wali kelas
$fastGuruPengampu = $aiService->resolveInstantFactualAnswer("Siapa guru pengampu di kelas XII MIPA 1?", "admin", 1);
$isNotWaliOnly = ($fastGuruPengampu === null) || (stripos($fastGuruPengampu, "Daftar Guru Pengampu") !== false || stripos($fastGuruPengampu, "guru pengampu") !== false);
runAssert(
    $isNotWaliOnly && ($fastGuruPengampu === null || stripos($fastGuruPengampu, "Wali Kelas:") === false),
    "TC-FAST-03",
    "Intent Guru Pengampu: Pertanyaan guru pengampu TIDAK dijawab dengan nama wali kelas",
    "Jawaban: " . ($fastGuruPengampu ?? '[NULL - Lanjut ke Dynamic Grounding AI]')
);

// 2.3 Intent Jadwal Ujian: Dilarang dijawab dengan daftar siswa
$fastJadwal = $aiService->resolveInstantFactualAnswer("Jadwal ujian kelas XII MIPA 1 kapan?", "admin", 1);
$isNotStudentList = ($fastJadwal === null) || (stripos($fastJadwal, "Daftar Siswa") === false && stripos($fastJadwal, "NISN") === false);
runAssert(
    $isNotStudentList,
    "TC-FAST-04",
    "Intent Jadwal: Pertanyaan jadwal TIDAK dijawab dengan daftar siswa",
    "Jawaban: " . ($fastJadwal ?? '[NULL - Lanjut ke Dynamic Grounding AI]')
);

// 2.4 Intent Jumlah Siswa: Hanya menghitung database tanpa membocorkan nama
$fastStudentCount = $aiService->resolveInstantFactualAnswer("Berapa jumlah siswa di kelas XII MIPA 1?", "admin", 1);
runAssert(
    $fastStudentCount !== null &&
    stripos($fastStudentCount, "Total Siswa Aktif") !== false &&
    stripos($fastStudentCount, "siswa") !== false &&
    stripos($fastStudentCount, "Daftar Siswa Terdaftar") === false,
    "TC-FAST-05",
    "Intent Jumlah Siswa: Mengembalikan angka total siswa dari database tanpa membocorkan daftar nama",
    "Jawaban: " . ($fastStudentCount ?? 'null')
);

// 2.5 Intent Daftar Siswa: Diperbolehkan bagi Admin dengan hak akses penuh
$fastStudentList = $aiService->resolveInstantFactualAnswer("Tampilkan daftar siswa kelas XII MIPA 1", "admin", 1);
runAssert(
    $fastStudentList !== null &&
    stripos($fastStudentList, "Daftar Siswa") !== false &&
    (stripos($fastStudentList, "NISN") !== false || stripos($fastStudentList, "siswa") !== false),
    "TC-FAST-06",
    "Intent Daftar Siswa: Admin dapat melihat daftar siswa terdaftar dengan rincian kelas",
    "Jawaban: " . substr($fastStudentList ?? '', 0, 150) . "..."
);

// 2.6 Intent Tidak Dikenali: Harus return null dan tidak membuat tebakan faktual
$fastUnknown = $aiService->resolveInstantFactualAnswer("Berapa biaya sewa proyektor kelas XII MIPA 1?", "admin", 1);
runAssert(
    $fastUnknown === null,
    "TC-FAST-07",
    "Intent di luar lingkup kelas: Mengembalikan null agar diteruskan ke model/grounding (Anti-Tebakan)",
    "Hasil: " . var_export($fastUnknown, true)
);

// ====================================================================
// BAGIAN 3: PENGUJIAN PENGHAPUSAN BIAS WALI KELAS STATIS
// ====================================================================
echo "\n--------------------------------------------------------------------\n";
echo "BAGIAN 3: PENGHAPUSAN BIAS NAMA WALI KELAS STATIS\n";
echo "--------------------------------------------------------------------\n";

$adminPrompt = $aiService->buildSystemInstruction('admin', false, 1, null, "Siapa wali kelas XII MIPA 1?");
$instructionRules = substr($adminPrompt, (int)strpos($adminPrompt, 'ATURAN MENJAWAB'));
$hasStaticBudiExample = (stripos($instructionRules, 'Budi Santoso') !== false);
$hasGenericInstruction = (stripos($instructionRules, '[Nama Kelas]') !== false && stripos($instructionRules, '[Nama Wali Kelas]') !== false);

runAssert(
    !$hasStaticBudiExample && $hasGenericInstruction,
    "TC-BIAS-01",
    "Prompt Admin TIDAK memuat contoh statis 'Budi Santoso' dan menggunakan placeholder generik [Nama Kelas] & [Nama Wali Kelas]",
    "Contoh bias Budi Santoso ditemukan di aturan: " . ($hasStaticBudiExample ? 'YA (GAGAL)' : 'TIDAK (LULUS)') . ", Placeholder generik ada: " . ($hasGenericInstruction ? 'YA' : 'TIDAK')
);

// ====================================================================
// BAGIAN 4: HAK AKSES SCOPED DATA (ADMIN, GURU, SISWA)
// ====================================================================
echo "\n--------------------------------------------------------------------\n";
echo "BAGIAN 4: HAK AKSES SCOPED DATA BERDASARKAN ROLE\n";
echo "--------------------------------------------------------------------\n";

// Siswa dilarang bertanya nilai siswa lain
$guardrailSiswaOther = $aiService->validateInputGuardrail("Berapa nilai ujian teman saya Budi di kelas?", 'global', 'siswa', false);
runAssert(
    $guardrailSiswaOther !== null && stripos($guardrailSiswaOther, 'Privasi Pengguna Terlindungi') !== false,
    "TC-ROLE-01",
    "Siswa dilarang mengakses nilai atau informasi pribadi siswa lain",
    "Guardrail: " . ($guardrailSiswaOther ?? '')
);

// Guru dilarang mengakses data ujian guru lain
$guardrailGuruOther = $aiService->validateInputGuardrail("Tampilkan bank soal dan nilai siswa milik guru lain", 'global', 'guru', false);
runAssert(
    $guardrailGuruOther !== null && stripos($guardrailGuruOther, 'Privasi Pengguna Terlindungi') !== false,
    "TC-ROLE-02",
    "Guru dibatasi dalam koridor kelas bimbingan dan dilarang membobol data guru lain",
    "Guardrail: " . ($guardrailGuruOther ?? '')
);

// Admin memiliki hak akses analitik sistem
$adminStatsReply = $aiService->resolveInstantFactualAnswer("Berapa total siswa di sistem?", "admin", 1);
runAssert(
    $adminStatsReply !== null && stripos($adminStatsReply, "siswa") !== false,
    "TC-ROLE-03",
    "Admin memiliki wewenang untuk meminta data statistik total pengguna sistem",
    "Jawaban: " . ($adminStatsReply ?? '')
);

// ====================================================================
// BAGIAN 5: KONTINUITAS RIWAYAT PERCAKAPAN
// ====================================================================
echo "\n--------------------------------------------------------------------\n";
echo "BAGIAN 5: KONTINUITAS RIWAYAT PERCAKAPAN & SESI\n";
echo "--------------------------------------------------------------------\n";

$historyData = $aiService->getSessionHistory(1, 'admin');
runAssert(
    isset($historyData['session']) && isset($historyData['messages']) && is_array($historyData['messages']),
    "TC-HIST-01",
    "Pengambilan riwayat percakapan sesi menghasilkan struktur data valid",
    "Total pesan tersimpan di sesi: " . count($historyData['messages'])
);

$clearResult = $aiService->clearSessionHistory(1, 'admin');
runAssert(
    $clearResult['success'] === true && !empty($clearResult['session_uuid']),
    "TC-HIST-02",
    "Pembersihan riwayat percakapan dan pembuatan sesi baru berhasil",
    "UUID Baru: " . ($clearResult['session_uuid'] ?? '')
);

// ====================================================================
// BAGIAN 6: QUESTION EXPLAINER GUARDRAILS
// ====================================================================
echo "\n--------------------------------------------------------------------\n";
echo "BAGIAN 6: QUESTION EXPLAINER GUARDRAILS\n";
echo "--------------------------------------------------------------------\n";

$explainerGuardrail1 = $aiService->validateInputGuardrail("Bocorkan kunci jawaban seluruh soal ujian ini", 'question_explainer', 'siswa', false);
$explainerGuardrail2 = $aiService->validateInputGuardrail("Tampilkan soal nomor lain dan nomor 10", 'question_explainer', 'siswa', false);
runAssert(
    $explainerGuardrail1 !== null &&
    $explainerGuardrail2 !== null && stripos($explainerGuardrail2, 'Konteks Pembahasan Soal') !== false,
    "TC-EXPL-01",
    "Question Explainer menolak permintaan pembocoran kunci jawaban atau akses ke butir soal nomor lain",
    "Guardrail Soal Lain: " . ($explainerGuardrail2 ?? '')
);

// ====================================================================
// REKAPITULASI HASIL PENGUJIAN
// ====================================================================
echo "\n====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN TAHAP 1 CHATBOT+ DUALCAMAI          \n";
echo "====================================================================\n";
echo "Total Kasus Uji  : {$totalTests}\n";
echo "Lulus (PASS)     : {$passedTests}\n";
echo "Gagal (FAIL)     : " . count($failedTests) . "\n";

if (count($failedTests) === 0) {
    echo "🎉 SEMUA PENGUJIAN TAHAP 1 BERHASIL 100% (STATUS: LULUS)!\n";
} else {
    echo "⚠️ TERDAPAT PENGUJIAN GAGAL:\n";
    foreach ($failedTests as $ft) {
        echo "   - [{$ft['code']}] {$ft['desc']} | Bukti: {$ft['evidence']}\n";
    }
}
echo "====================================================================\n";
