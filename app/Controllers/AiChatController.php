<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AiChatMessageModel;
use App\Models\AiChatSessionModel;
use App\Services\AI\AiChatService;

/**
 * AI Chat Controller
 * Mengelola antarmuka dan endpoint AJAX untuk:
 * 1. Floating Chatbot Global (Scoped Data Access per Role: Admin, Guru, Siswa)
 * 2. Chatbot Penjelasan Tiap Soal (Post-Exam Question Explainer)
 */
class AiChatController extends BaseController
{
    protected AiChatService $aiChatService;
    protected AiChatSessionModel $sessionModel;
    protected AiChatMessageModel $messageModel;

    public function __construct()
    {
        parent::__construct();
        $this->aiChatService = new AiChatService();
        $this->sessionModel = new AiChatSessionModel();
        $this->messageModel = new AiChatMessageModel();
    }

    /**
     * =========================================================================
     * VIEW PAGES (Redirect to Dashboards where Floating Chat is available)
     * =========================================================================
     */

    /**
     * Redirect AI Assistant Full Page untuk Admin ke Dashboard
     */
    public function adminIndex(): Response
    {
        return $this->redirect(base_url('admin/dashboard'));
    }

    /**
     * Redirect AI Assistant Full Page untuk Guru ke Dashboard
     */
    public function guruIndex(): Response
    {
        return $this->redirect(base_url('guru/dashboard'));
    }

    /**
     * Redirect AI Assistant Full Page untuk Siswa ke Dashboard
     */
    public function siswaIndex(): Response
    {
        return $this->redirect(base_url('siswa/dashboard'));
    }

    /**
     * =========================================================================
     * TIPE 1: FLOATING CHATBOT GLOBAL API ENDPOINTS
     * =========================================================================
     */

    /**
     * POST AJAX: Kirim Pesan ke AI Chatbot Global
     */
    public function sendMessage(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi login tidak valid atau telah berakhir.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $postData = $this->request->getPost() ?: $_POST;
        $payload = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;

        $message = trim((string)($payload['message'] ?? ''));
        if ($message === '') {
            return $this->json(['success' => false, 'error' => 'Pesan tidak boleh kosong.'], 422);
        }

        $userRole = (string)(user_role() ?? 'siswa');
        if (in_array($userRole, ['super_admin', 'admin'], true)) {
            $roleContext = 'admin';
        } elseif ($userRole === 'guru') {
            $roleContext = 'guru';
        } else {
            $roleContext = 'siswa';
        }

        $sessionUuid = !empty($payload['session_uuid']) ? trim((string)$payload['session_uuid']) : null;

        // Otoritas Server: Validasi status EXAM_MODE dari database resmi, jangan percaya payload client
        $isExamMode = false;
        $examSessionId = null;

        if ($roleContext === 'siswa') {
            $requestedExamSessionId = !empty($payload['exam_session_id']) ? (int)$payload['exam_session_id'] : null;
            $verifiedExamSession = $this->aiChatService->resolveActiveStudentExamSession($userId, $requestedExamSessionId);
            if ($verifiedExamSession !== null) {
                $isExamMode = true;
                $examSessionId = (int)$verifiedExamSession['id'];
            }
        }

        $result = $this->aiChatService->sendMessage(
            $userId,
            $roleContext,
            $message,
            $sessionUuid,
            $isExamMode,
            $examSessionId
        );

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result, 200);
    }

    /**
     * GET AJAX: Ambil Riwayat Pesan Sesi Tertentu
     */
    public function getHistory(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi login tidak valid.'], 401);
        }

        $userRole = (string)(user_role() ?? 'siswa');
        if (in_array($userRole, ['super_admin', 'admin'], true)) {
            $roleContext = 'admin';
        } elseif ($userRole === 'guru') {
            $roleContext = 'guru';
        } else {
            $roleContext = 'siswa';
        }
        $sessionUuid = $this->request->getGet('session_uuid');

        $data = $this->aiChatService->getSessionHistory($userId, $roleContext, $sessionUuid);

        return $this->json([
            'success'  => true,
            'session'  => $data['session'],
            'messages' => $data['messages']
        ]);
    }

    /**
     * POST AJAX: Hapus Riwayat Chat / Mulai Sesi Baru
     */
    public function clearHistory(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi login tidak valid.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $postData = $this->request->getPost() ?: $_POST;
        $payload = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;

        $userRole = (string)(user_role() ?? 'siswa');
        if (in_array($userRole, ['super_admin', 'admin'], true)) {
            $roleContext = 'admin';
        } elseif ($userRole === 'guru') {
            $roleContext = 'guru';
        } else {
            $roleContext = 'siswa';
        }
        $sessionUuid = !empty($payload['session_uuid']) ? trim((string)$payload['session_uuid']) : null;

        $result = $this->aiChatService->clearSessionHistory($userId, $roleContext, $sessionUuid);

        return $this->json($result, 200);
    }

    /**
     * =========================================================================
     * TIPE 2: CHATBOT PENJELASAN TIAP SOAL (Post-Exam Question Explainer)
     * =========================================================================
     */

    /**
     * POST AJAX: Kirim Pertanyaan Pembahasan Butir Soal Tertentu
     */
    public function explainQuestion(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi login tidak valid atau telah berakhir.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $postData = $this->request->getPost() ?: $_POST;
        $payload = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;

        $answerId = (int)($payload['answer_id'] ?? 0);
        $message = trim((string)($payload['message'] ?? ''));
        $sessionUuid = !empty($payload['session_uuid']) ? trim((string)$payload['session_uuid']) : null;

        if ($answerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID Butir Jawaban (answer_id) tidak valid.'], 422);
        }

        if ($message === '') {
            $message = 'Tolong jelaskan secara mendalam konsep materi butir soal ini, mengapa kunci jawaban tersebut benar, serta analisis jawaban yang saya pilih.';
        }

        $result = $this->aiChatService->explainQuestionItem($userId, $answerId, $message, $sessionUuid);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result, 200);
    }

    /**
     * GET AJAX: Ambil Riwayat Chat Pembahasan Butir Soal Tertentu
     */
    public function getQuestionHistory(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi login tidak valid.'], 401);
        }

        $answerId = (int)$this->request->get('answer_id', 0);
        if ($answerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID Butir Jawaban (answer_id) tidak valid.'], 422);
        }

        $result = $this->aiChatService->getQuestionChatHistory($userId, $answerId);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result, 200);
    }

    /**
     * POST AJAX: Reset/Clear Riwayat Chat Pembahasan Butir Soal
     */
    public function clearQuestionHistory(): Response
    {
        $userId = (int)user_id();
        if ($userId <= 0) {
            return $this->json(['success' => false, 'error' => 'Sesi login tidak valid.'], 401);
        }

        $rawInput = file_get_contents('php://input');
        $jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;
        $postData = $this->request->getPost() ?: $_POST;
        $payload = is_array($jsonInput) && !empty($jsonInput) ? $jsonInput : $postData;

        $answerId = (int)($payload['answer_id'] ?? 0);
        if ($answerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID Butir Jawaban tidak valid.'], 422);
        }

        $result = $this->aiChatService->clearQuestionChatHistory($userId, $answerId);

        return $this->json($result, 200);
    }
}
