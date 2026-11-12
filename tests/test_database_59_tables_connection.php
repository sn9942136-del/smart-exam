<?php
/**
 * Test Suite: Verifikasi Keterhubungan Seluruh 59 Tabel Database ke Chatbot AI
 * Memastikan seluruh role (Admin, Guru, Siswa) dapat merespons pertanyaan berbasis
 * seluruh 59 tabel database Smart Exam dengan faktual, ringkas, dan tanpa komentar basa-basi.
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Services\AI\AiChatService;

$db = Database::getConnection();
$aiChatService = new AiChatService();

echo "==============================================================================\n";
echo "  PENGUJIAN KETERHUBUNGAN SELURUH 59 TABEL DATABASE DI CHATBOT AI             \n";
echo "==============================================================================\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $testId, string $description, bool $condition, string $evidence = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "✅ [PASS] [{$testId}] {$description}\n";
        if ($evidence !== '') {
            echo "   Bukti: " . mb_substr(str_replace("\n", " ", $evidence), 0, 110) . "...\n";
        }
    } else {
        $failCount++;
        echo "❌ [FAIL] [{$testId}] {$description}\n";
        if ($evidence !== '') {
            echo "   Bukti: " . mb_substr(str_replace("\n", " ", $evidence), 0, 110) . "...\n";
        }
    }
}

// 1. Verifikasi Jumlah Tabel Aktual di MySQL
$actualTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$totalActualTables = count($actualTables);
assertTest('DB-TBL-01', "Jumlah tabel database aktual adalah 59 tabel", $totalActualTables === 59, "Total: {$totalActualTables} tabel");

// 2. Pengujian Chatbot - Hitung Tabel Database (Admin)
$adminId = 2; // Administrator Ujian
$resp1 = $aiChatService->resolveInstantFactualAnswer('ada berapa tabel di database', 'admin', $adminId);
$isDirectOneSentence = !str_contains((string)$resp1, "\n") && str_contains((string)$resp1, '59 tabel database');
assertTest('DB-CHAT-01', 'Admin: Menanyakan jumlah tabel dijawab langsung 1 kalimat fakta 59 tabel', $isDirectOneSentence, (string)$resp1);

// 3. Pengujian Chatbot - Status Keterhubungan Database (Guru & Siswa)
$guruId = 50;
$siswaId = 12;
$respGuruTbl = $aiChatService->resolveInstantFactualAnswer('apakah semua database terhubung', 'guru', $guruId);
$respSiswaTbl = $aiChatService->resolveInstantFactualAnswer('apakah semua database terhubung', 'siswa', $siswaId);
assertTest('DB-CHAT-02', 'Guru: Konfirmasi semua database terhubung dijawab tepat', str_contains((string)$respGuruTbl, '59 tabel database') && str_contains((string)$respGuruTbl, 'terhubung'), (string)$respGuruTbl);
assertTest('DB-CHAT-03', 'Siswa: Konfirmasi semua database terhubung dijawab tepat', str_contains((string)$respSiswaTbl, '59 tabel database') && str_contains((string)$respSiswaTbl, 'terhubung'), (string)$respSiswaTbl);

// 4. Pengujian Chatbot - Rincian Daftar Tabel Database
$respListTbl = $aiChatService->resolveInstantFactualAnswer('sebutkan tabel database yang terhubung', 'admin', $adminId);
$hasAllDomains = str_contains((string)$respListTbl, 'Pengguna & Otorisasi') &&
                 str_contains((string)$respListTbl, 'Struktur Akademik') &&
                 str_contains((string)$respListTbl, 'Bank Soal') &&
                 str_contains((string)$respListTbl, 'Ujian & Penjadwalan') &&
                 str_contains((string)$respListTbl, 'AI Proctoring') &&
                 str_contains((string)$respListTbl, 'Operasional & Infrastruktur');
assertTest('DB-CHAT-04', 'Admin: Permintaan daftar tabel menyajikan pengelompokan domain 59 tabel', $hasAllDomains, (string)$respListTbl);

// 4b. Pengujian Khusus Chatbot Plus: Kemampuan Membaca Semua Database
$respCbPlus1 = $aiChatService->resolveInstantFactualAnswer('apakah chatbot + bisa membaca semua database', 'siswa', $siswaId);
assertTest('CBP-CHAT-01', 'Siswa: Pertanyaan apakah Chatbot + bisa membaca semua database dijawab 1 kalimat tepat', str_contains((string)$respCbPlus1, '59 tabel database') && (str_contains((string)$respCbPlus1, 'Chatbot Plus') || str_contains((string)$respCbPlus1, 'Chatbot +')) && !str_contains((string)$respCbPlus1, "\n"), (string)$respCbPlus1);

$respCbPlus2 = $aiChatService->resolveInstantFactualAnswer('bisa baca database apa saja chatbot +', 'admin', $adminId);
assertTest('CBP-CHAT-02', 'Admin: Chatbot + menyajikan rincian 59 tabel database terhubung', str_contains((string)$respCbPlus2, '59 tabel database') && (str_contains((string)$respCbPlus2, 'Chatbot Plus') || str_contains((string)$respCbPlus2, 'Chatbot +')) && str_contains((string)$respCbPlus2, 'AI Proctoring'), (string)$respCbPlus2);

// 5. Pengujian Profil Sekolah / Institusi dari system_settings
$respSchoolName = $aiChatService->resolveInstantFactualAnswer('apa nama sekolah ini', 'siswa', $siswaId);
assertTest('SET-CHAT-01', 'Siswa: Nama institusi sekolah dijawab dari system_settings', str_contains((string)$respSchoolName, 'SMA Negeri 1 Smart Exam'), (string)$respSchoolName);

$respSchoolAddr = $aiChatService->resolveInstantFactualAnswer('dimana alamat sekolah', 'guru', $guruId);
assertTest('SET-CHAT-02', 'Guru: Alamat sekolah dijawab dari system_settings', str_contains((string)$respSchoolAddr, 'Jl. Pendidikan Unggul No. 1, Jakarta'), (string)$respSchoolAddr);

$respSchoolPhone = $aiChatService->resolveInstantFactualAnswer('berapa nomor telepon sekolah', 'admin', $adminId);
assertTest('SET-CHAT-03', 'Admin: Nomor telepon sekolah dijawab dari system_settings', str_contains((string)$respSchoolPhone, '021-5551234'), (string)$respSchoolPhone);

$respSchoolEmail = $aiChatService->resolveInstantFactualAnswer('apa email resmi sekolah', 'siswa', $siswaId);
assertTest('SET-CHAT-04', 'Siswa: Email resmi sekolah dijawab dari system_settings', str_contains((string)$respSchoolEmail, 'info@smartexam.sch.id'), (string)$respSchoolEmail);

$respTimezone = $aiChatService->resolveInstantFactualAnswer('apa zona waktu sistem', 'guru', $guruId);
assertTest('SET-CHAT-05', 'Guru: Timezone sistem dijawab dari system_settings', str_contains((string)$respTimezone, 'Asia/Jakarta'), (string)$respTimezone);

// 6. Pengujian Aturan Ujian dari exam_rules
$respExamRules = $aiChatService->resolveInstantFactualAnswer('bagaimana aturan pindah tab saat ujian', 'siswa', $siswaId);
assertTest('RUL-CHAT-01', 'Siswa: Aturan toleransi pindah tab dijawab faktual dari exam_rules', str_contains((string)$respExamRules, 'layar penuh') && str_contains((string)$respExamRules, '3 kali'), (string)$respExamRules);

// 7. Pengujian Sesi Login Aktif dari user_sessions (Admin)
$respSessions = $aiChatService->resolveInstantFactualAnswer('ada berapa sesi login aktif', 'admin', $adminId);
assertTest('SES-CHAT-01', 'Admin: Sesi login aktif dijawab langsung 1 kalimat dari user_sessions', str_contains((string)$respSessions, 'sesi login aktif') && !str_contains((string)$respSessions, "\n"), (string)$respSessions);

// 8. Pengujian Log Pengiriman Notifikasi dari notification_logs (Admin)
$respNotifLogs = $aiChatService->resolveInstantFactualAnswer('berapa notifikasi terkirim', 'admin', $adminId);
assertTest('NTF-CHAT-01', 'Admin: Hitung log notifikasi terkirim dijawab 1 kalimat dari notification_logs', str_contains((string)$respNotifLogs, 'pengiriman notifikasi') && !str_contains((string)$respNotifLogs, "\n"), (string)$respNotifLogs);

echo "==============================================================================\n";
echo "HASIL AKHIR: {$passCount} / " . ($passCount + $failCount) . " LULUS\n";
echo "==============================================================================\n";

if ($failCount === 0) {
    echo "🎉 SELURUH PENGUJIAN 59 TABEL & GROUNDING DATABASE LULUS 100%!\n";
    exit(0);
} else {
    echo "❌ TERDAPAT PENGUJIAN GAGAL!\n";
    exit(1);
}

