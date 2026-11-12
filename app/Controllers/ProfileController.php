<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\AuditLogModel;
use App\Models\UserModel;
use PDO;
use Throwable;

/**
 * Profile Controller
 * Mengelola fitur Profil Saya, Edit Profil, dan Upload Avatar Pengguna
 * Mendukung role: Admin/Super Admin, Guru, dan Peserta Didik.
 */
class ProfileController extends BaseController
{
    protected UserModel $userModel;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->userModel = new UserModel();
    }

    /**
     * Halaman Profil Saya (Lihat Informasi Akun Lengkap)
     */
    public function index(): Response
    {
        $userId = user_id();
        if (!$userId) {
            return $this->redirect(base_url('login'));
        }

        // Ambil data terbaru langsung dari database
        $user = $this->userModel->find($userId);
        if (!$user) {
            Session::setFlash('danger', 'Pengguna tidak ditemukan dalam sistem.');
            return $this->redirect(base_url('login'));
        }

        $userRole = (string)(user_role() ?? 'peserta_didik');
        $layout = $this->determineLayout($userRole);
        $roleDetails = $this->getRoleSpecificDetails((int)$userId, $userRole);

        $breadcrumbs = [
            ['title' => role_label(), 'url' => $this->getRoleDashboardUrl($userRole)],
            ['title' => 'Profil Saya']
        ];

        return $this->view('profile/index', [
            'pageTitle'    => 'Profil Saya',
            'user'         => $user,
            'roleLabel'    => role_label(),
            'roleBadge'    => role_badge_class(),
            'userRole'     => $userRole,
            'roleDetails'  => $roleDetails,
            'breadcrumbs'  => $breadcrumbs,
        ], $layout);
    }

    /**
     * Halaman Edit Profil (Formulir Pengubahan Data yang Diizinkan)
     */
    public function edit(): Response
    {
        $userId = user_id();
        if (!$userId) {
            return $this->redirect(base_url('login'));
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            Session::setFlash('danger', 'Pengguna tidak ditemukan.');
            return $this->redirect(base_url('login'));
        }

        $userRole = (string)(user_role() ?? 'peserta_didik');
        $layout = $this->determineLayout($userRole);
        $roleDetails = $this->getRoleSpecificDetails((int)$userId, $userRole);

        $breadcrumbs = [
            ['title' => role_label(), 'url' => $this->getRoleDashboardUrl($userRole)],
            ['title' => 'Profil Saya', 'url' => base_url('profile')],
            ['title' => 'Edit Profil']
        ];

        return $this->view('profile/edit', [
            'pageTitle'    => 'Edit Profil',
            'user'         => $user,
            'roleLabel'    => role_label(),
            'roleBadge'    => role_badge_class(),
            'userRole'     => $userRole,
            'roleDetails'  => $roleDetails,
            'breadcrumbs'  => $breadcrumbs,
        ], $layout);
    }

    /**
     * Simpan Perubahan Profil Pengguna
     */
    public function update(): Response
    {
        $userId = user_id();
        if (!$userId) {
            return $this->redirect(base_url('login'));
        }

        $currentUser = $this->userModel->find($userId);
        if (!$currentUser) {
            Session::setFlash('danger', 'Pengguna tidak ditemukan.');
            return $this->redirect(base_url('login'));
        }

        $name = trim((string)$this->request->input('name', ''));
        $username = trim((string)$this->request->input('username', ''));
        $gender = trim((string)$this->request->input('gender', ''));

        $errors = [];

        // Validasi Nama Lengkap
        if (empty($name)) {
            $errors['name'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            $errors['name'] = 'Nama lengkap harus antara 3 hingga 150 karakter.';
        }

        // Validasi Username
        if (empty($username)) {
            $errors['username'] = 'Username wajib diisi.';
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $errors['username'] = 'Username harus antara 3 hingga 50 karakter.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.\s-]+$/', $username)) {
            $errors['username'] = 'Username hanya boleh memuat huruf, angka, titik, strip, dan garis bawah.';
        } else {
            // Cek duplikasi username pada pengguna lain (tabel users memiliki unique index pada kolom username)
            $db = Database::getConnection();
            $stmtCheck = $db->prepare("SELECT id FROM users WHERE username = :un AND id != :uid LIMIT 1");
            $stmtCheck->execute([':un' => $username, ':uid' => $userId]);
            if ($stmtCheck->fetch()) {
                $errors['username'] = 'Username tersebut sudah digunakan oleh akun lain.';
            }
        }

        // Validasi Jenis Kelamin
        if (!empty($gender) && !in_array($gender, ['L', 'P'], true)) {
            $errors['gender'] = 'Pilihan jenis kelamin tidak valid.';
        }

        // Handle upload avatar jika ada file yang dikirim bersamaan
        $newAvatarPath = null;
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $this->processAvatarUpload($_FILES['avatar'], (int)$userId, $currentUser['avatar'] ?? null);
            if (!$uploadResult['success']) {
                $errors['avatar'] = $uploadResult['error'];
            } else {
                $newAvatarPath = $uploadResult['file_path'];
            }
        }

        if (!empty($errors)) {
            Session::setFlash('danger', implode(' ', $errors));
            return $this->redirect(base_url('profile/edit'));
        }

        // Siapkan data pembaruan (hanya kolom yang diizinkan untuk diubah oleh pemilik profil)
        $updateData = [
            'name'       => $name,
            'username'   => $username,
            'gender'     => !empty($gender) ? $gender : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($newAvatarPath !== null) {
            $updateData['avatar'] = $newAvatarPath;
        }

        try {
            $this->userModel->update($userId, $updateData);

            // Sinkronkan sesi login saat ini agar langsung terrefleksi ke navbar & dropdown
            $currentSessionUser = Session::get('user') ?? [];
            $currentSessionUser['name'] = $name;
            $currentSessionUser['username'] = $username;
            if ($newAvatarPath !== null) {
                $currentSessionUser['avatar'] = $newAvatarPath;
            }
            Session::set('user', $currentSessionUser);

            AuditLogModel::record('UPDATE_PROFILE', 'user', (string)$userId, null, [
                'name_changed'     => $name !== ($currentUser['name'] ?? ''),
                'username_changed' => $username !== ($currentUser['username'] ?? ''),
                'avatar_updated'   => $newAvatarPath !== null
            ]);

            Session::setFlash('success', 'Profil Anda berhasil diperbarui.');
            return $this->redirect(base_url('profile'));
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate entry')) {
                Session::setFlash('danger', 'Username tersebut sudah digunakan oleh akun lain.');
            } else {
                Session::setFlash('danger', 'Gagal memperbarui profil: ' . $msg);
            }
            return $this->redirect(base_url('profile/edit'));
        }
    }

    /**
     * Upload Avatar secara mandiri (Mendukung AJAX / Standalone Submit)
     */
    public function uploadAvatar(): Response
    {
        $userId = user_id();
        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'Sesi berakhir, silakan login kembali.'], 401);
        }

        $currentUser = $this->userModel->find($userId);
        if (!$currentUser) {
            return $this->json(['success' => false, 'error' => 'Pengguna tidak ditemukan.'], 404);
        }

        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            Session::setFlash('danger', 'Silakan pilih berkas gambar foto profil.');
            return $this->redirect(base_url('profile/edit'));
        }

        $uploadResult = $this->processAvatarUpload($_FILES['avatar'], (int)$userId, $currentUser['avatar'] ?? null);
        if (!$uploadResult['success']) {
            if ($this->isAjaxRequest()) {
                return $this->json(['success' => false, 'error' => $uploadResult['error']], 400);
            }
            Session::setFlash('danger', $uploadResult['error']);
            return $this->redirect(base_url('profile/edit'));
        }

        $newPath = $uploadResult['file_path'];

        $this->userModel->update($userId, [
            'avatar'     => $newPath,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Sinkronkan session
        $sessUser = Session::get('user') ?? [];
        $sessUser['avatar'] = $newPath;
        Session::set('user', $sessUser);

        AuditLogModel::record('UPLOAD_AVATAR', 'user', (string)$userId, null, [
            'avatar_path' => $newPath
        ]);

        if ($this->isAjaxRequest()) {
            return $this->json([
                'success'    => true,
                'message'    => 'Foto profil berhasil diperbarui.',
                'avatar_url' => user_avatar($newPath)
            ]);
        }

        Session::setFlash('success', 'Foto profil berhasil diperbarui.');
        return $this->redirect(base_url('profile'));
    }

    /**
     * Hapus Foto Profil (Mengembalikan ke Avatar Default Inisial)
     */
    public function deleteAvatar(): Response
    {
        $userId = user_id();
        if (!$userId) {
            return $this->redirect(base_url('login'));
        }

        $currentUser = $this->userModel->find($userId);
        if ($currentUser && !empty($currentUser['avatar'])) {
            $oldAvatar = $currentUser['avatar'];
            // Hapus file fisik hanya jika berada di dalam folder uploads/avatars/
            if (str_starts_with($oldAvatar, 'uploads/avatars/avatar_')) {
                $filePath = FCPATH . ltrim($oldAvatar, '/');
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            $this->userModel->update($userId, [
                'avatar'     => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $sessUser = Session::get('user') ?? [];
            $sessUser['avatar'] = null;
            Session::set('user', $sessUser);

            AuditLogModel::record('DELETE_AVATAR', 'user', (string)$userId, null, []);
            Session::setFlash('success', 'Foto profil telah dihapus dan dikembalikan ke avatar default.');
        }

        return $this->redirect(base_url('profile/edit'));
    }

    /**
     * Perbarui tema tampilan pengguna (Light Mode / Dark Mode)
     * Mendukung pemanggilan via AJAX (instan tanpa reload) maupun POST form submit.
     */
    public function updateTheme(): Response
    {
        $userId = user_id();
        $theme = strtolower(trim((string)$this->request->input('theme', '')));

        if (!in_array($theme, ['dark', 'light'], true)) {
            if ($this->isAjaxRequest()) {
                return $this->json(['success' => false, 'error' => 'Tema tidak valid.'], 400);
            }
            Session::setFlash('danger', 'Pilihan tema tampilan tidak valid.');
            return $this->redirect(base_url('profile/edit'));
        }

        // Simpan preferensi tema ke database jika akun login
        if ($userId) {
            \App\Models\UserPreferenceModel::setThemeForUser((int)$userId, $theme);

            // Perbarui sesi login pengguna
            $sessUser = Session::get('user') ?? [];
            $sessUser['theme'] = $theme;
            Session::set('user', $sessUser);
        }

        Session::set('theme', $theme);

        // Pasang cookie browser untuk konsistensi sesi lokal
        if (!headers_sent()) {
            setcookie('smart_exam_theme', $theme, time() + (30 * 86400), '/', '', false, false);
        }

        if ($this->isAjaxRequest()) {
            return $this->json([
                'success' => true,
                'theme'   => $theme,
                'message' => 'Tema tampilan berhasil diubah ke ' . ($theme === 'dark' ? 'Tema Gelap' : 'Tema Terang') . '.'
            ]);
        }

        Session::setFlash('success', 'Tema tampilan berhasil diperbarui.');
        return $this->redirect(base_url('profile'));
    }

    /**
     * Validasi dan simpan file avatar yang diunggah
     */
    protected function processAvatarUpload(array $file, int $userId, ?string $currentAvatar): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Terjadi kesalahan saat mengunggah berkas (Kode: ' . $file['error'] . ').'];
        }

        $tmpName = $file['tmp_name'];
        $originalName = $file['name'];
        $fileSize = $file['size'];

        // 1. Batas ukuran maksimal 2 MB
        $maxBytes = 2 * 1024 * 1024;
        if ($fileSize > $maxBytes) {
            return ['success' => false, 'error' => 'Ukuran berkas terlalu besar. Maksimal ukuran foto profil adalah 2 MB.'];
        }

        // 2. Validasi Ekstensi File yang diizinkan (JPG, JPEG, PNG, WEBP)
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExtensions, true)) {
            return ['success' => false, 'error' => 'Format berkas tidak diizinkan. Gunakan format JPG, JPEG, PNG, atau WebP.'];
        }

        // 3. Validasi MIME Type berdasarkan isi file nyata (MIME Content Detection)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($realMime, $allowedMimes, true)) {
            return ['success' => false, 'error' => 'Isi berkas bukan merupakan gambar valid (' . htmlspecialchars((string)$realMime) . ').'];
        }

        // 4. Verifikasi Integritas Dimensi Gambar
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'Berkas gambar rusak atau tidak dapat diproses.'];
        }

        // 5. Buat direktori upload jika belum ada
        $uploadDir = FCPATH . 'uploads/avatars';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        // Pastikan .htaccess keamanan ada di folder upload
        $htaccessPath = $uploadDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Nonaktifkan eksekusi script untuk keamanan upload avatar\n" .
                               "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|exe|sh|py|bat|cmd)$\">\n" .
                               "    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n" .
                               "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n" .
                               "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n" .
                               "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n" .
                               "Options -Indexes -ExecCGI\n";
            @file_put_contents($htaccessPath, $htaccessContent);
        }

        // 6. Nama file acak untuk mencegah penimpaan dan eksploitasi nama asli
        $uniqueName = 'avatar_' . $userId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $targetFile = $uploadDir . '/' . $uniqueName;

        if (!is_uploaded_file($tmpName) && !file_exists($tmpName)) {
            return ['success' => false, 'error' => 'Berkas upload sementara tidak ditemukan.'];
        }

        $moved = is_uploaded_file($tmpName)
            ? move_uploaded_file($tmpName, $targetFile)
            : copy($tmpName, $targetFile);

        if (!$moved) {
            return ['success' => false, 'error' => 'Gagal memindahkan berkas foto ke direktori penyimpanan.'];
        }

        // 7. Hapus foto lama hanya setelah foto baru berhasil tersimpan secara aman
        if (!empty($currentAvatar) && str_starts_with($currentAvatar, 'uploads/avatars/avatar_')) {
            $oldFilePath = FCPATH . ltrim($currentAvatar, '/');
            if (file_exists($oldFilePath) && is_file($oldFilePath) && $oldFilePath !== $targetFile) {
                @unlink($oldFilePath);
            }
        }

        return [
            'success'   => true,
            'file_path' => 'uploads/avatars/' . $uniqueName
        ];
    }

    /**
     * Ambil data relasi spesifik berdasarkan peran pengguna
     */
    protected function getRoleSpecificDetails(int $userId, string $role): array
    {
        $db = Database::getConnection();
        $details = [];

        if (in_array($role, ['peserta_didik', 'siswa'], true)) {
            // Data Akademik Siswa dari class_students JOIN classes JOIN academic_years
            $stmt = $db->prepare("
                SELECT cs.*, c.name as class_name, c.grade_level, c.major, ay.name as academic_year_name
                FROM class_students cs
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN academic_years ay ON cs.academic_year_id = ay.id
                WHERE cs.student_id = :uid
                ORDER BY cs.id DESC LIMIT 1
            ");
            $stmt->execute([':uid' => $userId]);
            $details['academic'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Statistik Ujian Siswa
            $statStmt = $db->prepare("
                SELECT 
                    COUNT(id) as total_exams,
                    AVG(final_score) as avg_score,
                    MAX(final_score) as max_score,
                    SUM(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END) as passed_exams
                FROM exam_results 
                WHERE student_id = :uid
            ");
            $statStmt->execute([':uid' => $userId]);
            $details['exam_stats'] = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_exams' => 0,
                'avg_score' => 0,
                'max_score' => 0,
                'passed_exams' => 0
            ];
        } elseif ($role === 'guru') {
            // Data Mata Pelajaran Diampu
            $subStmt = $db->prepare("
                SELECT DISTINCT s.name as subject_name, s.code as subject_code
                FROM teacher_subjects ts
                INNER JOIN subjects s ON ts.subject_id = s.id
                WHERE ts.teacher_id = :uid
                ORDER BY s.name ASC
            ");
            $subStmt->execute([':uid' => $userId]);
            $details['subjects'] = $subStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Data Kelas yang Diajar
            $classStmt = $db->prepare("
                SELECT DISTINCT c.name as class_name, c.grade_level, c.major
                FROM teacher_subjects ts
                INNER JOIN classes c ON ts.class_id = c.id
                WHERE ts.teacher_id = :uid
                ORDER BY c.grade_level ASC, c.name ASC
            ");
            $classStmt->execute([':uid' => $userId]);
            $details['classes'] = $classStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Data Wali Kelas (jika menjadi wali kelas)
            $hrStmt = $db->prepare("
                SELECT id, name as class_name, grade_level, major 
                FROM classes 
                WHERE homeroom_teacher_id = :uid
                LIMIT 1
            ");
            $hrStmt->execute([':uid' => $userId]);
            $details['homeroom_class'] = $hrStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Jumlah Bank Soal yang Dibuat
            $qbStmt = $db->prepare("SELECT COUNT(id) FROM question_banks WHERE creator_id = :uid");
            $qbStmt->execute([':uid' => $userId]);
            $details['question_banks_count'] = (int)$qbStmt->fetchColumn();
        } else {
            // Data Administrator / Super Admin
            $permStmt = $db->prepare("
                SELECT COUNT(DISTINCT rp.permission_id) 
                FROM user_roles ur
                INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
                WHERE ur.user_id = :uid
            ");
            $permStmt->execute([':uid' => $userId]);
            $details['permissions_count'] = (int)$permStmt->fetchColumn();
        }

        return $details;
    }

    /**
     * Tentukan layout master berdasarkan role
     */
    protected function determineLayout(string $role): string
    {
        return match ($role) {
            'super_admin', 'admin', 'superadmin' => 'layouts/admin',
            'guru'                                => 'layouts/guru',
            'peserta_didik', 'siswa'              => 'layouts/peserta',
            default                               => 'layouts/main',
        };
    }

    /**
     * Tentukan URL dashboard berdasarkan role
     */
    protected function getRoleDashboardUrl(string $role): string
    {
        return match ($role) {
            'super_admin', 'admin', 'superadmin' => base_url('admin/dashboard'),
            'guru'                                => base_url('guru/dashboard'),
            'peserta_didik', 'siswa'              => base_url('siswa/dashboard'),
            default                               => base_url(),
        };
    }

    /**
     * Cek apakah request merupakan AJAX
     */
    protected function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));
    }
}
