<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Subject Model (Mata Pelajaran)
 */
class SubjectModel extends BaseModel
{
    protected string $table = 'subjects';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'code',
        'name',
        'description',
        'created_at',
        'updated_at'
    ];

    /**
     * Cari mata pelajaran berdasarkan kode
     */
    public function findByCode(string $code, ?int $excludeId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE code = :code";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':code', trim($code));
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Ambil data mata pelajaran terpaginasi dengan pencarian
     */
    public function getPaginatedSubjects(string $search = '', int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $bindings = [];

        if (!empty($search)) {
            $where[] = "(s.code LIKE :search OR s.name LIKE :search OR s.description LIKE :search)";
            $bindings[':search'] = "%{$search}%";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Count total matching
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} s {$whereSql}";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($bindings as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalItems = (int) ($countStmt->fetchColumn() ?: 0);

        // Fetch paginated rows with question banks count
        $sql = "
            SELECT 
                s.*,
                (SELECT COUNT(*) FROM question_banks qb WHERE qb.subject_id = s.id AND qb.deleted_at IS NULL) as question_banks_count,
                (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.subject_id = s.id) as teachers_count
            FROM {$this->table} s
            {$whereSql}
            ORDER BY s.code ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        $totalPages = (int) ceil($totalItems / max($perPage, 1));

        return [
            'data'        => $rows,
            'totalItems'  => $totalItems,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'totalPages'  => max($totalPages, 1)
        ];
    }

    /**
     * Dapatkan semua mata pelajaran aktif
     */
    public function getAllActive(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Alias method untuk mendapatkan seluruh mata pelajaran aktif
     */
    public function getActiveSubjects(): array
    {
        return $this->getAllActive();
    }
}

