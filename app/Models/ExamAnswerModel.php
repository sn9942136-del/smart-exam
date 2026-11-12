<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Answer Model
 * Mengelola penyimpanan jawaban butir soal (Pilihan Ganda & Esai), auto-save, dan flag ragu-ragu.
 */
class ExamAnswerModel extends BaseModel
{
    protected string $table = 'exam_answers';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_session_id',
        'question_id',
        'selected_option_id',
        'essay_answer',
        'is_correct',
        'score_earned',
        'teacher_feedback',
        'ai_explanation',
        'is_flagged',
        'answered_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Simpan atau perbarui jawaban peserta ujian (Atomic Upsert)
     */
    public function upsertAnswer(
        int $sessionId,
        int $questionId,
        ?int $selectedOptionId = null,
        ?string $essayAnswer = null,
        int $isFlagged = 0
    ): bool {
        $sql = "
            INSERT INTO {$this->table} 
                (exam_session_id, question_id, selected_option_id, essay_answer, is_flagged, answered_at, created_at, updated_at)
            VALUES 
                (:session_id, :question_id, :opt_id, :essay, :flagged, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                selected_option_id = VALUES(selected_option_id),
                essay_answer       = VALUES(essay_answer),
                is_flagged         = VALUES(is_flagged),
                answered_at        = NOW(),
                updated_at         = NOW()
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
        $stmt->bindValue(':opt_id', $selectedOptionId !== null && $selectedOptionId > 0 ? $selectedOptionId : null, $selectedOptionId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':essay', $essayAnswer !== null && trim($essayAnswer) !== '' ? trim($essayAnswer) : null, $essayAnswer !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':flagged', $isFlagged ? 1 : 0, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Dapatkan seluruh jawaban tersimpan untuk sesi tertentu (diindeks berdasarkan question_id)
     */
    public function getAnswersBySessionId(int $sessionId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE exam_session_id = :session_id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        $indexed = [];
        foreach ($rows as $r) {
            $indexed[(int)$r['question_id']] = $r;
        }

        return $indexed;
    }

    /**
     * Hitung ringkasan jumlah soal yang sudah dijawab dan ragu-ragu
     */
    public function countAnsweredQuestions(int $sessionId): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN (selected_option_id IS NOT NULL OR (essay_answer IS NOT NULL AND TRIM(essay_answer) != '')) THEN 1 ELSE 0 END) as answered_count,
                SUM(CASE WHEN is_flagged = 1 THEN 1 ELSE 0 END) as flagged_count
            FROM {$this->table}
            WHERE exam_session_id = :session_id
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'answered' => (int)($row['answered_count'] ?? 0),
            'flagged'  => (int)($row['flagged_count'] ?? 0),
            'total'    => (int)($row['total_records'] ?? 0)
        ];
    }
}
