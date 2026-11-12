<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamParticipantModel;
use App\Models\ExamResultModel;
use App\Models\SubjectModel;

/**
 * Student Result Controller
 * Mengelola tampilan hasil nilai ujian, evaluasi kelulusan KKM,
 * dan skor integritas proctoring AI siswa (UC-05).
 */
class StudentResultController extends BaseController
{
    private ExamResultModel $resultModel;
    private ExamParticipantModel $participantModel;
    private SubjectModel $subjectModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->resultModel = new ExamResultModel();
        $this->participantModel = new ExamParticipantModel();
        $this->subjectModel = new SubjectModel();
    }

    /**
     * Daftar Nilai & Hasil Ujian yang Telah Dirilis (UC-05: Lihat Hasil Ujian)
     */
    public function index(): Response
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

        $results = $this->resultModel->getPublishedResultsForStudent($studentId, $filters);
        $subjects = $this->subjectModel->getActiveSubjects();
        $studentClass = $this->participantModel->getStudentClass($studentId);

        // Hitung statistik nilai
        $totalScores = 0.0;
        $passedCount = 0;
        foreach ($results as $res) {
            $score = (float)$res['final_score'];
            $totalScores += $score;
            if ($score >= (float)$res['passing_grade']) {
                $passedCount++;
            }
        }
        $avgScore = count($results) > 0 ? round($totalScores / count($results), 1) : 0.0;

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Hasil & Nilai Ujian']
        ];

        $data = [
            'pageTitle'       => 'Daftar Hasil & Nilai Ujian',
            'results'         => $results,
            'subjects'        => $subjects,
            'studentClass'    => $studentClass,
            'selectedSubject' => $subjectId,
            'search'          => $search,
            'stats'           => [
                'total_results' => count($results),
                'passed_count'  => $passedCount,
                'avg_score'     => $avgScore
            ],
            'breadcrumbs'     => $breadcrumbs
        ];

        return $this->view('siswa/results/index', $data, 'layouts/peserta');
    }

    /**
     * Rincian Evaluasi Hasil Ujian, Skor KKM & Integritas AI Siswa (UC-05)
     */
    public function show(string|int $id): Response
    {
        $studentId = (int) user_id();
        $result = $this->resultModel->getResultDetailForStudent((int)$id, $studentId);

        if (!$result) {
            Session::setFlash('danger', 'Hasil ujian tidak ditemukan atau Anda tidak memiliki akses.');
            return $this->redirect(base_url('siswa/results'));
        }

        $studentClass = $this->participantModel->getStudentClass($studentId);

        // Ambil daftar butir soal & jawaban siswa jika hasil sudah dirilis
        $questionAnswers = [];
        if (!empty($result['is_released']) && !empty($result['exam_session_id'])) {
            $resultService = new \App\Services\ResultService();
            $gradingData = $resultService->getSessionAnswersForGrading((int)$result['exam_session_id']);
            if ($gradingData && !empty($gradingData['answers'])) {
                $questionAnswers = $gradingData['answers'];
            }
        }

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Hasil & Nilai Ujian', 'url' => base_url('siswa/results')],
            ['title' => 'Rincian Hasil: ' . $result['exam_title']]
        ];

        $data = [
            'pageTitle'        => 'Rincian Hasil Ujian: ' . $result['exam_title'],
            'result'           => $result,
            'studentClass'     => $studentClass,
            'questionAnswers'  => $questionAnswers,
            'breadcrumbs'      => $breadcrumbs
        ];

        return $this->view('siswa/results/show', $data, 'layouts/peserta');
    }
}
