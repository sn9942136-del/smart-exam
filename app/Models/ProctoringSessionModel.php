<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Proctoring Session Model
 * Manages WebRTC stream links and AI proctoring lifecycle.
 */
class ProctoringSessionModel extends BaseModel
{
    protected string $table = 'proctoring_sessions';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_session_id',
        'student_id',
        'cam1_stream_id',
        'cam2_stream_id',
        'status', // 'initializing', 'calibrating', 'active', 'interrupted', 'finished'
        'initial_face_embedding',
        'started_at',
        'ended_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan daftar pelanggaran pada sesi ini
     */
    public function getViolations(int|string $proctoringSessionId): array
    {
        $sql = "
            SELECT v.*, vt.name as violation_name, vt.severity, vt.penalty_score
            FROM ai_violations v
            INNER JOIN ai_violation_types vt ON vt.id = v.violation_type_id
            WHERE v.proctoring_session_id = :psid
            ORDER BY v.created_at DESC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':psid', $proctoringSessionId);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
