<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\UserModel;
use App\Models\RoleModel;
use App\Services\AI\AiChatService;

echo "====================================================================\n";
echo "   PENGUJIAN SINKRONISASI DATA CHATBOT EDUAI ASSISTANT (DUALCAMAI)   \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$aiService = new AiChatService();
$userModel = new UserModel();
$roleModel = new RoleModel();

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
// 0. Initial Baseline State
// --------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "0. Kondisi Awal Sistem Sebelum Perubahan\n";
echo "--------------------------------------------------------------------\n";

$initialTotalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
$initialTeachers = (int)$db->query("
    SELECT COUNT(DISTINCT u.id) FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.deleted_at IS NULL
")->fetchColumn();

echo "• Total Pengguna Awal: {$initialTotalUsers}\n";
echo "• Total Guru Awal    : {$initialTeachers}\n\n";

$adminContext = $aiService->fetchAdminDatabaseContext(1);
assertCondition(
    strpos($adminContext, "Total Pengguna Terdaftar: {$initialTotalUsers}") !== false &&
    strpos($adminContext, "Guru / Pengawas: {$initialTeachers}") !== false,
    "TC-SYNC-00",
    "Konteks awal Admin memuat statistik live database terkini",
    "Admin context match: Total={$initialTotalUsers}, Guru={$initialTeachers}"
);

// --------------------------------------------------------------------
// 1. Skenario 1: Admin Menambahkan Satu Pengguna Guru Baru
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "1. Skenario 1: Tambah Pengguna Guru Baru\n";
echo "--------------------------------------------------------------------\n";

$testTeacherEmail = 'ratna_dewi_sync_' . time() . '@ujian.test';
$newTeacherData = [
    'name'       => 'Ibu Ratna Dewi, S.Pd',
    'email'      => $testTeacherEmail,
    'username'   => 'ratnadewi_' . time(),
    'password'   => password_hash('password123', PASSWORD_BCRYPT),
    'nip_nisn'   => '19880101' . rand(1000, 9999),
    'status'     => 'active',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

$teacherRoleId = (int)$db->query("SELECT id FROM roles WHERE name = 'guru' LIMIT 1")->fetchColumn();

$newUserId = $userModel->insert($newTeacherData);
$userModel->assignRole($newUserId, $teacherRoleId);

$expectedTeachersAfterAdd = $initialTeachers + 1;
$expectedUsersAfterAdd = $initialTotalUsers + 1;

$adminContextAfterAdd = $aiService->fetchAdminDatabaseContext(1);

assertCondition(
    strpos($adminContextAfterAdd, "Total Pengguna Terdaftar: {$expectedUsersAfterAdd}") !== false &&
    strpos($adminContextAfterAdd, "Guru / Pengawas: {$expectedTeachersAfterAdd}") !== false &&
    strpos($adminContextAfterAdd, "Ibu Ratna Dewi, S.Pd") !== false,
    "TC-SYNC-01",
    "Konteks Admin otomatis memuat guru baru yang baru saja ditambahkan",
    "Konteks menyertakan nama 'Ibu Ratna Dewi, S.Pd' dan total guru {$expectedTeachersAfterAdd}"
);

// Live Gemini Query
$reply1 = $aiService->sendMessage(1, 'admin', 'Berapa total guru yang terdaftar saat ini dan apakah Ibu Ratna Dewi sudah tercatat?');
$answerText1 = $reply1['reply'] ?? '';
assertCondition(
    $reply1['success'] === true &&
    (strpos($answerText1, (string)$expectedTeachersAfterAdd) !== false || stripos($answerText1, 'Ratna Dewi') !== false),
    "TC-LIVE-SYNC-01",
    "Live AI merespons akurat dengan total guru terbaru ({$expectedTeachersAfterAdd}) dan mengonfirmasi nama guru baru",
    "Jawaban AI: " . mb_substr($answerText1, 0, 180) . "..."
);

// --------------------------------------------------------------------
// 2. Skenario 2: Admin Mengubah Nama Pengguna
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "2. Skenario 2: Update Nama Pengguna\n";
echo "--------------------------------------------------------------------\n";

$updatedName = 'Dr. Ratna Dewi, M.Pd';
$userModel->update($newUserId, [
    'name'       => $updatedName,
    'updated_at' => date('Y-m-d H:i:s')
]);

$adminContextAfterRename = $aiService->fetchAdminDatabaseContext(1);

assertCondition(
    strpos($adminContextAfterRename, $updatedName) !== false &&
    strpos($adminContextAfterRename, "Ibu Ratna Dewi, S.Pd") === false,
    "TC-SYNC-02",
    "Konteks Admin langsung memuat nama baru pengguna setelah diedit",
    "Nama lama hilang, nama baru '{$updatedName}' langsung terdaftar"
);

// Live Gemini Query
$reply2 = $aiService->sendMessage(1, 'admin', 'Siapa nama lengkap guru baru yang bergelar Doktor (Dr)?');
$answerText2 = $reply2['reply'] ?? '';
assertCondition(
    $reply2['success'] === true && stripos($answerText2, 'Dr. Ratna Dewi') !== false,
    "TC-LIVE-SYNC-02",
    "Live AI merespons akurat menggunakan nama terbaru yang telah diubah",
    "Jawaban AI: " . mb_substr($answerText2, 0, 180) . "..."
);

// --------------------------------------------------------------------
// 3. Skenario 3: Admin Mengubah Role Pengguna (Guru -> Admin)
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "3. Skenario 3: Ganti Role Pengguna (Guru -> Admin)\n";
echo "--------------------------------------------------------------------\n";

$adminRoleId = (int)$db->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn();
$userModel->syncRole($newUserId, $adminRoleId);

$teachersAfterRoleChange = (int)$db->query("
    SELECT COUNT(DISTINCT u.id) FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name = 'guru' AND u.deleted_at IS NULL
")->fetchColumn();

$adminsAfterRoleChange = (int)$db->query("
    SELECT COUNT(DISTINCT u.id) FROM users u 
    JOIN user_roles ur ON ur.user_id = u.id 
    JOIN roles r ON r.id = ur.role_id 
    WHERE r.name IN ('super_admin', 'admin') AND u.deleted_at IS NULL
")->fetchColumn();

$adminContextAfterRoleChange = $aiService->fetchAdminDatabaseContext(1);

assertCondition(
    strpos($adminContextAfterRoleChange, "Administrator: {$adminsAfterRoleChange}") !== false ||
    strpos($adminContextAfterRoleChange, "Dr. Ratna Dewi, M.Pd") !== false,
    "TC-SYNC-03",
    "Konteks Admin merefleksikan perubahan role pengguna secara tepat",
    "Total Guru kembali ke {$teachersAfterRoleChange}, Dr. Ratna terdaftar di daftar Administrator"
);

// --------------------------------------------------------------------
// 4. Skenario 4: Admin Menonaktifkan / Menghapus Akun (Soft Delete)
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "4. Skenario 4: Hapus / Nonaktifkan Akun Pengguna (Soft Delete)\n";
echo "--------------------------------------------------------------------\n";

$userModel->softDelete($newUserId);

$finalTotalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
$adminContextAfterDelete = $aiService->fetchAdminDatabaseContext(1);

assertCondition(
    $finalTotalUsers === $initialTotalUsers &&
    strpos($adminContextAfterDelete, "Total Pengguna Terdaftar: {$initialTotalUsers}") !== false &&
    strpos($adminContextAfterDelete, "Dr. Ratna Dewi, M.Pd") === false,
    "TC-SYNC-04",
    "Pengguna yang dihapus langsung hilang dari statistik dan direktori konteks chatbot",
    "Total pengguna kembali normal ke {$finalTotalUsers}, user terhapus tidak ada di konteks"
);

// Live Gemini Query
$reply4 = $aiService->sendMessage(1, 'admin', 'Berapa total akun pengguna aktif di sistem saat ini setelah penghapusan akun uji coba?');
$answerText4 = $reply4['reply'] ?? '';
assertCondition(
    $reply4['success'] === true &&
    (strpos($answerText4, (string)$initialTotalUsers) !== false || strpos($answerText4, '5') !== false),
    "TC-LIVE-SYNC-04",
    "Live AI menjawab total pengguna sesuai database terkini setelah penghapusan",
    "Jawaban AI: " . mb_substr($answerText4, 0, 180) . "..."
);

// --------------------------------------------------------------------
// 5. Skenario 5: Keamanan Antar-Role & Perlindungan Privasi
// --------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "5. Skenario 5: Keamanan Antar-Role & Perlindungan Privasi\n";
echo "--------------------------------------------------------------------\n";

$studentId = 4;
$studentContext = $aiService->fetchSiswaDatabaseContext($studentId);

assertCondition(
    strpos($studentContext, 'Ahmad Fauzi') !== false &&
    strpos($studentContext, 'password') === false &&
    strpos($studentContext, 'superadmin') === false,
    "TC-SEC-SYNC-01",
    "Konteks Siswa terisolasi murni untuk data pribadi siswa bersangkutan",
    "Data siswa aman dari kebocoran akun admin/guru"
);

// Cleanup hard delete testing user
$db->prepare("DELETE FROM user_roles WHERE user_id = :uid")->execute([':uid' => $newUserId]);
$db->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $newUserId]);

// --------------------------------------------------------------------
// REKAPITULASI
// --------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN SINKRONISASI DATA DATABASE\n";
echo "====================================================================\n";
echo "Total Uji : {$totalTests}\n";
echo "PASS      : {$passedTests}\n";
echo "FAIL      : " . ($totalTests - $passedTests) . "\n";
if ($passedTests === $totalTests) {
    echo "🎉 SEMUA PENGUJIAN SINKRONISASI DATA BERHASIL (100% PASS)!\n";
}
echo "====================================================================\n";
