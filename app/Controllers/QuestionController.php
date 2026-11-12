<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\QuestionBankModel;
use App\Models\QuestionImportModel;
use App\Models\QuestionModel;
use App\Services\QuestionImportService;
use App\Services\QuestionService;

/**
 * Question Controller
 * Mengelola pembuatan, pengeditan, penghapusan, pratinjau, dan impor butir soal.
 */
class QuestionController extends BaseController
{
    protected QuestionModel $questionModel;
    protected QuestionBankModel $bankModel;
    protected QuestionService $questionService;
    protected QuestionImportService $importService;
    protected QuestionImportModel $importModel;

    public function __construct()
    {
        parent::__construct();
        $this->questionModel = new QuestionModel();
        $this->bankModel = new QuestionBankModel();
        $this->questionService = new QuestionService();
        $this->importService = new QuestionImportService();
        $this->importModel = new QuestionImportModel();
    }

    /**
     * Form Tambah Butir Soal Baru ke Bank Soal
     */
    public function create(int $bankId): Response
    {
        $bank = $this->bankModel->getByIdWithDetails($bankId);

        if (!$bank) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk menambah butir soal pada Bank Soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $nextOrder = $this->questionModel->getNextOrderNumber($bankId);

        return $this->renderView('guru/questions/create', [
            'pageTitle' => 'Tambah Butir Soal - ' . e($bank['title']),
            'bank' => $bank,
            'nextOrder' => $nextOrder,
            'errors' => session_flash('errors') ?? [],
            'old' => session_flash('old') ?? []
        ], 'layouts/guru');
    }

    /**
     * Simpan Butir Soal Baru
     */
    public function store(int $bankId): Response
    {
        $bank = $this->bankModel->find($bankId);
        if (!$bank || !empty($bank['deleted_at'])) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk menambah butir soal pada Bank Soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $data = $this->request->getPost();
        $file = $_FILES['question_image'] ?? null;

        $result = $this->questionService->createQuestion($bankId, $data, $file);

        if (!$result['success']) {
            flash('danger', 'Gagal menyimpan butir soal. Harap periksa isian form.');
            flash('errors', $result['errors']);
            flash('old', $data);
            return $this->redirect("guru/question-banks/{$bankId}/questions/create");
        }

        flash('success', 'Butir soal baru berhasil ditambahkan.');
        
        // Cek aksi tombol submit: simpan & tambah lagi atau kembali
        $action = $this->request->getPost('submit_action');
        if ($action === 'save_and_add') {
            return $this->redirect("guru/question-banks/{$bankId}/questions/create");
        } else {
            return $this->redirect("guru/question-banks/show/{$bankId}");
        }
    }

    /**
     * Form Edit Butir Soal
     */
    public function edit(int $id): Response
    {
        $question = $this->questionModel->getWithFullDetails($id);

        if (!$question) {
            flash('danger', 'Butir soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        $bank = $this->bankModel->getByIdWithDetails((int)$question['question_bank_id']);

        if (user_role() === 'guru' && $bank && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengedit butir soal ini.');
            return $this->redirect('guru/question-banks');
        }

        return $this->renderView('guru/questions/edit', [
            'pageTitle' => 'Edit Butir Soal #' . $question['order_number'],
            'bank' => $bank,
            'question' => $question,
            'errors' => session_flash('errors') ?? [],
            'old' => session_flash('old') ?? []
        ], 'layouts/guru');
    }

    /**
     * Simpan Perubahan Butir Soal
     */
    public function update(int $id): Response
    {
        $question = $this->questionModel->find($id);
        if (!$question || !empty($question['deleted_at'])) {
            flash('danger', 'Butir soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        $bankId = (int)$question['question_bank_id'];
        $bank = $this->bankModel->find($bankId);

        if (user_role() === 'guru' && $bank && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengedit butir soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $data = $this->request->getPost();
        $file = $_FILES['question_image'] ?? null;

        $result = $this->questionService->updateQuestion($id, $data, $file);

        if (!$result['success']) {
            flash('danger', 'Gagal memperbarui butir soal.');
            flash('errors', $result['errors']);
            flash('old', $data);
            return $this->redirect("guru/questions/edit/{$id}");
        }

        flash('success', "Butir soal #{$question['order_number']} berhasil diperbarui.");
        return $this->redirect("guru/question-banks/show/{$bankId}");
    }

    /**
     * Hapus Butir Soal
     */
    public function delete(int $id): Response
    {
        $question = $this->questionModel->find($id);
        if (!$question || !empty($question['deleted_at'])) {
            flash('danger', 'Butir soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        $bankId = (int)$question['question_bank_id'];
        $bank = $this->bankModel->find($bankId);

        if (user_role() === 'guru' && $bank && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus butir soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $this->questionService->deleteQuestion($id);

        flash('success', 'Butir soal berhasil dihapus.');
        return $this->redirect("guru/question-banks/show/{$bankId}");
    }

    /**
     * Pratinjau Soal (Modal & Direct Preview)
     */
    public function preview(int $id): Response
    {
        $question = $this->questionModel->getWithFullDetails($id);

        if (!$question) {
            flash('danger', 'Butir soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        $bank = $this->bankModel->getByIdWithDetails((int)$question['question_bank_id']);

        if (user_role() === 'guru' && $bank && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk pratinjau butir soal ini.');
            return $this->redirect('guru/question-banks');
        }

        return $this->renderView('guru/questions/preview', [
            'pageTitle' => 'Pratinjau Soal #' . $question['order_number'],
            'bank' => $bank,
            'question' => $question
        ], 'layouts/guru');
    }

    /**
     * Halaman Unggah Impor Soal
     */
    public function showImport(int $bankId): Response
    {
        $bank = $this->bankModel->getByIdWithDetails($bankId);

        if (!$bank) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengimpor soal ke Bank Soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $recentImports = $this->importModel->getRecentByBankId($bankId, 5);

        return $this->renderView('guru/questions/import', [
            'pageTitle' => 'Impor Butir Soal - ' . e($bank['title']),
            'bank' => $bank,
            'recentImports' => $recentImports,
            'errors' => session_flash('errors') ?? []
        ], 'layouts/guru');
    }

    /**
     * Proses File Impor Soal
     */
    public function processImport(int $bankId): Response
    {
        $bank = $this->bankModel->find($bankId);
        if (!$bank || !empty($bank['deleted_at'])) {
            flash('danger', 'Bank Soal tidak ditemukan.');
            return $this->redirect('guru/question-banks');
        }

        if (user_role() === 'guru' && (int)$bank['creator_id'] !== (int)user_id()) {
            flash('danger', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengimpor soal ke Bank Soal ini.');
            return $this->redirect('guru/question-banks');
        }

        $file = $_FILES['import_file'] ?? null;
        if (!$file || empty($file['tmp_name'])) {
            flash('danger', 'Pilih berkas Excel (.xlsx) atau CSV yang ingin diimpor.');
            return $this->redirect("guru/question-banks/{$bankId}/import");
        }

        $result = $this->importService->importFromFile($bankId, (int)user_id(), $file);

        if ($result['success']) {
            flash('success', $result['message']);
            if (!empty($result['errors'])) {
                flash('warning', 'Beberapa baris data diabaikan karena kesalahan format:<br>' . implode('<br>', array_slice($result['errors'], 0, 5)));
            }
            return $this->redirect("guru/question-banks/show/{$bankId}");
        } else {
            flash('danger', $result['message'] . '<br>' . implode('<br>', array_slice($result['errors'], 0, 5)));
            return $this->redirect("guru/question-banks/{$bankId}/import");
        }
    }
}
