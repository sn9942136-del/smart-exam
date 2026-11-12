<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Database;
use PDO;
use Throwable;

/**
 * User Preference Model
 * Mengelola preferensi individual pengguna (Tema Tampilan Light/Dark, dsb)
 * dengan isolasi ketat antar-akun pengguna.
 */
class UserPreferenceModel extends BaseModel
{
    protected string $table = 'user_preferences';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'user_id',
        'preference_key',
        'preference_value',
        'created_at',
        'updated_at'
    ];

    private static bool $tableChecked = false;

    /**
     * Pastikan tabel user_preferences ada di database secara otomatis
     */
    public static function ensureTableExists(): void
    {
        if (self::$tableChecked) {
            return;
        }

        try {
            $db = Database::getConnection();
            $sql = "
                CREATE TABLE IF NOT EXISTS `user_preferences` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `user_id` BIGINT UNSIGNED NOT NULL,
                    `preference_key` VARCHAR(50) NOT NULL,
                    `preference_value` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_user_pref` (`user_id`, `preference_key`),
                    INDEX `idx_user_pref_uid` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            $db->exec($sql);
            self::$tableChecked = true;
        } catch (Throwable $e) {
            // Log secara aman dan lanjutkan
            error_log('[UserPreferenceModel::ensureTableExists] Error: ' . $e->getMessage());
        }
    }

    /**
     * Ambil tema tampilan untuk user tertentu (default: 'light')
     */
    public static function getThemeForUser(int $userId): string
    {
        if ($userId <= 0) {
            return 'light';
        }

        self::ensureTableExists();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT preference_value 
                FROM user_preferences 
                WHERE user_id = :uid AND preference_key = 'theme' 
                LIMIT 1
            ");
            $stmt->execute([':uid' => $userId]);
            $val = $stmt->fetchColumn();

            if ($val !== false && in_array(strtolower((string)$val), ['dark', 'light'], true)) {
                return strtolower((string)$val);
            }
        } catch (Throwable $e) {
            error_log('[UserPreferenceModel::getThemeForUser] Error: ' . $e->getMessage());
        }

        return 'light';
    }

    /**
     * Simpan tema tampilan untuk user tertentu ('dark' atau 'light')
     */
    public static function setThemeForUser(int $userId, string $theme): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $theme = strtolower(trim($theme));
        if (!in_array($theme, ['dark', 'light'], true)) {
            $theme = 'light';
        }

        self::ensureTableExists();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at)
                VALUES (:uid, 'theme', :val, NOW())
                ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()
            ");
            return $stmt->execute([
                ':uid' => $userId,
                ':val' => $theme
            ]);
        } catch (Throwable $e) {
            error_log('[UserPreferenceModel::setThemeForUser] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil nilai preferensi umum
     */
    public static function getVal(int $userId, string $key, mixed $default = null): mixed
    {
        if ($userId <= 0) {
            return $default;
        }

        self::ensureTableExists();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT preference_value 
                FROM user_preferences 
                WHERE user_id = :uid AND preference_key = :k 
                LIMIT 1
            ");
            $stmt->execute([':uid' => $userId, ':k' => $key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (Throwable $e) {
            error_log('[UserPreferenceModel::getVal] Error: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Simpan nilai preferensi umum
     */
    public static function setVal(int $userId, string $key, string $value): bool
    {
        if ($userId <= 0) {
            return false;
        }

        self::ensureTableExists();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at)
                VALUES (:uid, :k, :val, NOW())
                ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = NOW()
            ");
            return $stmt->execute([
                ':uid' => $userId,
                ':k'   => $key,
                ':val' => $value
            ]);
        } catch (Throwable $e) {
            error_log('[UserPreferenceModel::set] Error: ' . $e->getMessage());
            return false;
        }
    }
}
