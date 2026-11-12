<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\AuditLogModel;
use App\Models\ExamModel;
use App\Models\ExamResultModel;
use App\Models\ExamSessionModel;
use PDO;

/**
 * Result Service
 * Mengelola daftar hasil pengerjaan ujian peserta (UC-16),
 * penyiapan data meja koreksi esai & review jawaban,
 * dan publikasi hasil ujian resmi (UC-24) dengan proteksi penguncian nilai.
 */
class ResultService
{
    protected ExamResultModel $resultModel;
    protected ExamSessionModel $sessionModel;
    protected ExamModel $examModel;
    protected NotificationService $notificationService;

    public function __construct(
        ?ExamResultModel $resultModel = null,
        ?ExamSessionModel $sessionModel = null,
        ?ExamModel $examModel = null,
        ?NotificationService $notificationService = null
    ) {
        $this->resultModel = $resultModel ?? new ExamResultModel();
        $this->sessionModel = $sessionModel ?? new ExamSessionModel();
        $this->examModel = $examModel ?? new ExamModel();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Dapatkan daftar seluruh penyerahan ujian (submissions) untuk satu ujian tertentu
     */
    public function getExamSubmissions(int $examId, ?string $statusFilter = null, ?string $search = null): array
    {
        $exam = $this->examModel->find($examId);
        if (!$exam) {
            return ['exam' => null, 'submissions' => [], 'summary' => []];
        }

        $db = Database::getConnection();

        $where = ["es.exam_id = :exam_id"];
        $params = [':exam_id' => $examId];

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            if ($statusFilter === 'passed') {
                $where[] = "er.is_passed = 1";
            } elseif ($statusFilter === 'failed') {
                $where[] = "er.is_passed = 0";
            } elseif ($statusFilter === 'pending_grading') {
                $where[] = "(er.status = 'pending_grading' OR er.status IS NULL)";
            } elseif ($statusFilter === 'graded') {
                $where[] = "er.status = 'graded'";
            } elseif ($statusFilter === 'published') {
                $where[] = "er.status = 'published'";
            }
        }

        if (!empty($search)) {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search2 OR u.username LIKE :search3)";
            $params[':search'] = '%' . trim($search) . '%';
            $params[':search2'] = '%' . trim($search) . '%';
            $params[':search3'] = '%' . trim($search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                es.id as session_id,
                es.session_token,
                es.student_id,
                es.start_time,
                es.submit_time,
                es.remaining_seconds,
                es.status as session_status,
                es.integrity_score,
                u.name as student_name,
                u.email as student_email,
                u.username as student_nis,
                c.name as class_name,
                er.id as result_id,
                er.total_questions,
                er.total_answered,
                er.correct_answers,
                er.wrong_answers,
                er.multiple_choice_score,
                er.essay_score,
                er.final_score,
                er.is_passed,
                er.teacher_feedback,
                er.status as grading_status,
                er.graded_at,
                er.published_at
            FROM exam_sessions es
            JOIN users u ON es.student_id = u.id
            LEFT JOIN exam_participants ep ON es.exam_id = ep.exam_id AND es.student_id = ep.student_id
            LEFT JOIN classes c ON ep.class_id = c.id
            LEFT JOIN exam_results er ON es.id = er.exam_session_id
            WHERE {$whereSql}
            ORDER BY er.final_score DESC, es.submit_time ASC

        ";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Hitung statistik ringkasan
        $totalSubmissions = count($submissions);
        $totalPassed = 0;
        $totalFailed = 0;
        $totalScore = 0.0;
        $highestScore = 0.0;
        $lowestScore = 100.0;
        $pendingEssaysCount = 0;
        $isAllPublished = ($totalSubmissions > 0);

        foreach ($submissions as $sub) {
            $score = (float)($sub['final_score'] ?? 0.0);
            $totalScore += $score;
            if ($score > $highestScore) $highestScore = $score;
            if ($score < $lowestScore) $lowestScore = $score;

            if (!empty($sub['is_passed'])) {
                $totalPassed++;
            } else {
                $totalFailed++;
            }

            if (($sub['grading_status'] ?? '') === 'pending_grading') {
                $pendingEssaysCount++;
            }

            if (($sub['grading_status'] ?? '') !== 'published') {
                $isAllPublished = false;
            }
        }

        $averageScore = ($totalSubmissions > 0) ? round($totalScore / $totalSubmissions, 2) : 0.0;
        $passRate = ($totalSubmissions > 0) ? round(($totalPassed / $totalSubmissions) * 100, 1) : 0.0;

        $summary = [
            'total_submissions'   => $totalSubmissions,
            'total_passed'        => $totalPassed,
            'total_failed'        => $totalFailed,
            'average_score'       => $averageScore,
            'highest_score'       => ($totalSubmissions > 0) ? $highestScore : 0.0,
            'lowest_score'        => ($totalSubmissions > 0) ? $lowestScore : 0.0,
            'pass_rate'           => $passRate,
            'pending_essays'      => $pendingEssaysCount,
            'is_all_published'    => $isAllPublished
        ];

        return [
            'exam'        => $exam,
            'submissions' => $submissions,
            'summary'     => $summary
        ];
    }

    /**
     * Dapatkan detail jawaban per butir untuk proses koreksi / review guru
     */
    public function getSessionAnswersForGrading(int $sessionId): ?array
    {
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return null;
        }

        $db = Database::getConnection();

        // 1. Data Siswa & Ujian
        $infoSql = "
            SELECT 
                es.*,
                u.name as student_name,
                u.email as student_email,
                u.username as student_nis,
                e.title as exam_title,
                e.code as exam_code,
                e.duration_minutes,
                e.passing_grade,
                e.teacher_id,
                s.name as subject_name,
                er.id as result_id,
                er.final_score,
                er.multiple_choice_score,
                er.essay_score,
                er.is_passed,
                er.status as result_status,
                er.teacher_feedback as exam_feedback
            FROM exam_sessions es
            JOIN users u ON es.student_id = u.id
            JOIN exams e ON es.exam_id = e.id
            JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN exam_results er ON es.id = er.exam_session_id
            WHERE es.id = :session_id
            LIMIT 1
        ";

        $infoStmt = $db->prepare($infoSql);
        $infoStmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $infoStmt->execute();
        $header = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            return null;
        }

