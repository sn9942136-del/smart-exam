<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Question Model
 * Menghubungkan butir soal dari Bank Soal ke Ujian beserta urutan dan bobot nilai.
 */
class ExamQuestionModel extends BaseModel
{
    protected string $table = 'exam_questions';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_id',
        'question_id',
        'order_number',
        'score_weight',
        'created_at'
    ];

    /**
     * Dapatkan butir soal untuk ujian tertentu beserta opsi jawabannya
     */
    public function getQuestionsByExamId(int $examId): array
    {
        $sql = "SELECT eq.*, q.type as question_type, q.content, q.question_image, q.explanation, q.difficulty, q.weight as base_weight
                FROM {$this->table} eq
                JOIN questions q ON eq.question_id = q.id
                WHERE eq.exam_id = :exam_id AND q.deleted_at IS NULL
                ORDER BY eq.order_number ASC, eq.id ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        $questions = $stmt->fetchAll() ?: [];

        if (empty($questions)) {
            return [];
        }

        // Ambil opsi pilihan jawaban
        $qIds = array_column($questions, 'question_id');
        $placeholders = implode(',', array_fill(0, count($qIds), '?'));
        $optSql = "SELECT * FROM question_options WHERE question_id IN ({$placeholders}) ORDER BY id ASC";
        $optStmt = $this->db()->prepare($optSql);
        foreach ($qIds as $idx => $qid) {
            $optStmt->bindValue($idx + 1, (int)$qid, PDO::PARAM_INT);
        }
        $optStmt->execute();
        $allOptions = $optStmt->fetchAll() ?: [];

        $groupedOptions = [];
        foreach ($allOptions as $opt) {
            $groupedOptions[$opt['question_id']][] = $opt;
        }

        foreach ($questions as &$q) {
            $q['options'] = $groupedOptions[$q['question_id']] ?? [];
        }

        return $questions;
    }

    /**
     * Hubungkan butir soal dari Bank Soal ke Ujian
     */
    public function syncQuestionsFromBank(int $examId, int $bankId, ?int $limitCount = null): int
    {
        // Hapus mapping lama
        $delSql = "DELETE FROM {$this->table} WHERE exam_id = :exam_id";
        $delStmt = $this->db()->prepare($delSql);
        $delStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $delStmt->execute();

        // Ambil soal dari bank
        $qSql = "SELECT id, weight, order_number FROM questions 
                 WHERE question_bank_id = :bank_id AND deleted_at IS NULL 
                 ORDER BY order_number ASC, id ASC";
        if ($limitCount !== null && $limitCount > 0) {
            $qSql .= " LIMIT " . (int)$limitCount;
        }

        $qStmt = $this->db()->prepare($qSql);
        $qStmt->bindValue(':bank_id', $bankId, PDO::PARAM_INT);
        $qStmt->execute();
        $bankQuestions = $qStmt->fetchAll() ?: [];

        if (empty($bankQuestions)) {
            return 0;
        }

        $insSql = "INSERT INTO {$this->table} (exam_id, question_id, order_number, score_weight, created_at) 
                   VALUES (:exam_id, :question_id, :order_number, :score_weight, NOW())";
        $insStmt = $this->db()->prepare($insSql);

        $count = 0;
        foreach ($bankQuestions as $idx => $bq) {
            $insStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
            $insStmt->bindValue(':question_id', (int)$bq['id'], PDO::PARAM_INT);
            $insStmt->bindValue(':order_number', $idx + 1, PDO::PARAM_INT);
            $insStmt->bindValue(':score_weight', (float)($bq['weight'] ?? 1.0), PDO::PARAM_STR);
            $insStmt->execute();
            $count++;
        }

        return $count;
    }

    /**
     * Cek apakah butir soal terdaftar dan aktif dalam ujian ini
     */
    public function isQuestionInExam(int $examId, int $questionId): bool
    {
        $sql = "SELECT eq.id 
                FROM {$this->table} eq
                JOIN questions q ON eq.question_id = q.id
                WHERE eq.exam_id = :exam_id 
                  AND eq.question_id = :question_id 
                  AND q.deleted_at IS NULL 
                LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }
}
