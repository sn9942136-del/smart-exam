<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\AiViolationModel;
use App\Models\AiWarningModel;
use App\Models\AuditLogModel;
use App\Models\ChatMessageModel;
use App\Models\EmergencyControlModel;
use App\Models\ExamEventModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamSessionModel;
use App\Models\FaceVerificationModel;
use App\Models\ProctoringSessionModel;
use App\Models\UserModel;
use PDO;
use Throwable;

/**
 * Exam Monitoring Service (UC-22, UC-22a, UC-22b, UC-22c)
 * Mengelola agregasi data pemantauan real-time guru, telemetri live peserta,
 * intervensi teguran/terminasi, soft reset sesi, ujian susulan, dan chat dua arah.
 */
class ExamMonitoringService
{
    private ExamModel $examModel;
    private ExamSessionModel $sessionModel;
    private ExamParticipantModel $participantModel;
    private UserModel $userModel;
    private AiViolationModel $violationModel;
    private AiWarningModel $warningModel;
    private FaceVerificationModel $verificationModel;
    private ProctoringSessionModel $proctoringSessionModel;
    private ChatMessageModel $chatModel;
    private EmergencyControlModel $emergencyModel;
    private ExamEventModel $eventModel;
    private AiProctoringService $aiProctoringService;

    public function __construct()
    {
        $this->examModel = new ExamModel();
        $this->sessionModel = new ExamSessionModel();
        $this->participantModel = new ExamParticipantModel();
        $this->userModel = new UserModel();
        $this->violationModel = new AiViolationModel();
        $this->warningModel = new AiWarningModel();
        $this->verificationModel = new FaceVerificationModel();
        $this->proctoringSessionModel = new ProctoringSessionModel();
        $this->chatModel = new ChatMessageModel();
        $this->emergencyModel = new EmergencyControlModel();
        $this->eventModel = new ExamEventModel();
        $this->aiProctoringService = new AiProctoringService();
    }

    /**
     * Dapatkan KPI & Ringkasan Metrik Pengawasan Ujian Real-Time
     */
    public function getExamMonitoringSummary(int $examId): array
    {
        $db = Database::getConnection();

        // 1. Total peserta terdaftar
        $stmtTotal = $db->prepare("SELECT COUNT(*) FROM exam_participants WHERE exam_id = :eid");
        $stmtTotal->execute([':eid' => $examId]);
        $totalRegistered = (int)$stmtTotal->fetchColumn();

        // 2. Metrik sesi peserta
        $sqlSessions = "
            SELECT 
                COUNT(CASE WHEN es.status = 'ongoing' THEN 1 END) as active_count,
                COUNT(CASE WHEN es.status = 'submitted' THEN 1 END) as submitted_count,
                COUNT(CASE WHEN es.status = 'terminated' THEN 1 END) as terminated_count,
                COUNT(CASE WHEN es.cam1_status = 'disconnected' OR es.cam2_status = 'disconnected' THEN 1 END) as cam_error_count,
                AVG(CASE WHEN es.status IN ('ongoing', 'submitted') THEN es.integrity_score END) as avg_integrity
            FROM exam_sessions es
            WHERE es.exam_id = :eid
        ";
        $stmtSessions = $db->prepare($sqlSessions);
        $stmtSessions->execute([':eid' => $examId]);
        $sessRow = $stmtSessions->fetch(PDO::FETCH_ASSOC) ?: [];

        $activeCount = (int)($sessRow['active_count'] ?? 0);
        $submittedCount = (int)($sessRow['submitted_count'] ?? 0);
        $terminatedCount = (int)($sessRow['terminated_count'] ?? 0);
        $camErrorCount = (int)($sessRow['cam_error_count'] ?? 0);
        $avgIntegrity = round((float)($sessRow['avg_integrity'] ?? 100.0), 1);

        // 3. Peserta dengan risiko tinggi / kritis (Risk Score > 60 atau Integrity < 40)
        $sqlCritical = "
            SELECT COUNT(DISTINCT ps.student_id)
            FROM proctoring_sessions ps
            INNER JOIN ai_violations v ON v.proctoring_session_id = ps.id
            INNER JOIN ai_violation_types vt ON vt.id = v.violation_type_id
            INNER JOIN exam_sessions es ON es.id = ps.exam_session_id
            WHERE es.exam_id = :eid AND v.proctor_action != 'dismissed'
            GROUP BY ps.id
            HAVING SUM(vt.penalty_score) > 60
        ";
        $stmtCrit = $db->prepare($sqlCritical);
        $stmtCrit->execute([':eid' => $examId]);
        $criticalRiskCount = count($stmtCrit->fetchAll());

        // 4. Total pelanggaran yang butuh review guru
        $sqlPending = "
            SELECT COUNT(v.id)
            FROM ai_violations v
            INNER JOIN proctoring_sessions ps ON ps.id = v.proctoring_session_id
            INNER JOIN exam_sessions es ON es.id = ps.exam_session_id
            WHERE es.exam_id = :eid AND v.proctor_action = 'pending'
        ";
        $stmtPending = $db->prepare($sqlPending);
        $stmtPending->execute([':eid' => $examId]);
        $pendingViolationsCount = (int)$stmtPending->fetchColumn();

        return [
            'total_registered'         => $totalRegistered,
            'active_count'             => $activeCount,
            'submitted_count'          => $submittedCount,
            'terminated_count'         => $terminatedCount,
            'not_started_count'        => max(0, $totalRegistered - ($activeCount + $submittedCount + $terminatedCount)),
            'cam_error_count'          => $camErrorCount,
            'critical_risk_count'      => $criticalRiskCount,
            'pending_violations_count' => $pendingViolationsCount,
            'avg_integrity_score'      => $avgIntegrity
        ];
    }

