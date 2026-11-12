-- ==============================================================================
-- SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI
-- Migration 008: Enhance AI Violation Types for Complete Proctoring Coverage
-- ==============================================================================

INSERT IGNORE INTO `ai_violation_types` (`code`, `name`, `severity`, `penalty_score`, `description`, `created_at`, `updated_at`) VALUES
('FULLSCREEN_EXIT', 'Keluar dari Mode Layar Penuh (Fullscreen)', 'medium', 5.00, 'Peserta keluar dari mode tampilan fullscreen selama ujian aktif berlangsung.', NOW(), NOW()),
('CAMERA_DISCONNECTED', 'Koneksi Kamera Utama/Ruangan Terputus', 'high', 15.00, 'Perangkat kamera video terputus atau tidak merespons selama ujian aktif.', NOW(), NOW());
