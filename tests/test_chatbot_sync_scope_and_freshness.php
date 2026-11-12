<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Services\AI\AiChatService;
use App\Models\UserModel;
use App\Models\QuestionBankModel;
use App\Models\ExamModel;

echo "====================================================================\n";
echo "   TEST CHATBOT LIVE DB SYNC, SCOPE RELEVANCE & DATA FRESHNESS     \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$aiChatService = new AiChatService();
$userModel = new UserModel();

$adminUser = $userModel->findByEmail('admin@gmail.com') ?? $userModel->find(2);
$guruUser  = $userModel->findByEmail('Budisantoso@gmail.com') ?? $userModel->find(3);
$siswaUser = $userModel->findByEmail('Ahmadfauzi@gmail.com') ?? $userModel->find(4);

$passed = 0;
$total = 0;

function assertTest(bool $condition, string $title, string $details = ''): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "✅ [PASS] {$title}\n";
        if ($details) echo "   {$details}\n";
    } else {
        echo "❌ [FAIL] {$title}\n";
        if ($details) echo "   {$details}\n";
    }
}

// ------------------------------------------------------------------
// 1. SKENARIO SINKRONISASI OTOMATIS SAAT DATA BANK SOAL DITAMBAH / DIUBAH
// ------------------------------------------------------------------
echo "\n--- 1. SINKRONISASI DATA BANK SOAL BARU & UPDATE REAL-TIME ---\n";

$testBankCode = 'QB-SYNC-' . time();
$initialBankTitle = 'Bank Soal Robotika & AI Otomasi Terapan';

// A. Tambah Bank Soal baru ke database
$db->prepare("
    INSERT INTO question_banks (code, title, subject_id, creator_id, status, created_at, updated_at)
    VALUES (:code, :title, 1, :creator_id, 'active', NOW(), NOW())
")->execute([
    ':code' => $testBankCode,
    ':title' => $initialBankTitle,
    ':creator_id' => $guruUser['id']
]);
$newBankId = (int)$db->lastInsertId();

// Chatbot Guru langsung membaca data bank soal baru
$fastpathGuru1 = $aiChatService->resolveInstantFactualAnswer('apa saja bank soal saya?', 'guru', (int)$guruUser['id']);
assertTest(
    !empty($fastpathGuru1) && str_contains($fastpathGuru1, $initialBankTitle),
    '1.1 Chatbot Guru langsung membaca Bank Soal baru tanpa cache basi',
    "Ditemukan judul: {$initialBankTitle}"
);

// Chatbot Admin langsung membaca data bank soal baru
$fastpathAdmin1 = $aiChatService->resolveInstantFactualAnswer('daftar bank soal di sistem', 'admin', (int)$adminUser['id']);
assertTest(
    !empty($fastpathAdmin1) && str_contains($fastpathAdmin1, $initialBankTitle),
    '1.2 Chatbot Admin langsung membaca Bank Soal baru dalam rekapitulasi sistem',
    "Ditemukan judul di Admin: {$initialBankTitle}"
);

// B. Edit judul Bank Soal di database
$updatedBankTitle = 'Bank Soal Kecerdasan Buatan & Sistem Pakar Modern';
$db->prepare("UPDATE question_banks SET title = :title, updated_at = NOW() WHERE id = :id")->execute([
    ':title' => $updatedBankTitle,
    ':id' => $newBankId
]);

// Chatbot Guru langsung membaca judul yang baru diupdate
$fastpathGuru2 = $aiChatService->resolveInstantFactualAnswer('daftar bank soal saya', 'guru', (int)$guruUser['id']);
assertTest(
    !empty($fastpathGuru2) && str_contains($fastpathGuru2, $updatedBankTitle) && !str_contains($fastpathGuru2, $initialBankTitle),
    '1.3 Chatbot Guru langsung menyinkronkan pengeditan judul bank soal terbaru',
    "Judul baru terbaca: {$updatedBankTitle}"
);

// C. Hapus data uji
$db->prepare("DELETE FROM question_banks WHERE id = :id")->execute([':id' => $newBankId]);
$fastpathGuru3 = $aiChatService->resolveInstantFactualAnswer('apa saja bank soal saya?', 'guru', (int)$guruUser['id']);
assertTest(
    !empty($fastpathGuru3) && !str_contains($fastpathGuru3, $updatedBankTitle),
    '1.4 Data bank soal yang dihapus langsung hilang secara real-time dari respon chatbot'
);

// ------------------------------------------------------------------
// 2. SKENARIO SINKRONISASI JADWAL UJIAN BARU & UPDATE REAL-TIME
// ------------------------------------------------------------------
echo "\n--- 2. SINKRONISASI JADWAL UJIAN SISWA & GURU SECARA REAL-TIME ---\n";

