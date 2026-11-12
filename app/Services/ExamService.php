<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use App\Models\ClassModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamRuleModel;
use App\Models\ExamScheduleModel;
use App\Models\ExamTokenModel;
use App\Models\QuestionBankModel;
use App\Models\SubjectModel;
use Throwable;

/**
 * Exam Service
 * Menangani orkestrasi bisnis pembuatan ujian, aturan AI Proctoring, penjadwalan kelas,
 * penautan bank soal, pendaftaran peserta didik, dan manajemen token terpusat.
 */
class ExamService
{
    protected ExamModel $examModel;
    protected ExamRuleModel $ruleModel;
    protected ExamScheduleModel $scheduleModel;
    protected ExamTokenModel $tokenModel;
    protected ExamQuestionModel $examQuestionModel;
    protected ExamParticipantModel $participantModel;
    protected SubjectModel $subjectModel;
    protected QuestionBankModel $bankModel;
    protected ClassModel $classModel;
    protected NotificationService $notificationService;

    public function __construct(
        ?ExamModel $examModel = null,
        ?ExamRuleModel $ruleModel = null,
        ?ExamScheduleModel $scheduleModel = null,
        ?ExamTokenModel $tokenModel = null,
        ?ExamQuestionModel $examQuestionModel = null,
        ?ExamParticipantModel $participantModel = null,
        ?SubjectModel $subjectModel = null,
        ?QuestionBankModel $bankModel = null,
        ?ClassModel $classModel = null,
        ?NotificationService $notificationService = null
    ) {
        $this->examModel = $examModel ?? new ExamModel();
        $this->ruleModel = $ruleModel ?? new ExamRuleModel();
        $this->scheduleModel = $scheduleModel ?? new ExamScheduleModel();
        $this->tokenModel = $tokenModel ?? new ExamTokenModel();
        $this->examQuestionModel = $examQuestionModel ?? new ExamQuestionModel();
        $this->participantModel = $participantModel ?? new ExamParticipantModel();
        $this->subjectModel = $subjectModel ?? new SubjectModel();
        $this->bankModel = $bankModel ?? new QuestionBankModel();
        $this->classModel = $classModel ?? new ClassModel();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Buat Ujian Baru secara Atomik (Transaction)
     */
    public function createExam(int $teacherId, array $data): array
    {
        // 1. Validasi Input
        $validation = $this->validateExamData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors'  => $validation['errors'],
                'exam_id' => null
            ];
        }

