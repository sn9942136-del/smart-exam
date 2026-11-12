<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\AiAnalysisLogModel;
use App\Models\AiViolationModel;
use App\Models\AiViolationTypeModel;
use App\Models\AiWarningModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\FaceVerificationModel;
use App\Models\ProctoringSessionModel;
use App\Models\UserModel;
use PDO;
use Throwable;

/**
 * AI Proctoring Service
 * Orkestrator utama analisis video real-time, risk scoring, integrasi telemetri,
 * dan workflow peninjauan pelanggaran oleh pengawas/guru.
 */
class AiProctoringService
{
    private ProctoringSessionModel $sessionModel;
    private AiAnalysisLogModel $logModel;
    private AiViolationModel $violationModel;
    private AiViolationTypeModel $violationTypeModel;
    private AiWarningModel $warningModel;
    private FaceVerificationModel $verificationModel;
    private ExamSessionModel $examSessionModel;
    private ExamResultModel $resultModel;
    private UserModel $userModel;
    private ViolationDetectionService $violationDetectionService;

    public function __construct()
    {
        $this->sessionModel = new ProctoringSessionModel();
        $this->logModel = new AiAnalysisLogModel();
        $this->violationModel = new AiViolationModel();
        $this->violationTypeModel = new AiViolationTypeModel();
        $this->warningModel = new AiWarningModel();
        $this->verificationModel = new FaceVerificationModel();
        $this->examSessionModel = new ExamSessionModel();
        $this->resultModel = new ExamResultModel();
        $this->userModel = new UserModel();
        $this->violationDetectionService = new ViolationDetectionService();
    }

    /**
     * Dapatkan atau mulai sesi proctoring untuk sesi ujian siswa
     */
    public function startOrGetProctoringSession(int $examSessionId, int $studentId): array
    {
        try {
            $existing = $this->sessionModel->where('exam_session_id', $examSessionId)
                ->where('student_id', $studentId)
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'session' => $existing,
                    'is_new' => false
                ];
            }

