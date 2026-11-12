<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AuditLogModel;
use App\Models\SubjectModel;

/**
 * Subject Controller
 * Manages school academic subjects (Mata Pelajaran).
 */
class SubjectController extends BaseController
{
    private SubjectModel $subjectModel;

    public function __construct()
    {
        parent::__construct();
        $this->subjectModel = new SubjectModel();
    }

    /**
     * Daftar Mata Pelajaran
     */
    public function index(): Response
    {
        $search = trim((string)$this->request->get('search', ''));
        $page = max((int)$this->request->get('page', 1), 1);
        $perPage = 10;

        $pagination = $this->subjectModel->getPaginatedSubjects($search, $page, $perPage);

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Mata Pelajaran']
        ];

        $data = [
            'pageTitle'   => 'Manajemen Mata Pelajaran',
            'breadcrumbs' => $breadcrumbs,
            'subjects'    => $pagination['data'],
            'totalItems'  => $pagination['totalItems'],
            'currentPage' => $pagination['currentPage'],
            'totalPages'  => $pagination['totalPages'],
            'perPage'     => $pagination['perPage'],
            'search'      => $search
        ];

        return $this->view('admin/subjects/index', $data, 'layouts/admin');
    }

    /**
     * Form tambah Mata Pelajaran baru
     */
    public function create(): Response
    {
        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Mata Pelajaran', 'url' => base_url('admin/subjects')],
            ['title' => 'Tambah Mata Pelajaran']
        ];

        $data = [
            'pageTitle'   => 'Tambah Mata Pelajaran Baru',
            'breadcrumbs' => $breadcrumbs,
            'errors'      => [],
            'old'         => []
        ];

        return $this->view('admin/subjects/create', $data, 'layouts/admin');
    }

    /**
     * Proses simpan Mata Pelajaran
     */
    public function store(): Response
    {
        $post = $this->request->all();

        $rules = [
            'code' => 'required|min:2|max:50',
            'name' => 'required|min:3|max:150'
        ];

        $errors = $this->validate($post, $rules);

        // Cek keunikan kode mapel
        $codeClean = strtoupper(trim($post['code']));
        if (!empty($codeClean) && $this->subjectModel->findByCode($codeClean)) {
            $errors['code'][] = "Kode mata pelajaran [{$codeClean}] sudah terdaftar.";
        }

        if (!empty($errors)) {
            $data = [
                'pageTitle'   => 'Tambah Mata Pelajaran Baru',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Mata Pelajaran', 'url' => base_url('admin/subjects')],
                    ['title' => 'Tambah Mata Pelajaran']
                ],
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon periksa isian data mata pelajaran.');
            return $this->view('admin/subjects/create', $data, 'layouts/admin');
        }

        $insertData = [
            'code'        => strip_tags($codeClean),
            'name'        => strip_tags(trim($post['name'])),
            'description' => !empty($post['description']) ? strip_tags(trim($post['description'])) : null,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ];

        $id = $this->subjectModel->insert($insertData);

        if ($id) {
            AuditLogModel::record('CREATE_SUBJECT', 'academic', (string)$id, null, $insertData);
            $this->setFlash('success', "Mata pelajaran [{$insertData['name']}] berhasil ditambahkan.");
            return $this->redirect(base_url('admin/subjects'));
        }

        $this->setFlash('danger', 'Gagal menyimpan data mata pelajaran.');
        return $this->redirect(base_url('admin/subjects/create'));
    }

    /**
     * Form edit Mata Pelajaran
     */
    public function edit(int|string $id): Response
    {
        $subject = $this->subjectModel->find($id);
        if (!$subject) {
            $this->setFlash('danger', 'Data mata pelajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/subjects'));
        }

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Mata Pelajaran', 'url' => base_url('admin/subjects')],
            ['title' => 'Edit: ' . $subject['name']]
        ];

        $data = [
            'pageTitle'   => 'Edit Mata Pelajaran',
            'breadcrumbs' => $breadcrumbs,
            'subject'     => $subject,
            'errors'      => [],
            'old'         => $subject
        ];

        return $this->view('admin/subjects/edit', $data, 'layouts/admin');
    }

    /**
     * Update Mata Pelajaran
     */
    public function update(int|string $id): Response
    {
        $subject = $this->subjectModel->find($id);
        if (!$subject) {
            $this->setFlash('danger', 'Data mata pelajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/subjects'));
        }

        $post = $this->request->all();

        $rules = [
            'code' => 'required|min:2|max:50',
            'name' => 'required|min:3|max:150'
        ];

        $errors = $this->validate($post, $rules);

        $codeClean = strtoupper(trim($post['code']));
        if (!empty($codeClean) && $this->subjectModel->findByCode($codeClean, (int)$id)) {
            $errors['code'][] = "Kode mata pelajaran [{$codeClean}] sudah digunakan.";
        }

        if (!empty($errors)) {
            $data = [
                'pageTitle'   => 'Edit Mata Pelajaran',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Mata Pelajaran', 'url' => base_url('admin/subjects')],
                    ['title' => 'Edit: ' . $subject['name']]
                ],
                'subject'     => $subject,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon periksa data mata pelajaran.');
            return $this->view('admin/subjects/edit', $data, 'layouts/admin');
        }

        $updateData = [
            'code'        => strip_tags($codeClean),
            'name'        => strip_tags(trim($post['name'])),
            'description' => !empty($post['description']) ? strip_tags(trim($post['description'])) : null,
            'updated_at'  => date('Y-m-d H:i:s')
        ];

        $this->subjectModel->update($id, $updateData);
        AuditLogModel::record('UPDATE_SUBJECT', 'academic', (string)$id, $subject, $updateData);

        $this->setFlash('success', "Mata pelajaran [{$updateData['name']}] berhasil diperbarui.");
        return $this->redirect(base_url('admin/subjects'));
    }

    /**
     * Hapus Mata Pelajaran
     */
    public function delete(int|string $id): Response
    {
        $subject = $this->subjectModel->find($id);
        if (!$subject) {
            $this->setFlash('danger', 'Data mata pelajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/subjects'));
        }

        $this->subjectModel->delete($id);
        AuditLogModel::record('DELETE_SUBJECT', 'academic', (string)$id, $subject);

        $this->setFlash('success', "Mata pelajaran [{$subject['name']}] berhasil dihapus.");
        return $this->redirect(base_url('admin/subjects'));
    }
}
