<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\App;
use App\Config\Notification;
use App\Libraries\WhatsAppClient;

echo "==============================================================================\n";
echo "  PENGUJIAN INTEGRASI LANGSUNG FONNTE API WHATSAPP SMART EXAM\n";
echo "==============================================================================\n\n";

// 1. Audit Konfigurasi .env
Notification::load();
$apiUrl = Notification::$whatsappApiUrl;
$token = Notification::$whatsappApiToken;
$testPhone = Notification::$testPhone;

echo "--- 1. AUDIT KONFIGURASI ENVIRONMENT (.env) ---\n";
echo "• WHATSAPP_API_URL      : " . ($apiUrl ?: '(KOSONG)') . "\n";
echo "• WHATSAPP_API_TOKEN    : " . (!empty($token) ? 'TERSEDIA (Panjang: ' . strlen($token) . ' karakter)' : '(KOSONG)') . "\n";
echo "• NOTIFICATION_TEST_PHONE : " . ($testPhone ?: '(KOSONG)') . "\n";
echo "• PHP cURL Extension    : " . (extension_loaded('curl') ? 'AKTIF' : 'TIDAK TERSEDIA') . "\n";
echo "• OpenSSL (HTTPS)       : " . (extension_loaded('openssl') ? 'AKTIF' : 'TIDAK TERSEDIA') . "\n\n";

if (empty($apiUrl) || empty($token)) {
    echo "[GAGAL] Konfigurasi Fonnte tidak lengkap pada .env!\n";
    exit(1);
}

// 2. Pemeriksaan Perangkat di Dashboard Fonnte
echo "--- 2. PEMERIKSAAN PERANGKAT PENGIRIM (FONNTE DEVICE STATUS) ---\n";
$waClient = new WhatsAppClient();
$connCheck = $waClient->verifyConnection();

echo "• Status Endpoint        : " . ($connCheck['success'] ? 'TERHUBUNG' : 'PERHATIAN') . "\n";
echo "• Pesan Gateway          : " . $connCheck['message'] . "\n";
echo "• Status Perangkat       : [" . ($connCheck['device_status'] ?? 'unknown') . "]\n";

$details = $connCheck['details'] ?? [];
if (!empty($details)) {
    echo "• Nomor Terdaftar Fonnte : " . ($details['device'] ?? '-') . "\n";
    echo "• Nama Perangkat         : " . ($details['name'] ?? '-') . "\n";
    echo "• Paket Akun             : " . ($details['package'] ?? '-') . "\n";
    echo "• Sisa Kuota Pesan       : " . ($details['quota'] ?? '-') . "\n";
}
echo "\n";

// 3. Pengujian Kirim Pesan Pengujian Langsung ke Fonnte API
echo "--- 3. PENGUJIAN KIRIM PESAN KE FONNTE API (/send) ---\n";

// Gunakan target nomor pengujian yang sah
$target = '62881022834828';
$testOtp = '123456'; // Bukan OTP akun nyata

$otpMessage = WhatsAppClient::formatOtpMessage($testOtp, null, 5);

echo "Penerima Target : {$target}\n";
echo "Format Pesan yang Dikirim:\n";
echo "--------------------------------------------------------\n";
echo $otpMessage . "\n";
echo "--------------------------------------------------------\n\n";

// Kirim melalui WhatsAppClient langsung (mengabaikan mode simulation)
$directClient = new WhatsAppClient(['test_phone' => null]);
$res = $directClient->send(
    $target,
    'Kode Verifikasi OTP',
    $otpMessage,
    null,
    [
        'type' => 'otp_verification',
        'otp'  => $testOtp
    ]
);

echo "--- HASIL RESPONS PENGIRIMAN DARI FONNTE --- \n";
echo "• Status Internal   : " . ($res['status'] ?? '-') . "\n";
echo "• Provider Status   : " . ($res['provider_status'] ?? '-') . "\n";
echo "• HTTP Status Code  : " . ($res['http_code'] ?? '-') . "\n";
echo "• Message ID        : " . ($res['message_id'] ?? '-') . "\n";
if (!empty($res['error_reason'])) {
    echo "• Keterangan Error  : " . $res['error_reason'] . "\n";
}
if (!empty($res['payload'])) {
    echo "• Payload JSON Resp :\n";
    echo json_encode($res['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n==============================================================================\n";
if (($res['status'] ?? '') === 'sent') {
    echo "  KESIMPULAN: API ACCEPTED / BERHASIL DIKIRIM KE GATEWAY FONNTE\n";
} else {
    echo "  KESIMPULAN: PENGIRIMAN DITOLAK ATAU BELUM DAPAT DITERUSKAN OLEH FONNTE\n";
    echo "  Alasan: " . ($res['error_reason'] ?? 'Status perangkat Fonnte belum connect') . "\n";
}
echo "==============================================================================\n";

