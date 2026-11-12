-- ==============================================================================
-- SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
-- Migration 007: AI Chatbot Sessions & Message History
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `ai_chat_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `session_uuid` VARCHAR(64) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL DEFAULT 'Percakapan Baru',
    `role_context` ENUM('guru', 'siswa') NOT NULL DEFAULT 'siswa',
    `is_exam_mode` TINYINT(1) NOT NULL DEFAULT 0,
    `exam_session_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ai_chat_user` (`user_id`, `role_context`),
    INDEX `idx_ai_chat_uuid` (`session_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `sender` ENUM('user', 'assistant', 'system') NOT NULL,
    `message` LONGTEXT NOT NULL,
    `tokens_used` INT UNSIGNED NOT NULL DEFAULT 0,
    `model_used` VARCHAR(100) NULL,
    `is_error` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `ai_chat_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ai_msg_session` (`session_id`),
    INDEX `idx_ai_msg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
