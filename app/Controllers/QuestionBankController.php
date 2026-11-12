<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\SubjectModel;
use App\Services\QuestionImportService;

/**
 * Question Bank Controller
 * Menangani pengelolaan Bank Soal (CRUD), rincian butir soal, dan unduhan template impor.
 */
class QuestionBankController extends BaseController
{
    protected QuestionBankModel $bankModel;
    protected QuestionModel $questionModel;
    protected SubjectModel $subjectModel;
    protected QuestionImportService $importService;

    public function __construct()
    {
        parent::__construct();
        $this->bankModel = new QuestionBankModel();
        $this->questionModel = new QuestionModel();
        $this->subjectModel = new SubjectModel();
        $this->importService = new QuestionImportService();
    }

    /**
     * Tampilkan daftar Bank Soal
     */
    public function index(): Response
    {
        $page = (int)($this->request->getGet('page') ?? 1);
        $search = $this->request->getGet('search');
        $subjectId = $this->request->getGet('subject_id');
        $status = $this->request->getGet('status');

        $userId = (int)user_id();
        $userRole = user_role();

        // Guru hanya melihat bank soal miliknya, admin bisa melihat semua atau difilter
        $creatorFilter = ($userRole === 'guru') ? $userId : null;

        $pagination = $this->bankModel->getPaginatedBanks(
            max(1, $page),
            10,
            $search ? (string)$search : null,
            $subjectId ? (string)$subjectId : null,
            $status ? (string)$status : null,
            $creatorFilter
        );

        $subjects = $this->subjectModel->getAllActive();
        $summary = $this->bankModel->getSummaryStats($creatorFilter);

        return $this->renderView('guru/question_banks/index', [
            'pageTitle' => 'Manajemen Bank Soal',
            'banks' => $pagination['data'],
            'pagination' => $pagination,
            'subjects' => $subjects,
            'summary' => $summary,
            'filters' => [
                'search' => $search ?? '',
                'subject_id' => $subjectId ?? '',
                'status' => $status ?? ''
            ]
        ], 'layouts/guru');
    }

    /**
     * Form Tambah Bank Soal Baru
     */
    public function create(): Response
    {
        $subjects = $this->subjectModel->getAllActive();

        return $this->renderView('guru/question_banks/create', [
            'pageTitle' => 'Buat Bank Soal Baru',
            'subjects' => $subjects,
            'errors' => session_flash('errors') ?? [],
            'old' => session_flash('old') ?? []
        ], 'layouts/guru');
    }

    /**
     * Simpan Bank Soal Baru
     */
    public function store(): Response
    {
        $title = trim((string)$this->request->getPost('title'));
        $subjectId = (int)$this->request->getPost('subject_id');
        $description = trim((string)$this->request->getPost('description'));
        $status = (string)$this->request->getPost('status') ?: 'draft';
        $customCode = trim((string)$this->request->getPost('code'));

        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Judul Bank Soal wajib diisi.';
        }

