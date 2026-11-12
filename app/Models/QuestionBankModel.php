<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Question Bank Model
 * Mengelola entitas bank soal, relasi dengan mata pelajaran, dan statistik butir soal.
 */
class QuestionBankModel extends BaseModel
{
    protected string $table = 'question_banks';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'subject_id',
        'creator_id',
        'code',
        'title',
        'description',
        'total_questions',
        'status', // 'draft', 'active', 'archived'
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan daftar bank soal dengan pagination, pencarian, dan filter
     */
    public function getPaginatedBanks(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $subjectId = null,
        ?string $status = null,
        ?int $creatorId = null
    ): array {
        $conditions = ["qb.deleted_at IS NULL"];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = "(qb.title LIKE :search OR qb.code LIKE :search OR qb.description LIKE :search OR s.name LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($subjectId !== null && $subjectId !== '' && $subjectId !== 'all') {
            $conditions[] = "qb.subject_id = :subject_id";
            $params[':subject_id'] = (int)$subjectId;
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $conditions[] = "qb.status = :status";
            $params[':status'] = $status;
        }

        if ($creatorId !== null && $creatorId > 0) {
            $conditions[] = "qb.creator_id = :creator_id";
            $params[':creator_id'] = $creatorId;
        }

        $whereClause = implode(' AND ', $conditions);

        // 1. Hitung total data
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} qb 
                     LEFT JOIN subjects s ON qb.subject_id = s.id 
                     WHERE {$whereClause}";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch()['total'] ?? 0);

        // 2. Ambil data halaman aktif
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT qb.*, 
                       s.name as subject_name, 
                       s.code as subject_code,
                       s.description as subject_description,
                       u.name as creator_name,
                       u.email as creator_email,
                       (SELECT COUNT(*) FROM questions q WHERE q.question_bank_id = qb.id AND q.deleted_at IS NULL) as live_question_count
                FROM {$this->table} qb
                LEFT JOIN subjects s ON qb.subject_id = s.id
                LEFT JOIN users u ON qb.creator_id = u.id
                WHERE {$whereClause}
                ORDER BY qb.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll() ?: [];

        $totalPages = (int)ceil($total / $perPage);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, $totalPages)
        ];
    }

    /**
     * Dapatkan detail lengkap bank soal beserta relasinya
     */
    public function getByIdWithDetails(int $id): ?array
    {
        $sql = "SELECT qb.*, 
                       s.name as subject_name, 
                       s.code as subject_code,
                       s.description as subject_description,
                       u.name as creator_name,
                       u.email as creator_email
                FROM {$this->table} qb
                LEFT JOIN subjects s ON qb.subject_id = s.id
                LEFT JOIN users u ON qb.creator_id = u.id
                WHERE qb.id = :id AND qb.deleted_at IS NULL";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $bank = $stmt->fetch();

        if (!$bank) {
            return null;
        }

        // Ambil ringkasan statistik butir soal
        $statSql = "SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN type = 'multiple_choice' THEN 1 ELSE 0 END) as total_mc,
                        SUM(CASE WHEN type = 'essay' THEN 1 ELSE 0 END) as total_essay,
                        SUM(CASE WHEN type = 'true_false' THEN 1 ELSE 0 END) as total_tf,
                        SUM(CASE WHEN difficulty = 'easy' THEN 1 ELSE 0 END) as total_easy,
                        SUM(CASE WHEN difficulty = 'medium' THEN 1 ELSE 0 END) as total_medium,
                        SUM(CASE WHEN difficulty = 'hard' THEN 1 ELSE 0 END) as total_hard,
                        COALESCE(SUM(weight), 0) as total_weight
                    FROM questions 
                    WHERE question_bank_id = :bank_id AND deleted_at IS NULL";
        $statStmt = $this->db()->prepare($statSql);
        $statStmt->bindValue(':bank_id', $id, \PDO::PARAM_INT);
        $statStmt->execute();
        $stats = $statStmt->fetch() ?: [];

        $bank['stats'] = $stats;
        return $bank;
    }

    /**
     * Hitung ulang total_questions pada bank soal
     */
    public function recalculateTotalQuestions(int $bankId): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM questions WHERE question_bank_id = :bank_id AND deleted_at IS NULL";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':bank_id', $bankId, \PDO::PARAM_INT);
        $stmt->execute();
        $count = (int)($stmt->fetch()['cnt'] ?? 0);

        $upSql = "UPDATE {$this->table} SET total_questions = :cnt, updated_at = NOW() WHERE id = :id";
        $upStmt = $this->db()->prepare($upSql);
        $upStmt->bindValue(':cnt', $count, \PDO::PARAM_INT);
        $upStmt->bindValue(':id', $bankId, \PDO::PARAM_INT);
        $upStmt->execute();

        return $count;
    }

    /**
     * Generate kode bank soal unik secara otomatis
     * Contoh: BNK-MAT10-8492
     */
    public function generateBankCode(string $subjectCode): string
    {
        $cleanPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subjectCode));
        if (empty($cleanPrefix)) {
            $cleanPrefix = 'GEN';
        }

        do {
            $randomDigits = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $candidateCode = "BNK-{$cleanPrefix}-{$randomDigits}";
            $existing = $this->where('code', $candidateCode)->first();
        } while ($existing !== null);

        return $candidateCode;
    }

    /**
     * Ringkasan statistik bank soal untuk seorang guru atau global
     */
     public function getSummaryStats(?int $creatorId = null): array
     {
         $where = "deleted_at IS NULL";
         $params = [];
         if ($creatorId !== null && $creatorId > 0) {
             $where .= " AND creator_id = :cid";
             $params[':cid'] = $creatorId;
         }

         $sql = "SELECT 
                     COUNT(*) as total_banks,
                     COALESCE(SUM(total_questions), 0) as total_questions,
                     SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_banks,
                     SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_banks
                 FROM {$this->table} 
                 WHERE {$where}";

         $stmt = $this->db()->prepare($sql);
         foreach ($params as $k => $v) {
             $stmt->bindValue($k, $v);
         }
         $stmt->execute();
         return $stmt->fetch() ?: [
             'total_banks' => 0,
             'total_questions' => 0,
             'active_banks' => 0,
             'draft_banks' => 0
         ];
     }

    /**
     * Dapatkan semua bank soal aktif milik creator atau global
     */
    public function getAllActiveByCreator(?int $creatorId = null): array
    {
        $where = "qb.deleted_at IS NULL AND qb.status = 'active'";
        $params = [];
        if ($creatorId !== null && $creatorId > 0) {
            $where .= " AND qb.creator_id = :cid";
            $params[':cid'] = $creatorId;
        }

        $sql = "SELECT qb.*, s.name as subject_name, s.code as subject_code
                FROM {$this->table} qb
                LEFT JOIN subjects s ON qb.subject_id = s.id
                WHERE {$where}
                ORDER BY qb.title ASC";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}


