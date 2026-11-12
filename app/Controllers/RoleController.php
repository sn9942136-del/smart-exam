<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AuditLogModel;
use App\Models\RoleModel;

/**
 * Role Controller
 * Handles user role definitions and permission mapping.
 */
class RoleController extends BaseController
{
    private RoleModel $roleModel;

    public function __construct()
    {
        parent::__construct();
        $this->roleModel = new RoleModel();
    }

    /**
     * Daftar seluruh peran & permission
     */
    public function index(): Response
    {
        $roles = $this->roleModel->getRolesWithCounts();

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Role & Hak Akses']
        ];

        $data = [
            'pageTitle'   => 'Manajemen Role & Hak Akses',
            'breadcrumbs' => $breadcrumbs,
            'roles'       => $roles
        ];

        return $this->view('admin/roles/index', $data, 'layouts/admin');
    }

    /**
     * Form tambah role baru
     */
    public function create(): Response
    {
        $permissionsGrouped = $this->roleModel->getAllPermissionsGrouped();

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Role & Hak Akses', 'url' => base_url('admin/roles')],
            ['title' => 'Tambah Role Baru']
        ];

        $data = [
            'pageTitle'          => 'Tambah Role Baru',
            'breadcrumbs'        => $breadcrumbs,
            'permissionsGrouped' => $permissionsGrouped,
            'currentPermIds'     => [],
            'errors'             => [],
            'old'                => []
        ];

        return $this->view('admin/roles/create', $data, 'layouts/admin');
    }

    /**
     * Proses simpan role baru
     */
    public function store(): Response
    {
        $post = $this->request->all();

        $rules = [
            'name'         => 'required|min:3|max:50',
            'display_name' => 'required|min:3|max:100'
        ];

        $errors = $this->validate($post, $rules);

        // Cek keunikan slug name
        $nameSlug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($post['name'])));
        $existing = $this->roleModel->where('name', $nameSlug)->first();
        if ($existing) {
            $errors['name'][] = "Nama identitas role [{$nameSlug}] sudah digunakan.";
        }

        if (!empty($errors)) {
            $data = [
                'pageTitle'          => 'Tambah Role Baru',
                'breadcrumbs'        => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Role & Hak Akses', 'url' => base_url('admin/roles')],
                    ['title' => 'Tambah Role Baru']
                ],
                'permissionsGrouped' => $this->roleModel->getAllPermissionsGrouped(),
                'currentPermIds'     => $post['permissions'] ?? [],
                'errors'             => $errors,
                'old'                => $post
            ];
            $this->setFlash('danger', 'Mohon periksa isian data role Anda.');
            return $this->view('admin/roles/create', $data, 'layouts/admin');
        }

        $roleData = [
            'name'         => $nameSlug,
            'display_name' => trim($post['display_name']),
            'description'  => !empty($post['description']) ? trim($post['description']) : null,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s')
        ];

        $roleId = $this->roleModel->insert($roleData);

        if ($roleId) {
            $permIds = $post['permissions'] ?? [];
            $this->roleModel->syncPermissions($roleId, (array)$permIds);

            AuditLogModel::record('CREATE_ROLE', 'roles', (string)$roleId, null, $roleData);

            $this->setFlash('success', "Role [{$roleData['display_name']}] berhasil dibuat.");
            return $this->redirect(base_url('admin/roles'));
        }

        $this->setFlash('danger', 'Gagal menyimpan data role ke database.');
        return $this->redirect(base_url('admin/roles/create'));
    }

    /**
     * Form edit role
     */
    public function edit(int|string $id): Response
    {
        $role = $this->roleModel->find($id);
        if (!$role) {
            $this->setFlash('danger', 'Data role tidak ditemukan.');
            return $this->redirect(base_url('admin/roles'));
        }

        $permissionsGrouped = $this->roleModel->getAllPermissionsGrouped();
        $currentPermIds = $this->roleModel->getPermissionIds($id);

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Role & Hak Akses', 'url' => base_url('admin/roles')],
            ['title' => 'Edit Role: ' . $role['display_name']]
        ];

        $data = [
            'pageTitle'          => 'Edit Role & Permissions',
            'breadcrumbs'        => $breadcrumbs,
            'role'               => $role,
            'permissionsGrouped' => $permissionsGrouped,
            'currentPermIds'     => $currentPermIds,
            'errors'             => [],
            'old'                => $role
        ];

        return $this->view('admin/roles/edit', $data, 'layouts/admin');
    }

    /**
     * Proses update role & permissions
     */
    public function update(int|string $id): Response
    {
        $role = $this->roleModel->find($id);
        if (!$role) {
            $this->setFlash('danger', 'Data role tidak ditemukan.');
            return $this->redirect(base_url('admin/roles'));
        }

        $post = $this->request->all();

        $rules = [
            'display_name' => 'required|min:3|max:100'
        ];

        $errors = $this->validate($post, $rules);

        if (!empty($errors)) {
            $data = [
                'pageTitle'          => 'Edit Role & Permissions',
                'breadcrumbs'        => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Role & Hak Akses', 'url' => base_url('admin/roles')],
                    ['title' => 'Edit Role: ' . $role['display_name']]
                ],
                'role'               => $role,
                'permissionsGrouped' => $this->roleModel->getAllPermissionsGrouped(),
                'currentPermIds'     => $post['permissions'] ?? [],
                'errors'             => $errors,
                'old'                => $post
            ];
            $this->setFlash('danger', 'Mohon periksa isian data role.');
            return $this->view('admin/roles/edit', $data, 'layouts/admin');
        }

        $updateData = [
            'display_name' => trim($post['display_name']),
            'description'  => !empty($post['description']) ? trim($post['description']) : null,
            'updated_at'   => date('Y-m-d H:i:s')
        ];

        $this->roleModel->update($id, $updateData);

        // Sync permissions
        $permIds = $post['permissions'] ?? [];
        $this->roleModel->syncPermissions($id, (array)$permIds);

        AuditLogModel::record('UPDATE_ROLE', 'roles', (string)$id, $role, $updateData);

        $this->setFlash('success', "Role [{$updateData['display_name']}] berhasil diperbarui.");
        return $this->redirect(base_url('admin/roles'));
    }

    /**
     * Hapus custom role
     */
    public function delete(int|string $id): Response
    {
        $role = $this->roleModel->find($id);
        if (!$role) {
            $this->setFlash('danger', 'Data role tidak ditemukan.');
            return $this->redirect(base_url('admin/roles'));
        }

        // Proteksi role bawaan sistem
        $systemRoles = ['super_admin', 'admin', 'guru', 'peserta_didik'];
        if (in_array($role['name'], $systemRoles, true)) {
            $this->setFlash('danger', "Role sistem bawaan [{$role['display_name']}] tidak dapat dihapus.");
            return $this->redirect(base_url('admin/roles'));
        }

        $this->roleModel->delete($id);
        AuditLogModel::record('DELETE_ROLE', 'roles', (string)$id, $role);

        $this->setFlash('success', "Role [{$role['display_name']}] berhasil dihapus.");
        return $this->redirect(base_url('admin/roles'));
    }
}
