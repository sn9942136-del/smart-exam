-- ==============================================================================
-- SMART EXAM — MIGRATION 012: CREATE SYSTEM SETTINGS TABLE
-- ==============================================================================
-- Modul: Konfigurasi Sistem Terpusat (System Settings & Preferences)
-- Kategori: Umum, Keamanan, CBT Ujian, Proctoring AI, AI Engine, Notifikasi, Penyimpanan, Pemeliharaan
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_name` VARCHAR(50) NOT NULL DEFAULT 'general',
    `key_name` VARCHAR(100) NOT NULL UNIQUE,
    `key_value` LONGTEXT NULL,
    `field_type` ENUM('text', 'textarea', 'number', 'boolean', 'select', 'password', 'range') NOT NULL DEFAULT 'text',
    `options` JSON NULL,
    `label` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_sensitive` TINYINT(1) NOT NULL DEFAULT 0,
    `is_editable` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settings_group` (`group_name`),
    INDEX `idx_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- SEED DEFAULT SYSTEM CONFIGURATIONS
-- ------------------------------------------------------------------------------

INSERT INTO `system_settings` (`group_name`, `key_name`, `key_value`, `field_type`, `options`, `label`, `description`, `is_sensitive`, `is_editable`)
VALUES
-- 1. IDENTITAS & UMUM (General)
('general', 'app_name', 'Smart Exam', 'text', NULL, 'Nama Aplikasi', 'Nama sistem ujian yang ditampilkan di header, navbar, dan dokumen.', 0, 1),
('general', 'app_tagline', 'Sistem Ujian Online CBT Cerdas Berbasis AI Dual Kamera', 'text', NULL, 'Slogan Aplikasi', 'Slogan pengenal yang tampil di halaman utama dan landing page.', 0, 1),
('general', 'institution_name', 'SMA Negeri 1 Smart Exam', 'text', NULL, 'Nama Sekolah / Institusi', 'Nama resmi sekolah atau lembaga penyelenggara ujian.', 0, 1),
('general', 'institution_address', 'Jl. Pendidikan Unggul No. 1, Jakarta', 'textarea', NULL, 'Alamat Institusi', 'Alamat lengkap institusi yang tertera pada kop laporan hasil ujian.', 0, 1),
('general', 'institution_phone', '021-5551234', 'text', NULL, 'Nomor Kontak Sekolah', 'Nomor telepon resmi sekolah atau panitia ujian.', 0, 1),
('general', 'institution_email', 'info@smartexam.sch.id', 'text', NULL, 'Email Resmi Institusi', 'Alamat kontak email sekolah untuk komunikasi peserta didik.', 0, 1),
('general', 'app_url', 'http://localhost/PRD/public', 'text', NULL, 'Base URL Aplikasi', 'Alamat URL dasar instalasi web aplikasi Smart Exam.', 0, 1),
('general', 'app_timezone', 'Asia/Jakarta', 'select', '["Asia/Jakarta", "Asia/Makassar", "Asia/Jayapura"]', 'Zona Waktu Sistem', 'Zona waktu standar yang digunakan untuk sinkronisasi jam server CBT.', 0, 1),
('general', 'default_locale', 'id', 'select', '["id", "en"]', 'Bahasa Default', 'Bahasa pengantar antarmuka sistem (Indonesia / English).', 0, 1),

