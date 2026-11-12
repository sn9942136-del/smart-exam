<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Password Reset Model
 * Manages secure password reset tokens and expiration windows.
 */
class PasswordResetModel extends BaseModel
{
    protected string $table = 'password_resets';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Uses created_at only

    protected array $allowedFields = [
        'email',
        'token',
        'expires_at',
        'created_at'
    ];

    /**
     * Buat token reset password baru (masa berlaku 60 menit)
     */
    public function createToken(string $email, int $expiresMinutes = 60): string
    {
        // Bersihkan token lama untuk email ini
        $this->deleteByEmail($email);

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresMinutes * 60));

        $this->insert([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => $now
        ]);

        return $token;
    }

    /**
     * Cari dan validasi token reset password
     */
    public function findValid(string $email, string $token): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email AND token = :token AND expires_at > NOW() LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Hapus token reset untuk email tertentu
     */
    public function deleteByEmail(string $email): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE email = :email";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':email', $email);
        return $stmt->execute();
    }
}
