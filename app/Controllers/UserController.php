<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AuditLogModel;
use App\Models\ClassModel;
use App\Models\RoleModel;
use App\Models\UserModel;

/**
 * User Controller
 * Manages user CRUD, role assignments, manual password reset, and account activation/deactivation.
 */
class UserController extends BaseController
{
    private UserModel $userModel;
    private RoleModel $roleModel;
    private ClassModel $classModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
        $this->classModel = new ClassModel();
    }

    /**
     * Menampilkan daftar pengguna dengan filter pencarian dan paginasi
     */
    public function index(): Response
    {
        $search = trim((string)$this->request->get('search', ''));
        $roleFilter = trim((string)$this->request->get('role', ''));
        $statusFilter = trim((string)$this->request->get('status', ''));
        $page = max((int)$this->request->get('page', 1), 1);
        $perPage = 10;
        $sort = strtolower(trim((string)$this->request->get('sort', 'name')));
        $order = strtoupper(trim((string)$this->request->get('order', 'ASC')));

        if (!in_array($sort, ['name', 'email', 'username', 'nip_nisn', 'status', 'id', 'created_at'], true)) {
            $sort = 'name';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $pagination = $this->userModel->getPaginatedUsers($search, $roleFilter, $statusFilter, $page, $perPage, $sort, $order);
        $roles = $this->roleModel->findAll([], 'id ASC');

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Pengguna']
        ];

        $data = [
            'pageTitle'    => 'Manajemen Pengguna',
            'breadcrumbs'  => $breadcrumbs,
            'users'        => $pagination['data'],
            'totalItems'   => $pagination['totalItems'],
            'currentPage'  => $pagination['currentPage'],
            'totalPages'   => $pagination['totalPages'],
            'perPage'      => $pagination['perPage'],
            'search'       => $search,
            'roleFilter'   => $roleFilter,
            'statusFilter' => $statusFilter,
            'roles'        => $roles,
            'sort'         => $sort,
            'order'        => $order
        ];

        return $this->view('admin/users/index', $data, 'layouts/admin');
    }

    /**
     * Form tambah pengguna baru
     */
    public function create(): Response
    {
        $roles = $this->roleModel->findAll([], 'id ASC');
        $classes = $this->classModel->getDropdownList();

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Pengguna', 'url' => base_url('admin/users')],
            ['title' => 'Tambah Pengguna Baru']
        ];

        $data = [
            'pageTitle'   => 'Tambah Pengguna Baru',
            'breadcrumbs' => $breadcrumbs,
            'roles'       => $roles,
            'classes'     => $classes,
            'errors'      => [],
            'old'         => []
        ];

        return $this->view('admin/users/create', $data, 'layouts/admin');
    }

    /**
     * Proses simpan pengguna baru
     */
    public function store(): Response
    {
        $post = $this->request->all();

        $rules = [
            'name'     => 'required|min:3|max:150',
            'email'    => 'required|email|max:150',
            'username' => 'required|min:3|max:50',
            'password' => 'required|min:6',
            'role_id'  => 'required'
        ];

        $errors = $this->validate($post, $rules);

        // Cek keunikan email
        if (!empty($post['email']) && $this->userModel->findByEmail($post['email'])) {
            $errors['email'][] = 'Alamat email sudah terdaftar oleh pengguna lain.';
        }

        // Cek keunikan username
        if (!empty($post['username']) && $this->userModel->findByUsername($post['username'])) {
            $errors['username'][] = 'Username sudah digunakan, silakan pilih username lain.';
        }

        // Cek keunikan NIP/NISN jika diisi
        if (!empty($post['nip_nisn']) && $this->userModel->findByNipNisn($post['nip_nisn'])) {
            $errors['nip_nisn'][] = 'NIP / NISN sudah terdaftar pada pengguna lain.';
        }

        if (!empty($errors)) {
            $roles = $this->roleModel->findAll([], 'id ASC');
            $classes = $this->classModel->getDropdownList();
            $data = [
                'pageTitle'   => 'Tambah Pengguna Baru',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Manajemen Pengguna', 'url' => base_url('admin/users')],
                    ['title' => 'Tambah Pengguna Baru']
                ],
                'roles'       => $roles,
                'classes'     => $classes,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Terdapat kesalahan pengisian formulir. Mohon periksa kembali.');
            return $this->view('admin/users/create', $data, 'layouts/admin');
        }

        $userData = [
            'name'        => strip_tags(trim($post['name'])),
            'email'       => trim($post['email']),
            'username'    => strip_tags(trim($post['username'])),
            'password'    => password_hash($post['password'], PASSWORD_BCRYPT),
            'nip_nisn'    => !empty($post['nip_nisn']) ? strip_tags(trim($post['nip_nisn'])) : null,
            'phone'       => !empty($post['phone']) ? strip_tags(trim($post['phone'])) : null,
            'gender'      => in_array($post['gender'] ?? '', ['L', 'P'], true) ? $post['gender'] : null,
            'status'      => in_array($post['status'] ?? '', ['active', 'inactive', 'banned'], true) ? $post['status'] : 'active',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ];

        $userId = $this->userModel->insert($userData);

        if ($userId) {
            // Assign role
            $roleId = (int)$post['role_id'];
            $this->userModel->assignRole($userId, $roleId);

            // Sync class if specified
            if (!empty($post['class_id'])) {
                $this->classModel->syncStudentClass($userId, (int)$post['class_id']);
            }

            // Audit log
            AuditLogModel::record('CREATE_USER', 'users', (string)$userId, null, [
                'name'     => $userData['name'],
                'email'    => $userData['email'],
                'username' => $userData['username'],
                'role_id'  => $roleId,
                'class_id' => !empty($post['class_id']) ? (int)$post['class_id'] : null
            ]);

            $this->setFlash('success', "Pengguna [{$userData['name']}] berhasil ditambahkan ke dalam sistem.");
            return $this->redirect(base_url('admin/users'));
        }

        $this->setFlash('danger', 'Gagal menyimpan data pengguna ke database.');
        return $this->redirect(base_url('admin/users/create'));
    }

    /**
     * Form edit pengguna
     */
    public function edit(int|string $id): Response
    {
        $user = $this->userModel->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            $this->setFlash('danger', 'Data pengguna tidak ditemukan.');
            return $this->redirect(base_url('admin/users'));
        }

        $roles = $this->roleModel->findAll([], 'id ASC');
        $userRoles = $this->userModel->getRoles($user['id']);
        $currentRoleId = !empty($userRoles) ? $userRoles[0]['id'] : null;

        $classes = $this->classModel->getDropdownList();
        $currentClassId = $this->classModel->getStudentActiveClassId($user['id']);

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Pengguna', 'url' => base_url('admin/users')],
            ['title' => 'Edit Pengguna: ' . $user['name']]
        ];

        $data = [
            'pageTitle'      => 'Edit Pengguna',
            'breadcrumbs'    => $breadcrumbs,
            'user'           => $user,
            'roles'          => $roles,
            'classes'        => $classes,
            'currentRoleId'  => $currentRoleId,
            'currentClassId' => $currentClassId,
            'errors'         => [],
            'old'            => $user
        ];

        return $this->view('admin/users/edit', $data, 'layouts/admin');
    }

    /**
     * Proses update data pengguna
     */
    public function update(int|string $id): Response
    {
        $user = $this->userModel->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            $this->setFlash('danger', 'Data pengguna tidak ditemukan.');
            return $this->redirect(base_url('admin/users'));
        }

        $post = $this->request->all();

        $rules = [
            'name'     => 'required|min:3|max:150',
            'email'    => 'required|email|max:150',
            'username' => 'required|min:3|max:50',
            'role_id'  => 'required'
        ];

        $errors = $this->validate($post, $rules);

        // Cek keunikan email selain ID sendiri
        if (!empty($post['email']) && $this->userModel->findByEmail($post['email'], (int)$id)) {
            $errors['email'][] = 'Alamat email sudah terdaftar pada pengguna lain.';
        }

        // Cek keunikan username selain ID sendiri
        if (!empty($post['username']) && $this->userModel->findByUsername($post['username'], (int)$id)) {
            $errors['username'][] = 'Username sudah digunakan oleh pengguna lain.';
        }

        // Cek keunikan NIP/NISN selain ID sendiri
        if (!empty($post['nip_nisn']) && $this->userModel->findByNipNisn($post['nip_nisn'], (int)$id)) {
            $errors['nip_nisn'][] = 'NIP / NISN sudah terdaftar pada pengguna lain.';
        }

        if (!empty($errors)) {
            $roles = $this->roleModel->findAll([], 'id ASC');
            $classes = $this->classModel->getDropdownList();
            $data = [
                'pageTitle'      => 'Edit Pengguna',
                'breadcrumbs'    => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Manajemen Pengguna', 'url' => base_url('admin/users')],
                    ['title' => 'Edit Pengguna: ' . $user['name']]
                ],
                'user'           => $user,
                'roles'          => $roles,
                'classes'        => $classes,
                'currentRoleId'  => (int)($post['role_id'] ?? 0),
                'currentClassId' => !empty($post['class_id']) ? (int)$post['class_id'] : $this->classModel->getStudentActiveClassId($user['id']),
                'errors'         => $errors,
                'old'            => $post
            ];
            $this->setFlash('danger', 'Mohon periksa kembali kolom formulir yang belum sesuai.');
            return $this->view('admin/users/edit', $data, 'layouts/admin');
        }

        $updateData = [
            'name'       => strip_tags(trim($post['name'])),
            'email'      => trim($post['email']),
            'username'   => strip_tags(trim($post['username'])),
            'nip_nisn'   => !empty($post['nip_nisn']) ? strip_tags(trim($post['nip_nisn'])) : null,
            'phone'      => !empty($post['phone']) ? strip_tags(trim($post['phone'])) : null,
            'gender'     => in_array($post['gender'] ?? '', ['L', 'P'], true) ? $post['gender'] : null,
            'status'     => in_array($post['status'] ?? '', ['active', 'inactive', 'banned'], true) ? $post['status'] : $user['status'],
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Ganti password jika diisi
        if (!empty($post['password'])) {
            if (strlen($post['password']) < 6) {
                $this->setFlash('danger', 'Password baru minimal 6 karakter.');
                return $this->redirect(base_url('admin/users/edit/' . $id));
            }
            $updateData['password'] = password_hash($post['password'], PASSWORD_BCRYPT);
        }

        $this->userModel->update($id, $updateData);

        // Sync role
        $roleId = (int)$post['role_id'];
        $this->userModel->syncRole($id, $roleId);

        // Sync class if role is student or if class_id is modified
        $role = $this->roleModel->find($roleId);
        if ($role && $role['name'] === 'peserta_didik') {
            $targetClassId = !empty($post['class_id']) ? (int)$post['class_id'] : null;
            $this->classModel->syncStudentClass($id, $targetClassId);
        } else {
            // Jika role diubah bukan siswa, lepaskan dari kelas
            $this->classModel->syncStudentClass($id, null);
        }

        // Audit log
        AuditLogModel::record('UPDATE_USER', 'users', (string)$id, $user, $updateData);

        $this->setFlash('success', "Data pengguna [{$updateData['name']}] berhasil diperbarui.");
        return $this->redirect(base_url('admin/users'));
    }

    /**
     * Hapus akun pengguna (Soft Delete)
     */
    public function delete(int|string $id): Response
    {
        // Proteksi: Tidak boleh menghapus diri sendiri
        if ((int)$id === (int)user_id()) {
            $this->setFlash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
            return $this->redirect(base_url('admin/users'));
        }

        $user = $this->userModel->find($id);
        if (!$user) {
            $this->setFlash('danger', 'Data pengguna tidak ditemukan.');
            return $this->redirect(base_url('admin/users'));
        }

        $this->userModel->softDelete($id);
        AuditLogModel::record('DELETE_USER', 'users', (string)$id, $user);

        $this->setFlash('success', "Pengguna [{$user['name']}] berhasil dinonaktifkan/dihapus.");
        return $this->redirect(base_url('admin/users'));
    }

    /**
     * Form / Halaman Reset Password Manual oleh Admin
     */
    public function showResetPassword(int|string $id): Response
    {
        $user = $this->userModel->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            $this->setFlash('danger', 'Pengguna tidak ditemukan.');
            return $this->redirect(base_url('admin/users'));
        }

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Pengguna', 'url' => base_url('admin/users')],
            ['title' => 'Reset Kata Sandi: ' . $user['name']]
        ];

        $data = [
            'pageTitle'   => 'Reset Kata Sandi Pengguna',
            'breadcrumbs' => $breadcrumbs,
            'user'        => $user,
            'errors'      => []
        ];

        return $this->view('admin/users/reset_password', $data, 'layouts/admin');
    }

    /**
     * Proses Reset Password Manual oleh Admin
     */
    public function resetPassword(int|string $id): Response
    {
        $user = $this->userModel->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            $this->setFlash('danger', 'Pengguna tidak ditemukan.');
            return $this->redirect(base_url('admin/users'));
        }

        $post = $this->request->all();
        $newPassword = $post['new_password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';

        if (empty($newPassword) || strlen($newPassword) < 6) {
            $this->setFlash('danger', 'Kata sandi baru minimal 6 karakter.');
            return $this->redirect(base_url('admin/users/reset-password/' . $id));
        }

        if ($newPassword !== $confirmPassword) {
            $this->setFlash('danger', 'Konfirmasi kata sandi tidak cocok.');
            return $this->redirect(base_url('admin/users/reset-password/' . $id));
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->userModel->updatePassword($id, $hash);

        AuditLogModel::record('ADMIN_RESET_PASSWORD', 'users', (string)$id, null, [
            'target_user' => $user['email']
        ]);

        $this->setFlash('success', "Kata sandi untuk pengguna [{$user['name']}] berhasil direset.");
        return $this->redirect(base_url('admin/users'));
    }

    /**
     * Toggle status aktif / nonaktif pengguna
     */
    public function toggleStatus(int|string $id): Response
    {
        if ((int)$id === (int)user_id()) {
            $this->setFlash('danger', 'Anda tidak dapat mengubah status akun Anda sendiri.');
            return $this->redirect(base_url('admin/users'));
        }

        $user = $this->userModel->find($id);
        if (!$user) {
            $this->setFlash('danger', 'Pengguna tidak ditemukan.');
            return $this->redirect(base_url('admin/users'));
        }

        $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
        $this->userModel->update($id, ['status' => $newStatus]);

        AuditLogModel::record('TOGGLE_USER_STATUS', 'users', (string)$id, [
            'old_status' => $user['status'],
            'new_status' => $newStatus
        ]);

        $statusLabel = $newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan';
        $this->setFlash('success', "Akun [{$user['name']}] berhasil {$statusLabel}.");
        return $this->redirect(base_url('admin/users'));
    }
}
