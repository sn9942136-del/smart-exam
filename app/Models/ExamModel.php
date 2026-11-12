<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Model (Ujian)
 * Mengelola entitas ujian online, relasi mata pelajaran, guru pengampu, status ujian, dan metrik pelaksanaan.
 */
class ExamModel extends BaseModel
{
    protected string $table = 'exams';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'subject_id',
        'teacher_id',
        'title',
        'code',
        'description',
        'type', // 'quiz', 'midterm', 'final_exam', 'tryout', 'daily_test'
        'duration_minutes',
        'passing_grade',
        'randomize_questions',
        'randomize_options',
        'show_result_immediately',
        'is_dual_cam_required',
        'status', // 'draft', 'published', 'scheduled', 'ongoing', 'in_progress', 'completed', 'archived'
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan daftar ujian dengan pagination, pencarian, dan filter role
     */
    public function getPaginatedExams(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $subjectId = null,
        ?string $status = null,
        ?int $teacherId = null
    ): array {
        $conditions = ["e.deleted_at IS NULL"];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $conditions[] = "(e.title LIKE :search_title OR e.code LIKE :search_code OR s.name LIKE :search_subject OR u.name LIKE :search_teacher)";
            $searchTerm = '%' . trim($search) . '%';
            $params[':search_title'] = $searchTerm;
            $params[':search_code'] = $searchTerm;
            $params[':search_subject'] = $searchTerm;
            $params[':search_teacher'] = $searchTerm;
        }

