<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamModel;
use App\Models\ExamSessionModel;
use App\Models\ChatMessageModel;
use App\Services\ExamMonitoringService;
use Throwable;

/**
 * Live Monitoring Dashboard Controller (Guru & Admin)
 * 
 * Mengelola pemantauan real-time peserta ujian, status kamera & heartbeat,
 * peringatan pengawas (UC-22a), terminasi darurat (UC-22a), chat dua arah (UC-22b),
 * serta soft reset sesi & otorisasi ujian susulan (UC-22c).
 */
class ExamMonitoringController extends BaseController
{
    private ExamMonitoringService $monitoringService;
    private ExamModel $examModel;
    private ExamSessionModel $sessionModel;
    private ChatMessageModel $chatModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->monitoringService = new ExamMonitoringService();
        $this->examModel = new ExamModel();
        $this->sessionModel = new ExamSessionModel();
        $this->chatModel = new ChatMessageModel();
    }

    /**
     * Memeriksa apakah pengguna saat ini berhak mengakses data ujian tertentu
     */
    private function canAccessExam(int $examId): bool
    {
        if (has_role(['super_admin', 'admin'])) {
            return true;
        }
        $exam = $this->examModel->find($examId);
        return $exam !== null && (int)$exam['teacher_id'] === (int)user_id();
    }

    /**
     * Memeriksa apakah pengguna saat ini berhak mengakses sesi ujian tertentu
     */
    private function canAccessSession(int $sessionId): bool
    {
        if (has_role(['super_admin', 'admin'])) {
            return true;
        }
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return false;
        }
        return $this->canAccessExam((int)$session['exam_id']);
    }

    /**
     * Entry Point / Hub Live Monitoring (Navigasi Guru & Admin)
     * GET /guru/exams/monitoring atau GET /admin/exams/monitoring
     */
    public function hub(): Response
    {
        $role = (string) user_role();
        $isTeacher = ($role === 'guru');
        $userId = (int) user_id();
        $basePrefix = ($role === 'super_admin' || $role === 'admin') ? 'admin' : 'guru';

        $db = \App\Libraries\Database::getConnection();

        // 1. Ambil ujian aktif / terbit (published / ongoing / in_progress) milik guru atau semua (admin)
        if ($isTeacher) {
            $stmt = $db->prepare("
                SELECT e.*, s.name as subject_name
                FROM exams e
                LEFT JOIN subjects s ON s.id = e.subject_id
                WHERE e.teacher_id = :teacher_id AND e.status IN ('published', 'ongoing', 'in_progress') AND e.deleted_at IS NULL
                ORDER BY e.id DESC
            ");
            $stmt->execute([':teacher_id' => $userId]);
        } else {
            $stmt = $db->prepare("
                SELECT e.*, s.name as subject_name
                FROM exams e
                LEFT JOIN subjects s ON s.id = e.subject_id
                WHERE e.status IN ('published', 'ongoing', 'in_progress') AND e.deleted_at IS NULL
                ORDER BY e.id DESC
            ");
            $stmt->execute();
        }
        $activeExams = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Jika tepat 1 ujian yang sedang aktif / published, langsung buka live room ujian tersebut
        if (count($activeExams) === 1) {
            return $this->redirect(base_url("{$basePrefix}/exams/monitoring/{$activeExams[0]['id']}"));
        }

        // Ambil daftar ujian lainnya milik guru / sekolah untuk ringkasan di hub
        if ($isTeacher) {
            $recentStmt = $db->prepare("
                SELECT e.*, s.name as subject_name
                FROM exams e
                LEFT JOIN subjects s ON s.id = e.subject_id
                WHERE e.teacher_id = :teacher_id AND e.deleted_at IS NULL
                ORDER BY e.id DESC
                LIMIT 10
            ");
            $recentStmt->execute([':teacher_id' => $userId]);
        } else {
            $recentStmt = $db->prepare("
                SELECT e.*, s.name as subject_name
                FROM exams e
                LEFT JOIN subjects s ON s.id = e.subject_id
                WHERE e.deleted_at IS NULL
                ORDER BY e.id DESC
                LIMIT 10
            ");
            $recentStmt->execute();
        }
        $allRecentExams = $recentStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $breadcrumbs = [
            ['title' => ($basePrefix === 'admin' ? 'Admin' : 'Guru'), 'url' => base_url("{$basePrefix}/dashboard")],
            ['title' => 'Live Monitoring Hub']
        ];

        $data = [
            'pageTitle'      => 'Pusat Pemantauan Ujian (Live Monitoring Hub)',
            'activeExams'    => $activeExams,
            'allRecentExams' => $allRecentExams,
            'breadcrumbs'    => $breadcrumbs,
            'basePrefix'     => $basePrefix,
            'currentUser'    => auth_user()
        ];

        return $this->view('guru/exams/monitoring_hub', $data, $isTeacher ? 'layouts/guru' : 'layouts/admin');
    }

    /**
     * Halaman Utama Live Monitoring Dashboard Guru / Admin (UC-22)
     * GET /guru/exams/monitoring/{examId}
     */
    public function index(string|int $examId): Response
    {
        $examId = (int) $examId;
        $exam = $this->examModel->find($examId);

        if (!$exam) {
            Session::setFlash('danger', 'Data ujian tidak ditemukan.');
            return $this->redirect(base_url(has_role(['super_admin', 'admin']) ? 'admin/exams' : 'guru/exams'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            Session::setFlash('danger', 'Akses ditolak: Anda hanya dapat memantau ujian yang Anda ampu.');
            return $this->redirect(base_url('guru/exams'));
        }

        $summary = $this->monitoringService->getExamMonitoringSummary($examId);
        $initialParticipants = $this->monitoringService->getLiveParticipants($examId);

        $role = (string) user_role();
        $basePrefix = ($role === 'super_admin' || $role === 'admin') ? 'admin' : 'guru';

        $breadcrumbs = [
            ['title' => ($basePrefix === 'admin' ? 'Admin' : 'Guru'), 'url' => base_url("{$basePrefix}/dashboard")],
            ['title' => 'Manajemen Ujian', 'url' => base_url("{$basePrefix}/exams")],
            ['title' => 'Detail Ujian', 'url' => base_url("{$basePrefix}/exams/show/{$examId}")],
            ['title' => 'Live Monitoring']
        ];

        $data = [
            'pageTitle'           => 'Live Monitoring: ' . ($exam['title'] ?? 'Ujian'),
            'exam'                => $exam,
            'summary'             => $summary,
            'initialParticipants' => $initialParticipants,
            'breadcrumbs'         => $breadcrumbs,
            'basePrefix'          => $basePrefix,
            'currentUser'         => auth_user()
        ];

        return $this->view('guru/exams/monitoring', $data, 'layouts/main');
    }

    /**
     * Endpoint Polling Real-Time Data Monitoring (UC-22)
     * GET /guru/exams/monitoring/{examId}/poll
     */
    public function pollData(string|int $examId): Response
    {
        $examId = (int) $examId;
        if (!$this->canAccessExam($examId)) {
            return $this->json(['success' => false, 'error' => 'Akses live monitoring ujian ini ditolak.'], 403);
        }

        $statusFilter = $this->request->get('status');
        $riskFilter = $this->request->get('risk');
        $search = $this->request->get('search');

        $filters = [];
        if (!empty($statusFilter)) {
            $filters['status'] = (string) $statusFilter;
        }
        if (!empty($riskFilter)) {
            $filters['risk_tier'] = (string) $riskFilter;
        }
        if (!empty($search)) {
            $filters['search'] = (string) $search;
        }

        $summary = $this->monitoringService->getExamMonitoringSummary($examId);
        $participants = $this->monitoringService->getLiveParticipants($examId, $filters);

        return $this->json([
            'success'      => true,
            'server_time'  => date('Y-m-d H:i:s'),
            'timestamp'    => time(),
            'summary'      => $summary,
            'participants' => $participants
        ]);
    }

    /**
     * Dapatkan Detail Siswa untuk Modal Drawer (UC-22)
     * GET /guru/exams/monitoring/{examId}/student/{studentId}
     */
    public function studentDetail(string|int $examId, string|int $studentId): Response
    {
        $examId = (int) $examId;
        $studentId = (int) $studentId;

        if (!$this->canAccessExam($examId)) {
            return $this->json(['success' => false, 'error' => 'Akses detail peserta ujian ini ditolak.'], 403);
        }

        $detail = $this->monitoringService->getParticipantDetail($examId, $studentId);

        if (!$detail || empty($detail['student'])) {
            return $this->json([
                'success' => false,
                'error'   => 'Data detail peserta didik tidak ditemukan.'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data'    => $detail
        ]);
    }

    /**
     * Kirim Teguran / Peringatan Pengawas ke Siswa (UC-22a)
     * POST /guru/exams/monitoring/send-warning
     */
    public function sendWarning(): Response
    {
        $teacherId = (int) user_id();
        $sessionId = (int) $this->request->input('session_id', 0);
        $message = (string) $this->request->input('message', '');
        $warningType = (string) $this->request->input('warning_type', 'toast');

        if ($sessionId <= 0 || empty(trim($message))) {
            return $this->json([
                'success' => false,
                'error'   => 'Parameter sesi atau pesan peringatan tidak boleh kosong.'
            ], 422);
        }

        if (!$this->canAccessSession($sessionId)) {
            return $this->json(['success' => false, 'error' => 'Akses pengiriman teguran ke sesi ini ditolak.'], 403);
        }

        $result = $this->monitoringService->sendTeacherWarning(
            $sessionId,
            $message,
            $warningType,
            $teacherId
        );

        $status = !empty($result['success']) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Hentikan / Diskualifikasi Sesi Ujian Siswa (UC-22a)
     * POST /guru/exams/monitoring/terminate
     */
    public function terminate(): Response
    {
        $teacherId = (int) user_id();
        $sessionId = (int) $this->request->input('session_id', 0);
        $reason = (string) $this->request->input('reason', '');

        if ($sessionId <= 0 || empty(trim($reason))) {
            return $this->json([
                'success' => false,
                'error'   => 'Alasan penghentian ujian wajib diisi.'
            ], 422);
        }

        if (!$this->canAccessSession($sessionId)) {
            return $this->json(['success' => false, 'error' => 'Akses terminasi sesi ini ditolak.'], 403);
        }

        $result = $this->monitoringService->terminateStudentExam(
            $sessionId,
            $reason,
            $teacherId
        );

        $status = !empty($result['success']) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Soft Reset Sesi Ujian Siswa (UC-22c)
     * POST /guru/exams/monitoring/reset-session
     */
    public function resetSession(): Response
    {
        $teacherId = (int) user_id();
        $sessionId = (int) $this->request->input('session_id', 0);
        $resetOption = (string) $this->request->input('reset_option', 'restore_time');
        $customMinutes = $this->request->input('custom_minutes') !== null ? (int)$this->request->input('custom_minutes') : null;

        if ($sessionId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'ID sesi ujian tidak valid.'
            ], 422);
        }

        if (!$this->canAccessSession($sessionId)) {
            return $this->json(['success' => false, 'error' => 'Akses reset sesi ini ditolak.'], 403);
        }

        $result = $this->monitoringService->resetStudentSession(
            $sessionId,
            $resetOption,
            $teacherId,
            $customMinutes
        );

        $status = !empty($result['success']) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Otorisasi Izin Ujian Susulan (UC-22c)
     * POST /guru/exams/monitoring/makeup-exam
     */
    public function makeupExam(): Response
    {
        $teacherId = (int) user_id();
        $examId = (int) $this->request->input('exam_id', 0);
        $studentId = (int) $this->request->input('student_id', 0);
        $validUntil = $this->request->input('valid_until') ? (string)$this->request->input('valid_until') : null;

        if ($examId <= 0 || $studentId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Data ujian atau peserta tidak valid.'
            ], 422);
        }

        if (!$this->canAccessExam($examId)) {
            return $this->json(['success' => false, 'error' => 'Akses otorisasi ujian susulan ditolak.'], 403);
        }

        $result = $this->monitoringService->authorizeMakeUpExam(
            $examId,
            $studentId,
            $teacherId,
            $validUntil
        );

        $status = !empty($result['success']) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Kirim Pesan Chat Guru ke Siswa / Broadcast (UC-22b)
     * POST /guru/exams/monitoring/send-chat
     */
    public function sendChat(): Response
    {
        $senderId = (int) user_id();
        $sessionId = (int) $this->request->input('session_id', 0);
        $receiverId = $this->request->input('receiver_id') !== null ? (int)$this->request->input('receiver_id') : null;
        $message = (string) $this->request->input('message', '');
        $channel = (string) $this->request->input('channel', 'proctor_to_student');

        if ($sessionId <= 0 || empty(trim($message))) {
            return $this->json([
                'success' => false,
                'error'   => 'Sesi ujian dan pesan tidak boleh kosong.'
            ], 422);
        }

        if (!$this->canAccessSession($sessionId)) {
            return $this->json(['success' => false, 'error' => 'Akses chat pada sesi ujian ini ditolak.'], 403);
        }

        $result = $this->monitoringService->sendChatMessage(
            $sessionId,
            $senderId,
            $receiverId,
            $message,
            $channel
        );

        $status = !empty($result['success']) ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Dapatkan Percakapan Chat Sesi Ujian (UC-22b)
     * GET /guru/exams/monitoring/chat-thread/{sessionId}
     */
    public function getChatThread(string|int $sessionId): Response
    {
        $sessionId = (int) $sessionId;

        if (user_role() === 'peserta_didik') {
            $session = $this->sessionModel->find($sessionId);
            if (!$session || (int)$session['student_id'] !== (int)user_id()) {
                return $this->json(['success' => false, 'error' => 'Akses riwayat chat ditolak.'], 403);
            }
        } else {
            if (!$this->canAccessSession($sessionId)) {
                return $this->json(['success' => false, 'error' => 'Akses riwayat chat ditolak.'], 403);
            }
        }

        $thread = $this->chatModel->getThread($sessionId);

        return $this->json([
            'success' => true,
            'thread'  => $thread
        ]);
    }
}
