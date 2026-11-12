<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Class Model
 */
class ClassModel extends BaseModel
{
    protected string $table = 'classes';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'academic_year_id',
        'name',
        'grade_level',
        'major',
        'homeroom_teacher_id',
        'created_at',
        'updated_at'
    ];

    /**
     * Ambil data kelas terpaginasi dengan pencarian & filter
     */
    public function getPaginatedClasses(
        string $search = '',
        ?int $yearFilter = null,
        string $gradeFilter = '',
        int $page = 1,
        int $perPage = 10
    ): array {
        $offset = ($page - 1) * $perPage;
        $where = [];
        $bindings = [];

        if (!empty($search)) {
            $where[] = "(c.name LIKE :search OR c.major LIKE :search OR u.name LIKE :search)";
            $bindings[':search'] = "%{$search}%";
        }

        if ($yearFilter !== null && $yearFilter > 0) {
            $where[] = "c.academic_year_id = :year_id";
            $bindings[':year_id'] = $yearFilter;
        }

        if (!empty($gradeFilter)) {
            $where[] = "c.grade_level = :grade_level";
            $bindings[':grade_level'] = $gradeFilter;
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Count total matching
        $countSql = "
            SELECT COUNT(*) as total 
            FROM {$this->table} c
            INNER JOIN academic_years ay ON ay.id = c.academic_year_id
            LEFT JOIN users u ON u.id = c.homeroom_teacher_id
            {$whereSql}
        ";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($bindings as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalItems = (int) ($countStmt->fetchColumn() ?: 0);

        // Fetch paginated rows with student counts
        $sql = "
            SELECT 
                c.*,
                ay.name as academic_year_name,
                u.name as homeroom_teacher_name,
                u.nip_nisn as homeroom_teacher_nip,
                (SELECT COUNT(*) FROM class_students cs INNER JOIN users u2 ON u2.id = cs.student_id WHERE cs.class_id = c.id AND cs.status = 'active' AND u2.deleted_at IS NULL) as students_count
            FROM {$this->table} c
            INNER JOIN academic_years ay ON ay.id = c.academic_year_id
            LEFT JOIN users u ON u.id = c.homeroom_teacher_id
            {$whereSql}
            ORDER BY c.grade_level ASC, c.name ASC
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
     * Dapatkan daftar ringkas kelas untuk dropdown / pilihan form
     */
    public function getDropdownList(): array
    {
        $sql = "SELECT c.id, c.name, c.grade_level, c.major, ay.name as academic_year_name 
                FROM {$this->table} c
                LEFT JOIN academic_years ay ON ay.id = c.academic_year_id
                ORDER BY c.grade_level ASC, c.name ASC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dapatkan detail kelas beserta tahun ajaran, wali kelas, dan jumlah siswa aktif
     */
    public function getClassWithDetails(int|string $id): ?array
    {
        $sql = "
            SELECT 
                c.*,
                ay.name as academic_year_name,
                ay.is_active as academic_year_is_active,
                u.name as homeroom_teacher_name,
                u.nip_nisn as homeroom_teacher_nip,
                u.email as homeroom_teacher_email,
                (SELECT COUNT(*) FROM class_students cs INNER JOIN users u2 ON u2.id = cs.student_id WHERE cs.class_id = c.id AND cs.status = 'active' AND u2.deleted_at IS NULL) as students_count
            FROM {$this->table} c
            LEFT JOIN academic_years ay ON ay.id = c.academic_year_id
            LEFT JOIN users u ON u.id = c.homeroom_teacher_id
            WHERE c.id = :id
            LIMIT 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Dapatkan seluruh siswa aktif yang terdaftar di kelas tertentu
     */
    public function getStudentsInClass(int|string $classId): array
    {
        $sql = "
            SELECT 
                cs.id as enrollment_id,
                cs.class_id,
                cs.student_id,
                cs.academic_year_id,
                cs.roll_number,
                cs.status as enrollment_status,
                cs.created_at as enrolled_at,
                u.id as user_id,
                u.name,
                u.username,
                u.email,
                u.nip_nisn,
                u.gender,
                u.phone,
                u.status as user_status
            FROM class_students cs
            INNER JOIN users u ON u.id = cs.student_id
            WHERE cs.class_id = :class_id 
              AND cs.status = 'active' 
              AND u.deleted_at IS NULL
            ORDER BY 
                CASE WHEN cs.roll_number IS NOT NULL AND cs.roll_number != '' THEN 0 ELSE 1 END ASC,
                CAST(cs.roll_number AS UNSIGNED) ASC, 
                cs.roll_number ASC, 
                u.name ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':class_id' => $classId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dapatkan daftar siswa yang tersedia untuk dimasukkan ke kelas ini
     * (Siswa dengan role peserta_didik yang belum aktif di kelas ini)
     */
    public function getAvailableStudentsForClass(int|string $classId): array
    {
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.username,
                u.email,
                u.nip_nisn,
                u.gender,
                u.status,
                c.id as current_class_id,
                c.name as current_class_name
            FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id AND r.name = 'peserta_didik'
            LEFT JOIN class_students cs ON cs.student_id = u.id AND cs.status = 'active'
            LEFT JOIN classes c ON c.id = cs.class_id
            WHERE u.deleted_at IS NULL
              AND (cs.class_id IS NULL OR cs.class_id != :class_id)
            ORDER BY u.name ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':class_id' => $classId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Masukkan / daftarkan siswa ke kelas
     */
    public function enrollStudent(int|string $classId, int|string $studentId, ?string $rollNumber = null): bool
    {
        $class = $this->find($classId);
        if (!$class) {
            return false;
        }
        $yearId = (int)$class['academic_year_id'];

        // 1. Nonaktifkan penempatan aktif siswa di kelas lain untuk tahun ajaran yang sama (status: transferred)
        $transferSql = "
            UPDATE class_students 
            SET status = 'transferred', updated_at = NOW() 
            WHERE student_id = :student_id 
              AND academic_year_id = :year_id 
              AND class_id != :class_id 
              AND status = 'active'
        ";
        $stmtTransfer = $this->db()->prepare($transferSql);
        $stmtTransfer->execute([
            ':student_id' => $studentId,
            ':year_id'    => $yearId,
            ':class_id'   => $classId,
        ]);

        // 2. Upsert pendaftaran ke kelas tujuan
        $cleanRoll = ($rollNumber !== null && trim($rollNumber) !== '') ? trim($rollNumber) : null;
        $upsertSql = "
            INSERT INTO class_students (class_id, student_id, academic_year_id, roll_number, status, created_at, updated_at)
            VALUES (:class_id, :student_id, :year_id, :roll_number, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                roll_number = COALESCE(:roll_number_update, roll_number),
                status = 'active',
                updated_at = NOW()
        ";
        $stmt = $this->db()->prepare($upsertSql);
        return $stmt->execute([
            ':class_id'            => $classId,
            ':student_id'          => $studentId,
            ':year_id'             => $yearId,
            ':roll_number'         => $cleanRoll,
            ':roll_number_update'  => $cleanRoll,
        ]);
    }

    /**
     * Keluarkan siswa dari kelas
     */
    public function removeStudentFromClass(int|string $classId, int|string $studentId): bool
    {
        $sql = "DELETE FROM class_students WHERE class_id = :class_id AND student_id = :student_id";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            ':class_id'   => $classId,
            ':student_id' => $studentId,
        ]);
    }

    /**
     * Dapatkan ID kelas aktif siswa saat ini
     */
    public function getStudentActiveClassId(int|string $studentId): ?int
    {
        $sql = "SELECT class_id FROM class_students WHERE student_id = :student_id AND status = 'active' ORDER BY id DESC LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':student_id' => $studentId]);
        $classId = $stmt->fetchColumn();
        return $classId ? (int)$classId : null;
    }

    /**
     * Sinkronisasi penempatan kelas siswa (bisa dipanggil dari formulir user create/edit)
     */
    public function syncStudentClass(int|string $studentId, ?int $classId, ?string $rollNumber = null): bool
    {
        if (empty($classId)) {
            // Hapus atau nonaktifkan kelas aktif siswa
            $sql = "DELETE FROM class_students WHERE student_id = :student_id AND status = 'active'";
            $stmt = $this->db()->prepare($sql);
            return $stmt->execute([':student_id' => $studentId]);
        }

        return $this->enrollStudent($classId, $studentId, $rollNumber);
    }
}

