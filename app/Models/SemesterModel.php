<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Semester Model
 */
class SemesterModel extends BaseModel
{
    protected string $table = 'semesters';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'academic_year_id',
        'name',
        'code',
        'is_active',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan semester aktif saat ini
     */
    public function getActive(): ?array
    {
        $sql = "
            SELECT s.*, ay.name as academic_year_name
            FROM {$this->table} s
            INNER JOIN academic_years ay ON ay.id = s.academic_year_id
            WHERE s.is_active = 1
            LIMIT 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Set semester tertentu menjadi aktif dan nonaktifkan yang lain
     */
    public function setActive(int|string $id): bool
    {
        $this->db()->beginTransaction();
        try {
            $this->db()->exec("UPDATE {$this->table} SET is_active = 0");

            $stmt = $this->db()->prepare("UPDATE {$this->table} SET is_active = 1, updated_at = NOW() WHERE id = :id");
            $stmt->bindValue(':id', $id);
            $stmt->execute();

            $this->db()->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            return false;
        }
    }

    /**
     * Ambil data semester terpaginasi dengan pencarian & filter tahun ajaran
     */
    public function getPaginatedSemesters(string $search = '', ?int $yearFilter = null, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $bindings = [];

        if (!empty($search)) {
            $where[] = "(s.name LIKE :search OR s.code LIKE :search OR ay.name LIKE :search)";
            $bindings[':search'] = "%{$search}%";
        }

        if ($yearFilter !== null && $yearFilter > 0) {
            $where[] = "s.academic_year_id = :year_id";
            $bindings[':year_id'] = $yearFilter;
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Count total matching
        $countSql = "
            SELECT COUNT(*) as total 
            FROM {$this->table} s
            INNER JOIN academic_years ay ON ay.id = s.academic_year_id
            {$whereSql}
        ";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($bindings as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalItems = (int) ($countStmt->fetchColumn() ?: 0);

        // Fetch paginated rows with academic year name
        $sql = "
            SELECT 
                s.*,
                ay.name as academic_year_name,
                ay.is_active as academic_year_is_active
            FROM {$this->table} s
            INNER JOIN academic_years ay ON ay.id = s.academic_year_id
            {$whereSql}
            ORDER BY s.id DESC
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
}
