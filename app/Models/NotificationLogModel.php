<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Notification Log Model
 * Mengelola riwayat audit pengiriman multi-channel (in-app, whatsapp, email, sms, push)
 * pada tabel `notification_logs`.
 */
class NotificationLogModel extends BaseModel
{
    protected string $table = 'notification_logs';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Menggunakan sent_at dan created_at

    protected array $allowedFields = [
        'notification_id',
        'channel', // 'email', 'sms', 'whatsapp', 'push'
        'recipient',
        'status', // 'queued', 'processing', 'sent', 'failed'
        'claimed_at',
        'claimed_by',
        'attempts',
        'response_payload',
        'sent_at',
        'created_at'
    ];

    /**
     * Catat log pengiriman notifikasi channel eksternal / multi-channel
     *
     * @param int|null $notificationId ID notifikasi in-app terkait (jika ada)
     * @param string $channel Saluran pengiriman ('email', 'sms', 'whatsapp', 'push')
     * @param string $recipient Tujuan (nomor WA, alamat email, dsb)
     * @param string $status Status pengiriman ('queued', 'processing', 'sent', 'failed')
     * @param mixed $responsePayload Data respon atau payload teknis (array/string)
     * @param string|null $sentAt Waktu terkirim nyata (null jika masih antrean/proses)
     * @return int|string|bool
     */
    public function recordLog(
        ?int $notificationId,
        string $channel,
        string $recipient,
        string $status = 'sent',
        mixed $responsePayload = null,
        ?string $sentAt = null
    ): int|string|bool {
        $validChannels = ['email', 'sms', 'whatsapp', 'push'];
        $cleanChannel = in_array($channel, $validChannels, true) ? $channel : 'email';

        $validStatuses = ['queued', 'processing', 'sent', 'failed'];
        $cleanStatus = in_array($status, $validStatuses, true) ? $status : 'sent';

        $payloadStr = is_array($responsePayload) 
            ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE) 
            : (!empty($responsePayload) ? (string)$responsePayload : null);

        $now = date('Y-m-d H:i:s');
        $actualSentAt = ($cleanStatus === 'sent') ? ($sentAt ?? $now) : $sentAt;

