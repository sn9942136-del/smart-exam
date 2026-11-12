<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Notification Model
 * Mengelola persistensi data notifikasi pengguna di tabel `notifications`.
 */
class NotificationModel extends BaseModel
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Hanya menggunakan created_at di skema tabel

    protected array $allowedFields = [
        'user_id',
        'title',
        'message',
        'type', // 'info', 'warning', 'danger', 'success'
        'action_url',
        'is_read', // 0 atau 1
        'read_at',
        'created_at'
    ];

    /**
     * Mengambil daftar notifikasi terpaginasi untuk pengguna tertentu dengan filter opsional
     *
     * @param int $userId ID pengguna pemilik notifikasi
     * @param int $limit Batas record per halaman
     * @param int $offset Offset data
     * @param string|null $type Filter tipe ('info', 'warning', 'danger', 'success')
     * @param bool|null $onlyUnread Hanya yang belum dibaca (true) atau semua (null/false)
     * @return array
     */
    public function getUserNotifications(
        int $userId,
        int $limit = 15,
        int $offset = 0,
        ?string $type = null,
        ?bool $onlyUnread = null,
        ?int $isRead = null
    ): array {
        $where = ["user_id = :uid"];
        $bindings = [':uid' => $userId];

        if (!empty($type)) {
            $where[] = "type = :type";
            $bindings[':type'] = $type;
        }

        if ($onlyUnread === true || $isRead === 0) {
            $where[] = "is_read = 0";
        } elseif ($isRead === 1) {
            $where[] = "is_read = 1";
        }

        $whereSql = implode(" AND ", $where);
        $sql = "
            SELECT id, user_id, title, message, type, action_url, is_read, read_at, created_at
            FROM {$this->table}
            WHERE {$whereSql}
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Hitung total notifikasi belum dibaca (unread count) untuk badge
     */
    public function getUnreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE user_id = :uid AND is_read = 0";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Hitung total semua notifikasi untuk pagination
     */
    public function countUserNotifications(
        int $userId,
        ?string $type = null,
        ?bool $onlyUnread = null,
        ?int $isRead = null
    ): int {
        $where = ["user_id = :uid"];
        $bindings = [':uid' => $userId];

        if (!empty($type)) {
            $where[] = "type = :type";
            $bindings[':type'] = $type;
        }

        if ($onlyUnread === true || $isRead === 0) {
            $where[] = "is_read = 0";
        } elseif ($isRead === 1) {
            $where[] = "is_read = 1";
        }

        $whereSql = implode(" AND ", $where);
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereSql}";
        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil notifikasi terbaru untuk dropdown navbar (default 5 item)
     */
    public function getLatestForDropdown(int $userId, int $limit = 5): array
    {
        return $this->getUserNotifications($userId, $limit, 0);
    }

    /**
     * Tandai notifikasi spesifik sebagai sudah dibaca (Scoping Ketat ke user_id)
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET is_read = 1, read_at = :read_at
            WHERE id = :id AND user_id = :uid AND is_read = 0
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':read_at' => date('Y-m-d H:i:s'),
            ':id'      => $notificationId,
            ':uid'     => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Tandai SEMUA notifikasi pengguna sebagai sudah dibaca
     */
    public function markAllAsRead(int $userId): int
    {
        $sql = "
            UPDATE {$this->table}
            SET is_read = 1, read_at = :read_at
            WHERE user_id = :uid AND is_read = 0
        ";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':read_at' => date('Y-m-d H:i:s'),
            ':uid'     => $userId
        ]);

        return $stmt->rowCount();
    }

    /**
     * Hapus notifikasi dengan scoping ketat ke user_id (mencegah IDOR)
     */
    public function deleteForUser(int $notificationId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id AND user_id = :uid";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':id'  => $notificationId,
            ':uid' => $userId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Tambahkan notifikasi baru ke database
     */
    public function createNotification(array $data): int|string|bool
    {
        // Validasi tipe enum
        $validTypes = ['info', 'warning', 'danger', 'success'];
        $type = in_array($data['type'] ?? '', $validTypes, true) ? $data['type'] : 'info';

        $payload = [
            'user_id'    => (int) ($data['user_id'] ?? 0),
            'title'      => trim((string) ($data['title'] ?? '')),
            'message'    => trim((string) ($data['message'] ?? '')),
            'type'       => $type,
            'action_url' => !empty($data['action_url']) ? trim((string) $data['action_url']) : null,
            'is_read'    => 0,
            'read_at'    => null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ];

        if ($payload['user_id'] <= 0 || empty($payload['title']) || empty($payload['message'])) {
            return false;
        }

        return $this->insert($payload);
    }

    /**
     * Tambahkan notifikasi batch (banyak user sekaligus dalam satu transaksi)
     *
     * @param array $userIds Daftar ID pengguna
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan notifikasi
     * @param string $type Tipe notifikasi ('info', 'warning', 'danger', 'success')
     * @param string|null $actionUrl URL aksi
     * @return int Jumlah notifikasi yang berhasil dibuat
     */
    public function createBatchNotifications(
        array $userIds,
        string $title,
        string $message,
        string $type = 'info',
        ?string $actionUrl = null
    ): int {
        $uniqueIds = array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0));
        if (empty($uniqueIds) || empty($title) || empty($message)) {
            return 0;
        }

        $validTypes = ['info', 'warning', 'danger', 'success'];
        $cleanType = in_array($type, $validTypes, true) ? $type : 'info';
        $now = date('Y-m-d H:i:s');

        $db = $this->db();
        $isExistingTransaction = $db->inTransaction();

        if (!$isExistingTransaction) {
            $db->beginTransaction();
        }

        $count = 0;
        try {
            $sql = "
                INSERT INTO {$this->table} (user_id, title, message, type, action_url, is_read, read_at, created_at)
                VALUES (:user_id, :title, :message, :type, :action_url, 0, NULL, :created_at)
            ";
            $stmt = $db->prepare($sql);

            foreach ($uniqueIds as $uid) {
                $stmt->execute([
                    ':user_id'    => $uid,
                    ':title'      => $title,
                    ':message'    => $message,
                    ':type'       => $cleanType,
                    ':action_url' => $actionUrl,
                    ':created_at' => $now
                ]);
                $count++;
            }

            if (!$isExistingTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$isExistingTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $count;
    }

    /**
     * Periksa apakah pengguna telah menerima notifikasi tertentu (Idempotensi Guard)
     * Mendukung opsi exact match dan window waktu untuk mencegah false deduplication.
     */
    public function hasNotification(
        int $userId,
        string $title,
        string $messageKeyword,
        bool $exactMatch = false,
        ?int $withinSeconds = null
    ): bool {
        $msgCondition = $exactMatch ? "message = :kw" : "message LIKE :kw";
        $timeCondition = ($withinSeconds !== null && $withinSeconds > 0)
            ? "AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$withinSeconds . " SECOND)"
            : "";

        $sql = "SELECT id FROM {$this->table} 
                WHERE user_id = :uid 
                  AND title = :title 
                  AND {$msgCondition} 
                  {$timeCondition}
                LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':kw', $exactMatch ? $messageKeyword : ('%' . $messageKeyword . '%'));
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Dapatkan daftar user_id yang telah menerima notifikasi dengan judul & keyword tertentu (Bulk Idempotensi Guard)
     * Mendukung opsi exact match dan window waktu untuk mencegah false deduplication.
     */
    public function getUserIdsWithNotification(
        array $userIds,
        string $title,
        string $messageKeyword,
        bool $exactMatch = false,
        ?int $withinSeconds = null
    ): array {
        $uniqueIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0)));
        if (empty($uniqueIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $msgCondition = $exactMatch ? "message = ?" : "message LIKE ?";
        $timeCondition = ($withinSeconds !== null && $withinSeconds > 0)
            ? "AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$withinSeconds . " SECOND)"
            : "";

        $sql = "SELECT DISTINCT user_id FROM {$this->table}
                WHERE user_id IN ({$placeholders})
                  AND title = ?
                  AND {$msgCondition}
                  {$timeCondition}";
        $stmt = $this->db()->prepare($sql);
        foreach ($uniqueIds as $idx => $uid) {
            $stmt->bindValue($idx + 1, $uid, PDO::PARAM_INT);
        }
        $stmt->bindValue(count($uniqueIds) + 1, $title);
        $stmt->bindValue(count($uniqueIds) + 2, $exactMatch ? $messageKeyword : ('%' . $messageKeyword . '%'));
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_map('intval', $rows);
    }
}


