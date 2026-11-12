<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Token Model
 * Mengelola perilisan token ujian terpusat 6-karakter alfanumerik, masa berlaku, dan regenerasi token.
 */
class ExamTokenModel extends BaseModel
{
    protected string $table = 'exam_tokens';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_id',
        'token',
        'valid_from',
        'valid_until',
        'is_active',
        'auto_refresh_minutes',
        'created_at',
        'updated_at'
    ];

    /**
     * Generate token alfanumerik 6-karakter acak unik
     * (Menghilangkan karakter ambigu 0/O dan 1/I untuk kemudahan input peserta)
     */
    public function generateTokenCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($alphabet) - 1;

        do {
            $token = '';
            for ($i = 0; $i < 6; $i++) {
                $token .= $alphabet[random_int(0, $maxIndex)];
            }
            $existing = $this->where('token', $token)->where('is_active', 1)->first();
        } while ($existing !== null);

        return $token;
    }

    /**
     * Dapatkan token aktif saat ini untuk ujian
     */
    public function getActiveToken(int $examId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE exam_id = :exam_id AND is_active = 1 
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Rilis / Regenerasi token baru untuk ujian
     */
    public function releaseToken(int $examId, int $validityHours = 24, int $autoRefresh = 15): array
    {
        // Nonaktifkan token lama
        $deactSql = "UPDATE {$this->table} SET is_active = 0, updated_at = NOW() WHERE exam_id = :exam_id";
        $deactStmt = $this->db()->prepare($deactSql);
        $deactStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $deactStmt->execute();

        $newTokenCode = $this->generateTokenCode();
        $now = date('Y-m-d H:i:s');
        $validUntil = date('Y-m-d H:i:s', strtotime("+{$validityHours} hours"));

        $id = $this->insert([
            'exam_id'              => $examId,
            'token'                => $newTokenCode,
            'valid_from'           => $now,
            'valid_until'          => $validUntil,
            'is_active'            => 1,
            'auto_refresh_minutes' => $autoRefresh,
            'created_at'           => $now,
            'updated_at'           => $now
        ]);

        return [
            'id'          => (int)$id,
            'exam_id'     => $examId,
            'token'       => $newTokenCode,
            'valid_from'  => $now,
            'valid_until' => $validUntil,
            'is_active'   => 1
        ];
    }

    /**
     * Toggle status aktif/nonaktif token
     */
    public function toggleTokenStatus(int $tokenId): bool
    {
        $token = $this->find($tokenId);
        if (!$token) {
            return false;
        }

        $newStatus = empty($token['is_active']) ? 1 : 0;
        return (bool)$this->update($tokenId, [
            'is_active'  => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Verifikasi keabsahan token ujian
     */
    public function validateToken(int $examId, string $token): bool
    {
        $cleanToken = strtoupper(trim($token));
        if ($cleanToken === '') {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $sql = "SELECT id FROM {$this->table} 
                WHERE exam_id = :exam_id 
                  AND UPPER(token) = :token 
                  AND is_active = 1 
                  AND valid_from <= :now1 
                  AND valid_until >= :now2 
                LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindValue(':token', $cleanToken);
        $stmt->bindValue(':now1', $now);
        $stmt->bindValue(':now2', $now);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();
    }
}
