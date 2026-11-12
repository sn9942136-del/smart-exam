<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Libraries\Database;

$db = Database::getConnection();

// Check if updated_at exists in ai_chat_messages
$cols = $db->query("SHOW COLUMNS FROM ai_chat_messages LIKE 'updated_at'")->fetch();
if (!$cols) {
    $db->exec("ALTER TABLE `ai_chat_messages` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;");
    echo "Column updated_at added to ai_chat_messages.\n";
} else {
    echo "Column updated_at already exists.\n";
}
