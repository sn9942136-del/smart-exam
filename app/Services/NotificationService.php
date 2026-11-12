<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassModel;
use App\Models\NotificationLogModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use PDO;

/**
 * Notification Service
 * Mengorkestrasi pembuatan, distribusi, dan audit notifikasi pengguna lintas role
 * (Siswa, Guru, Administrator) dengan arsitektur multi-channel yang siap diperluas
 * (In-App, WhatsApp, Email).
 */
class NotificationService
{
    // Konstanta Saluran Notifikasi (Multi-Channel Architecture)
    public const CHANNEL_IN_APP   = 'in_app';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL    = 'email';

    // Tipe Notifikasi Standar
    public const TYPE_INFO    = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_DANGER  = 'danger';
    public const TYPE_SUCCESS = 'success';

    protected NotificationModel $notificationModel;
    protected NotificationLogModel $logModel;
    protected UserModel $userModel;
    protected ClassModel $classModel;
    protected NotificationQueueService $queueService;

    /**
     * Konfigurasi status aktif channel eksternal.
     * Sesuai ketentuan: WhatsApp & Email dinonaktifkan secara default untuk pengiriman riil
     * dan hanya mencatat kesiapan arsitektur/log simulasi tanpa menghubungkan ke provider pihak ketiga.
     */
    protected bool $whatsAppEnabled = false;
    protected bool $emailEnabled = false;
    protected bool $autoDispatch = true;

    public function __construct(
        ?NotificationModel $notificationModel = null,
        ?NotificationLogModel $logModel = null,
        ?UserModel $userModel = null,
        ?ClassModel $classModel = null,
        ?NotificationQueueService $queueService = null
    ) {
        $this->notificationModel = $notificationModel ?? new NotificationModel();
        $this->logModel = $logModel ?? new NotificationLogModel();
        $this->userModel = $userModel ?? new UserModel();
        $this->classModel = $classModel ?? new ClassModel();
        $this->queueService = $queueService ?? new NotificationQueueService($this->logModel);
    }

    /**
     * Dapatkan instance NotificationQueueService
     */
    public function getQueueService(): NotificationQueueService
    {
        return $this->queueService;
    }

    /**
     * Setter auto-dispatch pengiriman otomatis
     */
    public function setAutoDispatch(bool $auto): self
    {
        $this->autoDispatch = $auto;
        return $this;
    }

    /**
     * Jalankan pemrosesan batch antrean simulasi notifikasi
     */
    public function processQueue(int $limit = 10): array
    {
        return $this->queueService->processBatch($limit);
    }

    /**
     * =========================================================================
     * DISTRIBUSI NOTIFIKASI BERDASARKAN TARGET PENGGUNA & ROLE
     * =========================================================================
     */

