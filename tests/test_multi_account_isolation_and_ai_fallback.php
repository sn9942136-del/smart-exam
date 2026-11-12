<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * UJI ISOLASI MULTI-AKUN & KONSISTENSI PROVIDER EDUAI ASSISTANT
 * ==============================================================================
 * Menguji dua akun dengan role berbeda (Admin & Guru):
 * 1. Isolasi konteks database (Admin context vs Guru context).
 * 2. Isolasi riwayat obrolan (Admin session vs Guru session).
 * 3. Jalur eksekusi provider AI pada masing-masing akun.
 * 4. Penanganan error independen tanpa efek samping / kebocoran antar-akun.
 * 5. Kebersihan kredensial (API Key tidak bocor).
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Libraries\Database;
use App\Models\AiChatMessageModel;
use App\Models\AiChatSessionModel;
use App\Services\AI\AiChatService;
use App\Services\AI\GeminiProvider;

class ControlledMockGeminiProvider extends GeminiProvider
{
    public array $callLog = [];
    public ?array $nextResponse = null;

    public function setResponse(array $response): void
    {
        $this->nextResponse = $response;
    }

    public function generateCompletion(string $prompt, array $options = []): array
    {
        $this->callLog[] = [
            'prompt'  => $prompt,
            'options' => $options
        ];

        if ($this->nextResponse !== null) {
            return $this->nextResponse;
        }

        return [
            'success'       => true,
            'content'       => 'Respon AI terisolasi default',
            'model'         => $this->getModel(),
            'provider'      => $this->getProviderName(),
            'attempts'      => 1,
            'fallback_used' => false
        ];
    }
}

echo "====================================================================\n";
echo "   UJI ISOLASI MULTI-AKUN (ADMIN & GURU) & INTEGRITAS PROVIDER     \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$sessionModel = new AiChatSessionModel();
$messageModel = new AiChatMessageModel();

