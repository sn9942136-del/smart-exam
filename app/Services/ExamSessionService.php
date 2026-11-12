<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\AuditLogModel;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use App\Models\ExamTokenModel;
use App\Models\NotificationModel;
use PDO;
use Throwable;

/**
 * Exam Session Service
 * Mengelola siklus hidup pengerjaan ujian online, verifikasi token,
 * rekoneksi sesi (UC-14), dan submit transaksi atomik (UC-12).
 */
class ExamSessionService
{
    private ExamSessionModel $sessionModel;
    private ExamModel $examModel;
    private ExamParticipantModel $participantModel;
    private ExamTokenModel $tokenModel;
    private ExamQuestionModel $examQuestionModel;
    private ExamAnswerModel $answerModel;
    private ExamResultModel $resultModel;
    private ExamTimerService $timerService;
    private NotificationService $notificationService;
    private NotificationModel $notificationModel;

    public function __construct(
        ?ExamSessionModel $sessionModel = null,
        ?ExamModel $examModel = null,
        ?ExamParticipantModel $participantModel = null,
        ?ExamTokenModel $tokenModel = null,
        ?ExamQuestionModel $examQuestionModel = null,
        ?ExamAnswerModel $answerModel = null,
        ?ExamResultModel $resultModel = null,
        ?ExamTimerService $timerService = null,
        ?NotificationService $notificationService = null,
        ?NotificationModel $notificationModel = null
    ) {
        $this->sessionModel = $sessionModel ?? new ExamSessionModel();
        $this->examModel = $examModel ?? new ExamModel();
        $this->participantModel = $participantModel ?? new ExamParticipantModel();
        $this->tokenModel = $tokenModel ?? new ExamTokenModel();
        $this->examQuestionModel = $examQuestionModel ?? new ExamQuestionModel();
        $this->answerModel = $answerModel ?? new ExamAnswerModel();
        $this->resultModel = $resultModel ?? new ExamResultModel();
        $this->timerService = $timerService ?? new ExamTimerService();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->notificationModel = $notificationModel ?? new NotificationModel();
    }

    /**
     * Memulai atau memulihkan sesi ujian siswa (UC-14: Rekoneksi Sesi)
     *
     * @return array{success: bool, resumed?: bool, session_token?: string, error?: string}
     */
    public function startOrResumeSession(
        int $examId,
        int $studentId,
        string $tokenCode,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        // 1. Periksa apakah sudah ada sesi aktif yang sedang berjalan (UC-14 Reconnect)
        $activeSession = $this->sessionModel->findActiveSession($examId, $studentId);
        if ($activeSession) {
            // Validasi apakah sesi ongoing masih dalam batas waktu server
            $timerValidation = $this->timerService->validateActiveExamSession($activeSession['session_token'], $studentId);
            if (!$timerValidation['valid']) {
                return [
                    'success' => false,
                    'error'   => $timerValidation['error'] ?? 'Sesi ujian Anda telah kedaluwarsa atau tidak valid.'
                ];
            }

            return [
                'success'       => true,
                'resumed'       => true,
                'session_token' => $activeSession['session_token']
            ];
        }

        // 2. Validasi Akses Siswa & Jadwal Kelas
        $validation = $this->participantModel->validateStudentAccess($examId, $studentId);
        if (!$validation['allowed']) {
            return [
                'success' => false,
                'error'   => $validation['reason'] ?? 'Akses ujian ditolak.'
            ];
        }

        // 3. Verifikasi Keabsahan Token Ujian
        $isTokenValid = $this->tokenModel->validateToken($examId, $tokenCode);
        if (!$isTokenValid) {
            return [
                'success' => false,
                'error'   => 'Token ujian tidak valid atau telah kedaluwarsa. Silakan minta token aktif kepada Pengawas Ruang.'
            ];
        }

        $exam = $validation['exam'] ?? $this->examModel->find($examId);
        $durationMinutes = (int)($exam['duration_minutes'] ?? 60);

        // 4. Inisialisasi Sesi Baru
        $newSession = $this->sessionModel->createSession($examId, $studentId, $durationMinutes, $ip, $userAgent);

        // Update status partisipan menjadi in_progress
        $db = Database::getConnection();
        $updPartSql = "UPDATE exam_participants 
                       SET status = 'in_progress', updated_at = NOW() 
                       WHERE exam_id = :exam_id AND student_id = :student_id AND status = 'not_started'";
        $updStmt = $db->prepare($updPartSql);
        $updStmt->execute([':exam_id' => $examId, ':student_id' => $studentId]);

        // Audit Trail
        AuditLogModel::record('EXAM_START', 'exam_sessions', (string)$newSession['id'], null, [
            'exam_id'    => $examId,
            'student_id' => $studentId,
            'ip'         => $ip
        ]);

        return [
            'success'       => true,
            'resumed'       => false,
            'session_token' => $newSession['session_token']
        ];
    }

