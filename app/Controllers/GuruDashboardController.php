<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\QuestionBankModel;
use App\Models\SubjectModel;
use App\Models\UserModel;

/**
 * Guru Dashboard Controller
 * Menangani tampilan beranda dan ringkasan metrik statistik untuk pengguna Guru.
 */
class GuruDashboardController extends BaseController
{
    protected QuestionBankModel $bankModel;
    protected SubjectModel $subjectModel;
    protected UserModel $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->bankModel = new QuestionBankModel();
        $this->subjectModel = new SubjectModel();
        $this->userModel = new UserModel();
    }

    /**
     * Halaman Dashboard Guru
     */
    public function index(): Response
    {
        $userId = (int)user_id();
        $currentUser = auth_user();

        // 1. Ambil statistik bank soal
        $bankStats = $this->bankModel->getSummaryStats($userId);

        // 2. Ambil bank soal terbaru milik guru
        $recentBanksResult = $this->bankModel->getPaginatedBanks(1, 5, null, null, null, $userId);
        $recentBanks = $recentBanksResult['data'] ?? [];

        // 3. Ambil daftar mata pelajaran
        $subjects = $this->subjectModel->getAllActive();

        // 4. Hitung statistik riil pengawasan & ujian milik guru
        $db = \App\Libraries\Database::getConnection();
        $upcomingExams = (int) ($db->query("SELECT COUNT(*) FROM exams WHERE teacher_id = {$userId} AND status IN ('published', 'scheduled')")->fetchColumn() ?: 0);
        $activeRooms = (int) ($db->query("SELECT COUNT(*) FROM exams WHERE teacher_id = {$userId} AND status IN ('published', 'ongoing')")->fetchColumn() ?: 0);
        $gradedEssays = (int) ($db->query("SELECT COUNT(DISTINCT ea.id) FROM exam_answers ea JOIN exam_sessions es ON es.id = ea.exam_session_id JOIN exams e ON e.id = es.exam_id JOIN questions q ON q.id = ea.question_id WHERE e.teacher_id = {$userId} AND e.deleted_at IS NULL AND q.type = 'essay' AND q.deleted_at IS NULL AND (ea.is_correct IS NOT NULL OR ea.score_earned > 0 OR ea.teacher_feedback IS NOT NULL)")->fetchColumn() ?: 0);
        $pendingEssays = (int) ($db->query("SELECT COUNT(DISTINCT ea.id) FROM exam_answers ea JOIN exam_sessions es ON es.id = ea.exam_session_id JOIN exams e ON e.id = es.exam_id JOIN questions q ON q.id = ea.question_id WHERE e.teacher_id = {$userId} AND e.deleted_at IS NULL AND q.type = 'essay' AND q.deleted_at IS NULL AND ea.is_correct IS NULL AND (ea.score_earned = 0 OR ea.score_earned IS NULL) AND ea.teacher_feedback IS NULL")->fetchColumn() ?: 0);

        $stats = [
            'question_banks' => $bankStats['total_banks'] ?? 0,
            'total_items'    => $bankStats['total_questions'] ?? 0,
            'upcoming_exams' => $upcomingExams,
            'graded_essays'  => $gradedEssays,
            'pending_essays' => $pendingEssays,
            'active_rooms'   => $activeRooms
        ];

        return $this->renderView('dashboard/guru', [
            'pageTitle'   => 'Dashboard Guru Pengajar',
            'user'        => $currentUser,
            'stats'       => $stats,
            'recentBanks' => $recentBanks,
            'subjects'    => $subjects
        ], 'layouts/guru');
    }
}
