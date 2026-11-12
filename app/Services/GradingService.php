<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\AuditLogModel;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\QuestionModel;
use App\Services\AI\AiQuestionService;
use Throwable;

/**
 * Grading Service
 * Mengelola koreksi otomatis pilihan ganda, penilaian esai manual guru (UC-23),
 * kalkulasi nilai akhir dan passing grade (KKM), penjelasan jawaban AI (UC-16a),
 * dan penyimpanan umpan balik guru.
 */
class GradingService
{
    protected ExamAnswerModel $answerModel;
    protected ExamResultModel $resultModel;
    protected ExamSessionModel $sessionModel;
    protected ExamModel $examModel;
    protected QuestionModel $questionModel;
    protected AiQuestionService $aiService;

    public function __construct()
    {
        $this->answerModel = new ExamAnswerModel();
        $this->resultModel = new ExamResultModel();
        $this->sessionModel = new ExamSessionModel();
        $this->examModel = new ExamModel();
        $this->questionModel = new QuestionModel();
        $this->aiService = new AiQuestionService();
    }

    /**
     * Koreksi otomatis seluruh soal pilihan ganda / objektif pada satu sesi ujian
     *
     * @param int $sessionId ID sesi ujian
     * @return array{total_mc: int, correct: int, wrong: int, mc_score: float}
     */
    public function autoGradeMultipleChoice(int $sessionId): array
    {
        $db = Database::getConnection();

        // Ambil semua jawaban peserta beserta data kunci soal
        $sql = "
            SELECT 
                ea.id as answer_id,
                ea.question_id,
                ea.selected_option_id,
                ea.essay_answer,
                q.type as question_type,
                q.weight,
                (SELECT qo.id FROM question_options qo WHERE qo.question_id = q.id AND qo.is_correct = 1 LIMIT 1) as correct_option_id
            FROM exam_answers ea
            JOIN questions q ON ea.question_id = q.id
            WHERE ea.exam_session_id = :session_id
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, \PDO::PARAM_INT);
        $stmt->execute();
        $answers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $totalMc = 0;
        $correct = 0;
        $wrong = 0;
        $mcScore = 0.0;

        foreach ($answers as $ans) {
            $type = $ans['question_type'];
            if (in_array($type, ['multiple_choice', 'single_choice', 'true_false'], true)) {
                $totalMc++;
                $selectedOpt = $ans['selected_option_id'] ? (int)$ans['selected_option_id'] : null;
                $correctOpt = $ans['correct_option_id'] ? (int)$ans['correct_option_id'] : null;

                $isCorrect = ($selectedOpt !== null && $correctOpt !== null && $selectedOpt === $correctOpt);
                $weight = (float)($ans['weight'] > 0 ? $ans['weight'] : 1.0);
                $earned = $isCorrect ? $weight : 0.0;

                if ($isCorrect) {
                    $correct++;
                    $mcScore += $earned;
                } else {
                    $wrong++;
                }

                $this->answerModel->update((int)$ans['answer_id'], [
                    'is_correct'   => $isCorrect ? 1 : 0,
                    'score_earned' => $earned,
                    'updated_at'   => date('Y-m-d H:i:s')
                ]);
            }
        }

        // Hitung ulang hasil total sesi ujian
        $this->calculateFinalScore($sessionId);

