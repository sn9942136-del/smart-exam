<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;

/**
 * Auth Filter (Middleware)
 * Memastikan pengguna telah terotentikasi (login) sebelum mengakses rute tertentu.
 */
class AuthFilter implements FilterInterface
{
    public function before(Request $request, array $arguments = []): ?Response
    {
        if (!is_logged_in()) {
            if ($request->isAjax()) {
                $response = new Response();
                return $response->json([
                    'status'  => 'error',
                    'message' => 'Sesi login Anda tidak valid atau telah berakhir.'
                ], 401);
            }

            Session::setFlash('warning', 'Silakan masuk ke akun Anda terlebih dahulu.');
            $response = new Response();
            return $response->redirect(base_url('login'));
        }

        // Cek kadaluarsa sesi berdasarkan durasi inaktivitas
        $lastActivity = Session::get('_last_activity');
        $lifetime = \App\Config\App::$sessionLifetime;
        if ($lastActivity !== null && (time() - (int)$lastActivity) > $lifetime) {
            logout_user();
            Session::destroy();

            if ($request->isAjax()) {
                $response = new Response();
                return $response->json([
                    'status'  => 'error',
                    'message' => 'Sesi login Anda telah kadaluarsa karena tidak ada aktivitas. Silakan login kembali.'
                ], 401);
            }

            Session::setFlash('warning', 'Sesi login Anda telah berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
            $response = new Response();
            return $response->redirect(base_url('login'));
        }

        Session::set('_last_activity', time());

        return null;
    }

    public function after(Request $request, Response $response, array $arguments = []): void
    {
        // No post-processing needed
    }
}
