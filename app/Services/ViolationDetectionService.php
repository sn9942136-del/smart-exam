<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\AiViolationModel;
use App\Models\AiViolationTypeModel;
use App\Models\AiWarningModel;
use App\Models\NotificationModel;
use App\Models\ProctoringSessionModel;
use App\Services\NotificationService;
use PDO;
use Throwable;

/**
 * Violation Detection Service (UC-13, UC-13a, UC-13b, UC-18)
 * Mengelola deteksi pelanggaran, pencatatan bukti, dan pembuatan peringatan otomatis.
 */
class ViolationDetectionService
{
    private AiViolationModel $violationModel;
    private AiViolationTypeModel $violationTypeModel;
    private AiWarningModel $warningModel;
    private ProctoringSessionModel $proctoringSessionModel;
    private NotificationService $notificationService;
    private NotificationModel $notificationModel;

    public function __construct(
        ?AiViolationModel $violationModel = null,
        ?AiViolationTypeModel $violationTypeModel = null,
        ?AiWarningModel $warningModel = null,
        ?ProctoringSessionModel $proctoringSessionModel = null,
        ?NotificationService $notificationService = null,
        ?NotificationModel $notificationModel = null
    ) {
        $this->violationModel = $violationModel ?? new AiViolationModel();
        $this->violationTypeModel = $violationTypeModel ?? new AiViolationTypeModel();
        $this->warningModel = $warningModel ?? new AiWarningModel();
        $this->proctoringSessionModel = $proctoringSessionModel ?? new ProctoringSessionModel();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->notificationModel = $notificationModel ?? new NotificationModel();
    }

