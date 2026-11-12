<?php

declare(strict_types=1);

namespace App\Models;

/**
 * User Session Model
 * Tracks active user sessions, device tokens, IP addresses, and expiry for security management.
 */
class UserSessionModel extends BaseModel
{
    protected string $table = 'user_sessions';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Uses created_at only

    protected array $allowedFields = [
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'device_info',
        'is_active',
        'last_activity',
        'expires_at',
        'created_at'
    ];

    /**
     * Catat sesi login baru ke database
     */
    public function recordSession(int|string $userId, string $token, ?string $ip, ?string $userAgent, int $lifetimeSeconds = 7200): int|string|bool
    {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetimeSeconds);

        return $this->insert([
            'user_id'       => $userId,
            'session_token' => $token,
            'ip_address'    => $ip ?? '127.0.0.1',
            'user_agent'    => substr($userAgent ?? 'Browser', 0, 500),
            'device_info'   => $this->detectDeviceInfo($userAgent),
            'is_active'     => 1,
            'last_activity' => $now,
            'expires_at'    => $expiresAt,
            'created_at'    => $now
        ]);
    }

    /**
     * Cari session aktif berdasarkan session token
     */
    public function findActiveByToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE session_token = :token AND is_active = 1 AND expires_at > NOW() LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Deaktivasi sesi tertentu (Logout)
     */
    public function deactivate(string $token): bool
    {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE session_token = :token";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':token', $token);
        return $stmt->execute();
    }

    /**
     * Deaktivasi seluruh sesi milik user tertentu (Force Logout All Devices)
     */
    public function deactivateAllForUser(int|string $userId): bool
    {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE user_id = :uid";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':uid', $userId);
        return $stmt->execute();
    }

    /**
     * Perbarui waktu aktivitas terakhir
     */
    public function updateActivity(string $token): bool
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE {$this->table} SET last_activity = :now WHERE session_token = :token AND is_active = 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':token', $token);
        return $stmt->execute();
    }

    /**
     * Helper deteksi platform perangkat sederhana
     */
    private function detectDeviceInfo(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device';
        }

        $platform = 'Desktop';
        if (preg_match('/(android|iphone|ipad|mobile)/i', $userAgent)) {
            $platform = 'Mobile/Tablet';
        }

        $browser = 'Browser';
        if (str_contains($userAgent, 'Edg')) {
            $browser = 'Edge';
        } elseif (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        }

        return "{$platform} ({$browser})";
    }
}