        $subject = $this->subjectModel->find((int)$data['subject_id']);
        if (!$subject) {
            return [
                'success' => false,
                'errors'  => ['subject_id' => 'Mata pelajaran tidak ditemukan.'],
                'exam_id' => null
            ];
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $subjectCode = $subject['code'] ?? 'MAPEL';
            $customCode = trim((string)($data['code'] ?? ''));
            $examCode = !empty($customCode) ? strtoupper($customCode) : $this->examModel->generateExamCode($subjectCode);

            $status = (string)($data['status'] ?? 'draft');
            if (!in_array($status, ['draft', 'published', 'scheduled', 'ongoing', 'in_progress', 'completed', 'archived'], true)) {
                $status = 'draft';
            }

            // 1. Simpan Data Utama Ujian
            $examId = (int)$this->examModel->insert([
                'subject_id'              => (int)$data['subject_id'],
                'teacher_id'              => $teacherId,
                'title'                   => clean_plain_text((string)$data['title']),
                'code'                    => $examCode,
                'description'             => !empty($data['description']) ? sanitize_html(trim((string)$data['description'])) : null,
                'type'                    => (string)($data['type'] ?? 'daily_test'),
                'duration_minutes'        => max(5, (int)($data['duration_minutes'] ?? 60)),
                'passing_grade'           => (float)($data['passing_grade'] ?? 75.0),
                'randomize_questions'     => !empty($data['randomize_questions']) ? 1 : 0,
                'randomize_options'       => !empty($data['randomize_options']) ? 1 : 0,
                'show_result_immediately' => !empty($data['show_result_immediately']) ? 1 : 0,
                'is_dual_cam_required'    => !empty($data['is_dual_cam_required']) ? 1 : 0,
                'status'                  => $status,
                'created_at'              => date('Y-m-d H:i:s'),
                'updated_at'              => date('Y-m-d H:i:s')
            ]);

            // 2. Simpan Aturan AI Proctoring & Keamanan Ujian
            $this->ruleModel->saveRules($examId, [
                'allow_tab_switch'        => !empty($data['allow_tab_switch']) ? 1 : 0,
                'max_tab_switches'        => isset($data['max_tab_switches']) ? max(0, (int)$data['max_tab_switches']) : 3,
                'lock_browser'            => !empty($data['lock_browser']) ? 1 : 0,
                'detect_multiple_faces'   => !empty($data['detect_multiple_faces']) ? 1 : 0,
                'detect_look_away'        => !empty($data['detect_look_away']) ? 1 : 0,
                'detect_audio_noise'      => !empty($data['detect_audio_noise']) ? 1 : 0,
                'detect_secondary_device' => !empty($data['detect_secondary_device']) ? 1 : 0,
            ]);

            // 3. Tautkan Butir Soal dari Bank Soal Terpilih
            if (!empty($data['question_bank_id'])) {
                $bankId = (int)$data['question_bank_id'];
                $questionLimit = !empty($data['total_questions_limit']) ? (int)$data['total_questions_limit'] : null;
                $this->examQuestionModel->syncQuestionsFromBank($examId, $bankId, $questionLimit);
            }

            // 4. Buat Jadwal Ujian untuk Kelas-Kelas Terpilih
            $classIds = (array)($data['class_ids'] ?? []);
            $startTime = (string)($data['start_time'] ?? date('Y-m-d H:i:00'));
            $endTime = (string)($data['end_time'] ?? date('Y-m-d H:i:00', strtotime('+2 hours')));
            $gracePeriod = (int)($data['grace_period_minutes'] ?? 15);

            $this->scheduleModel->syncSchedules($examId, $classIds, $startTime, $endTime, $gracePeriod);

            // 5. Daftarkan Siswa Kelas ke Peserta Ujian
            $this->participantModel->syncParticipantsFromClasses($examId, $classIds);

            // 6. Rilis Token Ujian 6-Karakter Otomatis
            $this->tokenModel->releaseToken($examId, 24, 15);

            $db->commit();

            // TAHAP 5: Jika status ujian baru langsung 'published', notifikasi peserta
            if ($status === 'published') {
                $this->notifyExamPublished($examId, clean_plain_text((string)$data['title']));
            }

            return [
                'success' => true,
                'errors'  => [],
                'exam_id' => $examId
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            return [
                'success' => false,
                'errors'  => ['general' => 'Gagal membuat ujian: ' . $e->getMessage()],
                'exam_id' => null
            ];
        }
    }

    /**
     * Perbarui Konfigurasi Ujian
     */
    public function updateExam(int $examId, array $data): array
    {
        $exam = $this->examModel->find($examId);
        if (!$exam || !empty($exam['deleted_at'])) {
            return [
                'success' => false,
                'errors'  => ['general' => 'Ujian tidak ditemukan.'],
                'exam_id' => $examId
            ];
        }

        $validation = $this->validateExamData($data, false);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors'  => $validation['errors'],
                'exam_id' => $examId
            ];
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $status = (string)($data['status'] ?? $exam['status']);
            if (!in_array($status, ['draft', 'published', 'scheduled', 'ongoing', 'in_progress', 'completed', 'archived'], true)) {
                $status = $exam['status'];
            }

            // Update main exam
            $this->examModel->update($examId, [
                'subject_id'              => (int)($data['subject_id'] ?? $exam['subject_id']),
                'title'                   => isset($data['title']) ? clean_plain_text((string)$data['title']) : $exam['title'],
                'description'             => isset($data['description']) ? sanitize_html(trim((string)$data['description'])) : $exam['description'],
                'type'                    => (string)($data['type'] ?? $exam['type']),
                'duration_minutes'        => max(5, (int)($data['duration_minutes'] ?? $exam['duration_minutes'])),
                'passing_grade'           => (float)($data['passing_grade'] ?? $exam['passing_grade']),
                'randomize_questions'     => isset($data['randomize_questions']) ? (!empty($data['randomize_questions']) ? 1 : 0) : $exam['randomize_questions'],
                'randomize_options'       => isset($data['randomize_options']) ? (!empty($data['randomize_options']) ? 1 : 0) : $exam['randomize_options'],
                'show_result_immediately' => isset($data['show_result_immediately']) ? (!empty($data['show_result_immediately']) ? 1 : 0) : $exam['show_result_immediately'],
                'is_dual_cam_required'    => isset($data['is_dual_cam_required']) ? (!empty($data['is_dual_cam_required']) ? 1 : 0) : $exam['is_dual_cam_required'],
                'status'                  => $status,
                'updated_at'              => date('Y-m-d H:i:s')
            ]);

