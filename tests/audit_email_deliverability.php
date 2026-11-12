<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Libraries\SmtpClient;
use App\Config\Notification;

echo "==============================================================================\n";
echo "  AUDIT EMAIL DELIVERABILITY & SMTP CONFIGURATION\n";
echo "==============================================================================\n\n";

Notification::load(true);

echo "--- 1. Konfigurasi SMTP (Kredensial Disamarkan) ---\n";
echo "Host        : " . Notification::$smtpHost . "\n";
echo "Port        : " . Notification::$smtpPort . "\n";
echo "Encryption  : " . Notification::$smtpEncryption . "\n";
echo "Username    : " . Notification::maskSecret(Notification::$smtpUsername) . "\n";
echo "From Email  : " . Notification::$smtpFromEmail . "\n";
echo "From Name   : " . Notification::$smtpFromName . "\n";
echo "Test Recip  : " . (Notification::$testRecipient ?? '(null)') . "\n";
echo "Mode        : " . Notification::getMode() . "\n";
echo "Configured? : " . (Notification::isSmtpConfigured() ? 'YES' : 'NO') . "\n";

echo "\n--- 2. Pemeriksaan Log Email Terakhir ---\n";
$db = Database::getConnection();
$stmt = $db->query("SELECT id, recipient, status, attempts, sent_at, created_at, response_payload FROM notification_logs WHERE channel = 'email' ORDER BY id DESC LIMIT 3");
$recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recentLogs as $log) {
    $payload = json_decode($log['response_payload'] ?? '{}', true) ?: [];
    echo "ID #{$log['id']} -> Status: {$log['status']}, Recipient: {$log['recipient']}, SentAt: {$log['sent_at']}\n";
    echo "   Subject: " . ($payload['subject'] ?? $payload['title'] ?? '-') . "\n";
    echo "   Transport: " . ($payload['smtp_transport'] ?? $payload['transport'] ?? '-') . "\n";
    echo "   SMTP Code: " . ($payload['smtp_code'] ?? '-') . "\n";
    echo "   Error: " . ($payload['error_reason'] ?? 'none') . "\n";
}

echo "\n--- 3. Verifikasi Handshake & Kredensial SMTP Gmail ---\n";
$smtpClient = new SmtpClient();
$verifyResult = $smtpClient->verifyConnection();
echo "Koneksi SMTP Berhasil? : " . ($verifyResult['success'] ? 'YA' : 'TIDAK') . "\n";
echo "Pesan Status           : " . $verifyResult['message'] . "\n";
if (!empty($verifyResult['banner'])) {
    echo "Banner Server          : " . $verifyResult['banner'] . "\n";
}

echo "\n--- 4. Pemeriksaan Domain & DNS (SPF, DKIM, DMARC) ---\n";
$domain = substr(strrchr(Notification::$smtpFromEmail, "@"), 1) ?: 'gmail.com';
echo "Domain Pengirim: {$domain}\n";

// Cek SPF (TXT records)
$txtRecords = @dns_get_record($domain, DNS_TXT);
$spfRecord = null;
if (is_array($txtRecords)) {
    foreach ($txtRecords as $rec) {
        $txt = $rec['txt'] ?? $rec['entries'][0] ?? '';
        if (str_starts_with($txt, 'v=spf1')) {
            $spfRecord = $txt;
            break;
        }
    }
}
echo "SPF Record     : " . ($spfRecord ?? 'Tidak ditemukan via DNS') . "\n";

// Cek DMARC (_dmarc.domain)
$dmarcRecords = @dns_get_record("_dmarc.{$domain}", DNS_TXT);
$dmarcRecord = null;
if (is_array($dmarcRecords)) {
    foreach ($dmarcRecords as $rec) {
        $txt = $rec['txt'] ?? $rec['entries'][0] ?? '';
        if (str_starts_with($txt, 'v=DMARC1')) {
            $dmarcRecord = $txt;
            break;
        }
    }
}
echo "DMARC Record   : " . ($dmarcRecord ?? 'Tidak ditemukan via DNS') . "\n";

echo "\n==============================================================================\n";

