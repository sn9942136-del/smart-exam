<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI Question Generation Model
 * Mengelola draf batch pembuatan soal otomatis berbasis AI, riwayat prompt, respon mentah, dan status approval guru.
 */
class AiQuestionGenerationModel extends BaseModel
{
    protected string $table = 'ai_question_generations';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'question_bank_id',
        'user_id',
        'prompt',
        'subject_topic',
        'grade_level',
        'question_type',
        'language',
        'difficulty_level',
        'question_count',
        'accepted_count',
        'raw_response',
        'error_message',
        'status', // 'pending', 'success', 'failed'
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan riwayat batch generasi soal AI oleh guru dengan pagination
     */
    public function getHistoryByTeacher(int $userId, int $page = 1, int $perPage = 10): array
    {
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE user_id = :user_id";
        $countStmt = $this->db()->prepare($countSql);
        $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)($countStmt->fetch()['total'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT ag.*, qb.title as bank_title, qb.code as bank_code, s.name as subject_name
            FROM {$this->table} ag
            JOIN question_banks qb ON ag.question_bank_id = qb.id
            LEFT JOIN subjects s ON qb.subject_id = s.id
            WHERE ag.user_id = :user_id
            ORDER BY ag.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalPages = (int)ceil($total / $perPage);

        return [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => max(1, $totalPages)
            ]
        ];
    }

    /**
     * Dapatkan statistik ringkasan AI generator guru
     */
    public function getTeacherStats(int $userId): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_generations,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_batches,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_batches,
                COALESCE(SUM(question_count), 0) as total_questions_generated,
                COALESCE(SUM(accepted_count), 0) as total_questions_accepted
            FROM {$this->table}
            WHERE user_id = :user_id
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_generations'          => 0,
            'successful_batches'         => 0,
            'pending_batches'            => 0,
            'total_questions_generated'  => 0,
            'total_questions_accepted'   => 0
        ];
    }

    /**
     * Dapatkan detail batch generasi soal beserta info bank soal
     */
    public function getDetailWithBank(int $id, ?int $userId = null): ?array
    {
        $where = "ag.id = :id";
        $params = [':id' => $id];

        if ($userId !== null) {
            $where .= " AND ag.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        $sql = "
            SELECT ag.*, qb.title as bank_title, qb.code as bank_code, qb.total_questions, s.name as subject_name
            FROM {$this->table} ag
            JOIN question_banks qb ON ag.question_bank_id = qb.id
            LEFT JOIN subjects s ON qb.subject_id = s.id
            WHERE {$where}
            LIMIT 1
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
