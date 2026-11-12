<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;

/**
 * Permission Filter (Middleware)
 * Membatasi akses rute berdasarkan permission pengguna.
 */
class PermissionFilter implements FilterInterface
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

        if (empty($arguments)) {
            return null;
        }

        // Cek apakah user memiliki salah satu permission yang dibutuhkan
        foreach ($arguments as $perm) {
            if (has_permission($perm)) {
                return null;
            }
        }

        if ($request->isAjax()) {
            $response = new Response();
            return $response->json([
                'status'  => 'error',
                'message' => 'Akses ditolak: Anda tidak memiliki permission yang diperlukan.'
            ], 403);
        }

        Session::setFlash('danger', 'Akses Ditolak: Anda tidak memiliki izin untuk melakukan aksi ini.');
        $response = new Response();
        return $response->redirect(base_url('unauthorized'));
    }

    public function after(Request $request, Response $response, array $arguments = []): void
    {
        // No post-processing needed
    }
}
