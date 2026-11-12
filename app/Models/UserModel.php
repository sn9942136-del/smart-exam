<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * User Model
 * Handles user identity, credentials lookup, roles, permissions, searching, and pagination.
 */
class UserModel extends BaseModel
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'name',
        'email',
        'username',
        'password',
        'nip_nisn',
        'phone',
        'avatar',
        'gender',
        'status', // 'active', 'inactive', 'banned'
        'two_factor_enabled',
        'last_login_at',
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Cari user berdasarkan email, username, atau NIP/NISN aktif beserta alias akun demo
     */
    public function findByCredentials(string $login): ?array
    {
        $clean = trim($login);
        if (empty($clean)) {
            return null;
        }

        // 1. Pencarian langsung case-insensitive berdasarkan email, username, NIP/NISN, atau nomor telepon
        $cleanDigits = preg_replace('/[^0-9]/', '', $clean);
        $normPhone = '';
        if (strlen($cleanDigits) >= 8) {
            $normPhone = str_starts_with($cleanDigits, '0') ? ('62' . substr($cleanDigits, 1)) : $cleanDigits;
        }

        $sql = "
            SELECT * FROM {$this->table} 
            WHERE (LOWER(email) = LOWER(:login1) 
               OR LOWER(username) = LOWER(:login2)
               OR nip_nisn = :login3
               OR phone = :login4
               " . (!empty($normPhone) ? "OR phone = :login5 OR phone = :login6" : "") . ") 
              AND deleted_at IS NULL 
            LIMIT 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':login1', $clean);
        $stmt->bindValue(':login2', $clean);
        $stmt->bindValue(':login3', $clean);
        $stmt->bindValue(':login4', $clean);
        if (!empty($normPhone)) {
            $stmt->bindValue(':login5', $normPhone);
            $stmt->bindValue(':login6', '0' . substr($normPhone, 2));
        }
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            return $user;
        }

        // 2. Pencarian fallback alias akun demo (kompatibilitas akun demo bawaan dan akun pengguna sekolah)
        $lower = strtolower($clean);
        $demoAliases = [
            'admin@example.com'      => ['email' => 'admin@gmail.com', 'username' => 'admin', 'id' => 2],
            'admin'                  => ['email' => 'admin@gmail.com', 'username' => 'admin', 'id' => 2],
            'guru@example.com'       => ['email' => 'Budisantoso@gmail.com', 'username' => 'Budi Santoso', 'id' => 3],
            'guru'                   => ['email' => 'Budisantoso@gmail.com', 'username' => 'Budi Santoso', 'id' => 3],
            'guru_budi'              => ['email' => 'Budisantoso@gmail.com', 'username' => 'Budi Santoso', 'id' => 3],
            'siswa@example.com'      => ['email' => 'Ahmadfauzi@gmail.com', 'username' => 'Ahmad Fauzi', 'id' => 4],
            'siswa'                  => ['email' => 'Ahmadfauzi@gmail.com', 'username' => 'Ahmad Fauzi', 'id' => 4],
            'siswa_fauzi'            => ['email' => 'Ahmadfauzi@gmail.com', 'username' => 'Ahmad Fauzi', 'id' => 4],
            'superadmin@example.com' => ['email' => 'superadmin@gmail.com', 'username' => 'superadmin', 'id' => 1],
            'superadmin'             => ['email' => 'superadmin@gmail.com', 'username' => 'superadmin', 'id' => 1],
        ];

        if (isset($demoAliases[$lower])) {
            $target = $demoAliases[$lower];
            $sql = "
                SELECT * FROM {$this->table} 
                WHERE (id = :id OR LOWER(email) = LOWER(:email) OR LOWER(username) = LOWER(:username)) 
                  AND deleted_at IS NULL 
                LIMIT 1
            ";
            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue(':id', $target['id']);
            $stmt->bindValue(':email', $target['email']);
            $stmt->bindValue(':username', $target['username']);
            $stmt->execute();
            $user = $stmt->fetch();
            return $user ?: null;
        }

        return null;
    }

    /**
     * Cari user berdasarkan email
     */
    public function findByEmail(string $email, ?int $excludeId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email AND deleted_at IS NULL";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':email', trim($email));
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId);
        }
        $stmt->execute();
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Cari user berdasarkan username
     */
    public function findByUsername(string $username, ?int $excludeId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE username = :username AND deleted_at IS NULL";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':username', trim($username));
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId);
        }
        $stmt->execute();
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Cari user berdasarkan NIP / NISN
     */
    public function findByNipNisn(string $nipNisn, ?int $excludeId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE nip_nisn = :nip_nisn AND deleted_at IS NULL";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':nip_nisn', trim($nipNisn));
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId);
        }
        $stmt->execute();
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Ambil daftar user terpaginasi dengan pencarian dan filter role/status
     */
    public function getPaginatedUsers(
        string $search = '',
        string $roleFilter = '',
        string $statusFilter = '',
        int $page = 1,
        int $perPage = 10,
        string $sortBy = 'name',
        string $sortOrder = 'ASC'
    ): array {
        $offset = ($page - 1) * $perPage;
        $where = ["u.deleted_at IS NULL"];
        $bindings = [];

        if (!empty($search)) {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search OR u.username LIKE :search OR u.nip_nisn LIKE :search)";
            $bindings[':search'] = "%{$search}%";
        }

        if (!empty($statusFilter)) {
            $where[] = "u.status = :status";
            $bindings[':status'] = $statusFilter;
        }

        if (!empty($roleFilter)) {
            $where[] = "r.name = :role";
            $bindings[':role'] = $roleFilter;
        }

        $whereSql = implode(" AND ", $where);

        // Count total matching
        $countSql = "
            SELECT COUNT(DISTINCT u.id) as total
            FROM {$this->table} u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE {$whereSql}
        ";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($bindings as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalItems = (int) ($countStmt->fetchColumn() ?: 0);

        // Whitelist kolom pengurutan
        $allowedSort = [
            'name'       => 'u.name',
            'email'      => 'u.email',
            'username'   => 'u.username',
            'nip_nisn'   => 'u.nip_nisn',
            'status'     => 'u.status',
            'id'         => 'u.id',
            'created_at' => 'u.created_at'
        ];
        $orderCol = $allowedSort[strtolower($sortBy)] ?? 'u.name';
        $orderDir = (strtoupper($sortOrder) === 'DESC') ? 'DESC' : 'ASC';

        // Fetch paginated rows (diurutkan berdasarkan abjad nama secara default agar rapi)
        $sql = "
            SELECT 
                u.*,
                GROUP_CONCAT(r.name) as role_names,
                GROUP_CONCAT(r.display_name) as role_display_names
            FROM {$this->table} u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE {$whereSql}
            GROUP BY u.id
            ORDER BY {$orderCol} {$orderDir}, u.id ASC
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
     * Dapatkan daftar guru aktif (untuk wali kelas & pengampu mata pelajaran)
     */
    public function getActiveTeachers(): array
    {
        $sql = "
            SELECT u.id, u.name, u.email, u.nip_nisn
            FROM {$this->table} u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'guru' AND u.status = 'active' AND u.deleted_at IS NULL
            ORDER BY u.name ASC
        ";
        return $this->query($sql);
    }

    /**
     * Perbarui kata sandi user dengan hash baru
     */
    public function updatePassword(int|string $userId, string $newPasswordHash): bool
    {
        $sql = "UPDATE {$this->table} SET password = :password, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':password', $newPasswordHash);
        $stmt->bindValue(':id', $userId);
        return $stmt->execute();
    }

    /**
     * Perbarui waktu login terakhir
     */
    public function updateLastLogin(int|string $userId): bool
    {
        $sql = "UPDATE {$this->table} SET last_login_at = NOW() WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $userId);
        return $stmt->execute();
    }

    /**
     * Ambil role utama milik user
     */
    public function getPrimaryRole(int|string $userId): string
    {
        $roles = $this->getRoles($userId);
        if (!empty($roles)) {
            return $roles[0]['name'];
        }
        return 'peserta_didik';
    }

    /**
     * Ambil seluruh role milik user
     */
    public function getRoles(int|string $userId): array
    {
        $sql = "
            SELECT r.* 
            FROM roles r
            INNER JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
            ORDER BY r.id ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Ambil seluruh permission yang dimiliki user
     */
    public function getPermissions(int|string $userId): array
    {
        $sql = "
            SELECT DISTINCT p.name
            FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            INNER JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = :user_id
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Hubungkan user dengan role (sync / replace)
     */
    public function syncRole(int|string $userId, int|string $roleId): bool
    {
        // Hapus role lama
        $delSql = "DELETE FROM user_roles WHERE user_id = :user_id";
        $delStmt = $this->db()->prepare($delSql);
        $delStmt->execute([':user_id' => $userId]);

        // Insert role baru
        $insSql = "INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, NOW())";
        $insStmt = $this->db()->prepare($insSql);
        return $insStmt->execute([':user_id' => $userId, ':role_id' => $roleId]);
    }

    /**
     * Hubungkan user dengan role (insert ignore)
     */
    public function assignRole(int|string $userId, int|string $roleId): bool
    {
        $sql = "
            INSERT INTO user_roles (user_id, role_id, created_at) 
            VALUES (:user_id, :role_id, NOW()) 
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)
        ";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
    }

    /**
     * Soft delete user
     */
    public function softDelete(int|string $id): bool
    {
        $sql = "UPDATE {$this->table} SET deleted_at = NOW(), status = 'inactive' WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
