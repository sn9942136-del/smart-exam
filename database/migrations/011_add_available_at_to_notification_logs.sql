-- ==============================================================================
-- SMART EXAM — MIGRATION 011: ADD AVAILABLE_AT TO NOTIFICATION LOGS
-- ==============================================================================
-- Target: ujian_dual_kamera_test (DATABASE TESTING SAJA)
-- DILARANG DIJALANKAN PADA: ujian_dual_kamera (Operasional)
-- Fitur: Exponential Backoff & Retry Delay Scheduler
-- ==============================================================================

ALTER TABLE `notification_logs`
    ADD COLUMN IF NOT EXISTS `available_at` DATETIME NULL DEFAULT NULL AFTER `claimed_by`,
    ADD INDEX IF NOT EXISTS `idx_queue_available` (`status`, `available_at`, `claimed_at`);
