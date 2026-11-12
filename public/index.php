<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
 * Front Controller & Main Entry Point
 * ==============================================================================
 */

// Muat bootstrap engine
require_once __DIR__ . '/../app/bootstrap.php';

// Inisialisasi HTTP Request
$request = new \App\Libraries\Request();

// Muat definisi routing
require_once __DIR__ . '/../app/Config/Routes.php';

// Jalankan Dispatcher
\App\Libraries\Router::dispatch($request);