        if ($subjectId !== null && $subjectId !== '' && $subjectId !== 'all') {
            $conditions[] = "e.subject_id = :subject_id";
            $params[':subject_id'] = (int)$subjectId;
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'ongoing') {
                $conditions[] = "e.status IN ('ongoing', 'in_progress')";
            } else {
                $conditions[] = "e.status = :status";
                $params[':status'] = $status;
            }
        }

        if ($teacherId !== null && $teacherId > 0) {
            $conditions[] = "e.teacher_id = :teacher_id";
            $params[':teacher_id'] = $teacherId;
        }

        $whereClause = implode(' AND ', $conditions);

        // 1. Hitung total data
        $countSql = "SELECT COUNT(*) as total 
                     FROM {$this->table} e 
                     LEFT JOIN subjects s ON e.subject_id = s.id 
                     LEFT JOIN users u ON e.teacher_id = u.id
                     WHERE {$whereClause}";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $countStmt->bindValue($k, $v, $type);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetchColumn() ?: 0);

        // 2. Ambil data halaman aktif beserta relasi ringkas
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT e.*,
                       s.name as subject_name,
                       s.code as subject_code,
                       u.name as teacher_name,
                       u.email as teacher_email,
                       (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count,
                       (SELECT COUNT(*) FROM exam_participants ep WHERE ep.exam_id = e.id) as participant_count,
                       (SELECT token FROM exam_tokens et WHERE et.exam_id = e.id AND et.is_active = 1 ORDER BY et.id DESC LIMIT 1) as active_token,
                       (SELECT MIN(start_time) FROM exam_schedules es WHERE es.exam_id = e.id) as earliest_schedule,
                       (SELECT MAX(end_time) FROM exam_schedules es WHERE es.exam_id = e.id) as latest_schedule
                FROM {$this->table} e
                LEFT JOIN subjects s ON e.subject_id = s.id
                LEFT JOIN users u ON e.teacher_id = u.id
                WHERE {$whereClause}
                ORDER BY e.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll() ?: [];

        foreach ($data as &$row) {
            $row['exam_code'] = $row['code'] ?? '';
            $row['created_by_name'] = $row['teacher_name'] ?? '';
            $row['created_by_email'] = $row['teacher_email'] ?? '';
            $row['active_token_code'] = $row['active_token'] ?? null;
            $row['require_dual_camera'] = (int)($row['is_dual_cam_required'] ?? 0);
            $row['start_time'] = $row['earliest_schedule'] ?? null;
            $row['end_time'] = $row['latest_schedule'] ?? null;
        }

        $totalPages = (int)ceil($total / max(1, $perPage));

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, $totalPages),
            'page'         => $page,
            'total_pages'  => max(1, $totalPages)
        ];
    }

    /**
     * Dapatkan detail lengkap ujian beserta aturan AI, jadwal, token, soal, dan peserta
     */
    public function getDetailsWithFullRelations(int $id): ?array
    {
        $sql = "SELECT e.*,
                       s.name as subject_name,
                       s.code as subject_code,
                       s.description as subject_description,
                       u.name as teacher_name,
                       u.email as teacher_email,
                       u.nip_nisn as teacher_nip
                FROM {$this->table} e
                LEFT JOIN subjects s ON e.subject_id = s.id
                LEFT JOIN users u ON e.teacher_id = u.id
                WHERE e.id = :id AND e.deleted_at IS NULL";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $exam = $stmt->fetch();

        if (!$exam) {
            return null;
        }

        // Aturan Ujian (Rules)
        $ruleSql = "SELECT * FROM exam_rules WHERE exam_id = :exam_id LIMIT 1";
        $ruleStmt = $this->db()->prepare($ruleSql);
        $ruleStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $ruleStmt->execute();
        $exam['rules'] = $ruleStmt->fetch() ?: [];

        // Token Aktif & Riwayat Token
        $tokenSql = "SELECT * FROM exam_tokens WHERE exam_id = :exam_id ORDER BY id DESC";
        $tokenStmt = $this->db()->prepare($tokenSql);
        $tokenStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $tokenStmt->execute();
        $tokens = $tokenStmt->fetchAll() ?: [];
        $exam['tokens'] = $tokens;
        $exam['active_token'] = null;
        foreach ($tokens as $tok) {
            if (!empty($tok['is_active'])) {
                $exam['active_token'] = $tok;
                break;
            }
        }

        // Jadwal Ujian per Kelas (Schedules)
        $schedSql = "SELECT es.*, c.name as class_name, c.grade_level as class_level, c.major as class_major
                     FROM exam_schedules es
                     LEFT JOIN classes c ON es.class_id = c.id
                     WHERE es.exam_id = :exam_id
                     ORDER BY es.start_time ASC";
        $schedStmt = $this->db()->prepare($schedSql);
        $schedStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $schedStmt->execute();
        $exam['schedules'] = $schedStmt->fetchAll() ?: [];

        // Butir Soal Terkait (Questions)
        $qSql = "SELECT eq.*, q.content, q.type as question_type, q.difficulty, q.weight, q.question_image, q.explanation
                 FROM exam_questions eq
                 JOIN questions q ON eq.question_id = q.id
                 WHERE eq.exam_id = :exam_id
                 ORDER BY eq.order_number ASC, eq.id ASC";
        $qStmt = $this->db()->prepare($qSql);
        $qStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $qStmt->execute();
        $exam['questions'] = $qStmt->fetchAll() ?: [];

        // Peserta Didik Terdaftar (Participants Count & Summary)
        $partSql = "SELECT 
                        COUNT(*) as total_participants,
                        SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started_count,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                        SUM(CASE WHEN status IN ('completed', 'submitted') THEN 1 ELSE 0 END) as completed_count,
                        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count,
                        AVG(score) as average_score,
                        MAX(score) as highest_score,
                        MIN(score) as lowest_score
                    FROM exam_participants 
                    WHERE exam_id = :exam_id";
        $partStmt = $this->db()->prepare($partSql);
        $partStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $partStmt->execute();
        $exam['participants_summary'] = $partStmt->fetch() ?: [];

        // Daftar Peserta Detail
        $pListSql = "SELECT ep.*, u.name as student_name, u.nip_nisn as student_nis, u.email as student_email, c.name as class_name
                     FROM exam_participants ep
                     JOIN users u ON ep.student_id = u.id
                     LEFT JOIN classes c ON ep.class_id = c.id
                     WHERE ep.exam_id = :exam_id
                     ORDER BY c.name ASC, u.name ASC";
        $pListStmt = $this->db()->prepare($pListSql);
        $pListStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $pListStmt->execute();
        $exam['participants'] = $pListStmt->fetchAll() ?: [];

        // Bank Soal Terkait Info
        $bankSql = "SELECT qb.id as bank_id, qb.title as bank_title, qb.code as bank_code
                    FROM exam_questions eq
                    JOIN questions q ON eq.question_id = q.id
                    JOIN question_banks qb ON q.question_bank_id = qb.id
                    WHERE eq.exam_id = :exam_id
                    LIMIT 1";
        $bankStmt = $this->db()->prepare($bankSql);
        $bankStmt->bindValue(':exam_id', $id, PDO::PARAM_INT);
        $bankStmt->execute();
        $bankInfo = $bankStmt->fetch();
        if ($bankInfo) {
            $exam['bank_id'] = $bankInfo['bank_id'];
            $exam['bank_title'] = $bankInfo['bank_title'];
            $exam['bank_code'] = $bankInfo['bank_code'];
            $exam['question_bank_id'] = $bankInfo['bank_id'];
        }

        // Aliases untuk kompatibilitas tampilan
        $exam['exam_code'] = $exam['code'] ?? '';
        $exam['created_by_name'] = $exam['teacher_name'] ?? '';
        $exam['created_by_email'] = $exam['teacher_email'] ?? '';
        $exam['require_dual_camera'] = (int)($exam['is_dual_cam_required'] ?? 0);
        $exam['enable_ai_proctoring'] = 1;
        $exam['all_tokens'] = $exam['tokens'] ?? [];
        $exam['total_questions'] = count($exam['questions']);

        return $exam;
    }

    /**
     * Generate kode unik ujian
     * Contoh: EXAM-MAT10-8492
     */
    public function generateExamCode(string $subjectCode): string
    {
        $cleanPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subjectCode));
        if (empty($cleanPrefix)) {
            $cleanPrefix = 'EXAM';
        }

        do {
            $randomDigits = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $candidateCode = "EXAM-{$cleanPrefix}-{$randomDigits}";
            $existing = $this->where('code', $candidateCode)->first();
        } while ($existing !== null);

        return $candidateCode;
    }

    /**
     * Update status ujian
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowedStatuses = ['draft', 'published', 'scheduled', 'ongoing', 'in_progress', 'completed', 'archived'];
        if (!in_array($status, $allowedStatuses, true)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Ringkasan statistik ujian (Global atau per Guru)
     */
    public function getSummaryStats(?int $teacherId = null): array
    {
        $conditions = ["deleted_at IS NULL"];
        $params = [];
        if ($teacherId !== null && $teacherId > 0) {
            $conditions[] = "teacher_id = :teacher_id";
            $params[':teacher_id'] = $teacherId;
        }
        $where = implode(' AND ', $conditions);

        $sql = "SELECT 
                    COUNT(*) as total_exams,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_exams,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_exams,
                    SUM(CASE WHEN status IN ('ongoing', 'in_progress') THEN 1 ELSE 0 END) as ongoing_exams,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exams,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_exams
                FROM {$this->table} 
                WHERE {$where}";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $stats = $stmt->fetch() ?: [];

        // Hitung token aktif
        $tokSql = "SELECT COUNT(*) FROM exam_tokens et 
                   JOIN exams e ON et.exam_id = e.id 
                   WHERE et.is_active = 1 AND e.deleted_at IS NULL";
        if ($teacherId !== null && $teacherId > 0) {
            $tokSql .= " AND e.teacher_id = " . (int)$teacherId;
        }
        $activeTokens = (int)$this->db()->query($tokSql)->fetchColumn();

        $total = (int)($stats['total_exams'] ?? 0);
        $published = (int)($stats['published_exams'] ?? 0);
        $scheduled = (int)($stats['scheduled_exams'] ?? 0);
        $ongoing = (int)($stats['ongoing_exams'] ?? 0);
        $completed = (int)($stats['completed_exams'] ?? 0);
        $draft = (int)($stats['draft_exams'] ?? 0);

        return [
            'total_exams'     => $total,
            'published_exams' => $published,
            'scheduled_exams' => $scheduled,
            'ongoing_exams'   => $ongoing,
            'completed_exams' => $completed,
            'draft_exams'     => $draft,
            // Aliases untuk antarmuka Admin & Widget
            'total'           => $total,
            'ongoing'         => $ongoing,
            'scheduled'       => ($scheduled + $published),
            'completed'       => $completed,
            'draft'           => $draft,
            'active_tokens'   => $activeTokens
        ];
    }

    /**
     * Dapatkan seluruh ujian beserta ringkasan status koreksi & penyerahan untuk grading
     */
    public function getAllWithStats(?int $teacherId = null): array
    {
        $where = "e.deleted_at IS NULL";
        $params = [];
        if ($teacherId !== null && $teacherId > 0) {
            $where .= " AND e.teacher_id = :tid";
            $params[':tid'] = $teacherId;
        }

        $sql = "
            SELECT 
                e.*,
                s.name as subject_name,
                s.code as subject_code,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as question_count,
                (SELECT COUNT(*) FROM exam_sessions es WHERE es.exam_id = e.id) as total_submissions,
                (SELECT COUNT(*) FROM exam_results er WHERE er.exam_id = e.id AND er.status = 'published') as published_submissions,
                (SELECT COUNT(*) FROM exam_results er WHERE er.exam_id = e.id AND er.status = 'pending_grading') as pending_grading_count,
                (SELECT AVG(er.final_score) FROM exam_results er WHERE er.exam_id = e.id) as average_score
            FROM {$this->table} e
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON e.teacher_id = u.id
            WHERE {$where}
            ORDER BY e.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

