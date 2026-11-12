<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;

$db = Database::getConnection();
try {
    $db->exec("ALTER TABLE ai_chat_sessions MODIFY COLUMN role_context VARCHAR(50) NOT NULL DEFAULT 'siswa'");
    echo "MIGRATION_008_OK\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
