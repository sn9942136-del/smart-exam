<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: GROUNDING JADWAL UJIAN DATABASE CHATBOT+ DUALCAMAI
 * ==============================================================================
 * Menguji integrasi database chatbot terhadap pertanyaan jadwal ujian:
 * Contoh spesifik: "kapan ujian PAS matematika peminatan"
 * 
 * Target Pengujian:
 * 1. Peserta Didik (Siswa):
 *    - Menjawab jadwal ujian sesuai kelas siswa (XII MIPA 1)
 *    - Menampilkan rentang waktu pelaksanaan aktual dari tabel `exam_schedules`
 *    - Menampilkan status jadwal (Sedang Berlangsung / Akan Datang / Selesai)
 *    - Menampilkan durasi, KKM, dan pengingat dual camera
 *    - Tidak membocorkan token ujian kepada siswa
 * 2. Guru:
 *    - Menjawab jadwal ujian yang diampu per rombel/kelas
 *    - Menampilkan token akses ujian yang sah
 * 3. Administrator:
 *    - Menjawab jadwal operasional lengkap seluruh rombel
 *    - Menampilkan nama guru pengampu dan token akses ujian
 * 4. Variasi pertanyaan mata pelajaran lain (Informatika, Biologi, Fisika)
 * 5. Integrasi sendMessage() dan penyimpanan riwayat chat
 * ==============================================================================
 */

// Gunakan target database pengujian
$targetDb = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'ujian_dual_kamera_test');
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Services\AI\AiChatService;

echo "====================================================================\n";
echo "   TEST SUITE: GROUNDING JADWAL UJIAN CHATBOT+ DUALCAMAI            \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Terkoneksi ke database pengujian: {$currentDb}\n\n";

$aiChatService = new AiChatService();

// Ambil tanggal jadwal aktual dari database untuk grounding presisi
$sch39 = $db->query("SELECT start_time, end_time FROM exam_schedules WHERE exam_id = 39 AND class_id = 1")->fetch(PDO::FETCH_ASSOC);
$date39_start = $aiChatService->formatDateTimeIndo($sch39['start_time'] ?? '2026-11-07 07:00:00');
$date39_parts = explode(' ', $date39_start);
$date39_start_date = $date39_parts[0] . ' ' . $date39_parts[1] . ' ' . $date39_parts[2];
$date39_end = $aiChatService->formatDateTimeIndo($sch39['end_time'] ?? '2026-11-09 23:59:59');
$date39_end_parts = explode(' ', $date39_end);
$date39_end_date = $date39_end_parts[0] . ' ' . $date39_end_parts[1] . ' ' . $date39_end_parts[2];

$sch40 = $db->query("SELECT start_time, end_time FROM exam_schedules WHERE exam_id = 40 AND class_id = 1")->fetch(PDO::FETCH_ASSOC);
$date40_start = $aiChatService->formatDateTimeIndo($sch40['start_time'] ?? '2026-11-10 08:00:00');
$date40_end = $aiChatService->formatDateTimeIndo($sch40['end_time'] ?? '2026-11-10 10:00:00');

$sch42 = $db->query("SELECT start_time, end_time FROM exam_schedules WHERE exam_id = 42 AND class_id = 1")->fetch(PDO::FETCH_ASSOC);
$date42_start = $aiChatService->formatDateTimeIndo($sch42['start_time'] ?? '2026-11-12 08:00:00');

$passCount = 0;
$failCount = 0;

function report(string $code, string $name, bool $ok, string $details = ''): void {
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo "[\033[32mPASS\033[0m] {$code}: {$name}\n";
    } else {
        $failCount++;
        echo "[\033[31mFAIL\033[0m] {$code}: {$name}\n";
    }
    if (!empty($details)) {
        echo "       Details: {$details}\n";
    }
}

// --------------------------------------------------------------------------
// TEST CASE 01: Siswa menanyakan "kapan ujian PAS matematika peminatan"
// --------------------------------------------------------------------------
$query1 = "kapan ujian PAS matematika peminatan";
$ansSiswa = $aiChatService->resolveInstantFactualAnswer($query1, 'siswa', 4);