// Ambil kelas siswa Ahmad Fauzi (XII MIPA 1)
$cStmt = $db->prepare("SELECT class_id FROM class_students WHERE student_id = :uid AND status = 'active' LIMIT 1");
$cStmt->execute([':uid' => $siswaUser['id']]);
$siswaClassId = (int)$cStmt->fetchColumn();

// A. Siswa bertanya jadwal ujian
$fastpathSiswaJadwal = $aiChatService->resolveInstantFactualAnswer('jadwal ujian saya apa saja?', 'siswa', (int)$siswaUser['id']);
assertTest(
    !empty($fastpathSiswaJadwal) && str_contains($fastpathSiswaJadwal, 'Total Ujian Terjadwal') && str_contains($fastpathSiswaJadwal, '* **'),
    '2.1 Fastpath Siswa membaca seluruh jadwal ujian resmi kelasnya dengan format bullet point rapi',
    "Snippet: " . substr($fastpathSiswaJadwal ?? '', 0, 160) . "..."
);

// B. Tambah jadwal ujian baru untuk kelas siswa
$testStartTime = date('Y-m-d H:i:s', strtotime('+2 days 10:00:00'));
$testEndTime   = date('Y-m-d H:i:s', strtotime('+2 days 12:00:00'));
// Ambil salah satu exam id yang berstatus aktif/terjadwal
$examRow = $db->query("SELECT id, title FROM exams WHERE deleted_at IS NULL AND status IN ('published', 'ongoing', 'scheduled') LIMIT 1")->fetch();
$examId = (int)$examRow['id'];
$examTitle = $examRow['title'];

$db->prepare("
    INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, created_at, updated_at)
    VALUES (:eid, :cid, :start, :end, NOW(), NOW())
")->execute([
    ':eid' => $examId,
    ':cid' => $siswaClassId,
    ':start' => $testStartTime,
    ':end' => $testEndTime
]);
$newScheduleId = (int)$db->lastInsertId();

$monthsEn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthsId = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

// Siswa cek jadwal lagi -> langsung membaca jadwal baru
$fastpathSiswaJadwal2 = $aiChatService->resolveInstantFactualAnswer('kapan jadwal ujian ' . mb_strtolower($examTitle) . '?', 'siswa', (int)$siswaUser['id']);
$expectedStartFmt = str_replace($monthsEn, $monthsId, date('d M Y H:i', strtotime($testStartTime)));
assertTest(
    !empty($fastpathSiswaJadwal2) && str_contains($fastpathSiswaJadwal2, $expectedStartFmt),
    '2.2 Chatbot Siswa langsung menyinkronkan jadwal ujian yang baru dibuat',
    "Terbaca jadwal waktu: {$expectedStartFmt}"
);

// C. Update waktu jadwal di database
$updatedStartTime = date('Y-m-d H:i:s', strtotime('+5 days 14:00:00'));
$updatedEndTime   = date('Y-m-d H:i:s', strtotime('+5 days 16:00:00'));
$db->prepare("UPDATE exam_schedules SET start_time = :st, end_time = :et, updated_at = NOW() WHERE id = :id")->execute([
    ':st' => $updatedStartTime,
    ':et' => $updatedEndTime,
    ':id' => $newScheduleId
]);
$updatedStartFmt = str_replace($monthsEn, $monthsId, date('d M Y H:i', strtotime($updatedStartTime)));

$fastpathSiswaJadwal3 = $aiChatService->resolveInstantFactualAnswer('jadwal ujian saya apa saja?', 'siswa', (int)$siswaUser['id']);
assertTest(
    !empty($fastpathSiswaJadwal3) && str_contains($fastpathSiswaJadwal3, $updatedStartFmt) && !str_contains($fastpathSiswaJadwal3, $expectedStartFmt),
    '2.3 Chatbot Siswa langsung menyinkronkan pengubahan jam/tanggal jadwal ujian secara real-time',
    "Waktu baru terbaca: {$updatedStartFmt}"
);

// Bersihkan data uji jadwal
$db->prepare("DELETE FROM exam_schedules WHERE id = :id")->execute([':id' => $newScheduleId]);

// ------------------------------------------------------------------
// 3. SKENARIO BATASAN LINGKUP (TOLAK DI LUAR LINGKUP DATABASE)
// ------------------------------------------------------------------
echo "\n--- 3. PENEGAKAN BATASAN LINGKUP DATABASE (OUT-OF-SCOPE REJECTION) ---\n";