-- 2. KEAMANAN & AUTENTIKASI (Security)
('security', 'session_lifetime', '7200', 'number', NULL, 'Masa Berlaku Sesi (Detik)', 'Durasi aktif sesi login pengguna sebelum harus login kembali (7200 = 2 jam).', 0, 1),
('security', 'csrf_protection', '1', 'boolean', NULL, 'Proteksi CSRF', 'Aktifkan validasi token Cross-Site Request Forgery pada seluruh form HTTP POST.', 0, 1),
('security', 'otp_expiry_minutes', '15', 'number', NULL, 'Masa Berlaku OTP (Menit)', 'Batas waktu berlaku kode One-Time Password reset kata sandi.', 0, 1),
('security', 'otp_max_attempts', '3', 'number', NULL, 'Batas Maksimum Percobaan OTP', 'Jumlah maksimal toleransi salah input OTP sebelum kode hangus.', 0, 1),
('security', 'rate_limit_login_max', '5', 'number', NULL, 'Batas Percobaan Login Salah', 'Jumlah maksimal percobaan kata sandi salah sebelum akun dikunci sementara.', 0, 1),
('security', 'rate_limit_login_decay', '60', 'number', NULL, 'Jeda Kunci Login Sementara (Detik)', 'Durasi penguncian akun saat melewati batas percobaan login gagal.', 0, 1),
('security', 'password_min_length', '8', 'number', NULL, 'Minimal Panjang Kata Sandi', 'Jumlah minimal karakter yang diwajibkan saat membuat kata sandi baru.', 0, 1),
('security', 'enforce_single_session', '1', 'boolean', NULL, 'Paksa Sesi Tunggal (Anti Dual-Login)', 'Mencegah akun peserta dibuka bersamaan di dua perangkat berbeda.', 0, 1),

-- 3. MESIN CBT & STANDAR UJIAN (Exam)
('exam', 'default_randomize_questions', '1', 'boolean', NULL, 'Acak Urutan Soal Default', 'Otomatis aktifkan pengacakan nomor butir soal pada setiap sesi ujian baru.', 0, 1),
('exam', 'default_randomize_options', '1', 'boolean', NULL, 'Acak Urutan Opsi Jawaban Default', 'Otomatis acak pilihan jawaban A/B/C/D/E pada butir soal pilihan ganda.', 0, 1),
('exam', 'default_show_result_instant', '0', 'boolean', NULL, 'Tampilkan Nilai Instan ke Siswa', 'Langsung perlihatkan skor total ke peserta tepat setelah submit ujian.', 0, 1),
('exam', 'default_show_explanation', '1', 'boolean', NULL, 'Izinkan Pembahasan Soal', 'Izinkan peserta membaca pembahasan dan kunci jawaban setelah ujian dirilis guru.', 0, 1),
('exam', 'autosave_interval_seconds', '15', 'number', NULL, 'Interval Autosave Jawaban (Detik)', 'Jeda waktu otomatis penyimpanan jawaban peserta ke server database.', 0, 1),
('exam', 'late_tolerance_minutes', '15', 'number', NULL, 'Toleransi Keterlambatan (Menit)', 'Batas maksimal keterlambatan peserta didik memasuki ruang ujian setelah jadwal mulai.', 0, 1),
('exam', 'prevent_tab_switch', '1', 'boolean', NULL, 'Deteksi Pindah Tab / Blur Window', 'Peringatkan peserta dan catat log pelanggaran saat membuka aplikasi atau tab lain.', 0, 1),
('exam', 'max_tab_switch_allowed', '3', 'number', NULL, 'Batas Toleransi Pindah Tab', 'Jumlah maksimal toleransi membuka tab lain sebelum ujian dikunci otomatis.', 0, 1),
('exam', 'auto_submit_on_time_out', '1', 'boolean', NULL, 'Auto-Submit Saat Waktu Habis', 'Otomatis kumpulkan lembar jawaban saat waktu hitung mundur ujian habis.', 0, 1),

