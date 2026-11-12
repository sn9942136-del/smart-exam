<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\WhatsAppClient;

$wa = new WhatsAppClient();
$diag = $wa->verifyConnection();

echo "=== DIAGNOSTIK STATUS FONNTE SAAT INI ===\n";
echo "Success       : " . ($diag['success'] ? 'TRUE' : 'FALSE') . "\n";
echo "Message       : " . $diag['message'] . "\n";
echo "Device Status : " . ($diag['device_status'] ?? 'N/A') . "\n";
if (!empty($diag['details'])) {
    echo "Details       :\n" . json_encode($diag['details'], JSON_PRETTY_PRINT) . "\n";
}
echo "=========================================\n";

