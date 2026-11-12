-- Migration 006: Enhance ai_question_generations table
ALTER TABLE ai_question_generations
ADD COLUMN IF NOT EXISTS grade_level VARCHAR(50) NULL AFTER subject_topic,
ADD COLUMN IF NOT EXISTS question_type VARCHAR(50) NOT NULL DEFAULT 'multiple_choice' AFTER grade_level,
ADD COLUMN IF NOT EXISTS language VARCHAR(20) NOT NULL DEFAULT 'id' AFTER question_type,
ADD COLUMN IF NOT EXISTS accepted_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER question_count,
ADD COLUMN IF NOT EXISTS error_message TEXT NULL AFTER raw_response;