        // 2. Daftar Jawaban Soal
        $answersSql = "
            SELECT 
                ea.*,
                q.content as question_content,
                q.type as question_type,
                q.weight as question_weight,
                q.difficulty as question_difficulty,
                q.explanation as question_explanation,
                qo.content as selected_option_text,
                (SELECT content FROM question_options WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_option_text
            FROM exam_answers ea
            JOIN questions q ON ea.question_id = q.id
            LEFT JOIN question_options qo ON ea.selected_option_id = qo.id
            WHERE ea.exam_session_id = :session_id
            ORDER BY ea.id ASC
        ";

        $ansStmt = $db->prepare($answersSql);
        $ansStmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $ansStmt->execute();
        $answers = $ansStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Ambil juga opsi-opsi untuk setiap soal pilihan
        foreach ($answers as &$ans) {
            if ($ans['question_type'] !== 'essay') {
                $optSql = "SELECT id, content as option_text, option_key, is_correct FROM question_options WHERE question_id = :qid ORDER BY id ASC";
                $optStmt = $db->prepare($optSql);
                $optStmt->bindValue(':qid', (int)$ans['question_id'], PDO::PARAM_INT);
                $optStmt->execute();
                $ans['options'] = $optStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $ans['options'] = [];
            }
        }
        unset($ans);


        return [
            'header'  => $header,
            'answers' => $answers
        ];
    }

