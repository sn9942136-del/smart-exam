-- ==============================================================================
-- SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
-- Comprehensive Database Schema Migration (7 Modules)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ==============================================================================
-- MODUL 1: AUTHENTICATION & AUTHORIZATION (RBAC)
-- ==============================================================================

-- 1.1 Tabel Roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.2 Tabel Permissions
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(150) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_permissions_module` (`module`),
    INDEX `idx_permissions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.3 Tabel Users
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nip_nisn` VARCHAR(50) NULL,
    `phone` VARCHAR(25) NULL,
    `avatar` VARCHAR(255) NULL,
    `gender` ENUM('L', 'P') NULL,
    `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active',
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at` DATETIME NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_username` (`username`),
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_nip_nisn` (`nip_nisn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.4 Pivot User Roles
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.5 Pivot Role Permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.6 User Sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `session_token` VARCHAR(255) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `device_info` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_activity` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_sessions_token` (`session_token`),
    INDEX `idx_user_sessions_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.7 Password Resets
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_password_resets_email` (`email`),
    INDEX `idx_password_resets_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.8 OTP Tokens
CREATE TABLE IF NOT EXISTS `otp_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token` VARCHAR(10) NOT NULL,
    `type` ENUM('login', '2fa', 'reset_password', 'exam_verify') NOT NULL DEFAULT 'login',
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_otp_user_token` (`user_id`, `token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- MODUL 2: AKADEMIK
-- ==============================================================================

-- 2.1 Academic Years
CREATE TABLE IF NOT EXISTS `academic_years` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE, -- Contoh: '2026/2027'
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.2 Semesters
CREATE TABLE IF NOT EXISTS `semesters` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `name` ENUM('Ganjil', 'Genap') NOT NULL,
    `code` VARCHAR(10) NOT NULL, -- '1', '2'
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_year_semester` (`academic_year_id`, `name`),
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 Classes
CREATE TABLE IF NOT EXISTS `classes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL, -- Contoh: 'XII MIPA 1'
    `grade_level` VARCHAR(10) NOT NULL, -- '10', '11', '12'
    `major` VARCHAR(50) NULL, -- 'MIPA', 'IPS', 'TKJ', dll
    `homeroom_teacher_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_classes_grade` (`grade_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.4 Subjects
CREATE TABLE IF NOT EXISTS `subjects` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subjects_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.5 Class Students
CREATE TABLE IF NOT EXISTS `class_students` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `class_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `roll_number` VARCHAR(20) NULL,
    `status` ENUM('active', 'transferred', 'graduated') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_class_student_year` (`class_id`, `student_id`, `academic_year_id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.6 Teacher Subjects
CREATE TABLE IF NOT EXISTS `teacher_subjects` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `class_id` BIGINT UNSIGNED NULL,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- MODUL 3: BANK SOAL & AI QUESTION GENERATION
-- ==============================================================================

-- 3.1 Question Banks
CREATE TABLE IF NOT EXISTS `question_banks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `creator_id` BIGINT UNSIGNED NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `total_questions` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_qbank_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 Questions
CREATE TABLE IF NOT EXISTS `questions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_bank_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('multiple_choice', 'single_choice', 'essay', 'true_false', 'matching') NOT NULL DEFAULT 'multiple_choice',
    `content` LONGTEXT NOT NULL,
    `question_image` VARCHAR(255) NULL,
    `question_audio` VARCHAR(255) NULL,
    `explanation` TEXT NULL,
    `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `difficulty` ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    `order_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`question_bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE,
    INDEX `idx_questions_type` (`type`),
    INDEX `idx_questions_difficulty` (`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3 Question Options
CREATE TABLE IF NOT EXISTS `question_options` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `option_key` VARCHAR(10) NOT NULL, -- 'A', 'B', 'C', 'D', 'E'
    `content` LONGTEXT NOT NULL,
    `option_image` VARCHAR(255) NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `score_value` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    INDEX `idx_qoptions_correct` (`is_correct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 Question Imports
CREATE TABLE IF NOT EXISTS `question_imports` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_bank_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_type` ENUM('excel', 'word', 'json', 'csv') NOT NULL DEFAULT 'excel',
    `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `total_imported` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_log` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`question_bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.5 AI Question Generations
CREATE TABLE IF NOT EXISTS `ai_question_generations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_bank_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `prompt` TEXT NOT NULL,
    `subject_topic` VARCHAR(200) NOT NULL,
    `grade_level` VARCHAR(50) NULL,
    `question_type` VARCHAR(50) NOT NULL DEFAULT 'multiple_choice',
    `language` VARCHAR(20) NOT NULL DEFAULT 'id',
    `difficulty_level` ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    `question_count` INT UNSIGNED NOT NULL DEFAULT 5,
    `accepted_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `raw_response` LONGTEXT NULL,
    `error_message` TEXT NULL,
    `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`question_bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- MODUL 4: UJIAN & EVALUASI
-- ==============================================================================

-- 4.1 Exams
CREATE TABLE IF NOT EXISTS `exams` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `type` ENUM('quiz', 'midterm', 'final_exam', 'tryout', 'daily_test') NOT NULL DEFAULT 'daily_test',
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
    `passing_grade` DECIMAL(5,2) NOT NULL DEFAULT 75.00,
    `randomize_questions` TINYINT(1) NOT NULL DEFAULT 1,
    `randomize_options` TINYINT(1) NOT NULL DEFAULT 1,
    `show_result_immediately` TINYINT(1) NOT NULL DEFAULT 0,
    `is_dual_cam_required` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('draft', 'scheduled', 'published', 'ongoing', 'in_progress', 'completed', 'archived') NOT NULL DEFAULT 'draft',
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_exams_code` (`code`),
    INDEX `idx_exams_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 Exam Rules
CREATE TABLE IF NOT EXISTS `exam_rules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `allow_tab_switch` TINYINT(1) NOT NULL DEFAULT 0,
    `max_tab_switches` INT UNSIGNED NOT NULL DEFAULT 3,
    `lock_browser` TINYINT(1) NOT NULL DEFAULT 1,
    `detect_multiple_faces` TINYINT(1) NOT NULL DEFAULT 1,
    `detect_look_away` TINYINT(1) NOT NULL DEFAULT 1,
    `detect_audio_noise` TINYINT(1) NOT NULL DEFAULT 1,
    `detect_secondary_device` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.3 Exam Schedules
CREATE TABLE IF NOT EXISTS `exam_schedules` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `class_id` BIGINT UNSIGNED NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `grace_period_minutes` INT UNSIGNED NOT NULL DEFAULT 15,
    `status` ENUM('scheduled', 'active', 'expired') NOT NULL DEFAULT 'scheduled',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    INDEX `idx_schedules_time` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.4 Exam Tokens
CREATE TABLE IF NOT EXISTS `exam_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `token` VARCHAR(10) NOT NULL,
    `valid_from` DATETIME NOT NULL,
    `valid_until` DATETIME NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_refresh_minutes` INT UNSIGNED NOT NULL DEFAULT 15,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    INDEX `idx_exam_tokens_active` (`token`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.5 Exam Questions
CREATE TABLE IF NOT EXISTS `exam_questions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `order_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `score_weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_exam_question` (`exam_id`, `question_id`),
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.6 Exam Participants
CREATE TABLE IF NOT EXISTS `exam_participants` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `class_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('not_started', 'in_progress', 'completed', 'blocked', 'submitted') NOT NULL DEFAULT 'not_started',
    `score` DECIMAL(5,2) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_exam_student` (`exam_id`, `student_id`),
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    INDEX `idx_participants_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.7 Exam Sessions
CREATE TABLE IF NOT EXISTS `exam_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `session_token` VARCHAR(255) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `start_time` DATETIME NOT NULL,
    `submit_time` DATETIME NULL,
    `remaining_seconds` INT NOT NULL DEFAULT 0,
    `status` ENUM('ongoing', 'paused', 'submitted', 'terminated') NOT NULL DEFAULT 'ongoing',
    `integrity_score` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `cam1_status` ENUM('connected', 'disconnected', 'error') NOT NULL DEFAULT 'disconnected',
    `cam2_status` ENUM('connected', 'disconnected', 'error') NOT NULL DEFAULT 'disconnected',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_sessions_status` (`status`),
    INDEX `idx_sessions_integrity` (`integrity_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.8 Exam Answers
CREATE TABLE IF NOT EXISTS `exam_answers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_session_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `selected_option_id` BIGINT UNSIGNED NULL,
    `essay_answer` LONGTEXT NULL,
    `is_correct` TINYINT(1) NULL,
    `score_earned` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `teacher_feedback` TEXT NULL,
    `ai_explanation` TEXT NULL,
    `is_flagged` TINYINT(1) NOT NULL DEFAULT 0, -- Ragu-ragu
    `answered_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_session_question_answer` (`exam_session_id`, `question_id`),
    FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`selected_option_id`) REFERENCES `question_options`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.9 Exam Events
CREATE TABLE IF NOT EXISTS `exam_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_session_id` BIGINT UNSIGNED NOT NULL,
    `event_name` VARCHAR(100) NOT NULL, -- 'START', 'TAB_BLUR', 'TAB_FOCUS', 'FULLSCREEN_EXIT', 'DISCONNECT', 'SUBMIT'
    `payload` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions`(`id`) ON DELETE CASCADE,
    INDEX `idx_exam_events_name` (`event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- MODUL 5: AI PROCTORING (DUAL KAMERA & DETEKSI FRAUD)
-- ==============================================================================

-- 5.1 Proctoring Sessions
CREATE TABLE IF NOT EXISTS `proctoring_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_session_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `cam1_stream_id` VARCHAR(150) NULL,
    `cam2_stream_id` VARCHAR(150) NULL,
    `status` ENUM('initializing', 'calibrating', 'active', 'interrupted', 'finished') NOT NULL DEFAULT 'initializing',
    `initial_face_embedding` LONGTEXT NULL,
    `started_at` DATETIME NOT NULL,
    `ended_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.2 Camera Devices
CREATE TABLE IF NOT EXISTS `camera_devices` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proctoring_session_id` BIGINT UNSIGNED NOT NULL,
    `camera_type` ENUM('primary_webcam', 'secondary_phone', 'third_party_ip') NOT NULL DEFAULT 'primary_webcam',
    `device_label` VARCHAR(200) NULL,
    `resolution` VARCHAR(50) NULL, -- '1280x720', '1920x1080'
    `fps` INT UNSIGNED NOT NULL DEFAULT 30,
    `connection_status` ENUM('connected', 'disconnected', 'degraded') NOT NULL DEFAULT 'connected',
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`proctoring_session_id`) REFERENCES `proctoring_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.3 Camera Quality Checks
CREATE TABLE IF NOT EXISTS `camera_quality_checks` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `camera_device_id` BIGINT UNSIGNED NOT NULL,
    `lighting_score` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    `blur_score` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    `face_visibility_score` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    `is_passed` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT NULL,
    `checked_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`camera_device_id`) REFERENCES `camera_devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.4 Face Verifications
CREATE TABLE IF NOT EXISTS `face_verifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proctoring_session_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `reference_photo_url` VARCHAR(255) NULL,
    `captured_photo_url` VARCHAR(255) NOT NULL,
    `similarity_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `verification_status` ENUM('matched', 'mismatched', 'uncertain') NOT NULL DEFAULT 'matched',
    `verified_by` ENUM('ai', 'manual_proctor') NOT NULL DEFAULT 'ai',
    `verified_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`proctoring_session_id`) REFERENCES `proctoring_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.5 AI Analysis Logs
CREATE TABLE IF NOT EXISTS `ai_analysis_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proctoring_session_id` BIGINT UNSIGNED NOT NULL,
    `camera_device_id` BIGINT UNSIGNED NULL,
    `frame_timestamp` BIGINT UNSIGNED NOT NULL, -- Unix ms timestamp
    `head_pose_yaw` DECIMAL(6,2) NULL,
    `head_pose_pitch` DECIMAL(6,2) NULL,
    `head_pose_roll` DECIMAL(6,2) NULL,
    `gaze_direction` ENUM('center', 'left', 'right', 'up', 'down', 'away') NOT NULL DEFAULT 'center',
    `detected_face_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `mouth_movement_detected` TINYINT(1) NOT NULL DEFAULT 0,
    `phone_detected` TINYINT(1) NOT NULL DEFAULT 0,
    `audio_noise_level` DECIMAL(5,2) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`proctoring_session_id`) REFERENCES `proctoring_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`camera_device_id`) REFERENCES `camera_devices`(`id`) ON DELETE SET NULL,
    INDEX `idx_ai_logs_gaze` (`gaze_direction`),
    INDEX `idx_ai_logs_faces` (`detected_face_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.6 AI Violation Types
CREATE TABLE IF NOT EXISTS `ai_violation_types` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE, -- 'NO_FACE', 'MULTIPLE_FACES', 'LOOK_AWAY', 'PHONE_DETECTED', etc.
    `name` VARCHAR(150) NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `penalty_score` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_violation_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.7 AI Violations
CREATE TABLE IF NOT EXISTS `ai_violations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proctoring_session_id` BIGINT UNSIGNED NOT NULL,
    `violation_type_id` BIGINT UNSIGNED NOT NULL,
    `camera_device_id` BIGINT UNSIGNED NULL,
    `confidence_score` DECIMAL(5,2) NOT NULL DEFAULT 95.00,
    `evidence_snapshot_url` VARCHAR(255) NULL,
    `evidence_video_clip_url` VARCHAR(255) NULL,
    `metadata` JSON NULL,
    `is_flagged_for_review` TINYINT(1) NOT NULL DEFAULT 1,
    `proctor_action` ENUM('pending', 'approved', 'dismissed', 'penalized') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`proctoring_session_id`) REFERENCES `proctoring_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`violation_type_id`) REFERENCES `ai_violation_types`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`camera_device_id`) REFERENCES `camera_devices`(`id`) ON DELETE SET NULL,
    INDEX `idx_violations_action` (`proctor_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.8 AI Warnings
CREATE TABLE IF NOT EXISTS `ai_warnings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proctoring_session_id` BIGINT UNSIGNED NOT NULL,
    `violation_id` BIGINT UNSIGNED NULL,
    `warning_type` ENUM('toast', 'modal_alert', 'sound_beep', 'exam_freeze') NOT NULL DEFAULT 'toast',
    `warning_message` TEXT NOT NULL,
    `is_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
    `acknowledged_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`proctoring_session_id`) REFERENCES `proctoring_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`violation_id`) REFERENCES `ai_violations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.9 Proctoring Recordings
CREATE TABLE IF NOT EXISTS `proctoring_recordings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proctoring_session_id` BIGINT UNSIGNED NOT NULL,
    `camera_type` ENUM('primary', 'secondary') NOT NULL DEFAULT 'primary',
    `file_path` VARCHAR(255) NOT NULL,
    `file_size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `video_codec` VARCHAR(50) NOT NULL DEFAULT 'webm/vp8',
    `storage_disk` ENUM('local', 's3', 'minio') NOT NULL DEFAULT 'local',
    `status` ENUM('uploading', 'saved', 'archived', 'deleted') NOT NULL DEFAULT 'saved',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`proctoring_session_id`) REFERENCES `proctoring_sessions`(`id`) ON DELETE CASCADE,
    INDEX `idx_recordings_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- MODUL 6: PENILAIAN & ANALISIS AI
-- ==============================================================================

-- 6.1 Exam Results
CREATE TABLE IF NOT EXISTS `exam_results` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_session_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `exam_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `total_questions` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_answered` INT UNSIGNED NOT NULL DEFAULT 0,
    `correct_answers` INT UNSIGNED NOT NULL DEFAULT 0,
    `wrong_answers` INT UNSIGNED NOT NULL DEFAULT 0,
    `multiple_choice_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `essay_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `proctoring_integrity_score` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    `is_passed` TINYINT(1) NOT NULL DEFAULT 0,
    `teacher_feedback` TEXT NULL,
    `status` ENUM('pending_grading', 'graded', 'published') NOT NULL DEFAULT 'pending_grading',
    `graded_at` DATETIME NULL,
    `published_at` DATETIME NULL,
    `published_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`published_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_results_final_score` (`final_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.2 Essay Gradings
CREATE TABLE IF NOT EXISTS `essay_gradings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_answer_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `teacher_id` BIGINT UNSIGNED NULL,
    `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `feedback` TEXT NULL,
    `grading_method` ENUM('manual', 'ai_assisted', 'fully_ai') NOT NULL DEFAULT 'manual',
    `ai_suggested_score` DECIMAL(5,2) NULL,
    `ai_feedback` TEXT NULL,
    `graded_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_answer_id`) REFERENCES `exam_answers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.3 AI Answer Explanations
CREATE TABLE IF NOT EXISTS `ai_answer_explanations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `exam_answer_id` BIGINT UNSIGNED NULL,
    `ai_model_name` VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    `explanation_text` LONGTEXT NOT NULL,
    `strengths_analysis` TEXT NULL,
    `weaknesses_analysis` TEXT NULL,
    `learning_recommendation` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exam_answer_id`) REFERENCES `exam_answers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- MODUL 7: SISTEM, KEAMANAN, & MONITORING
-- ==============================================================================

-- 7.1 Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'warning', 'danger', 'success') NOT NULL DEFAULT 'info',
    `action_url` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.2 Chat Messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `receiver_id` BIGINT UNSIGNED NULL,
    `exam_session_id` BIGINT UNSIGNED NULL,
    `channel` ENUM('broadcast', 'proctor_to_student', 'tech_support') NOT NULL DEFAULT 'proctor_to_student',
    `message` TEXT NOT NULL,
    `attachment_url` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.3 Integrations
CREATE TABLE IF NOT EXISTS `integrations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE, -- 'google_classroom', 'moodle', 'webhook', 'whatsapp_gateway'
    `api_key` VARCHAR(255) NULL,
    `secret_key` VARCHAR(255) NULL,
    `endpoint_url` VARCHAR(255) NULL,
    `config` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.4 Notification Logs
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `notification_id` BIGINT UNSIGNED NULL,
    `channel` ENUM('email', 'sms', 'whatsapp', 'push') NOT NULL DEFAULT 'email',
    `recipient` VARCHAR(150) NOT NULL,
    `status` ENUM('sent', 'failed', 'queued') NOT NULL DEFAULT 'sent',
    `response_payload` TEXT NULL,
    `sent_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.5 AI Configurations
CREATE TABLE IF NOT EXISTS `ai_configurations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key_name` VARCHAR(100) NOT NULL UNIQUE,
    `key_value` LONGTEXT NOT NULL,
    `category` ENUM('proctoring_ai', 'question_generation_ai', 'essay_grading_ai', 'system') NOT NULL DEFAULT 'proctoring_ai',
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ai_configs_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.6 Storage Policies
CREATE TABLE IF NOT EXISTS `storage_policies` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `media_type` VARCHAR(50) NOT NULL UNIQUE, -- 'recordings', 'snapshots', 'backups', 'uploads'
    `retention_days` INT UNSIGNED NOT NULL DEFAULT 30,
    `auto_delete_after_expiration` TINYINT(1) NOT NULL DEFAULT 1,
    `compression_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.7 Storage Usage Logs
