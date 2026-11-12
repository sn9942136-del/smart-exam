<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — GMAIL SMTP DIAGNOSTIC & VERIFICATION TOOL (CLI)
 * ==============================================================================
 * Utilitas command-line mandiri untuk memverifikasi konfigurasi dan koneksi
 * SMTP Gmail (Port 587 STARTTLS) secara aman tanpa mengirim email massal.
 *
 * Penggunaan:
 *   php bin/test_gmail_smtp.php --verify-only
 *   php bin/test_gmail_smtp.php --to=alamat.anda@gmail.com
 *   php bin/test_gmail_smtp.php --help
 *
 * Keamanan:
 * - Kredensial (password/token) TIDAK PERNAH ditampilkan di layar atau disimpan ke log.
 * - Memeriksa kepatuhan format 16-digit Google App Password.
 * - Menolak keras eksekusi jika database aktif adalah database operasional.
 * ==============================================================================
 */

// 1. Parsing Argumen CLI
$options = getopt('', ['to::', 'verify-only', 'help', 'verbose']);

if (isset($options['help'])) {
    echo "==============================================================================\n";
    echo "  SMART EXAM — GMAIL SMTP VERIFICATION TOOL\n";
    echo "==============================================================================\n";
    echo "Penggunaan:\n";
    echo "  php bin/test_gmail_smtp.php --verify-only\n";
    echo "      Memverifikasi handshake TLS dan autentikasi kredensial Gmail tanpa mengirim email.\n\n";
    echo "  php bin/test_gmail_smtp.php --to=alamat.email@anda.com\n";
    echo "      Mengirim satu pesan email uji coba format HTML ke alamat yang ditentukan.\n\n";
    echo "Opsi tambahan:\n";
    echo "  --verbose    Menampilkan rincian teknis handshake SMTP secara detail.\n";
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
use App\Libraries\SmtpClient;

// 3. Validasi Keamanan Database
try {
    $pdo = Database::getConnection();
    $actualDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    Notification::validateActiveDatabase($actualDb);
} catch (\Throwable $e) {
    fwrite(STDERR, "[SAFETY GUARD TRIGGERED] " . $e->getMessage() . "\n");
    exit(1);
}

// 4. Periksa Konfigurasi SMTP
Notification::load();
$summary = Notification::getSmtpConfigSummary();

echo "==============================================================================\n";
echo "  SMART EXAM — GMAIL SMTP DIAGNOSTIC & VERIFICATION\n";
echo "==============================================================================\n";
echo "  Target Database : {$actualDb}\n";
echo "  SMTP Host       : {$summary['host']}:{$summary['port']} ({$summary['encryption']})\n";
echo "  SMTP Username   : " . ($summary['username_set'] ? Notification::$smtpUsername : '[KOSONG]') . "\n";
echo "  SMTP Password   : " . Notification::maskSecret(Notification::$smtpPassword) . "\n";
echo "  From Email      : " . ($summary['from_email'] ?: '[KOSONG]') . "\n";
echo "  From Name       : " . ($summary['from_name'] ?: 'Smart Exam') . "\n";
echo "==============================================================================\n\n";

if (!$summary['is_configured']) {
    fwrite(STDERR, "[KONFIGURASI BELUM LENGKAP]\n");
    fwrite(STDERR, "Variabel berikut belum diatur di file .env Anda:\n");
    foreach ($summary['missing'] as $miss) {
        fwrite(STDERR, "  - {$miss}\n");
    }
    fwrite(STDERR, "\nPetunjuk:\n");
    fwrite(STDERR, "1. Salin .env.example menjadi .env (jika belum ada).\n");
    fwrite(STDERR, "2. Buat App Password 16 karakter di Akun Google Anda.\n");
    fwrite(STDERR, "3. Isi SMTP_USERNAME=email_anda@gmail.com dan SMTP_PASSWORD=16karakterapppass.\n");
    exit(2);
}

// 5. Validasi Karakteristik App Password Google
$cleanPass = str_replace(' ', '', Notification::$smtpPassword);
if (strlen($cleanPass) !== 16 && Notification::$smtpHost === 'smtp.gmail.com') {
    echo "[PERINGATAN FORMAT PASSWORD]\n";
    echo "Panjang password yang Anda masukkan adalah " . strlen(Notification::$smtpPassword) . " karakter.\n";
    echo "Google App Password umumnya terdiri dari tepat 16 huruf kecil tanpa spasi.\n";
    echo "Pastikan Anda menggunakan 'Sandi Aplikasi' (App Password), BUKAN password akun Google biasa.\n\n";
}

$client = new SmtpClient();

// 6. Mode Verifikasi Saja (--verify-only)
if (isset($options['verify-only']) || empty($options['to'])) {
    echo "[Langkah 1/2] Melakukan handshake STARTTLS dan autentikasi kredensial ke {$summary['host']}...\n";
    $verifyResult = $client->verifyConnection();

    if ($verifyResult['success']) {
        echo "  [SUKSES] " . $verifyResult['message'] . "\n\n";
        echo "Konfigurasi SMTP Gmail Anda telah valid dan siap digunakan oleh Notification Worker!\n";
        echo "Untuk mengirim email uji coba ke alamat Anda, jalankan:\n";
        echo "  php bin/test_gmail_smtp.php --to=" . (Notification::$smtpUsername ?: 'email_anda@gmail.com') . "\n";
        exit(0);
    } else {
        fwrite(STDERR, "  [GAGAL] " . $verifyResult['message'] . "\n\n");
        fwrite(STDERR, "Kemungkinan penyebab:\n");
        fwrite(STDERR, "1. 2-Step Verification pada Akun Google belum aktif.\n");
        fwrite(STDERR, "2. Sandi Aplikasi (App Password) salah ketik atau telah dicabut.\n");
        fwrite(STDERR, "3. Port 587 diblokir oleh ISP / firewall lokal Anda.\n");
        exit(3);
    }
}

// 7. Mode Pengiriman Nyata (--to=target)
$targetEmail = trim((string)$options['to']);
echo "[Langkah 1/2] Memvalidasi alamat penerima pengujian: {$targetEmail}...\n";
if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "[ERROR] Format email penerima '{$targetEmail}' tidak valid.\n");
    exit(4);
}