        return $this->insert([
            'notification_id'  => $notificationId,
            'channel'          => $cleanChannel,
            'recipient'        => trim($recipient),
            'status'           => $cleanStatus,
            'claimed_at'       => ($cleanStatus === 'processing') ? $now : null,
            'claimed_by'       => null,
            'attempts'         => 0,
            'response_payload' => $payloadStr,
            'sent_at'          => $actualSentAt,
            'created_at'       => $now
        ]);
    }

    /**
     * Masukkan item notifikasi ke dalam antrean (status: queued)
     *
     * @param int|null $notificationId
     * @param string $channel
     * @param string $recipient
     * @param mixed $responsePayload
     * @return int|string|bool
     */
    public function enqueue(
        ?int $notificationId,
        string $channel,
        string $recipient,
        mixed $responsePayload = null
    ): int|string|bool {
        return $this->recordLog($notificationId, $channel, $recipient, 'queued', $responsePayload, null);
    }

    protected ?bool $hasAvailableAtCol = null;

    /**
     * Cek apakah kolom available_at tersedia di tabel database (dynamic schema detection)
     */
    public function hasAvailableAtColumn(): bool
    {
        if ($this->hasAvailableAtCol === null) {
            try {
                $stmt = $this->db()->prepare("SHOW COLUMNS FROM {$this->table} LIKE 'available_at'");
                $stmt->execute();
                $this->hasAvailableAtCol = $stmt->rowCount() > 0;
            } catch (\Throwable) {
                $this->hasAvailableAtCol = false;
            }
        }
        return $this->hasAvailableAtCol;
    }

    /**
     * Ambil antrean notifikasi yang siap diproses worker:
     * 1. Status 'queued' (hanya jika available_at null atau <= NOW() bila kolom tersedia)
     * 2. Status 'processing' yang sudah kedaluwarsa (crash recovery / lease expired)
     *
     * @param int $limit Batas jumlah item antrean yang diambil
     * @param int $leaseTimeoutSeconds Batas waktu kedaluwarsa klaim (detik)
     * @return array
     */
    public function getPendingQueue(int $limit = 10, int $leaseTimeoutSeconds = 300): array
    {
        $queueCondition = $this->hasAvailableAtColumn()
            ? "(status = 'queued' AND (available_at IS NULL OR available_at <= NOW()))"
            : "status = 'queued'";

        $sql = "SELECT * FROM {$this->table} 
                WHERE {$queueCondition}
                   OR (status = 'processing' AND claimed_at < (NOW() - INTERVAL :leaseSec SECOND))
                ORDER BY id ASC 
                LIMIT :limit";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':leaseSec', max(10, $leaseTimeoutSeconds), PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Klaim item antrean secara atomik SEBELUM pengiriman dimulai (PRE-DISPATCH CLAIM)
     * Mengembalikan true HANYA jika worker berhasil mengklaim item (rowCount == 1).
     * Mengembalikan false jika item sudah diambil/diproses worker lain.
     *
     * @param int $id ID baris notification_logs
     * @param string $claimToken Token identitas unik worker pengklaim
     * @param int $leaseTimeoutSeconds Batas waktu sewa klaim (detik)
     * @param int $maxAttempts Batas maksimal percobaan
     * @return bool
     */
    public function claimItem(
        int $id,
        string $claimToken,
        int $leaseTimeoutSeconds = 300,
        int $maxAttempts = 3
    ): bool {
        $sql = "UPDATE {$this->table} 
                SET status = 'processing',
                    claimed_at = NOW(),
                    claimed_by = :claim_token,
                    attempts = attempts + 1
                WHERE id = :id 
                  AND attempts < :max_attempts
                  AND (
                      status = 'queued' 
                      OR (status = 'processing' AND claimed_at < (NOW() - INTERVAL :leaseSec SECOND))
                  )";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':claim_token', $claimToken);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':max_attempts', $maxAttempts, PDO::PARAM_INT);
        $stmt->bindValue(':leaseSec', max(10, $leaseTimeoutSeconds), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Finalisasi status antrean pasca-pengiriman (POST-DISPATCH FINALIZATION)
     * HANYA worker pemilik claimToken sah yang dapat menyelesaikan item ini.
     *
     * @param int $id ID baris notification_logs
     * @param string $claimToken Token identitas worker pengklaim
     * @param string $finalStatus Status akhir ('sent' atau 'failed')
     * @param mixed $responsePayload Data respon / payload teknis
     * @return bool
     */
    public function finalizeClaim(
        int $id,
        string $claimToken,
        string $finalStatus,
        mixed $responsePayload = null
    ): bool {
        $validStatuses = ['sent', 'failed'];
        $cleanStatus = in_array($finalStatus, $validStatuses, true) ? $finalStatus : 'sent';

        $payloadStr = is_array($responsePayload)
            ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE)
            : (!empty($responsePayload) ? (string)$responsePayload : null);

        $now = date('Y-m-d H:i:s');
        $sentAt = ($cleanStatus === 'sent') ? $now : null;

        $sql = "UPDATE {$this->table} 
                SET status = :status,
                    sent_at = :sent_at,
                    claimed_by = NULL,
                    response_payload = :payload 
                WHERE id = :id 
                  AND claimed_by = :claim_token 
                  AND status = 'processing'";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':status', $cleanStatus);
        $stmt->bindValue(':sent_at', $sentAt);
        $stmt->bindValue(':payload', $payloadStr);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':claim_token', $claimToken);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Kembalikan item dari 'processing' ke 'queued' (jika terjadi error internal sebelum kirim)
     */
    public function releaseClaim(int $id, string $claimToken, mixed $responsePayload = null): bool
    {
        $payloadStr = is_array($responsePayload)
            ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE)
            : (!empty($responsePayload) ? (string)$responsePayload : null);

        $sql = "UPDATE {$this->table}
                SET status = 'queued',
                    claimed_at = NULL,
                    claimed_by = NULL,
                    response_payload = :payload
                WHERE id = :id 
                  AND claimed_by = :claim_token 
                  AND status = 'processing'";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':payload', $payloadStr);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':claim_token', $claimToken);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Jadwalkan ulang item untuk retry dengan jeda backoff (exponential backoff)
     *
     * @param int $id ID log notifikasi
     * @param string $claimToken Token pemilik sewa klaim
     * @param int $delaySeconds Jeda waktu dalam detik sebelum item boleh diambil lagi
     * @param mixed $responsePayload Metadata payload teknis dan alasan error
     * @return bool
     */
    public function scheduleRetry(
        int $id,
        string $claimToken,
        int $delaySeconds = 60,
        mixed $responsePayload = null
    ): bool {
        $payloadArr = is_array($responsePayload) ? $responsePayload : [];
        $payloadArr['retry_delay_seconds'] = $delaySeconds;
        $payloadArr['retry_scheduled_at'] = date('Y-m-d H:i:s');
        $payloadArr['available_at_projected'] = date('Y-m-d H:i:s', time() + $delaySeconds);

        $payloadStr = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

        if ($this->hasAvailableAtColumn()) {
            $sql = "UPDATE {$this->table}
                    SET status = 'queued',
                        claimed_at = NULL,
                        claimed_by = NULL,
                        available_at = DATE_ADD(NOW(), INTERVAL :delay SECOND),
                        response_payload = :payload
                    WHERE id = :id 
                      AND claimed_by = :claim_token 
                      AND status = 'processing'";
            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue(':delay', max(1, $delaySeconds), PDO::PARAM_INT);
            $stmt->bindValue(':payload', $payloadStr);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':claim_token', $claimToken);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        }

        // Fallback aman jika migrasi available_at belum diterapkan
        return $this->releaseClaim($id, $claimToken, $payloadArr);
    }

    /**
     * Klaim dan perbarui status antrean secara langsung (Backward compatibility untuk test eksisting)
     */
    public function claimAndFinalize(int $id, string $newStatus, mixed $responsePayload = null): bool
    {
        $validStatuses = ['sent', 'failed'];
        $cleanStatus = in_array($newStatus, $validStatuses, true) ? $newStatus : 'sent';

        $payloadStr = is_array($responsePayload)
            ? json_encode($responsePayload, JSON_UNESCAPED_UNICODE)
            : (!empty($responsePayload) ? (string)$responsePayload : null);

        $now = date('Y-m-d H:i:s');
        $sentAt = ($cleanStatus === 'sent') ? $now : null;

        $sql = "UPDATE {$this->table} 
                SET status = :status, 
                    sent_at = :sent_at, 
                    claimed_by = NULL, 
                    response_payload = :payload 
                WHERE id = :id AND (status = 'queued' OR status = 'processing')";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':status', $cleanStatus);
        $stmt->bindValue(':sent_at', $sentAt);
        $stmt->bindValue(':payload', $payloadStr);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Cek apakah notifikasi terkait sudah memiliki antrean aktif berstatus 'queued'
     *
     * @param int $notificationId ID notifikasi in-app
     * @param string $channel Saluran ('whatsapp', 'email', dsb.)
     * @return bool
     */
    public function hasActiveQueue(int $notificationId, string $channel): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE notification_id = :nid AND channel = :channel AND status IN ('queued', 'processing')";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':nid' => $notificationId, ':channel' => $channel]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Ambil riwayat log pengiriman berdasarkan notification_id
     */
    public function getLogsByNotificationId(int $notificationId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE notification_id = :nid ORDER BY id DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':nid' => $notificationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ambil riwayat log terbaru (untuk audit admin)
     */
    public function getRecentLogs(int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT :limit";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ambil data log terpaginasi dengan filter channel, status, dan pencarian penerima
     *
     * @param array $filters ['channel' => ..., 'status' => ..., 'search' => ...]
     * @param int $page Nomor halaman aktif
     * @param int $perPage Jumlah data per halaman
     * @return array
     */
    public function getPaginatedLogs(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $bindings] = $this->buildFilterConditions($filters);

        $sql = "
            SELECT nl.id, nl.notification_id, nl.channel, nl.recipient, nl.status, 
                   nl.response_payload, nl.sent_at, nl.created_at,
                   n.title AS notification_title,
                   u.name AS user_name
            FROM {$this->table} nl
            LEFT JOIN notifications n ON n.id = nl.notification_id
            LEFT JOIN users u ON u.id = n.user_id
            {$whereSql}
            ORDER BY nl.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Hitung total data log berdasarkan filter untuk kalkulasi paginasi
     *
     * @param array $filters ['channel' => ..., 'status' => ..., 'search' => ...]
     * @return int
     */
    public function countLogs(array $filters = []): int
    {
        [$whereSql, $bindings] = $this->buildFilterConditions($filters);

        $sql = "
            SELECT COUNT(*)
            FROM {$this->table} nl
            LEFT JOIN notifications n ON n.id = nl.notification_id
            LEFT JOIN users u ON u.id = n.user_id
            {$whereSql}
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Helper privat untuk menyusun klausa WHERE dan binding PDO secara aman
     */
    protected function buildFilterConditions(array $filters): array
    {
        $where = [];
        $bindings = [];

        // Filter Channel (email, whatsapp)
        $channel = trim((string)($filters['channel'] ?? ''));
        if ($channel !== '' && in_array($channel, ['email', 'whatsapp', 'sms', 'push'], true)) {
            $where[] = "nl.channel = :channel";
            $bindings[':channel'] = $channel;
        }

        // Filter Status (queued, processing, sent, failed)
        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['queued', 'processing', 'sent', 'failed'], true)) {
            $where[] = "nl.status = :status";
            $bindings[':status'] = $status;
        }

        // Filter Pencarian (recipient, notification title, user name)
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(nl.recipient LIKE :kw1 OR n.title LIKE :kw2 OR u.name LIKE :kw3)";
            $paramKw = '%' . $search . '%';
            $bindings[':kw1'] = $paramKw;
            $bindings[':kw2'] = $paramKw;
            $bindings[':kw3'] = $paramKw;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $bindings];
    }
}