    /**
     * Catat pelanggaran baru yang terdeteksi oleh AI proctoring
     *
     * @param int $proctoringSessionId ID sesi proctoring
     * @param string $violationCode Kode tipe pelanggaran (NO_FACE, MULTIPLE_FACES, LOOK_AWAY, PHONE_DETECTED, TAB_SWITCH, SECONDARY_CAM_LOST, AUDIO_SPEECH_DETECTED)
     * @param float $confidenceScore Tingkat keyakinan deteksi (0.0 - 1.0)
     * @param string|null $evidenceSnapshotBase64 Tangkapan frame bukti (Base64)
     * @param array|null $metadata Data telemetri pendukung (yaw, pitch, bounding box, dll)
     * @param int|null $cameraDeviceId ID kamera asal deteksi
     * @return array
     */
    public function recordViolation(
        int $proctoringSessionId,
        string $violationCode,
        float $confidenceScore,
        ?string $evidenceSnapshotBase64 = null,
        ?array $metadata = null,
        ?int $cameraDeviceId = null
    ): array {
        try {
            $session = $this->proctoringSessionModel->find($proctoringSessionId);
            if (!$session) {
                return [
                    'success' => false,
                    'code' => 'INVALID_SESSION',
                    'error' => 'Sesi proctoring tidak ditemukan.'
                ];
            }

            // Validasi status sesi ujian terkait: harus ongoing
            if (!empty($session['exam_session_id'])) {
                $examSessionModel = new \App\Models\ExamSessionModel();
                $examSession = $examSessionModel->find((int)$session['exam_session_id']);
                if (!$examSession || ($examSession['status'] ?? '') !== 'ongoing') {
                    return [
                        'success' => false,
                        'code' => 'EXAM_SESSION_INACTIVE',
                        'error' => 'Sesi ujian telah selesai atau tidak dalam status aktif.'
                    ];
                }
            }

            // 1. Ambil konfigurasi tipe pelanggaran dan validasi
            $violationType = $this->violationTypeModel->where('code', $violationCode)->first();
            if (!$violationType) {
                return [
                    'success' => false,
                    'code' => 'INVALID_VIOLATION_TYPE',
                    'error' => "Tipe pelanggaran '{$violationCode}' tidak terdaftar."
                ];
            }

            // 2. Cooldown check (Cegah spam duplikat dalam 3 detik untuk tipe yang sama pada sesi yang sama)
            if ($this->isRateLimited($proctoringSessionId, (int)$violationType['id'], 3)) {
                return [
                    'success' => true,
                    'rate_limited' => true,
                    'message' => 'Pelanggaran serupa baru saja dicatat, mengabaikan duplikasi cepat.'
                ];
            }

            // 3. Simpan snapshot bukti jika tersedia
            $snapshotUrl = null;
            if ($evidenceSnapshotBase64 && str_starts_with($evidenceSnapshotBase64, 'data:image')) {
                $snapshotUrl = $this->saveBase64Snapshot($evidenceSnapshotBase64, $violationCode, $proctoringSessionId);
            }

            // 4. Rekam ke tabel ai_violations
            // Catatan: proctor_action SELALU 'pending' agar ada human-in-the-loop review oleh guru/pengawas
            $sanitizedConfidence = is_numeric($confidenceScore) ? round(max(0.0, min(1.0, (float)$confidenceScore)), 2) : 0.90;
            $violationData = [
                'proctoring_session_id' => $proctoringSessionId,
                'violation_type_id' => $violationType['id'],
                'camera_device_id' => $cameraDeviceId,
                'confidence_score' => $sanitizedConfidence,
                'evidence_snapshot_url' => $snapshotUrl,
                'evidence_video_clip_url' => null,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'is_flagged_for_review' => 1,
                'proctor_action' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $violationId = (int)$this->violationModel->insert($violationData);

            // 5. Buat peringatan otomatis ke peserta didik (UC-18)
            $warningData = $this->createWarningForViolation($proctoringSessionId, $violationId, $violationType);

            // 6. Notifikasi indikasi ke Guru Pengampu (Stage 6)
            $notificationData = null;
            try {
                $notificationData = $this->handleTeacherNotificationOnViolation($session, $violationType, $proctoringSessionId);
            } catch (Throwable $notifErr) {
                // Isolasi error: kegagalan notifikasi tidak membatalkan penyimpanan pelanggaran
                error_log('Gagal memproses notifikasi AI proctoring: ' . $notifErr->getMessage());
            }

            return [
                'success' => true,
                'violation_id' => $violationId,
                'violation_code' => $violationType['code'],
                'violation_name' => $violationType['name'],
                'severity' => $violationType['severity'],
                'penalty_score' => (int)$violationType['penalty_score'],
                'evidence_snapshot_url' => $snapshotUrl,
                'warning' => $warningData,
                'notification' => $notificationData
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'code' => 'VIOLATION_RECORD_FAILED',
                'error' => 'Gagal mencatat pelanggaran: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Buat catatan peringatan untuk peserta (UC-18)
     */
    private function createWarningForViolation(int $proctoringSessionId, int $violationId, array $violationType): array
    {
        $code = $violationType['code'];
        $severity = $violationType['severity'];

        // Format pesan peringatan yang informatif dan ramah pengguna
        $message = match ($code) {
            'NO_FACE' => 'Peringatan: Wajah Anda tidak terdeteksi di depan kamera. Pastikan wajah selalu menghadap layar.',
            'MULTIPLE_FACES' => 'Peringatan: Terdeteksi lebih dari satu orang di area ujian Anda.',
            'LOOK_AWAY' => 'Peringatan: Arah pandangan mata atau kepala Anda terdeteksi berpaling dari layar.',
            'PHONE_DETECTED' => 'Peringatan Keras: Terdeteksi perangkat ponsel/smartphone di area ujian.',
            'TAB_SWITCH' => 'Peringatan: Terdeteksi perpindahan tab browser atau meninggalkan jendela ujian.',
            'FULLSCREEN_EXIT' => 'Peringatan: Anda terdeteksi keluar dari mode layar penuh (Fullscreen). Harap tetap berada di mode fullscreen.',
            'SECONDARY_CAM_LOST', 'CAMERA_DISCONNECTED' => 'Peringatan: Koneksi kamera pengawas terputus. Pastikan perangkat kamera terpasang dengan baik.',
            'AUDIO_SPEECH_DETECTED' => 'Peringatan: Terdeteksi suara percakapan di sekitar Anda.',
            default => 'Peringatan: Terdeteksi aktivitas tidak wajar selama pengerjaan ujian (' . $violationType['name'] . ').'
        };

        // Tentukan tipe tampilan peringatan
        // Critical: modal_ack (butuh tombol konfirmasi 'Saya Mengerti')
        // Warning: toast
        // Info: toast
        $warningType = match ($severity) {
            'critical' => 'modal_ack',
            'warning' => 'toast',
            default => 'toast'
        };

        $insertData = [
            'proctoring_session_id' => $proctoringSessionId,
            'violation_id' => $violationId,
            'warning_type' => $warningType,
            'warning_message' => $message,
            'is_acknowledged' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $warningId = (int)$this->warningModel->insert($insertData);

        return [
            'id' => $warningId,
            'warning_type' => $warningType,
            'warning_message' => $message,
            'severity' => $severity,
            'created_at' => $insertData['created_at']
        ];
    }

    /**
     * Konfirmasi pengakuan peringatan oleh siswa
     */
    public function acknowledgeWarning(int $warningId, int $studentId): bool
    {
        $warning = $this->warningModel->find($warningId);
        if (!$warning) {
            return false;
        }

        return $this->warningModel->update($warningId, [
            'is_acknowledged' => 1,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Cek apakah pelanggaran serupa baru saja dicatat dalam X detik terakhir
     */
    private function isRateLimited(int $proctoringSessionId, int $violationTypeId, int $cooldownSeconds): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - $cooldownSeconds);
        $recent = $this->violationModel->where('proctoring_session_id', $proctoringSessionId)
            ->where('violation_type_id', $violationTypeId)
            ->where('created_at', $cutoff, '>=')
            ->first();

        return !empty($recent);
    }

    /**
     * Simpan frame snapshot bukti ke direktori uploads
     */
    public function saveBase64Snapshot(string $base64Data, string $violationCode, int $sessionId): ?string
    {
        try {
            if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=\r\n]+)$/', $base64Data, $matches)) {
                return null;
            }

            $type = strtolower($matches[1]);
            if ($type === 'jpeg') {
                $type = 'jpg';
            }

            $data = base64_decode($matches[2]);
            if ($data === false || strlen($data) > 5 * 1024 * 1024) { // Max 5MB
                return null;
            }

            $uploadDir = FCPATH . 'uploads/proctoring/snapshots/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = sprintf('violation_%d_%s_%s.%s', $sessionId, strtolower($violationCode), uniqid('', true), $type);
            $filePath = $uploadDir . $fileName;

            if (file_put_contents($filePath, $data)) {
                return 'uploads/proctoring/snapshots/' . $fileName;
            }
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ambil riwayat pelanggaran lengkap dengan tipe dan status review
     */
    public function getViolationsBySession(int $proctoringSessionId): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT v.*, vt.code as violation_code, vt.name as violation_name, vt.severity, vt.penalty_score
            FROM ai_violations v
            INNER JOIN ai_violation_types vt ON vt.id = v.violation_type_id
            WHERE v.proctoring_session_id = :psid
            ORDER BY v.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':psid', $proctoringSessionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Ambil daftar peringatan untuk sesi proctoring
     */
    public function getWarningsBySession(int $proctoringSessionId): array
    {
        return $this->warningModel->getWarningsBySessionId($proctoringSessionId);
    }

    /**
     * Evaluasi dan kirim notifikasi indikasi pelanggaran ke Guru Pengampu (Stage 6)
     *
     * @param array $session Data sesi proctoring
     * @param array $violationType Data tipe pelanggaran
     * @param int $proctoringSessionId ID sesi proctoring
     * @return array|null Status pengiriman notifikasi, atau null jika tidak memenuhi syarat
     */
    private function handleTeacherNotificationOnViolation(
        array $session,
        array $violationType,
        int $proctoringSessionId
    ): ?array {
        $violationCode = (string)($violationType['code'] ?? '');
        $isCriticalEvent = in_array($violationCode, ['MULTIPLE_FACES', 'PHONE_DETECTED', 'CAMERA_DISCONNECTED'], true);
        $isRepeatedEvent = ($violationCode === 'TAB_SWITCH');

        // 1. Filter Event yang diizinkan memicu notifikasi guru
        if (!$isCriticalEvent && !$isRepeatedEvent) {
            return null;
        }

        // 2. Evaluasi threshold untuk event TAB_SWITCH (minimal 3 event)
        if ($isRepeatedEvent) {
            $db = Database::getConnection();
            $stmtCount = $db->prepare("
                SELECT COUNT(*) 
                FROM ai_violations 
                WHERE proctoring_session_id = :psid 
                  AND violation_type_id = :vtid
            ");
            $stmtCount->execute([
                ':psid' => $proctoringSessionId,
                ':vtid' => (int)$violationType['id']
            ]);
            $tabSwitchCount = (int)$stmtCount->fetchColumn();

            if ($tabSwitchCount < 3) {
                return null;
            }
        }

        // 3. Validasi Exam Session
        $examSessionId = (int)($session['exam_session_id'] ?? 0);
        $studentId = (int)($session['student_id'] ?? 0);
        if ($examSessionId <= 0 || $studentId <= 0) {
            return null;
        }

        $examSessionModel = new \App\Models\ExamSessionModel();
        $examSession = $examSessionModel->find($examSessionId);
        if (!$examSession) {
            return null;
        }

        // Validasi status sesi ujian harus ongoing (tolak submitted, terminated, dll)
        if (($examSession['status'] ?? '') !== 'ongoing') {
            return null;
        }

        // Validasi kecocokan student_id sesi dengan proctoring session
        if ((int)($examSession['student_id'] ?? 0) !== $studentId) {
            return null;
        }

        // 4. Validasi Ujian & Guru Pengampu
        $examId = (int)($examSession['exam_id'] ?? 0);
        if ($examId <= 0) {
            return null;
        }

        $examModel = new \App\Models\ExamModel();
        $exam = $examModel->find($examId);
        if (!$exam || !empty($exam['deleted_at'])) {
            return null;
        }

        // Validasi status ujian aktif (published / ongoing / in_progress)
        if (!in_array($exam['status'] ?? '', ['published', 'ongoing', 'in_progress'], true)) {
            return null;
        }

        $teacherId = (int)($exam['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            return null;
        }

        // 5. Ambil data nama siswa & judul ujian
        $userModel = new \App\Models\UserModel();
        $student = $userModel->find($studentId);
        if (!$student || !empty($student['deleted_at']) || ($student['status'] ?? '') !== 'active') {
            return null;
        }

        $studentName = (string)($student['name'] ?? 'Peserta');
        $examTitle = (string)($exam['title'] ?? 'Ujian');
        $violationName = (string)($violationType['name'] ?? $violationCode);
        $severity = (string)($violationType['severity'] ?? 'warning');

        // 6. Anti-Spam & Deduplication (300 Detik Cooldown per Event/Siswa/Ujian dengan Concurrency Lock)
        $title = 'Indikasi Pelanggaran AI Proctoring';
        $message = "Terdeteksi indikasi {$violationName} pada siswa {$studentName} dalam ujian {$examTitle}. Silakan lakukan peninjauan melalui Live Monitoring.";

        $db = Database::getConnection();
        $lockKey = 'notif_proctor_' . $teacherId . '_' . md5($title . '_' . $message);
        $lockAcquired = false;

        try {
            $lockStmt = $db->query("SELECT GET_LOCK(" . $db->quote($lockKey) . ", 5)");
            $lockAcquired = ((int)$lockStmt->fetchColumn() === 1);

            if ($this->notificationModel->hasNotification($teacherId, $title, $message, true, 300)) {
                return [
                    'success'      => true,
                    'rate_limited' => true,
                    'message'      => 'Notifikasi serupa telah dikirim ke guru dalam jendela waktu 300 detik.'
                ];
            }

            // 7. Kirim Notifikasi via NotificationService
            return $this->notificationService->createAiProctoringAlertNotificationForTeacher(
                $teacherId,
                $studentName,
                $examTitle,
                $violationName,
                $examId,
                $severity
            );
        } finally {
            if ($lockAcquired) {
                $db->query("SELECT RELEASE_LOCK(" . $db->quote($lockKey) . ")");
            }
        }
    }
}
