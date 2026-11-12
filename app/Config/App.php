<?php

declare(strict_types=1);

namespace App\Config;

/**
 * App Configuration Class
 * Central application settings loader and environment management.
 */
class App
{
    public static string $appName = 'Smart Exam';
    public static string $appEnv = 'development';
    public static bool $appDebug = true;
    public static string $appUrl = 'http://localhost/PRD/public';
    public static string $timezone = 'Asia/Jakarta';

    public static int $sessionLifetime = 7200; // 2 hours
    public static bool $csrfProtection = true;

    /**
     * Inisialisasi konfigurasi dari environment file .env
     */
    public static function load(): void
    {
        self::$appName = self::env('APP_NAME', self::$appName);
        self::$appEnv = self::env('APP_ENV', self::$appEnv);
        self::$appDebug = filter_var(self::env('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN);
        self::$appUrl = rtrim(self::env('APP_URL', self::detectBaseUrl()), '/');
        self::$timezone = self::env('APP_TIMEZONE', self::$timezone);

        self::$sessionLifetime = (int) self::env('SESSION_LIFETIME', self::$sessionLifetime);
        self::$csrfProtection = filter_var(self::env('CSRF_PROTECTION', true), FILTER_VALIDATE_BOOLEAN);

        date_default_timezone_set(self::$timezone);
    }

    /**
     * Helper untuk mengambil nilai environment dengan fallback default.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }

        if ($value === 'true' || $value === '(true)') {
            return true;
        }
        if ($value === 'false' || $value === '(false)') {
            return false;
        }
        if ($value === 'empty' || $value === '(empty)' || $value === 'null') {
            return null;
        }

        return $value;
    }

    /**
     * Mendeteksi Base URL secara otomatis jika tidak didefinisikan di .env
     */
    private static function detectBaseUrl(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        $baseDir = ($baseDir === '/' || $baseDir === '\\') ? '' : $baseDir;

        return "{$scheme}://{$host}{$baseDir}";
    }
}
