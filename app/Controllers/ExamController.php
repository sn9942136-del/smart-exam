<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Models\ClassModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamScheduleModel;
use App\Models\ExamTokenModel;
use App\Models\QuestionBankModel;
use App\Models\SubjectModel;
use App\Services\ExamService;

/**
 * Exam Controller
 * Menangani alur kerja pembuatan ujian, konfigurasi AI Proctoring, penjadwalan kelas,
 * manajemen token terpusat (UC-29), dan pratinjau ujian (UC-20a).
 */
class ExamController extends BaseController
{
    protected ExamModel $examModel;
    protected ExamService $examService;
    protected SubjectModel $subjectModel;
    protected QuestionBankModel $bankModel;
    protected ClassModel $classModel;
    protected ExamTokenModel $tokenModel;
    protected ExamScheduleModel $scheduleModel;
    protected ExamQuestionModel $examQuestionModel;
    protected ExamParticipantModel $participantModel;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->examModel = new ExamModel();
        $this->examService = new ExamService();
        $this->subjectModel = new SubjectModel();
        $this->bankModel = new QuestionBankModel();
        $this->classModel = new ClassModel();
        $this->tokenModel = new ExamTokenModel();
        $this->scheduleModel = new ExamScheduleModel();
        $this->examQuestionModel = new ExamQuestionModel();
        $this->participantModel = new ExamParticipantModel();
    }

    /**
     * Daftar Ujian (Guru & Admin)
     */
    public function index(): Response
    {
        $page = (int)($this->request->getGet('page') ?? 1);
        $search = $this->request->getGet('search');
        $subjectId = $this->request->getGet('subject_id');
        $status = $this->request->getGet('status');

        $userId = (int)user_id();
        $userRole = user_role();
        $isGuru = ($userRole === 'guru');

        // Jika Guru: hanya tampilkan ujian miliknya
        $teacherFilter = $isGuru ? $userId : null;

        $pagination = $this->examModel->getPaginatedExams(
            max(1, $page),
            10,
            $search ? (string)$search : null,
            $subjectId ? (string)$subjectId : null,
            $status ? (string)$status : null,
            $teacherFilter
        );

        $subjects = $this->subjectModel->getAllActive();
        $summary = $this->examModel->getSummaryStats($teacherFilter);

        $viewPath = $isGuru ? 'guru/exams/index' : 'admin/exams/index';
        $layout = $isGuru ? 'layouts/guru' : 'layouts/admin';

        return $this->renderView($viewPath, [
            'pageTitle'  => $isGuru ? 'Jadwal & Manajemen Ujian' : 'Monitoring Ujian Terpusat',
            'exams'      => $pagination['data'],
            'pagination' => $pagination,
            'subjects'   => $subjects,
            'summary'    => $summary,
            'stats'      => $summary, // Alias untuk kompatibilitas view admin
            'isGuru'     => $isGuru,
            'filters'    => [
                'search'     => $search ?? '',
                'subject_id' => $subjectId ?? '',
                'status'     => $status ?? ''
            ]
        ], $layout);
    }

    /**
     * Form Buat Jadwal Ujian Baru (Guru)
     */
    public function create(): Response
    {
        $userId = (int)user_id();
        $userRole = user_role();
        $isGuru = ($userRole === 'guru');

        $subjects = $this->subjectModel->getAllActive();
        $classes = $this->classModel->getDropdownList();
        
        // Ambil Bank Soal aktif (milik guru atau semua untuk admin)
        $bankFilter = $isGuru ? $userId : null;
        $banksResult = $this->bankModel->getPaginatedBanks(1, 100, null, null, 'active', $bankFilter);
        $questionBanks = $banksResult['data'] ?? [];

        return $this->renderView('guru/exams/create', [
            'pageTitle'     => 'Buat Jadwal Ujian Baru',
            'subjects'      => $subjects,
            'classes'       => $classes,
            'questionBanks' => $questionBanks,
            'errors'        => session_flash('errors') ?? [],
            'old'           => session_flash('old') ?? []
        ], 'layouts/guru');
    }

    /**
     * Simpan Ujian Baru
     */
    public function store(): Response
    {
        $data = $this->request->getPost();
        $userId = (int)user_id();

        $result = $this->examService->createExam($userId, $data);

        if (!$result['success']) {
            flash('danger', 'Gagal membuat jadwal ujian. Harap periksa kembali isian formulir.');
            flash('errors', $result['errors']);
            flash('old', $data);
            return $this->redirect(base_url('guru/exams/create'));
        }

        $examId = $result['exam_id'];
        flash('success', 'Jadwal Ujian baru berhasil dibuat dan token akses telah diterbitkan.');
        return $this->redirect(base_url('guru/exams/show/' . $examId));
    }

    /**
     * Tampilan Detail Ujian
     */
    public function show(int $id): Response
    {
        $exam = $this->examModel->getDetailsWithFullRelations($id);

        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan atau telah diarsipkan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        $isGuru = (user_role() === 'guru');
        if ($isGuru && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk melihat detail ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $layout = $isGuru ? 'layouts/guru' : 'layouts/admin';
        $viewPath = $isGuru ? 'guru/exams/show' : 'admin/exams/show';

        $participants = $this->participantModel->getParticipantsByExamId($id);

        return $this->renderView($viewPath, [
            'pageTitle'    => 'Detail Ujian - ' . e($exam['title']),
            'exam'         => $exam,
            'participants' => $participants,
            'isGuru'       => $isGuru
        ], $layout);
    }

    /**
     * Form Edit Ujian
     */
    public function edit(int $id): Response
    {
        $exam = $this->examModel->getDetailsWithFullRelations($id);

        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        $isGuru = (user_role() === 'guru');
        if ($isGuru && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Anda tidak memiliki hak akses untuk mengedit ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $subjects = $this->subjectModel->getAllActive();
        $classes = $this->classModel->getDropdownList();
        $banksResult = $this->bankModel->getPaginatedBanks(1, 100, null, null, null, $isGuru ? (int)user_id() : null);

        $viewPath = $isGuru ? 'guru/exams/edit' : 'admin/exams/edit';
        $layout = $isGuru ? 'layouts/guru' : 'layouts/admin';

        return $this->renderView($viewPath, [
            'pageTitle'     => 'Edit Ujian - ' . e($exam['title']),
            'exam'          => $exam,
            'subjects'      => $subjects,
            'classes'       => $classes,
            'questionBanks' => $banksResult['data'] ?? [],
            'isGuru'        => $isGuru,
            'errors'        => session_flash('errors') ?? [],
            'old'           => session_flash('old') ?? []
        ], $layout);
    }

    /**
     * Simpan Perubahan Ujian
     */
    public function update(int $id): Response
    {
        $exam = $this->examModel->find($id);
        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        $isGuru = (user_role() === 'guru');
        if ($isGuru && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Anda tidak memiliki hak akses untuk mengedit ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $data = $this->request->getPost();
        $result = $this->examService->updateExam($id, $data);

        $editUrl = base_url($isGuru ? 'guru/exams/edit/' . $id : 'admin/exams/edit/' . $id);
        $showUrl = base_url($isGuru ? 'guru/exams/show/' . $id : 'admin/exams/show/' . $id);

        if (!$result['success']) {
            flash('danger', 'Gagal memperbarui ujian.');
            flash('errors', $result['errors']);
            flash('old', $data);
            return $this->redirect($editUrl);
        }

        flash('success', 'Pengaturan ujian berhasil diperbarui.');
        return $this->redirect($showUrl);
    }

    /**
     * Hapus / Arsipkan Ujian
     */
    public function delete(int $id): Response
    {
        $exam = $this->examModel->find($id);
        $isGuru = (user_role() === 'guru');

        if ($exam) {
            if ($isGuru && (int)$exam['teacher_id'] !== (int)user_id()) {
                flash('danger', 'Anda tidak memiliki hak akses untuk menghapus ujian ini.');
                return $this->redirect(base_url('guru/exams'));
            }
            $this->examService->deleteExam($id);
            flash('success', 'Ujian berhasil diarsipkan / dihapus.');
        } else {
            flash('danger', 'Ujian tidak ditemukan.');
        }

        return $this->redirect(base_url($isGuru ? 'guru/exams' : 'admin/exams'));
    }

    /**
     * Pratinjau Ujian (UC-20a)
     */
    public function preview(int $id): Response
    {
        $exam = $this->examService->getExamDetailsForPreview($id);

        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        $isGuru = (user_role() === 'guru');
        if ($isGuru && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk pratinjau ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $layout = $isGuru ? 'layouts/guru' : 'layouts/admin';
        $backUrl = $isGuru ? base_url('guru/exams/show/' . $id) : base_url('admin/exams/show/' . $id);

        return $this->renderView('guru/exams/preview', [
            'pageTitle' => 'Pratinjau Ujian - ' . e($exam['title']),
            'exam'      => $exam,
            'isGuru'    => $isGuru,
            'backUrl'   => $backUrl
        ], $layout);
    }

    /**
     * Regenerasi Token Ujian 6-Karakter (UC-29)
     */
    public function refreshToken(int $id): Response
    {
        $exam = $this->examModel->find($id);
        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah token ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $token = $this->examService->generateOrRefreshToken($id);
        flash('success', "Token Ujian baru berhasil diterbitkan: <strong>{$token['token']}</strong> (Masa berlaku 24 jam).");
        
        $redirectUrl = base_url((user_role() === 'guru') ? "guru/exams/show/{$id}" : "admin/exams/show/{$id}");
        return $this->redirect($redirectUrl);
    }

    /**
     * Toggle Aktif/Nonaktif Token
     */
    public function toggleToken(int $id): Response
    {
        $token = $this->tokenModel->find($id);
        if ($token) {
            $examId = (int)$token['exam_id'];
        } else {
            $examId = $id;
            $token = $this->tokenModel->getActiveToken($id);
        }

        $exam = $this->examModel->find($examId);
        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah token ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        if ($token) {
            $this->examService->toggleTokenStatus((int)$token['id']);
        }

        flash('success', 'Status keaktifan token berhasil diperbarui.');
        $redirectUrl = base_url((user_role() === 'guru') ? "guru/exams/show/{$examId}" : "admin/exams/show/{$examId}");
        return $this->redirect($redirectUrl);
    }

    /**
     * Ubah Status Ujian (Publish / Mulai / Selesai / Arsip)
     */
    public function updateStatus(int $id): Response
    {
        $exam = $this->examModel->find($id);
        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah status ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $status = (string)$this->request->getPost('status');
        $this->examService->changeStatus($id, $status);

        flash('success', "Status ujian berhasil diubah menjadi: <strong>" . strtoupper($status) . "</strong>.");
        $redirectUrl = base_url((user_role() === 'guru') ? "guru/exams/show/{$id}" : "admin/exams/show/{$id}");
        return $this->redirect($redirectUrl);
    }

    /**
     * Ubah Jadwal Ujian
     */
    public function updateSchedule(int $id): Response
    {
        $exam = $this->examModel->find($id);
        if (!$exam) {
            flash('danger', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url(user_role() === 'guru' ? 'guru/exams' : 'admin/exams'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah jadwal ujian ini.');
            return $this->redirect(base_url('guru/exams'));
        }

        $classIds = (array)$this->request->getPost('class_ids');
        $startTime = (string)$this->request->getPost('start_time');
        $endTime = (string)$this->request->getPost('end_time');
        $grace = (int)($this->request->getPost('grace_period_minutes') ?? 15);

        if (empty($classIds) || empty($startTime) || empty($endTime)) {
            flash('danger', 'Isian jadwal atau kelas peserta tidak lengkap.');
            $redirectUrl = base_url((user_role() === 'guru') ? "guru/exams/show/{$id}" : "admin/exams/show/{$id}");
            return $this->redirect($redirectUrl);
        }

        $result = $this->examService->updateSchedule($id, $classIds, $startTime, $endTime, $grace);

        if ($result['success']) {
            if ($result['changed']) {
                $msg = 'Jadwal pelaksanaan ujian dan kelas peserta berhasil diperbarui.';
                if (($result['notified_count'] ?? 0) > 0) {
                    $msg .= " ({$result['notified_count']} siswa peserta menerima notifikasi).";
                }
                flash('success', $msg);
            } else {
                flash('info', 'Tidak ada perubahan pada jadwal ujian (data sama dengan sebelumnya).');
            }
        } else {
            flash('danger', $result['message'] ?? 'Gagal memperbarui jadwal ujian.');
        }

        $redirectUrl = base_url((user_role() === 'guru') ? "guru/exams/show/{$id}" : "admin/exams/show/{$id}");
        return $this->redirect($redirectUrl);
    }

}