    /**
     * Kirim notifikasi ke seorang pengguna tertentu dengan proteksi role-scoping
     *
     * @param int $userId ID pengguna penerima
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan notifikasi
     * @param string $type Tipe notifikasi ('info', 'warning', 'danger', 'success')
     * @param string|null $actionUrl URL tautan aksi
     * @param array $channels Saluran yang dituju (default: in_app)
     * @return array Status pengiriman per channel ['in_app' => ..., 'whatsapp' => ..., 'email' => ...]
     */
    public function notifyUser(
        int $userId,
        string $title,
        string $message,
        string $type = self::TYPE_INFO,
        ?string $actionUrl = null,
        array $channels = [self::CHANNEL_IN_APP],
        array $recipientOverrides = []
    ): array {
        $user = $this->userModel->find($userId);
        if (!$user || !empty($user['deleted_at']) || $user['status'] !== 'active') {
            return [
                'success' => false,
                'error'   => 'Pengguna tidak ditemukan atau berstatus tidak aktif.'
            ];
        }

        // Terapkan override email/nomor telepon kustom jika disediakan (fitur edit kontak mandiri)
        if (!empty($recipientOverrides['phone'])) {
            $user['phone'] = trim((string)$recipientOverrides['phone']);
        }
        if (!empty($recipientOverrides['email'])) {
            $user['email'] = trim((string)$recipientOverrides['email']);
        }

        // Jika diminta memperbarui database profil pengguna
        if (!empty($recipientOverrides['update_profile'])) {
            $updateData = [];
            if (!empty($recipientOverrides['phone'])) {
                $updateData['phone'] = trim((string)$recipientOverrides['phone']);
            }
            if (!empty($recipientOverrides['email']) && filter_var($recipientOverrides['email'], FILTER_VALIDATE_EMAIL)) {
                $updateData['email'] = trim((string)$recipientOverrides['email']);
            }
            if (!empty($updateData)) {
                try {
                    $this->userModel->update($userId, $updateData);
                } catch (\Throwable) {
                    // Abaikan jika update gagal agar dispatch notifikasi tetap berjalan
                }
            }
        }

        // Tentukan role penerima untuk penerapan guardrail konten
        $userRole = $this->getUserPrimaryRole($userId);

        // Guardrail Keamanan: Sanitasi judul & teks jika penerima adalah siswa (anti-leak token)
        $cleanTitle = $this->sanitizeContentForRole($title, $userRole);
        $cleanMessage = $this->sanitizeContentForRole($message, $userRole);

        $results = [
            'success'      => true,
            'user_id'      => $userId,
            'role'         => $userRole,
            'channels'     => []
        ];

        // 1. Channel In-App (Menyimpan ke tabel `notifications`)
        if (in_array(self::CHANNEL_IN_APP, $channels, true)) {
            $notifId = $this->notificationModel->createNotification([
                'user_id'    => $userId,
                'title'      => $cleanTitle,
                'message'    => $cleanMessage,
                'type'       => $type,
                'action_url' => $actionUrl
            ]);

            $results['channels'][self::CHANNEL_IN_APP] = [
                'status'          => $notifId ? 'sent' : 'failed',
                'notification_id' => $notifId ?: null
            ];
        }

        $inAppNotifId = (int)($results['channels'][self::CHANNEL_IN_APP]['notification_id'] ?? 0);

        // 2. Channel WhatsApp (Persiapan Arsitektur Multi-Channel)
        if (in_array(self::CHANNEL_WHATSAPP, $channels, true)) {
            $results['channels'][self::CHANNEL_WHATSAPP] = $this->dispatchWhatsApp(
                $userId,
                $user,
                $cleanTitle,
                $cleanMessage,
                $actionUrl,
                $inAppNotifId ?: null
            );
        }

        // 3. Channel Email (Persiapan Arsitektur Multi-Channel)
        if (in_array(self::CHANNEL_EMAIL, $channels, true)) {
            $results['channels'][self::CHANNEL_EMAIL] = $this->dispatchEmail(
                $userId,
                $user,
                $cleanTitle,
                $cleanMessage,
                $actionUrl,
                $inAppNotifId ?: null
            );
        }

        return $results;
    }

    /**
     * Kirim notifikasi kustom dengan opsi memasukkan/mengedit email dan nomor WhatsApp pengguna
     *
     * @param int $userId ID pengguna terdaftar
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan notifikasi
     * @param string $type Tipe notifikasi ('info', 'warning', 'danger', 'success')
     * @param string|null $actionUrl Tautan aksi opsional
     * @param array $channels Daftar saluran tujuan (in_app, whatsapp, email)
     * @param string|null $overrideEmail Alamat email yang diedit
     * @param string|null $overridePhone Nomor WhatsApp yang diedit
     * @param bool $updateProfile Simpan perubahan email & phone ke profil akun pengguna di database
     * @return array
     */
    public function sendCustomNotification(
        int $userId,
        string $title,
        string $message,
        string $type = self::TYPE_INFO,
        ?string $actionUrl = null,
        array $channels = [self::CHANNEL_IN_APP, self::CHANNEL_WHATSAPP, self::CHANNEL_EMAIL],
        ?string $overrideEmail = null,
        ?string $overridePhone = null,
        bool $updateProfile = false
    ): array {
        $overrides = [
            'update_profile' => $updateProfile
        ];
        if (!empty($overrideEmail)) {
            $overrides['email'] = $overrideEmail;
        }
        if (!empty($overridePhone)) {
            $overrides['phone'] = $overridePhone;
        }

        return $this->notifyUser(
            $userId,
            $title,
            $message,
            $type,
            $actionUrl,
            $channels,
            $overrides
        );
    }