        if ($subjectId <= 0) {
            $errors['subject_id'] = 'Pilih mata pelajaran yang valid.';
        } else {
            $subject = $this->subjectModel->find($subjectId);
            if (!$subject) {
                $errors['subject_id'] = 'Mata pelajaran yang dipilih tidak terdaftar.';
            }
        }

        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = 'draft';
        }

        if (!empty($errors)) {
            flash('danger', 'Terdapat kesalahan pada isian form.');
            flash('errors', $errors);
            flash('old', [
                'title' => $title,
                'subject_id' => $subjectId,
                'description' => $description,
                'status' => $status,
                'code' => $customCode
            ]);
            return $this->redirect('guru/question-banks/create');
        }

        // Tentukan kode unik bank soal
        $subject = $this->subjectModel->find($subjectId);
        $subjectCode = $subject['code'] ?? 'MAPEL';
        $code = !empty($customCode) ? strtoupper($customCode) : $this->bankModel->generateBankCode($subjectCode);

        // Cek duplikasi kode
        $existing = $this->bankModel->where('code', $code)->first();
        if ($existing !== null) {
            $code = $this->bankModel->generateBankCode($subjectCode);
        }

        $cleanTitle = strip_tags($title);
        $cleanDesc = $description !== '' ? sanitize_html($description) : null;

        $bankId = $this->bankModel->insert([
            'subject_id' => $subjectId,
            'creator_id' => (int)user_id(),
            'code' => $code,
            'title' => $cleanTitle,
            'description' => $cleanDesc,
            'total_questions' => 0,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        flash('success', "Bank Soal '{$cleanTitle}' ({$code}) berhasil dibuat.");
        return $this->redirect('guru/question-banks/show/' . $bankId);
    }

    /**
     * Tampilan Detail Bank Soal & Daftar Butir Soal
     */
    public function show(int $id): Response
    {
        $bank = $this->bankModel->getByIdWithDetails($id);

        if (!$bank) {
            flash('danger', 'Bank Soal tidak ditemukan atau telah dihapus.');
            return $this->redirect('guru/question-banks');
        }

        // Guru hanya boleh melihat bank soal miliknya sendiri
        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk melihat Bank Soal ini.');
            return $this->redirect('guru/question-banks');
        }

        // Filter soal
        $type = $this->request->getGet('type');
        $difficulty = $this->request->getGet('difficulty');
        $search = $this->request->getGet('search');

        $questions = $this->questionModel->getQuestionsByBankId(
            $id,
            $type ? (string)$type : null,
            $difficulty ? (string)$difficulty : null,
            $search ? (string)$search : null
        );

        return $this->renderView('guru/question_banks/show', [
            'pageTitle' => 'Detail Bank Soal - ' . e($bank['title']),
            'bank' => $bank,
            'questions' => $questions,
            'filters' => [
                'type' => $type ?? '',
                'difficulty' => $difficulty ?? '',
                'search' => $search ?? ''
            ]
        ], 'layouts/guru');
    }

    /**
     * Form Edit Bank Soal
     */
    public function edit(int $id): Response
    {
        $bank = $this->bankModel->getByIdWithDetails($id);

        if (!$bank) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        // Cek izin (Guru hanya bisa edit miliknya, admin bisa edit semua)
        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Anda tidak memiliki hak akses untuk mengedit bank soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $subjects = $this->subjectModel->getAllActive();

        return $this->renderView('guru/question_banks/edit', [
            'pageTitle' => 'Edit Bank Soal - ' . e($bank['title']),
            'bank' => $bank,
            'subjects' => $subjects,
            'errors' => session_flash('errors') ?? [],
            'old' => session_flash('old') ?? []
        ], 'layouts/guru');
    }

    /**
     * Simpan Perubahan Bank Soal
     */
    public function update(int $id): Response
    {
        $bank = $this->bankModel->find($id);

        if (!$bank || !empty($bank['deleted_at'])) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Anda tidak memiliki hak akses untuk mengedit bank soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $title = trim((string)$this->request->getPost('title'));
        $subjectId = (int)$this->request->getPost('subject_id');
        $description = trim((string)$this->request->getPost('description'));
        $status = (string)$this->request->getPost('status') ?: 'draft';

        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Judul Bank Soal wajib diisi.';
        }

        if ($subjectId <= 0 || !$this->subjectModel->find($subjectId)) {
            $errors['subject_id'] = 'Mata pelajaran tidak valid.';
        }

        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = 'draft';
        }

        if (!empty($errors)) {
            flash('danger', 'Terdapat kesalahan pada form.');
            flash('errors', $errors);
            flash('old', [
                'title' => $title,
                'subject_id' => $subjectId,
                'description' => $description,
                'status' => $status
            ]);
            return $this->redirect('guru/question-banks/edit/' . $id);
        }

        $cleanTitle = strip_tags($title);
        $cleanDesc = $description !== '' ? sanitize_html($description) : null;

        $this->bankModel->update($id, [
            'subject_id' => $subjectId,
            'title' => $cleanTitle,
            'description' => $cleanDesc,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        flash('success', "Bank Soal '{$cleanTitle}' berhasil diperbarui.");
        return $this->redirect('guru/question-banks/show/' . $id);
    }

    /**
     * Hapus Bank Soal (Soft Delete)
     */
    public function delete(int $id): Response
    {
        $bank = $this->bankModel->find($id);

        if (!$bank || !empty($bank['deleted_at'])) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Anda tidak memiliki hak akses untuk menghapus bank soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $this->bankModel->update($id, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'archived'
        ]);

        flash('success', "Bank Soal '{$bank['title']}' berhasil dihapus.");
        return $this->redirect('guru/question-banks');
    }

    /**
     * Unduh Berkas Template Soal CSV Siap Pakai
     */
    public function downloadTemplate(): void
    {
        $csvContent = $this->importService->generateSampleCsv();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_import_soal_' . date('Ymd') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csvContent;
        exit;
    }
}
