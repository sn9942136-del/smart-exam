<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Request;
use App\Libraries\Response;

/**
 * Filter Interface
 * Middleware contract for request pre-processing and response post-processing.
 */
interface FilterInterface
{
    /**
     * Dijalankan sebelum Controller dieksekusi.
     * Return Response object untuk menghentikan alur (misal redirect atau JSON error),
     * atau null untuk melanjutkan ke controller.
     */
    public function before(Request $request, array $arguments = []): ?Response;

    /**
     * Dijalankan setelah Controller selesai dieksekusi.
     */
    public function after(Request $request, Response $response, array $arguments = []): void;
}
