<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;

/**
 * Rate Limit Filter (Middleware)
 * Melindungi endpoint sensitif dan AI dari spam / request berlebih (DDoS & API Quota exhaustion).
 */
class RateLimitFilter implements FilterInterface
{
    /**
     * Memeriksa batas laju request sebelum controller dieksekusi
     * 
     * @param Request $request
     * @param array $arguments [maxRequests, decaySeconds] contoh: ['30', '60']
     */
    public function before(Request $request, array $arguments = []): ?Response
    {
        $maxAttempts = isset($arguments[0]) ? (int)$arguments[0] : 30; // default 30 requests
        $decaySeconds = isset($arguments[1]) ? (int)$arguments[1] : 60; // per 60 detik

        $userId = user_id() ?: 'guest';
        $ip = $request->getIp() ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $path = '/' . trim($request->getPath(), '/');
        $rateKey = md5("ratelimit:{$userId}:{$ip}:{$path}");

        $now = time();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['_rate_limits']) || !is_array($_SESSION['_rate_limits'])) {
            $_SESSION['_rate_limits'] = [];
        }

        $attempts = $_SESSION['_rate_limits'][$rateKey] ?? [];

        // Hapus timestamp yang sudah melewati batas decay
        $attempts = array_filter($attempts, function ($timestamp) use ($now, $decaySeconds) {
            return ($now - $timestamp) < $decaySeconds;
        });

        if (count($attempts) >= $maxAttempts) {
            $oldest = reset($attempts);
            $retryAfter = $oldest ? max(1, $decaySeconds - ($now - $oldest)) : $decaySeconds;

            if ($request->isAjax()) {
                $response = new Response();
                return $response->json([
                    'status'      => 'error',
                    'success'     => false,
                    'message'     => "Terlalu banyak percobaan. Harap tunggu {$retryAfter} detik sebelum mencoba kembali.",
                    'retry_after' => $retryAfter
                ], 429);
            }

            Session::setFlash('danger', "Terlalu banyak percobaan. Harap tunggu {$retryAfter} detik sebelum mencoba kembali.");
            $response = new Response();
            return $response->redirect($_SERVER['HTTP_REFERER'] ?? base_url());
        }

        // Catat request sekarang
        $attempts[] = $now;
        $_SESSION['_rate_limits'][$rateKey] = array_values($attempts);

        return null;
    }

    /**
     * Reset / bersihkan limit untuk path tertentu (misal: setelah sukses login/verifikasi)
     */
    public static function clearLimit(string $path, ?string $ip = null, mixed $userId = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $uid = $userId ?: (user_id() ?: 'guest');
        $clientIp = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $cleanPath = '/' . trim($path, '/');
        $rateKey = md5("ratelimit:{$uid}:{$clientIp}:{$cleanPath}");

        if (isset($_SESSION['_rate_limits'][$rateKey])) {
            unset($_SESSION['_rate_limits'][$rateKey]);
        }
    }

    /**
     * Ambil jumlah percobaan aktif saat ini
     */
    public static function getAttempts(string $path, ?string $ip = null, mixed $userId = null, int $decaySeconds = 60): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $uid = $userId ?: (user_id() ?: 'guest');
        $clientIp = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $cleanPath = '/' . trim($path, '/');
        $rateKey = md5("ratelimit:{$uid}:{$clientIp}:{$cleanPath}");

        $now = time();
        $attempts = $_SESSION['_rate_limits'][$rateKey] ?? [];
        $active = array_filter($attempts, function ($timestamp) use ($now, $decaySeconds) {
            return ($now - $timestamp) < $decaySeconds;
        });

        return count($active);
    }

    public function after(Request $request, Response $response, array $arguments = []): void
    {
        // No post-processing needed
    }
}
