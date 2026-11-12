<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;
use App\Models\ClassModel;
use App\Models\UserModel;

/**
 * Class Controller
 * Manages school class groups (Rombongan Belajar), grades, majors, and homeroom teachers.
 */
class ClassController extends BaseController
{
    private ClassModel $classModel;
    private AcademicYearModel $yearModel;
    private UserModel $userModel;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->classModel = new ClassModel();
        $this->yearModel = new AcademicYearModel();
        $this->userModel = new UserModel();
    }

    /**
     * Daftar Kelas
     */
    public function index(): Response
    {
        $search = trim((string)$this->request->get('search', ''));
        $yearFilter = $this->request->get('academic_year_id') ? (int)$this->request->get('academic_year_id') : null;
        $gradeFilter = trim((string)$this->request->get('grade_level', ''));
        $page = max((int)$this->request->get('page', 1), 1);
        $perPage = 10;

        $pagination = $this->classModel->getPaginatedClasses($search, $yearFilter, $gradeFilter, $page, $perPage);
        $years = $this->yearModel->findAll([], 'start_date DESC');

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Kelas']
        ];

        $data = [
            'pageTitle'    => 'Manajemen Kelas & Rombel',
            'breadcrumbs'  => $breadcrumbs,
            'classes'      => $pagination['data'],
            'totalItems'   => $pagination['totalItems'],
            'currentPage'  => $pagination['currentPage'],
            'totalPages'   => $pagination['totalPages'],
            'perPage'      => $pagination['perPage'],
            'search'       => $search,
            'yearFilter'   => $yearFilter,
            'gradeFilter'  => $gradeFilter,
            'years'        => $years
        ];

        return $this->view('admin/classes/index', $data, 'layouts/admin');
    }

    /**
     * Form tambah Kelas baru
     */
    public function create(): Response
    {
        $years = $this->yearModel->findAll([], 'start_date DESC');
        $teachers = $this->userModel->getActiveTeachers();

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Kelas', 'url' => base_url('admin/classes')],
            ['title' => 'Tambah Kelas Baru']
        ];

        $data = [
            'pageTitle'   => 'Tambah Kelas Baru',
            'breadcrumbs' => $breadcrumbs,
            'years'       => $years,
            'teachers'    => $teachers,
            'errors'      => [],
            'old'         => []
        ];

        return $this->view('admin/classes/create', $data, 'layouts/admin');
    }

    /**
     * Proses simpan Kelas baru
     */
    public function store(): Response
    {
        $post = $this->request->all();

        $rules = [
            'academic_year_id' => 'required',
            'name'             => 'required|min:2|max:50',
            'grade_level'      => 'required'
        ];

        $errors = $this->validate($post, $rules);

        if (!empty($errors)) {
            $years = $this->yearModel->findAll([], 'start_date DESC');
            $teachers = $this->userModel->getActiveTeachers();
            $data = [
                'pageTitle'   => 'Tambah Kelas Baru',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Manajemen Kelas', 'url' => base_url('admin/classes')],
                    ['title' => 'Tambah Kelas Baru']
                ],
                'years'       => $years,
                'teachers'    => $teachers,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon lengkapi formulir kelas.');
            return $this->view('admin/classes/create', $data, 'layouts/admin');
        }

        $insertData = [
            'academic_year_id'    => (int)$post['academic_year_id'],
            'name'                => strip_tags(trim($post['name'])),
            'grade_level'         => strip_tags(trim($post['grade_level'])),
            'major'               => !empty($post['major']) ? strip_tags(trim($post['major'])) : null,
            'homeroom_teacher_id' => !empty($post['homeroom_teacher_id']) ? (int)$post['homeroom_teacher_id'] : null,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s')
        ];

        $id = $this->classModel->insert($insertData);

        if ($id) {
            AuditLogModel::record('CREATE_CLASS', 'academic', (string)$id, null, $insertData);
            $this->setFlash('success', "Kelas [{$insertData['name']}] berhasil dibuat.");
            return $this->redirect(base_url('admin/classes'));
        }

        $this->setFlash('danger', 'Gagal menyimpan data kelas.');
        return $this->redirect(base_url('admin/classes/create'));
    }

    /**
     * Form edit Kelas
     */
    public function edit(int|string $id): Response
    {
        $class = $this->classModel->find($id);
        if (!$class) {
            $this->setFlash('danger', 'Data kelas tidak ditemukan.');
            return $this->redirect(base_url('admin/classes'));
        }

        $years = $this->yearModel->findAll([], 'start_date DESC');
        $teachers = $this->userModel->getActiveTeachers();

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Kelas', 'url' => base_url('admin/classes')],
            ['title' => 'Edit Kelas: ' . $class['name']]
        ];

        $data = [
            'pageTitle'   => 'Edit Kelas',
            'breadcrumbs' => $breadcrumbs,
            'class'       => $class,
            'years'       => $years,
            'teachers'    => $teachers,
            'errors'      => [],
            'old'         => $class
        ];

        return $this->view('admin/classes/edit', $data, 'layouts/admin');
    }

    /**
     * Proses update Kelas
     */
    public function update(int|string $id): Response
    {
        $class = $this->classModel->find($id);
        if (!$class) {
            $this->setFlash('danger', 'Data kelas tidak ditemukan.');
            return $this->redirect(base_url('admin/classes'));
        }

        $post = $this->request->all();

        $rules = [
            'academic_year_id' => 'required',
            'name'             => 'required|min:2|max:50',
            'grade_level'      => 'required'
        ];

        $errors = $this->validate($post, $rules);

        if (!empty($errors)) {
            $years = $this->yearModel->findAll([], 'start_date DESC');
            $teachers = $this->userModel->getActiveTeachers();
            $data = [
                'pageTitle'   => 'Edit Kelas',
                'breadcrumbs' => [
                    ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
                    ['title' => 'Manajemen Kelas', 'url' => base_url('admin/classes')],
                    ['title' => 'Edit Kelas: ' . $class['name']]
                ],
                'class'       => $class,
                'years'       => $years,
                'teachers'    => $teachers,
                'errors'      => $errors,
                'old'         => $post
            ];
            $this->setFlash('danger', 'Mohon periksa isian data kelas.');
            return $this->view('admin/classes/edit', $data, 'layouts/admin');
        }

        $updateData = [
            'academic_year_id'    => (int)$post['academic_year_id'],
            'name'                => strip_tags(trim($post['name'])),
            'grade_level'         => strip_tags(trim($post['grade_level'])),
            'major'               => !empty($post['major']) ? strip_tags(trim($post['major'])) : null,
            'homeroom_teacher_id' => !empty($post['homeroom_teacher_id']) ? (int)$post['homeroom_teacher_id'] : null,
            'updated_at'          => date('Y-m-d H:i:s')
        ];

        $this->classModel->update($id, $updateData);
        AuditLogModel::record('UPDATE_CLASS', 'academic', (string)$id, $class, $updateData);

        $this->setFlash('success', "Data kelas [{$updateData['name']}] berhasil diperbarui.");
        return $this->redirect(base_url('admin/classes'));
    }

    /**
     * Hapus Kelas
     */
    public function delete(int|string $id): Response
    {
        $class = $this->classModel->find($id);
        if (!$class) {
            $this->setFlash('danger', 'Data kelas tidak ditemukan.');
            return $this->redirect(base_url('admin/classes'));
        }

        $this->classModel->delete($id);
        AuditLogModel::record('DELETE_CLASS', 'academic', (string)$id, $class);

        $this->setFlash('success', "Kelas [{$class['name']}] berhasil dihapus.");
        return $this->redirect(base_url('admin/classes'));
    }

    /**
     * Tampilkan halaman Kelola Siswa per Kelas
     */
    public function students(int|string $id): Response
    {
        $class = $this->classModel->getClassWithDetails($id);
        if (!$class) {
            $this->setFlash('danger', 'Data kelas tidak ditemukan.');
            return $this->redirect(base_url('admin/classes'));
        }

        $enrolledStudents = $this->classModel->getStudentsInClass($id);
        $availableStudents = $this->classModel->getAvailableStudentsForClass($id);

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Manajemen Kelas', 'url' => base_url('admin/classes')],
            ['title' => 'Siswa Kelas: ' . $class['name']]
        ];

        $data = [
            'pageTitle'         => 'Kelola Siswa - ' . $class['name'],
            'breadcrumbs'       => $breadcrumbs,
            'class'             => $class,
            'enrolledStudents'  => $enrolledStudents,
            'availableStudents' => $availableStudents,
        ];

        return $this->view('admin/classes/students', $data, 'layouts/admin');
    }

    /**
     * Tambah / Daftarkan Siswa ke Kelas
     */
    public function addStudent(int|string $id): Response
    {
        $class = $this->classModel->find($id);
        if (!$class) {
            $this->setFlash('danger', 'Data kelas tidak ditemukan.');
            return $this->redirect(base_url('admin/classes'));
        }

        $studentId = (int)($this->request->input('student_id') ?? $this->request->post('student_id'));
        $rollNumber = trim((string)($this->request->input('roll_number') ?? $this->request->post('roll_number') ?? ''));

        if ($studentId <= 0) {
            $this->setFlash('danger', 'Silakan pilih siswa yang akan dimasukkan ke kelas.');
            return $this->redirect(base_url('admin/classes/' . $id . '/students'));
        }

        $student = $this->userModel->find($studentId);
        if (!$student || !empty($student['deleted_at'])) {
            $this->setFlash('danger', 'Data siswa tidak valid atau tidak ditemukan.');
            return $this->redirect(base_url('admin/classes/' . $id . '/students'));
        }

        $success = $this->classModel->enrollStudent($id, $studentId, $rollNumber !== '' ? $rollNumber : null);

        if ($success) {
            AuditLogModel::record('ENROLL_STUDENT_CLASS', 'academic', (string)$id, null, [
                'class_id'     => $id,
                'class_name'   => $class['name'],
                'student_id'   => $studentId,
                'student_name' => $student['name'],
                'roll_number'  => $rollNumber
            ]);
            $this->setFlash('success', "Siswa [{$student['name']}] berhasil dimasukkan ke kelas [{$class['name']}].");
        } else {
            $this->setFlash('danger', 'Gagal memasukkan siswa ke kelas.');
        }

        return $this->redirect(base_url('admin/classes/' . $id . '/students'));
    }

    /**
     * Keluarkan Siswa dari Kelas
     */
    public function removeStudent(int|string $id, int|string $studentId): Response
    {
        $class = $this->classModel->find($id);
        if (!$class) {
            $this->setFlash('danger', 'Data kelas tidak ditemukan.');
            return $this->redirect(base_url('admin/classes'));
        }

        $student = $this->userModel->find($studentId);
        $studentName = $student ? $student['name'] : "ID #{$studentId}";

        $this->classModel->removeStudentFromClass($id, $studentId);

        AuditLogModel::record('REMOVE_STUDENT_CLASS', 'academic', (string)$id, null, [
            'class_id'     => $id,
            'class_name'   => $class['name'],
            'student_id'   => $studentId,
            'student_name' => $studentName
        ]);

        $this->setFlash('success', "Siswa [{$studentName}] berhasil dikeluarkan dari kelas [{$class['name']}].");
        return $this->redirect(base_url('admin/classes/' . $id . '/students'));
    }
}