CREATE TABLE IF NOT EXISTS `storage_usage_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `storage_disk` VARCHAR(50) NOT NULL, -- 'local', 's3', 'recordings_drive'
    `used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `free_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_files_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `recorded_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.8 Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `action` VARCHAR(100) NOT NULL, -- 'LOGIN', 'LOGOUT', 'CREATE_EXAM', 'UPDATE_SCORE', 'DELETE_USER'
    `module` VARCHAR(50) NOT NULL, -- 'auth', 'exam', 'grading', 'system', 'proctoring'
    `target_id` VARCHAR(100) NULL,
    `before_data` JSON NULL,
    `after_data` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_audit_logs_action` (`action`),
    INDEX `idx_audit_logs_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.9 Emergency Controls
CREATE TABLE IF NOT EXISTS `emergency_controls` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exam_id` BIGINT UNSIGNED NULL,
    `triggered_by` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(100) NOT NULL, -- 'SERVER_OVERLOAD', 'MASS_DISCONNECT', 'EXAM_FREEZE'
    `action_taken` VARCHAR(100) NOT NULL, -- 'PAUSE_ALL_TIMERS', 'EXTEND_TIME_15MIN', 'RESUME_ALL'
    `reason` TEXT NOT NULL,
    `affected_students_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`triggered_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.10 Server Monitoring Logs
CREATE TABLE IF NOT EXISTS `server_monitoring_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cpu_usage_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `ram_usage_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `disk_usage_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `active_websocket_connections` INT UNSIGNED NOT NULL DEFAULT 0,
    `active_proctoring_streams` INT UNSIGNED NOT NULL DEFAULT 0,
    `recorded_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.11 Backup Logs
CREATE TABLE IF NOT EXISTS `backup_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `backup_type` ENUM('database', 'storage', 'full') NOT NULL DEFAULT 'database',
    `file_name` VARCHAR(255) NOT NULL,
    `file_size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('success', 'failed', 'running') NOT NULL DEFAULT 'success',
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.12 Maintenance Logs
CREATE TABLE IF NOT EXISTS `maintenance_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `initiated_by` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `is_maintenance_active` TINYINT(1) NOT NULL DEFAULT 0,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`initiated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
