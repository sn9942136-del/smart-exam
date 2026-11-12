<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Chat Message Model (UC-22b)
 * Mengelola pesan komunikasi dua arah antara Pengawas/Guru dan Peserta Ujian.
 */
class ChatMessageModel extends BaseModel
{
    protected string $table = 'chat_messages';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Only created_at in schema

    protected array $allowedFields = [
        'sender_id',
        'receiver_id',
        'exam_session_id',
        'channel', // 'broadcast', 'proctor_to_student', 'tech_support'
        'message',
        'attachment_url',
        'is_read',
        'created_at'
    ];

    /**
     * Ambil utas percakapan untuk sesi ujian tertentu
     */
    public function getThread(int $examSessionId): array
    {
        $sql = "
            SELECT m.*, u.name as sender_name, u.username as sender_username, r.name as sender_role
            FROM {$this->table} m
            INNER JOIN users u ON u.id = m.sender_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE m.exam_session_id = :sid
            ORDER BY m.id ASC
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':sid', $examSessionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Tandai semua pesan sebagai telah dibaca
     */
    public function markAsRead(int $examSessionId, int $readerId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET is_read = 1 
                WHERE exam_session_id = :sid 
                  AND (receiver_id = :rid OR receiver_id IS NULL) 
                  AND sender_id != :rid";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':sid', $examSessionId, PDO::PARAM_INT);
        $stmt->bindValue(':rid', $readerId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