$tc01_ok = $ansSiswa !== null
    && str_contains($ansSiswa, 'Penilaian Akhir Semester (PAS) Matematika Peminatan XII')
    && str_contains($ansSiswa, 'XII MIPA 1')
    && str_contains($ansSiswa, $date39_start_date)
    && str_contains($ansSiswa, $date39_end_date)
    && str_contains($ansSiswa, '90 menit')
    && str_contains($ansSiswa, '75.00')
    && !str_contains($ansSiswa, 'PASMAT1'); // token must NOT leak to student

report(
    'TC-SCHED-01',
    'Siswa menerima jadwal PAS Matematika Peminatan kelas XII MIPA 1 tanpa token',
    $tc01_ok,
    $tc01_ok ? "Siswa ID 4 mendapat waktu {$date39_start_date} - {$date39_end_date}, durasi 90m, KKM 75, token terproteksi." : 'Output tidak sesuai: ' . substr((string)$ansSiswa, 0, 150)
);

// --------------------------------------------------------------------------
// TEST CASE 02: Guru menanyakan "kapan ujian PAS matematika peminatan"
// --------------------------------------------------------------------------
$ansGuru = $aiChatService->resolveInstantFactualAnswer($query1, 'guru', 3);

$tc02_ok = $ansGuru !== null
    && str_contains($ansGuru, 'Penilaian Akhir Semester (PAS) Matematika Peminatan XII')
    && str_contains($ansGuru, 'PASMAT1')
    && str_contains($ansGuru, 'Kelas XII MIPA 1')
    && str_contains($ansGuru, 'Kelas XII MIPA 2')
    && str_contains($ansGuru, 'Kelas XII MIPA 3')
    && str_contains($ansGuru, $date39_start_date)
    && str_contains($ansGuru, $date39_end_date);

report(
    'TC-SCHED-02',
    'Guru pengampu menerima jadwal per kelas dan token PAS Matematika Peminatan',
    $tc02_ok,
    $tc02_ok ? 'Guru ID 3 mendapat token PASMAT1 dan jadwal untuk XII MIPA 1, 2, 3.' : 'Output tidak sesuai: ' . substr((string)$ansGuru, 0, 150)
);

// --------------------------------------------------------------------------
// TEST CASE 03: Admin menanyakan "kapan ujian PAS matematika peminatan"
// --------------------------------------------------------------------------
$ansAdmin = $aiChatService->resolveInstantFactualAnswer($query1, 'admin', 2);

$tc03_ok = $ansAdmin !== null
    && str_contains($ansAdmin, 'Penilaian Akhir Semester (PAS) Matematika Peminatan XII')
    && str_contains($ansAdmin, 'Budi Santoso')
    && str_contains($ansAdmin, 'PASMAT1')
    && str_contains($ansAdmin, 'Kelas XII MIPA 1')
    && str_contains($ansAdmin, $date39_start_date);

report(
    'TC-SCHED-03',
    'Admin menerima jadwal operasional lengkap beserta nama guru dan token',
    $tc03_ok,
    $tc03_ok ? 'Admin ID 2 menerima detail pengampu Budi Santoso, token PASMAT1, dan jadwal kelas.' : 'Output tidak sesuai: ' . substr((string)$ansAdmin, 0, 150)
);

// --------------------------------------------------------------------------
// TEST CASE 04: Variasi frasa pertanyaan siswa
// --------------------------------------------------------------------------
$variations = [
    "kapan ujian matematika peminatan",
    "kapan pas matematika peminatan",
    "jadwal ujian pas matematika peminatan",
    "kapan jadwal ujian pas matematika peminatan"
];

$allVarOk = true;
foreach ($variations as $var) {
    $ansVar = $aiChatService->resolveInstantFactualAnswer($var, 'siswa', 4);
    if ($ansVar === null || !str_contains($ansVar, $date39_start_date) || !str_contains($ansVar, 'XII MIPA 1')) {
        $allVarOk = false;
        break;
    }
}

report(
    'TC-SCHED-04',
    'Siswa mendapatkan jadwal akurat untuk variasi frasa pertanyaan',
    $allVarOk,
    $allVarOk ? 'Seluruh variasi frasa berhasil dipetakan ke jadwal PAS Matematika Peminatan.' : 'Salah satu variasi gagal dipetakan.'
);

// --------------------------------------------------------------------------
// TEST CASE 05: Jadwal mata pelajaran lain untuk Siswa (Informatika)
// --------------------------------------------------------------------------
$ansInfo = $aiChatService->resolveInstantFactualAnswer("kapan ujian informatika", 'siswa', 4);
$tc05_ok = $ansInfo !== null
    && str_contains($ansInfo, 'Pemrograman Web & AI XII')
    && str_contains($ansInfo, $date40_start)
    && str_contains($ansInfo, $date40_end);