            // Update rules
            $this->ruleModel->saveRules($examId, [
                'allow_tab_switch'        => !empty($data['allow_tab_switch']) ? 1 : 0,
                'max_tab_switches'        => isset($data['max_tab_switches']) ? max(0, (int)$data['max_tab_switches']) : 3,
                'lock_browser'            => !empty($data['lock_browser']) ? 1 : 0,
                'detect_multiple_faces'   => !empty($data['detect_multiple_faces']) ? 1 : 0,
                'detect_look_away'        => !empty($data['detect_look_away']) ? 1 : 0,
                'detect_audio_noise'      => !empty($data['detect_audio_noise']) ? 1 : 0,
                'detect_secondary_device' => !empty($data['detect_secondary_device']) ? 1 : 0,
            ]);

            // Jika bank soal diubah
            if (!empty($data['question_bank_id'])) {
                $bankId = (int)$data['question_bank_id'];
                $questionLimit = !empty($data['total_questions_limit']) ? (int)$data['total_questions_limit'] : null;
                $this->examQuestionModel->syncQuestionsFromBank($examId, $bankId, $questionLimit);
            }

            // Jika jadwal & kelas di-update
            if (!empty($data['class_ids']) && !empty($data['start_time']) && !empty($data['end_time'])) {
                $classIds = (array)$data['class_ids'];
                $startTime = (string)$data['start_time'];
                $endTime = (string)$data['end_time'];
                $gracePeriod = (int)($data['grace_period_minutes'] ?? 15);

                $this->scheduleModel->syncSchedules($examId, $classIds, $startTime, $endTime, $gracePeriod);
                $this->participantModel->syncParticipantsFromClasses($examId, $classIds);
            }

            $db->commit();

            // TAHAP 5: Jika status bertransisi menjadi 'published', notifikasi peserta
            if ($status === 'published' && ($exam['status'] ?? '') !== 'published') {
                $this->notifyExamPublished($examId, (string)($data['title'] ?? $exam['title']));
            }

