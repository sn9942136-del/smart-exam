<?php

declare(strict_types=1);

use App\Libraries\Session;

if (!function_exists('is_logged_in')) {
    /**
     * Memeriksa apakah pengguna saat ini telah login
     */
    function is_logged_in(): bool
    {
        return Session::has('user') && !empty(Session::get('user')['id']);
    }
}

if (!function_exists('auth_user')) {
    /**
     * Mengambil seluruh data array user yang sedang login
     */
    function auth_user(): ?array
    {
        return Session::get('user');
    }
}

if (!function_exists('user')) {
    /**
     * Alias helper untuk auth_user()
     */
    function user(): ?array
    {
        return auth_user();
    }
}

if (!function_exists('user_id')) {
    /**
     * Mengambil ID user yang sedang login
     */
    function user_id(): ?int
    {
        $user = auth_user();
        return isset($user['id']) ? (int) $user['id'] : null;
    }
}

if (!function_exists('user_role')) {
    /**
     * Mengambil role utama user yang sedang login (super_admin, admin, guru, peserta_didik)
     */
    function user_role(): ?string
    {
        $user = auth_user();
        return $user['role'] ?? null;
    }
}

if (!function_exists('user_name')) {
    /**
     * Mengambil nama lengkap user yang sedang login
     */
    function user_name(): string
    {
        $user = auth_user();
        return $user['name'] ?? 'Tamu';
    }
}

if (!function_exists('user_email')) {
    /**
     * Mengambil email user yang sedang login
     */
    function user_email(): string
    {
        $user = auth_user();
        return $user['email'] ?? '';
    }
}

if (!function_exists('user_avatar')) {
    /**
     * Mengambil URL avatar user yang sedang login atau dari path avatar
     */
    function user_avatar(?string $avatar = null): ?string
    {
        $av = $avatar ?? (auth_user()['avatar'] ?? null);
        if (empty($av)) {
            return null;
        }
        $clean = trim((string)$av);
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $clean;
        }
        return base_url(ltrim($clean, '/'));
    }
}