    /**
     * Publikasikan hasil ujian resmi ke seluruh siswa (UC-24)
     *
     * @param int $examId ID Ujian
     * @param int $publisherId ID Guru/Admin yang mempublikasikan
     * @return array{success: bool, message?: string, published_count?: int, error?: string}
     */
    public function publishExamResults(int $examId, int $publisherId): array
    {
        $exam = $this->examModel->find($examId);
        if (!$exam) {
            return ['success' => false, 'error' => 'Ujian tidak ditemukan.'];
        }

        $db = Database::getConnection();

        // 1. Identifikasi siswa yang hasilnya BENAR-BENAR BARU akan dipublikasikan (status != 'published')
        $pendingStudentSql = "
            SELECT DISTINCT student_id 
            FROM exam_results 
            WHERE exam_id = :exam_id AND (status IS NULL OR status != 'published')
        ";
        $pendingStmt = $db->prepare($pendingStudentSql);
        $pendingStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $pendingStmt->execute();
        $targetStudentIds = $pendingStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // 2. Update HANYA exam_results yang belum dipublikasikan
        $updateSql = "
            UPDATE exam_results 
            SET status = 'published', 
                published_at = NOW(), 
                published_by = :pub_id,
                updated_at = NOW()
            WHERE exam_id = :exam_id AND (status IS NULL OR status != 'published')
        ";

        $stmt = $db->prepare($updateSql);
        $stmt->bindValue(':pub_id', $publisherId, PDO::PARAM_INT);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        $publishedCount = $stmt->rowCount();

        // 3. Set status ujian menjadi 'completed' jika belum
        $this->examModel->update($examId, [
            'status'     => 'completed',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // 4. Catat Audit Log
        AuditLogModel::record(
            'PUBLISH_EXAM_RESULTS',
            'exams',
            (string)$examId,
            null,
            [
                'exam_title'      => $exam['title'],
                'published_count' => $publishedCount,
                'publisher_id'    => $publisherId
            ]
        );

        // =========================================================================
        // TAHAP 4B: EVENT NOTIFIKASI OTOMATIS (PUBLIKASI NILAI UJIAN - IDEMPOTEN)
        // =========================================================================
        // Hanya kirim notifikasi ke siswa yang hasilnya BENAR-BENAR BARU dipublikasikan pada operasi ini
        if ($publishedCount > 0 && !empty($targetStudentIds)) {
            try {
                $uniqueTargetIds = array_unique(array_filter(array_map('intval', $targetStudentIds), fn($id) => $id > 0));

                if (!empty($uniqueTargetIds)) {
                    $examTitle = $exam['title'] ?? 'Ujian';
                    foreach ($uniqueTargetIds as $sId) {
                        $this->notificationService->notifyUser(
                            (int)$sId,
                            'Hasil Ujian Dipublikasikan',
                            "Nilai dan hasil evaluasi untuk ujian '{$examTitle}' telah resmi dipublikasikan.",
                            NotificationService::TYPE_SUCCESS,
                            '/siswa/results'
                        );
                    }
                }
            } catch (\Throwable $notifEx) {
                error_log('[ResultService] Gagal mengirim notifikasi publikasi hasil ujian: ' . $notifEx->getMessage());
            }
        }

        return [
            'success'         => true,
            'message'         => ($publishedCount > 0)
                ? "Hasil ujian '{$exam['title']}' resmi dipublikasikan ({$publishedCount} siswa dapat melihat nilai)."
                : "Seluruh hasil ujian '{$exam['title']}' sudah dalam status terpublikasi sebelumnya.",
            'published_count' => $publishedCount
        ];
    }

    /**
     * Ringkasan koreksi untuk Dashboard Guru
     */
    public function getTeacherGradingSummary(int $teacherId): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT 
                COUNT(DISTINCT e.id) as total_exams,
                COUNT(DISTINCT es.id) as total_submissions,
                SUM(CASE WHEN er.status = 'pending_grading' THEN 1 ELSE 0 END) as pending_grading_count,
                SUM(CASE WHEN er.status = 'published' THEN 1 ELSE 0 END) as published_count
            FROM exams e
            LEFT JOIN exam_sessions es ON e.id = es.exam_id
            LEFT JOIN exam_results er ON es.id = er.exam_session_id
            WHERE e.teacher_id = :teacher_id AND e.deleted_at IS NULL
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_exams'           => 0,
            'total_submissions'     => 0,
            'pending_grading_count' => 0,
            'published_count'       => 0
        ];
    }
}