report(
    'TC-SCHED-05',
    'Siswa menerima jadwal ujian Informatika & Rekayasa Perangkat Lunak',
    $tc05_ok,
    $tc05_ok ? "Jadwal STS Informatika {$date40_start} berhasil disajikan." : 'Output Informatika tidak sesuai.'
);

// --------------------------------------------------------------------------
// TEST CASE 06: Jadwal mata pelajaran lain untuk Siswa (Biologi)
// --------------------------------------------------------------------------
$ansBio = $aiChatService->resolveInstantFactualAnswer("kapan ujian biologi", 'siswa', 4);
$tc06_ok = $ansBio !== null
    && str_contains($ansBio, 'Biologi')
    && str_contains($ansBio, $date42_start);

report(
    'TC-SCHED-06',
    'Siswa menerima jadwal ujian Biologi Terapan',
    $tc06_ok,
    $tc06_ok ? "Jadwal PHB Biologi {$date42_start} berhasil disajikan." : 'Output Biologi tidak sesuai.'
);

// --------------------------------------------------------------------------
// TEST CASE 07: Pertanyaan jadwal umum menampilkan daftar seluruh jadwal kelas siswa
// --------------------------------------------------------------------------
$ansGeneral = $aiChatService->resolveInstantFactualAnswer("jadwal ujian saya apa saja", 'siswa', 4);
$tc07_ok = $ansGeneral !== null
    && str_contains($ansGeneral, 'Penilaian Akhir Semester (PAS) Matematika Peminatan XII')
    && str_contains($ansGeneral, 'Informatika')
    && str_contains($ansGeneral, 'Fisika')
    && str_contains($ansGeneral, 'Biologi');

report(
    'TC-SCHED-07',
    'Pertanyaan rekapitulasi jadwal umum menyajikan seluruh ujian kelas siswa',
    $tc07_ok,
    $tc07_ok ? 'Daftar rekapitulasi jadwal untuk XII MIPA 1 lengkap ditampilkan.' : 'Daftar rekapitulasi jadwal tidak lengkap.'
);

// --------------------------------------------------------------------------
// TEST CASE 08: Integrasi penuh sendMessage() menyimpan ke tabel ai_chat_messages
// --------------------------------------------------------------------------
$sendResp = $aiChatService->sendMessage(4, 'siswa', "kapan ujian PAS matematika peminatan");
$tc08_ok = isset($sendResp['reply'])
    && str_contains($sendResp['reply'], 'Penilaian Akhir Semester (PAS) Matematika Peminatan XII')
    && str_contains($sendResp['reply'], $date39_start_date);

// Cek penyimpanan di database ai_chat_messages
$lastUserMsg = $db->query("
    SELECT cm.message, cm.sender 
    FROM ai_chat_messages cm 
    JOIN ai_chat_sessions cs ON cs.id = cm.session_id 
    WHERE cs.user_id = 4 AND cm.sender = 'user'
    ORDER BY cm.id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$lastAssistantMsg = $db->query("
    SELECT cm.message, cm.sender, cm.model_used 
    FROM ai_chat_messages cm 
    JOIN ai_chat_sessions cs ON cs.id = cm.session_id 
    WHERE cs.user_id = 4 AND cm.sender = 'assistant'
    ORDER BY cm.id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$tc08_db_ok = $lastUserMsg !== false 
    && $lastAssistantMsg !== false
    && str_contains($lastUserMsg['message'], 'kapan ujian PAS matematika peminatan')
    && str_contains($lastAssistantMsg['message'], $date39_start_date)
    && $lastAssistantMsg['model_used'] === 'system-db-fastpath';

report(
    'TC-SCHED-08',
    'Integrasi sendMessage() dan persistensi database ai_chat_messages',
    $tc08_ok && $tc08_db_ok,
    ($tc08_ok && $tc08_db_ok) ? 'Pesan user dan respon fast-path tersimpan sempurna di ai_chat_messages.' : 'Penyimpanan ai_chat_messages gagal.'
);

// --------------------------------------------------------------------------
// RINGKASAN HASIL
// --------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   RINGKASAN PENGUJIAN JADWAL UJIAN: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
