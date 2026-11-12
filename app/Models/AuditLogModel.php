<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Audit Log Model
 */
class AuditLogModel extends BaseModel
{
    protected string $table = 'audit_logs';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Uses created_at only

    protected array $allowedFields = [
        'user_id',
        'ip_address',
        'user_agent',
        'action',
        'module',
        'target_id',
        'before_data',
        'after_data',
        'created_at'
    ];

    /**
     * Catat aksi audit ke database
     */
    public static function record(string $action, string $module, ?string $targetId = null, ?array $before = null, ?array $after = null): bool
    {
        $model = new self();
        return (bool) $model->insert([
            'user_id'     => user_id(),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System',
            'action'      => $action,
            'module'      => $module,
            'target_id'   => $targetId,
            'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_data'  => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'created_at'  => date('Y-m-d H:i:s')
        ]);
    }
}
