<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI Configuration Model
 * Manages system-wide AI proctoring, question generation, and grading thresholds.
 */
class AiConfigurationModel extends BaseModel
{
    protected string $table = 'ai_configurations';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'key_name',
        'key_value',
        'category',
        'description',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan nilai konfigurasi berdasarkan key
     */
    public static function getVal(string $key, mixed $default = null): mixed
    {
        $model = new self();
        $row = $model->where('key_name', $key)->first();
        return $row['key_value'] ?? $default;
    }

    /**
     * Dapatkan semua konfigurasi AI yang dikelompokkan berdasarkan kategori
     */
    public function getAllGrouped(): array
    {
        $rows = $this->findAll([], 'category ASC, id ASC');
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    /**
     * Update bulk konfigurasi dari array key => value
     */
    public function updateBulk(array $settings): bool
    {
        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare("
                UPDATE {$this->table} 
                SET key_value = :key_value, updated_at = NOW() 
                WHERE key_name = :key_name
            ");

            foreach ($settings as $key => $val) {
                $stmt->execute([
                    ':key_name'  => $key,
                    ':key_value' => (string) $val
                ]);
            }

            $this->db()->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            return false;
        }
    }

    /**
     * Reset seluruh parameter AI ke baseline default rekomendasi
     */
    public function resetToDefaults(): bool
    {
        $defaults = [
            'face_detection_model'          => 'MediaPipe Face Mesh v2',
            'gaze_sensitivity_threshold'    => '0.75',
            'no_face_timeout_seconds'       => '5',
            'dual_cam_sync_interval_ms'     => '1000',
            'ai_question_generation_model' => 'gpt-4o-mini',
            'ai_essay_grading_tolerance'    => '0.85'
        ];

        return $this->updateBulk($defaults);
    }
}
