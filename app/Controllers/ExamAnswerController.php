<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Services\ExamAnswerService;
use App\Services\ExamTimerService;

/**
 * Exam Answer Controller
 * Endpoint AJAX / Fetch API untuk Auto-Save jawaban butir soal dan sinkronisasi waktu ujian.
 */
class ExamAnswerController extends BaseController
{
    private ExamAnswerService $answerService;
    private ExamTimerService $timerService;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->answerService = new ExamAnswerService();
        $this->timerService = new ExamTimerService();
    }

    /**
     * Auto-Save Jawaban Butir Soal (UC-11)
     */
    public function save(): Response
    {
        $studentId = (int) user_id();
        $sessionToken = (string) $this->request->input('session_token', '');
        $questionId = (int) $this->request->input('question_id', 0);
        $selectedOptionId = $this->request->input('selected_option_id') !== null ? (int)$this->request->input('selected_option_id') : null;
        $essayAnswer = $this->request->input('essay_answer') !== null ? (string)$this->request->input('essay_answer') : null;
        $isFlagged = (int) $this->request->input('is_flagged', 0);

        if (empty($sessionToken) || $questionId <= 0) {
            return $this->json([
                'success' => false,
                'error'   => 'Parameter sesi atau butir soal tidak lengkap.'
            ], 422);
        }

        $result = $this->answerService->saveAnswer(
            $sessionToken,
            $studentId,
            $questionId,
            $selectedOptionId,
            $essayAnswer,
            $isFlagged
        );

        $status = $result['success'] ? 200 : 422;
        return $this->json($result, $status);
    }

    /**
     * Sinkronisasi Countdown Timer Server (UC-10)
     */
    public function syncTimer(string $sessionToken): Response
    {
        $studentId = (int) user_id();
        $result = $this->timerService->syncTimer($sessionToken, $studentId);

        $status = $result['success'] ? 200 : 422;
        return $this->json($result, $status);
    }
}
