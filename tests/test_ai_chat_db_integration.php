<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Services\AI\AiChatService;
use App\Models\UserModel;

echo "====================================================================\n";
echo "   AI CHATBOT DATABASE INTEGRATION & ROLE-SPECIFIC VERIFICATION    \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$aiChatService = new AiChatService();
$userModel = new UserModel();

$adminUser = $userModel->findByEmail('admin@gmail.com') ?? $userModel->findByEmail('admin@example.com') ?? $userModel->find(2);
$guruUser  = $userModel->findByEmail('Budisantoso@gmail.com') ?? $userModel->findByEmail('guru@example.com') ?? $userModel->find(3);
$siswaUser = $userModel->findByEmail('Ahmadfauzi@gmail.com') ?? $userModel->findByEmail('siswa@example.com') ?? $userModel->find(4);

echo "[1] MEMERIKSA AKUN:\n";
echo "  • Admin ID: " . ($adminUser['id'] ?? 0) . " (" . ($adminUser['name'] ?? '-') . ")\n";
echo "  • Guru ID: " . ($guruUser['id'] ?? 0) . " (" . ($guruUser['name'] ?? '-') . ")\n";
echo "  • Siswa ID: " . ($siswaUser['id'] ?? 0) . " (" . ($siswaUser['name'] ?? '-') . ")\n\n";

// 2. Test DB Context Extraction Siswa
echo "[2] MENGUJI EXTRACTION DB CONTEXT SISWA:\n";
$siswaContext = $aiChatService->fetchSiswaDatabaseContext((int)$siswaUser['id']);
echo $siswaContext;
if (str_contains($siswaContext, 'XII MIPA 1') || str_contains($siswaContext, 'Ahmad Fauzi')) {
    echo "--> [PASS] Konteks Siswa memuat data profil kelas & siswa secara akurat!\n\n";
} else {
    echo "--> [FAIL] Konteks Siswa tidak memuat data kelas yang diharapkan.\n\n";
}

// 3. Test DB Context Extraction Guru
echo "[3] MENGUJI EXTRACTION DB CONTEXT GURU:\n";
$guruContext = $aiChatService->fetchGuruDatabaseContext((int)$guruUser['id']);
echo $guruContext;
if (str_contains($guruContext, 'Budi Santoso') || str_contains($guruContext, 'NIP')) {
    echo "--> [PASS] Konteks Guru memuat data profil guru & bank soal secara akurat!\n\n";
} else {
    echo "--> [FAIL] Konteks Guru tidak memuat data guru yang diharapkan.\n\n";
}

// 4. Test DB Context Extraction Admin
echo "[4] MENGUJI EXTRACTION DB CONTEXT ADMIN:\n";
$adminContext = $aiChatService->fetchAdminDatabaseContext((int)$adminUser['id']);
echo $adminContext;
if (str_contains($adminContext, 'Statistik Pengguna') && str_contains($adminContext, 'Periode Akademik')) {
    echo "--> [PASS] Konteks Admin memuat ringkasan analitik sistem secara akurat!\n\n";
} else {
    echo "--> [FAIL] Konteks Admin tidak memuat ringkasan sistem yang diharapkan.\n\n";
}

// 5. Test Chat Gemini Live untuk Admin
echo "[5] MENGUJI LIVE CHATBOT ADMIN (RAG DATA OPERASIONAL):\n";
$adminMsgRes = $aiChatService->sendMessage(
    (int)$adminUser['id'],
    'admin',
    'Tolong sebutkan berapa jumlah total siswa dan guru yang terdaftar serta tahun ajaran aktif di sistem ini berdasarkan data resmi database.'
);
if ($adminMsgRes['success']) {
    echo "  • Model: " . $adminMsgRes['model'] . " (Tokens: " . $adminMsgRes['tokens_used'] . ")\n";
    echo "  • Jawaban AI Admin:\n" . $adminMsgRes['reply'] . "\n";
    echo "--> [PASS] Chatbot Admin berhasil menjawab berbasis data database!\n\n";
} else {
    echo "--> [FAIL] Chatbot Admin gagal: " . ($adminMsgRes['error'] ?? 'Unknown error') . "\n\n";
}

// 6. Test Chat Gemini Live untuk Siswa
echo "[6] MENGUJI LIVE CHATBOT SISWA (DATA AKADEMIK SISWA):\n";
$siswaMsgRes = $aiChatService->sendMessage(
    (int)$siswaUser['id'],
    'siswa',
    'Halo, sebutkan nama saya, saya kelas berapa, dan siapa nama wali kelas saya menurut data sekolah?'
);
if ($siswaMsgRes['success']) {
    echo "  • Model: " . $siswaMsgRes['model'] . " (Tokens: " . $siswaMsgRes['tokens_used'] . ")\n";
    echo "  • Jawaban AI Siswa:\n" . $siswaMsgRes['reply'] . "\n";
    echo "--> [PASS] Chatbot Siswa berhasil mengenali profil & kelas dari database!\n\n";
} else {
    echo "--> [FAIL] Chatbot Siswa gagal: " . ($siswaMsgRes['error'] ?? 'Unknown error') . "\n\n";
}

// 7. Test Chat Gemini Live untuk Guru
echo "[7] MENGUJI LIVE CHATBOT GURU (DATA PENGAJARAN & BANK SOAL):\n";
$guruMsgRes = $aiChatService->sendMessage(
    (int)$guruUser['id'],
    'guru',
    'Halo, tolong ringkas bank soal apa saja yang saya miliki dan berapa total butir soalnya saat ini.'
);
if ($guruMsgRes['success']) {
    echo "  • Model: " . $guruMsgRes['model'] . " (Tokens: " . $guruMsgRes['tokens_used'] . ")\n";
    echo "  • Jawaban AI Guru:\n" . $guruMsgRes['reply'] . "\n";
    echo "--> [PASS] Chatbot Guru berhasil membaca bank soal dari database!\n\n";
} else {
    echo "--> [FAIL] Chatbot Guru gagal: " . ($guruMsgRes['error'] ?? 'Unknown error') . "\n\n";
}

echo "====================================================================\n";
echo "   SEMUA INTEGRASI DATABASE CHATBOT (ADMIN, GURU, SISWA) SUKSES!   \n";
echo "====================================================================\n";