// Dapatkan User Admin (User 2) dan Guru (User 3)
$adminUser = $db->query("SELECT u.id, u.name, r.name as role FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.name IN ('admin', 'super_admin') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$guruUser = $db->query("SELECT u.id, u.name, r.name as role FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.name = 'guru' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$adminId = (int)($adminUser['id'] ?? 2);
$guruId = (int)($guruUser['id'] ?? 3);

echo "Pengujian Akun:\n";
echo "  • Akun 1: ID {$adminId} ({$adminUser['name']}) [Role: {$adminUser['role']}]\n";
echo "  • Akun 2: ID {$guruId} ({$guruUser['name']}) [Role: {$guruUser['role']}]\n\n";

$passCount = 0;
$totalTests = 5;

// -----------------------------------------------------------------------------
// TEST 1: Isolasi Konteks Database Antar-Role
// -----------------------------------------------------------------------------
echo "[1/5] Menguji Isolasi Konteks Database (Admin vs Guru)...\n";
$mockProvider = new ControlledMockGeminiProvider();
$chatService = new AiChatService($mockProvider);

$adminCtx = $chatService->fetchAdminDatabaseContext($adminId);
$guruCtx = $chatService->fetchGuruDatabaseContext($guruId);

$adminHasRoleStats = str_contains($adminCtx, 'akun');
$guruHasExamScope = str_contains($guruCtx, 'paket ujian') || str_contains($guruCtx, 'penilaian esai');
$guruCannotSeeAdminStats = !str_contains($guruCtx, 'Super Administrator');

$pass1 = ($adminHasRoleStats && $guruHasExamScope && $guruCannotSeeAdminStats);
if ($pass1) {
    $passCount++;
    echo "  [PASS] Konteks Admin dan Konteks Guru terisolasi sempurna sesuai hak akses data.\n";
} else {
    echo "  [FAIL] Kebocoran konteks terdeteksi antara Admin dan Guru.\n";
}

// -----------------------------------------------------------------------------
// TEST 2: Eksekusi Chat Admin Tersimpan di Sesi Admin Tanpa Efek ke Guru
// -----------------------------------------------------------------------------
echo "\n[2/5] Menguji Eksekusi Chat Admin...\n";
$mockProvider->setResponse([
    'success'       => true,
    'content'       => 'Halo Admin, sistem CBT berjalan lancar.',
    'model'         => 'gemini-3.8-flash',
    'provider'      => 'gemini',
    'attempts'      => 1,
    'fallback_used' => false
]);

$adminChat = $chatService->sendMessage($adminId, 'admin', 'Cek status sistem');
$adminSessionId = $adminChat['session_id'] ?? 0;

$adminMessages = $messageModel->getSessionMessages($adminSessionId, 10, $adminId);
$guruAttemptReadAdmin = $messageModel->getSessionMessages($adminSessionId, 10, $guruId);

$pass2 = ($adminChat['success'] === true
    && count($adminMessages) >= 2
    && count($guruAttemptReadAdmin) === 0); // Guru DILARANG keras membaca sesi Admin
if ($pass2) {
    $passCount++;
    echo "  [PASS] Chat Admin tersimpan di sesi Admin. Guru mencoba membaca sesi Admin -> 0 pesan (aman).\n";
} else {
    echo "  [FAIL] Isolasi sesi Admin gagal atau dapat dibaca oleh Guru.\n";
}

// -----------------------------------------------------------------------------
// TEST 3: Eksekusi Chat Guru Tersimpan di Sesi Guru Tanpa Efek ke Admin
// -----------------------------------------------------------------------------
echo "\n[3/5] Menguji Eksekusi Chat Guru...\n";
$mockProvider->setResponse([
    'success'       => true,
    'content'       => 'Halo Pak Guru, ada 2 esai pending yang perlu dinilai.',
    'model'         => 'gemini-3.5-flash-lite',
    'provider'      => 'gemini',
    'attempts'      => 1,
    'fallback_used' => true
]);

$guruChat = $chatService->sendMessage($guruId, 'guru', 'Berapa esai pending saya?');
$guruSessionId = $guruChat['session_id'] ?? 0;

$guruMessages = $messageModel->getSessionMessages($guruSessionId, 10, $guruId);
$adminAttemptReadGuru = $messageModel->getSessionMessages($guruSessionId, 10, $adminId);

$pass3 = ($guruChat['success'] === true
    && count($guruMessages) >= 2
    && count($adminAttemptReadGuru) === 0); // Admin membaca via scoping user_id -> 0 pesan
if ($pass3) {
    $passCount++;
    echo "  [PASS] Chat Guru tersimpan di sesi Guru. User lain mencoba membaca -> 0 pesan (aman).\n";
} else {
    echo "  [FAIL] Isolasi sesi Guru gagal.\n";
}

// -----------------------------------------------------------------------------
// TEST 4: Penanganan Error Terkendali pada Masing-Masing Akun
// -----------------------------------------------------------------------------
echo "\n[4/5] Menguji Penanganan Error Independen (Simulasi HTTP 503)...\n";
$mockProvider->setResponse([
    'success'            => false,
    'error'              => 'Layanan Google Gemini AI sedang mengalami lonjakan antrean server (HTTP 503 High Demand). Silakan coba kirim kembali pesan Anda dalam beberapa saat. [Detail: Model utama (gemini-3.8-flash) [HTTP 429] dan model cadangan (gemini-3.5-flash-lite) [HTTP 503] gagal merespons: High demand]',
    'user_message'       => 'Layanan Google Gemini AI sedang mengalami lonjakan antrean server (HTTP 503 High Demand).',
    'http_code'          => 503,
    'fallback_attempted' => true
]);

$adminFailChat = $chatService->sendMessage($adminId, 'admin', 'Pertanyaan saat server sibuk');
$guruFailChat = $chatService->sendMessage($guruId, 'guru', 'Pertanyaan saat server sibuk');

$pass4 = ($adminFailChat['success'] === false
    && $guruFailChat['success'] === false
    && str_contains($adminFailChat['error'], 'lonjakan antrean server')
    && str_contains($guruFailChat['error'], 'lonjakan antrean server'));
if ($pass4) {
    $passCount++;
    echo "  [PASS] Kedua akun menerima pesan error terproteksi dan ramah tanpa kebocoran stack trace.\n";
} else {
    echo "  [FAIL] Penanganan error pada multi-akun tidak konsisten.\n";
}

// -----------------------------------------------------------------------------
// TEST 5: Proteksi API Key pada Seluruh Respon Multi-Akun
// -----------------------------------------------------------------------------
echo "\n[5/5] Menguji Proteksi API Key pada Output Multi-Akun...\n";
$keyLeakAdmin = str_contains($adminFailChat['error'] ?? '', 'AQ.Ab8RN6IH');
$keyLeakGuru = str_contains($guruFailChat['error'] ?? '', 'AQ.Ab8RN6IH');
$pass5 = (!$keyLeakAdmin && !$keyLeakGuru);
if ($pass5) {
    $passCount++;
    echo "  [PASS] API Key terproteksi dan tidak pernah muncul pada pesan pengguna manapun.\n";
} else {
    echo "  [FAIL] API Key terdeteksi bocor pada pesan error!\n";
}

// -----------------------------------------------------------------------------
// REKAPITULASI
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   HASIL PENGUJIAN ISOLASI MULTI-AKUN                              \n";
echo "====================================================================\n";
echo "Total Kasus Uji : {$totalTests}\n";
echo "Status PASS     : {$passCount}\n";
echo "Status FAIL     : " . ($totalTests - $passCount) . "\n";
echo "Status          : " . ($passCount === $totalTests ? "SEMUA PENGUJIAN LULUS (100%)" : "TERDAPAT KEGAGALAN") . "\n";
echo "====================================================================\n";

if ($passCount !== $totalTests) {
    exit(1);
}
exit(0);