-- 4. DUAL CAMERA & AI PROCTORING (Proctoring)
('proctoring', 'strict_dual_camera', '1', 'boolean', NULL, 'Wajibkan Dual Kamera', 'Wajibkan kalibrasi kamera depan laptop dan kamera samping smartphone peserta.', 0, 1),
('proctoring', 'face_detection_model', 'Canvas Vision Engine', 'select', '["Canvas Vision Engine", "MediaPipe Face Mesh v2", "Canvas Heuristic Fast Detector"]', 'Model Deteksi Wajah AI', 'Algoritma machine learning yang digunakan untuk estimasi posisi kepala dan wajah.', 0, 1),
('proctoring', 'dual_cam_sync_interval_ms', '1000', 'select', '["500", "1000", "2000", "3000"]', 'Interval Sinkronisasi Kamera (ms)', 'Frekuensi sinkronisasi frame kamera utama dan kamera samping dalam milidetik.', 0, 1),
('proctoring', 'gaze_sensitivity_threshold', '0.75', 'range', '{"min": 0.50, "max": 0.95, "step": 0.05}', 'Sensitivitas Gaze Tracking', 'Batas sensitivitas pelacakan arah pandangan mata peserta (0.50 longgar - 0.95 ketat).', 0, 1),
('proctoring', 'no_face_timeout_seconds', '5', 'number', NULL, 'Batas Wajah Hilang (Detik)', 'Toleransi durasi wajah tidak terlihat di kamera sebelum tercatat pelanggaran.', 0, 1),
('proctoring', 'multi_face_detection', '1', 'boolean', NULL, 'Deteksi Banyak Wajah (Orang Lain)', 'Picu peringatan otomatis jika terdeteksi lebih dari satu wajah di area ujian.', 0, 1),
('proctoring', 'max_proctoring_warnings', '5', 'number', NULL, 'Batas Maksimal Peringatan AI', 'Batas akumulasi peringatan sebelum pengawas mengambil tindakan intervensi.', 0, 1),
('proctoring', 'webrtc_stun_server', 'stun:stun.l.google.com:19302', 'text', NULL, 'WebRTC STUN Server', 'Alamat server STUN publik untuk koneksi WebRTC streaming kamera sekunder.', 0, 1),

-- 5. KECERDASAN BUATAN / LLM (AI)
('ai', 'ai_provider', 'gemini', 'select', '["gemini", "openai"]', 'Provider AI Utama', 'Penyedia layanan AI generatif yang digunakan untuk pembuatan soal dan asisten.', 0, 1),
('ai', 'gemini_model', 'gemini-3.5-flash-lite', 'select', '["gemini-3.5-flash-lite", "gemini-3.8-flash", "gemini-2.5-flash"]', 'Model AI Gemini Utama', 'Model bahasa LLM yang digunakan untuk memproses permintaan AI.', 0, 1),
('ai', 'gemini_fallback_model', 'gemini-flash-lite-latest,gemini-2.5-flash', 'text', NULL, 'Model Cadangan AI Gemini', 'Daftar model fallback jika model utama mengalami kuota limit atau kendala jaringan.', 0, 1),
('ai', 'gemini_api_key', '', 'password', NULL, 'API Key Gemini AI', 'Kunci rahasia API Google Gemini (kosongkan jika menggunakan konfigurasi .env).', 1, 1),
('ai', 'ai_question_generation_model', 'gpt-4o-mini', 'select', '["gpt-4o-mini", "gemini-3.8-flash", "claude-3-haiku"]', 'Model AI Generator Soal', 'Model default yang digunakan guru pada modul AI Question Generator.', 0, 1),
('ai', 'ai_essay_grading_tolerance', '0.85', 'range', '{"min": 0.60, "max": 0.95, "step": 0.05}', 'Toleransi Penilaian Esai AI', 'Derajat kesamaan semantik jawaban siswa terhadap kunci jawaban guru (0.60 - 0.95).', 0, 1),
('ai', 'ai_chat_assistant_enabled', '1', 'boolean', NULL, 'Aktifkan Chatbot Asisten AI', 'Aktifkan tombol floating chatbot AI asisten proctoring untuk guru dan admin.', 0, 1),
('ai', 'ai_question_generation_enabled', '1', 'boolean', NULL, 'Aktifkan AI Generator Soal', 'Aktifkan fasilitas pembuatan paket bank soal otomatis berbantuan AI.', 0, 1),

