<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\ExamAnswerModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamSessionModel;
use App\Models\SubjectModel;
use App\Services\ExamTimerService;

/**
 * Student Dashboard Controller
 * Mengelola antarmuka dashboard utama peserta didik, kesiapan perangkat,
 * ringkasan statistik ujian, dan monitoring ujian aktif hari ini.
 */
class StudentDashboardController extends BaseController
{
    private ExamParticipantModel $participantModel;
    private SubjectModel $subjectModel;
    private ExamSessionModel $sessionModel;
    private ExamTimerService $timerService;
    private ExamAnswerModel $answerModel;
    private ExamQuestionModel $examQuestionModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->participantModel = new ExamParticipantModel();
        $this->subjectModel = new SubjectModel();
        $this->sessionModel = new ExamSessionModel();
        $this->timerService = new ExamTimerService();
        $this->answerModel = new ExamAnswerModel();
        $this->examQuestionModel = new ExamQuestionModel();
    }

    /**
     * Halaman Dashboard Utama Peserta Didik
     */
    public function index(): Response
    {
        $studentId = (int) user_id();
        $user = auth_user();

        $studentClass = $this->participantModel->getStudentClass($studentId);
        $stats = $this->participantModel->getStudentStats($studentId);
        $availableExams = $this->participantModel->getAvailableExamsForStudent($studentId);
        $recentHistory = array_slice($this->participantModel->getStudentExamHistory($studentId), 0, 5);

        // Periksa Sesi Ongoing untuk Exam Session Recovery (UC-14)
        $activeSession = $this->sessionModel->findAnyActiveSessionForStudent($studentId);
        $ongoingRecovery = null;
        if ($activeSession) {
            $timerVal = $this->timerService->validateActiveExamSession($activeSession['session_token'], $studentId);
            if ($timerVal['valid'] && empty($timerVal['is_expired'])) {
                $counts = $this->answerModel->countAnsweredQuestions((int)$activeSession['id']);
                $questions = $this->examQuestionModel->getQuestionsByExamId((int)$activeSession['exam_id']);
                $totalQuestions = count($questions);

                $ongoingRecovery = [
                    'session'           => $activeSession,
                    'session_token'     => $activeSession['session_token'],
                    'exam_id'           => (int)$activeSession['exam_id'],
                    'exam_title'        => $activeSession['exam_title'] ?? 'Ujian CBT',
                    'exam_code'         => $activeSession['exam_code'] ?? '-',
                    'remaining_seconds' => $timerVal['remaining_seconds'],
                    'answered_count'    => $counts['answered'] ?? 0,
                    'flagged_count'     => $counts['flagged'] ?? 0,
                    'total_questions'   => $totalQuestions,
                    'start_time'        => $activeSession['start_time'],
                    'room_url'          => base_url('siswa/exam/room/' . $activeSession['session_token'])
                ];
            }
        }

        // Filter ujian aktif hari ini / ongoing
        $todayExams = array_filter($availableExams, static function ($ex) {
            return $ex['time_status'] === 'ongoing' || $ex['time_status'] === 'upcoming';
        });

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Ruang Ujian & Kalibrasi']
        ];

        $data = [
            'pageTitle'       => 'Dashboard Peserta Didik',
            'user'            => $user,
            'studentClass'    => $studentClass,
            'stats'           => $stats,
            'availableExams'  => $availableExams,
            'todayExams'      => array_values($todayExams),
            'recentHistory'   => $recentHistory,
            'ongoingRecovery' => $ongoingRecovery,
            'breadcrumbs'     => $breadcrumbs
        ];

        return $this->view('siswa/dashboard/index', $data, 'layouts/peserta');
    }
}