echo "[Langkah 2/2] Mengirim email uji coba notifikasi Smart Exam ke {$targetEmail}...\n";
$subject = "Uji Coba Notifikasi Email Smart Exam — " . date('d M Y H:i:s');
$message = "Halo!\n\nEmail ini adalah pesan konfirmasi bahwa integrasi SMTP Gmail pada sistem notifikasi Smart Exam telah berhasil dikonfigurasi dan berfungsi dengan baik.\n\nWaktu pengujian: " . date('d F Y H:i:s T') . "\nHost: " . $summary['host'] . ":" . $summary['port'] . "\nEnkripsi: " . strtoupper($summary['encryption']);
$html = SmtpClient::buildHtmlTemplate($subject, $message, base_url(), 'Administrator / Penguji');

$sendResult = $client->send($targetEmail, $subject, $html, $message, [
    'test_run' => true,
    'cli'      => true
]);

if ($sendResult['status'] === 'sent') {
    echo "==============================================================================\n";
    echo "  PENGIRIMAN EMAIL BERHASIL!\n";
    echo "==============================================================================\n";
    echo "  Status        : " . strtoupper($sendResult['status']) . "\n";
    echo "  Penerima      : {$targetEmail}\n";
    echo "  Message-ID    : " . ($sendResult['message_id'] ?? 'N/A') . "\n";
    echo "  Kode Respons  : " . ($sendResult['smtp_code'] ?? 250) . " (" . ($sendResult['smtp_response'] ?? 'OK') . ")\n";
    echo "  Waktu Kirim   : " . ($sendResult['processed_at'] ?? date('Y-m-d H:i:s')) . "\n";
    echo "==============================================================================\n\n";
    echo "Silakan periksa folder Inbox (atau Spam/Promotions) pada alamat {$targetEmail}.\n";
    exit(0);
} else {
    fwrite(STDERR, "==============================================================================\n");
    fwrite(STDERR, "  PENGIRIMAN EMAIL GAGAL!\n");
    fwrite(STDERR, "==============================================================================\n");
    fwrite(STDERR, "  Status        : " . strtoupper((string)$sendResult['status']) . "\n");
    fwrite(STDERR, "  Penerima      : {$targetEmail}\n");
    fwrite(STDERR, "  Alasan Gagal  : " . ($sendResult['error_reason'] ?? 'Tidak diketahui') . "\n");
    fwrite(STDERR, "  Tipe Error    : " . (!empty($sendResult['is_permanent']) ? 'Permanen' : 'Sementara') . "\n");
    fwrite(STDERR, "==============================================================================\n");
    exit(5);
}
