<?php

declare(strict_types=1);

use App\Config\App;
use App\Libraries\Session;

if (!function_exists('base_url')) {
    /**
     * Menghasilkan Full URL berdasarkan path yang diberikan
     */
    function base_url(string $path = ''): string
    {
        $baseUrl = App::$appUrl;
        $path = ltrim($path, '/');
        return empty($path) ? $baseUrl : "{$baseUrl}/{$path}";
    }
}

if (!function_exists('url')) {
    /**
     * Alias untuk base_url()
     */
    function url(string $path = ''): string
    {
        return base_url($path);
    }
}


if (!function_exists('asset')) {
    /**
     * Menghasilkan URL asset dari direktori public/assets/ dengan cache busting otomatis
     */
    function asset(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $fullPath = defined('FCPATH') ? FCPATH . 'assets/' . $path : (defined('APP_ROOT') ? APP_ROOT . '/public/assets/' . $path : '');
        $version = ($fullPath !== '' && file_exists($fullPath)) ? '?v=' . filemtime($fullPath) : '';
        return base_url("assets/{$path}") . $version;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Mengambil CSRF token aktif
     */
    function csrf_token(): string
    {
        return Session::getCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Menghasilkan hidden input tag CSRF
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('e')) {
    /**
     * Sanitasi output string untuk mencegah XSS
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_html')) {
    /**
     * Membersihkan string HTML dari tag dan atribut berbahaya (XSS prevention)
     * Mengizinkan tag format teks standar (b, i, u, strong, em, code, pre, p, br, table, ul, ol, li, span, h1-h6, img, a, blockquote)
     */
    function sanitize_html(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $cleaned = (string) $html;

        // 1. Recursive removal of dangerous tags and their content
        $dangerousTags = 'script|style|iframe|object|embed|applet|meta|link|form|svg|math|base|input|button';
        $prev = '';
        while ($prev !== $cleaned) {
            $prev = $cleaned;
            $cleaned = preg_replace('/<(?:' . $dangerousTags . ')[^>]*>.*?<\/(?:' . $dangerousTags . ')>/si', '', $cleaned);
            $cleaned = preg_replace('/<\/?(?:' . $dangerousTags . ')[^>]*\/?>/si', '', $cleaned);
        }

        // 2. Remove all event handlers on* (onerror, onload, onclick, onmouseover, onfocus, onblur, etc.)
        $cleaned = preg_replace('/\s+on[a-zA-Z0-9_\-]+\s*=\s*([\'"]).*?\1/si', '', $cleaned);
        $cleaned = preg_replace('/\s+on[a-zA-Z0-9_\-]+\s*=\s*[^\s>]+/si', '', $cleaned);

        // 3. Neutralize dangerous pseudo-protocols in href, src, action, formaction attributes
        $cleaned = preg_replace_callback(
            '/\b(href|src|action|formaction)\s*=\s*([\'"])(.*?)\2/si',
            function ($matches) {
                $attr = $matches[1];
                $val = trim($matches[3]);
                // Allow safe data:image/ URIs
                if (preg_match('/^(?:javascript|vbscript):/i', $val) || (preg_match('/^data:/i', $val) && !preg_match('/^data:image\/(?:png|jpeg|jpg|gif|webp);base64,/i', $val))) {
                    return $attr . '="#"';
                }
                return $matches[0];
            },
            $cleaned
        );
        $cleaned = preg_replace('/\b(href|src|action|formaction)\s*=\s*(?:javascript|vbscript):[^\s>]+/si', '$1="#"', $cleaned);

        // 4. Disarm style attributes with script execution or expression
        $cleaned = preg_replace('/style\s*=\s*([\'"]).*?(?:expression|javascript|behavior|vbscript).*?\1/si', '', $cleaned);

        return $cleaned;
    }
}

if (!function_exists('clean_plain_text')) {
    /**
     * Membersihkan string teks biasa dari tag HTML dan konten script/style berbahaya
     */
    function clean_plain_text(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        return trim(strip_tags(sanitize_html($text)));
    }
}

if (!function_exists('old')) {
    /**
     * Mengambil nilai input request sebelumnya
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::get('_old_input_' . $key, $default);
    }
}

if (!function_exists('system_setting')) {
    /**
     * Mengambil nilai konfigurasi sistem terpusat dari database dengan fallback default
     */
    function system_setting(string $key, mixed $default = null): mixed
    {
        return \App\Models\SystemSettingModel::getVal($key, $default);
    }
}

if (!function_exists('app_name')) {
    /**
     * Mengambil nama aplikasi
     */
    function app_name(): string
    {
        return (string) system_setting('app_name', App::$appName);
    }
}

if (!function_exists('set_flash')) {
    /**
     * Set Flash Message ke session
     */
    function set_flash(string $type, string $message): void
    {
        Session::setFlash($type, $message);
    }
}

if (!function_exists('flash')) {
    /**
     * Helper serbaguna untuk flash message atau flash data
     */
    function flash(string $key, mixed $value = null): mixed
    {
        if ($value === null) {
            return Session::getFlash($key);
        }
        if (is_array($value)) {
            Session::set('_flash_data_' . $key, $value);
        } else {
            Session::setFlash($key, (string)$value);
        }
        return null;
    }
}

if (!function_exists('session_flash')) {
    /**
     * Ambil flash data (array error / old inputs) dan bersihkan otomatis
     */
    function session_flash(string $key, mixed $default = null): mixed
    {
        $complexKey = '_flash_data_' . $key;
        if (Session::has($complexKey)) {
            $val = Session::get($complexKey);
            Session::remove($complexKey);
            return $val;
        }

        $flashMessages = Session::getFlash($key);
        if (!empty($flashMessages)) {
            return $flashMessages;
        }

        return $default;
    }
}