    /**
     * Dapatkan daftar peserta live beserta status koneksi, kamera, timer, dan risk scoring AI
     */
    public function getLiveParticipants(int $examId, array $filters = []): array
    {
        $db = Database::getConnection();

        $where = ["ep.exam_id = :eid"];
        $params = [':eid' => $examId];

        if (!empty($filters['class_id'])) {
            $where[] = "ep.class_id = :cid";
            $params[':cid'] = (int)$filters['class_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.name LIKE :search OR u.username LIKE :search OR u.nip_nisn LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $whereSql = implode(" AND ", $where);

        $sql = "
            SELECT 
                ep.id as participant_id,
                ep.exam_id,
                ep.student_id,
                ep.class_id,
                ep.status as participant_status,
                ep.score as participant_score,
                u.name as student_name,
                u.username as student_username,
                u.nip_nisn as student_nisn,
                u.avatar as student_avatar,
                c.name as class_name,
                es.id as exam_session_id,
                es.session_token,
                es.ip_address,
                es.start_time,
                es.submit_time,
                es.remaining_seconds,
                es.status as session_status,
                es.integrity_score,
                es.cam1_status,
                es.cam2_status,
                es.updated_at as last_activity_at,
                ps.id as proctoring_session_id,
                (SELECT COUNT(*) FROM exam_answers ea WHERE ea.exam_session_id = es.id AND (ea.selected_option_id IS NOT NULL OR (ea.essay_answer IS NOT NULL AND ea.essay_answer != ''))) as answered_count,
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = ep.exam_id) as total_questions_count
            FROM exam_participants ep
            INNER JOIN users u ON u.id = ep.student_id
            LEFT JOIN classes c ON c.id = ep.class_id
            LEFT JOIN exam_sessions es ON es.exam_id = ep.exam_id 
                AND es.student_id = ep.student_id 
                AND es.id = (SELECT MAX(id) FROM exam_sessions WHERE exam_id = ep.exam_id AND student_id = ep.student_id)
            LEFT JOIN proctoring_sessions ps ON ps.exam_session_id = es.id
            WHERE {$whereSql}
            ORDER BY 
                CASE 
                    WHEN es.status = 'ongoing' THEN 1 
                    WHEN es.status = 'paused' THEN 2 
                    WHEN es.status = 'submitted' THEN 3 
                    WHEN es.status = 'terminated' THEN 4 
                    ELSE 5 
                END ASC,
                u.name ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $now = time();
        $participants = [];

        foreach ($rows as $r) {
            $sessId = !empty($r['exam_session_id']) ? (int)$r['exam_session_id'] : null;
            $procId = !empty($r['proctoring_session_id']) ? (int)$r['proctoring_session_id'] : null;

            // 1. Status Heartbeat Online (Aktif jika aktivitas <= 45 detik)
            $lastActive = !empty($r['last_activity_at']) ? strtotime((string)$r['last_activity_at']) : 0;
            $isOnline = ($r['session_status'] === 'ongoing' && ($now - $lastActive) <= 45);

            // 2. Risk Score & Violations Aggregation
            $riskScore = 0;
            $riskTier = 'low';
            $tierBadge = 'bg-success';
            $totalViolations = 0;
            $pendingViolations = 0;

            if ($procId) {
                $scoreData = $this->aiProctoringService->calculateRiskScore($procId);
                $riskScore = $scoreData['risk_score'];
                $riskTier = $scoreData['risk_tier'];
                $tierBadge = $scoreData['tier_badge'];
                $totalViolations = $scoreData['total_violations'];
                $pendingViolations = $scoreData['pending_review_count'];
            }

            // 3. Unread chat count
            $unreadChat = 0;
            if ($sessId) {
                $stmtChat = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE exam_session_id = :sid AND is_read = 0 AND sender_id = :stuid");
                $stmtChat->execute([':sid' => $sessId, ':stuid' => (int)$r['student_id']]);
                $unreadChat = (int)$stmtChat->fetchColumn();
            }

            // 4. Filter by status if requested
            if (!empty($filters['status'])) {
                $statusFilter = $filters['status'];
                if ($statusFilter === 'active' && $r['session_status'] !== 'ongoing') continue;
                if ($statusFilter === 'submitted' && $r['session_status'] !== 'submitted') continue;
                if ($statusFilter === 'critical' && $riskScore <= 60 && $r['cam1_status'] === 'connected' && $r['cam2_status'] === 'connected') continue;
                if ($statusFilter === 'cam_error' && $r['cam1_status'] !== 'disconnected' && $r['cam2_status'] !== 'disconnected') continue;
            }

            $participants[] = [
                'participant_id'        => (int)$r['participant_id'],
                'student_id'            => (int)$r['student_id'],
                'student_name'          => $r['student_name'],
                'student_username'      => $r['student_username'],
                'student_nisn'          => $r['student_nisn'] ?? '-',
                'student_avatar'        => $r['student_avatar'],
                'class_name'            => $r['class_name'] ?? '-',
                'exam_session_id'       => $sessId,
                'session_token'         => $r['session_token'],
                'session_status'        => $r['session_status'] ?? 'not_started',
                'is_online'             => $isOnline,
                'remaining_seconds'     => max(0, (int)($r['remaining_seconds'] ?? 0)),
                'answered_count'        => (int)$r['answered_count'],
                'total_questions_count' => max(1, (int)$r['total_questions_count']),
                'progress_percent'      => round(((int)$r['answered_count'] / max(1, (int)$r['total_questions_count'])) * 100),
                'cam1_status'           => $r['cam1_status'] ?? 'disconnected',
                'cam2_status'           => $r['cam2_status'] ?? 'disconnected',
                'integrity_score'       => round((float)($r['integrity_score'] ?? 100.0)),
                'risk_score'            => $riskScore,
                'risk_tier'             => $riskTier,
                'risk_badge'            => $tierBadge,
                'total_violations'      => $totalViolations,
                'pending_violations'    => $pendingViolations,
                'proctoring_session_id' => $procId,
                'unread_chat_count'     => $unreadChat
            ];
        }

        return $participants;
    }

    /**
     * Dapatkan detail mendalam peserta ujian (Modal / Drawer view)
     */
    public function getParticipantDetail(int $examId, int $studentId): ?array
    {
        $db = Database::getConnection();

        $student = $this->userModel->find($studentId);
        if (!$student) return null;

        $exam = $this->examModel->find($examId);
        $participant = $this->participantModel->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->first();

        // Cari sesi ujian aktif / terbaru
        $session = $this->sessionModel->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->first();

        $procSession = null;
        $violations = [];
        $warnings = [];
        $verifications = [];
        $recentTelemetry = [];
        $scoreData = [
            'risk_score' => 0,
            'risk_tier' => 'low',
            'tier_label' => 'Normal',
            'tier_badge' => 'bg-success',
            'integrity_score' => 100,
            'total_violations' => 0,
            'pending_review_count' => 0
        ];

        if ($session) {
            $procSession = $this->proctoringSessionModel->where('exam_session_id', $session['id'])->first();
            if ($procSession) {
                $procId = (int)$procSession['id'];
                $scoreData = $this->aiProctoringService->calculateRiskScore($procId);

                // Ambil pelanggaran
                $sqlViolations = "
                    SELECT v.*, vt.name as violation_name, vt.code as violation_code, vt.severity, vt.penalty_score
                    FROM ai_violations v
                    INNER JOIN ai_violation_types vt ON vt.id = v.violation_type_id
                    WHERE v.proctoring_session_id = :psid
                    ORDER BY v.id DESC
                ";
                $stmtV = $db->prepare($sqlViolations);
                $stmtV->execute([':psid' => $procId]);
                $violations = $stmtV->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $warnings = $this->warningModel->findAll(['proctoring_session_id' => $procId], 'id DESC');
                $verifications = $this->verificationModel->findAll(['proctoring_session_id' => $procId], 'id DESC');
            }
        }

        // Ambil riwayat chat
        $chatThread = [];
        if ($session) {
            $chatThread = $this->chatModel->getThread((int)$session['id']);
        }

        return [
            'student'          => $student,
            'exam'             => $exam,
            'participant'      => $participant,
            'session'          => $session,
            'proctoring'       => $procSession,
            'score_data'       => $scoreData,
            'violations'       => $violations,
            'warnings'         => $warnings,
            'verifications'    => $verifications,
            'chat_thread'      => $chatThread
        ];
    }

    /**
     * Kirim Teguran / Peringatan Pengawas ke Siswa (UC-22a)
     */
    public function sendTeacherWarning(int|string $examSessionId, string $message, string $warningType, int|string $teacherId): array
    {
        try {
            $examSessionId = (int)$examSessionId;
            $teacherId = (int)$teacherId;
            $session = $this->sessionModel->find($examSessionId);
            if (!$session) {
                return ['success' => false, 'error' => 'Sesi ujian tidak ditemukan.'];
            }

            $procSession = $this->proctoringSessionModel->where('exam_session_id', $examSessionId)->first();
            $procId = $procSession ? (int)$procSession['id'] : null;

            if ($procId) {
                $this->warningModel->insert([
                    'proctoring_session_id' => $procId,
                    'violation_id'          => null,
                    'warning_type'          => in_array($warningType, ['modal_ack', 'toast'], true) ? $warningType : 'toast',
                    'warning_message'       => '[Pengawas] ' . trim($message),
                    'is_acknowledged'       => 0,
                    'created_at'            => date('Y-m-d H:i:s')
                ]);
            }

            // Catat ke event ujian
            ExamEventModel::log($examSessionId, 'teacher_warning', [
                'teacher_id'   => $teacherId,
                'message'      => $message,
                'warning_type' => $warningType
            ]);

            // Catat ke Audit Log
            AuditLogModel::record(
                'TEACHER_SEND_WARNING',
                'exam_monitoring',
                (string)$examSessionId,
                null,
                ['teacher_id' => $teacherId, 'student_id' => $session['student_id'], 'message' => $message, 'type' => $warningType]
            );

            return [
                'success' => true,
                'message' => 'Peringatan pengawas berhasil dikirim ke layar peserta.'
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Gagal mengirim teguran: ' . $e->getMessage()];
        }
    }

    /**
     * Hentikan / Diskualifikasi Sesi Ujian Siswa (UC-22a)
     */
    public function terminateStudentExam(int|string $examSessionId, string $reason, int|string $teacherId): array
    {
        try {
            $examSessionId = (int)$examSessionId;
            $teacherId = (int)$teacherId;
            $session = $this->sessionModel->find($examSessionId);
            if (!$session) {
                return ['success' => false, 'error' => 'Sesi ujian tidak ditemukan.'];
            }

            $beforeData = [
                'status' => $session['status'],
                'submit_time' => $session['submit_time']
            ];

            // 1. Update status sesi menjadi terminated
            $this->sessionModel->update($examSessionId, [
                'status'      => 'terminated',
                'submit_time' => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ]);

            // 2. Update status exam_participants menjadi blocked
            $this->participantModel->updateParticipantStatus(
                (int)$session['exam_id'],
                (int)$session['student_id'],
                'blocked'
            );

            // 3. Catat ke emergency_controls
            $this->emergencyModel->insert([
                'exam_id'                 => $session['exam_id'],
                'triggered_by'            => $teacherId,
                'event_type'              => 'individual_termination',
                'action_taken'            => 'terminate_student',
                'reason'                  => $reason,
                'affected_students_count' => 1,
                'created_at'              => date('Y-m-d H:i:s')
            ]);

            // 4. Catat ke Exam Event
            ExamEventModel::log($examSessionId, 'exam_terminated', [
                'teacher_id' => $teacherId,
                'reason'     => $reason
            ]);

            // 5. Catat Audit Log
            AuditLogModel::record(
                'TERMINATE_STUDENT_EXAM',
                'exam_monitoring',
                (string)$examSessionId,
                $beforeData,
                ['status' => 'terminated', 'reason' => $reason, 'teacher_id' => $teacherId]
            );

            return [
                'success' => true,
                'message' => 'Sesi ujian peserta berhasil dihentikan / didiskualifikasi.'
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Gagal menghentikan ujian: ' . $e->getMessage()];
        }
    }

    /**
     * Soft Reset Sesi Ujian Siswa Tanpa Hapus Data Permanen (UC-22c)
     *
     * @param string $resetOption 'restore_time' (pulihkan sisa waktu) | 'full_restart' (mulai ulang durasi penuh)
     */
    public function resetStudentSession(int|string $examSessionId, string $resetOption, int|string $teacherId, ?int $customMinutes = null): array
    {
        try {
            $examSessionId = (int)$examSessionId;
            $teacherId = (int)$teacherId;
            $session = $this->sessionModel->find($examSessionId);
            if (!$session) {
                return ['success' => false, 'error' => 'Sesi ujian tidak ditemukan.'];
            }

            $exam = $this->examModel->find((int)$session['exam_id']);
            $beforeData = $session;

            if ($resetOption === 'full_restart') {
                // Opsi A: Mulai Ulang Durasi Penuh (Sesi Baru)
                // Arsipkan sesi lama
                $this->sessionModel->update($examSessionId, [
                    'status'     => 'terminated',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $durationSeconds = ($customMinutes && $customMinutes > 0) 
                    ? ($customMinutes * 60) 
                    : ((int)($exam['duration_minutes'] ?? 60) * 60);

                // Buat sesi aktif baru
                $newSessionId = (int)$this->sessionModel->insert([
                    'exam_id'           => $session['exam_id'],
                    'student_id'        => $session['student_id'],
                    'session_token'     => bin2hex(random_bytes(16)),
                    'ip_address'        => $session['ip_address'],
                    'user_agent'        => $session['user_agent'],
                    'start_time'        => date('Y-m-d H:i:s'),
                    'submit_time'       => null,
                    'remaining_seconds' => $durationSeconds,
                    'status'            => 'ongoing',
                    'integrity_score'   => 100.00,
                    'cam1_status'       => 'connected',
                    'cam2_status'       => 'connected',
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s')
                ]);

                // Buat sesi proctoring baru
                $this->proctoringSessionModel->insert([
                    'exam_session_id' => $newSessionId,
                    'student_id'      => $session['student_id'],
                    'status'          => 'active',
                    'started_at'      => date('Y-m-d H:i:s'),
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s')
                ]);

                $this->participantModel->updateParticipantStatus(
                    (int)$session['exam_id'],
                    (int)$session['student_id'],
                    'in_progress'
                );

                $targetSessionId = $newSessionId;
                $actionMessage = "Sesi ujian direset dengan durasi penuh baru ({$durationSeconds} detik).";
            } else {
                // Opsi B: Pulihkan Sesi & Tambah/Kembalikan Sisa Waktu
                $addSeconds = ($customMinutes && $customMinutes > 0) ? ($customMinutes * 60) : 0;
                $newRemaining = max(300, (int)$session['remaining_seconds'] + $addSeconds);

                $this->sessionModel->update($examSessionId, [
                    'status'            => 'ongoing',
                    'submit_time'       => null,
                    'remaining_seconds' => $newRemaining,
                    'updated_at'        => date('Y-m-d H:i:s')
                ]);

                $this->participantModel->updateParticipantStatus(
                    (int)$session['exam_id'],
                    (int)$session['student_id'],
                    'in_progress'
                );

                $targetSessionId = $examSessionId;
                $actionMessage = "Sesi ujian dipulihkan dengan sisa waktu {$newRemaining} detik.";
            }

            // Catat Event
            ExamEventModel::log($targetSessionId, 'session_reset', [
                'reset_option'   => $resetOption,
                'old_session_id' => $examSessionId,
                'teacher_id'     => $teacherId
            ]);

            // Catat Audit Log
            AuditLogModel::record(
                'RESET_STUDENT_SESSION',
                'exam_monitoring',
                (string)$targetSessionId,
                $beforeData,
                ['reset_option' => $resetOption, 'teacher_id' => $teacherId, 'target_session_id' => $targetSessionId]
            );

            return [
                'success'           => true,
                'message'           => $actionMessage,
                'target_session_id' => $targetSessionId
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Gagal mereset sesi: ' . $e->getMessage()];
        }
    }

    /**
     * Izinkan Ujian Susulan untuk Siswa Tertentu (UC-22c)
     */
    public function authorizeMakeUpExam(int|string $examId, int|string $studentId, int|string $teacherId, ?string $validUntil = null): array
    {
        try {
            $examId = (int)$examId;
            $studentId = (int)$studentId;
            $teacherId = (int)$teacherId;
            $participant = $this->participantModel->where('exam_id', $examId)
                ->where('student_id', $studentId)
                ->first();

            if (!$participant) {
                return ['success' => false, 'error' => 'Peserta tidak terdaftar pada ujian ini.'];
            }

            $beforeData = $participant;

            // 1. Nonaktifkan/arsipkan sesi lama jika ada
            $db = Database::getConnection();
            $stmtArchive = $db->prepare("UPDATE exam_sessions SET status = 'terminated' WHERE exam_id = :eid AND student_id = :sid AND status != 'submitted'");
            $stmtArchive->execute([':eid' => $examId, ':sid' => $studentId]);

            // 2. Set status partisipan menjadi not_started untuk mengizinkan login ulang
            $this->participantModel->updateParticipantStatus($examId, $studentId, 'not_started');

            // 3. Catat Audit Log
            AuditLogModel::record(
                'AUTHORIZE_MAKEUP_EXAM',
                'exam_monitoring',
                "{$examId}_{$studentId}",
                $beforeData,
                ['status' => 'not_started', 'authorized_by' => $teacherId, 'valid_until' => $validUntil]
            );

            return [
                'success' => true,
                'message' => 'Otorisasi ujian susulan berhasil diberikan. Siswa dapat memulai ujian kembali.'
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Gagal memberikan izin ujian susulan: ' . $e->getMessage()];
        }
    }

    /**
     * Kirim Pesan Chat Guru ke Siswa / Broadcast (UC-22b)
     */
    public function sendChatMessage(int|string $examSessionId, int|string $senderId, int|string|null $receiverId, string $message, string $channel = 'proctor_to_student'): array
    {
        try {
            $examSessionId = (int)$examSessionId;
            $senderId = (int)$senderId;
            $receiverId = $receiverId !== null ? (int)$receiverId : null;
            $cleanMsg = trim($message);
            if (empty($cleanMsg)) {
                return ['success' => false, 'error' => 'Pesan tidak boleh kosong.'];
            }

            $msgId = (int)$this->chatModel->insert([
                'sender_id'       => $senderId,
                'receiver_id'     => $receiverId,
                'exam_session_id' => $examSessionId,
                'channel'         => $channel,
                'message'         => $cleanMsg,
                'is_read'         => 0,
                'created_at'      => date('Y-m-d H:i:s')
            ]);

            // Catat Audit Log
            AuditLogModel::record(
                'SEND_PROCTOR_CHAT',
                'exam_monitoring',
                (string)$msgId,
                null,
                ['exam_session_id' => $examSessionId, 'sender_id' => $senderId, 'receiver_id' => $receiverId, 'message' => $cleanMsg]
            );

            return [
                'success'    => true,
                'message_id' => $msgId,
                'message'    => 'Pesan chat berhasil dikirim.'
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Gagal mengirim pesan chat: ' . $e->getMessage()];
        }
    }
}
