-- Migration 005: Add grading feedback & publish metadata
ALTER TABLE exam_answers 
ADD COLUMN IF NOT EXISTS teacher_feedback TEXT NULL AFTER score_earned,
ADD COLUMN IF NOT EXISTS ai_explanation TEXT NULL AFTER teacher_feedback;

ALTER TABLE exam_results
ADD COLUMN IF NOT EXISTS teacher_feedback TEXT NULL AFTER is_passed,
ADD COLUMN IF NOT EXISTS published_at DATETIME NULL AFTER graded_at,
ADD COLUMN IF NOT EXISTS published_by BIGINT(20) UNSIGNED NULL AFTER published_at;
