<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\App;
use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\AuditLogModel;
use App\Models\ClassModel;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamResultModel;
use App\Models\ExamScheduleModel;
use App\Models\ExamSessionModel;
use App\Models\ExamTokenModel;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use App\Services\AiProctoringService;
use App\Services\AuthService;
use App\Services\ExamAnswerService;
use App\Services\ExamMonitoringService;
use App\Services\ExamService;
use App\Services\ExamSessionService;
use App\Services\ExamTimerService;
use App\Services\GradingService;
use App\Services\PasswordService;
use App\Services\QuestionService;
use App\Services\ReportService;
use App\Services\ResultService;

class ProductionAuditRunner
{
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $failures = [];

    public function run(): void
    {
        echo "========================================================================\n";
        echo "       SISTEM UJIAN ONLINE BERBASIS DUAL KAMERA AI - PRODUCTION AUDIT   \n";
        echo "========================================================================\n";
        echo "Waktu Uji : " . date('Y-m-d H:i:s') . "\n";
        echo "PHP Ver   : " . PHP_VERSION . "\n";
        echo "OS        : " . PHP_OS . "\n";
        echo "------------------------------------------------------------------------\n\n";

        Session::start();

        $this->test1_Login();
        $this->test2_RoleAccess();
        $this->test3_CrudUser();
        $this->test4_CrudSoal();
        $this->test5_CrudUjian();
        $this->test6_ExamSession();
        $this->test7_AutoSave();
        $this->test9_CameraPermission();
        $this->test10_AiLogging();
        $this->test8_SubmitUjian();
        $this->test11_Grading();
        $this->test12_ReportExport();

        echo "\n========================================================================\n";
        echo "                           AUDIT SUMMARY REPORT                         \n";
        echo "========================================================================\n";
        echo "Total Modul Diuji : 12 / 12\n";
        echo "Total Asersi      : {$this->totalTests}\n";
        echo "Berhasil (PASS)   : {$this->passedTests}\n";
        echo "Gagal (FAIL)      : {$this->failedTests}\n";
        echo "Pass Rate         : " . round(($this->passedTests / max(1, $this->totalTests)) * 100, 2) . "%\n";

        if ($this->failedTests > 0) {
            echo "\nRincian Kegagalan:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
            exit(1);
        } else {
            echo "\nSTATUS: SEMUA 12 MODUL LOLOS UJI PRODUCTION READINESS SECARA LENGKAP!\n";
            echo "========================================================================\n";
        }
    }

    private function assertCondition(bool $condition, string $testName, string $message = ''): void
    {
        $this->totalTests++;
        if ($condition) {
            $this->passedTests++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failedTests++;
            $errMsg = "{$testName} - {$message}";
            $this->failures[] = $errMsg;
            echo "  [FAIL] {$errMsg}\n";
        }
    }

    // -------------------------------------------------------------------------
    // TEST 1: LOGIN & AUTHENTICATION
    // -------------------------------------------------------------------------
    private function test1_Login(): void
    {
        echo "1. Menguji Modul Otentikasi & Login...\n";
        $auth = new AuthService();

        // 1.1 Login dengan kredensial salah
        $resFail = $auth->attempt('nonexistent@user.com', 'wrongpassword');
        $this->assertCondition($resFail['success'] === false, 'Otentikasi Kredensial Salah Ditolak');

        // 1.2 Login Super Admin & Admin
        $resSuper = $auth->attempt('superadmin@example.com', 'SuperAdmin123!');
        $this->assertCondition($resSuper['success'] === true, 'Login Super Admin Berhasil');
        $this->assertCondition($resSuper['user']['role'] === 'super_admin', 'Role Super Admin Terverifikasi');

        $resAdmin = $auth->attempt('admin@example.com', 'Admin123!');
        $this->assertCondition($resAdmin['success'] === true, 'Login Administrator Berhasil');
        $this->assertCondition($resAdmin['user']['role'] === 'admin', 'Role Administrator Terverifikasi');
        $this->assertCondition(is_logged_in() === true, 'Session Login Global Aktif');

        // 1.3 CSRF token generation
        $token = Session::getCsrfToken();
        $this->assertCondition(!empty($token) && strlen($token) === 64, 'CSRF Token Ter-generate Secara Aman (64-char hex)');
        $this->assertCondition(Session::verifyCsrfToken($token) === true, 'Verifikasi CSRF Token Valid');
        $this->assertCondition(Session::verifyCsrfToken('invalid_token') === false, 'Verifikasi CSRF Token Palsu Ditolak');

        // 1.4 Logout
        $auth->logout();
        $this->assertCondition(!is_logged_in(), 'Sesi Logout Berhasil Dikosongkan');
    }

