<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Config\App;

/**
 * Session Library
 * Manages secure PHP sessions, Flash Data, and CSRF protection tokens.
 */
class Session
{
    private static bool $started = false;

    /**
     * Memulai session jika belum aktif
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Konfigurasi cookie session aman jika header belum terkirim
        if (!headers_sent()) {
            $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            session_set_cookie_params([
                'lifetime' => App::$sessionLifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        @session_start();
        self::$started = true;

        // Auto generate CSRF Token jika belum ada
        if (!self::has('_csrf_token')) {
            self::generateCsrfToken();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        @session_destroy();
        self::$started = false;
    }

    /**
     * Regenerasi ID Session untuk mencegah Session Fixation attack
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            return @session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    /**
     * Ambil ID Session saat ini
     */
    public static function id(): string
    {
        self::start();
        return session_id() ?: '';
    }

    /**
     * Set Flash Message (hanya tampil 1x request berikutnya)
     */
    public static function setFlash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * Ambil Flash Message dan hapus dari session
     */
    public static function getFlash(string $type): array
    {
        self::start();
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    /**
     * Cek apakah ada flash message dengan tipe tertentu
     */
    public static function hasFlash(string $type): bool
    {
        self::start();
        return !empty($_SESSION['_flash'][$type]);
    }

    /**
     * Ambil semua flash messages lalu bersihkan
     */
    public static function getAllFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    /**
     * Generate CSRF Token baru
     */
    public static function generateCsrfToken(): string
    {
        self::start();
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        return $token;
    }

    /**
     * Ambil CSRF Token aktif
     */
    public static function getCsrfToken(): string
    {
        self::start();
        return $_SESSION['_csrf_token'] ?? self::generateCsrfToken();
    }

    /**
     * Verifikasi CSRF Token
     */
    public static function verifyCsrfToken(?string $token): bool
    {
        self::start();
        $sessionToken = self::getCsrfToken();
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}
