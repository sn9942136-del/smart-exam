<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI Chat Message Model
 * Mengelola riwayat pesan individual antara User (Guru/Siswa) dan AI Assistant.
 */
class AiChatMessageModel extends BaseModel
{
    protected string $table = 'ai_chat_messages';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'session_id',
        'user_id',
        'sender', // 'user', 'assistant', 'system'
        'message',
        'tokens_used',
        'model_used',
        'is_error',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan N pesan terbaru dalam satu sesi percakapan secara urut kronologis (lama ke baru)
     */
    public function getSessionMessages(int $sessionId, int $limit = 50, ?int $userId = null): array
    {
        $userCondition = ($userId !== null) ? "AND user_id = :user_id" : "";
        $sql = "
            SELECT * FROM (
                SELECT * FROM {$this->table} 
                WHERE session_id = :session_id {$userCondition}
                ORDER BY id DESC 
                LIMIT :limit
            ) sub
            ORDER BY id ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Hapus semua pesan dalam satu sesi percakapan
     */
    public function clearSessionMessages(int $sessionId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE session_id = :session_id AND user_id = :user_id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
