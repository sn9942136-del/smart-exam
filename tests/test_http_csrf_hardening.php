<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Filters\CsrfFilter;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Router;
use App\Libraries\Session;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: HTTP METHOD & CSRF HARDENING REGRESSION TEST\n";
echo "==============================================================================\n\n";

$passedCount = 0;
$totalCount = 0;

function runTest(string $title, callable $fn): void {
    global $passedCount, $totalCount;
    $totalCount++;
    try {
        $fn();
        echo "  ✓ [PASS] {$title}\n";
        $passedCount++;
    } catch (\Throwable $e) {
        echo "  ✗ [FAIL] {$title}: " . $e->getMessage() . "\n";
    }
}

// ----------------------------------------------------------------------
// 1. Audit Route Definitions: Hardened Routes must NOT accept GET
// ----------------------------------------------------------------------
echo "[1/3] Memeriksa bahwa endpoint mutasi data menolak HTTP GET...\n";

$hardenedUris = [
    '/admin/users/delete/999',
    '/admin/users/toggle-status/999',
    '/admin/roles/delete/999',
    '/admin/academic-years/delete/999',
    '/admin/academic-years/set-active/999',
    '/admin/semesters/delete/999',
    '/admin/semesters/set-active/999',
    '/admin/classes/delete/999',
    '/admin/subjects/delete/999',
    '/admin/ai-configuration/reset',
    '/guru/question-banks/delete/999',
    '/guru/questions/delete/999',
    '/guru/ai-questions/delete/999',
];

// Load routes into Router table
require __DIR__ . '/../app/Config/Routes.php';

$allRoutes = Router::getRoutes();

foreach ($hardenedUris as $uri) {
    runTest("Endpoint '{$uri}' TIDAK boleh menerima HTTP GET", function () use ($allRoutes, $uri) {
        // Cek apakah ada route GET yang match
        foreach ($allRoutes as $route) {
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri)) {
                if (in_array('GET', $route['methods'], true)) {
                    throw new \Exception("Route {$route['uri']} masih menerima method GET!");
                }
                if (!in_array('POST', $route['methods'], true)) {
                    throw new \Exception("Route {$route['uri']} tidak menerima method POST!");
                }
            }
        }
    });
}

// ----------------------------------------------------------------------
// 2. Test CSRF Filter: POST Tanpa Token CSRF Harus Ditolak
// ----------------------------------------------------------------------
echo "\n[2/3] Memeriksa CSRF Filter Rejection saat token absen/invalid...\n";

runTest("CsrfFilter menolak AJAX POST tanpa token (HTTP 419)", function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = [];
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);

    $request = new Request();
    $filter = new CsrfFilter();
    $response = $filter->before($request);

    if ($response === null) {
        throw new \Exception("CsrfFilter mengizinkan AJAX POST tanpa CSRF token.");
    }

    if ($response->getStatusCode() !== 419) {
        throw new \Exception("Status code yang diharapkan 419, didapat: " . $response->getStatusCode());
    }
});

runTest("CsrfFilter menolak Form POST standar dengan token salah", function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    $_POST = ['_csrf_token' => 'invalid-token-12345'];

    $request = new Request();
    $filter = new CsrfFilter();
    $response = $filter->before($request);

    if ($response === null) {
        throw new \Exception("CsrfFilter mengizinkan request dengan token CSRF salah.");
    }

    $flash = Session::getFlash('danger');
    if (!$flash) {
        throw new \Exception("Flash message keamanan CSRF tidak diset.");
    }
});

// ----------------------------------------------------------------------
// 3. Test CSRF Filter: POST Dengan Token CSRF Valid Harus Lolos
// ----------------------------------------------------------------------
echo "\n[3/3] Memeriksa CSRF Filter Acceptance saat token valid...\n";

runTest("CsrfFilter mengizinkan Form POST dengan _csrf_token valid", function () {
    $validToken = Session::generateCsrfToken();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    $_POST = ['_csrf_token' => $validToken];

    $request = new Request();
    $filter = new CsrfFilter();
    $response = $filter->before($request);

    if ($response !== null) {
        throw new \Exception("CsrfFilter menolak request dengan valid token!");
    }
});

runTest("CsrfFilter mengizinkan AJAX POST dengan X-CSRF-TOKEN header valid", function () {
    $validToken = Session::generateCsrfToken();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $validToken;
    $_POST = [];

    $request = new Request();
    $filter = new CsrfFilter();
    $response = $filter->before($request);

    if ($response !== null) {
        throw new \Exception("CsrfFilter menolak AJAX dengan header X-CSRF-TOKEN valid!");
    }
});

echo "\n==============================================================================\n";
echo "HASIL PENGUJIAN: {$passedCount} / {$totalCount} Test Case BERHASIL (" . round(($passedCount / $totalCount) * 100) . "% PASSED)\n";
echo "==============================================================================\n";
