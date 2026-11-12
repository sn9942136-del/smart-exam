<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamSessionModel;
use App\Models\ProctoringSessionModel;
use App\Services\AiProctoringService;
use App\Services\FaceVerificationService;
use App\Services\ViolationDetectionService;

/**
 * AI Proctoring Controller
 * Mengelola API endpoint verifikasi wajah awal (UC-08), live telemetri video (UC-13, UC-13a, UC-13b),
 * peringatan peserta didik (UC-18), dan antarmuka peninjauan pengawas/guru.
 */
class AiProctoringController extends BaseController
{
    private AiProctoringService $proctoringService;
    private FaceVerificationService $faceService;
    private ViolationDetectionService $violationService;
    private ExamSessionModel $examSessionModel;
    private ProctoringSessionModel $proctoringSessionModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->proctoringService = new AiProctoringService();
        $this->faceService = new FaceVerificationService();
        $this->violationService = new ViolationDetectionService();
        $this->examSessionModel = new ExamSessionModel();
        $this->proctoringSessionModel = new ProctoringSessionModel();
    }

    /**
     * Inisialisasi atau hubungkan sesi proctoring untuk sesi ujian siswa
     * POST /siswa/proctoring/init-session
     */
    public function initSession(): Response
    {
        $studentId = (int) user_id();
        $sessionToken = (string) $this->request->input('session_token', '');
        $examSessionId = (int) $this->request->input('exam_session_id', 0);

        if ($examSessionId <= 0 && !empty($sessionToken)) {
            $examSession = $this->examSessionModel->findBySessionToken($sessionToken);
            if ($examSession) {
                $examSessionId = (int) $examSession['id'];
            }
        }

        if ($examSessionId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi ujian tidak valid atau token tidak ditemukan.'
            ], 422);
        }

        // Verifikasi kepemilikan sesi ujian
        $examSession = $this->examSessionModel->find($examSessionId);
        if (!$examSession || (int)$examSession['student_id'] !== $studentId) {
            return $this->json([
                'success' => false,
                'error'   => 'Akses sesi ujian tidak diizinkan.'
            ], 403);
        }

        if (($examSession['status'] ?? '') !== 'ongoing') {
            return $this->json([
                'success' => false,
                'code'    => 'EXAM_SESSION_INACTIVE',
                'error'   => 'Sesi ujian telah selesai atau tidak dalam status aktif.'
            ], 422);
        }

        $result = $this->proctoringService->startOrGetProctoringSession($examSessionId, $studentId);
        $status = $result['success'] ? 200 : 422;

        return $this->json($result, $status);
    }

    /**
     * Verifikasi Wajah Awal / Berkala (UC-08)
     * POST /siswa/proctoring/verify-face
     */
    public function verifyFace(): Response
    {
        $studentId = (int) user_id();
        $proctoringSessionId = (int) $this->request->input('proctoring_session_id', 0);
        $capturedPhoto = (string) $this->request->input('captured_photo', '');
        $faceEmbedding = $this->request->input('face_embedding');
        $confidence = $this->request->input('confidence') !== null ? (float)$this->request->input('confidence') : null;

        if ($proctoringSessionId <= 0 || empty($capturedPhoto)) {
            return $this->json([
                'success' => false,
                'error'   => 'Parameter verifikasi wajah tidak lengkap.'
            ], 422);
        }

        $session = $this->proctoringSessionModel->find($proctoringSessionId);
        if (!$session || (int)$session['student_id'] !== $studentId) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi proctoring tidak valid atau tidak cocok dengan akun Anda.'
            ], 403);
        }

        $embeddingArray = is_array($faceEmbedding) ? $faceEmbedding : null;

        $result = $this->faceService->verifyFace(
            $proctoringSessionId,
            $studentId,
            $capturedPhoto,
            $embeddingArray,
            $confidence
        );

        $status = $result['success'] ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Log Telemetri AI Real-Time dari Browser (UC-13, UC-13a, UC-13b)
     * POST /siswa/proctoring/log-telemetry
     */
    public function logTelemetry(): Response
    {
        $studentId = (int) user_id();
        $proctoringSessionId = (int) $this->request->input('proctoring_session_id', 0);
        $telemetry = $this->request->input('telemetry', []);

        if ($proctoringSessionId <= 0 || !is_array($telemetry)) {
            return $this->json([
                'success' => false,
                'error'   => 'Data telemetri AI tidak valid.'
            ], 422);
        }

        $session = $this->proctoringSessionModel->find($proctoringSessionId);
        if (!$session || (int)$session['student_id'] !== $studentId) {
            return $this->json([
                'success' => false,
                'error'   => 'Akses sesi proctoring ditolak.'
            ], 403);
        }

        $result = $this->proctoringService->logTelemetry($proctoringSessionId, $telemetry);
        $status = $result['success'] ? 200 : 422;

        return $this->json($result, $status);
    }

    /**
     * Catat Pelanggaran Khusus (misal Tab Switch, Kamera Putus, Objek Terlarang)
     * POST /siswa/proctoring/record-violation
     */
    public function recordViolation(): Response
    {
        $studentId = (int) user_id();
        $proctoringSessionId = (int) $this->request->input('proctoring_session_id', 0);
        $violationCode = (string) $this->request->input('violation_code', '');
        $confidenceScore = (float) $this->request->input('confidence_score', 0.90);
        $snapshot = $this->request->input('evidence_snapshot');
        $metadata = $this->request->input('metadata', []);
        $cameraDeviceId = $this->request->input('camera_device_id') !== null ? (int)$this->request->input('camera_device_id') : null;

        if ($proctoringSessionId <= 0 || empty($violationCode)) {
            return $this->json([
                'success' => false,
                'error'   => 'Parameter pencatatan pelanggaran tidak lengkap.'
            ], 422);
        }

        $session = $this->proctoringSessionModel->find($proctoringSessionId);
        if (!$session || (int)$session['student_id'] !== $studentId) {
            return $this->json([
                'success' => false,
                'error'   => 'Akses sesi proctoring tidak diizinkan.'
            ], 403);
        }

        $metaArray = is_array($metadata) ? $metadata : [];
        $result = $this->violationService->recordViolation(
            $proctoringSessionId,
            $violationCode,
            $confidenceScore,
            is_string($snapshot) ? $snapshot : null,
            $metaArray,
            $cameraDeviceId
        );

        $status = !empty($result['success']) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Konfirmasi Pengakuan Peringatan oleh Peserta (UC-18)
     * POST /siswa/proctoring/acknowledge-warning
     */
    public function acknowledgeWarning(): Response
    {
        $studentId = (int) user_id();
        $warningId = (int) $this->request->input('warning_id', 0);

        if ($warningId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'ID peringatan tidak valid.'
            ], 422);
        }

        $warningModel = new \App\Models\AiWarningModel();
        $warning = $warningModel->find($warningId);
        if (!$warning) {
            return $this->json([
                'success' => false,
                'error'   => 'Peringatan tidak ditemukan.'
            ], 404);
        }

        $session = $this->proctoringSessionModel->find((int)$warning['proctoring_session_id']);
        if (!$session || (int)$session['student_id'] !== $studentId) {
            return $this->json([
                'success' => false,
                'error'   => 'Akses konfirmasi peringatan ditolak.'
            ], 403);
        }

        $ack = $this->violationService->acknowledgeWarning($warningId, $studentId);
        return $this->json([
            'success' => $ack,
            'message' => $ack ? 'Peringatan telah dikonfirmasi.' : 'Gagal mengonfirmasi peringatan.'
        ]);
    }

    /**
     * Dapatkan Status Live Skor Risiko & Skor Integritas Sesi
     * GET /siswa/proctoring/session-status/{id}
     */
    public function sessionStatus(string|int $id): Response
    {
        $proctoringSessionId = (int) $id;
        $studentId = (int) user_id();

        $session = $this->proctoringSessionModel->find($proctoringSessionId);
        if (!$session || (int)$session['student_id'] !== $studentId) {
            return $this->json([
                'success' => false,
                'error'   => 'Akses status sesi ditolak.'
            ], 403);
        }

        $scoreData = $this->proctoringService->calculateRiskScore($proctoringSessionId);
        $recentViolations = $this->violationService->getViolationsBySession($proctoringSessionId);

        return $this->json([
            'success' => true,
            'score'   => $scoreData,
            'recent_violations' => array_slice($recentViolations, 0, 10)
        ]);
    }

    /**
     * Detail Sesi Proctoring untuk Pengawas / Guru (Live Monitoring)
     * GET /guru/proctoring/session-detail/{id}
     */
    public function teacherSessionDetail(string|int $id): Response
    {
        $role = (string) user_role();
        if (!in_array($role, ['guru', 'admin', 'super_admin', 'pengawas'], true)) {
            return $this->json([
                'success' => false,
                'error'   => 'Hanya guru dan admin yang berwenang melihat detail monitoring.'
            ], 403);
        }

        $proctoringSessionId = (int) $id;
        $procSession = $this->proctoringSessionModel->find($proctoringSessionId);
        if (!$procSession) {
            return $this->json([
                'success' => false,
                'error'   => 'Data sesi proctoring tidak ditemukan.'
            ], 404);
        }

        // Jika Guru: pastikan ujian ini diampu oleh guru yang sedang login
        if ($role === 'guru') {
            $examSession = $this->examSessionModel->find((int)$procSession['exam_session_id']);
            $exam = $examSession ? (new \App\Models\ExamModel())->find((int)$examSession['exam_id']) : null;
            if (!$exam || (int)$exam['teacher_id'] !== (int)user_id()) {
                return $this->json([
                    'success' => false,
                    'error'   => 'Akses ditolak: Anda tidak memiliki wewenang untuk memantau sesi ujian ini.'
                ], 403);
            }
        }

        $detail = $this->proctoringService->getSessionDetailForProctor($proctoringSessionId);

        if (!$detail) {
            return $this->json([
                'success' => false,
                'error'   => 'Data sesi proctoring tidak ditemukan.'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data'    => $detail
        ]);
    }

    /**
     * Aksi Peninjauan Pelanggaran oleh Pengawas/Guru (Human-in-the-Loop)
     * POST /guru/proctoring/review-violation
     */
    public function teacherReviewViolation(): Response
    {
        $role = (string) user_role();
        if (!in_array($role, ['guru', 'admin', 'super_admin', 'pengawas'], true)) {
            return $this->json([
                'success' => false,
                'error'   => 'Akses peninjauan pengawas ditolak.'
            ], 403);
        }

        $teacherId = (int) user_id();
        $violationId = (int) $this->request->input('violation_id', 0);
        $action = (string) $this->request->input('action', 'approved');
        $notes = (string) $this->request->input('notes', '');

        if ($violationId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'ID pelanggaran tidak valid.'
            ], 422);
        }

        $violationModel = new \App\Models\AiViolationModel();
        $violation = $violationModel->find($violationId);
        if (!$violation) {
            return $this->json([
                'success' => false,
                'error'   => 'Data pelanggaran tidak ditemukan.'
            ], 404);
        }

        // Jika Guru: pastikan pelanggaran berasal dari ujian milik guru yang login
        if ($role === 'guru') {
            $procSession = $this->proctoringSessionModel->find((int)$violation['proctoring_session_id']);
            $examSession = $procSession ? $this->examSessionModel->find((int)$procSession['exam_session_id']) : null;
            $exam = $examSession ? (new \App\Models\ExamModel())->find((int)$examSession['exam_id']) : null;
            if (!$exam || (int)$exam['teacher_id'] !== $teacherId) {
                return $this->json([
                    'success' => false,
                    'error'   => 'Akses ditolak: Anda tidak memiliki wewenang untuk meninjau pelanggaran pada ujian ini.'
                ], 403);
            }
        }

        $result = $this->proctoringService->handleProctorReview($violationId, $action, $notes, $teacherId);
        $status = $result['success'] ? 200 : 422;

        return $this->json($result, $status);
    }
}
