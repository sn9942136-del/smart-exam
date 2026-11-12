<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AiQuestionGenerationModel;
use App\Models\QuestionBankModel;
use App\Models\SubjectModel;
use App\Services\AI\AiQuestionService;

/**
 * AI Question Controller
 * Mengelola antarmuka dan API generasi butir soal otomatis berbasis AI (UC-21c),
 * peninjauan draf soal oleh guru, dan impor ke Bank Soal.
 */
class AiQuestionController extends BaseController
{
    protected AiQuestionService $aiService;
    protected AiQuestionGenerationModel $generationModel;
    protected QuestionBankModel $bankModel;
    protected SubjectModel $subjectModel;

    public function __construct()
    {
        parent::__construct();
        $this->aiService = new AiQuestionService();
        $this->generationModel = new AiQuestionGenerationModel();
        $this->bankModel = new QuestionBankModel();
        $this->subjectModel = new SubjectModel();
    }

    /**
     * Dashboard Generator AI & Riwayat Generasi Guru
     */
    public function index(): Response
    {
        $userId = (int)user_id();
        $userRole = (string)user_role();
        $preselectedBankId = (int)($this->request->getGet('bank_id') ?? 0);

        // Ambil daftar Bank Soal milik guru (atau semua jika admin)
        $creatorFilter = ($userRole === 'guru') ? $userId : null;
        $banks = $this->bankModel->getAllActiveByCreator($creatorFilter);
        $subjects = $this->subjectModel->getAllActive();

        // Riwayat generasi
        $page = (int)($this->request->getGet('page') ?? 1);
        $generations = $this->generationModel->getHistoryByTeacher($userId, max(1, $page), 10);
        $stats = $this->generationModel->getTeacherStats($userId);

        $selectedBank = null;
        if ($preselectedBankId > 0) {
            $selectedBank = $this->bankModel->find($preselectedBankId);
        }

        return $this->view('guru/ai_questions/index', [
            'banks'             => $banks,
            'subjects'          => $subjects,
            'generations'       => $generations['items'] ?? [],
            'pagination'        => $generations['pagination'] ?? [],
            'stats'             => $stats,
            'selectedBank'      => $selectedBank,
            'preselectedBankId' => $preselectedBankId,
            'title'             => 'AI Question Generator'
        ], 'layouts/guru');
    }

    /**
     * Form cepat generasi soal terikat ke Bank Soal tertentu
     */
    public function create(?int $bankId = null): Response
    {
        $bankId = $bankId ?? (int)($this->request->getGet('bank_id') ?? 0);
        return $this->redirect(base_url("/guru/ai-questions?bank_id={$bankId}"));
    }

    /**
     * POST AJAX: Jalankan AI Question Generator (UC-21c)
     */
    public function generate(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        // Ambil data POST (baik format JSON body, Request object, maupun global $_POST)
        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $postData = $this->request->getPost() ?: $_POST;
        $params = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;

        $bankId = (int)($params['question_bank_id'] ?? 0);
        if ($bankId <= 0) {
            return $this->json(['success' => false, 'error' => 'Pilih Bank Soal tujuan.'], 422);
        }

        // Validasi kepemilikan bank soal
        $bank = $this->bankModel->find($bankId);
        if (!$bank || (user_role() === 'guru' && (int)$bank['creator_id'] !== $userId)) {
            return $this->json(['success' => false, 'error' => 'Akses ditolak: Bank Soal tidak ditemukan atau bukan milik Anda.'], 403);
        }

        $topic = trim((string)($params['topic'] ?? ''));
        if (empty($topic)) {
            return $this->json(['success' => false, 'error' => 'Topik / Materi Pembelajaran wajib diisi.'], 422);
        }

        $result = $this->aiService->generateQuestions($params, $userId);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json([
            'success'       => true,
            'message'       => 'Draf soal AI berhasil dibuat. Silakan tinjau dan pilih butir soal.',
            'generation_id' => $result['generation_id'],
            'redirect_url'  => base_url("/guru/ai-questions/review/{$result['generation_id']}"),
            'summary'       => $result['summary'] ?? []
        ]);
    }

    /**
     * Halaman Workbench Peninjauan & Penyuntingan Draf Soal AI oleh Guru
     */
    public function review(int $id): Response
    {
        $userId = (int)user_id();
        $draft = $this->aiService->getDraftForReview($id, $userId);

        if (!$draft) {
            $this->setFlash('error', 'Draf generasi soal tidak ditemukan atau bukan milik Anda.');
            return $this->redirect(base_url('/guru/ai-questions'));
        }

        return $this->view('guru/ai_questions/review', [
            'draft' => $draft,
            'title' => 'Tinjau Draf Soal AI: ' . ($draft['subject_topic'] ?? 'Topik Soal')
        ], 'layouts/guru');
    }

    /**
     * POST AJAX: Simpan Butir Soal Terpilih & Disetujui Guru ke Bank Soal
     */
    public function import(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi tidak valid.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $postData = $this->request->getPost() ?: $_POST;
        $payload = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;


        $generationId = (int)($payload['generation_id'] ?? 0);
        $questions = $payload['questions'] ?? [];

        if ($generationId <= 0 || !is_array($questions) || empty($questions)) {
            return $this->json([
                'success' => false,
                'error'   => 'Tidak ada butir soal yang dipilih atau data tidak lengkap.'
            ], 422);
        }

        $result = $this->aiService->importApprovedQuestions($generationId, $questions, $userId);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json([
            'success'        => true,
            'message'        => $result['message'],
            'imported_count' => $result['imported_count'],
            'bank_url'       => base_url("/guru/question-banks/show/{$result['bank_id']}")
        ]);
    }

    /**
     * Hapus riwayat / draf generasi AI
     */
    public function delete(int $id): Response
    {
        $userId = (int)user_id();
        $batch = $this->generationModel->find($id);

        if ($batch && (int)$batch['user_id'] === $userId) {
            $this->generationModel->delete($id);
            $this->setFlash('success', 'Riwayat generasi AI berhasil dihapus.');
        } else {
            $this->setFlash('error', 'Riwayat generasi AI tidak ditemukan.');
        }

        return $this->redirect(base_url('/guru/ai-questions'));
    }
}
