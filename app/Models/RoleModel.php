<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Role Model
 * Manages user roles and permission assignments.
 */
class RoleModel extends BaseModel
{
    protected string $table = 'roles';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'name',
        'display_name',
        'description',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan semua role dengan jumlah pengguna yang terhubung
     */
    public function getRolesWithCounts(): array
    {
        $sql = "
            SELECT 
                r.*,
                COUNT(DISTINCT ur.user_id) as users_count,
                COUNT(DISTINCT rp.permission_id) as permissions_count
            FROM {$this->table} r
            LEFT JOIN user_roles ur ON ur.role_id = r.id
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            GROUP BY r.id
            ORDER BY r.id ASC
        ";
        return $this->query($sql);
    }

    /**
     * Dapatkan ID permissions yang dimiliki role tertentu
     */
    public function getPermissionIds(int|string $roleId): array
    {
        $sql = "SELECT permission_id FROM role_permissions WHERE role_id = :role_id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':role_id', $roleId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Dapatkan daftar permissions yang dimiliki role tertentu
     */
    public function getPermissions(int|string $roleId): array
    {
        $sql = "
            SELECT p.*
            FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
            ORDER BY p.module ASC, p.name ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':role_id', $roleId);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Sinkronisasi permissions untuk role (delete & re-insert)
     */
    public function syncPermissions(int|string $roleId, array $permissionIds): bool
    {
        $this->db()->beginTransaction();
        try {
            $delSql = "DELETE FROM role_permissions WHERE role_id = :role_id";
            $delStmt = $this->db()->prepare($delSql);
            $delStmt->execute([':role_id' => $roleId]);

            if (!empty($permissionIds)) {
                $insSql = "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, NOW())";
                $insStmt = $this->db()->prepare($insSql);
                foreach ($permissionIds as $permId) {
                    $insStmt->execute([':role_id' => $roleId, ':permission_id' => (int)$permId]);
                }
            }

            $this->db()->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            return false;
        }
    }

    /**
     * Ambil seluruh permission di sistem dikelompokkan berdasarkan modul
     */
    public function getAllPermissionsGrouped(): array
    {
        $sql = "SELECT * FROM permissions ORDER BY module ASC, id ASC";
        $all = $this->query($sql);
        $grouped = [];
        foreach ($all as $perm) {
            $grouped[$perm['module']][] = $perm;
        }
        return $grouped;
    }
}
