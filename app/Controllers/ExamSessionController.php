<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Services\ExamSessionService;

/**
 * Exam Session Controller
 * Mengelola antarmuka ruang ujian fullscreen, inisialisasi sesi token,
 * rekoneksi sesi (UC-14), dan finalisasi submit ujian (UC-12).
 */
class ExamSessionController extends BaseController
{
    private ExamSessionService $sessionService;
    private ExamSessionModel $sessionModel;
    private ExamResultModel $resultModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->sessionService = new ExamSessionService();
        $this->sessionModel = new ExamSessionModel();
        $this->resultModel = new ExamResultModel();
    }

    /**
     * Memulai Sesi Ujian / Verifikasi Token (UC-10)
     */
    public function start(string|int $id): Response
    {
        $examId = (int) $id;
        $studentId = (int) user_id();
        $token = (string) $this->request->input('token', '');
        $ip = $this->request->getIp();
        $userAgent = $this->request->getUserAgent();

        $result = $this->sessionService->startOrResumeSession($examId, $studentId, $token, $ip, $userAgent);

        if (!$result['success']) {
            if ($this->request->isAjax()) {
                return $this->json([
                    'success' => false,
                    'message' => $result['error']
                ], 422);
            }

            Session::setFlash('danger', $result['error']);
            return $this->redirect(base_url('siswa/exams/show/' . $examId));
        }

        $roomUrl = base_url('siswa/exam/room/' . $result['session_token']);

        if ($this->request->isAjax()) {
            return $this->json([
                'success'  => true,
                'resumed'  => $result['resumed'] ?? false,
                'redirect' => $roomUrl
            ]);
        }

        if (!empty($result['resumed'])) {
            Session::setFlash('info', 'Sesi ujian Anda yang sedang berlangsung berhasil dipulihkan.');
        }

        return $this->redirect($roomUrl);
    }

    /**
     * Halaman Ruang Ujian Fullscreen (UC-10, UC-11, UC-12, UC-14)
     */
    public function room(string $sessionToken): Response
    {
        $studentId = (int) user_id();
        $roomData = $this->sessionService->getExamRoomData($sessionToken, $studentId);

        if (!$roomData) {
            Session::setFlash('danger', 'Sesi ujian tidak valid atau Anda tidak memiliki akses ke ruang ujian ini.');
            return $this->redirect(base_url('siswa/exams'));
        }

        // Jika ujian telah disubmit, alihkan langsung ke halaman selesai
        if ($roomData['session']['status'] === 'submitted') {
            return $this->redirect(base_url('siswa/exam/completed/' . $sessionToken));
        }

        // Jika ujian telah diterminasi / didiskualifikasi
        if ($roomData['session']['status'] === 'terminated') {
            Session::setFlash('danger', 'Sesi ujian telah dihentikan atau didiskualifikasi oleh pengawas.');
            return $this->redirect(base_url('siswa/exams'));
        }

        // Jika waktu pengerjaan telah berakhir (expired)
        if (!empty($roomData['is_expired']) || (int)$roomData['remaining_seconds'] <= 0) {
            $this->sessionService->submitExam($sessionToken, $studentId);
            Session::setFlash('info', 'Waktu pengerjaan ujian telah berakhir. Lembar ujian Anda telah otomatis dikumpulkan.');
            return $this->redirect(base_url('siswa/exam/completed/' . $sessionToken));
        }

        $data = [
            'pageTitle'        => 'Ujian: ' . ($roomData['exam']['title'] ?? 'Ruang Ujian CBT'),
            'session'          => $roomData['session'],
            'exam'             => $roomData['exam'],
            'questions'        => $roomData['questions'],
            'savedAnswers'     => $roomData['saved_answers'],
            'remainingSeconds' => $roomData['remaining_seconds'],
            'counts'           => $roomData['counts'],
            'user'             => auth_user(),
            'sessionToken'     => $sessionToken
        ];

        return $this->view('siswa/exam/room', $data, 'layouts/exam');
    }

    /**
     * Submit Ujian (Manual & Auto-Submit saat Waktu Habis - UC-12)
     */
    public function submit(): Response
    {
        $studentId = (int) user_id();
        $sessionToken = (string) $this->request->input('session_token', '');

        if (empty($sessionToken)) {
            if ($this->request->isAjax()) {
                return $this->json(['success' => false, 'error' => 'Token sesi ujian tidak ditemukan.'], 422);
            }
            Session::setFlash('danger', 'Token sesi ujian tidak ditemukan.');
            return $this->redirect(base_url('siswa/exams'));
        }

        $result = $this->sessionService->submitExam($sessionToken, $studentId);

        if (!$result['success']) {
            if ($this->request->isAjax()) {
                return $this->json([
                    'success' => false,
                    'error'   => $result['error']
                ], 422);
            }

            Session::setFlash('danger', $result['error']);
            return $this->redirect(base_url('siswa/exam/room/' . $sessionToken));
        }

        $completedUrl = base_url('siswa/exam/completed/' . $sessionToken);

        if ($this->request->isAjax()) {
            return $this->json([
                'success'  => true,
                'redirect' => $completedUrl,
                'result'   => $result
            ]);
        }

        Session::setFlash('success', 'Ujian telah berhasil diselesaikan dan dikumpulkan.');
        return $this->redirect($completedUrl);
    }

    /**
     * Halaman Konfirmasi Ujian Selesai Dikumpulkan
     */
    public function completed(string $sessionToken): Response
    {
        $studentId = (int) user_id();
        $session = $this->sessionModel->findByToken($sessionToken);

        if (!$session || (int)$session['student_id'] !== $studentId) {
            Session::setFlash('danger', 'Sesi ujian tidak valid.');
            return $this->redirect(base_url('siswa/exams'));
        }

        $result = $this->resultModel->where('exam_session_id', $session['id'])->first();

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Ujian Selesai']
        ];

        $data = [
            'pageTitle'    => 'Ujian Berhasil Dikumpulkan',
            'session'      => $session,
            'result'       => $result,
            'breadcrumbs'  => $breadcrumbs
        ];

        return $this->view('siswa/exam/completed', $data, 'layouts/peserta');
    }
}
