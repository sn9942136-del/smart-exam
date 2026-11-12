<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;

/**
 * Academic Year Controller
 * Manages school academic years and active calendar periods.
 */
class AcademicYearController extends BaseController
{
    private AcademicYearModel $yearModel;

    public function __construct()
    {
        parent::__construct();
        $this->yearModel = new AcademicYearModel();
    }

    /**
     * Daftar Tahun Ajaran
     */
    public function index(): Response
    {
        $search = trim((string)$this->request->get('search', ''));
        $page = max((int)$this->request->get('page', 1), 1);
        $perPage = 10;

        $pagination = $this->yearModel->getPaginatedYears($search, $page, $perPage);

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Tahun Ajaran']
        ];

        $data = [
            'pageTitle'   => 'Manajemen Tahun Ajaran',
            'breadcrumbs' => $breadcrumbs,
            'years'       => $pagination['data'],
            'totalItems'  => $pagination['totalItems'],
            'currentPage' => $pagination['currentPage'],
            'totalPages'  => $pagination['totalPages'],
            'perPage'     => $pagination['perPage'],
            'search'      => $search
        ];

        return $this->view('admin/academic_years/index', $data, 'layouts/admin');
    }

    /**
     * Form tambah Tahun Ajaran
     */
    public function create(): Response
    {
        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Tahun Ajaran', 'url' => base_url('admin/academic-years')],
            ['title' => 'Tambah Tahun Ajaran']
        ];

        $data = [
            'pageTitle'   => 'Tambah Tahun Ajaran Baru',
            'breadcrumbs' => $breadcrumbs,
            'errors'      => [],
            'old'         => []
        ];

        return $this->view('admin/academic_years/create', $data, 'layouts/admin');
    }

    /**
     * Proses simpan Tahun Ajaran
     */
    public function store(): Response
    {
        $post = $this->request->all();

        $rules = [
            'name'       => 'required|min:4|max:50',
            'start_date' => 'required',
            'end_date'   => 'required'
        ];

        $errors = $this->validate($post, $rules);

        // Cek keunikan nama tahun ajaran
        $existing = $this->yearModel->where('name', trim($post['name']))->first();
        if ($existing) {
            $errors['name'][] = "Tahun ajaran [{$post['name']}] sudah terdaftar.";
        }

        if (!empty($errors)) {
            $data = [
                'pageTitle'   => 'Tambah Tahun Ajaran Baru',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Tahun Ajaran', 'url' => base_url('admin/academic-years')],
                    ['title' => 'Tambah Tahun Ajaran']
                ],
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon periksa isian data tahun ajaran.');
            return $this->view('admin/academic_years/create', $data, 'layouts/admin');
        }

        $isActive = !empty($post['is_active']) ? 1 : 0;

        $insertData = [
            'name'       => trim($post['name']),
            'start_date' => $post['start_date'],
            'end_date'   => $post['end_date'],
            'is_active'  => $isActive,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $id = $this->yearModel->insert($insertData);

        if ($id) {
            if ($isActive) {
                $this->yearModel->setActive($id);
            }

            AuditLogModel::record('CREATE_ACADEMIC_YEAR', 'academic', (string)$id, null, $insertData);
            $this->setFlash('success', "Tahun ajaran [{$insertData['name']}] berhasil ditambahkan.");
            return $this->redirect(base_url('admin/academic-years'));
        }

        $this->setFlash('danger', 'Gagal menyimpan tahun ajaran.');
        return $this->redirect(base_url('admin/academic-years/create'));
    }

    /**
     * Form edit Tahun Ajaran
     */
    public function edit(int|string $id): Response
    {
        $year = $this->yearModel->find($id);
        if (!$year) {
            $this->setFlash('danger', 'Tahun ajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/academic-years'));
        }

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Tahun Ajaran', 'url' => base_url('admin/academic-years')],
            ['title' => 'Edit: ' . $year['name']]
        ];

        $data = [
            'pageTitle'   => 'Edit Tahun Ajaran',
            'breadcrumbs' => $breadcrumbs,
            'year'        => $year,
            'errors'      => [],
            'old'         => $year
        ];

        return $this->view('admin/academic_years/edit', $data, 'layouts/admin');
    }

    /**
     * Proses update Tahun Ajaran
     */
    public function update(int|string $id): Response
    {
        $year = $this->yearModel->find($id);
        if (!$year) {
            $this->setFlash('danger', 'Tahun ajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/academic-years'));
        }

        $post = $this->request->all();

        $rules = [
            'name'       => 'required|min:4|max:50',
            'start_date' => 'required',
            'end_date'   => 'required'
        ];

        $errors = $this->validate($post, $rules);

        if (!empty($errors)) {
            $data = [
                'pageTitle'   => 'Edit Tahun Ajaran',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Tahun Ajaran', 'url' => base_url('admin/academic-years')],
                    ['title' => 'Edit: ' . $year['name']]
                ],
                'year'        => $year,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon periksa data tahun ajaran.');
            return $this->view('admin/academic_years/edit', $data, 'layouts/admin');
        }

        $isActive = !empty($post['is_active']) ? 1 : 0;

        $updateData = [
            'name'       => trim($post['name']),
            'start_date' => $post['start_date'],
            'end_date'   => $post['end_date'],
            'is_active'  => $isActive,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->yearModel->update($id, $updateData);

        if ($isActive) {
            $this->yearModel->setActive($id);
        }

        AuditLogModel::record('UPDATE_ACADEMIC_YEAR', 'academic', (string)$id, $year, $updateData);

        $this->setFlash('success', "Tahun ajaran [{$updateData['name']}] berhasil diperbarui.");
        return $this->redirect(base_url('admin/academic-years'));
    }

    /**
     * Hapus Tahun Ajaran
     */
    public function delete(int|string $id): Response
    {
        $year = $this->yearModel->find($id);
        if (!$year) {
            $this->setFlash('danger', 'Tahun ajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/academic-years'));
        }

        $this->yearModel->delete($id);
        AuditLogModel::record('DELETE_ACADEMIC_YEAR', 'academic', (string)$id, $year);

        $this->setFlash('success', "Tahun ajaran [{$year['name']}] berhasil dihapus.");
        return $this->redirect(base_url('admin/academic-years'));
    }

    /**
     * Set tahun ajaran aktif
     */
    public function setActive(int|string $id): Response
    {
        $year = $this->yearModel->find($id);
        if (!$year) {
            $this->setFlash('danger', 'Tahun ajaran tidak ditemukan.');
            return $this->redirect(base_url('admin/academic-years'));
        }

        $this->yearModel->setActive($id);
        AuditLogModel::record('SET_ACTIVE_ACADEMIC_YEAR', 'academic', (string)$id, null, ['name' => $year['name']]);

        $this->setFlash('success', "Tahun ajaran [{$year['name']}] telah diatur sebagai Tahun Ajaran Aktif.");
        return $this->redirect(base_url('admin/academic-years'));
    }
}
