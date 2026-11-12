-- ==============================================================================
-- SMART EXAM — MIGRATION 013: CREATE USER PREFERENCES TABLE
-- ==============================================================================
-- Modul: Preferensi Pengguna Individual (User Preferences & Display Themes)
-- Mendukung: Light Mode, Dark Mode, dan preferensi akun di masa depan
-- ==============================================================================

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