            $sessionData = [
                'exam_session_id' => $examSessionId,
                'student_id' => $studentId,
                'status' => 'initializing',
                'started_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $sessionId = (int)$this->sessionModel->insert($sessionData);
            $session = $this->sessionModel->find($sessionId);

            return [
                'success' => true,
                'session' => $session,
                'is_new' => true
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'Gagal menginisialisasi sesi proctoring: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Catat deret waktu frame telemetri AI (throttled dari browser) dan evaluasi anomali
     *
     * @param int $proctoringSessionId ID sesi proctoring
     * @param array $telemetry Data frame telemetri dari client
     * @return array Status live scoring, peringatan aktif, dan anomali terdeteksi
     */
    public function logTelemetry(int $proctoringSessionId, array $telemetry): array
    {
        try {
            $session = $this->sessionModel->find($proctoringSessionId);
            if (!$session) {
                return ['success' => false, 'code' => 'INVALID_SESSION', 'error' => 'Sesi proctoring tidak valid.'];
            }

            // Validasi status sesi ujian terkait: harus ongoing
            if (!empty($session['exam_session_id'])) {
                $examSession = $this->examSessionModel->find((int)$session['exam_session_id']);
                if (!$examSession || ($examSession['status'] ?? '') !== 'ongoing') {
                    return [
                        'success' => false,
                        'code' => 'EXAM_SESSION_INACTIVE',
                        'error' => 'Sesi ujian telah selesai atau tidak dalam status aktif.'
                    ];
                }
            }

            $cameraDeviceId = !empty($telemetry['camera_device_id']) && is_numeric($telemetry['camera_device_id']) ? (int)$telemetry['camera_device_id'] : null;
            $yaw = isset($telemetry['head_pose_yaw']) && is_numeric($telemetry['head_pose_yaw']) ? (float)$telemetry['head_pose_yaw'] : 0.0;
            $pitch = isset($telemetry['head_pose_pitch']) && is_numeric($telemetry['head_pose_pitch']) ? (float)$telemetry['head_pose_pitch'] : 0.0;
            $roll = isset($telemetry['head_pose_roll']) && is_numeric($telemetry['head_pose_roll']) ? (float)$telemetry['head_pose_roll'] : 0.0;

            $validGazes = ['center', 'left', 'right', 'up', 'down', 'away'];
            $gaze = isset($telemetry['gaze_direction']) && in_array($telemetry['gaze_direction'], $validGazes, true) ? $telemetry['gaze_direction'] : 'center';

            $faceCount = isset($telemetry['detected_face_count']) && is_numeric($telemetry['detected_face_count']) ? max(0, (int)$telemetry['detected_face_count']) : 1;
            $mouthMovement = !empty($telemetry['mouth_movement_detected']) ? 1 : 0;
            $phoneDetected = !empty($telemetry['phone_detected']) ? 1 : 0;
            $audioLevel = isset($telemetry['audio_noise_level']) && is_numeric($telemetry['audio_noise_level']) ? max(0.0, (float)$telemetry['audio_noise_level']) : 0.0;

            // Pastikan format frame_timestamp adalah integer Unix ms timestamp (BIGINT UNSIGNED)
            $frameTimestamp = null;
            if (!empty($telemetry['timestamp']) && is_numeric($telemetry['timestamp']) && (float)$telemetry['timestamp'] > 1000000000) {
                $frameTimestamp = (int)$telemetry['timestamp'];
            } elseif (!empty($telemetry['frame_timestamp']) && is_numeric($telemetry['frame_timestamp']) && (float)$telemetry['frame_timestamp'] > 1000000000) {
                $frameTimestamp = (int)$telemetry['frame_timestamp'];
            } else {
                $frameTimestamp = (int)round(microtime(true) * 1000);
            }

            // 1. Simpan ke time-series ai_analysis_logs
            $logData = [
                'proctoring_session_id' => $proctoringSessionId,
                'camera_device_id' => $cameraDeviceId,
                'frame_timestamp' => $frameTimestamp,
                'head_pose_yaw' => $yaw,
                'head_pose_pitch' => $pitch,
                'head_pose_roll' => $roll,
                'gaze_direction' => $gaze,
                'detected_face_count' => $faceCount,
                'mouth_movement_detected' => $mouthMovement,
                'phone_detected' => $phoneDetected,
                'audio_noise_level' => $audioLevel,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->logModel->insert($logData);

            // 2. Analisis Anomali Otomatis (Rules & Thresholds)
            $triggeredViolations = [];
            $snapshotBase64 = $telemetry['snapshot_base64'] ?? null;

            // Anomali A: Wajah Tidak Ditemukan
            if ($faceCount === 0) {
                $res = $this->violationDetectionService->recordViolation(
                    $proctoringSessionId,
                    'NO_FACE',
                    0.90,
                    $snapshotBase64,
                    ['yaw' => $yaw, 'pitch' => $pitch, 'face_count' => 0],
                    $cameraDeviceId
                );
                if (!empty($res['success']) && empty($res['rate_limited'])) {
                    $triggeredViolations[] = $res;
                }
            }
            // Anomali B: Terdeteksi Lebih dari 1 Wajah
            elseif ($faceCount > 1) {
                $res = $this->violationDetectionService->recordViolation(
                    $proctoringSessionId,
                    'MULTIPLE_FACES',
                    0.95,
                    $snapshotBase64,
                    ['face_count' => $faceCount],
                    $cameraDeviceId
                );
                if (!empty($res['success']) && empty($res['rate_limited'])) {
                    $triggeredViolations[] = $res;
                }
            }

            // Anomali C: Terdeteksi Ponsel / Gadget Terlarang
            if ($phoneDetected === 1) {
                $res = $this->violationDetectionService->recordViolation(
                    $proctoringSessionId,
                    'PHONE_DETECTED',
                    0.92,
                    $snapshotBase64,
                    ['phone_confidence' => $telemetry['phone_confidence'] ?? 0.92],
                    $cameraDeviceId
                );
                if (!empty($res['success']) && empty($res['rate_limited'])) {
                    $triggeredViolations[] = $res;
                }
            }

            // Anomali D: Menoleh / Berpaling (Head pose yaw > 35° atau < -35°, atau pitch > 30°)
            if ($faceCount === 1 && (abs($yaw) > 35.0 || abs($pitch) > 30.0 || $gaze === 'away')) {
                $res = $this->violationDetectionService->recordViolation(
                    $proctoringSessionId,
                    'LOOK_AWAY',
                    0.80,
                    $snapshotBase64,
                    ['yaw' => $yaw, 'pitch' => $pitch, 'gaze' => $gaze],
                    $cameraDeviceId
                );
                if (!empty($res['success']) && empty($res['rate_limited'])) {
                    $triggeredViolations[] = $res;
                }
            }

            // 3. Hitung Skor Risiko Terkini
            $scoreData = $this->calculateRiskScore($proctoringSessionId);

            // 4. Ambil peringatan aktif yang belum diakui (unacknowledged)
            $activeWarnings = $this->warningModel->findAll(
                ['proctoring_session_id' => $proctoringSessionId, 'is_acknowledged' => 0],
                'id DESC'
            );

            return [
                'success' => true,
                'risk_score' => $scoreData['risk_score'],
                'risk_tier' => $scoreData['risk_tier'],
                'integrity_score' => $scoreData['integrity_score'],
                'triggered_violations' => $triggeredViolations,
                'active_warnings' => $activeWarnings
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'Gagal memproses telemetri: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Hitung Skor Risiko & Skor Integritas berdasarkan akumulasi pelanggaran
     * 
     * Sistem Risk Scoring:
     * - Low: 0 - 30 (Aman / Normal)
     * - Medium: 31 - 60 (Perlu Perhatian / Pantauan)
     * - High: 61 - 80 (Tinggi / Peringatan Keras)
     * - Critical: 81 - 100 (Kritis / Prioritas Review Guru)
     * 
     * Skor Integritas = 100 - min(100, Total Poin Penalti Pelanggaran)
     */
    public function calculateRiskScore(int $proctoringSessionId): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                COUNT(v.id) as total_violations,
                SUM(CASE WHEN v.proctor_action != 'dismissed' THEN vt.penalty_score ELSE 0 END) as total_penalty_points,
                SUM(CASE WHEN vt.severity = 'critical' AND v.proctor_action != 'dismissed' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN vt.severity = 'warning' AND v.proctor_action != 'dismissed' THEN 1 ELSE 0 END) as warning_count,
                SUM(CASE WHEN v.proctor_action = 'pending' THEN 1 ELSE 0 END) as pending_review_count
            FROM ai_violations v
            INNER JOIN ai_violation_types vt ON vt.id = v.violation_type_id
            WHERE v.proctoring_session_id = :psid
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':psid', $proctoringSessionId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        $totalViolations = (int)($row['total_violations'] ?? 0);
        $totalPenalty = (int)($row['total_penalty_points'] ?? 0);
        $criticalCount = (int)($row['critical_count'] ?? 0);
        $warningCount = (int)($row['warning_count'] ?? 0);
        $pendingCount = (int)($row['pending_review_count'] ?? 0);

        // Risk Score berada di rentang 0 - 100
        $riskScore = min(100, max(0, $totalPenalty));

        // Tentukan Tingkat Risiko (Risk Tier)
        if ($riskScore <= 30) {
            $riskTier = 'low';
            $tierLabel = 'Rendah (Normal)';
            $tierBadge = 'bg-success';
        } elseif ($riskScore <= 60) {
            $riskTier = 'medium';
            $tierLabel = 'Sedang (Perlu Perhatian)';
            $tierBadge = 'bg-warning text-dark';
        } elseif ($riskScore <= 80) {
            $riskTier = 'high';
            $tierLabel = 'Tinggi (Risiko Tinggi)';
            $tierBadge = 'bg-danger';
        } else {
            $riskTier = 'critical';
            $tierLabel = 'Kritis (Prioritas Peninjauan)';
            $tierBadge = 'bg-dark text-white border border-danger';
        }

        // Skor Integritas (100 -> 0)
        $integrityScore = max(0, 100 - $riskScore);

        // Update skor integritas pada tabel exam_results jika ada
        $session = $this->sessionModel->find($proctoringSessionId);
        if ($session && !empty($session['exam_session_id'])) {
            $this->syncIntegrityScoreToResult((int)$session['exam_session_id'], $integrityScore);
        }

        return [
            'risk_score' => $riskScore,
            'risk_tier' => $riskTier,
            'tier_label' => $tierLabel,
            'tier_badge' => $tierBadge,
            'integrity_score' => $integrityScore,
            'total_violations' => $totalViolations,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'pending_review_count' => $pendingCount
        ];
    }

    /**
     * Sinkronisasi skor integritas ke hasil ujian siswa
     */
    private function syncIntegrityScoreToResult(int $examSessionId, int $integrityScore): void
    {
        try {
            $db = Database::getConnection();
            $sql = "UPDATE exam_results 
                    SET proctoring_integrity_score = :score, updated_at = NOW() 
                    WHERE exam_session_id = :esid";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':score', $integrityScore, PDO::PARAM_INT);
            $stmt->bindValue(':esid', $examSessionId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable) {
            // Ignore if exam_results record not yet created
        }
    }

    /**
     * Review tindakan pelanggaran oleh Pengawas/Guru (Human-in-the-Loop)
     *
     * @param int $violationId ID pelanggaran
     * @param string $action 'approved', 'dismissed', 'penalized'
     * @param string|null $notes Catatan peninjauan pengawas
     * @param int $proctorUserId ID user guru/pengawas
     * @return array
     */
    public function handleProctorReview(int $violationId, string $action, ?string $notes = null, int $proctorUserId = 0): array
    {
        try {
            $violation = $this->violationModel->find($violationId);
            if (!$violation) {
                return ['success' => false, 'error' => 'Data pelanggaran tidak ditemukan.'];
            }

            if (!in_array($action, ['approved', 'dismissed', 'penalized'], true)) {
                return ['success' => false, 'error' => 'Aksi peninjauan tidak valid.'];
            }

            $metadata = !empty($violation['metadata']) ? json_decode((string)$violation['metadata'], true) : [];
            if (!is_array($metadata)) {
                $metadata = [];
            }
            $metadata['reviewed_by_user_id'] = $proctorUserId;
            $metadata['review_notes'] = $notes;
            $metadata['reviewed_at'] = date('Y-m-d H:i:s');

            $this->violationModel->update($violationId, [
                'proctor_action' => $action,
                'metadata' => json_encode($metadata),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Hitung ulang skor risiko untuk sesi ini
            $scoreData = $this->calculateRiskScore((int)$violation['proctoring_session_id']);

            return [
                'success' => true,
                'message' => "Pelanggaran berhasil diperbarui dengan status: {$action}",
                'new_risk_score' => $scoreData['risk_score'],
                'new_integrity_score' => $scoreData['integrity_score']
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Gagal memproses review: ' . $e->getMessage()];
        }
    }

    /**
     * Dapatkan detail lengkap sesi proctoring untuk dashboard Pengawas/Guru
     */
    public function getSessionDetailForProctor(int $proctoringSessionId): ?array
    {
        $session = $this->sessionModel->find($proctoringSessionId);
        if (!$session) {
            return null;
        }

        $student = $this->userModel->find($session['student_id']);
        $examSession = $this->examSessionModel->find($session['exam_session_id']);
        $violations = $this->violationDetectionService->getViolationsBySession($proctoringSessionId);
        $warnings = $this->warningModel->getWarningsBySessionId($proctoringSessionId);
        $verifications = $this->verificationModel->findAll(
            ['proctoring_session_id' => $proctoringSessionId],
            'id DESC'
        );
        $recentTelemetry = $this->logModel->getRecentLogs($proctoringSessionId, 30);
        $scoreData = $this->calculateRiskScore($proctoringSessionId);

        return [
            'session' => $session,
            'student' => $student,
            'exam_session' => $examSession,
            'score_data' => $scoreData,
            'violations' => $violations,
            'warnings' => $warnings,
            'verifications' => $verifications,
            'recent_telemetry' => $recentTelemetry
        ];
    }
}
