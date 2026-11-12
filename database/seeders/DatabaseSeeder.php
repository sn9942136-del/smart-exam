<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Libraries\Database;
use PDO;

/**
 * Database Seeder
 * Populates default master data, roles, permissions, demo users, AI violation types, and academic data.
 */
class DatabaseSeeder
{
    public static function run(): void
    {
        $db = Database::getConnection();

        echo "========================================================\n";
        echo "DATABASE SEEDER: SISTEM UJIAN ONLINE DUAL KAMERA AI\n";
        echo "========================================================\n";

        // 1. Seed Roles
        echo "[1/8] Seeding Roles...\n";
        $roles = [
            ['name' => 'super_admin',   'display_name' => 'Super Administrator', 'description' => 'Akses penuh seluruh konfigurasi sistem dan server.'],
            ['name' => 'admin',         'display_name' => 'Administrator',       'description' => 'Manajemen operasional sekolah, pengguna, dan ujian.'],
            ['name' => 'guru',          'display_name' => 'Guru / Pengawas',     'description' => 'Pembuat soal, penjadwal ujian, pengawas live proctoring, dan penilai.'],
            ['name' => 'peserta_didik', 'display_name' => 'Peserta Didik',       'description' => 'Mengikuti ujian online dengan dual kamera AI.']
        ];

        $stmtRole = $db->prepare("
            INSERT INTO roles (name, display_name, description, created_at, updated_at)
            VALUES (:name, :display_name, :description, NOW(), NOW())
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description), updated_at = NOW()
        ");

        $roleIds = [];
        foreach ($roles as $r) {
            $stmtRole->execute($r);
            $roleIds[$r['name']] = (int) $db->query("SELECT id FROM roles WHERE name = '{$r['name']}'")->fetchColumn();
            echo "  ✓ Role: {$r['display_name']} ({$r['name']})\n";
        }

        // 2. Seed Permissions
        echo "[2/8] Seeding Permissions...\n";
        $permissions = [
            // Auth & Users
            ['name' => 'user.view', 'display_name' => 'Melihat Pengguna', 'module' => 'users'],
            ['name' => 'user.create', 'display_name' => 'Menambah Pengguna', 'module' => 'users'],
            ['name' => 'user.edit', 'display_name' => 'Mengubah Pengguna', 'module' => 'users'],
            ['name' => 'user.delete', 'display_name' => 'Menghapus Pengguna', 'module' => 'users'],
            // Akademik
            ['name' => 'academic.manage', 'display_name' => 'Kelola Data Akademik & Kelas', 'module' => 'academic'],
            // Bank Soal
            ['name' => 'question.view', 'display_name' => 'Melihat Bank Soal', 'module' => 'questions'],
            ['name' => 'question.create', 'display_name' => 'Membuat Bank Soal', 'module' => 'questions'],
            ['name' => 'question.ai_generate', 'display_name' => 'Generate Soal dengan AI', 'module' => 'questions'],
            // Ujian
            ['name' => 'exam.view', 'display_name' => 'Melihat Ujian', 'module' => 'exams'],
            ['name' => 'exam.create', 'display_name' => 'Membuat & Menjadwalkan Ujian', 'module' => 'exams'],
            ['name' => 'exam.take', 'display_name' => 'Mengerjakan Ujian Online', 'module' => 'exams'],
            // AI Proctoring
            ['name' => 'proctoring.monitor', 'display_name' => 'Memantau Live Feed Dual Kamera', 'module' => 'proctoring'],
            ['name' => 'proctoring.review', 'display_name' => 'Review & Validasi Pelanggaran AI', 'module' => 'proctoring'],
            // Penilaian
            ['name' => 'grading.view', 'display_name' => 'Melihat Hasil Ujian', 'module' => 'grading'],
            ['name' => 'grading.evaluate', 'display_name' => 'Menilai Esai & Publikasi Nilai', 'module' => 'grading'],
            // Sistem
            ['name' => 'system.config', 'display_name' => 'Konfigurasi Sistem & AI Parameter', 'module' => 'system'],
            ['name' => 'system.audit', 'display_name' => 'Melihat Audit Logs & Server Monitor', 'module' => 'system']
        ];

        $stmtPerm = $db->prepare("
            INSERT INTO permissions (name, display_name, module, created_at, updated_at)
            VALUES (:name, :display_name, :module, NOW(), NOW())
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), module = VALUES(module), updated_at = NOW()
        ");

        foreach ($permissions as $p) {
            $stmtPerm->execute($p);
        }
        echo "  ✓ Total " . count($permissions) . " permissions berhasil disinkronisasi.\n";

        // 3. Seed Demo Users & Role Assignments
        echo "[3/8] Seeding Demo User Accounts...\n";
        $users = [
            [
                'name'               => 'Super Administrator',
                'email'              => 'superadmin@example.com',
                'username'           => 'superadmin',
                'password'           => password_hash('SuperAdmin123!', PASSWORD_BCRYPT),
                'nip_nisn'           => 'ADM-0001',
                'phone'              => '081200000001',
                'role'               => 'super_admin'
            ],
            [
                'name'               => 'Administrator Ujian',
                'email'              => 'admin@example.com',
                'username'           => 'admin',
                'password'           => password_hash('Admin123!', PASSWORD_BCRYPT),
                'nip_nisn'           => 'ADM-0002',
                'phone'              => '081200000002',
                'role'               => 'admin'
            ],
            [
                'name'               => 'Budi Santoso, S.Kom., M.T.',
                'email'              => 'guru@example.com',
                'username'           => 'guru_budi',
                'password'           => password_hash('Guru123!', PASSWORD_BCRYPT),
                'nip_nisn'           => '198501012010011005',
                'phone'              => '081200000003',
                'role'               => 'guru'
            ],
            [
                'name'               => 'Ahmad Fauzi (Siswa Demo)',
                'email'              => 'siswa@example.com',
                'username'           => 'siswa_fauzi',
                'password'           => password_hash('Siswa123!', PASSWORD_BCRYPT),
                'nip_nisn'           => '0054321987',
                'phone'              => '081200000004',
                'role'               => 'peserta_didik'
            ]
        ];

        $stmtUser = $db->prepare("
            INSERT INTO users (name, email, username, password, nip_nisn, phone, status, created_at, updated_at)
            VALUES (:name, :email, :username, :password, :nip_nisn, :phone, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), updated_at = NOW()
        ");

        $stmtUserRole = $db->prepare("
            INSERT INTO user_roles (user_id, role_id, created_at)
            VALUES (:user_id, :role_id, NOW())
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)
        ");

        foreach ($users as $u) {
            $roleKey = $u['role'];
            unset($u['role']);
            $stmtUser->execute($u);

            $userId = (int) $db->query("SELECT id FROM users WHERE email = '{$u['email']}'")->fetchColumn();
            $roleId = $roleIds[$roleKey];

            $stmtUserRole->execute(['user_id' => $userId, 'role_id' => $roleId]);
            echo "  ✓ Akun Demo: {$u['name']} <{$u['email']}> [Role: {$roleKey}]\n";
        }

        // 4. Seed Academic Master
        echo "[4/8] Seeding Academic Master Data...\n";
        $db->exec("
            INSERT INTO academic_years (name, start_date, end_date, is_active, created_at, updated_at)
            VALUES ('2026/2027', '2026-07-15', '2027-06-20', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();
        ");
        $academicYearId = (int) $db->query("SELECT id FROM academic_years WHERE name = '2026/2027'")->fetchColumn();

        $db->exec("
            INSERT INTO semesters (academic_year_id, name, code, is_active, created_at, updated_at)
            VALUES ({$academicYearId}, 'Ganjil', '1', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW();
        ");

        // Subjects
        $subjects = [
            ['code' => 'MAT-12', 'name' => 'Matematika Peminatan', 'description' => 'Kalkulus, Vektor, dan Matriks Lanjut'],
            ['code' => 'INF-12', 'name' => 'Informatika & Rekayasa Perangkat Lunak', 'description' => 'Algoritma, Web MVC, dan Kecerdasan Buatan'],
            ['code' => 'FIS-12', 'name' => 'Fisika Terapan', 'description' => 'Mekanika Kuantum dan Elektromagnetika']
        ];
        $stmtSubj = $db->prepare("
            INSERT INTO subjects (code, name, description, created_at, updated_at)
            VALUES (:code, :name, :description, NOW(), NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), updated_at = NOW()
        ");
        foreach ($subjects as $s) {
            $stmtSubj->execute($s);
        }

        // Classes
        $guruId = (int) $db->query("SELECT id FROM users WHERE email = 'guru@example.com'")->fetchColumn();
        $siswaId = (int) $db->query("SELECT id FROM users WHERE email = 'siswa@example.com'")->fetchColumn();
        
        $db->exec("
            INSERT INTO classes (academic_year_id, name, grade_level, major, homeroom_teacher_id, created_at, updated_at)
            VALUES ({$academicYearId}, 'XII MIPA 1', '12', 'MIPA', {$guruId}, NOW(), NOW())
            ON DUPLICATE KEY UPDATE homeroom_teacher_id = VALUES(homeroom_teacher_id), updated_at = NOW();
        ");
        $classId = (int) $db->query("SELECT id FROM classes WHERE name = 'XII MIPA 1' AND academic_year_id = {$academicYearId}")->fetchColumn();

        // Enroll Demo Student to Class
        if ($siswaId && $classId) {
            $db->exec("
                INSERT INTO class_students (class_id, student_id, academic_year_id, roll_number, status, created_at, updated_at)
                VALUES ({$classId}, {$siswaId}, {$academicYearId}, '01', 'active', NOW(), NOW())
                ON DUPLICATE KEY UPDATE status = 'active', updated_at = NOW();
            ");
        }

        // Assign Teacher to Subjects for this Class
        $matId = (int) $db->query("SELECT id FROM subjects WHERE code = 'MAT-12'")->fetchColumn();
        $infId = (int) $db->query("SELECT id FROM subjects WHERE code = 'INF-12'")->fetchColumn();
        if ($guruId && $matId && $academicYearId) {
            $db->exec("
                INSERT INTO teacher_subjects (teacher_id, subject_id, class_id, academic_year_id, created_at, updated_at)
                VALUES ({$guruId}, {$matId}, {$classId}, {$academicYearId}, NOW(), NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW();
            ");
        }
        if ($guruId && $infId && $academicYearId) {
            $db->exec("
                INSERT INTO teacher_subjects (teacher_id, subject_id, class_id, academic_year_id, created_at, updated_at)
                VALUES ({$guruId}, {$infId}, {$classId}, {$academicYearId}, NOW(), NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW();
            ");
        }

        echo "  ✓ Tahun Ajaran 2026/2027, Semester Ganjil, 3 Mata Pelajaran, Kelas XII MIPA 1, & Penugasan Guru/Siswa dibuat.\n";

        // 5. Seed AI Violation Types
        echo "[5/8] Seeding AI Proctoring Violation Types...\n";
        $violations = [
            [
                'code'          => 'NO_FACE',
                'name'          => 'Wajah Tidak Terdeteksi di Kamera',
                'severity'      => 'high',
                'penalty_score' => 10.00,
                'description'   => 'Kamera depan tidak dapat mendeteksi wajah peserta dalam rentang waktu toleransi (>5 detik).'
            ],
            [
                'code'          => 'MULTIPLE_FACES',
                'name'          => 'Terdeteksi Lebih dari 1 Wajah',
                'severity'      => 'critical',
                'penalty_score' => 25.00,
                'description'   => 'Terdapat individu lain yang tertangkap kamera di ruang ujian peserta.'
            ],
            [
                'code'          => 'LOOK_AWAY',
                'name'          => 'Arah Pandangan Mata Berpaling dari Layar',
                'severity'      => 'medium',
                'penalty_score' => 5.00,
                'description'   => 'AI Gaze tracking mendeteksi peserta menoleh ke samping/bawah secara berulang (>3 detik).'
            ],
            [
                'code'          => 'PHONE_DETECTED',
                'name'          => 'Smartphone Terdeteksi di Area Meja',
                'severity'      => 'critical',
                'penalty_score' => 30.00,
                'description'   => 'Kamera sekunder mendeteksi penggunaan smartphone atau perangkat komunikasi terlarang.'
            ],
            [
                'code'          => 'TAB_SWITCH',
                'name'          => 'Berpindah Tab / Jendela Aplikasi',
                'severity'      => 'high',
                'penalty_score' => 15.00,
                'description'   => 'Peserta meninggalkan jendela ujian (Browser Blur / Window switch).'
            ],
            [
                'code'          => 'SECONDARY_CAM_LOST',
                'name'          => 'Feed Kamera Sekunder Terputus',
                'severity'      => 'high',
                'penalty_score' => 15.00,
                'description'   => 'Koneksi WebRTC dari smartphone kamera sekunder terputus atau tidak merespons.'
            ],
            [
                'code'          => 'AUDIO_SPEECH_DETECTED',
                'name'          => 'Suara Percakapan Mencurigakan',
                'severity'      => 'medium',
                'penalty_score' => 10.00,
                'description'   => 'AI Audio Spectrogram mendeteksi suara percakapan manusia saat ujian berlangsung.'
            ]
        ];

        $stmtViol = $db->prepare("
            INSERT INTO ai_violation_types (code, name, severity, penalty_score, description, created_at, updated_at)
            VALUES (:code, :name, :severity, :penalty_score, :description, NOW(), NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name), severity = VALUES(severity), penalty_score = VALUES(penalty_score), description = VALUES(description), updated_at = NOW()
        ");

        foreach ($violations as $v) {
            $stmtViol->execute($v);
            echo "  ✓ Violation Type: [{$v['code']}] - {$v['name']} ({$v['severity']})\n";
        }

        // 6. Seed AI Configurations
        echo "[6/8] Seeding Default AI Configurations...\n";
        $aiConfigs = [
            ['key_name' => 'face_detection_model', 'key_value' => 'MediaPipe Face Mesh v2', 'category' => 'proctoring_ai', 'description' => 'Model deteksi 468 titik landmark wajah.'],
            ['key_name' => 'gaze_sensitivity_threshold', 'key_value' => '0.75', 'category' => 'proctoring_ai', 'description' => 'Batas ambang toleransi deviasi arah pandang mata.'],
            ['key_name' => 'no_face_timeout_seconds', 'key_value' => '5', 'category' => 'proctoring_ai', 'description' => 'Durasi toleransi wajah hilang sebelum mencatat pelanggaran.'],
            ['key_name' => 'dual_cam_sync_interval_ms', 'key_value' => '1000', 'category' => 'proctoring_ai', 'description' => 'Interval sinkronisasi snapshot dual kamera (ms).'],
            ['key_name' => 'ai_question_generation_model', 'key_value' => 'gpt-4o-mini', 'category' => 'question_generation_ai', 'description' => 'Model LLM untuk pembuatan bank soal otomatis.'],
            ['key_name' => 'ai_essay_grading_tolerance', 'key_value' => '0.85', 'category' => 'essay_grading_ai', 'description' => 'Derajat kesamaan semantik untuk skor esai berbantuan AI.']
        ];

        $stmtAiConfig = $db->prepare("
            INSERT INTO ai_configurations (key_name, key_value, category, description, created_at, updated_at)
            VALUES (:key_name, :key_value, :category, :description, NOW(), NOW())
            ON DUPLICATE KEY UPDATE key_value = VALUES(key_value), description = VALUES(description), updated_at = NOW()
        ");

        foreach ($aiConfigs as $ac) {
            $stmtAiConfig->execute($ac);
        }
        echo "  ✓ " . count($aiConfigs) . " parameter konfigurasi AI terpasang.\n";

        // 7. Seed Storage Policies
        echo "[7/8] Seeding Storage Policies...\n";
        $storagePolicies = [
            ['media_type' => 'recordings', 'retention_days' => 30, 'auto_delete_after_expiration' => 1, 'compression_enabled' => 1],
            ['media_type' => 'snapshots', 'retention_days' => 60, 'auto_delete_after_expiration' => 1, 'compression_enabled' => 1],
            ['media_type' => 'backups', 'retention_days' => 90, 'auto_delete_after_expiration' => 0, 'compression_enabled' => 1],
            ['media_type' => 'uploads', 'retention_days' => 365, 'auto_delete_after_expiration' => 0, 'compression_enabled' => 0]
        ];

        $stmtPolicy = $db->prepare("
            INSERT INTO storage_policies (media_type, retention_days, auto_delete_after_expiration, compression_enabled, created_at, updated_at)
            VALUES (:media_type, :retention_days, :auto_delete_after_expiration, :compression_enabled, NOW(), NOW())
            ON DUPLICATE KEY UPDATE retention_days = VALUES(retention_days), auto_delete_after_expiration = VALUES(auto_delete_after_expiration), updated_at = NOW()
        ");

        foreach ($storagePolicies as $sp) {
            $stmtPolicy->execute($sp);
        }
        echo "  ✓ Kebijakan retensi penyimpanan terpasang.\n";

        // 8. Seed Demo Question Bank, Questions, Exam, Schedules & Active Token
        echo "[8/8] Seeding Demo Question Bank & Published Exam Package...\n";
        
        $bankCode = 'BNK-MAT12-UAS';
        $db->exec("
            INSERT INTO question_banks (subject_id, creator_id, code, title, description, total_questions, status, created_at, updated_at)
            VALUES ({$matId}, {$guruId}, '{$bankCode}', 'Bank Soal Matematika Peminatan XII MIPA (Demo UAS)', 'Bank soal komprehensif Matematika Peminatan untuk asesmen CBT Dual Kamera AI.', 6, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE title = VALUES(title), status = 'active', total_questions = 6, updated_at = NOW();
        ");
        $bankId = (int) $db->query("SELECT id FROM question_banks WHERE code = '{$bankCode}'")->fetchColumn();

        // Butir Soal Demo
        $demoQuestions = [
            [
                'type' => 'multiple_choice',
                'content' => '<p>Nilai dari limit trigonometri dasar berikut adalah:</p><p>$$\\lim_{x \\to 0} \\frac{\\sin 4x}{2x}$$</p>',
                'explanation' => '<p>Berdasarkan sifat limit dasar trigonometri $\\lim_{x \\to 0} \\frac{\\sin ax}{bx} = \\frac{a}{b}$, maka $\\frac{4}{2} = 2$.</p>',
                'weight' => 15.00,
                'difficulty' => 'easy',
                'order' => 1,
                'options' => [
                    ['key' => 'A', 'content' => '1', 'is_correct' => 0],
                    ['key' => 'B', 'content' => '2', 'is_correct' => 1],
                    ['key' => 'C', 'content' => '4', 'is_correct' => 0],
                    ['key' => 'D', 'content' => '0', 'is_correct' => 0],
                    ['key' => 'E', 'content' => '1/2', 'is_correct' => 0]
                ]
            ],
            [
                'type' => 'multiple_choice',
                'content' => '<p>Turunan pertama dari fungsi trigonometri $f(x) = \\sin^2(3x)$ adalah...</p>',
                'explanation' => '<p>Gunakan aturan rantai: $f\'(x) = 2 \\sin(3x) \\cdot \\cos(3x) \\cdot 3 = 3 \\cdot (2\\sin 3x \\cos 3x) = 3\\sin(6x)$.</p>',
                'weight' => 15.00,
                'difficulty' => 'medium',
                'order' => 2,
                'options' => [
                    ['key' => 'A', 'content' => '3 sin(6x)', 'is_correct' => 1],
                    ['key' => 'B', 'content' => '6 sin(3x)', 'is_correct' => 0],
                    ['key' => 'C', 'content' => '2 sin(3x) cos(3x)', 'is_correct' => 0],
                    ['key' => 'D', 'content' => '6 cos(3x)', 'is_correct' => 0],
                    ['key' => 'E', 'content' => '3 cos(6x)', 'is_correct' => 0]
                ]
            ],
            [
                'type' => 'multiple_choice',
                'content' => '<p>Diketahui dua vektor pada ruang 3 dimensi $\\vec{a} = 2\\hat{i} - \\hat{j} + 3\\hat{k}$ dan $\\vec{b} = \\hat{i} + 2\\hat{j} - \\hat{k}$. Nilai perkalian skalar (dot product) $\\vec{a} \\cdot \\vec{b}$ adalah...</p>',
                'explanation' => '<p>$\\vec{a} \\cdot \\vec{b} = (2)(1) + (-1)(2) + (3)(-1) = 2 - 2 - 3 = -3$.</p>',
                'weight' => 15.00,
                'difficulty' => 'easy',
                'order' => 3,
                'options' => [
                    ['key' => 'A', 'content' => '-3', 'is_correct' => 1],
                    ['key' => 'B', 'content' => '3', 'is_correct' => 0],
                    ['key' => 'C', 'content' => '7', 'is_correct' => 0],
                    ['key' => 'D', 'content' => '-1', 'is_correct' => 0],
                    ['key' => 'E', 'content' => '5', 'is_correct' => 0]
                ]
            ],
            [
                'type' => 'multiple_choice',
                'content' => '<p>Persamaan garis singgung kurva $y = \\cos x$ pada titik dengan absis $x = \\frac{\\pi}{2}$ adalah...</p>',
                'explanation' => '<p>Untuk $x = \\pi/2$, $y = \\cos(\\pi/2) = 0$. Titik singgung $(\\pi/2, 0)$. Gradien $m = y\' = -\\sin(\\pi/2) = -1$. Persamaan: $y - 0 = -1(x - \\pi/2) \\Rightarrow y = -x + \\frac{\\pi}{2}$.</p>',
                'weight' => 15.00,
                'difficulty' => 'medium',
                'order' => 4,
                'options' => [
                    ['key' => 'A', 'content' => 'y = -x + π/2', 'is_correct' => 1],
                    ['key' => 'B', 'content' => 'y = x - π/2', 'is_correct' => 0],
                    ['key' => 'C', 'content' => 'y = -x - π/2', 'is_correct' => 0],
                    ['key' => 'D', 'content' => 'y = 2x - π', 'is_correct' => 0],
                    ['key' => 'E', 'content' => 'y = -2x + π', 'is_correct' => 0]
                ]
            ],
            [
                'type' => 'true_false',
                'content' => '<p><strong>Pernyataan:</strong> Jika matriks bujur sangkar $A$ berordo $3 \\times 3$ memiliki determinan bernilai $0$ (det $A = 0$), maka matriks $A$ adalah matriks singular dan tidak memiliki invers.</p>',
                'explanation' => '<p>Matriks singular adalah matriks yang nilai determinannya sama dengan nol, sehingga matriks tersebut tidak memiliki invers ($A^{-1}$ tidak terdefinisi).</p>',
                'weight' => 15.00,
                'difficulty' => 'easy',
                'order' => 5,
                'options' => [
                    ['key' => 'A', 'content' => 'Benar', 'is_correct' => 1],
                    ['key' => 'B', 'content' => 'Salah', 'is_correct' => 0]
                ]
            ],
            [
                'type' => 'essay',
                'content' => '<p>Jelaskan secara sistematis langkah-langkah dalam menentukan titik stasioner serta jenis nilai stasioner (maksimum relatif, minimum relatif, atau titik belok) dari suatu fungsi trigonometri $f(x)$ pada interval tertutup $[0, 2\\pi]$ menggunakan uji turunan pertama dan uji turunan kedua!</p>',
                'explanation' => '<p>Langkah: 1) Tentukan turunan pertama $f\'(x)$. 2) Cari titik stasioner dengan menyelesaikan persamaan $f\'(x) = 0$. 3) Hitung nilai turunan kedua $f\'\'(x)$ pada absis stasioner. Jika $f\'\'(x) < 0$ maka titik balik maksimum; jika $f\'\'(x) > 0$ maka titik balik minimum; jika $f\'\'(x) = 0$ lakukan uji tanda turunan pertama.</p>',
                'weight' => 25.00,
                'difficulty' => 'hard',
                'order' => 6,
                'options' => []
            ]
        ];

        // Insert Soal & Opsi
        $stmtQ = $db->prepare("
            INSERT INTO questions (question_bank_id, type, content, explanation, weight, difficulty, order_number, created_at, updated_at)
            VALUES (:bank_id, :type, :content, :explanation, :weight, :difficulty, :order_number, NOW(), NOW())
        ");
        $stmtOpt = $db->prepare("
            INSERT INTO question_options (question_id, option_key, content, is_correct, score_value, created_at, updated_at)
            VALUES (:qid, :key, :content, :is_correct, :score, NOW(), NOW())
        ");

        $questionIds = [];
        $existingQIds = $db->query("SELECT id FROM questions WHERE question_bank_id = {$bankId}")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($existingQIds)) {
            $questionIds = array_map('intval', $existingQIds);
        } else {
            foreach ($demoQuestions as $dq) {
                $stmtQ->execute([
                    ':bank_id'      => $bankId,
                    ':type'         => $dq['type'],
                    ':content'      => $dq['content'],
                    ':explanation'  => $dq['explanation'],
                    ':weight'       => $dq['weight'],
                    ':difficulty'   => $dq['difficulty'],
                    ':order_number' => $dq['order']
                ]);
                $qId = (int) $db->lastInsertId();
                $questionIds[] = $qId;

                foreach ($dq['options'] as $opt) {
                    $scoreVal = $opt['is_correct'] ? $dq['weight'] : 0.00;
                    $stmtOpt->execute([
                        ':qid'        => $qId,
                        ':key'        => $opt['key'],
                        ':content'    => $opt['content'],
                        ':is_correct' => $opt['is_correct'],
                        ':score'      => $scoreVal
                    ]);
                }
            }
        }
        echo "  ✓ Bank Soal & 6 Butir Soal (5 PG + 1 Esai) siap.\n";

        // Buat Paket Ujian Demo UAS
        $examCode = 'EXAM-MAT12-UAS';
        $db->exec("
            INSERT INTO exams (subject_id, teacher_id, title, code, description, type, duration_minutes, passing_grade, randomize_questions, randomize_options, show_result_immediately, is_dual_cam_required, status, created_at, updated_at)
            VALUES ({$matId}, {$guruId}, 'Penilaian Akhir Semester (PAS) Matematika Peminatan XII', '{$examCode}', 'Ujian Akhir Semester berbasis Dual Kamera AI. Pastikan kamera laptop dan smartphone sekunder terhubung dengan baik.', 'final_exam', 60, 75.00, 1, 1, 1, 1, 'published', NOW(), NOW())
            ON DUPLICATE KEY UPDATE title = VALUES(title), status = 'published', is_dual_cam_required = 1, updated_at = NOW();
        ");
        $examId = (int) $db->query("SELECT id FROM exams WHERE code = '{$examCode}'")->fetchColumn();

        // Aturan Ujian
        $db->exec("
            INSERT INTO exam_rules (exam_id, allow_tab_switch, max_tab_switches, lock_browser, detect_multiple_faces, detect_look_away, detect_audio_noise, detect_secondary_device, created_at, updated_at)
            VALUES ({$examId}, 0, 3, 1, 1, 1, 1, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW();
        ");

        // Relasikan Soal ke Ujian
        $stmtEq = $db->prepare("
            INSERT INTO exam_questions (exam_id, question_id, order_number, score_weight, created_at)
            VALUES (:exam_id, :qid, :ord, :weight, NOW())
            ON DUPLICATE KEY UPDATE score_weight = VALUES(score_weight)
        ");
        foreach ($questionIds as $idx => $qid) {
            $weight = ($idx === 5) ? 25.00 : 15.00;
            $stmtEq->execute([
                ':exam_id' => $examId,
                ':qid'     => $qid,
                ':ord'     => $idx + 1,
                ':weight'  => $weight
            ]);
        }

        // Jadwal Ujian untuk Kelas XII MIPA 1 (Aktif 30 hari ke depan)
        $db->exec("
            INSERT INTO exam_schedules (exam_id, class_id, start_time, end_time, grace_period_minutes, status, created_at, updated_at)
            VALUES ({$examId}, {$classId}, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 30 DAY), 15, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE status = 'active', end_time = DATE_ADD(NOW(), INTERVAL 30 DAY), updated_at = NOW();
        ");

        // Enroll Siswa Demo ke Peserta Ujian
        $db->exec("
            INSERT INTO exam_participants (exam_id, student_id, class_id, status, created_at, updated_at)
            VALUES ({$examId}, {$siswaId}, {$classId}, 'not_started', NOW(), NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW();
        ");

        // Rilis Token Ujian Aktif: 'UAS2026'
        $db->exec("
            INSERT INTO exam_tokens (exam_id, token, valid_from, valid_until, is_active, auto_refresh_minutes, created_at, updated_at)
            VALUES ({$examId}, 'UAS2026', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 30 DAY), 1, 15, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_active = 1, valid_until = DATE_ADD(NOW(), INTERVAL 30 DAY), updated_at = NOW();
        ");

        echo "  ✓ Paket Ujian Aktif [{$examCode}], Aturan Dual Camera, Jadwal Rombel XII MIPA 1, & Token [UAS2026] siap digunakan.\n";

        // Completed
        echo "\n========================================================\n";
        echo "DATABASE SEEDING SELESAI 100% (ENVIRONMENT DEMO SIAP)\n";
        echo "========================================================\n";
    }
}