    /**
     * Kirim notifikasi ke seluruh siswa aktif dalam satu rombongan belajar (Kelas)
     * Otomatis menerapkan guardrail proteksi token (siswa tidak menerima token)
     *
     * @param int $classId ID kelas rombel
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan notifikasi
     * @param string $type Tipe notifikasi ('info', 'warning', 'danger', 'success')
     * @param string|null $actionUrl URL aksi
     * @return array
     */
    public function notifyClass(
        int $classId,
        string $title,
        string $message,
        string $type = self::TYPE_INFO,
        ?string $actionUrl = null
    ): array {
        // Ambil seluruh siswa aktif di kelas tersebut
        $studentIds = $this->getActiveStudentIdsInClass($classId);
        if (empty($studentIds)) {
            return [
                'success'        => true,
                'class_id'       => $classId,
                'notified_count' => 0,
                'message'        => 'Tidak ada siswa aktif yang terdaftar di kelas ini.'
            ];
        }

        // Sanitasi ketat: Judul & Pesan ke rombel siswa TIDAK BOLEH memuat token ujian
        $safeTitle = $this->sanitizeContentForRole($title, 'peserta_didik');
        $safeMessage = $this->sanitizeContentForRole($message, 'peserta_didik');

        // Gunakan batch insert efisien
        $notifiedCount = $this->notificationModel->createBatchNotifications(
            $studentIds,
            $safeTitle,
            $safeMessage,
            $type,
            $actionUrl
        );

        return [
            'success'        => true,
            'class_id'       => $classId,
            'notified_count' => $notifiedCount,
            'student_ids'    => $studentIds
        ];
    }

