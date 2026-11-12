<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\ExamModel;
use App\Services\GradingService;
use App\Services\ResultService;

/**
 * Grading Controller
 * Mengelola antarmuka penilaian hasil ujian guru, koreksi esai manual (UC-23),
 * koreksi otomatis pilihan ganda, dan rilis publikasi nilai ujian (UC-24).
 */
class GradingController extends BaseController
{
    protected GradingService $gradingService;
    protected ResultService $resultService;
    protected ExamModel $examModel;

    public function __construct()
    {
        parent::__construct();
        $this->gradingService = new GradingService();
        $this->resultService = new ResultService();
        $this->examModel = new ExamModel();
    }

    /**
     * Dashboard Penilaian & Daftar Ujian Guru
     */
    public function index(): Response
    {
        $userId = (int)user_id();
        $userRole = (string)user_role();

        $creatorFilter = ($userRole === 'guru') ? $userId : null;
        $exams = $this->examModel->getAllWithStats($creatorFilter);
        $summary = $this->resultService->getTeacherGradingSummary($userId);

        return $this->view('guru/grading/index', [
            'exams'   => $exams,
            'summary' => $summary,
            'title'   => 'Penilaian & Koreksi Ujian'
        ], 'layouts/guru');
    }

    /**
     * Daftar Penyerahan Jawaban Peserta per Ujian
     */
    /**
     * Daftar Penyerahan Jawaban Peserta per Ujian
     */
    public function examSubmissions(int $id): Response
    {
        $exam = $this->examModel->find($id);
        if (!$exam) {
            $this->setFlash('error', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url('/guru/grading'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            $this->setFlash('error', 'Akses ditolak: Anda hanya dapat melihat hasil ujian yang Anda ampu.');
            return $this->redirect(base_url('/guru/grading'));
        }

        $statusFilter = $this->request->getGet('status');
        $search = $this->request->getGet('search');

        $data = $this->resultService->getExamSubmissions($id, $statusFilter, $search);

        return $this->view('guru/grading/exam_submissions', [
            'exam'         => $data['exam'],
            'submissions'  => $data['submissions'],
            'summary'      => $data['summary'],
            'statusFilter' => $statusFilter,
            'search'       => $search,
            'title'        => 'Hasil Ujian: ' . $data['exam']['title']
        ], 'layouts/guru');
    }

    /**
     * Meja Kerja Koreksi Esai & Review Jawaban Siswa (UC-23)
     */
    public function gradeSession(int $id): Response
    {
        $data = $this->resultService->getSessionAnswersForGrading($id);

        if (!$data) {
            $this->setFlash('error', 'Sesi ujian siswa tidak ditemukan.');
            return $this->redirect(base_url('/guru/grading'));
        }

        if (user_role() === 'guru' && (int)$data['header']['teacher_id'] !== (int)user_id()) {
            $this->setFlash('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk menilai sesi ujian ini.');
            return $this->redirect(base_url('/guru/grading'));
        }

        return $this->view('guru/grading/grade_session', [
            'header'  => $data['header'],
            'answers' => $data['answers'],
            'title'   => 'Koreksi Lembar Jawaban: ' . $data['header']['student_name']
        ], 'layouts/guru');
    }

    /**
     * POST AJAX: Simpan Nilai Esai Manual & Feedback Guru (UC-23)
     */
    public function saveEssayScore(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $params = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : ($this->request->getPost() ?: $_POST);

        $answerId = (int)($params['answer_id'] ?? 0);
        $score = (float)($params['score'] ?? 0.0);
        $feedback = (string)($params['feedback'] ?? '');

        if ($answerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID Jawaban tidak valid.'], 422);
        }

        if (user_role() === 'guru') {
            $answer = (new \App\Models\ExamAnswerModel())->find($answerId);
            if (!$answer) {
                return $this->json(['success' => false, 'error' => 'Jawaban tidak ditemukan.'], 404);
            }
            $session = (new \App\Models\ExamSessionModel())->find((int)$answer['exam_session_id']);
            $exam = $session ? $this->examModel->find((int)$session['exam_id']) : null;
            if (!$exam || (int)$exam['teacher_id'] !== $userId) {
                return $this->json(['success' => false, 'error' => 'Akses ditolak: Anda tidak berwenang menilai jawaban ini.'], 403);
            }
        }

        $result = $this->gradingService->gradeEssayAnswer($answerId, $score, $feedback, $userId);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result);
    }

    /**
     * POST AJAX: Jalankan Koreksi Otomatis Pilihan Ganda Massal
     */
    public function triggerAutoGrading(int $examId): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        $exam = $this->examModel->find($examId);
        if (!$exam) {
            return $this->json(['success' => false, 'error' => 'Ujian tidak ditemukan.'], 404);
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== $userId) {
            return $this->json(['success' => false, 'error' => 'Akses ditolak: Anda tidak berwenang mengoreksi ujian ini.'], 403);
        }

        $submissionsData = $this->resultService->getExamSubmissions($examId);
        $count = 0;

        foreach ($submissionsData['submissions'] as $sub) {
            $this->gradingService->autoGradeMultipleChoice((int)$sub['session_id']);
            $count++;
        }

        return $this->json([
            'success' => true,
            'message' => "Koreksi otomatis berhasil dijalankan untuk {$count} peserta."
        ]);
    }

