<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Permission Model
 * Manages atomic permissions across modules.
 */
class PermissionModel extends BaseModel
{
    protected string $table = 'permissions';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'name',
        'display_name',
        'module',
        'description',
        'created_at',
        'updated_at'
    ];

    /**
     * Ambil permissions dikelompokkan berdasarkan modul
     */
    public function getGroupedByModule(): array
    {
        $all = $this->findAll([], 'module ASC, name ASC');
        $grouped = [];
        foreach ($all as $perm) {
            $grouped[$perm['module']][] = $perm;
        }
        return $grouped;
    }
}
