<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI Warning Model (UC-18)
 * Mencatat peringatan yang dikirimkan ke layar siswa dan riwayat konfirmasi pengakuan peringatan.
 */
class AiWarningModel extends BaseModel
{
    protected string $table = 'ai_warnings';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false;

    protected array $allowedFields = [
        'proctoring_session_id',
        'violation_id',
        'warning_type',
        'warning_message',
        'is_acknowledged',
        'acknowledged_at',
        'created_at'
    ];

    /**
     * Ambil seluruh peringatan untuk sesi proctoring tertentu
     */
    public function getWarningsBySessionId(int $proctoringSessionId): array
    {
        $sql = "SELECT w.*, v.confidence_score, vt.name as violation_name, vt.code as violation_code
                FROM {$this->table} w
                LEFT JOIN ai_violations v ON v.id = w.violation_id
                LEFT JOIN ai_violation_types vt ON vt.id = v.violation_type_id
                WHERE w.proctoring_session_id = :psid
                ORDER BY w.id DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':psid', $proctoringSessionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
