<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Academic Year Model
 */
class AcademicYearModel extends BaseModel
{
    protected string $table = 'academic_years';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan tahun ajaran aktif
     */
    public function getActive(): ?array
    {
        return $this->where('is_active', 1)->first();
    }

    /**
     * Set tahun ajaran tertentu menjadi aktif dan menonaktifkan tahun lainnya
     */
    public function setActive(int|string $id): bool
    {
        $this->db()->beginTransaction();
        try {
            // Nonaktifkan semua tahun ajaran
            $this->db()->exec("UPDATE {$this->table} SET is_active = 0");

            // Aktifkan tahun ajaran terpilih
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
     * Ambil data tahun ajaran terpaginasi dengan pencarian
     */
    public function getPaginatedYears(string $search = '', int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $bindings = [];

        if (!empty($search)) {
            $where[] = "ay.name LIKE :search";
            $bindings[':search'] = "%{$search}%";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Count total matching
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} ay {$whereSql}";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($bindings as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalItems = (int) ($countStmt->fetchColumn() ?: 0);

        // Fetch paginated rows with count of semesters and classes
        $sql = "
            SELECT 
                ay.*,
                (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id = ay.id) as semesters_count,
                (SELECT COUNT(*) FROM classes c WHERE c.academic_year_id = ay.id) as classes_count
            FROM {$this->table} ay
            {$whereSql}
            ORDER BY ay.start_date DESC
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
