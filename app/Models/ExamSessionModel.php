<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Session Model
 * Manages active student exam attempts, countdown timer synchronization, and live dual-cam stream statuses.
 */
class ExamSessionModel extends BaseModel
{
    protected string $table = 'exam_sessions';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_id',
        'student_id',
        'session_token',
        'ip_address',
        'user_agent',
        'start_time',
        'submit_time',
        'remaining_seconds',
        'status', // 'ongoing', 'paused', 'submitted', 'terminated'
        'integrity_score',
        'cam1_status', // 'connected', 'disconnected', 'error'
        'cam2_status', // 'connected', 'disconnected', 'error'
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan sesi ujian aktif berdasarkan token sesi
     */
    public function findByToken(string $sessionToken): ?array
    {
        return $this->where('session_token', $sessionToken)->first();
    }

    /**
     * Cari sesi aktif yang sedang berjalan untuk siswa dan ujian tertentu
     */
    public function findActiveSession(int $examId, int $studentId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE exam_id = :exam_id 
                  AND student_id = :student_id 
                  AND status = 'ongoing' 
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Cari sesi aktif yang sedang berjalan untuk siswa di ujian apapun (untuk Session Recovery Dashboard)
     */
    public function findAnyActiveSessionForStudent(int $studentId): ?array
    {
        $sql = "SELECT es.*, e.title as exam_title, e.code as exam_code, e.duration_minutes
                FROM {$this->table} es
                JOIN exams e ON es.exam_id = e.id
                WHERE es.student_id = :student_id 
                  AND es.status = 'ongoing' 
                  AND e.deleted_at IS NULL
                ORDER BY es.id DESC LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Cari dan verifikasi sesi ujian aktif berdasarkan ID sesi dan ID siswa
     */
    public function findActiveSessionByIdAndStudent(int $sessionId, int $studentId): ?array
    {
        $sql = "SELECT es.*, e.title as exam_title, e.code as exam_code, e.duration_minutes
                FROM {$this->table} es
                JOIN exams e ON es.exam_id = e.id
                WHERE es.id = :session_id
                  AND es.student_id = :student_id 
                  AND es.status = 'ongoing' 
                  AND e.deleted_at IS NULL
                LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Ambil seluruh sesi berstatus ongoing untuk siswa tertentu (terbaru lebih dahulu)
     */
    public function findAllActiveSessionsForStudent(int $studentId): array
    {
        $sql = "SELECT es.*, e.title as exam_title, e.code as exam_code, e.duration_minutes
                FROM {$this->table} es
                JOIN exams e ON es.exam_id = e.id
                WHERE es.student_id = :student_id 
                  AND es.status = 'ongoing' 
                  AND e.deleted_at IS NULL
                ORDER BY es.id DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Buat sesi ujian baru untuk peserta didik
     */
    public function createSession(int $examId, int $studentId, int $durationMinutes, ?string $ip = null, ?string $userAgent = null): array
    {
        $sessionToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $remainingSeconds = $durationMinutes * 60;

        $id = $this->insert([
            'exam_id'           => $examId,
            'student_id'        => $studentId,
            'session_token'     => $sessionToken,
            'ip_address'        => $ip ?? '127.0.0.1',
            'user_agent'        => $userAgent ?? 'Browser',
            'start_time'        => $now,
            'submit_time'       => null,
            'remaining_seconds' => $remainingSeconds,
            'status'            => 'ongoing',
            'integrity_score'   => 100.00,
            'cam1_status'       => 'connected',
            'cam2_status'       => 'connected',
            'created_at'        => $now,
            'updated_at'        => $now
        ]);

        return [
            'id'                => (int)$id,
            'exam_id'           => $examId,
            'student_id'        => $studentId,
            'session_token'     => $sessionToken,
            'start_time'        => $now,
            'remaining_seconds' => $remainingSeconds,
            'status'            => 'ongoing'
        ];
    }

    /**
     * Perbarui sisa detik waktu pengerjaan
     */
    public function updateRemainingSeconds(int $sessionId, int $remainingSeconds): bool
    {
        $sql = "UPDATE {$this->table} 
                SET remaining_seconds = :sec, updated_at = NOW() 
                WHERE id = :id AND status = 'ongoing'";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':sec', max(0, $remainingSeconds), PDO::PARAM_INT);
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Tandai sesi ujian telah disubmit / selesai dikumpulkan
     */
    public function markSubmitted(int $sessionId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET status = 'submitted', submit_time = NOW(), remaining_seconds = 0, updated_at = NOW() 
                WHERE id = :id AND status = 'ongoing'";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Dapatkan sesi terkini siswa untuk ujian tertentu
     */
    public function getStudentLatestSession(int $examId, int $studentId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE exam_id = :exam_id AND student_id = :student_id 
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Dapatkan riwayat seluruh sesi ujian siswa
     */
    public function getStudentSessionHistory(int $studentId): array
    {
        $sql = "
            SELECT es.*, e.title as exam_title, e.code as exam_code, s.name as subject_name
            FROM {$this->table} es
            JOIN exams e ON es.exam_id = e.id
            JOIN subjects s ON e.subject_id = s.id
            WHERE es.student_id = :student_id
            ORDER BY es.created_at DESC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
