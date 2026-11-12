-- ==============================================================================
-- SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
-- LEGACY / ARCHIVED Initial Database Schema Migration
-- (Arsip skema awal sebelum modul RBAC lengkap 7 modul diimplementasikan)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabel Pengguna (Users - Legacy Schema)
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'admin', 'guru', 'siswa') NOT NULL DEFAULT 'siswa',
    `nip_nisn` VARCHAR(50) NULL,
    `phone` VARCHAR(20) NULL,
    `avatar` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Master Ujian (Exams - Legacy Schema)
CREATE TABLE IF NOT EXISTS `exams` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
    `start_time` DATETIME NULL,
    `end_time` DATETIME NULL,
    `is_dual_cam_required` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('draft', 'published', 'ongoing', 'completed') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Sesi Ujian Peserta (Exam Sessions - Legacy Schema)
CREATE TABLE IF NOT EXISTS `exam_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `started_at` DATETIME NULL,
    `finished_at` DATETIME NULL,
    `score` DECIMAL(5,2) NULL,
    `cam1_status` ENUM('connected', 'disconnected', 'error') DEFAULT 'disconnected',
    `cam2_status` ENUM('connected', 'disconnected', 'error') DEFAULT 'disconnected',
    `integrity_score` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `status` ENUM('ready', 'in_progress', 'submitted', 'blocked') NOT NULL DEFAULT 'ready',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Log & Deteksi AI Proctoring (Exam Logs - Legacy Schema)
CREATE TABLE IF NOT EXISTS `exam_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_session_id` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
    `confidence_score` DECIMAL(4,3) NULL,
    `snapshot_cam1` VARCHAR(255) NULL,
    `snapshot_cam2` VARCHAR(255) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions`(`id`) ON DELETE CASCADE,
    INDEX `idx_logs_event` (`event_type`),
    INDEX `idx_logs_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
