<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Libraries\Database;

$db = Database::getConnection();
$types = $db->query("SELECT id, code, name, severity, penalty_score FROM ai_violation_types")->fetchAll(PDO::FETCH_ASSOC);

echo "Total Violation Types: " . count($types) . "\n";
print_r($types);
