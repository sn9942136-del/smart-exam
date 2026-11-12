<?php

declare(strict_types=1);

namespace App\Models;

/**
 * OTP Token Model
 * Manages 6-digit numeric OTP generation, expiry check, and single-use verification.
 */
class OtpTokenModel extends BaseModel
{
    protected string $table = 'otp_tokens';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Uses created_at only

    protected array $allowedFields = [
        'user_id',
        'token',
        'type', // 'login', '2fa', 'reset_password', 'exam_verify'
        'is_used',
        'expires_at',
        'created_at'
    ];

    /**
     * Generate 6-digit OTP baru untuk user (default masa berlaku 5 menit)
     */
    public function generateOtp(int|string $userId, string $type = 'reset_password', int $expiresMinutes = 5): array
    {
        // Invalidasi OTP lama yang belum terpakai untuk tipe ini
        $this->invalidateExisting($userId, $type);

        $otp = (string) random_int(100000, 999999);
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresMinutes * 60));

        $this->insert([
            'user_id'    => $userId,
            'token'      => $otp,
            'type'       => $type,
            'is_used'    => 0,
            'expires_at' => $expiresAt,
            'created_at' => $now
        ]);

        return [
            'otp'        => $otp,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Verifikasi OTP yang dimasukkan user
     */
    public function verifyOtp(int|string $userId, string $token, string $type = 'reset_password'): bool
    {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE user_id = :uid 
              AND token = :token 
              AND type = :type 
              AND is_used = 0 
              AND expires_at > NOW() 
            LIMIT 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':token', trim($token));
        $stmt->bindValue(':type', $type);
        $stmt->execute();
        $record = $stmt->fetch();

        if ($record) {
            // Tandai OTP telah digunakan
            $updateSql = "UPDATE {$this->table} SET is_used = 1 WHERE id = :id";
            $updateStmt = $this->db()->prepare($updateSql);
            $updateStmt->bindValue(':id', $record['id']);
            $updateStmt->execute();
            return true;
        }

        return false;
    }

    /**
     * Ambil OTP aktif terakhir untuk keperluan simulasi/demo
     */
    public function getLatestActiveOtp(int|string $userId, string $type = 'reset_password'): ?string
    {
        $sql = "
            SELECT token FROM {$this->table} 
            WHERE user_id = :uid 
              AND type = :type 
              AND is_used = 0 
              AND expires_at > NOW() 
            ORDER BY id DESC LIMIT 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
        $res = $stmt->fetchColumn();
        return $res ? (string)$res : null;
    }

    /**
     * Invalidasi semua OTP sebelumnya untuk user dan tipe tertentu
     */
    public function invalidateExisting(int|string $userId, string $type): bool
    {
        $sql = "UPDATE {$this->table} SET is_used = 1 WHERE user_id = :uid AND type = :type AND is_used = 0";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':type', $type);
        return $stmt->execute();
    }
}