            return [
                'success' => true,
                'errors'  => [],
                'exam_id' => $examId
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            return [
                'success' => false,
                'errors'  => ['general' => 'Gagal memperbarui ujian: ' . $e->getMessage()],
                'exam_id' => $examId
            ];
        }
    }

    /**
     * Regenerasi Token Ujian 6-Karakter (UC-29)
     */
    public function generateOrRefreshToken(int $examId, int $validityHours = 24): array
    {
        return $this->tokenModel->releaseToken($examId, $validityHours, 15);
    }

    /**
     * Toggle status aktif token
     */
    public function toggleTokenStatus(int $tokenId): bool
    {
        return $this->tokenModel->toggleTokenStatus($tokenId);
    }

    /**
     * Ganti status ujian
     * Menerapkan audit idempotensi untuk mencegah duplikasi notifikasi saat status sama,
     * serta menginisiasi Event 1 (Publikasi Ujian Baru) ketika status bertransisi ke 'published'.
     */
    public function changeStatus(int $examId, string $status): bool
    {
        $allowedStatuses = ['draft', 'published', 'scheduled', 'ongoing', 'in_progress', 'completed', 'archived'];
        if (!in_array($status, $allowedStatuses, true)) {
            return false;
        }

        $exam = $this->examModel->find($examId);
        if (!$exam || !empty($exam['deleted_at'])) {
            return false;
        }

        $oldStatus = (string)($exam['status'] ?? 'draft');

        // Idempotensi Level 1: Jika status sama dengan nilai saat ini, no-op tanpa notifikasi duplikat
        if ($oldStatus === $status) {
            return true;
        }

        // Jalankan atomic conditional update:
        // WHERE id = :id AND status != :status_check AND deleted_at IS NULL
        // Menjamin rowCount === 1 hanya untuk request yang benar-benar melakukan transisi status.
        // Worker konkuren kedua akan mendapatkan rowCount === 0 sehingga terhindar dari notifikasi ganda.
        $db = Database::getConnection();
        $sql = "UPDATE exams SET status = :status, updated_at = NOW() WHERE id = :id AND status != :status_check AND deleted_at IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':status_check', $status);
        $stmt->bindValue(':id', $examId, \PDO::PARAM_INT);
        $success = $stmt->execute();
        $affectedRows = $stmt->rowCount();

        if (!$success) {
            return false;
        }

        // =========================================================================
        // TAHAP 5: EVENT 1 — PUBLIKASI UJIAN BARU
        // =========================================================================
        // Hanya dikirim saat status berubah dari status non-published menjadi 'published'
        // DAN atomic update ini yang benar-benar memenangkan transisi status (affectedRows > 0)
        if ($status === 'published' && $oldStatus !== 'published' && $affectedRows > 0) {
            $this->notifyExamPublished($examId, (string)$exam['title']);
        }

        return true;
    }


    /**
     * Update Jadwal Pelaksanaan Ujian & Sinkronisasi Peserta Rombel
     * Menjalankan dirty-check untuk mencegah notifikasi pada jadwal yang tidak berubah,
     * dan menginisiasi Event 2 (Perubahan Jadwal Ujian) hanya kepada siswa peserta terdaftar.
     *
     * @param int $examId ID Ujian
     * @param array $classIds Daftar ID Kelas Peserta
     * @param string $startTime Waktu mulai ujian
     * @param string $endTime Waktu selesai ujian
     * @param int $gracePeriod Toleransi keterlambatan (menit)
     * @return array Status operasi dan informasi perubahan
     */
    public function updateSchedule(int $examId, array $classIds, string $startTime, string $endTime, int $gracePeriod = 15): array
    {
        $cleanClassIds = array_values(array_unique(array_filter(array_map('intval', $classIds), fn($id) => $id > 0)));
        if (empty($cleanClassIds)) {
            return [
                'success'        => false,
                'changed'        => false,
                'message'        => 'Pilih minimal satu kelas rombel peserta ujian.',
                'notified_count' => 0
            ];
        }

        $startTs = strtotime($startTime);
        $endTs = strtotime($endTime);
        if (!$startTs || !$endTs || $startTs >= $endTs) {
            return [
                'success'        => false,
                'changed'        => false,
                'message'        => 'Waktu mulai harus lebih awal daripada waktu selesai ujian.',
                'notified_count' => 0
            ];
        }

        $normStartTime = date('Y-m-d H:i:s', $startTs);
        $normEndTime = date('Y-m-d H:i:s', $endTs);
        $grace = max(0, $gracePeriod);

        // Buka transaksi dan kunci baris ujian (FOR UPDATE) untuk serialisasi penuh request konkuren & double-submit
        $db = Database::getConnection();
        $inTx = $db->inTransaction();
        if (!$inTx) {
            $db->beginTransaction();
        }

        try {
            $lockStmt = $db->prepare("SELECT id, title, status FROM exams WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
            $lockStmt->bindValue(':id', $examId, \PDO::PARAM_INT);
            $lockStmt->execute();
            $exam = $lockStmt->fetch();

            if (!$exam) {
                if (!$inTx) {
                    $db->rollBack();
                }
                return [
                    'success'        => false,
                    'changed'        => false,
                    'message'        => 'Ujian tidak ditemukan atau telah dihapus.',
                    'notified_count' => 0
                ];
            }

            // 1. Ambil jadwal eksisting di dalam transaksi yang terkunci (dirty-check akurat)
            $existingSchedules = $this->scheduleModel->getByExamId($examId);
            $isInitial = empty($existingSchedules);

            $isChanged = false;
            if ($isInitial) {
                $isChanged = true;
            } else {
                $oldClassIds = array_values(array_unique(array_map('intval', array_column($existingSchedules, 'class_id'))));
                sort($oldClassIds);

                $newSortedClassIds = $cleanClassIds;
                sort($newSortedClassIds);

                $firstOld = $existingSchedules[0];
                $oldStartTs = strtotime((string)$firstOld['start_time']);
                $oldEndTs = strtotime((string)$firstOld['end_time']);
                $oldGrace = (int)($firstOld['grace_period_minutes'] ?? 15);

                $isClassChanged = ($oldClassIds !== $newSortedClassIds);
                $isTimeChanged = (date('Y-m-d H:i', $oldStartTs) !== date('Y-m-d H:i', $startTs)
                               || date('Y-m-d H:i', $oldEndTs) !== date('Y-m-d H:i', $endTs)
                               || $oldGrace !== $grace);

                $isChanged = ($isClassChanged || $isTimeChanged);
            }

            // Idempotensi: Jika bukan initial dan tidak ada perubahan jadwal, commit lock dan return no-op
            if (!$isInitial && !$isChanged) {
                if (!$inTx) {
                    $db->commit();
                }
                return [
                    'success'        => true,
                    'changed'        => false,
                    'message'        => 'Jadwal pelaksanaan ujian tidak mengalami perubahan.',
                    'notified_count' => 0
                ];
            }

            // 2. Simpan perubahan ke database secara atomik
            $this->scheduleModel->syncSchedules($examId, $cleanClassIds, $normStartTime, $normEndTime, $grace);
            $this->participantModel->syncParticipantsFromClasses($examId, $cleanClassIds);

            // Update timestamp ujian
            $updStmt = $db->prepare("UPDATE exams SET updated_at = NOW() WHERE id = :id");
            $updStmt->bindValue(':id', $examId, \PDO::PARAM_INT);
            $updStmt->execute();

            if (!$inTx) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'success'        => false,
                'changed'        => false,
                'message'        => 'Gagal menyimpan perubahan jadwal: ' . $e->getMessage(),
                'notified_count' => 0
            ];
        }

        // =========================================================================
        // TAHAP 5: EVENT 2 — PERUBAHAN JADWAL UJIAN
        // =========================================================================
        // Ketentuan:
        // - Hanya dikirim jika bukan pembuatan jadwal pertama kali (isInitial = false)
        // - Hanya dikirim jika data jadwal telah berubah secara aktual
        // - Hanya dikirim jika status ujian published, scheduled, atau ongoing
        $notifiedCount = 0;
        $shouldNotify = (!$isInitial && $isChanged && in_array($exam['status'], ['published', 'scheduled', 'ongoing'], true));

        if ($shouldNotify) {
            $notifiedCount = $this->notifyScheduleChanged($examId, (string)$exam['title'], $normStartTime, $normEndTime);
        }

        return [
            'success'        => true,
            'changed'        => true,
            'message'        => 'Jadwal pelaksanaan ujian dan kelas peserta berhasil diperbarui.',
            'notified_count' => $notifiedCount
        ];
    }

    /**
     * Dapatkan daftar student_id unik yang terdaftar sebagai peserta ujian
     * Menggabungkan peserta di exam_participants dan siswa aktif di kelas yang dijadwalkan
     *
     * @param int $examId
     * @return int[]
     */
    public function getEnrolledParticipantStudentIds(int $examId): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT DISTINCT student_id FROM (
                SELECT ep.student_id
                FROM exam_participants ep
                JOIN users u ON ep.student_id = u.id
                WHERE ep.exam_id = :eid1
                  AND u.status = 'active'
                  AND u.deleted_at IS NULL
                UNION
                SELECT cs.student_id
                FROM exam_schedules es
                JOIN class_students cs ON es.class_id = cs.class_id
                JOIN users u ON cs.student_id = u.id
                WHERE es.exam_id = :eid2
                  AND cs.status = 'active'
                  AND u.status = 'active'
                  AND u.deleted_at IS NULL
            ) as participants
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':eid1', $examId, \PDO::PARAM_INT);
        $stmt->bindValue(':eid2', $examId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        return array_values(array_unique(array_filter(array_map('intval', $rows), fn($id) => $id > 0)));
    }

    /**
     * Dispatch Notifikasi Publikasi Ujian Baru (Event 1) dengan Idempotensi Guard
     */
    public function notifyExamPublished(int $examId, string $examTitle): int
    {
        try {
            $studentIds = $this->getEnrolledParticipantStudentIds($examId);
            if (empty($studentIds)) {
                return 0;
            }

            $title = 'Ujian Baru Tersedia';
            $message = "Ujian {$examTitle} telah dipublikasikan dan tersedia untuk dikerjakan.";
            $actionUrl = '/siswa/exams';

            // Idempotency check: Ambil siswa yang sudah menerima notifikasi publikasi ujian ini secara persis (anti false dedup)
            $notificationModel = new \App\Models\NotificationModel();
            $alreadyNotifiedIds = $notificationModel->getUserIdsWithNotification($studentIds, $title, $message, true, null);
            $targetStudentIds = array_values(array_diff($studentIds, $alreadyNotifiedIds));

            if (empty($targetStudentIds)) {
                return 0;
            }

            $notifiedCount = 0;
            foreach ($targetStudentIds as $sId) {
                $this->notificationService->notifyUser(
                    (int)$sId,
                    $title,
                    $message,
                    NotificationService::TYPE_INFO,
                    $actionUrl
                );
                $notifiedCount++;
            }

            return $notifiedCount;
        } catch (Throwable $e) {
            error_log('[ExamService] Gagal mengirim notifikasi publikasi ujian: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Dispatch Notifikasi Perubahan Jadwal Ujian (Event 2) dengan Idempotensi Guard
     */
    public function notifyScheduleChanged(int $examId, string $examTitle, string $startTime, string $endTime): int
    {
        try {
            $studentIds = $this->getEnrolledParticipantStudentIds($examId);
            if (empty($studentIds)) {
                return 0;
            }

            $formattedStart = date('d M Y H:i', strtotime($startTime));
            $formattedEnd = date('d M Y H:i', strtotime($endTime));
            $title = "Perubahan Jadwal Ujian: {$examTitle}";
            $message = "Jadwal pelaksanaan ujian {$examTitle} telah diperbarui menjadi {$formattedStart} s/d {$formattedEnd}. Silakan periksa detail jadwal pada katalog ujian.";
            $actionUrl = '/siswa/exams';

            // Idempotency check: Siswa yang sudah menerima notifikasi perubahan ke jadwal ini dalam 300 detik tidak dikirimkan ulang
            $notificationModel = new \App\Models\NotificationModel();
            $alreadyNotifiedIds = $notificationModel->getUserIdsWithNotification($studentIds, $title, $message, true, 300);
            $targetStudentIds = array_values(array_diff($studentIds, $alreadyNotifiedIds));

            if (empty($targetStudentIds)) {
                return 0;
            }

            $notifiedCount = 0;
            foreach ($targetStudentIds as $sId) {
                $this->notificationService->notifyUser(
                    (int)$sId,
                    $title,
                    $message,
                    NotificationService::TYPE_WARNING,
                    $actionUrl
                );
                $notifiedCount++;
            }

            return $notifiedCount;
        } catch (Throwable $e) {
            error_log('[ExamService] Gagal mengirim notifikasi perubahan jadwal ujian: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Hapus ujian (Soft delete)
     */
    public function deleteExam(int $examId): bool
    {
        return (bool)$this->examModel->update($examId, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'status'     => 'archived'
        ]);
    }

    /**
     * Dapatkan data lengkap untuk Pratinjau Ujian (UC-20a)
     */
    public function getExamDetailsForPreview(int $examId): ?array
    {
        $exam = $this->examModel->getDetailsWithFullRelations($examId);
        if (!$exam) {
            return null;
        }

        // Ambil butir soal lengkap beserta pilihan jawabannya
        $questions = $this->examQuestionModel->getQuestionsByExamId($examId);
        $exam['questions_with_options'] = $questions;

        return $exam;
    }

    /**
     * Validasi data input form ujian
     */
    public function validateExamData(array $data, bool $isNew = true): array
    {
        $errors = [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Judul ujian wajib diisi.';
        } elseif (strlen($title) < 3) {
            $errors['title'] = 'Judul ujian minimal 3 karakter.';
        }

        $subjectId = (int)($data['subject_id'] ?? 0);
        if ($subjectId <= 0) {
            $errors['subject_id'] = 'Pilih mata pelajaran yang valid.';
        }

        $duration = (int)($data['duration_minutes'] ?? 0);
        if ($duration <= 0) {
            $errors['duration_minutes'] = 'Durasi ujian harus lebih dari 0 menit.';
        }

        $passingGrade = (float)($data['passing_grade'] ?? -1);
        if ($passingGrade < 0 || $passingGrade > 100) {
            $errors['passing_grade'] = 'Standar kelulusan (KKM) harus bernilai antara 0.00 hingga 100.00.';
        }

        if ($isNew) {
            $bankId = (int)($data['question_bank_id'] ?? 0);
            if ($bankId <= 0) {
                $errors['question_bank_id'] = 'Pilih Bank Soal sumber materi ujian.';
            }

            $classIds = (array)($data['class_ids'] ?? []);
            if (empty($classIds)) {
                $errors['class_ids'] = 'Pilih minimal satu kelas rombel peserta ujian.';
            }

            $startTime = trim((string)($data['start_time'] ?? ''));
            $endTime = trim((string)($data['end_time'] ?? ''));
            if ($startTime === '' || $endTime === '') {
                $errors['schedule_time'] = 'Waktu mulai dan waktu selesai jadwal ujian wajib ditentukan.';
            } elseif (strtotime($startTime) >= strtotime($endTime)) {
                $errors['schedule_time'] = 'Waktu mulai harus lebih awal daripada waktu selesai ujian.';
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors
        ];
    }
}
