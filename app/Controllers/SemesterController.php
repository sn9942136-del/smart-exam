<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;
use App\Models\SemesterModel;

/**
 * Semester Controller
 * Manages school semesters within academic years.
 */
class SemesterController extends BaseController
{
    private SemesterModel $semesterModel;
    private AcademicYearModel $yearModel;

    public function __construct()
    {
        parent::__construct();
        $this->semesterModel = new SemesterModel();
        $this->yearModel = new AcademicYearModel();
    }

    /**
     * Daftar Semester
     */
    public function index(): Response
    {
        $search = trim((string)$this->request->get('search', ''));
        $yearFilter = $this->request->get('academic_year_id') ? (int)$this->request->get('academic_year_id') : null;
        $page = max((int)$this->request->get('page', 1), 1);
        $perPage = 10;

        $pagination = $this->semesterModel->getPaginatedSemesters($search, $yearFilter, $page, $perPage);
        $years = $this->yearModel->findAll([], 'start_date DESC');

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Semester']
        ];

        $data = [
            'pageTitle'    => 'Manajemen Semester',
            'breadcrumbs'  => $breadcrumbs,
            'semesters'    => $pagination['data'],
            'totalItems'   => $pagination['totalItems'],
            'currentPage'  => $pagination['currentPage'],
            'totalPages'   => $pagination['totalPages'],
            'perPage'      => $pagination['perPage'],
            'search'       => $search,
            'yearFilter'   => $yearFilter,
            'years'        => $years
        ];

        return $this->view('admin/semesters/index', $data, 'layouts/admin');
    }

    /**
     * Form tambah Semester
     */
    public function create(): Response
    {
        $years = $this->yearModel->findAll([], 'start_date DESC');

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Semester', 'url' => base_url('admin/semesters')],
            ['title' => 'Tambah Semester']
        ];

        $data = [
            'pageTitle'   => 'Tambah Semester Baru',
            'breadcrumbs' => $breadcrumbs,
            'years'       => $years,
            'errors'      => [],
            'old'         => []
        ];

        return $this->view('admin/semesters/create', $data, 'layouts/admin');
    }

    /**
     * Simpan Semester
     */
    public function store(): Response
    {
        $post = $this->request->all();

        $rules = [
            'academic_year_id' => 'required',
            'name'             => 'required',
            'code'             => 'required'
        ];

        $errors = $this->validate($post, $rules);

        if (!empty($errors)) {
            $years = $this->yearModel->findAll([], 'start_date DESC');
            $data = [
                'pageTitle'   => 'Tambah Semester Baru',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Semester', 'url' => base_url('admin/semesters')],
                    ['title' => 'Tambah Semester']
                ],
                'years'       => $years,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon lengkapi formulir data semester.');
            return $this->view('admin/semesters/create', $data, 'layouts/admin');
        }

        $isActive = !empty($post['is_active']) ? 1 : 0;

        $insertData = [
            'academic_year_id' => (int)$post['academic_year_id'],
            'name'             => $post['name'],
            'code'             => trim($post['code']),
            'is_active'        => $isActive,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s')
        ];

        $id = $this->semesterModel->insert($insertData);

        if ($id) {
            if ($isActive) {
                $this->semesterModel->setActive($id);
            }

            AuditLogModel::record('CREATE_SEMESTER', 'academic', (string)$id, null, $insertData);
            $this->setFlash('success', "Semester [{$insertData['name']}] berhasil ditambahkan.");
            return $this->redirect(base_url('admin/semesters'));
        }

        $this->setFlash('danger', 'Gagal menyimpan data semester.');
        return $this->redirect(base_url('admin/semesters/create'));
    }

    /**
     * Form edit Semester
     */
    public function edit(int|string $id): Response
    {
        $semester = $this->semesterModel->find($id);
        if (!$semester) {
            $this->setFlash('danger', 'Data semester tidak ditemukan.');
            return $this->redirect(base_url('admin/semesters'));
        }

        $years = $this->yearModel->findAll([], 'start_date DESC');

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Semester', 'url' => base_url('admin/semesters')],
            ['title' => 'Edit Semester: ' . $semester['name']]
        ];

        $data = [
            'pageTitle'   => 'Edit Semester',
            'breadcrumbs' => $breadcrumbs,
            'semester'    => $semester,
            'years'       => $years,
            'errors'      => [],
            'old'         => $semester
        ];

        return $this->view('admin/semesters/edit', $data, 'layouts/admin');
    }

    /**
     * Update Semester
     */
    public function update(int|string $id): Response
    {
        $semester = $this->semesterModel->find($id);
        if (!$semester) {
            $this->setFlash('danger', 'Data semester tidak ditemukan.');
            return $this->redirect(base_url('admin/semesters'));
        }

        $post = $this->request->all();

        $rules = [
            'academic_year_id' => 'required',
            'name'             => 'required',
            'code'             => 'required'
        ];

        $errors = $this->validate($post, $rules);

        if (!empty($errors)) {
            $years = $this->yearModel->findAll([], 'start_date DESC');
            $data = [
                'pageTitle'   => 'Edit Semester',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Semester', 'url' => base_url('admin/semesters')],
                    ['title' => 'Edit Semester: ' . $semester['name']]
                ],
                'semester'    => $semester,
                'years'       => $years,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon lengkapi formulir semester.');
            return $this->view('admin/semesters/edit', $data, 'layouts/admin');
        }

        $isActive = !empty($post['is_active']) ? 1 : 0;

        $updateData = [
            'academic_year_id' => (int)$post['academic_year_id'],
            'name'             => $post['name'],
            'code'             => trim($post['code']),
            'is_active'        => $isActive,
            'updated_at'       => date('Y-m-d H:i:s')
        ];

        $this->semesterModel->update($id, $updateData);

        if ($isActive) {
            $this->semesterModel->setActive($id);
        }

        AuditLogModel::record('UPDATE_SEMESTER', 'academic', (string)$id, $semester, $updateData);

        $this->setFlash('success', "Semester [{$updateData['name']}] berhasil diperbarui.");
        return $this->redirect(base_url('admin/semesters'));
    }

    /**
     * Hapus Semester
     */
    public function delete(int|string $id): Response
    {
        $semester = $this->semesterModel->find($id);
        if (!$semester) {
            $this->setFlash('danger', 'Data semester tidak ditemukan.');
            return $this->redirect(base_url('admin/semesters'));
        }

        $this->semesterModel->delete($id);
        AuditLogModel::record('DELETE_SEMESTER', 'academic', (string)$id, $semester);

        $this->setFlash('success', "Semester [{$semester['name']}] berhasil dihapus.");
        return $this->redirect(base_url('admin/semesters'));
    }

    /**
     * Set Semester Aktif
     */
    public function setActive(int|string $id): Response
    {
        $semester = $this->semesterModel->find($id);
        if (!$semester) {
            $this->setFlash('danger', 'Data semester tidak ditemukan.');
            return $this->redirect(base_url('admin/semesters'));
        }

        $this->semesterModel->setActive($id);
        AuditLogModel::record('SET_ACTIVE_SEMESTER', 'academic', (string)$id, null, ['name' => $semester['name']]);

        $this->setFlash('success', "Semester [{$semester['name']}] telah diatur sebagai Semester Aktif.");
        return $this->redirect(base_url('admin/semesters'));
    }
}
