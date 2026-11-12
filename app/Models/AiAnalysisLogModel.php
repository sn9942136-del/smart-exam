<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI Analysis Log Model (UC-13, UC-13a, UC-13b)
 * Mencatat deret waktu (time-series) frame telemetry analitik AI dari browser siswa.
 */
class AiAnalysisLogModel extends BaseModel
{
    protected string $table = 'ai_analysis_logs';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false;

    protected array $allowedFields = [
        'proctoring_session_id',
        'camera_device_id',
        'frame_timestamp',
        'head_pose_yaw',
        'head_pose_pitch',
        'head_pose_roll',
        'gaze_direction',
        'detected_face_count',
        'mouth_movement_detected',
        'phone_detected',
        'audio_noise_level',
        'created_at'
    ];

    /**
     * Dapatkan ringkasan telemetry terbaru untuk sesi proctoring
     */
    public function getRecentLogs(int $proctoringSessionId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE proctoring_session_id = :psid 
                ORDER BY id DESC 
                LIMIT :lim";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':psid', $proctoringSessionId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
