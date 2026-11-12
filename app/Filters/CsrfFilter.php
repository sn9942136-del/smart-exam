<?php

declare(strict_types=1);

namespace App\Filters;

use App\Config\App;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;

/**
 * CSRF Filter (Middleware)
 * Melindungi aplikasi dari serangan Cross-Site Request Forgery pada request state-changing.
 */
class CsrfFilter implements FilterInterface
{
    public function before(Request $request, array $arguments = []): ?Response
    {
        if (!App::$csrfProtection) {
            return null;
        }

        $method = $request->getMethod();
        // Hanya periksa request mutasi data
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $token = $request->input('_csrf_token') 
                ?? $request->input('csrf_token')
                ?? $request->header('X-CSRF-TOKEN') 
                ?? $request->header('X-Csrf-Token') 
                ?? $request->header('CSRF-Token');

            if (!Session::verifyCsrfToken($token)) {
                if ($request->isAjax()) {
                    $response = new Response();
                    return $response->json([
                        'status'  => 'error',
                        'message' => 'Token CSRF tidak valid atau sesi kadaluarsa. Silakan refresh halaman.'
                    ], 419);
                }

                Session::setFlash('danger', 'Validasi sesi keamanan (CSRF) gagal. Silakan coba lagi.');
                $response = new Response();
                return $response->redirect($_SERVER['HTTP_REFERER'] ?? base_url());
            }
        }

        return null;
    }

    public function after(Request $request, Response $response, array $arguments = []): void
    {
        // No post-processing needed
    }
}
