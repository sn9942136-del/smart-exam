<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Database;
use App\Libraries\Response;
use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;
use App\Models\ClassModel;
use App\Models\RoleModel;
use App\Models\SemesterModel;
use App\Models\SubjectModel;
use App\Models\UserModel;

/**
 * Admin Dashboard Controller
 * Central controller for administrative operations, metrics, and system status overview.
 */
class AdminDashboardController extends BaseController
{
    private UserModel $userModel;
    private RoleModel $roleModel;
    private AcademicYearModel $academicYearModel;
    private SemesterModel $semesterModel;
    private ClassModel $classModel;
    private SubjectModel $subjectModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
        $this->academicYearModel = new AcademicYearModel();
        $this->semesterModel = new SemesterModel();
        $this->classModel = new ClassModel();
        $this->subjectModel = new SubjectModel();
    }

    /**
     * Tampilan Pusat Kontrol Dashboard Admin
     */
    public function index(): Response
    {
        $db = Database::getConnection();

        // Hitung statistik pengguna berdasarkan role
        $totalUsers = $this->userModel->count(['deleted_at' => null]);
        if ($totalUsers <= 0) {
            $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
        }
        $totalTeachers = (int) $db->query("
            SELECT COUNT(DISTINCT u.id) FROM users u 
            JOIN user_roles ur ON ur.user_id = u.id 
            JOIN roles r ON r.id = ur.role_id 
            WHERE r.name = 'guru' AND u.deleted_at IS NULL
        ")->fetchColumn();

        $totalStudents = (int) $db->query("
            SELECT COUNT(DISTINCT u.id) FROM users u 
            JOIN user_roles ur ON ur.user_id = u.id 
            JOIN roles r ON r.id = ur.role_id 
            WHERE r.name = 'peserta_didik' AND u.deleted_at IS NULL
        ")->fetchColumn();

        $totalAdmins = (int) $db->query("
            SELECT COUNT(DISTINCT u.id) FROM users u 
            JOIN user_roles ur ON ur.user_id = u.id 
            JOIN roles r ON r.id = ur.role_id 
            WHERE r.name IN ('super_admin', 'admin') AND u.deleted_at IS NULL
        ")->fetchColumn();

        if ($totalUsers <= 0) {
            $totalUsers = $totalTeachers + $totalStudents + $totalAdmins;
        }

        // Data master akademik
        $activeYear = $this->academicYearModel->getActive();
        $activeSemester = $this->semesterModel->getActive();
        $totalClasses = $this->classModel->count();
        $totalSubjects = $this->subjectModel->count();

        // Statistik Ujian & Soal (Hanya hitung yang aktif / belum dihapus)
        $totalExams = (int) ($db->query("SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL")->fetchColumn() ?: 0);
        $totalQuestionBanks = (int) ($db->query("SELECT COUNT(*) FROM question_banks WHERE deleted_at IS NULL")->fetchColumn() ?: 0);

        // Log Audit Terkini
        $recentAuditLogs = $db->query("
            SELECT al.*, u.name as user_name, u.email as user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.id DESC
            LIMIT 6
        ")->fetchAll() ?: [];

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Pusat Kontrol Utama']
        ];

        $data = [
            'pageTitle'          => 'Dashboard Administrator',
            'user'               => auth_user(),
            'breadcrumbs'        => $breadcrumbs,
            'stats'              => [
                'total_users'        => $totalUsers,
                'total_teachers'     => $totalTeachers,
                'total_students'     => $totalStudents,
                'total_admins'       => $totalAdmins,
                'total_classes'      => $totalClasses,
                'total_subjects'     => $totalSubjects,
                'total_exams'        => $totalExams,
                'total_qbanks'       => $totalQuestionBanks,
                'active_year'        => $activeYear['name'] ?? 'Belum Diatur',
                'active_semester'    => $activeSemester['name'] ?? 'Belum Diatur',
                'active_proctorings' => 0,
                'violations_today'   => 0
            ],
            'recentAuditLogs'    => $recentAuditLogs
        ];

        return $this->view('admin/dashboard', $data, 'layouts/admin');
    }
}
