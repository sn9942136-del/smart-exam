<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Services\AI\AiChatService;

$db = Database::getConnection();
$aiService = new AiChatService();

// Ambil User
$adminUser = $db->query("
    SELECT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name IN ('admin', 'super_admin') AND u.deleted_at IS NULL 
    ORDER BY u.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$guruUser = $db->query("
    SELECT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.deleted_at IS NULL 
    ORDER BY u.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$siswaUser = $db->query("
    SELECT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'peserta_didik' AND u.deleted_at IS NULL 
    ORDER BY u.id ASC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$adminId = (int)$adminUser['id'];
$guruId = (int)$guruUser['id'];
$siswaId = (int)$siswaUser['id'];

$passCount = 0;
$failCount = 0;

function assertKnowledge(string $testCode, string $description, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "✅ [PASS] [{$testCode}] {$description}\n";
    } else {
        $failCount++;
        echo "❌ [FAIL] [{$testCode}] {$description}\n";
        if ($detail !== '') {
            echo "   Detail Output: {$detail}\n";
        }
    }
}

function isToThePoint(string $reply, int $maxSentences = 4): bool {
    // Cek tidak ada basa-basi pembuka
    if (preg_match('/^(halo|hai|tentu saja|baik|senang membantu)/i', trim($reply))) {
        return false;
    }
    // Cek tidak ada salam penutup
    if (preg_match('/(semoga membantu|ada yang bisa dibantu lagi|apakah ada pertanyaan|semoga sukses)[\.!]*\s*$/i', trim($reply))) {
        return false;
    }
    // Jika tidak memakai bullet points, batasi jumlah kalimat
    if (!str_contains($reply, '*')) {
        $sentences = preg_split('/[\.\?!]+(\s+|$)/u', trim($reply), -1, PREG_SPLIT_NO_EMPTY);
        return count($sentences) <= $maxSentences;
    }
    return true;
}

echo "==============================================================================\n";
echo "  PENGUJIAN GROUNDING DATABASE & KNOWLEDGE BASE SMART EXAM (TO THE POINT)     \n";
echo "==============================================================================\n";

// ----------------------------------------------------------------------------
// A. PENGUJIAN DATABASE AKTUAL (ADMIN, GURU, SISWA)
// ----------------------------------------------------------------------------

// 1. Admin: Total user terdaftar
$rAdminUsers = $aiService->resolveInstantFactualAnswer('ada berapa user terdaftar di sistem?', 'admin', $adminId);
assertKnowledge('DB-01', 'Admin: Total user terdaftar dijawab akurat dan to the point', 
    $rAdminUsers !== null && str_contains($rAdminUsers, 'user (pengguna)') && isToThePoint($rAdminUsers),
    (string)$rAdminUsers
);

// 2. Admin: Wali Kelas 12 MIPA 1
$rAdminWali = $aiService->resolveInstantFactualAnswer('siapa wali kelas 12 mipa 1?', 'admin', $adminId);
assertKnowledge('DB-02', 'Admin: Wali kelas 12 MIPA 1 dijawab akurat sesuai database', 
    $rAdminWali !== null && str_contains($rAdminWali, 'Budi Santoso') && str_contains($rAdminWali, 'XII MIPA 1') && isToThePoint($rAdminWali),
    (string)$rAdminWali
);

// 3. Guru: Bank Soal Guru
$rGuruBank = $aiService->resolveInstantFactualAnswer('berapa bank soal yang saya miliki?', 'guru', $guruId);
assertKnowledge('DB-03', 'Guru: Bank soal guru dijawab akurat sesuai kepemilikan database', 
    $rGuruBank !== null && (str_contains($rGuruBank, 'bank soal') || str_contains($rGuruBank, 'belum memiliki')) && isToThePoint($rGuruBank),
    (string)$rGuruBank
);

// 4. Siswa: Jadwal Ujian Siswa
$rSiswaJadwal = $aiService->resolveInstantFactualAnswer('kapan jadwal ujian saya?', 'siswa', $siswaId);
assertKnowledge('DB-04', 'Siswa: Jadwal ujian siswa diambil dari live exam_schedules', 
    $rSiswaJadwal !== null && str_contains($rSiswaJadwal, 'jadwal ujian') && isToThePoint($rSiswaJadwal),
    (string)$rSiswaJadwal
);

// 5. Semua Role: Keterhubungan 59 Tabel Database
$rTables = $aiService->resolveInstantFactualAnswer('apakah 59 tabel database sudah terhubung?', 'admin', $adminId);
assertKnowledge('DB-05', 'Keterhubungan 59 tabel database terjawab 1 kalimat tepat', 
    $rTables !== null && str_contains($rTables, '59 tabel database') && isToThePoint($rTables),
    (string)$rTables
);

// ----------------------------------------------------------------------------
// B. PENGUJIAN ILMU PENGETAHUAN & ARSITEKTUR SMART EXAM (TO THE POINT)
// ----------------------------------------------------------------------------

// 6. Definisi Smart Exam
$rDef = $aiService->resolveInstantFactualAnswer('apa itu smart exam?', 'siswa', $siswaId);
assertKnowledge('KB-01', 'Definisi Smart Exam dijawab to the point', 
    $rDef !== null && str_contains($rDef, 'Computer-Based Test') && str_contains($rDef, 'AI Proctoring Dual Camera') && isToThePoint($rDef),
    (string)$rDef
);

// 7. Dual Camera (Arsitektur Umum)
$rDual = $aiService->resolveInstantFactualAnswer('bagaimana cara kerja dual camera?', 'siswa', $siswaId);
assertKnowledge('KB-02', 'Dual camera dijawab to the point (Kamera 1 webcam laptop + Kamera 2 smartphone)', 
    $rDual !== null && str_contains($rDual, 'Kamera 1') && str_contains($rDual, 'Kamera 2') && isToThePoint($rDual),
    (string)$rDual
);

// 8. Kamera 1 Spesifik (Webcam Depan)
$rCam1 = $aiService->resolveInstantFactualAnswer('apa fungsi kamera 1?', 'siswa', $siswaId);
assertKnowledge('KB-03', 'Kamera 1 dijawab spesifik hanya kamera depan tanpa filler', 
    $rCam1 !== null && str_contains($rCam1, 'Kamera 1') && str_contains($rCam1, 'depan') && !str_contains($rCam1, 'Kamera 2') && isToThePoint($rCam1),
    (string)$rCam1
);

// 9. Kamera 2 Spesifik (Smartphone Samping/Belakang)
$rCam2 = $aiService->resolveInstantFactualAnswer('apa fungsi kamera 2?', 'siswa', $siswaId);
assertKnowledge('KB-04', 'Kamera 2 dijawab spesifik hanya smartphone/samping tanpa filler', 
    $rCam2 !== null && str_contains($rCam2, 'Kamera 2') && str_contains($rCam2, 'samping') && !str_contains($rCam2, 'Kamera 1') && isToThePoint($rCam2),
    (string)$rCam2
);

// 10. WebRTC QR Code
$rWebrtc = $aiService->resolveInstantFactualAnswer('apa itu webrtc qr?', 'siswa', $siswaId);
assertKnowledge('KB-05', 'WebRTC QR dijawab to the point (scan QR via browser hp tanpa instal aplikasi)', 
    $rWebrtc !== null && str_contains($rWebrtc, 'WebRTC') && str_contains($rWebrtc, 'QR') && isToThePoint($rWebrtc),
    (string)$rWebrtc
);

// 11. Deteksi Kecurangan AI
$rProctor = $aiService->resolveInstantFactualAnswer('apa saja jenis deteksi kecurangan ai?', 'siswa', $siswaId);
assertKnowledge('KB-06', 'Deteksi kecurangan AI menyajikan 5 jenis pelanggaran terstruktur', 
    $rProctor !== null && str_contains($rProctor, 'Look-Away') && str_contains($rProctor, 'Multiple Faces') && str_contains($rProctor, 'Tab Switching') && isToThePoint($rProctor),
    (string)$rProctor
);

// 12. Lock Browser & Batas Pindah Tab
$rLock = $aiService->resolveInstantFactualAnswer('apa itu lock browser di smart exam?', 'siswa', $siswaId);
assertKnowledge('KB-07', 'Lock browser dijawab to the point memuat layar penuh dan toleransi 3 kali', 
    $rLock !== null && str_contains($rLock, 'layar penuh') && str_contains($rLock, '3 kali') && isToThePoint($rLock),
    (string)$rLock
);

// 13. Token Ujian
$rToken = $aiService->resolveInstantFactualAnswer('apa fungsi token ujian?', 'siswa', $siswaId);
assertKnowledge('KB-08', 'Token ujian dijawab to the point (6 digit kode keamanan acak)', 
    $rToken !== null && str_contains($rToken, 'Token ujian') && str_contains($rToken, '6 digit') && isToThePoint($rToken),
    (string)$rToken
);

// 14. Session Recovery & Koneksi Putus
$rRecover = $aiService->resolveInstantFactualAnswer('apa yang terjadi jika koneksi terputus saat ujian?', 'siswa', $siswaId);
assertKnowledge('KB-09', 'Session recovery dijawab to the point (autosave real-time & lanjut tanpa hilang)', 
    $rRecover !== null && str_contains($rRecover, 'autosave') && str_contains($rRecover, 'melanjutkan') && isToThePoint($rRecover),
    (string)$rRecover
);

// 15. Timer Server
$rTimer = $aiService->resolveInstantFactualAnswer('bagaimana sistem sinkronisasi timer ujian?', 'siswa', $siswaId);
assertKnowledge('KB-10', 'Timer server dijawab to the point (sinkron waktu server, anti manipulasi jam klien)', 
    $rTimer !== null && str_contains($rTimer, 'waktu server') && isToThePoint($rTimer),
    (string)$rTimer
);

// 16. Sistem Penilaian Esai
$rEssay = $aiService->resolveInstantFactualAnswer('bagaimana cara sistem menilai esai?', 'guru', $guruId);
assertKnowledge('KB-11', 'Penilaian esai dijawab to the point (koreksi guru dibantu AI rubrik semantik)', 
    $rEssay !== null && str_contains($rEssay, 'guru pengampu') && str_contains($rEssay, 'AI Essay Grading') && isToThePoint($rEssay),
    (string)$rEssay
);

// 17. Alur Pengerjaan Ujian Siswa
$rWorkflow = $aiService->resolveInstantFactualAnswer('bagaimana alur pengerjaan ujian siswa?', 'siswa', $siswaId);
assertKnowledge('KB-12', 'Alur ujian siswa dijawab to the point 5 tahapan berurutan', 
    $rWorkflow !== null && str_contains($rWorkflow, 'Alur ujian siswa') && str_contains($rWorkflow, 'token') && isToThePoint($rWorkflow),
    (string)$rWorkflow
);

// 18. Pra-Cek Kamera
$rPrecheck = $aiService->resolveInstantFactualAnswer('apa tujuan pra-cek kamera?', 'siswa', $siswaId);
assertKnowledge('KB-13', 'Pra-cek kamera dijawab to the point (kalibrasi awal pencahayaan & sudut pandang)', 
    $rPrecheck !== null && str_contains($rPrecheck, 'Pra-cek kamera') && isToThePoint($rPrecheck),
    (string)$rPrecheck
);

// 19. Perbedaan Hak Akses Peran
$rRoles = $aiService->resolveInstantFactualAnswer('apa perbedaan hak akses admin guru dan siswa?', 'admin', $adminId);
assertKnowledge('KB-14', 'Perbedaan hak akses dijawab to the point membedakan Admin, Guru, Siswa', 
    $rRoles !== null && str_contains($rRoles, 'Admin') && str_contains($rRoles, 'Guru') && str_contains($rRoles, 'Siswa') && isToThePoint($rRoles),
    (string)$rRoles
);

// 20. Siswa: Pertanyaan NIM Diri Sendiri
$rNimSelf = $aiService->resolveInstantFactualAnswer('berapa nim saya?', 'siswa', $siswaId);
assertKnowledge('KB-15', 'Siswa: Pertanyaan NIM diri sendiri dijawab to the point nomor identitas', 
    $rNimSelf !== null && (str_contains($rNimSelf, 'NIM/NISN') || str_contains($rNimSelf, 'Nomor identitas')) && isToThePoint($rNimSelf),
    (string)$rNimSelf
);

// 21. Siswa: Pertanyaan NIM Siswa Lain (Guardrail Privasi RBAC)
$guardrailNimOther = $aiService->validateInputGuardrail('berapa nim budi?', 'global', 'siswa', false);
assertKnowledge('KB-16', 'Siswa: Pertanyaan NIM pengguna lain diblokir oleh guardrail privasi', 
    $guardrailNimOther !== null && str_contains($guardrailNimOther, 'Privasi Pengguna Terlindungi'),
    (string)$guardrailNimOther
);

// 22. Konseptual: Kenapa Chatbot Tidak Menjawab NIM
$rNimConcept = $aiService->resolveInstantFactualAnswer('kenapa chatbot tidak mau menjawab nim?', 'siswa', $siswaId);
assertKnowledge('KB-17', 'Penjelasan kenapa tidak menjawab NIM dijawab to the point (NISN database & privasi)', 
    $rNimConcept !== null && str_contains($rNimConcept, 'NISN') && str_contains($rNimConcept, 'privasi') && isToThePoint($rNimConcept),
    (string)$rNimConcept
);

echo "==============================================================================\n";
echo "HASIL AKHIR: {$passCount} / " . ($passCount + $failCount) . " LULUS\n";
echo "==============================================================================\n";

if ($failCount === 0) {
    echo "🎉 SELURUH PENGUJIAN DATABASE & KNOWLEDGE BASE SMART EXAM (TO THE POINT) LULUS 100%!\n";
    exit(0);
} else {
    echo "❌ TERDAPAT {$failCount} PENGUJIAN GAGAL!\n";
    exit(1);
}

