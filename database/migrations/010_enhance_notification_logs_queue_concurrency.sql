-- ==============================================================================
-- SMART EXAM — MIGRATION 010: ENHANCE NOTIFICATION LOGS QUEUE CONCURRENCY
-- ==============================================================================
-- Target: ujian_dual_kamera_test (Testing Database)
-- Fitur: Pre-Dispatch Atomic Claim, Lock Recovery, Lease Timeout, & Retry Counter
-- ==============================================================================

ALTER TABLE `notification_logs`
    MODIFY COLUMN `status` ENUM('queued', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    MODIFY COLUMN `sent_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `claimed_at` DATETIME NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `claimed_by` VARCHAR(64) NULL DEFAULT NULL AFTER `claimed_at`,
    ADD COLUMN IF NOT EXISTS `attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `claimed_by`,
    ADD INDEX IF NOT EXISTS `idx_queue_status_claimed` (`status`, `claimed_at`);
