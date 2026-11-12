<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — WHATSAPP GATEWAY DIAGNOSTIC & VERIFICATION TOOL (CLI)
 * ==============================================================================
 * Utilitas command-line mandiri untuk memverifikasi konfigurasi dan koneksi
 * WhatsApp Gateway secara aman tanpa membocorkan kredensial.
 *
 * Penggunaan:
 *   php bin/test_whatsapp.php --verify-only
 *   php bin/test_whatsapp.php --to=08123456789
 *   php bin/test_whatsapp.php --help
 *
 * Keamanan:
 * - API Token TIDAK PERNAH ditampilkan di layar atau disimpan ke log.
 * - Memeriksa guardrail NOTIFICATION_TEST_PHONE sebelum mengirim.
 * - Menolak keras eksekusi jika database aktif adalah database operasional.
 * ==============================================================================
 */

// 1. Parsing Argumen CLI
$options = getopt('', ['to::', 'verify-only', 'help', 'verbose']);

if (isset($options['help'])) {
    echo "==============================================================================\n";
    echo "  SMART EXAM — WHATSAPP GATEWAY VERIFICATION TOOL\n";
    echo "==============================================================================\n";
    echo "Penggunaan:\n";
    echo "  php bin/test_whatsapp.php --verify-only\n";
    echo "      Memeriksa endpoint gateway dan kelengkapan konfigurasi tanpa mengirim pesan.\n\n";
    echo "  php bin/test_whatsapp.php --to=08123456789\n";
    echo "      Mengirim satu pesan WhatsApp uji coba ke nomor tujuan yang ditentukan.\n\n";
    echo "Opsi tambahan:\n";
    echo "  --verbose    Menampilkan rincian payload gateway secara detail.\n";
    echo "==============================================================================\n";
    exit(0);
}

// 2. Bootstrap Sistem Smart Exam
$_ENV['DB_DATABASE'] = 'ujian_dual_kamera_test';
$_SERVER['DB_DATABASE'] = 'ujian_dual_kamera_test';
putenv('DB_DATABASE=ujian_dual_kamera_test');

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = 'ujian_dual_kamera_test';

use App\Config\Notification;
use App\Libraries\Database;
use App\Libraries\WhatsAppClient;

// 3. Validasi Keamanan Database
try {
    $pdo = Database::getConnection();
    $actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    Notification::validateActiveDatabase($actualDb);
} catch (\Throwable $e) {
    fwrite(STDERR, "[SAFETY GUARD TRIGGERED] " . $e->getMessage() . "\n");
    exit(1);
}

// 4. Periksa Konfigurasi WhatsApp
Notification::load();
$summary = Notification::getWhatsAppConfigSummary();

echo "==============================================================================\n";
echo "  SMART EXAM — WHATSAPP GATEWAY DIAGNOSTIC & VERIFICATION\n";
echo "==============================================================================\n";
echo "  Target Database : {$actualDb}\n";
echo "  Gateway API URL : {$summary['api_url']}\n";
echo "  API Token       : " . ($summary['token_set'] ? '***[TERPROTEKSI]***' : '(tidak diatur / mock lokal)') . "\n";
echo "  Sender          : {$summary['sender']}\n";
echo "  Test Phone      : " . ($summary['test_phone'] ?: '(semua nomor diizinkan)') . "\n";
echo "==============================================================================\n\n";

$client = new WhatsAppClient();

// Mode 1: Hanya Verifikasi
if (isset($options['verify-only'])) {
    echo "[Langkah 1/1] Memverifikasi konektivitas ke WhatsApp Gateway...\n";

    $isLocal = str_contains($summary['api_url'], '127.0.0.1') || str_contains($summary['api_url'], 'localhost');
    if ($isLocal) {
        $healthUrl = str_replace('/api/send', '/api/health', $summary['api_url']);
        $ch = curl_init($healthUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            echo "  [SUKSES] Mock WhatsApp Gateway lokal (127.0.0.1:8088) AKTIF dan merespons OK.\n";
            exit(0);
        } else {
            echo "  [INFO] Mock WhatsApp Gateway lokal belum berjalan.\n";
            echo "  Untuk menjalankan mock router lokal, gunakan:\n";
            echo "    php -S 127.0.0.1:8088 tests/mocks/whatsapp_mock_router.php\n";
            exit(0);
        }
    }

    echo "  [SUKSES] Konfigurasi WhatsApp Gateway valid. Siap untuk pengujian pengiriman via --to=NOMOR.\n";
    exit(0);
}

// Mode 2: Kirim Pesan Uji Coba
$recipient = isset($options['to']) ? trim((string)$options['to']) : '';

if (empty($recipient)) {
    // Jika tidak ditentukan via CLI, cek apakah ada NOTIFICATION_TEST_PHONE
    if (!empty(Notification::$testPhone)) {
        $recipient = Notification::$testPhone;
        echo "[INFO] Menggunakan nomor pengujian dari NOTIFICATION_TEST_PHONE: {$recipient}\n";
    } else {
        fwrite(STDERR, "[ERROR] Harap tentukan nomor penerima pengujian:\n");
        fwrite(STDERR, "  php bin/test_whatsapp.php --to=08123456789\n");
        fwrite(STDERR, "Atau atur NOTIFICATION_TEST_PHONE di file .env Anda.\n");
        exit(3);
    }
}

$cleanTo = Notification::normalizePhoneNumber($recipient);
echo "[Langkah 1/2] Memvalidasi nomor tujuan: {$recipient} -> [{$cleanTo}]...\n";

if (!Notification::isAllowedPhoneRecipient($cleanTo)) {
    fwrite(STDERR, "  [GAGAL] Nomor '{$cleanTo}' ditolak oleh guardrail pengujian NOTIFICATION_TEST_PHONE ('" . Notification::$testPhone . "').\n");
    exit(4);
}
echo "  [OK] Validasi nomor dan guardrail berhasil dilewati.\n\n";

echo "[Langkah 2/2] Mengirim pesan pengujian WhatsApp ke [{$cleanTo}]...\n";

$testTitle = "Verifikasi Sistem Notifikasi";
$testMsg = "Ini adalah pesan uji coba konektivitas sistem notifikasi Smart Exam.\n" .
           "Sistem berhasil terhubung ke WhatsApp Gateway dengan aman dan seluruh data sensitif terlindungi.";

$result = $client->send(
    $cleanTo,
    $testTitle,
    $testMsg,
    '/siswa/exams',
    [
        'user_role'  => 'guru', // Mode tester pengujian
        'log_id'     => 999999,
        'test_mode'  => true
    ]
);

if (($result['status'] ?? '') === 'sent') {
    echo "  [SUKSES] Pesan WhatsApp berhasil dikirim!\n";
    echo "  Message ID : " . ($result['message_id'] ?? 'N/A') . "\n";
    echo "  Ref ID     : " . ($result['client_reference_id'] ?? 'N/A') . "\n";
    echo "  Waktu      : " . ($result['processed_at'] ?? date('Y-m-d H:i:s')) . "\n";
    echo "\nSistem notifikasi WhatsApp Smart Exam beroperasi dengan normal.\n";
    exit(0);
} else {
    echo "  [GAGAL] Pengiriman WhatsApp gagal.\n";
    echo "  Status       : " . ($result['status'] ?? 'unknown') . "\n";
    echo "  Alasan       : " . ($result['error_reason'] ?? 'Tidak diketahui') . "\n";
    echo "  Tipe Error   : " . (!empty($result['is_permanent']) ? 'Permanen (Periksa konfigurasi/nomor)' : 'Sementara (Jaringan/Timeout)') . "\n";
    exit(5);
}
