<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Libraries\Database;

$db = Database::getConnection();
$row = $db->query("SELECT id, error_message, raw_response, prompt FROM ai_question_generations ORDER BY id DESC LIMIT 1")->fetch();

echo "ID: " . $row['id'] . "\n";
echo "Error Message: " . $row['error_message'] . "\n";
echo "Raw Response Length: " . strlen($row['raw_response'] ?? '') . "\n";
echo "Raw Response Full:\n";
echo $row['raw_response'] . "\n";
