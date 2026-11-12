<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;

/**
 * Role Filter (Middleware)
 * Membatasi akses rute berdasarkan role pengguna (Super Admin/Admin, Guru, Peserta Didik).
 */
class RoleFilter implements FilterInterface
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

        // Cek apakah user memiliki salah satu role yang diizinkan
        if (!has_role($arguments)) {
            if ($request->isAjax()) {
                $response = new Response();
                return $response->json([
                    'status'  => 'error',
                    'message' => 'Akses ditolak: Anda tidak memiliki izin untuk tindakan ini.'
                ], 403);
            }

            Session::setFlash('danger', 'Akses Ditolak: Anda tidak memiliki izin untuk membuka halaman tersebut.');
            $response = new Response();
            return $response->redirect(base_url('unauthorized'));
        }

        return null;
    }

    public function after(Request $request, Response $response, array $arguments = []): void
    {
        // No post-processing needed
    }
}