    /**
     * Dapatkan seluruh data ruang ujian untuk antarmuka siswa (UC-10)
     */
    public function getExamRoomData(string $sessionToken, int $studentId): ?array
    {
        $session = $this->sessionModel->findByToken($sessionToken);
        if (!$session || (int)$session['student_id'] !== $studentId) {
            return null;
        }

        $examId = (int)$session['exam_id'];
        $exam = $this->examModel->getDetailsWithFullRelations($examId);
        if (!$exam) {
            return null;
        }

        // Ambil butir soal lengkap dengan pilihan jawaban
        $questions = $this->examQuestionModel->getQuestionsByExamId($examId);

        // Keamanan: Pastikan kunci jawaban (is_correct) dan pembahasan (explanation) dihapus dari payload peserta
        foreach ($questions as &$q) {
            unset($q['explanation']);
            if (!empty($q['options']) && is_array($q['options'])) {
                foreach ($q['options'] as &$opt) {
                    unset($opt['is_correct']);
                }
                unset($opt);
            }
        }
        unset($q);

        // Ambil seluruh jawaban yang telah tersimpan
        $savedAnswers = $this->answerModel->getAnswersBySessionId((int)$session['id']);

        // Sinkronisasi sisa waktu server
        $timerSync = $this->timerService->syncTimer($sessionToken, $studentId);
        $remainingSeconds = $timerSync['remaining_seconds'];

        $counts = $this->answerModel->countAnsweredQuestions((int)$session['id']);

        return [
            'session'           => $session,
            'exam'              => $exam,
            'questions'         => $questions,
            'saved_answers'     => $savedAnswers,
            'remaining_seconds' => $remainingSeconds,
            'counts'            => $counts,
            'is_expired'        => $timerSync['is_expired']
        ];
    }

    /**
     * Submit ujian secara atomik dengan penilaian otomatis pilihan ganda (UC-12)
     *
     * @return array{success: bool, already_submitted?: bool, result_id?: int, final_score?: float, is_passed?: bool, error?: string}
     */
    public function submitExam(string $sessionToken, int $studentId): array
    {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // 1. Lock baris sesi ujian dengan FOR UPDATE untuk mencegah double submit / race condition
            $lockSql = "SELECT * FROM exam_sessions WHERE session_token = :token FOR UPDATE";
            $lockStmt = $db->prepare($lockSql);
            $lockStmt->bindValue(':token', $sessionToken);
            $lockStmt->execute();
            $session = $lockStmt->fetch();

            if (!$session) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Sesi ujian tidak ditemukan.'];
            }