        return [
            'total_mc' => $totalMc,
            'correct'  => $correct,
            'wrong'    => $wrong,
            'mc_score' => $mcScore
        ];
    }

    /**
     * Koreksi manual butir soal esai oleh guru (UC-23)
     *
     * @param int $answerId ID baris exam_answers
     * @param float $score Nilai yang diberikan guru (0.0 s.d. bobot max soal)
     * @param string|null $feedback Komentar / catatan perbaikan dari guru
     * @param int $teacherId ID guru penilai
     * @return array{success: bool, message?: string, error?: string, final_score?: float}
     */
    public function gradeEssayAnswer(int $answerId, float $score, ?string $feedback, int $teacherId): array
    {
        $answer = $this->answerModel->find($answerId);
        if (!$answer) {
            return ['success' => false, 'error' => 'Data jawaban peserta tidak ditemukan.'];
        }

        $sessionId = (int)$answer['exam_session_id'];
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Sesi ujian tidak ditemukan.'];
        }

        $exam = $this->examModel->find((int)$session['exam_id']);
        if (!$exam) {
            return ['success' => false, 'error' => 'Ujian tidak ditemukan.'];
        }

        // Cek apakah nilai sudah dikunci/dipublikasikan
        $existingResult = $this->resultModel->where('exam_session_id', $sessionId)->first();
        if ($existingResult && $existingResult['status'] === 'published') {
            return [
                'success' => false, 
                'error'   => 'Nilai ujian ini telah dipublikasikan dan terkunci. Batalkan publikasi terlebih dahulu untuk mengubah nilai.'
            ];
        }

        $question = $this->questionModel->find((int)$answer['question_id']);
        $maxWeight = (float)($question['weight'] ?? 4.0);

        if ($score < 0 || $score > $maxWeight) {
            return [
                'success' => false, 
                'error'   => "Nilai esai tidak valid. Rentang yang diizinkan adalah 0 s.d. {$maxWeight}."
            ];
        }

        $isCorrect = ($score > 0) ? 1 : 0;

        $this->answerModel->update($answerId, [
            'score_earned'     => $score,
            'is_correct'       => $isCorrect,
            'teacher_feedback' => !empty($feedback) ? trim($feedback) : null,
            'updated_at'       => date('Y-m-d H:i:s')
        ]);

        // Rekalkulasi nilai akhir sesi
        $calc = $this->calculateFinalScore($sessionId);

        // Catat audit log penilaian
        AuditLogModel::record(
            'GRADE_ESSAY',
            'exam_answers',
            (string)$answerId,
            null,
            [
                'session_id'   => $sessionId,
                'question_id'  => $answer['question_id'],
                'score_earned' => $score,
                'max_weight'   => $maxWeight,
                'teacher_id'   => $teacherId
            ]
        );

        return [
            'success'     => true,
            'message'     => 'Nilai esai berhasil disimpan.',
            'score'       => $score,
            'final_score' => $calc['final_score'] ?? 0.0
        ];
    }

    /**
     * Hitung akumulasi nilai akhir, perbandingan KKM / Passing Grade, dan status kelulusan
     *
     * @param int $sessionId ID sesi ujian
     * @return array
     */
    public function calculateFinalScore(int $sessionId): array
    {
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return [];
        }

        $examId = (int)$session['exam_id'];
        $studentId = (int)$session['student_id'];
        $exam = $this->examModel->find($examId);
        $passingGrade = (float)($exam['passing_grade'] ?? 75.0);

        $db = Database::getConnection();

        // 1. Ambil data akumulasi dari seluruh jawaban pada sesi ini
        $sql = "
            SELECT 
                COUNT(*) as total_questions,
                SUM(CASE WHEN ea.selected_option_id IS NOT NULL OR (ea.essay_answer IS NOT NULL AND TRIM(ea.essay_answer) != '') THEN 1 ELSE 0 END) as total_answered,
                SUM(CASE WHEN q.type != 'essay' AND ea.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                SUM(CASE WHEN q.type != 'essay' AND (ea.is_correct = 0 OR ea.selected_option_id IS NULL) THEN 1 ELSE 0 END) as wrong_answers,
                SUM(CASE WHEN q.type != 'essay' THEN COALESCE(ea.score_earned, 0) ELSE 0 END) as mc_score,
                SUM(CASE WHEN q.type = 'essay' THEN COALESCE(ea.score_earned, 0) ELSE 0 END) as essay_score,
                SUM(COALESCE(q.weight, 1)) as total_max_weight,
                SUM(CASE WHEN q.type = 'essay' AND ea.is_correct IS NULL AND (ea.score_earned = 0 OR ea.score_earned IS NULL) AND ea.teacher_feedback IS NULL THEN 1 ELSE 0 END) as uncorrected_essays
            FROM exam_answers ea
            JOIN questions q ON ea.question_id = q.id
            WHERE ea.exam_session_id = :session_id
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, \PDO::PARAM_INT);
        $stmt->execute();
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $totalQuestions = (int)($stats['total_questions'] ?? 0);
        $totalAnswered = (int)($stats['total_answered'] ?? 0);
        $correctAnswers = (int)($stats['correct_answers'] ?? 0);
        $wrongAnswers = (int)($stats['wrong_answers'] ?? 0);
        $mcScore = (float)($stats['mc_score'] ?? 0.0);
        $essayScore = (float)($stats['essay_score'] ?? 0.0);
        $totalEarned = $mcScore + $essayScore;
        $maxWeight = (float)($stats['total_max_weight'] ?? 1.0);

        // Konversi ke skala 100
        $finalScore = ($maxWeight > 0) ? round(($totalEarned / $maxWeight) * 100, 2) : 0.0;
        $finalScore = min(100.0, max(0.0, $finalScore));

        // Kelulusan berdasarkan passing grade
        $isPassed = ($finalScore >= $passingGrade) ? 1 : 0;

        // Status penilaian
        $uncorrectedEssays = (int)($stats['uncorrected_essays'] ?? 0);
        $status = ($uncorrectedEssays > 0) ? 'pending_grading' : 'graded';

        // Nilai integritas pengawasan AI
        $integrityScore = (float)($session['integrity_score'] ?? 100.0);

        // Simpan / update record exam_results
        $existing = $this->resultModel->where('exam_session_id', $sessionId)->first();

        $resultPayload = [
            'exam_session_id'            => $sessionId,
            'exam_id'                    => $examId,
            'student_id'                 => $studentId,
            'total_questions'            => $totalQuestions,
            'total_answered'             => $totalAnswered,
            'correct_answers'            => $correctAnswers,
            'wrong_answers'              => $wrongAnswers,
            'multiple_choice_score'      => $mcScore,
            'essay_score'                => $essayScore,
            'final_score'                => $finalScore,
            'proctoring_integrity_score' => $integrityScore,
            'is_passed'                  => $isPassed,
            'status'                     => ($existing && $existing['status'] === 'published') ? 'published' : $status,
            'graded_at'                  => date('Y-m-d H:i:s'),
            'updated_at'                 => date('Y-m-d H:i:s')
        ];

        if ($existing) {
            $this->resultModel->update((int)$existing['id'], $resultPayload);
            $resultId = (int)$existing['id'];
        } else {
            $resultPayload['created_at'] = date('Y-m-d H:i:s');
            $resultId = (int)$this->resultModel->insert($resultPayload);
        }

        return [
            'result_id'       => $resultId,
            'final_score'     => $finalScore,
            'is_passed'       => $isPassed,
            'status'          => $status,
            'passing_grade'   => $passingGrade,
            'mc_score'        => $mcScore,
            'essay_score'     => $essayScore
        ];
    }

    /**
     * Simpan umpan balik / catatan evaluasi guru untuk satu hasil ujian siswa
     *
     * @param int $resultId ID exam_results
     * @param string $feedback Komentar guru
     * @param int $teacherId ID guru
     * @return array{success: bool, message?: string, error?: string}
     */
    public function saveFeedback(int $resultId, string $feedback, int $teacherId): array
    {
        $result = $this->resultModel->find($resultId);
        if (!$result) {
            return ['success' => false, 'error' => 'Data hasil ujian tidak ditemukan.'];
        }

        $this->resultModel->update($resultId, [
            'teacher_feedback' => trim($feedback),
            'updated_at'       => date('Y-m-d H:i:s')
        ]);

        AuditLogModel::record(
            'SAVE_TEACHER_FEEDBACK',
            'exam_results',
            (string)$resultId,
            null,
            ['teacher_id' => $teacherId, 'feedback' => substr($feedback, 0, 100)]
        );

        return ['success' => true, 'message' => 'Umpan balik guru berhasil disimpan.'];
    }

    /**
     * Generate penjelasan jawaban cerdas berbasis AI untuk butir soal ujian (UC-16a)
     *
     * @param int $answerId ID exam_answers
     * @param int $teacherId ID guru yang meminta / sistem
     * @return array{success: bool, ai_explanation?: string, error?: string}
     */
    public function generateAiAnswerExplanation(int $answerId, int $teacherId): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT 
                ea.*,
                q.content as question_text,
                q.type as question_type,
                q.explanation as key_explanation,
                qo.content as selected_option_text,
                (SELECT content FROM question_options WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_option_text
            FROM exam_answers ea
            JOIN questions q ON ea.question_id = q.id
            LEFT JOIN question_options qo ON ea.selected_option_id = qo.id
            WHERE ea.id = :id

        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $answerId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'error' => 'Data jawaban tidak ditemukan.'];
        }

        // Jika sudah ada pembahasan soal dari bank soal, gunakan sebagai basis
        $prompt = "Sebagai asisten tutor AI, jelaskan secara mendalam mengapa jawaban siswa ini ";
        $prompt .= ($row['is_correct'] ? 'BENAR (Tepat)' : 'BELUM TEPAT (Kurang Tepat)') . " berdasarkan data resmi butir soal dan kunci jawaban database berikut:\n";
        $prompt .= "Pertanyaan Soal: " . $row['question_text'] . "\n";
        
        if ($row['question_type'] !== 'essay') {
            $prompt .= "Pilihan Jawaban Siswa: " . ($row['selected_option_text'] ?? 'Tidak menjawab') . "\n";
            $prompt .= "Kunci Jawaban Benar Resmi (Database): " . ($row['correct_option_text'] ?? '-') . "\n";
        } else {
            $prompt .= "Jawaban Esai Siswa: " . ($row['essay_answer'] ?? '-') . "\n";
        }

        if (!empty($row['key_explanation'])) {
            $prompt .= "Penjelasan Resmi Kurikulum/Guru (Database): " . $row['key_explanation'] . "\n";
        }

        $prompt .= "\nPANDUAN: Penjelasan HARUS 100% konsisten dengan data kunci jawaban database di atas. Berikan penjelasan ramah, konstruktif, dan edukatif dalam 2-3 paragraf singkat.";

        $provider = $this->aiService->resolveProvider();
        $completion = $provider->generateCompletion($prompt, [
            'temperature'        => 0.4,
            'max_tokens'         => 800,
            'system_instruction' => "Anda adalah Guru Ahli yang memberikan penjelasan pembahasan soal edukatif dan konstruktif yang wajib 100% konsisten dan merujuk pada data resmi butir soal dan kunci jawaban dari database."
        ]);

        $explanationText = '';
        if ($completion['success'] && !empty($completion['content'])) {
            // Bersihkan format JSON jika provider mock merespon format raw
            $raw = $completion['content'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['explanation'])) {
                    $explanationText = $decoded['explanation'];
                } else {
                    $explanationText = trim($raw);
                }
            }
        }

        if (empty($explanationText)) {
            $explanationText = !empty($row['key_explanation']) 
                ? $row['key_explanation'] 
                : "Pembahasan AI: Konsep dasar pada soal ini menguji pemahaman materi terkait. Jawaban yang benar adalah '{$row['correct_option_text']}'.";
        }

        $this->answerModel->update($answerId, [
            'ai_explanation' => $explanationText,
            'updated_at'     => date('Y-m-d H:i:s')
        ]);

        return [
            'success'        => true,
            'ai_explanation' => $explanationText
        ];
    }
}