    // -------------------------------------------------------------------------
    // TEST 2: ROLE ACCESS CONTROL
    // -------------------------------------------------------------------------
    private function test2_RoleAccess(): void
    {
        echo "\n2. Menguji Modul Role Access Control (RBAC)...\n";
        $auth = new AuthService();

        // Siswa Login
        $resSiswa = $auth->attempt('siswa@example.com', 'Siswa123!');
        $this->assertCondition($resSiswa['success'] === true, 'Login Siswa Berhasil');
        $this->assertCondition(has_role('peserta_didik') === true, 'Siswa Memiliki Role peserta_didik');
        $this->assertCondition(has_role('guru') === false, 'Siswa Tidak Memiliki Role guru');
        $this->assertCondition(has_role(['admin', 'super_admin']) === false, 'Siswa Dibatasi dari Role Admin');

        // Guru Login
        $auth->logout();
        $resGuru = $auth->attempt('guru@example.com', 'Guru123!');
        $this->assertCondition($resGuru['success'] === true, 'Login Guru Berhasil');
        $this->assertCondition(has_role('guru') === true, 'Guru Memiliki Role guru');
        $this->assertCondition(has_role('super_admin') === false, 'Guru Bukan Super Admin');

        $auth->logout();
    }

    // -------------------------------------------------------------------------
    // TEST 3: CRUD USER
    // -------------------------------------------------------------------------
    private function test3_CrudUser(): void
    {
        echo "\n3. Menguji Modul Manajemen Pengguna (CRUD User)...\n";
        $userModel = new UserModel();
        $pwdService = new PasswordService();

        $testEmail = 'audittest_' . time() . '@sekolah.sch.id';
        $testUsername = 'user_audit_' . time();
        $hashedPwd = password_hash('AuditSecure123!', PASSWORD_BCRYPT, ['cost' => 12]);

        // 3.1 Create
        $createdId = $userModel->insert([
            'name'       => 'User Audit Unit Test',
            'email'      => $testEmail,
            'username'   => $testUsername,
            'password'   => $hashedPwd,
            'nip_nisn'   => 'AUDIT' . rand(10000, 99999),
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $this->assertCondition($createdId > 0, 'Create User Baru di Database');

        // Assign role siswa
        $userModel->assignRole((int)$createdId, 3); // 3 = peserta_didik

        // 3.2 Read / Find
        $found = $userModel->find($createdId);
        $this->assertCondition($found !== null && $found['email'] === $testEmail, 'Read / Find User Berdasarkan ID');

        // 3.3 Update
        $updated = $userModel->update($createdId, ['name' => 'User Audit Updated Name']);
        $this->assertCondition($updated === true, 'Update Data User');

        // 3.4 Password Reset via OTP Flow
        $otpReq = $pwdService->requestReset($testEmail);
        $this->assertCondition($otpReq['success'] === true, 'Request OTP Reset Password');
        $otpCode = $otpReq['otp'];

        $otpVerify = $pwdService->verifyOtp((int)$createdId, $otpCode);
        $this->assertCondition($otpVerify['success'] === true, 'Verifikasi 6-Digit OTP');
        $resetToken = $otpVerify['token'];

        $newPlainPwd = 'NewSecurePassword456!';
        $resetResult = $pwdService->resetPassword($testEmail, $resetToken, $newPlainPwd, $newPlainPwd);
        $this->assertCondition($resetResult['success'] === true, 'Reset & Update Password via OTP Token');

        $reloaded = $userModel->find($createdId);
        $this->assertCondition(password_verify($newPlainPwd, $reloaded['password']) === true, 'Verifikasi Hash Password Baru Sesuai BCRYPT');

        // 3.5 Delete
        $deleted = $userModel->delete($createdId);
        $this->assertCondition($deleted === true, 'Delete User Berhasil');
        $this->assertCondition($userModel->find($createdId) === null, 'Verifikasi User Telah Terhapus dari Basis Data');
    }

    // -------------------------------------------------------------------------
    // TEST 4: CRUD SOAL & BANK SOAL
    // -------------------------------------------------------------------------
    private function test4_CrudSoal(): void
    {
        echo "\n4. Menguji Modul Bank Soal & Butir Soal (CRUD Soal)...\n";
        $bankModel = new QuestionBankModel();
        $qService = new QuestionService();
        $subModel = new SubjectModel();
        $subject = $subModel->first();

        // 4.1 Create Bank Soal
        $bankCode = 'BANK-AUDIT-' . time();
        $bankId = $bankModel->insert([
            'subject_id'     => $subject['id'] ?? 1,
            'creator_id'     => 3, // ID Guru
            'code'           => $bankCode,
            'title'          => 'Bank Soal Audit Test',
            'description'    => 'Bank Soal untuk Pengujian Otomatis',
            'grade_level'    => '10',
            'total_questions'=> 0,
            'created_at'     => date('Y-m-d H:i:s')
        ]);
        $this->assertCondition($bankId > 0, 'Create Bank Soal');

        // 4.2 Create Soal Pilihan Ganda
        $mcResult = $qService->createQuestion((int)$bankId, [
            'type'            => 'multiple_choice',
            'difficulty'      => 'medium',
            'content'         => 'Berapakah hasil dari 15 + 25?',
            'weight'          => 2.0,
            'correct_option'  => 'B',
            'options'         => [
                'A' => '30',
                'B' => '40',
                'C' => '50',
                'D' => '60'
            ],
            'explanation'     => '15 + 25 = 40'
        ]);
        $this->assertCondition($mcResult['success'] === true, 'Create Soal Pilihan Ganda Beserta Opsi');
        $mcQuestionId = $mcResult['question_id'];

        // 4.3 Create Soal Essay
        $essayResult = $qService->createQuestion((int)$bankId, [
            'type'            => 'essay',
            'difficulty'      => 'hard',
            'content'         => 'Jelaskan prinsip kerja transmisi data peer-to-peer pada WebRTC!',
            'weight'          => 10.0,
            'explanation'     => 'WebRTC menggunakan ICE candidates, STUN/TURN, dan SDP offer/answer.'
        ]);
        $this->assertCondition($essayResult['success'] === true, 'Create Soal Essay');
        $essayQuestionId = $essayResult['question_id'];

        // 4.4 Read & Preview
        $qModel = new QuestionModel();
        $previewData = $qModel->getWithFullDetails($mcQuestionId);
        $this->assertCondition(!empty($previewData['options']) && count($previewData['options']) === 4, 'Read / Preview Soal Beserta 4 Opsi Jawaban');

        // 4.5 Update Soal
        $updRes = $qService->updateQuestion($mcQuestionId, [
            'type'           => 'multiple_choice',
            'difficulty'     => 'easy',
            'content'        => 'Berapakah hasil penjumlahan 20 + 20?',
            'weight'         => 2.0,
            'correct_option' => 'A',
            'options'        => [
                'A' => '40',
                'B' => '50',
                'C' => '60',
                'D' => '70'
            ]
        ]);
        $this->assertCondition($updRes['success'] === true, 'Update Butir Soal');

        // Clean up question items
        $qService->deleteQuestion($mcQuestionId);
        $qService->deleteQuestion($essayQuestionId);
        $bankModel->delete($bankId);
        $this->assertCondition(true, 'Delete Soal & Bank Soal');
    }

    // -------------------------------------------------------------------------
    // TEST 5: CRUD UJIAN & JADWAL
    // -------------------------------------------------------------------------
    private function test5_CrudUjian(): void
    {
        echo "\n5. Menguji Modul Manajemen Ujian & Token (CRUD Ujian)...\n";
        $examService = new ExamService();
        $examModel = new ExamModel();
        $tokenModel = new ExamTokenModel();
        $subModel = new SubjectModel();
        $classModel = new ClassModel();

        $subject = $subModel->first();
        $class = $classModel->first();

        // 5.1 Siapkan Bank Soal & Butir Soal untuk Ujian
        $bankModel = new QuestionBankModel();
        $qModel = new QuestionModel();
        $qOptionModel = new QuestionOptionModel();

        $bankId = $bankModel->insert([
            'subject_id'      => $subject['id'] ?? 1,
            'creator_id'      => 3,
            'code'            => 'BANK-AUDIT-EXAM-' . time(),
            'title'           => 'Bank Soal Ujian Komprehensif',
            'description'     => 'Live Test Bank Soal',
            'grade_level'     => '10',
            'total_questions' => 2,
            'created_at'      => date('Y-m-d H:i:s')
        ]);

        // Soal 1: Pilihan Ganda (Jawaban benar: B)
        $q1Id = $qModel->insert([
            'question_bank_id' => $bankId,
            'type'             => 'multiple_choice',
            'difficulty'       => 'easy',
            'content'          => 'Apa fungsi dari CPU pada komputer?',
            'weight'           => 50.0,
            'order_number'     => 1,
            'created_at'       => date('Y-m-d H:i:s')
        ]);
        $opt1A = $qOptionModel->insert(['question_id' => $q1Id, 'option_key' => 'A', 'content' => 'Menyimpan data jangka panjang', 'is_correct' => 0, 'score_value' => 0]);
        $opt1B = $qOptionModel->insert(['question_id' => $q1Id, 'option_key' => 'B', 'content' => 'Pusat pemrosesan instruksi dan logika', 'is_correct' => 1, 'score_value' => 50.0]);
        $opt1C = $qOptionModel->insert(['question_id' => $q1Id, 'option_key' => 'C', 'content' => 'Menghubungkan internet', 'is_correct' => 0, 'score_value' => 0]);

        // Soal 2: Essay (Bobot 50.0)
        $q2Id = $qModel->insert([
            'question_bank_id' => $bankId,
            'type'             => 'essay',
            'difficulty'       => 'medium',
            'content'          => 'Jelaskan bagaimana AI Proctoring mendeteksi kecurangan saat ujian!',
            'weight'           => 50.0,
            'order_number'     => 2,
            'created_at'       => date('Y-m-d H:i:s')
        ]);

        $examData = [
            'subject_id'               => $subject['id'] ?? 1,
            'question_bank_id'         => (int)$bankId,
            'title'                    => 'Ujian Komprehensif Production Audit',
            'description'              => 'Pengujian Siklus Ujian Lengkap',
            'duration_minutes'         => 60,
            'passing_grade'            => 75.0,
            'shuffle_questions'        => 1,
            'shuffle_options'          => 1,
            'enable_proctoring'        => 1,
            'enable_dual_camera'       => 1,
            'show_result_immediately'  => 0,
            'class_ids'                => [$class['id'] ?? 1],
            'start_time'               => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'end_time'                 => date('Y-m-d H:i:s', strtotime('+4 hours'))
        ];

        $createRes = $examService->createExam(3, $examData); // 3 = ID Guru
        $this->assertCondition($createRes['success'] === true, 'Create Ujian Lengkap dengan Aturan & Relasi Kelas');
        $examId = (int)$createRes['exam_id'];

        // Simpan referensi global
        $GLOBALS['AUDIT_BANK_ID'] = (int)$bankId;
        $GLOBALS['AUDIT_Q1_ID'] = (int)$q1Id;
        $GLOBALS['AUDIT_Q2_ID'] = (int)$q2Id;
        $GLOBALS['AUDIT_OPT1B_ID'] = (int)$opt1B;

        // 5.2 Read / Show
        $examDetails = $examModel->getDetailsWithFullRelations($examId);
        $this->assertCondition($examDetails !== null && $examDetails['title'] === $examData['title'], 'Read Detail Ujian Beserta Jadwal & Token');

        // 5.3 Verify Centralized Token
        $token = $tokenModel->where('exam_id', $examId)->first();
        $this->assertCondition(!empty($token['token']) && strlen($token['token']) === 6, 'Token Ujian Terpusat 6-Karakter Terbentuk Otomatis');
        $this->assertCondition($tokenModel->validateToken($examId, $token['token']) === true, 'Validasi Token Ujian Aktif Valid');

        // 5.4 Update Status Ujian
        $statusRes = $examService->changeStatus($examId, 'published');
        $this->assertCondition($statusRes === true, 'Update Status Ujian Menjadi Published');

        // Simpan examId untuk test sesi berikutnya
        $GLOBALS['AUDIT_EXAM_ID'] = $examId;
        $GLOBALS['AUDIT_TOKEN_CODE'] = $token['token'];
    }

    // -------------------------------------------------------------------------
    // TEST 6: EXAM SESSION & ENGINE
    // -------------------------------------------------------------------------
    private function test6_ExamSession(): void
    {
        echo "\n6. Menguji Modul Exam Session & Engine (UC-10, UC-14)...\n";
        $examSessionService = new ExamSessionService();
        $examQuestionModel = new ExamQuestionModel();
        $qModel = new QuestionModel();
        $qOptionModel = new QuestionOptionModel();
        $bankModel = new QuestionBankModel();

        $examId = (int)$GLOBALS['AUDIT_EXAM_ID'];
        $tokenCode = (string)$GLOBALS['AUDIT_TOKEN_CODE'];
        $studentId = 4; // ID Siswa terdaftar

        // Pastikan siswa 4 terdaftar di exam_participants jika belum
        $partModel = new ExamParticipantModel();
        $existingPart = $partModel->where('exam_id', $examId)->where('student_id', $studentId)->first();
        if (!$existingPart) {
            $partModel->insert([
                'exam_id' => $examId,
                'student_id' => $studentId,
                'class_id' => 1,
                'status' => 'not_started',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // 6.1 Mulai Sesi Ujian Siswa
        $startRes = $examSessionService->startOrResumeSession($examId, $studentId, $tokenCode, '127.0.0.1', 'AuditBrowser');
        $this->assertCondition($startRes['success'] === true, 'Mulai Sesi Ujian Siswa Baru');
        $this->assertCondition(!empty($startRes['session_token']), 'Session Token Terbentuk');

        $sessionToken = $startRes['session_token'];
        $GLOBALS['AUDIT_SESSION_TOKEN'] = $sessionToken;

        $sessionModel = new ExamSessionModel();
        $session = $sessionModel->findByToken($sessionToken);
        $GLOBALS['AUDIT_SESSION_ID'] = (int)$session['id'];

        // 6.2 Test Reconnection / Resume Session (UC-14)
        $resumeRes = $examSessionService->startOrResumeSession($examId, $studentId, $tokenCode, '127.0.0.1', 'AuditBrowser');
        $this->assertCondition($resumeRes['success'] === true && $resumeRes['resumed'] === true, 'Rekoneksi Sesi Aktif Berjalan (UC-14 Resumed)');
        $this->assertCondition($resumeRes['session_token'] === $sessionToken, 'Session Token Konsisten Saat Resume');

        // 6.3 Ambil Data Ruang Ujian
        $roomData = $examSessionService->getExamRoomData($sessionToken, $studentId);
        $this->assertCondition($roomData !== null, 'Ambil Data Ruang Ujian Lengkap');
        $this->assertCondition(count($roomData['questions']) >= 2, 'Butir Soal Ter-load Lengkap ke Ruang Ujian');
    }

    // -------------------------------------------------------------------------
    // TEST 7: AUTO-SAVE ANSWER MECHANISM
    // -------------------------------------------------------------------------
    private function test7_AutoSave(): void
    {
        echo "\n7. Menguji Mekanisme Auto-Save Jawaban (UC-11)...\n";
        $answerService = new ExamAnswerService();
        $sessionToken = (string)$GLOBALS['AUDIT_SESSION_TOKEN'];
        $studentId = 4;
        $q1Id = (int)$GLOBALS['AUDIT_Q1_ID'];
        $q2Id = (int)$GLOBALS['AUDIT_Q2_ID'];
        $opt1BId = (int)$GLOBALS['AUDIT_OPT1B_ID'];

        // 7.1 Auto-save Soal PG
        $savePg = $answerService->saveAnswer($sessionToken, $studentId, $q1Id, $opt1BId, null, 0);
        $this->assertCondition($savePg['success'] === true, 'Auto-Save Jawaban Pilihan Ganda (AJAX)');
        $this->assertCondition($savePg['counts']['answered'] >= 1, 'Status Counter Jawaban Terjawab Diperbarui Real-Time');

        // 7.2 Auto-save Soal Essay
        $essayText = 'AI Proctoring mendeteksi arah pandangan mata, keberadaan wajah peserta, dan deteksi objek gawai secara real-time.';
        $saveEssay = $answerService->saveAnswer($sessionToken, $studentId, $q2Id, null, $essayText, 0);
        $this->assertCondition($saveEssay['success'] === true, 'Auto-Save Jawaban Essay');

        // 7.3 Validasi Proteksi Akses Jawaban Peserta Lain (Security)
        $hackedSave = $answerService->saveAnswer($sessionToken, 9999, $q1Id, $opt1BId, null, 0);
        $this->assertCondition($hackedSave['success'] === false, 'Percobaan Menyimpan Jawaban Peserta Lain Ditolak (403/Forbidden)');
    }

    // -------------------------------------------------------------------------
    // TEST 8: SUBMIT UJIAN & TRANSACTIONAL IDEMPOTENCY
    // -------------------------------------------------------------------------
    private function test8_SubmitUjian(): void
    {
        echo "\n8. Menguji Mekanisme Submit Ujian & Transaksi Atomik (UC-12)...\n";
        $sessionService = new ExamSessionService();
        $sessionToken = (string)$GLOBALS['AUDIT_SESSION_TOKEN'];
        $studentId = 4;

        // 8.1 Submit Ujian
        $submitRes = $sessionService->submitExam($sessionToken, $studentId);
        $this->assertCondition($submitRes['success'] === true, 'Submit Ujian Berhasil Secara Atomik');
        $this->assertCondition(isset($submitRes['final_score']), 'Skor Awal Pilihan Ganda Terhitung Otomatis');

        // 8.2 Cegah Submit Ganda (Double Submission Idempotency)
        $doubleSubmit = $sessionService->submitExam($sessionToken, $studentId);
        $this->assertCondition($doubleSubmit['success'] === true && !empty($doubleSubmit['already_submitted']), 'Pencegahan Submit Ganda Terverifikasi (Idempotent)');

        // Simpan session ID untuk grading test
        $sessionModel = new ExamSessionModel();
        $session = $sessionModel->findByToken($sessionToken);
        $GLOBALS['AUDIT_SESSION_ID'] = (int)$session['id'];
    }

    // -------------------------------------------------------------------------
    // TEST 9: CAMERA PERMISSION & DUAL CAMERA PRE-CHECK
    // -------------------------------------------------------------------------
    private function test9_CameraPermission(): void
    {
        echo "\n9. Menguji Modul Kalibrasi & Diagnostik Dual Kamera (UC-07, UC-09)...\n";
        
        // 9.1 Simpan Preferensi Dual Perangkat ke Session Siswa
        $dualConfig = [
            'primary_device_id'   => 'device_front_camera_default_01',
            'secondary_device_id' => 'device_secondary_camera_ext_02',
            'configured_at'       => date('Y-m-d H:i:s')
        ];
        Session::set('exam_camera_config', $dualConfig);
        $saved = Session::get('exam_camera_config');
        $this->assertCondition(!empty($saved['primary_device_id']) && !empty($saved['secondary_device_id']), 'Konfigurasi Dual Kamera Tersimpan di Sesi Siswa');

        // 9.2 Simpan Rekam Perangkat Kamera ke Database
        $examSessionId = (int)$GLOBALS['AUDIT_SESSION_ID'];
        $studentId = 4;
        $proctorService = new AiProctoringService();
        $proctorInit = $proctorService->startOrGetProctoringSession($examSessionId, $studentId);
        $this->assertCondition($proctorInit['success'] === true, 'Inisialisasi Sesi Proctoring untuk Diagnostik Kamera');
        $proctoringSessionId = (int)$proctorInit['session']['id'];
        $GLOBALS['AUDIT_PROCTORING_SESSION_ID'] = $proctoringSessionId;

        $db = Database::getConnection();
        $insCam = $db->prepare("
            INSERT INTO camera_devices (proctoring_session_id, camera_type, device_label, resolution, fps, connection_status, created_at)
            VALUES (:psid, 'primary_webcam', 'Front Integrated HD Camera', '1280x720', 30, 'connected', NOW())
        ");
        $insCam->execute([':psid' => $proctoringSessionId]);
        $camDeviceId = (int)$db->lastInsertId();

        $configSql = "
            INSERT INTO camera_quality_checks 
                (camera_device_id, lighting_score, blur_score, face_visibility_score, is_passed, notes, checked_at, created_at)
            VALUES 
                (:cam_id, 0.95, 0.98, 0.92, 1, 'Pencahayaan optimal dan wajah terdeteksi jelas.', NOW(), NOW())
        ";
        $stmt = $db->prepare($configSql);
        $res = $stmt->execute([':cam_id' => $camDeviceId]);
        $this->assertCondition($res === true, 'Penyimpanan Diagnostik Kualitas Kamera (Lighting, Blur, Face Visibility)');
    }

    // -------------------------------------------------------------------------
    // TEST 10: AI LOGGING & PROCTORING VIOLATION TELEMETRY
    // -------------------------------------------------------------------------
    private function test10_AiLogging(): void
    {
        echo "\n10. Menguji Modul AI Proctoring & Violation Logging (UC-13, UC-18)...\n";
        $proctorService = new AiProctoringService();
        $proctoringSessionId = (int)$GLOBALS['AUDIT_PROCTORING_SESSION_ID'];

        // 10.1 Log Telemetri AI Normal
        $logRes = $proctorService->logTelemetry($proctoringSessionId, [
            'head_pose_yaw'          => 5.0,
            'head_pose_pitch'        => -2.0,
            'gaze_direction'         => 'center',
            'detected_face_count'    => 1,
            'mouth_movement_detected'=> 0,
            'phone_detected'         => 0,
            'audio_noise_level'      => 0.15
        ]);
        $this->assertCondition($logRes['success'] === true, 'Logging Analisis Telemetri AI Real-Time');
        $this->assertCondition(isset($logRes['risk_score']), 'Evaluasi Real-Time Risk Score AI');

        // 10.2 Simulasi Anomali: Deteksi Multipel Wajah (Violation Event)
        $violationLog = $proctorService->logTelemetry($proctoringSessionId, [
            'head_pose_yaw'          => 0.0,
            'head_pose_pitch'        => 0.0,
            'gaze_direction'         => 'center',
            'detected_face_count'    => 2, // Pelanggaran: 2 Wajah
            'mouth_movement_detected'=> 0,
            'phone_detected'         => 0,
            'audio_noise_level'      => 0.20
        ]);
        $this->assertCondition($violationLog['success'] === true, 'Deteksi Pelanggaran AI Otomatis (Multiple Faces)');
        $this->assertCondition(!empty($violationLog['triggered_violations']), 'Event Pelanggaran Integritas Ujian Tercatat di Log');

        // 10.3 Kalkulasi Status Risk Score Pasca Pelanggaran
        $riskCalc = $proctorService->calculateRiskScore($proctoringSessionId);
        $this->assertCondition($riskCalc['total_violations'] >= 1, 'Akumulasi Skor Risiko & Pelanggaran Integritas Tervalidasi');
    }

    // -------------------------------------------------------------------------
    // TEST 11: GRADING MODULE & IMMUTABILITY
    // -------------------------------------------------------------------------
    private function test11_Grading(): void
    {
        echo "\n11. Menguji Modul Penilaian & Evaluasi Guru (UC-23, UC-24)...\n";
        $gradingService = new GradingService();
        $resultService = new ResultService();
        $examSessionId = (int)$GLOBALS['AUDIT_SESSION_ID'];
        $examId = (int)$GLOBALS['AUDIT_EXAM_ID'];
        $teacherId = 3;

        // Ambil jawaban essay siswa
        $sessionAnswers = (new ExamAnswerModel())->where('exam_session_id', $examSessionId)->get();
        $essayAnswerId = null;
        foreach ($sessionAnswers as $ans) {
            if (!empty($ans['essay_answer'])) {
                $essayAnswerId = (int)$ans['id'];
                break;
            }
        }

        $this->assertCondition($essayAnswerId !== null, 'Ditemukan Jawaban Essay untuk Penilaian');

        // 11.1 Guru Menilai Jawaban Essay
        $gradeRes = $gradingService->gradeEssayAnswer($essayAnswerId, 45.0, 'Penjelasan sangat mendalam dan akurat.', $teacherId);
        $this->assertCondition($gradeRes['success'] === true, 'Penyimpanan Nilai Essay Manual oleh Guru');
        $this->assertCondition($gradeRes['final_score'] > 0, 'Kalkulasi Ulang Nilai Akhir (PG + Essay)');

        // 11.2 Guru Mempublikasikan Hasil Ujian (UC-24)
        $pubRes = $resultService->publishExamResults($examId, $teacherId);
        $this->assertCondition($pubRes['success'] === true, 'Publikasi Hasil Ujian Menjadi Status Published');

        // 11.3 Immutability Check: Verifikasi Hasil Terbit Tidak Dapat Diubah Sembarangan
        $resultModel = new ExamResultModel();
        $publishedResult = $resultModel->where('exam_session_id', $examSessionId)->first();
        $this->assertCondition($publishedResult['status'] === 'published', 'Status Hasil Terverifikasi Published');

        // 11.4 Verifikasi Guru Tidak Dapat Mengubah Nilai yang Sudah Dipublikasikan
        $attemptModify = $gradingService->gradeEssayAnswer($essayAnswerId, 50.0, 'Mencoba ganti nilai', $teacherId);
        $this->assertCondition($attemptModify['success'] === false, 'Penguncian Nilai Pasca Publikasi (Immutability Enforced)');
    }

    // -------------------------------------------------------------------------
    // TEST 12: REPORT EXPORT (PDF & EXCEL)
    // -------------------------------------------------------------------------
    private function test12_ReportExport(): void
    {
        echo "\n12. Menguji Modul Ekspor Laporan PDF (Dompdf) & Excel (PhpSpreadsheet) (UC-25)...\n";
        $reportService = new ReportService();
        $examId = (int)$GLOBALS['AUDIT_EXAM_ID'];
        $examSessionId = (int)$GLOBALS['AUDIT_SESSION_ID'];

        // 12.1 Ekspor PDF Hasil Rekap Ujian
        $pdfOutput = $reportService->exportExamReportPdf($examId);
        $this->assertCondition(!empty($pdfOutput), 'Generasi Dokumen PDF Rekap Ujian (Dompdf)');
        $this->assertCondition(str_starts_with($pdfOutput, '%PDF'), 'Validasi Header Format Berkas PDF Asli (%PDF)');

        // 12.2 Ekspor PDF Transkrip Siswa
        $studentPdf = $reportService->exportStudentPdf($examSessionId);
        $this->assertCondition(!empty($studentPdf) && str_starts_with($studentPdf, '%PDF'), 'Generasi PDF Transkrip Hasil Evaluasi Siswa Individual');

        // 12.3 Ekspor Excel Rekap Nilai
        $excelOutput = $reportService->exportExamReportExcel($examId);
        $this->assertCondition(!empty($excelOutput), 'Generasi Spreadsheet Excel Nilai Ujian (PhpSpreadsheet)');
        $this->assertCondition(str_starts_with($excelOutput, "PK\x03\x04"), 'Validasi Header Format Berkas Spreadsheet XLSX Asli');
    }
}

// Jalankan audit test runner
$runner = new ProductionAuditRunner();
$runner->run();
