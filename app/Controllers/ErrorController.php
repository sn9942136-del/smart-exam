<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\App;
use App\Libraries\Response;

/**
 * Error Controller
 * Handles application HTTP error pages and error formatting.
 */
class ErrorController extends BaseController
{
    /**
     * Error 404 - Not Found
     */
    public function notFound(): Response
    {
        if ($this->request->isAjax()) {
            return $this->json([
                'status'  => 'error',
                'code'    => 404,
                'message' => 'Halaman atau endpoint yang Anda cari tidak ditemukan.'
            ], 404);
        }

        $data = [
            'pageTitle' => '404 - Halaman Tidak Ditemukan',
            'requestedPath' => $this->request->getPath()
        ];

        $response = $this->view('errors/404', $data);
        $response->setStatusCode(404);
        return $response;
    }

    /**
     * Error 500 - Internal Server Error
     */
    public function serverError(?\Throwable $exception = null): Response
    {
        if ($this->request->isAjax()) {
            return $this->json([
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Terjadi kesalahan internal pada server.',
                'debug'   => App::$appDebug && $exception ? [
                    'message' => $exception->getMessage(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine()
                ] : null
            ], 500);
        }

        $data = [
            'pageTitle' => '500 - Terjadi Kesalahan Server',
            'exception' => $exception,
            'debug'     => App::$appDebug
        ];

        $response = $this->view('errors/500', $data);
        $response->setStatusCode(500);
        return $response;
    }

    /**
     * Error 403 - Forbidden / Unauthorized
     */
    public function unauthorized(): Response
    {
        if ($this->request->isAjax()) {
            return $this->json([
                'status'  => 'error',
                'code'    => 403,
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk halaman ini.'
            ], 403);
        }

        $data = [
            'pageTitle' => '403 - Akses Ditolak'
        ];

        $response = $this->view('errors/403', $data);
        $response->setStatusCode(403);
        return $response;
    }
}
