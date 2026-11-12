<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Face Verification Model (UC-08)
 * Mengelola hasil perbandingan foto peserta dengan wajah yang tertangkap kamera saat mulai ujian.
 */
class FaceVerificationModel extends BaseModel
{
    protected string $table = 'face_verifications';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false;

    protected array $allowedFields = [
        'proctoring_session_id',
        'student_id',
        'reference_photo_url',
        'captured_photo_url',
        'similarity_score',
        'verification_status', // 'matched', 'mismatched', 'uncertain'
        'verified_by',         // 'ai', 'manual_proctor'
        'verified_at',
        'created_at'
    ];

    /**
     * Dapatkan verifikasi wajah terbaru untuk sesi proctoring tertentu
     */
    public function getLatestBySessionId(int $proctoringSessionId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE proctoring_session_id = :psid 
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':psid', $proctoringSessionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }
}