if (!function_exists('has_role')) {
    /**
     * Memeriksa apakah user memiliki satu atau beberapa role tertentu
     * @param string|array $roles
     */
    function has_role(string|array $roles): bool
    {
        if (!is_logged_in()) {
            return false;
        }

        $userRole = (string) (user_role() ?? '');
        $userRoleNorm = match ($userRole) {
            'superadmin'    => 'super_admin',
            'administrator' => 'admin',
            'siswa'         => 'peserta_didik',
            default         => $userRole
        };

        $rawUserRoles = auth_user()['roles'] ?? [$userRole];
        $userRolesNorm = array_map(function ($r) {
            return match ((string)$r) {
                'superadmin'    => 'super_admin',
                'administrator' => 'admin',
                'siswa'         => 'peserta_didik',
                default         => (string)$r
            };
        }, (array) $rawUserRoles);

        // Super Admin memiliki akses universal
        if ($userRoleNorm === 'super_admin' || in_array('super_admin', $userRolesNorm, true)) {
            return true;
        }

        $targetRoles = is_array($roles) ? $roles : [$roles];
        foreach ($targetRoles as $r) {
            // Normalisasi target role
            $rNorm = match ((string)$r) {
                'superadmin'    => 'super_admin',
                'administrator' => 'admin',
                'siswa'         => 'peserta_didik',
                default         => (string)$r
            };
            if (in_array($rNorm, $userRolesNorm, true) || $userRoleNorm === $rNorm) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('has_permission')) {
    /**
     * Memeriksa apakah user memiliki permission tertentu
     */
    function has_permission(string $permission): bool
    {
        if (!is_logged_in()) {
            return false;
        }

        if (user_role() === 'super_admin') {
            return true;
        }

        $perms = auth_user()['permissions'] ?? [];
        return in_array($permission, $perms, true);
    }
}

if (!function_exists('role_badge_class')) {
    /**
     * Mendapatkan class CSS badge Bootstrap berdasarkan role
     */
    function role_badge_class(?string $role = null): string
    {
        $role = $role ?? user_role();
        return match ($role) {
            'super_admin', 'superadmin' => 'bg-danger text-white',
            'admin'                     => 'bg-danger-subtle text-danger border border-danger-subtle',
            'guru'                      => 'bg-primary-subtle text-primary border border-primary-subtle',
            'peserta_didik', 'siswa'    => 'bg-success-subtle text-success border border-success-subtle',
            default                     => 'bg-secondary-subtle text-secondary'
        };
    }
}

if (!function_exists('role_label')) {
    /**
     * Label manusiawi untuk setiap role pengguna
     */
    function role_label(?string $role = null): string
    {
        $role = $role ?? user_role();
        return match ($role) {
            'super_admin', 'superadmin' => 'Super Admin',
            'admin'                     => 'Administrator',
            'guru'                      => 'Guru / Pengawas',
            'peserta_didik', 'siswa'    => 'Peserta Didik',
            default                     => 'Tamu'
        };
    }
}

if (!function_exists('login_user')) {
    /**
     * Menyimpan data login user ke dalam session beserta preferensi tema tampilan
     */
    function login_user(array $userData): void
    {
        Session::start();
        $userId = (int)($userData['id'] ?? 0);

        // Ambil tema tampilan dari database pengguna
        $userTheme = $userData['theme'] ?? null;
        if (!$userTheme && $userId > 0) {
            $userTheme = \App\Models\UserPreferenceModel::getThemeForUser($userId);
        }
        $theme = in_array($userTheme, ['dark', 'light'], true) ? $userTheme : 'light';

        Session::set('user', [
            'id'          => $userData['id'] ?? null,
            'name'        => $userData['name'] ?? '',
            'email'       => $userData['email'] ?? '',
            'username'    => $userData['username'] ?? '',
            'role'        => $userData['role'] ?? 'peserta_didik',
            'roles'       => $userData['roles'] ?? [$userData['role'] ?? 'peserta_didik'],
            'permissions' => $userData['permissions'] ?? [],
            'nip_nisn'    => $userData['nip_nisn'] ?? null,
            'avatar'      => $userData['avatar'] ?? null,
            'theme'       => $theme,
        ]);
        Session::set('theme', $theme);

        // Pasang cookie fallback untuk browser
        if (!headers_sent()) {
            setcookie('smart_exam_theme', $theme, time() + (30 * 86400), '/', '', false, false);
        }
    }
}

if (!function_exists('logout_user')) {
    /**
     * Menghapus sesi login user
     */
    function logout_user(): void
    {
        Session::remove('user');
        Session::remove('theme');
        Session::setFlash('info', 'Anda telah berhasil keluar dari sistem.');
    }
}

if (!function_exists('theme_mode')) {
    /**
     * Dapatkan tema tampilan aktif saat ini ('dark' atau 'light')
     * Urutan prioritas: Sesi User Login -> Sesi Global -> Cookie -> Default ('light')
     */
    function theme_mode(): string
    {
        // 1. Akun pengguna yang sedang login (prioritas per-akun)
        $user = Session::get('user');
        if (!empty($user['theme']) && in_array($user['theme'], ['dark', 'light'], true)) {
            return $user['theme'];
        }

        // 2. Sesi tema global
        $sessTheme = Session::get('theme');
        if (!empty($sessTheme) && in_array($sessTheme, ['dark', 'light'], true)) {
            return $sessTheme;
        }

        // 3. Cookie fallback browser lokal
        if (!empty($_COOKIE['smart_exam_theme']) && in_array($_COOKIE['smart_exam_theme'], ['dark', 'light'], true)) {
            return $_COOKIE['smart_exam_theme'];
        }

        // 4. Default: Light Mode
        return 'light';
    }
}

if (!function_exists('is_dark_mode')) {
    /**
     * Periksa apakah saat ini sedang dalam Dark Mode
     */
    function is_dark_mode(): bool
    {
        return theme_mode() === 'dark';
    }
}