// A. Pertanyaan Kripto / Saham / Trading
$gCrypto = $aiChatService->validateInputGuardrail('Berapa prediksi harga bitcoin dan crypto hari ini?');
assertTest(
    $gCrypto !== null && str_contains($gCrypto, 'Di Luar Ruang Lingkup Sistem Ujian'),
    '3.1 Menolak pertanyaan Kripto / Finansial bebas di luar database sistem'
);

// B. Pertanyaan Skor Pertandingan Sepakbola
$gBola = $aiChatService->validateInputGuardrail('Berapa skor sepak bola liga inggris tadi malam?');
assertTest(
    $gBola !== null && str_contains($gBola, 'Di Luar Ruang Lingkup Sistem Ujian'),
    '3.2 Menolak pertanyaan hasil liga / skor bola non-akademik'
);

// C. Pertanyaan Gosip Selebriti
$gGosip = $aiChatService->validateInputGuardrail('Ceritakan gosip artis dan skandal selebriti terbaru');
assertTest(
    $gGosip !== null && str_contains($gGosip, 'Di Luar Ruang Lingkup Sistem Ujian'),
    '3.3 Menolak topik gosip artis / hiburan non-sekolah'
);

// D. Pertanyaan Resep Masak
$gResep = $aiChatService->validateInputGuardrail('Tuliskan resep makanan dan cara membuat kue nastar');
assertTest(
    $gResep !== null && str_contains($gResep, 'Di Luar Ruang Lingkup Sistem Ujian'),
    '3.4 Menolak resep makanan / gaya hidup di luar kurikulum sekolah'
);

// E. Pertanyaan yang SAH dan SESUAI database diizinkan
$gSah = $aiChatService->validateInputGuardrail('Kapan jadwal ujian matematika dan apa saja mata pelajaran saya?');
assertTest(
    $gSah === null,
    '3.5 Pertanyaan sah terkait jadwal ujian dan mata pelajaran database diizinkan'
);

// ------------------------------------------------------------------
// 4. SKENARIO RESPON GURU & ADMIN TERHUBUNG KE DATABASE RESMI
// ------------------------------------------------------------------
echo "\n--- 4. DATA TERHUBUNG KE DATABASE RESMI GURU & ADMIN ---\n";

// A. Guru menanyakan daftar ujian yang dikelolanya
$guruExamFastpath = $aiChatService->resolveInstantFactualAnswer('apa saja ujian yang saya buat?', 'guru', (int)$guruUser['id']);
assertTest(
    !empty($guruExamFastpath) && str_contains($guruExamFastpath, 'Total Paket Ujian') && str_contains($guruExamFastpath, 'KKM:'),
    '4.1 Chatbot Guru menampilkan rincian paket ujian yang dikelola langsung dari database',
    "Snippet: " . substr($guruExamFastpath ?? '', 0, 150) . "..."
);

// B. Admin menanyakan daftar ujian di sistem
$adminExamFastpath = $aiChatService->resolveInstantFactualAnswer('berapa ujian di sistem?', 'admin', (int)$adminUser['id']);
assertTest(
    !empty($adminExamFastpath) && str_contains($adminExamFastpath, 'Total Paket Ujian') && str_contains($adminExamFastpath, 'paket'),
    '4.2 Chatbot Admin menampilkan rekapitulasi seluruh ujian di sistem langsung dari database',
    "Snippet: " . substr($adminExamFastpath ?? '', 0, 150) . "..."
);

// C. Admin menanyakan mata pelajaran di sistem
$adminSubFastpath = $aiChatService->resolveInstantFactualAnswer('daftar mata pelajaran', 'admin', (int)$adminUser['id']);
assertTest(
    !empty($adminSubFastpath) && str_contains($adminSubFastpath, 'Total Mata Pelajaran') && str_contains($adminSubFastpath, 'guru pengampu'),
    '4.3 Chatbot Admin menampilkan seluruh mata pelajaran terdaftar dari database'
);

// ------------------------------------------------------------------
// 5. REKAPITULASI HASIL
// ------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI PENGUJIAN SINKRONISASI REALTIME & SCOPE DATABASE    \n";
echo "====================================================================\n";
echo "Total Uji : {$total}\n";
echo "PASS      : {$passed}\n";
echo "FAIL      : " . ($total - $passed) . "\n";

if ($passed === $total) {
    echo "🎉 SEMUA PENGUJIAN SINKRONISASI REALTIME & LINGKUP DATABASE SUKSES (100% PASS)!\n";
    exit(0);
} else {
    echo "⚠️ ADA PENGUJIAN GAGAL. CEK DETAIL DI ATAS.\n";
    exit(1);
}
