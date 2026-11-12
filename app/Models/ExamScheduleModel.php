<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Schedule Model
 * Mengelola jadwal pelaksanaan ujian online per rombel/kelas.
 */
class ExamScheduleModel extends BaseModel
{
    protected string $table = 'exam_schedules';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_id',
        'class_id',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'status', // 'scheduled', 'active', 'expired'
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan seluruh jadwal ujian untuk exam_id tertentu
     */
    public function getByExamId(int $examId): array
    {
        $sql = "SELECT es.*, c.name as class_name, c.grade_level as class_level, c.major as class_major
                FROM {$this->table} es
                JOIN classes c ON es.class_id = c.id
                WHERE es.exam_id = :exam_id
                ORDER BY es.start_time ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Hapus dan simpan ulang jadwal kelas untuk suatu ujian
     */
    public function syncSchedules(int $examId, array $classIds, string $startTime, string $endTime, int $gracePeriod = 15): bool
    {
        // Hapus jadwal lama
        $delSql = "DELETE FROM {$this->table} WHERE exam_id = :exam_id";
        $delStmt = $this->db()->prepare($delSql);
        $delStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $delStmt->execute();

        if (empty($classIds)) {
            return true;
        }

        $insSql = "INSERT INTO {$this->table} 
                   (exam_id, class_id, start_time, end_time, grace_period_minutes, status, created_at, updated_at) 
                   VALUES (:exam_id, :class_id, :start_time, :end_time, :grace, :status, NOW(), NOW())";
        $insStmt = $this->db()->prepare($insSql);

        $now = date('Y-m-d H:i:s');
        $status = ($startTime <= $now && $endTime >= $now) ? 'active' : ($endTime < $now ? 'expired' : 'scheduled');

        foreach ($classIds as $classId) {
            $insStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
            $insStmt->bindValue(':class_id', (int)$classId, PDO::PARAM_INT);
            $insStmt->bindValue(':start_time', $startTime);
            $insStmt->bindValue(':end_time', $endTime);
            $insStmt->bindValue(':grace', $gracePeriod, PDO::PARAM_INT);
            $insStmt->bindValue(':status', $status);
            $insStmt->execute();
        }

        return true;
    }
}