-- 6. NOTIFIKASI EMAIL & WHATSAPP (Notification)
('notification', 'notification_mode', 'sandbox', 'select', '["simulation", "sandbox", "production"]', 'Mode Pengiriman Notifikasi', 'Simulation = in-memory aman; Sandbox = pengujian terkontrol; Production = rilis nyata.', 0, 1),
('notification', 'smtp_host', 'smtp.gmail.com', 'text', NULL, 'Host Server SMTP', 'Nama host SMTP server penyedia email (contoh: smtp.gmail.com).', 0, 1),
('notification', 'smtp_port', '587', 'number', NULL, 'Port Server SMTP', 'Port layanan SMTP (587 untuk TLS/STARTTLS, 465 untuk SSL).', 0, 1),
('notification', 'smtp_encryption', 'tls', 'select', '["tls", "ssl", "none"]', 'Protokol Enkripsi SMTP', 'Standar keamanan transmisi email antar server.', 0, 1),
('notification', 'smtp_username', 'smart.examm@gmail.com', 'text', NULL, 'Username / Akun SMTP', 'Alamat email akun pengirim SMTP (akun Google pengirim).', 0, 1),
('notification', 'smtp_password', '', 'password', NULL, 'App Password SMTP Gmail', '16-digit Sandi Aplikasi (App Password) akun Google pengirim.', 1, 1),
('notification', 'smtp_from_email', 'smart.examm@gmail.com', 'text', NULL, 'Alamat Email Pengirim', 'Alamat email resmi yang tertera pada kolom FROM email penerima.', 0, 1),
('notification', 'smtp_from_name', 'Smart Exam', 'text', NULL, 'Nama Pengirim Notifikasi', 'Nama pengirim resmi yang tampil pada kotak masuk email penerima.', 0, 1),
('notification', 'notification_test_recipient', 'sn9942136@gmail.com,tiasitihawa4@gmail.com', 'text', NULL, 'Whitelist Email Pengujian', 'Daftar email pengujian yang diizinkan menerima pesan pada mode sandbox/testing.', 0, 1),
('notification', 'whatsapp_api_url', 'https://api.fonnte.com/send', 'text', NULL, 'URL API WhatsApp Gateway', 'Alamat endpoint pengiriman pesan WhatsApp (Fonnte API resmi).', 0, 1),
('notification', 'whatsapp_api_token', '', 'password', NULL, 'Token API WhatsApp Gateway', 'Token autentikasi Fonnte API pengirim pesan WhatsApp.', 1, 1),
('notification', 'notification_test_phone', '62881022834828,628813029392', 'text', NULL, 'Whitelist WhatsApp Pengujian', 'Daftar nomor WhatsApp pengujian yang diizinkan menerima OTP saat testing.', 0, 1),

-- 7. PENYIMPANAN MEDIA & BERKAS (Storage)
('storage', 'upload_max_filesize_mb', '10', 'number', NULL, 'Batas Maksimal Upload (MB)', 'Ukuran maksimal berkas soal, gambar, atau lampiran yang dapat diunggah.', 0, 1),
('storage', 'allowed_upload_extensions', 'jpg,jpeg,png,webp,pdf,xlsx,docx', 'text', NULL, 'Ekstensi Berkas Diizinkan', 'Daftar ekstensi berkas yang lolos validasi keamanan sistem unggahan.', 0, 1),
('storage', 'recording_retention_days', '30', 'number', NULL, 'Masa Simpan Rekaman (Hari)', 'Batas hari penyimpanan snapshot dan video WebRTC sebelum dihapus berkala.', 0, 1),
('storage', 'auto_cleanup_expired', '1', 'boolean', NULL, 'Pembersihan Otomatis Berkas Usang', 'Otomatis bersihkan berkas yang telah melewati masa retensi saat antrean berjalan.', 0, 1),
('storage', 'storage_disk_driver', 'local', 'select', '["local", "public", "s3"]', 'Driver Penyimpanan Media', 'Tipe driver tempat berkas media proctoring dan unggahan disimpan.', 0, 1),

-- 8. PEMELIHARAAN SISTEM (Maintenance)
('maintenance', 'maintenance_mode', '0', 'boolean', NULL, 'Mode Pemeliharaan (Maintenance)', 'Nonaktifkan sementara akses peserta ujian untuk keperluan perawatan server.', 0, 1),
('maintenance', 'maintenance_message', 'Sistem Ujian Smart Exam sedang dalam pemeliharaan berkala untuk peningkatan performa. Silakan coba kembali beberapa saat lagi.', 'textarea', NULL, 'Pesan Pemeliharaan', 'Pesan yang ditampilkan di layar siswa ketika maintenance mode aktif.', 0, 1)

ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `description` = VALUES(`description`),
    `field_type` = VALUES(`field_type`),
    `options` = VALUES(`options`),
    `group_name` = VALUES(`group_name`),
    `is_sensitive` = VALUES(`is_sensitive`);

