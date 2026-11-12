<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Question Import Model
 * Mencatat log riwayat impor butir soal dari file Excel, CSV, Word, atau JSON.
 */
class QuestionImportModel extends BaseModel
{
    protected string $table = 'question_imports';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'question_bank_id',
        'user_id',
        'file_name',
        'file_type', // 'excel', 'word', 'json', 'csv'
        'status', // 'pending', 'processing', 'completed', 'failed'
        'total_imported',
        'error_log',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan riwayat impor terakhir untuk suatu bank soal
     */
    public function getRecentByBankId(int $bankId, int $limit = 10): array
    {
        $sql = "SELECT qi.*, u.name as user_name, u.email as user_email
                FROM {$this->table} qi
                LEFT JOIN users u ON qi.user_id = u.id
                WHERE qi.question_bank_id = :bank_id
                ORDER BY qi.id DESC
                LIMIT :limit";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':bank_id', $bankId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
