<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Question Model (Butir Soal)
 * Mengelola butir soal, tipe (Pilihan Ganda, Esai, Benar/Salah), bobot nilai, dan kesulitan.
 */
class QuestionModel extends BaseModel
{
    protected string $table = 'questions';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'question_bank_id',
        'type', // 'multiple_choice', 'single_choice', 'essay', 'true_false', 'matching'
        'content',
        'question_image',
        'question_audio',
        'explanation',
        'weight',
        'difficulty', // 'easy', 'medium', 'hard'
        'order_number',
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan opsi pilihan jawaban untuk soal ini
     */
    public function getOptions(int|string $questionId): array
    {
        $sql = "SELECT * FROM question_options WHERE question_id = :qid ORDER BY id ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':qid', (int)$questionId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dapatkan seluruh butir soal dalam bank soal beserta opsi jawabannya
     */
    public function getQuestionsByBankId(
        int $bankId,
        ?string $type = null,
        ?string $difficulty = null,
        ?string $search = null
    ): array {
        $conditions = [
            "q.question_bank_id = :bank_id",
            "q.deleted_at IS NULL"
        ];
        $params = [':bank_id' => $bankId];

        if ($type !== null && $type !== '' && $type !== 'all') {
            $conditions[] = "q.type = :type";
            $params[':type'] = $type;
        }

        if ($difficulty !== null && $difficulty !== '' && $difficulty !== 'all') {
            $conditions[] = "q.difficulty = :difficulty";
            $params[':difficulty'] = $difficulty;
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = "(q.content LIKE :search OR q.explanation LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT q.* 
                FROM {$this->table} q 
                WHERE {$whereClause}
                ORDER BY q.order_number ASC, q.id ASC";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $questions = $stmt->fetchAll() ?: [];

        if (empty($questions)) {
            return [];
        }

        // Ambil semua opsi dalam 1 query efisien
        $questionIds = array_column($questions, 'id');
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        
        $optSql = "SELECT * FROM question_options WHERE question_id IN ({$placeholders}) ORDER BY id ASC";
        $optStmt = $this->db()->prepare($optSql);
        foreach ($questionIds as $idx => $qid) {
            $optStmt->bindValue($idx + 1, (int)$qid, \PDO::PARAM_INT);
        }
        $optStmt->execute();
        $allOptions = $optStmt->fetchAll() ?: [];

        // Kelompokkan opsi per question_id
        $groupedOptions = [];
        foreach ($allOptions as $opt) {
            $groupedOptions[$opt['question_id']][] = $opt;
        }

        foreach ($questions as &$q) {
            $q['options'] = $groupedOptions[$q['id']] ?? [];
        }

        return $questions;
    }

    /**
     * Dapatkan detail lengkap 1 butir soal beserta opsi dan informasi bank soal
     */
    public function getWithFullDetails(int $id): ?array
    {
        $sql = "SELECT q.*, 
                       qb.title as bank_title,
                       qb.code as bank_code,
                       qb.subject_id,
                       qb.creator_id,
                       s.name as subject_name,
                       s.code as subject_code
                FROM {$this->table} q
                JOIN question_banks qb ON q.question_bank_id = qb.id
                LEFT JOIN subjects s ON qb.subject_id = s.id
                WHERE q.id = :id AND q.deleted_at IS NULL";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $question = $stmt->fetch();

        if (!$question) {
            return null;
        }

        $question['options'] = $this->getOptions($id);
        return $question;
    }

    /**
     * Hitung nomor urut berikutnya dalam bank soal
     */
    public function getNextOrderNumber(int $bankId): int
    {
        $sql = "SELECT COALESCE(MAX(order_number), 0) + 1 as next_order 
                FROM {$this->table} 
                WHERE question_bank_id = :bank_id AND deleted_at IS NULL";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':bank_id', $bankId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int)($stmt->fetch()['next_order'] ?? 1);
    }
}