    /**
     * POST AJAX: Publikasikan Hasil Ujian Resmi (UC-24)
     */
    public function publishResults(int $examId): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        $exam = $this->examModel->find($examId);
        if (!$exam) {
            return $this->json(['success' => false, 'error' => 'Ujian tidak ditemukan.'], 404);
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== $userId) {
            return $this->json(['success' => false, 'error' => 'Akses ditolak: Anda tidak berwenang mempublikasikan ujian ini.'], 403);
        }

        $result = $this->resultService->publishExamResults($examId, $userId);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result);
    }

    /**
     * POST AJAX: Simpan Umpan Balik Guru untuk Hasil Sesi
     */
    public function saveFeedback(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $params = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : ($this->request->getPost() ?: $_POST);

        $resultId = (int)($params['result_id'] ?? 0);
        $feedback = (string)($params['feedback'] ?? '');

        if ($resultId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID Hasil Ujian tidak valid.'], 422);
        }

        if (user_role() === 'guru') {
            $resultModel = new \App\Models\ExamResultModel();
            $examResult = $resultModel->find($resultId);
            if (!$examResult) {
                return $this->json(['success' => false, 'error' => 'Hasil ujian tidak ditemukan.'], 404);
            }
            $exam = $this->examModel->find((int)$examResult['exam_id']);
            if (!$exam || (int)$exam['teacher_id'] !== $userId) {
                return $this->json(['success' => false, 'error' => 'Akses ditolak: Anda tidak berwenang memberi umpan balik pada hasil ujian ini.'], 403);
            }
        }

        $result = $this->gradingService->saveFeedback($resultId, $feedback, $userId);
        return $this->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST AJAX: Generate Penjelasan Jawaban Cerdas AI (UC-16a)
     */
    public function generateAiExplanation(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $params = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : ($this->request->getPost() ?: $_POST);

        $answerId = (int)($params['answer_id'] ?? 0);
        if ($answerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID Jawaban tidak valid.'], 422);
        }

        if (user_role() === 'guru') {
            $answer = (new \App\Models\ExamAnswerModel())->find($answerId);
            if (!$answer) {
                return $this->json(['success' => false, 'error' => 'Jawaban tidak ditemukan.'], 404);
            }
            $session = (new \App\Models\ExamSessionModel())->find((int)$answer['exam_session_id']);
            $exam = $session ? $this->examModel->find((int)$session['exam_id']) : null;
            if (!$exam || (int)$exam['teacher_id'] !== $userId) {
                return $this->json(['success' => false, 'error' => 'Akses ditolak: Anda tidak berwenang mengakses penjelasan AI untuk ujian ini.'], 403);
            }
        }

        $result = $this->gradingService->generateAiAnswerExplanation($answerId, $userId);
        return $this->json($result, $result['success'] ? 200 : 400);
    }
}
