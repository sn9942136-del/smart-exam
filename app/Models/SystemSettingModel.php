<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

/**
 * System Setting Model
 * Mengelola konfigurasi terpusat seluruh modul aplikasi Smart Exam:
 * Identitas Umum, Keamanan Sesi, CBT Engine, AI Proctoring, Model LLM, Notifikasi, dan Storage.
 */
class SystemSettingModel extends BaseModel
{
    protected string $table = 'system_settings';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'group_name',
        'key_name',
        'key_value',
        'field_type',
        'options',
        'label',
        'description',
        'is_sensitive',
        'is_editable',
        'created_at',
        'updated_at'
    ];

    /**
     * Cache memory sederhana untuk optimasi performa query
     */
    private static array $cache = [];

    /**
     * Daftar metadata kelompok konfigurasi beserta ikon dan deskripsinya
     */
    public static function getGroupMetadata(): array
    {
        return [
            'general' => [
                'title'       => 'Identitas & Umum',
                'icon'        => 'bi-buildings',
                'badge'       => 'Sistem',
                'badge_class' => 'bg-primary-subtle text-primary',
                'description' => 'Konfigurasi nama aplikasi, branding institusi sekolah, zona waktu, dan bahasa sistem.'
            ],
            'security' => [
                'title'       => 'Keamanan & Autentikasi',
                'icon'        => 'bi-shield-lock',
                'badge'       => 'Security',
                'badge_class' => 'bg-danger-subtle text-danger',
                'description' => 'Durasi sesi, proteksi CSRF, masa berlaku OTP, dan rate limiting login.'
            ],
            'exam' => [
                'title'       => 'CBT & Pelaksanaan Ujian',
                'icon'        => 'bi-journal-check',
                'badge'       => 'CBT Engine',
                'badge_class' => 'bg-success-subtle text-success',
                'description' => 'Pengacakan butir soal, antisipasi pindah tab (blur window), autosave, dan rilis nilai.'
            ],
            'proctoring' => [
                'title'       => 'Dual Camera & AI Vision',
                'icon'        => 'bi-camera-reels',
                'badge'       => 'WebRTC & Vision',
                'badge_class' => 'bg-info-subtle text-info',
                'description' => 'Model deteksi wajah, gaze tracking, toleransi wajah hilang, dan sinkronisasi dual cam.'
            ],
            'ai' => [
                'title'       => 'Kecerdasan Buatan (LLM)',
                'icon'        => 'bi-robot',
                'badge'       => 'Gemini & AI',
                'badge_class' => 'bg-warning-subtle text-warning-emphasis',
                'description' => 'Provider AI, model Gemini/GPT, generator soal guru, dan toleransi penilaian esai.'
            ],
            'notification' => [
                'title'       => 'Notifikasi Email & WhatsApp',
                'icon'        => 'bi-bell',
                'badge'       => 'SMTP & Gateway',
                'badge_class' => 'bg-purple-subtle text-purple',
                'description' => 'Mode pengiriman, SMTP Gmail STARTTLS, WhatsApp Gateway Fonnte, dan whitelist testing.'
            ],
            'storage' => [
                'title'       => 'Penyimpanan Media & Berkas',
                'icon'        => 'bi-hdd-network',
                'badge'       => 'Storage',
                'badge_class' => 'bg-secondary-subtle text-secondary',
                'description' => 'Batas ukuran berkas, ekstensi unggahan, retensi rekaman video, dan pembersihan otomatis.'
            ],
            'maintenance' => [
                'title'       => 'Pemeliharaan Sistem',
                'icon'        => 'bi-tools',
                'badge'       => 'Server State',
                'badge_class' => 'bg-dark-subtle text-dark',
                'description' => 'Mode pemeliharaan sistem (maintenance mode) dan pesan pemberitahuan peserta.'
            ],
        ];
    }

    /**
     * Dapatkan nilai konfigurasi berdasarkan key_name
     */
    public static function getVal(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }

        try {
            $model = new self();
            $row = $model->where('key_name', $key)->first();
            if ($row) {
                $val = $row['key_value'];
                // Cast tipe data sesuai field_type
                if ($row['field_type'] === 'boolean') {
                    $val = in_array(strtolower((string)$val), ['1', 'true', 'yes', 'on'], true);
                } elseif ($row['field_type'] === 'number' || $row['field_type'] === 'range') {
                    $val = is_numeric($val) ? (str_contains((string)$val, '.') ? (float)$val : (int)$val) : $val;
                }
                self::$cache[$key] = $val;
                return $val;
            }
        } catch (Throwable) {
            // Fallback jika tabel belum terbentuk
        }

        return $default;
    }

    /**
     * Bersihkan memory cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Set nilai konfigurasi tunggal
     */
    public static function setVal(string $key, mixed $value): bool
    {
        $model = new self();
        $strVal = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
        $existing = $model->where('key_name', $key)->first();

        if ($existing) {
            $model->update($existing['id'], [
                'key_value'  => $strVal,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            $model->insert([
                'group_name'  => 'general',
                'key_name'    => $key,
                'key_value'   => $strVal,
                'label'       => ucwords(str_replace('_', ' ', $key)),
                'field_type'  => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'text'),
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ]);
        }

        self::clearCache();
        return true;
    }

    /**
     * Dapatkan seluruh konfigurasi yang dikelompokkan berdasarkan group_name
     */
    public function getAllGrouped(): array
    {
        $this->ensureTableExists();

        $rows = $this->findAll([], 'group_name ASC, id ASC');
        $grouped = [];

        foreach ($rows as $row) {
            // Decode opsi JSON jika ada
            if (!empty($row['options']) && is_string($row['options'])) {
                $row['options_decoded'] = json_decode($row['options'], true);
            } else {
                $row['options_decoded'] = null;
            }
            $grouped[$row['group_name']][] = $row;
        }

        return $grouped;
    }

    /**
     * Update bulk konfigurasi dari array form [key_name => key_value]
     * Menjaga kolom password/sensitive agar tidak tertimpa jika dikirimkan kosong.
     */
    public function updateBulk(array $settings): bool
    {
        $this->ensureTableExists();
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                UPDATE {$this->table}
                SET key_value = :key_value, updated_at = NOW()
                WHERE key_name = :key_name
            ");

            // Ambil daftar field yang bertipe sensitive
            $sensitiveKeys = $db->query("
                SELECT key_name FROM {$this->table} WHERE is_sensitive = 1
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $aiSyncValues = [];

            foreach ($settings as $key => $val) {
                // Jangan timpa password/token sensitif jika dikirim kosong
                if (in_array($key, $sensitiveKeys, true) && (string)$val === '') {
                    continue;
                }

                $strVal = (string) $val;
                $stmt->execute([
                    ':key_name'  => $key,
                    ':key_value' => $strVal
                ]);

                $aiSyncValues[$key] = $strVal;
            }

            $db->commit();
            self::clearCache();

            // Sinkronisasikan ke tabel ai_configurations jika ada parameter AI
            $this->syncToAiConfigurations($aiSyncValues);

            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Sinkronisasi nilai-nilai ke tabel ai_configurations agar modul AI tetap kompatibel
     */
    public function syncToAiConfigurations(array $settings): void
    {
        $aiKeysMapping = [
            'face_detection_model'          => 'face_detection_model',
            'gaze_sensitivity_threshold'    => 'gaze_sensitivity_threshold',
            'no_face_timeout_seconds'       => 'no_face_timeout_seconds',
            'dual_cam_sync_interval_ms'     => 'dual_cam_sync_interval_ms',
            'ai_question_generation_model' => 'ai_question_generation_model',
            'ai_essay_grading_tolerance'    => 'ai_essay_grading_tolerance'
        ];

        try {
            $db = $this->db();
            // Periksa apakah tabel ai_configurations ada
            $check = $db->query("SHOW TABLES LIKE 'ai_configurations'")->fetch();
            if (!$check) {
                return;
            }

            $stmt = $db->prepare("
                UPDATE ai_configurations 
                SET key_value = :val, updated_at = NOW() 
                WHERE key_name = :key
            ");

            foreach ($aiKeysMapping as $sysKey => $aiKey) {
                if (isset($settings[$sysKey]) && $settings[$sysKey] !== '') {
                    $stmt->execute([
                        ':key' => $aiKey,
                        ':val' => (string)$settings[$sysKey]
                    ]);
                }
            }
        } catch (Throwable) {
            // Ignore error gracefully
        }
    }

    /**
     * Reset seluruh parameter sistem ke baseline default rekomendasi
     */
    public function resetToDefaults(): bool
    {
        $this->ensureTableExists();

        $defaults = [
            // General
            'app_name'                     => 'Smart Exam',
            'app_tagline'                  => 'Sistem Ujian Online CBT Cerdas Berbasis AI Dual Kamera',
            'institution_name'             => 'SMA Negeri 1 Smart Exam',
            'institution_address'          => 'Jl. Pendidikan Unggul No. 1, Jakarta',
            'institution_phone'            => '021-5551234',
            'institution_email'            => 'info@smartexam.sch.id',
            'app_url'                      => 'http://localhost/PRD/public',
            'app_timezone'                 => 'Asia/Jakarta',
            'default_locale'               => 'id',

            // Security
            'session_lifetime'             => '7200',
            'csrf_protection'              => '1',
            'otp_expiry_minutes'           => '15',
            'otp_max_attempts'             => '3',
            'rate_limit_login_max'         => '5',
            'rate_limit_login_decay'       => '60',
            'password_min_length'          => '8',
            'enforce_single_session'       => '1',

            // Exam
            'default_randomize_questions'  => '1',
            'default_randomize_options'    => '1',
            'default_show_result_instant'  => '0',
            'default_show_explanation'     => '1',
            'autosave_interval_seconds'    => '15',
            'late_tolerance_minutes'       => '15',
            'prevent_tab_switch'           => '1',
            'max_tab_switch_allowed'       => '3',
            'auto_submit_on_time_out'      => '1',

            // Proctoring
            'strict_dual_camera'           => '1',
            'face_detection_model'         => 'Canvas Vision Engine',
            'dual_cam_sync_interval_ms'    => '1000',
            'gaze_sensitivity_threshold'   => '0.75',
            'no_face_timeout_seconds'      => '5',
            'multi_face_detection'         => '1',
            'max_proctoring_warnings'      => '5',
            'webrtc_stun_server'           => 'stun:stun.l.google.com:19302',

            // AI
            'ai_provider'                  => 'gemini',
            'gemini_model'                 => 'gemini-3.5-flash-lite',
            'gemini_fallback_model'        => 'gemini-flash-lite-latest,gemini-2.5-flash',
            'ai_question_generation_model' => 'gpt-4o-mini',
            'ai_essay_grading_tolerance'   => '0.85',
            'ai_chat_assistant_enabled'    => '1',
            'ai_question_generation_enabled' => '1',

            // Notification
            'notification_mode'            => 'sandbox',
            'smtp_host'                    => 'smtp.gmail.com',
            'smtp_port'                    => '587',
            'smtp_encryption'              => 'tls',
            'smtp_username'                => 'smart.examm@gmail.com',
            'smtp_from_email'              => 'smart.examm@gmail.com',
            'smtp_from_name'               => 'Smart Exam',
            'notification_test_recipient'  => 'sn9942136@gmail.com,tiasitihawa4@gmail.com',
            'whatsapp_api_url'             => 'https://api.fonnte.com/send',
            'notification_test_phone'      => '62881022834828,628813029392',

            // Storage
            'upload_max_filesize_mb'       => '10',
            'allowed_upload_extensions'    => 'jpg,jpeg,png,webp,pdf,xlsx,docx',
            'recording_retention_days'     => '30',
            'auto_cleanup_expired'         => '1',
            'storage_disk_driver'          => 'local',

            // Maintenance
            'maintenance_mode'             => '0',
            'maintenance_message'          => 'Sistem Ujian Smart Exam sedang dalam pemeliharaan berkala untuk peningkatan performa. Silakan coba kembali beberapa saat lagi.'
        ];

        return $this->updateBulk($defaults);
    }

    /**
     * Ekspor seluruh konfigurasi (kecuali kunci sensitif) ke array
     */
    public function exportConfig(): array
    {
        $this->ensureTableExists();
        $rows = $this->findAll([], 'group_name ASC, id ASC');
        $export = [
            'app_version' => 'v1.0-alpha',
            'exported_at' => date('Y-m-d H:i:s'),
            'settings'    => []
        ];

        foreach ($rows as $row) {
            $export['settings'][$row['key_name']] = [
                'group'        => $row['group_name'],
                'value'        => (int)$row['is_sensitive'] === 1 ? '***[PROTECTED]***' : $row['key_value'],
                'label'        => $row['label'],
                'is_sensitive' => (bool)$row['is_sensitive']
            ];
        }

        return $export;
    }

    /**
     * Impor konfigurasi dari array JSON yang diunggah
     */
    public function importConfig(array $data): array
    {
        if (empty($data['settings']) || !is_array($data['settings'])) {
            return ['success' => false, 'message' => 'Format file konfigurasi tidak valid (tidak ada elemen settings).'];
        }

        $importedCount = 0;
        $updateList = [];

        foreach ($data['settings'] as $key => $item) {
            $val = is_array($item) ? ($item['value'] ?? null) : $item;
            if ($val !== null && $val !== '***[PROTECTED]***') {
                $updateList[$key] = $val;
                $importedCount++;
            }
        }

        if (!empty($updateList)) {
            $this->updateBulk($updateList);
        }

        return [
            'success' => true,
            'count'   => $importedCount,
            'message' => "Berhasil memulihkan {$importedCount} konfigurasi sistem."
        ];
    }

    /**
     * Memastikan tabel system_settings telah dibuat dan memiliki data dasar
     */
    public function ensureTableExists(): void
    {
        try {
            $db = $this->db();
            $check = $db->query("SHOW TABLES LIKE '{$this->table}'")->fetch();
            if (!$check) {
                $migrationFile = dirname(__DIR__, 2) . '/database/migrations/012_create_system_settings_table.sql';
                if (file_exists($migrationFile)) {
                    $sql = file_get_contents($migrationFile);
                    $db->exec($sql);
                }
            } else {
                // Cek apakah tabel memiliki isi
                $count = (int)$db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
                if ($count === 0) {
                    $migrationFile = dirname(__DIR__, 2) . '/database/migrations/012_create_system_settings_table.sql';
                    if (file_exists($migrationFile)) {
                        $sql = file_get_contents($migrationFile);
                        $db->exec($sql);
                    }
                }
            }
        } catch (Throwable) {
            // Ignore error
        }
    }
}
