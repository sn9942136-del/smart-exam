<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * APPLICATION BOOTSTRAPPER
 * ==============================================================================
 */

define('APP_ROOT', dirname(__DIR__));
define('STORAGE_PATH', APP_ROOT . '/storage');
define('FCPATH', APP_ROOT . '/public/');

// 1. PSR-4 Class Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = APP_ROOT . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load Composer vendor libraries (Dompdf, PhpSpreadsheet, etc.)
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

// 2. Simple .env file parser
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 3. Load Helper Functions
require_once APP_ROOT . '/app/Helpers/app_helper.php';
require_once APP_ROOT . '/app/Helpers/auth_helper.php';

// 4. Initialize Configurations
\App\Config\App::load();
\App\Config\Notification::load();

// 5. Start Secure Session
\App\Libraries\Session::start();

// 6. Global Exception & Error Handlers
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e) {
    \App\Libraries\Logger::exception($e);

    $request = new \App\Libraries\Request();
    $controller = new \App\Controllers\ErrorController($request);
    $response = $controller->serverError($e);
    $response->send();
    exit(1);
});