            if ((int)$session['student_id'] !== $studentId) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Akses ditolak: Sesi ujian milik peserta lain.'];
            }

            // 2. Cegah submit ganda (Idempotent check)
            if ($session['status'] === 'submitted') {
                $db->rollBack();
                $existingResult = $this->resultModel->where('exam_session_id', $session['id'])->first();
                return [
                    'success'           => true,
                    'already_submitted' => true,
                    'result_id'         => (int)($existingResult['id'] ?? 0),
                    'final_score'       => (float)($existingResult['final_score'] ?? 0),
                    'is_passed'         => (bool)($existingResult['is_passed'] ?? false)
                ];
            }

            if ($session['status'] === 'terminated') {
                $db->rollBack();
                return [
                    'success' => false,
                    'error'   => 'Akses ditolak: Sesi ujian telah dihentikan/didiskualifikasi oleh pengawas ruangan.'
                ];
            }

            if ($session['status'] !== 'ongoing') {
                $db->rollBack();
                return [
                    'success' => false,
                    'error'   => 'Status sesi ujian tidak valid untuk pengumpulan (' . ($session['status'] ?? 'unknown') . ').'
                ];
            }

            $sessionId = (int)$session['id'];
            $examId = (int)$session['exam_id'];

            // 3. Update status sesi menjadi submitted
            $updSessionSql = "UPDATE exam_sessions 
                              SET status = 'submitted', submit_time = NOW(), remaining_seconds = 0, updated_at = NOW() 
                              WHERE id = :id";
            $updSessionStmt = $db->prepare($updSessionSql);
            $updSessionStmt->execute([':id' => $sessionId]);

            // 4. Ambil data ujian & butir soal untuk penilaian otomatis
            $exam = $this->examModel->find($examId);
            $questions = $this->examQuestionModel->getQuestionsByExamId($examId);
            $savedAnswers = $this->answerModel->getAnswersBySessionId($sessionId);

            $totalQuestions = count($questions);
            $totalAnswered = 0;
            $correctAnswers = 0;
            $wrongAnswers = 0;

            $totalMaxScore = 0.0;
            $mcEarnedScore = 0.0;
            $hasEssay = false;

            foreach ($questions as $q) {
                $qid = (int)$q['question_id'];
                $qType = $q['question_type'] ?? 'multiple_choice';
                $weight = (float)($q['score_weight'] ?? 1.0);
                $totalMaxScore += $weight;

                $ans = $savedAnswers[$qid] ?? null;

                if ($qType === 'essay') {
                    $hasEssay = true;
                    if ($ans && !empty(trim((string)($ans['essay_answer'] ?? '')))) {
                        $totalAnswered++;
                    }
                } else {
                    // Pilihan ganda, single choice, true/false
                    $selectedOptId = $ans ? (int)($ans['selected_option_id'] ?? 0) : 0;
                    $isCorrect = 0;
                    $scoreEarned = 0.0;

                    if ($selectedOptId > 0) {
                        $totalAnswered++;
                        // Periksa opsi yang benar
                        foreach ($q['options'] as $opt) {
                            if ((int)$opt['id'] === $selectedOptId && (int)$opt['is_correct'] === 1) {
                                $isCorrect = 1;
                                $scoreEarned = $weight;
                                break;
                            }
                        }
                    }

                    if ($isCorrect === 1) {
                        $correctAnswers++;
                        $mcEarnedScore += $scoreEarned;
                    } elseif ($selectedOptId > 0) {
                        $wrongAnswers++;
                    }

                    // Simpan evaluasi butir soal ke exam_answers
                    if ($ans) {
                        $updAnsSql = "UPDATE exam_answers 
                                      SET is_correct = :is_correct, score_earned = :score_earned, updated_at = NOW() 
                                      WHERE id = :id";
                        $updAnsStmt = $db->prepare($updAnsSql);
                        $updAnsStmt->execute([
                            ':is_correct'   => $isCorrect,
                            ':score_earned' => $scoreEarned,
                            ':id'           => (int)$ans['id']
                        ]);
                    }
                }
            }

            // Hitung skala nilai akhir 0 - 100
            $finalScore = ($totalMaxScore > 0) ? round(($mcEarnedScore / $totalMaxScore) * 100, 2) : 0.0;
            $passingGrade = (float)($exam['passing_grade'] ?? 75.0);
            $isPassed = ($finalScore >= $passingGrade) ? 1 : 0;

            // Tentukan status hasil
            if ($hasEssay) {
                $resultStatus = 'pending_grading';
            } elseif ((int)($exam['show_result_immediately'] ?? 0) === 1) {
                $resultStatus = 'published';
            } else {
                $resultStatus = 'graded';
            }

            // 5. Simpan ke exam_results
            $insResultSql = "
                INSERT INTO exam_results 
                    (exam_session_id, exam_id, student_id, total_questions, total_answered, correct_answers, wrong_answers,
                     multiple_choice_score, essay_score, final_score, proctoring_integrity_score, is_passed, status, graded_at, created_at, updated_at)
                VALUES 
                    (:sess_id, :exam_id, :student_id, :total_q, :total_ans, :correct, :wrong,
                     :mc_score, 0.00, :final_score, :integrity, :is_passed, :status, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    total_answered = VALUES(total_answered),
                    correct_answers = VALUES(correct_answers),
                    wrong_answers = VALUES(wrong_answers),
                    multiple_choice_score = VALUES(multiple_choice_score),
                    final_score = VALUES(final_score),
                    is_passed = VALUES(is_passed),
                    status = VALUES(status),
                    updated_at = NOW()
            ";
            $insResultStmt = $db->prepare($insResultSql);
            $insResultStmt->execute([
                ':sess_id'      => $sessionId,
                ':exam_id'      => $examId,
                ':student_id'   => $studentId,
                ':total_q'      => $totalQuestions,
                ':total_ans'    => $totalAnswered,
                ':correct'      => $correctAnswers,
                ':wrong'        => $wrongAnswers,
                ':mc_score'     => $finalScore,
                ':final_score'  => $finalScore,
                ':integrity'    => (float)($session['integrity_score'] ?? 100.0),
                ':is_passed'    => $isPassed,
                ':status'       => $resultStatus
            ]);
            $resultId = (int)$db->lastInsertId();
            if ($resultId <= 0) {
                $existingRes = $this->resultModel->where('exam_session_id', $sessionId)->first();
                $resultId = (int)($existingRes['id'] ?? 0);
            }

            // 6. Update status di exam_participants
            $updPartSql = "UPDATE exam_participants 
                           SET status = 'completed', score = :score, updated_at = NOW() 
                           WHERE exam_id = :exam_id AND student_id = :student_id";
            $updPartStmt = $db->prepare($updPartSql);
            $updPartStmt->execute([
                ':score'      => $finalScore,
                ':exam_id'    => $examId,
                ':student_id' => $studentId
            ]);

            // Commit Transaction
            $db->commit();

            // Audit Trail
            AuditLogModel::record('EXAM_SUBMIT', 'exam_sessions', (string)$sessionId, null, [
                'exam_id'     => $examId,
                'student_id'  => $studentId,
                'final_score' => $finalScore,
                'is_passed'   => $isPassed
            ]);

            // =========================================================================
            // TAHAP 4: EVENT NOTIFIKASI OTOMATIS (SUBMIT UJIAN)
            // =========================================================================
            try {
                $userModel = new \App\Models\UserModel();
                $student = $userModel->find($studentId);
                $studentName = $student['name'] ?? 'Peserta Ujian';
                $examTitle = $exam['title'] ?? 'Ujian Online';

                // 1. Notifikasi ke Guru Pengampu Ujian (Idempotensi Guard: Cegah notifikasi duplikat ke guru)
                $teacherId = (int)($exam['teacher_id'] ?? 0);
                if ($teacherId > 0) {
                    $teacherTitle = 'Jawaban Siswa Masuk';
                    $teacherMsg = "Siswa {$studentName} telah mengumpulkan lembar jawaban ujian '{$examTitle}'.";

                    if (!$this->notificationModel->hasNotification($teacherId, $teacherTitle, $teacherMsg, true)) {
                        $this->notificationService->notifyUser(
                            $teacherId,
                            $teacherTitle,
                            $teacherMsg,
                            NotificationService::TYPE_INFO,
                            '/guru/grading'
                        );
                    }
                }

                // 2. Notifikasi Konfirmasi Pengumpulan ke Siswa (Idempotensi Guard: Cegah notifikasi duplikat ke siswa)
                $studentTitle = 'Ujian Berhasil Dikumpulkan';
                $studentMsg = "Lembar jawaban Anda untuk ujian '{$examTitle}' telah berhasil diserahkan.";

                if (!$this->notificationModel->hasNotification($studentId, $studentTitle, $studentMsg, true)) {
                    $this->notificationService->notifyUser(
                        $studentId,
                        $studentTitle,
                        $studentMsg,
                        NotificationService::TYPE_SUCCESS,
                        '/siswa/history'
                    );
                }
            } catch (Throwable $notifEx) {
                error_log('[ExamSessionService] Gagal mengirim notifikasi submit ujian: ' . $notifEx->getMessage());
            }

            return [
                'success'           => true,
                'already_submitted' => false,
                'result_id'         => $resultId,
                'final_score'       => $finalScore,
                'is_passed'         => (bool)$isPassed,
                'status'            => $resultStatus
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            return [
                'success' => false,
                'error'   => 'Gagal memproses submit ujian: ' . $e->getMessage()
            ];
        }
    }
}