    /**
     * Kirim notifikasi ke daftar ID siswa peserta ujian tertentu (Batch dengan sanitasi dan filter unik)
     *
     * @param array $studentIds Daftar ID siswa
     * @param string $title Judul notifikasi
     * @param string $message Isi pesan
     * @param string $type Tipe notifikasi
     * @param string|null $actionUrl URL aksi
     * @return array
     */
    public function notifyStudents(
        array $studentIds,
        string $title,
        string $message,
        string $type = self::TYPE_INFO,
        ?string $actionUrl = null
    ): array {
        $uniqueIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn($id) => $id > 0)));
        if (empty($uniqueIds)) {
            return [
                'success'        => true,
                'notified_count' => 0,
                'message'        => 'Tidak ada siswa target.'
            ];
        }

        // Sanitasi ketat untuk siswa (proteksi token ujian pada judul & isi pesan)
        $safeTitle = $this->sanitizeContentForRole($title, 'peserta_didik');
        $safeMessage = $this->sanitizeContentForRole($message, 'peserta_didik');

        // Batch insert ke notifications
        $notifiedCount = $this->notificationModel->createBatchNotifications(
            $uniqueIds,
            $safeTitle,
            $safeMessage,
            $type,
            $actionUrl
        );

        return [
            'success'        => true,
            'notified_count' => $notifiedCount,
            'student_ids'    => $uniqueIds
        ];
    }

    /**
     * Kirim notifikasi ke Guru pengampu / wali kelas
     */
    public function notifyTeacher(
        int $teacherId,
        string $title,
        string $message,
        string $type = self::TYPE_INFO,
        ?string $actionUrl = null,
        array $channels = [self::CHANNEL_IN_APP]
    ): array {
        $teacherRole = $this->getUserPrimaryRole($teacherId);
        if (!in_array($teacherRole, ['guru', 'admin', 'super_admin'], true)) {
            return [
                'success' => false,
                'error'   => 'ID pengguna yang ditentukan bukan merupakan Tenaga Pendidik (Guru).'
            ];
        }

        return $this->notifyUser($teacherId, $title, $message, $type, $actionUrl, $channels);
    }

    /**
     * Kirim notifikasi operasional ke seluruh Administrator sistem
     */
    public function notifyAdmins(
        string $title,
        string $message,
        string $type = self::TYPE_INFO,
        ?string $actionUrl = null,
        array $channels = [self::CHANNEL_IN_APP]
    ): array {
        $adminIds = $this->getAdminUserIds();
        if (empty($adminIds)) {
            return [
                'success'        => true,
                'notified_count' => 0,
                'message'        => 'Tidak ada akun administrator aktif.'
            ];
        }

        $notifiedCount = $this->notificationModel->createBatchNotifications(
            $adminIds,
            $title,
            $message,
            $type,
            $actionUrl
        );

        return [
            'success'        => true,
            'notified_count' => $notifiedCount,
            'admin_ids'      => $adminIds
        ];
    }

    /**
     * =========================================================================
     * PERSIAPAN ARSITEKTUR SALURAN EKSTERNAL (WHATSAPP & EMAIL)
     * =========================================================================
     */

    /**
     * Handler Saluran WhatsApp (Mock & Architectural Preparedness)
     * Tidak mengirim ke provider eksternal sebelum ada persetujuan & kredensial sah.
     */
    protected function dispatchWhatsApp(
        int $userId,
        array $user,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $notificationId = null
    ): array {
        $phone = trim((string)($user['phone'] ?? ''));

        // Jika nomor telepon tidak tersedia
        if (empty($phone)) {
            $this->logModel->recordLog(
                $notificationId,
                'whatsapp',
                'N/A',
                'failed',
                ['reason' => 'Nomor WhatsApp pengguna belum terdaftar di profil akun.']
            );

            return [
                'status'    => 'skipped',
                'reason'    => 'No phone number registered.',
                'recipient' => null
            ];
        }

        // Salurkan seluruh notifikasi WhatsApp melalui antrean asinkron (Queue Service)
        $queueRes = $this->queueService->enqueue(
            $notificationId,
            'whatsapp',
            $phone,
            [
                'info'       => $this->whatsAppEnabled
                    ? 'WhatsApp Gateway aktif / antrean siap diproses worker.'
                    : 'WhatsApp Gateway standby / simulasi antrean siap diproses worker.',
                'title'      => $title,
                'message'    => $message,
                'action_url' => !empty($actionUrl) ? \App\Libraries\SmtpClient::ensureAbsoluteUrl($actionUrl) : null,
                'preview'    => mb_substr($message, 0, 100),
                'user_id'    => $userId,
                'user_name'  => $user['name'] ?? null
            ]
        );

        $logId = (int)($queueRes['log_id'] ?? 0);
        $dispatchStatus = null;

        // Auto-Dispatch otomatis jika mode bukan simulation dan autoDispatch aktif
        if ($logId > 0 && $this->autoDispatch && \App\Config\Notification::getMode() !== \App\Config\Notification::MODE_SIMULATION) {
            $logRow = $this->logModel->find($logId);
            if ($logRow) {
                $processRes = $this->queueService->processItem($logRow, 'auto_dispatcher');
                $dispatchStatus = $processRes['status'] ?? null;
            }
        }

        return [
            'status'          => $this->whatsAppEnabled ? 'queued' : 'queued_simulation',
            'dispatch_status' => $dispatchStatus,
            'recipient'       => $phone,
            'queue_id'        => $logId,
            'message'         => 'Channel WhatsApp terdaftar dalam antrean dan siap diproses worker.'
        ];
    }

    /**
     * Handler Saluran Email (Mock & Architectural Preparedness)
     * Tidak mengirim SMTP riil sebelum ada persetujuan & kredensial mail server sah.
     */
    protected function dispatchEmail(
        int $userId,
        array $user,
        string $title,
        string $message,
        ?string $actionUrl,
        ?int $notificationId
    ): array {
        $email = trim((string)($user['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logModel->recordLog(
                $notificationId,
                'email',
                'N/A',
                'failed',
                ['reason' => 'Alamat email pengguna tidak valid atau kosong.']
            );

            return [
                'status'    => 'skipped',
                'reason'    => 'Invalid or empty email address.',
                'recipient' => null
            ];
        }

        // Salurkan seluruh notifikasi email melalui antrean asinkron (Queue Service)
        $queueRes = $this->queueService->enqueue(
            $notificationId,
            'email',
            $email,
            [
                'info'       => $this->emailEnabled 
                    ? 'SMTP Mailer aktif / antrean siap diproses worker.' 
                    : 'SMTP Mailer standby / simulasi antrean siap diproses worker.',
                'title'      => $title,
                'subject'    => $title,
                'message'    => $message,
                'action_url' => !empty($actionUrl) ? \App\Libraries\SmtpClient::ensureAbsoluteUrl($actionUrl) : null,
                'preview'    => mb_substr($message, 0, 100),
                'user_id'    => $userId,
                'user_name'  => $user['name'] ?? null
            ]
        );

        $logId = (int)($queueRes['log_id'] ?? 0);
        $dispatchStatus = null;

        // Auto-Dispatch otomatis jika mode bukan simulation dan autoDispatch aktif
        if ($logId > 0 && $this->autoDispatch && \App\Config\Notification::getMode() !== \App\Config\Notification::MODE_SIMULATION) {
            $logRow = $this->logModel->find($logId);
            if ($logRow) {
                $processRes = $this->queueService->processItem($logRow, 'auto_dispatcher');
                $dispatchStatus = $processRes['status'] ?? null;
            }
        }

        return [
            'status'          => $this->emailEnabled ? 'queued' : 'queued_simulation',
            'dispatch_status' => $dispatchStatus,
            'recipient'       => $email,
            'queue_id'        => $logId,
            'message'         => 'Channel Email terdaftar dalam antrean dan siap diproses worker.'
        ];
    }

    /**
     * Setter untuk mengaktifkan/menonaktifkan channel WhatsApp (untuk pengujian atau integrasi mendatang)
     */
    public function setWhatsAppEnabled(bool $enabled): self
    {
        $this->whatsAppEnabled = $enabled;
        return $this;
    }

    /**
     * Setter untuk mengaktifkan/menonaktifkan channel Email (untuk pengujian atau integrasi mendatang)
     */
    public function setEmailEnabled(bool $enabled): self
    {
        $this->emailEnabled = $enabled;
        return $this;
    }

    /**
     * =========================================================================
     * EVENT TEMPLATE HELPERS (MEMUDAHKAN INTEGRASI EVENT MENDATANG)
     * =========================================================================
     */

    /**
     * Format notifikasi jadwal ujian baru untuk kelas siswa (BEBAS TOKEN)
     */
    public function createExamScheduleNotification(
        int $classId,
        string $examTitle,
        string $subjectName,
        string $startTime,
        string $endTime,
        int $durationMinutes
    ): array {
        $formattedStart = !empty($startTime) && strtotime($startTime) !== false ? date('d M Y H:i', strtotime($startTime)) : $startTime;
        $formattedEnd = !empty($endTime) && strtotime($endTime) !== false ? date('d M Y H:i', strtotime($endTime)) : $endTime;
        $title = "Jadwal Ujian Baru: {$examTitle}";
        $message = "Ujian {$examTitle} mata pelajaran {$subjectName} telah dijadwalkan pada {$formattedStart} s/d {$formattedEnd} (Durasi: {$durationMinutes} menit). Pastikan koneksi internet stabil dan perangkat dual kamera siap digunakan.";

        return $this->notifyClass(
            $classId,
            $title,
            $message,
            self::TYPE_INFO,
            '/siswa/exams'
        );
    }

    /**
     * Format notifikasi rilis hasil ujian untuk siswa
     */
    public function createExamResultPublishedNotification(
        int $studentId,
        string $examTitle,
        float $score,
        float $kkm,
        bool $isPassed,
        array $channels = [self::CHANNEL_IN_APP]
    ): array {
        $title = "Hasil Ujian Dirilis: {$examTitle}";
        $statusKet = $isPassed ? 'Tuntas (Memenuhi KKM)' : 'Belum Tuntas (Perlu Remedial)';
        $message = "Hasil pengerjaan ujian {$examTitle} Anda telah dipublikasikan oleh pihak sekolah. Nilai: " . number_format($score, 2) . " (KKM: " . number_format($kkm, 2) . ") - Status: {$statusKet}. Silakan periksa rincian pada menu Hasil & Nilai.";

        return $this->notifyUser(
            $studentId,
            $title,
            $message,
            $isPassed ? self::TYPE_SUCCESS : self::TYPE_WARNING,
            '/siswa/results',
            $channels
        );
    }

    /**
     * Format notifikasi publikasi ujian baru untuk siswa peserta ujian
     */
    public function createExamPublishedNotification(
        int $studentId,
        string $examTitle,
        array $channels = [self::CHANNEL_IN_APP]
    ): array {
        $title = 'Ujian Baru Tersedia';
        $message = "Ujian {$examTitle} telah dipublikasikan dan tersedia untuk dikerjakan.";

        return $this->notifyUser(
            $studentId,
            $title,
            $message,
            self::TYPE_INFO,
            '/siswa/exams',
            $channels
        );
    }

    /**
     * Format notifikasi perubahan jadwal ujian untuk siswa peserta ujian
     */
    public function createExamScheduleChangedNotification(
        int $studentId,
        string $examTitle,
        string $startTime,
        string $endTime,
        array $channels = [self::CHANNEL_IN_APP]
    ): array {
        $formattedStart = date('d M Y H:i', strtotime($startTime));
        $formattedEnd = date('d M Y H:i', strtotime($endTime));
        $title = "Perubahan Jadwal Ujian: {$examTitle}";
        $message = "Jadwal pelaksanaan ujian {$examTitle} telah diperbarui menjadi {$formattedStart} s/d {$formattedEnd}. Silakan periksa detail jadwal pada katalog ujian.";

        return $this->notifyUser(
            $studentId,
            $title,
            $message,
            self::TYPE_WARNING,
            '/siswa/exams',
            $channels
        );
    }

    /**
     * Format notifikasi token ujian untuk Guru Pengampu (DIIZINKAN TOKEN)
     */
    public function createExamTokenNotificationForTeacher(
        int $teacherId,
        string $examTitle,
        string $token,
        string $validUntil,
        string $className
    ): array {
        $title = "Token Akses Ujian: {$examTitle}";
        $message = "Token ujian {$examTitle} untuk kelas {$className} telah siap: [{$token}] (berlaku s/d {$validUntil}). Bagikan token ini kepada peserta didik saat sesi ujian resmi dimulai.";

        return $this->notifyTeacher(
            $teacherId,
            $title,
            $message,
            self::TYPE_INFO,
            '/guru/exams'
        );
    }

    /**
     * Format notifikasi untuk Guru saat siswa mengumpulkan ujian yang memerlukan koreksi esai
     */
    public function createStudentSubmissionNotificationForTeacher(
        int $teacherId,
        string $studentName,
        string $examTitle,
        string $className,
        bool $hasEssay = false
    ): array {
        $title = "Ujian Diserahkan: {$studentName}";
        $message = "Peserta didik {$studentName} ({$className}) telah menyelesaikan ujian {$examTitle}.";
        if ($hasEssay) {
            $message .= " Paket ujian ini memiliki jawaban esai yang memerlukan koreksi dan penilaian manual Anda.";
        }

        return $this->notifyTeacher(
            $teacherId,
            $title,
            $message,
            $hasEssay ? self::TYPE_WARNING : self::TYPE_INFO,
            $hasEssay ? '/guru/grading' : '/guru/exams'
        );
    }

    /**
     * Format notifikasi indikasi pelanggaran AI Proctoring untuk Guru Pengampu Ujian
     *
     * @param int $teacherId ID guru pengampu ujian
     * @param string $studentName Nama siswa yang terindikasi
     * @param string $examTitle Judul ujian
     * @param string $violationName Nama deskriptif pelanggaran
     * @param int $examId ID ujian untuk tautan monitoring
     * @param string $severity Tingkat keparahan ('critical', 'high', 'medium', 'low')
     * @return array Status pengiriman notifikasi
     */
    public function createAiProctoringAlertNotificationForTeacher(
        int $teacherId,
        string $studentName,
        string $examTitle,
        string $violationName,
        int $examId,
        string $severity = 'critical'
    ): array {
        $title = 'Indikasi Pelanggaran AI Proctoring';
        $message = "Terdeteksi indikasi {$violationName} pada siswa {$studentName} dalam ujian {$examTitle}. Silakan lakukan peninjauan melalui Live Monitoring.";
        $type = ($severity === 'critical') ? self::TYPE_DANGER : self::TYPE_WARNING;
        $actionUrl = "/guru/exams/monitoring/{$examId}";

        return $this->notifyTeacher(
            $teacherId,
            $title,
            $message,
            $type,
            $actionUrl
        );
    }

    /**
     * =========================================================================
     * GUARDRAIL KEAMANAN & SANITASI KONTEN NOTIFIKASI
     * =========================================================================
     */

    protected ?array $cachedExamTokens = null;

    /**
     * Dapatkan daftar token ujian yang aktif/terdaftar untuk sanitasi presisi
     */
    protected function getKnownExamTokens(): array
    {
        if ($this->cachedExamTokens !== null) {
            return $this->cachedExamTokens;
        }

        try {
            $db = \App\Libraries\Database::getConnection();
            $tokens = $db->query("SELECT DISTINCT token FROM exam_tokens WHERE token IS NOT NULL AND token != ''")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $this->cachedExamTokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => $t !== '' && strlen($t) >= 3));
        } catch (\Throwable) {
            $this->cachedExamTokens = [];
        }

        return $this->cachedExamTokens;
    }

    /**
     * Membersihkan konten notifikasi agar tidak membocorkan token ujian ke siswa
     */
    public function sanitizeContentForRole(string $content, string $role): string
    {
        $normalizedRole = in_array($role, ['peserta_didik', 'siswa'], true) ? 'siswa' : $role;

        // Hanya proteksi khusus untuk siswa (guru dan admin diizinkan melihat token operasional)
        if ($normalizedRole === 'siswa') {
            // 1. Sensor presisi seluruh token yang terdaftar di tabel exam_tokens
            $tokens = $this->getKnownExamTokens();
            foreach ($tokens as $tkn) {
                if ($tkn !== '') {
                    $content = str_ireplace($tkn, '[TERPROTEKSI]', $content);
                }
            }

            // 2. Sensor pola token berpenanda eksplisit (Token: XYZ, Token ujian Anda: XYZ, Token adalah XYZ, Token = XYZ)
            $scrubbed = preg_replace('/(\btoken\b[^\:\=\n]{0,30}(?::|=|adalah)\s*)(?!\[?TERPROTEKSI)[A-Za-z0-9_]{4,15}\b/i', '$1[TERPROTEKSI]', $content);

            // 3. Sensor pola token format bracket [TOKEN]
            $scrubbed = preg_replace('/\[(?!TERPROTEKSI)[A-Za-z0-9_]{4,15}\]/i', '[TERPROTEKSI]', (string)$scrubbed);

            // Bersihkan jika terjadi penumpukan tag proteksi
            $scrubbed = preg_replace('/(\[TERPROTEKSI\])+/', '[TERPROTEKSI]', (string)$scrubbed);

            return $scrubbed ?: $content;
        }

        return $content;
    }

    /**
     * Ambil primary role pengguna dari tabel user_roles & roles
     */
    public function getUserPrimaryRole(int $userId): string
    {
        $db = \App\Libraries\Database::getConnection();
        $sql = "
            SELECT r.name 
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $roleName = $stmt->fetchColumn();

        if ($roleName) {
            return (string)$roleName;
        }

        // Fallback default
        return 'siswa';
    }

    /**
     * Ambil daftar student_id aktif pada kelas tertentu
     */
    protected function getActiveStudentIdsInClass(int $classId): array
    {
        $db = \App\Libraries\Database::getConnection();
        $sql = "
            SELECT DISTINCT cs.student_id
            FROM class_students cs
            JOIN users u ON u.id = cs.student_id
            WHERE cs.class_id = :cid
              AND cs.status = 'active'
              AND u.deleted_at IS NULL
              AND u.status = 'active'
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':cid' => $classId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map('intval', $rows);
    }

    /**
     * Ambil daftar ID seluruh akun Administrator aktif
     */
    protected function getAdminUserIds(): array
    {
        $db = \App\Libraries\Database::getConnection();
        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.name IN ('admin', 'super_admin')
              AND u.deleted_at IS NULL
              AND u.status = 'active'
        ";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map('intval', $rows);
    }
}
