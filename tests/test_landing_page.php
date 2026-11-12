<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Config/Routes.php';

$request = new \App\Libraries\Request();
$controller = new \App\Controllers\HomeController($request);
$response = $controller->index();

echo "HTTP Status Code: " . $response->getStatusCode() . PHP_EOL;
echo "Body Length: " . strlen($response->getContent()) . " bytes" . PHP_EOL;

if ($response->getStatusCode() === 200 && strlen($response->getContent()) > 1000) {
    echo "[SUCCESS] Landing Page renders completely without errors!" . PHP_EOL;
} else {
    echo "[ERROR] Landing page render failed!" . PHP_EOL;
}
