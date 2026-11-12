<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Participant Model
 * Mengelola pendaftaran peserta didik per kelas ke dalam ujian online,
 * validasi akses pengerjaan siswa, dan query riwayat ujian siswa.
 */
class ExamParticipantModel extends BaseModel
{
    protected string $table = 'exam_participants';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_id',
        'student_id',
        'class_id',
        'status', // 'not_started', 'in_progress', 'completed', 'blocked', 'submitted'
        'score',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan daftar peserta ujian beserta info kelas dan identitas siswa
     */
    public function getParticipantsByExamId(int $examId, ?int $classId = null): array
    {
        $where = "ep.exam_id = :exam_id";
        $params = [':exam_id' => $examId];

        if ($classId !== null && $classId > 0) {
            $where .= " AND ep.class_id = :class_id";
            $params[':class_id'] = $classId;
        }

        $sql = "SELECT ep.*, u.name as student_name, u.email as student_email, u.nip_nisn as student_nisn,
                       c.name as class_name, c.grade_level as class_level
                FROM {$this->table} ep
                JOIN users u ON ep.student_id = u.id
                JOIN classes c ON ep.class_id = c.id
                WHERE {$where}
                ORDER BY c.name ASC, u.name ASC";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dapatkan kelas aktif siswa
     */
    public function getStudentClass(int $studentId): ?array
    {
        $sql = "SELECT c.*, ay.name as academic_year_name, cs.roll_number
                FROM class_students cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
                WHERE cs.student_id = :student_id AND cs.status = 'active'
                ORDER BY cs.id DESC
                LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        // Fallback: periksa kelas pertama di database jika siswa belum diassign ke class_students
        $fallbackSql = "SELECT c.*, ay.name as academic_year_name, '' as roll_number
                        FROM classes c
                        LEFT JOIN academic_years ay ON c.academic_year_id = ay.id
                        ORDER BY c.id ASC LIMIT 1";
        $fbStmt = $this->db()->query($fallbackSql);
        return $fbStmt->fetch() ?: null;
    }

    /**
     * Dapatkan daftar ID kelas tempat siswa terdaftar
     */
    public function getStudentClassIds(int $studentId): array
    {
        $sql = "SELECT DISTINCT class_id FROM class_students WHERE student_id = :student_id AND status = 'active'";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $classIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (empty($classIds)) {
            // Cek jika ada riwayat participant
            $pSql = "SELECT DISTINCT class_id FROM {$this->table} WHERE student_id = :student_id";
            $pStmt = $this->db()->prepare($pSql);
            $pStmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $pStmt->execute();
            $classIds = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        if (empty($classIds)) {
            // Fallback default kelas 1 jika belum ter-assign
            $fSql = "SELECT id FROM classes ORDER BY id ASC LIMIT 1";
            $fStmt = $this->db()->query($fSql);
            $c = $fStmt->fetchColumn();
            if ($c) {
                $classIds[] = (int)$c;
            }
        }

        return array_map('intval', $classIds);
    }

    /**
     * Cek apakah siswa terdaftar di ujian (baik via exam_participants maupun kelas yang dijadwalkan)
     */
    public function isStudentEnrolled(int $examId, int $studentId): bool
    {
        // 1. Cek langsung di exam_participants
        $sql1 = "SELECT id FROM {$this->table} WHERE exam_id = :exam_id AND student_id = :student_id LIMIT 1";
        $stmt1 = $this->db()->prepare($sql1);
        $stmt1->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt1->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt1->execute();
        if ($stmt1->fetchColumn()) {
            return true;
        }

        // 2. Cek apakah kelas siswa cocok dengan jadwal ujian
        $classIds = $this->getStudentClassIds($studentId);
        if (empty($classIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $sql2 = "SELECT id FROM exam_schedules WHERE exam_id = ? AND class_id IN ({$placeholders}) LIMIT 1";
        $stmt2 = $this->db()->prepare($sql2);
        $stmt2->bindValue(1, $examId, PDO::PARAM_INT);
        foreach ($classIds as $idx => $cid) {
            $stmt2->bindValue($idx + 2, (int)$cid, PDO::PARAM_INT);
        }
        $stmt2->execute();
        return (bool)$stmt2->fetchColumn();
    }

    /**
     * Dapatkan daftar ujian yang tersedia untuk kelas siswa (UC-06: Pilih Mata Pelajaran/Ujian)
     */
    public function getAvailableExamsForStudent(int $studentId, array $filters = []): array
    {
        $classIds = $this->getStudentClassIds($studentId);
        if (empty($classIds)) {
            return [];
        }

        $classPlaceholders = implode(',', array_fill(0, count($classIds), '?'));
        $now = date('Y-m-d H:i:s');

        $where = [
            "e.deleted_at IS NULL",
            "e.status IN ('published', 'ongoing', 'scheduled')",
            "es.class_id IN ({$classPlaceholders})"
        ];

        $params = array_values($classIds);

        if (!empty($filters['subject_id'])) {
            $where[] = "e.subject_id = ?";
            $params[] = (int)$filters['subject_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(e.title LIKE ? OR e.code LIKE ? OR s.name LIKE ?)";
            $searchTerm = '%' . trim($filters['search']) . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                e.id as exam_id,
                e.title,
                e.code as exam_code,
                e.description,
                e.type as exam_type,
                e.duration_minutes,
                e.passing_grade,
                e.is_dual_cam_required,
                e.show_result_immediately,
                e.status as exam_status,
                s.id as subject_id,
                s.name as subject_name,
                s.code as subject_code,
                u.name as teacher_name,
                c.name as class_name,
                c.id as class_id,
                es.id as schedule_id,
                es.start_time,
                es.end_time,
                es.grace_period_minutes,
                es.status as schedule_status,
                er.lock_browser,
                er.detect_multiple_faces,
                er.detect_look_away,
                er.detect_audio_noise,
                er.detect_secondary_device,
                er.max_tab_switches,
                ep.status as participant_status,
                ep.score as participant_score,
                sess.id as session_id,
                sess.status as session_status,
                res.id as result_id,
                res.final_score,
                res.is_passed,
                res.status as result_status,
                (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as total_questions,
                CASE 
                    WHEN '{$now}' < es.start_time THEN 'upcoming'
                    WHEN '{$now}' >= es.start_time AND '{$now}' <= es.end_time THEN 'ongoing'
                    ELSE 'expired'
                END as time_status
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON e.teacher_id = u.id
            JOIN exam_schedules es ON es.exam_id = e.id
            JOIN classes c ON es.class_id = c.id
            LEFT JOIN exam_rules er ON er.exam_id = e.id
            LEFT JOIN {$this->table} ep ON ep.exam_id = e.id AND ep.student_id = {$studentId}
            LEFT JOIN exam_sessions sess ON sess.exam_id = e.id AND sess.student_id = {$studentId}
            LEFT JOIN exam_results res ON res.exam_id = e.id AND res.student_id = {$studentId}
            WHERE {$whereClause}
            GROUP BY e.id
            ORDER BY 
                CASE 
                    WHEN '{$now}' >= es.start_time AND '{$now}' <= es.end_time THEN 1
                    WHEN '{$now}' < es.start_time THEN 2
                    ELSE 3
                END ASC,
                es.start_time ASC
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $idx => $val) {
            $stmt->bindValue($idx + 1, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dapatkan riwayat ujian yang pernah diikuti siswa (UC-05: Lihat Riwayat & Status Ujian)
     */
    public function getStudentExamHistory(int $studentId, array $filters = []): array
    {
        $stId = (int)$studentId;
        $where = [
            "e.deleted_at IS NULL",
            "(ep.student_id = {$stId} OR sess.student_id = {$stId} OR res.student_id = {$stId})"
        ];
        $params = [];

        if (!empty($filters['subject_id'])) {
            $where[] = "e.subject_id = :subject_id";
            $params[':subject_id'] = (int)$filters['subject_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(e.title LIKE :search OR e.code LIKE :search2 OR s.name LIKE :search3)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
            $params[':search2'] = '%' . trim($filters['search']) . '%';
            $params[':search3'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                e.id as exam_id,
                e.title,
                e.code as exam_code,
                e.type as exam_type,
                e.duration_minutes,
                e.passing_grade,
                e.show_result_immediately,
                e.is_dual_cam_required,
                e.status as exam_status,
                s.id as subject_id,
                s.name as subject_name,
                s.code as subject_code,
                u.name as teacher_name,
                es.start_time,
                es.end_time,
                c.name as class_name,
                ep.status as participant_status,
                ep.score as participant_score,
                sess.id as session_id,
                sess.status as session_status,
                sess.start_time as session_start_time,
                sess.submit_time as session_submit_time,
                sess.integrity_score as session_integrity_score,
                res.id as result_id,
                res.final_score,
                res.multiple_choice_score,
                res.essay_score,
                res.proctoring_integrity_score,
                res.is_passed,
                res.status as result_status,
                res.total_questions,
                res.correct_answers,
                res.wrong_answers,
                (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON e.teacher_id = u.id
            LEFT JOIN exam_schedules es ON es.exam_id = e.id
            LEFT JOIN classes c ON es.class_id = c.id
            LEFT JOIN {$this->table} ep ON ep.exam_id = e.id AND ep.student_id = {$stId}
            LEFT JOIN exam_sessions sess ON sess.exam_id = e.id AND sess.student_id = {$stId}
            LEFT JOIN exam_results res ON res.exam_id = e.id AND res.student_id = {$stId}
            WHERE {$whereSql}
            GROUP BY e.id
            ORDER BY COALESCE(sess.submit_time, sess.start_time, es.start_time, e.created_at) DESC
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dapatkan ringkasan statistik ujian siswa untuk dashboard
     */
    public function getStudentStats(int $studentId): array
    {
        $availableExams = $this->getAvailableExamsForStudent($studentId);
        $history = $this->getStudentExamHistory($studentId);

        $totalAvailable = 0;
        $ongoingToday = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($availableExams as $ex) {
            if ($ex['time_status'] === 'ongoing' || $ex['time_status'] === 'upcoming') {
                $totalAvailable++;
            }
            if ($ex['time_status'] === 'ongoing') {
                $ongoingToday++;
            }
        }

        $completedExams = 0;
        $passedExams = 0;
        $totalScores = 0.0;
        $scoreCount = 0;

        foreach ($history as $h) {
            $isSubmitted = ($h['session_status'] === 'submitted' || $h['participant_status'] === 'completed' || $h['participant_status'] === 'submitted' || !empty($h['result_id']));
            if ($isSubmitted) {
                $completedExams++;
                $score = $h['final_score'] ?? $h['participant_score'] ?? null;
                if ($score !== null) {
                    $totalScores += (float)$score;
                    $scoreCount++;
                    if ((float)$score >= (float)$h['passing_grade']) {
                        $passedExams++;
                    }
                }
            }
        }

        $averageScore = $scoreCount > 0 ? round($totalScores / $scoreCount, 1) : 0.0;

        return [
            'total_available'  => $totalAvailable,
            'ongoing_today'    => $ongoingToday,
            'completed_exams'  => $completedExams,
            'passed_exams'     => $passedExams,
            'average_score'    => $averageScore,
            'total_participated' => count($history)
        ];
    }

    /**
     * Validasi akses peserta didik terhadap ujian tertentu
     * 
     * Memeriksa 4 kondisi utama:
     * 1. Ujian belum dipublikasikan (status draft / archived)
     * 2. Peserta tidak terdaftar (bukan kelas target)
     * 3. Ujian belum dimulai (jadwal waktu di masa depan)
     * 4. Ujian sudah selesai (waktu pengerjaan telah berakhir)
     *
     * @return array{allowed: bool, code: string, reason: ?string, exam?: array, schedule?: array, session?: array, participant?: array}
     */
    public function validateStudentAccess(int $examId, int $studentId): array
    {
        // 1. Ambil data ujian
        $examSql = "
            SELECT e.*, s.name as subject_name, s.code as subject_code, u.name as teacher_name,
                   er.lock_browser, er.detect_multiple_faces, er.detect_look_away,
                   er.detect_audio_noise, er.detect_secondary_device, er.max_tab_switches
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON e.teacher_id = u.id
            LEFT JOIN exam_rules er ON er.exam_id = e.id
            WHERE e.id = :id AND e.deleted_at IS NULL
            LIMIT 1
        ";
        $eStmt = $this->db()->prepare($examSql);
        $eStmt->bindValue(':id', $examId, PDO::PARAM_INT);
        $eStmt->execute();
        $exam = $eStmt->fetch();

        if (!$exam) {
            return [
                'allowed' => false,
                'code'    => 'NOT_FOUND',
                'reason'  => 'Ujian tidak ditemukan atau telah dihapus oleh sistem.'
            ];
        }

        // 2. Validasi status publikasi
        if (in_array($exam['status'], ['draft', 'archived'], true)) {
            return [
                'allowed' => false,
                'code'    => 'NOT_PUBLISHED',
                'reason'  => 'Ujian belum dipublikasikan oleh guru/pengawas dan belum dapat diakses.',
                'exam'    => $exam
            ];
        }

        // 3. Validasi kepesertaan kelas
        $isEnrolled = $this->isStudentEnrolled($examId, $studentId);
        if (!$isEnrolled) {
            return [
                'allowed' => false,
                'code'    => 'NOT_REGISTERED',
                'reason'  => 'Anda tidak terdaftar sebagai peserta ujian pada kelas/jadwal ini.',
                'exam'    => $exam
            ];
        }

        // 4. Ambil jadwal kelas siswa
        $classIds = $this->getStudentClassIds($studentId);
        $schSql = "
            SELECT es.*, c.name as class_name
            FROM exam_schedules es
            JOIN classes c ON es.class_id = c.id
            WHERE es.exam_id = :exam_id
        ";
        if (!empty($classIds)) {
            $cList = implode(',', $classIds);
            $schSql .= " AND es.class_id IN ({$cList})";
        }
        $schSql .= " ORDER BY es.start_time ASC LIMIT 1";

        $schStmt = $this->db()->prepare($schSql);
        $schStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $schStmt->execute();
        $schedule = $schStmt->fetch() ?: null;

        $now = date('Y-m-d H:i:s');

        // 5. Validasi waktu mulai
        if ($schedule && $now < $schedule['start_time']) {
            $formattedStart = date('d M Y, H:i', strtotime($schedule['start_time'])) . ' WIB';
            return [
                'allowed'  => false,
                'code'     => 'NOT_STARTED',
                'reason'   => "Ujian belum dimulai. Pelaksanaan dijadwalkan pada {$formattedStart}.",
                'exam'     => $exam,
                'schedule' => $schedule
            ];
        }

        // 6. Validasi waktu selesai
        if ($schedule && $now > $schedule['end_time']) {
            $formattedEnd = date('d M Y, H:i', strtotime($schedule['end_time'])) . ' WIB';
            return [
                'allowed'  => false,
                'code'     => 'EXPIRED',
                'reason'   => "Waktu pelaksanaan ujian telah berakhir pada {$formattedEnd}.",
                'exam'     => $exam,
                'schedule' => $schedule
            ];
        }

        // 7. Ambil status partisipan & sesi aktif
        $partSql = "SELECT * FROM {$this->table} WHERE exam_id = :exam_id AND student_id = :student_id LIMIT 1";
        $pStmt = $this->db()->prepare($partSql);
        $pStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $pStmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $pStmt->execute();
        $participant = $pStmt->fetch() ?: null;

        $sessSql = "SELECT * FROM exam_sessions WHERE exam_id = :exam_id AND student_id = :student_id ORDER BY id DESC LIMIT 1";
        $sStmt = $this->db()->prepare($sessSql);
        $sStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $sStmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $sStmt->execute();
        $session = $sStmt->fetch() ?: null;

        // 8. Cek apakah sudah submit
        if (($participant && in_array($participant['status'], ['completed', 'submitted'], true)) ||
            ($session && in_array($session['status'], ['submitted', 'terminated'], true))) {
            return [
                'allowed'     => false,
                'code'        => 'ALREADY_SUBMITTED',
                'reason'      => 'Anda telah menyelesaikan dan mengumpulkan ujian ini.',
                'exam'        => $exam,
                'schedule'    => $schedule,
                'participant' => $participant,
                'session'     => $session
            ];
        }

        return [
            'allowed'     => true,
            'code'        => 'OK',
            'reason'      => null,
            'exam'        => $exam,
            'schedule'    => $schedule,
            'participant' => $participant,
            'session'     => $session
        ];
    }

    /**
     * Sinkronisasi siswa dari kelas-kelas terpilih ke dalam peserta ujian
     */
    public function syncParticipantsFromClasses(int $examId, array $classIds): int
    {
        $delSql = "DELETE FROM {$this->table} WHERE exam_id = :exam_id AND status = 'not_started'";
        $delStmt = $this->db()->prepare($delSql);
        $delStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $delStmt->execute();

        if (empty($classIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        
        $studSql = "SELECT DISTINCT cs.student_id, cs.class_id 
                    FROM class_students cs
                    JOIN users u ON cs.student_id = u.id
                    WHERE cs.class_id IN ({$placeholders}) 
                      AND cs.status = 'active'
                      AND u.status = 'active'";
        $studStmt = $this->db()->prepare($studSql);
        foreach ($classIds as $idx => $cid) {
            $studStmt->bindValue($idx + 1, (int)$cid, PDO::PARAM_INT);
        }
        $studStmt->execute();
        $students = $studStmt->fetchAll() ?: [];

        if (empty($students)) {
            return 0;
        }

        $insSql = "INSERT IGNORE INTO {$this->table} 
                   (exam_id, student_id, class_id, status, created_at, updated_at) 
                   VALUES (:exam_id, :student_id, :class_id, 'not_started', NOW(), NOW())";
        $insStmt = $this->db()->prepare($insSql);

        $inserted = 0;
        foreach ($students as $st) {
            $insStmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
            $insStmt->bindValue(':student_id', (int)$st['student_id'], PDO::PARAM_INT);
            $insStmt->bindValue(':class_id', (int)$st['class_id'], PDO::PARAM_INT);
            $insStmt->execute();
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Update status peserta ujian berdasarkan exam_id dan student_id
     */
    public function updateParticipantStatus(int $examId, int $studentId, string $status): bool
    {
        $sql = "UPDATE {$this->table} 
                SET status = :status, updated_at = NOW() 
                WHERE exam_id = :exam_id AND student_id = :student_id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

