<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Result Model
 * Mengelola perolehan nilai hasil ujian, evaluasi KKM, dan integrasi skor pengawasan AI.
 */
class ExamResultModel extends BaseModel
{
    protected string $table = 'exam_results';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_session_id',
        'exam_id',
        'student_id',
        'total_questions',
        'total_answered',
        'correct_answers',
        'wrong_answers',
        'multiple_choice_score',
        'essay_score',
        'final_score',
        'proctoring_integrity_score',
        'is_passed',
        'teacher_feedback',
        'status', // 'pending_grading', 'graded', 'published'
        'graded_at',
        'published_at',
        'published_by',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan seluruh hasil ujian siswa yang sudah dirilis atau diset langsung tampil
     */
    public function getPublishedResultsForStudent(int $studentId, array $filters = []): array
    {
        $where = [
            "er.student_id = :student_id",
            "e.deleted_at IS NULL",
            "(er.status = 'published' OR e.show_result_immediately = 1)"
        ];
        $params = [':student_id' => $studentId];

        if (!empty($filters['subject_id'])) {
            $where[] = "e.subject_id = :subject_id";
            $params[':subject_id'] = (int)$filters['subject_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(e.title LIKE :search OR e.code LIKE :search2 OR s.name LIKE :search3)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
            $params[':search2'] = '%' . trim($filters['search']) . '%';
            $params[':search3'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                er.*,
                e.title as exam_title,
                e.code as exam_code,
                e.type as exam_type,
                e.duration_minutes,
                e.passing_grade,
                e.show_result_immediately,
                e.is_dual_cam_required,
                s.name as subject_name,
                s.code as subject_code,
                u.name as teacher_name,
                sess.start_time as session_start_time,
                sess.submit_time as session_submit_time,
                sess.status as session_status
            FROM {$this->table} er
            JOIN exams e ON er.exam_id = e.id
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON e.teacher_id = u.id
            LEFT JOIN exam_sessions sess ON er.exam_session_id = sess.id
            WHERE {$whereSql}
            ORDER BY er.created_at DESC
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dapatkan detail nilai ujian siswa (berdasarkan result ID atau exam ID)
     * Mengembalikan null jika siswa tidak berhak melihat (hasil belum dirilis)
     */
    public function getResultDetailForStudent(int $idOrExamId, int $studentId): ?array
    {
        $id = (int)$idOrExamId;
        $stId = (int)$studentId;

        $sql = "
            SELECT 
                er.*,
                e.title as exam_title,
                e.code as exam_code,
                e.description as exam_description,
                e.type as exam_type,
                e.duration_minutes,
                e.passing_grade,
                e.show_result_immediately,
                e.is_dual_cam_required,
                e.status as exam_status,
                s.name as subject_name,
                s.code as subject_code,
                u.name as teacher_name,
                sess.start_time as session_start_time,
                sess.submit_time as session_submit_time,
                sess.ip_address as session_ip,
                sess.cam1_status,
                sess.cam2_status,
                sess.integrity_score as session_live_integrity
            FROM {$this->table} er
            JOIN exams e ON er.exam_id = e.id
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON e.teacher_id = u.id
            LEFT JOIN exam_sessions sess ON er.exam_session_id = sess.id
            WHERE (er.id = {$id} OR er.exam_id = {$id})
              AND er.student_id = {$stId}
              AND e.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db()->query($sql);
        $result = $stmt ? $stmt->fetch() : null;

        if (!$result) {
            return null;
        }

        // Cek apakah hasil sudah diizinkan untuk dilihat oleh siswa
        $isReleased = ($result['status'] === 'published' || (int)$result['show_result_immediately'] === 1);
        $result['is_released'] = $isReleased;

        return $result;
    }
}
