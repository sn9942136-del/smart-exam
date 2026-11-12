<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * AI Chat Session Model
 * Mengelola sesi percakapan chatbot AI untuk Guru dan Siswa (termasuk EXAM_MODE).
 */
class AiChatSessionModel extends BaseModel
{
    protected string $table = 'ai_chat_sessions';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'user_id',
        'session_uuid',
        'title',
        'role_context', // 'guru', 'siswa', 'admin', 'question_explainer'
        'is_exam_mode', // 0 or 1
        'exam_session_id',
        'exam_answer_id',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan sesi aktif terbaru milik user berdasarkan role dan exam_mode
     */
    public function getOrCreateActiveSession(int $userId, string $roleContext, bool $isExamMode = false, ?int $examSessionId = null): array
    {
        $examModeInt = $isExamMode ? 1 : 0;
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE user_id = :user_id 
              AND role_context = :role_context 
              AND is_exam_mode = :is_exam_mode
              AND exam_answer_id IS NULL
        ";
        $params = [
            ':user_id'      => $userId,
            ':role_context' => $roleContext,
            ':is_exam_mode' => $examModeInt
        ];

        if ($isExamMode && $examSessionId !== null) {
            $sql .= " AND exam_session_id = :exam_session_id";
            $params[':exam_session_id'] = $examSessionId;
        }

        $sql .= " ORDER BY updated_at DESC LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $session = $stmt->fetch();

        if ($session) {
            return $session;
        }

        // Buat sesi baru jika belum ada
        $uuid = bin2hex(random_bytes(16));
        $title = $isExamMode ? 'Bantuan Sesi Ujian #' . ($examSessionId ?? 0) : 'Percakapan Baru';

        $id = (int)$this->insert([
            'user_id'         => $userId,
            'session_uuid'    => $uuid,
            'title'           => $title,
            'role_context'    => $roleContext,
            'is_exam_mode'    => $examModeInt,
            'exam_session_id' => $examSessionId,
            'exam_answer_id'  => null,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s')
        ]);

        return $this->find($id) ?? [
            'id'              => $id,
            'user_id'         => $userId,
            'session_uuid'    => $uuid,
            'title'           => $title,
            'role_context'    => $roleContext,
            'is_exam_mode'    => $examModeInt,
            'exam_session_id' => $examSessionId,
            'exam_answer_id'  => null
        ];
    }

    /**
     * Dapatkan atau buat sesi percakapan khusus untuk pembahasan satu butir soal ujian
     */
    public function getOrCreateQuestionSession(int $userId, int $answerId, int $examSessionId, string $questionTitle = 'Pembahasan Soal'): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE user_id = :user_id
              AND exam_answer_id = :answer_id
            ORDER BY id DESC LIMIT 1
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':answer_id', $answerId, PDO::PARAM_INT);
        $stmt->execute();
        $session = $stmt->fetch();

        if ($session) {
            return $session;
        }

        $uuid = 'qexp_' . bin2hex(random_bytes(14));
        $id = (int)$this->insert([
            'user_id'         => $userId,
            'session_uuid'    => $uuid,
            'title'           => $questionTitle,
            'role_context'    => 'question_explainer',
            'is_exam_mode'    => 0,
            'exam_session_id' => $examSessionId,
            'exam_answer_id'  => $answerId,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s')
        ]);

        return $this->find($id) ?? [
            'id'              => $id,
            'user_id'         => $userId,
            'session_uuid'    => $uuid,
            'title'           => $questionTitle,
            'role_context'    => 'question_explainer',
            'is_exam_mode'    => 0,
            'exam_session_id' => $examSessionId,
            'exam_answer_id'  => $answerId
        ];
    }

    /**
     * Cari sesi berdasarkan UUID dan User ID
     */
    public function findByUuidAndUser(string $uuid, int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE session_uuid = :uuid AND user_id = :user_id LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':uuid', $uuid);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Dapatkan daftar riwayat sesi percakapan user
     */
    public function getUserSessions(int $userId, string $roleContext, int $limit = 20): array
    {
        $sql = "
            SELECT s.*, 
                   COUNT(m.id) as total_messages,
                   MAX(m.created_at) as last_message_at
            FROM {$this->table} s
            LEFT JOIN ai_chat_messages m ON m.session_id = s.id
            WHERE s.user_id = :user_id 
              AND s.role_context = :role_context
              AND s.exam_answer_id IS NULL
            GROUP BY s.id
            ORDER BY s.updated_at DESC
            LIMIT :limit
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':role_context', $roleContext);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
