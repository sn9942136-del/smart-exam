<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamSessionModel;
use App\Models\SubjectModel;
use App\Services\ExamTimerService;

/**
 * Student Exam Controller
 * Mengelola katalog ujian tersedia untuk kelas siswa (UC-06), validasi akses & status jadwal,
 * serta riwayat pengerjaan ujian siswa (UC-05).
 */
class StudentExamController extends BaseController
{
    private ExamModel $examModel;
    private ExamParticipantModel $participantModel;
    private SubjectModel $subjectModel;
    private ExamSessionModel $sessionModel;
    private ExamTimerService $timerService;
    private ExamAnswerModel $answerModel;
    private ExamQuestionModel $examQuestionModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->examModel = new ExamModel();
        $this->participantModel = new ExamParticipantModel();
        $this->subjectModel = new SubjectModel();
        $this->sessionModel = new ExamSessionModel();
        $this->timerService = new ExamTimerService();
        $this->answerModel = new ExamAnswerModel();
        $this->examQuestionModel = new ExamQuestionModel();
    }

    /**
     * Daftar Ujian Tersedia untuk Kelas Siswa (UC-06: Pilih Mata Pelajaran/Ujian)
     */
    public function index(): Response
    {
        $studentId = (int) user_id();
        $subjectId = (int) $this->request->get('subject_id', 0);
        $search = (string) $this->request->get('search', '');
        $statusFilter = (string) $this->request->get('status', 'all');

        $filters = [];
        if ($subjectId > 0) {
            $filters['subject_id'] = $subjectId;
        }
        if (!empty($search)) {
            $filters['search'] = $search;
        }

        $allExams = $this->participantModel->getAvailableExamsForStudent($studentId, $filters);

        // Filter by time status jika dipilih
        if ($statusFilter === 'ongoing') {
            $exams = array_filter($allExams, static fn($e) => $e['time_status'] === 'ongoing');
        } elseif ($statusFilter === 'upcoming') {
            $exams = array_filter($allExams, static fn($e) => $e['time_status'] === 'upcoming');
        } else {
            $exams = $allExams;
        }

        // Peta Sesi Ongoing Aktif Siswa (Exam Session Recovery)
        $activeSessions = $this->sessionModel->where('student_id', $studentId)->where('status', 'ongoing')->findAll();
        $activeSessionExamMap = [];
        foreach ($activeSessions as $as) {
            $timerVal = $this->timerService->validateActiveExamSession($as['session_token'], $studentId);
            if ($timerVal['valid'] && empty($timerVal['is_expired'])) {
                $activeSessionExamMap[(int)$as['exam_id']] = [
                    'session_token'     => $as['session_token'],
                    'remaining_seconds' => $timerVal['remaining_seconds']
                ];
            }
        }

        $subjects = $this->subjectModel->getActiveSubjects();
        $studentClass = $this->participantModel->getStudentClass($studentId);

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Daftar Ujian']
        ];

        $data = [
            'pageTitle'            => 'Daftar Ujian Tersedia',
            'exams'                => array_values($exams),
            'subjects'             => $subjects,
            'studentClass'         => $studentClass,
            'selectedSubject'      => $subjectId,
            'selectedStatus'       => $statusFilter,
            'search'               => $search,
            'activeSessionExamMap' => $activeSessionExamMap,
            'breadcrumbs'          => $breadcrumbs
        ];

        return $this->view('siswa/exams/index', $data, 'layouts/peserta');
    }

    /**
     * Detail & Konfirmasi Informasi Ujian dengan Validasi Akses Lengkap & Recovery Sesi Aktif
     */
    public function show(string|int $id): Response
    {
        $examId = (int) $id;
        $studentId = (int) user_id();

        // Validasi akses komprehensif
        $validation = $this->participantModel->validateStudentAccess($examId, $studentId);

        if ($validation['code'] === 'NOT_FOUND') {
            Session::setFlash('danger', $validation['reason']);
            return $this->redirect(base_url('siswa/exams'));
        }

        $exam = $validation['exam'] ?? $this->examModel->find($examId);
        $schedule = $validation['schedule'] ?? null;
        $studentClass = $this->participantModel->getStudentClass($studentId);

        // Periksa Sesi Ongoing untuk Exam Session Recovery (UC-14)
        $activeSession = $this->sessionModel->findActiveSession($examId, $studentId);
        $recoveryData = null;
        if ($activeSession) {
            $timerValidation = $this->timerService->validateActiveExamSession($activeSession['session_token'], $studentId);
            if ($timerValidation['valid'] && empty($timerValidation['is_expired'])) {
                $counts = $this->answerModel->countAnsweredQuestions((int)$activeSession['id']);
                $questions = $this->examQuestionModel->getQuestionsByExamId($examId);
                $totalQuestions = count($questions);

                $recoveryData = [
                    'session'           => $activeSession,
                    'session_token'     => $activeSession['session_token'],
                    'exam_title'        => $exam['title'] ?? 'Ujian',
                    'remaining_seconds' => $timerValidation['remaining_seconds'],
                    'answered_count'    => $counts['answered'] ?? 0,
                    'flagged_count'     => $counts['flagged'] ?? 0,
                    'total_questions'   => $totalQuestions,
                    'start_time'        => $activeSession['start_time'],
                    'room_url'          => base_url('siswa/exam/room/' . $activeSession['session_token'])
                ];
            }
        }

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Daftar Ujian', 'url' => base_url('siswa/exams')],
            ['title' => 'Informasi & Konfirmasi Ujian']
        ];

        $data = [
            'pageTitle'    => 'Informasi Ujian: ' . ($exam['title'] ?? 'Detail Ujian'),
            'exam'         => $exam,
            'schedule'     => $schedule,
            'studentClass' => $studentClass,
            'validation'   => $validation,
            'recoveryData' => $recoveryData,
            'breadcrumbs'  => $breadcrumbs
        ];

        return $this->view('siswa/exams/show', $data, 'layouts/peserta');
    }

    /**
     * Riwayat Seluruh Ujian yang Pernah Diikuti Siswa (UC-05: Lihat Riwayat & Status Ujian)
     */
    public function history(): Response
    {
        $studentId = (int) user_id();
        $subjectId = (int) $this->request->get('subject_id', 0);
        $search = (string) $this->request->get('search', '');

        $filters = [];
        if ($subjectId > 0) {
            $filters['subject_id'] = $subjectId;
        }
        if (!empty($search)) {
            $filters['search'] = $search;
        }

        $history = $this->participantModel->getStudentExamHistory($studentId, $filters);
        $subjects = $this->subjectModel->getActiveSubjects();
        $studentClass = $this->participantModel->getStudentClass($studentId);

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Riwayat Ujian']
        ];

        $data = [
            'pageTitle'       => 'Riwayat Ujian & Evaluasi',
            'history'         => $history,
            'subjects'        => $subjects,
            'studentClass'    => $studentClass,
            'selectedSubject' => $subjectId,
            'search'          => $search,
            'breadcrumbs'     => $breadcrumbs
        ];

        return $this->view('siswa/exams/history', $data, 'layouts/peserta');
    }
}
