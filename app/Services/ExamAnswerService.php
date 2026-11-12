<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamAnswerModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamSessionModel;
use App\Models\QuestionOptionModel;

/**
 * Exam Answer Service
 * Mengelola penyimpanan jawaban (Auto-Save), validasi kepemilikan sesi & relasi data (Data Ownership),
 * sinkronisasi sisa waktu server, dan rekonsiliasi status pengerjaan butir soal.
 */
class ExamAnswerService
{
    private ExamAnswerModel $answerModel;
    private ExamSessionModel $sessionModel;
    private ExamQuestionModel $examQuestionModel;
    private QuestionOptionModel $optionModel;
    private ExamTimerService $timerService;

    public function __construct()
    {
        $this->answerModel = new ExamAnswerModel();
        $this->sessionModel = new ExamSessionModel();
        $this->examQuestionModel = new ExamQuestionModel();
        $this->optionModel = new QuestionOptionModel();
        $this->timerService = new ExamTimerService();
    }

    /**
     * Simpan jawaban butir soal secara instan (Auto-Save)
     * Menggunakan otoritas Server-Side Timer, Session Validation, & Relational Data Ownership
     *
     * @return array{success: bool, saved_at?: string, question_id?: int, counts?: array, remaining_seconds?: int, is_expired?: bool, code?: string, error?: string}
     */
    public function saveAnswer(
        string $sessionToken,
        int $studentId,
        int $questionId,
        ?int $selectedOptionId = null,
        ?string $essayAnswer = null,
        int $isFlagged = 0
    ): array {
        // 1. Validasi Sesi Aktif, Kepemilikan Sesi, & Otoritas Deadline Server-Side
        $validation = $this->timerService->validateActiveExamSession($sessionToken, $studentId);
        if (!$validation['valid']) {
            return [
                'success'    => false,
                'code'       => $validation['code'],
                'is_expired' => $validation['is_expired'] ?? false,
                'error'      => $validation['error'] ?? 'Sesi ujian tidak valid.'
            ];
        }

        $session = $validation['session'];
        $examId = (int)$session['exam_id'];

        // 2. Data Ownership: Validasi apakah question_id terdaftar pada ujian ini (exam_questions)
        if ($questionId <= 0 || !$this->examQuestionModel->isQuestionInExam($examId, $questionId)) {
            return [
                'success' => false,
                'code'    => 'INVALID_QUESTION',
                'error'   => 'Akses ditolak: Butir soal tidak terdaftar dalam paket ujian ini.'
            ];
        }

        // 3. Data Ownership: Jika selected_option_id dikirim, validasi apakah opsi tersebut milik butir soal ini (question_options)
        if ($selectedOptionId !== null && $selectedOptionId > 0) {
            if (!$this->optionModel->isOptionBelongsToQuestion($questionId, $selectedOptionId)) {
                return [
                    'success' => false,
                    'code'    => 'INVALID_OPTION',
                    'error'   => 'Akses ditolak: Pilihan jawaban yang dikirim tidak sesuai dengan butir soal yang bersangkutan.'
                ];
            }
        }

        // 4. Simpan atau perbarui jawaban ke database
        $saved = $this->answerModel->upsertAnswer(
            (int)$session['id'],
            $questionId,
            $selectedOptionId,
            $essayAnswer,
            $isFlagged
        );

        if (!$saved) {
            return [
                'success' => false,
                'code'    => 'DATABASE_ERROR',
                'error'   => 'Gagal menyimpan jawaban ke basis data.'
            ];
        }

        // 5. Ambil ringkasan jawaban terkini
        $counts = $this->answerModel->countAnsweredQuestions((int)$session['id']);

        return [
            'success'           => true,
            'saved_at'          => date('H:i:s'),
            'question_id'       => $questionId,
            'counts'            => $counts,
            'remaining_seconds' => $validation['remaining_seconds']
        ];
    }

    /**
     * Dapatkan seluruh jawaban tersimpan untuk sesi ujian
     */
    public function getSavedAnswers(int $sessionId): array
    {
        return $this->answerModel->getAnswersBySessionId($sessionId);
    }
}
