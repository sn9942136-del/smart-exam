-- ==============================================================================
-- SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
-- Migration 009: Add exam_answer_id to ai_chat_sessions for Question Explainer
-- ==============================================================================

ALTER TABLE `ai_chat_sessions` 
    ADD COLUMN IF NOT EXISTS `exam_answer_id` BIGINT UNSIGNED NULL AFTER `exam_session_id`,
    MODIFY COLUMN `role_context` VARCHAR(50) NOT NULL DEFAULT 'siswa',
    ADD INDEX IF NOT EXISTS `idx_ai_chat_answer` (`exam_answer_id`);
