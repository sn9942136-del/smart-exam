<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * CLEAN FRESH INSTALLATION & COMPREHENSIVE END-TO-END FLOW SIMULATOR
 * ==============================================================================
 * Mensimulasikan:
 * 1. Fresh database & tables clean
 * 2. Migration execution
 * 3. Seeder execution
 * 4. Application startup
 * 5. Login Admin
 * 6. Login Guru
 * 7. Login Siswa
 * 8. Akses dashboard masing-masing role
 * 9. Akses/buat exam
 * 10. Jalankan minimal satu exam flow lengkap (start -> answer -> telemetry -> submit -> result)
 * 11. Cek keterhubungan seluruh relasi data seeder
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Config\App;
use App\Config\Database as DbConfig;
use App\Libraries\Database;
use App\Libraries\Session;
use App\Models\AuditLogModel;
use App\Models\ClassModel;
use App\Models\ExamAnswerModel;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamQuestionModel;
use App\Models\ExamResultModel;
use App\Models\ExamRuleModel;
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
use App\Services\ExamSessionService;
use App\Services\GradingService;
use App\Services\ResultService;
use Database\Seeders\DatabaseSeeder;

echo "\n";
echo "================================================================================\n";
echo "    SIMULASI CLEAN FRESH INSTALLATION & USER FLOWS SECARA KOMPREHENSIF         \n";
echo "================================================================================\n";
echo "Waktu Uji : " . date('Y-m-d H:i:s') . "\n";
echo "PHP Ver   : " . PHP_VERSION . "\n";
echo "--------------------------------------------------------------------------------\n\n";

$passCount = 0;
$failCount = 0;

function reportStep(bool $status, string $stepName, string $detail = ''): void {
    global $passCount, $failCount;
    if ($status) {
        $passCount++;
        echo "  ✓ [PASS] {$stepName}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $failCount++;
        echo "  ✗ [FAIL] {$stepName}" . ($detail ? " ({$detail})" : "") . "\n";
    }
}

try {
    // -------------------------------------------------------------------------
    // STEP 1 & 2: FRESH DATABASE & MIGRATION EXECUTION
    // -------------------------------------------------------------------------
    echo "[TAHAP 1 & 2] Reset Database & Eksekusi Migrasi SQL...\n";
    DbConfig::load();
    $dbHost = DbConfig::$host;
    $dbPort = DbConfig::$port;
    $dbName = DbConfig::$database;
    $dbUser = DbConfig::$username;
    $dbPass = DbConfig::$password;
    $dbCharset = DbConfig::$charset;

    $rawPdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset={$dbCharset}", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$dbCharset} COLLATE {$dbCharset}_unicode_ci");
    reportStep(true, "Database Server Connected & DB `{$dbName}` Ready");

    $db = Database::getConnection();
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $existingTables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existingTables as $tbl) {
        $db->exec("DROP TABLE IF EXISTS `{$tbl}`;");
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    reportStep(true, "Fresh DB Reset Clean (Semua tabel lama dibersihkan)");

    // Jalankan Berkas Migrasi
    $migrationsDir = __DIR__ . '/../database/migrations';
    $files = glob($migrationsDir . '/*.sql');
    sort($files, SORT_NATURAL);

    $db->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $migratedCount = 0;
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if (!empty(trim($sql))) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $db->exec($sql);
            $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $stmt = $db->prepare("INSERT INTO migrations (migration, batch, executed_at) VALUES (:m, 1, NOW())");
            $stmt->execute([':m' => basename($file)]);
            $migratedCount++;
        }
    }
    reportStep($migratedCount === count($files), "Eksekusi Seluruh Berkas Migrasi SQL", "{$migratedCount} berkas migrasi terpasang");

    $tables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    reportStep(count($tables) >= 36, "Verifikasi Jumlah Tabel Database", count($tables) . " tabel terverifikasi");

    // -------------------------------------------------------------------------
    // STEP 3: SEEDER EXECUTION
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 3] Menjalankan Database Seeder Lengkap...\n";
    require_once __DIR__ . '/../database/seeders/DatabaseSeeder.php';
    DatabaseSeeder::run();
    reportStep(true, "DatabaseSeeder Berhasil Dieksekusi 100%");

    // -------------------------------------------------------------------------
    // STEP 4: APPLICATION STARTUP & CONFIG INTEGRITY
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 4] Verifikasi Application Startup...\n";
    Session::start();
    $appName = App::$appName;
    $appUrl = App::$appUrl;
    reportStep(!empty($appName) && !empty($appUrl), "Bootstrap & Konfigurasi Global App", "App: {$appName}");

    // -------------------------------------------------------------------------
    // STEP 5: LOGIN ADMIN
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 5] Simulasi Login Admin...\n";
    $auth = new AuthService();
    $adminLogin = $auth->attempt('admin@example.com', 'Admin123!');
    reportStep(
        $adminLogin['success'] === true && $adminLogin['user']['role'] === 'admin',
        "Login Admin Berhasil & Role Terverifikasi",
        "Email: admin@example.com | Role: admin"
    );
    reportStep(has_role('admin') === true, "Session Admin Aktif Global");
    $auth->logout();

    // -------------------------------------------------------------------------
    // STEP 6: LOGIN GURU
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 6] Simulasi Login Guru...\n";
    $guruLogin = $auth->attempt('guru@example.com', 'Guru123!');
    reportStep(
        $guruLogin['success'] === true && $guruLogin['user']['role'] === 'guru',
        "Login Guru Berhasil & Role Terverifikasi",
        "Nama: " . ($guruLogin['user']['name'] ?? 'Guru') . " | Role: guru"
    );
    reportStep(has_role('guru') === true, "Session Guru Aktif Global");
    $auth->logout();

    // -------------------------------------------------------------------------
    // STEP 7: LOGIN SISWA
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 7] Simulasi Login Siswa...\n";
    $siswaLogin = $auth->attempt('siswa@example.com', 'Siswa123!');
    reportStep(
        $siswaLogin['success'] === true && $siswaLogin['user']['role'] === 'peserta_didik',
        "Login Siswa Berhasil & Role Terverifikasi",
        "Nama: " . ($siswaLogin['user']['name'] ?? 'Siswa') . " | Role: peserta_didik"
    );
    reportStep(has_role('peserta_didik') === true, "Session Siswa Aktif Global");
    $studentId = (int)$siswaLogin['user']['id'];
    $auth->logout();

    // -------------------------------------------------------------------------
    // STEP 8: AKSES DASHBOARD MASING-MASING ROLE
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 8] Simulasi Akses & Agregasi Data Dashboard Role...\n";
    
    // 8.1 Admin Dashboard Data
    $userModel = new UserModel();
    $examModel = new ExamModel();
    $totalUsers = $userModel->count();
    $examStats = $examModel->getSummaryStats();
    reportStep($totalUsers >= 4, "Akses Dashboard Admin: Agregasi Pengguna", "Total Users: {$totalUsers}");
    reportStep(isset($examStats['total']), "Akses Dashboard Admin: Metrik Ujian", "Total Ujian: {$examStats['total']}");

    // 8.2 Guru Dashboard Data
    $bankModel = new QuestionBankModel();
    $teacherBanks = $bankModel->where('creator_id', 3)->get();
    reportStep(count($teacherBanks) >= 1, "Akses Dashboard Guru: Bank Soal Pengajar", count($teacherBanks) . " bank soal terdaftar");

    // 8.3 Siswa Dashboard Data
    $partModel = new ExamParticipantModel();
    $studentExams = $partModel->getAvailableExamsForStudent($studentId);
    reportStep(count($studentExams) >= 1, "Akses Dashboard Siswa: Daftar Ujian Tersedia", count($studentExams) . " ujian terdaftar untuk siswa");

    // -------------------------------------------------------------------------
    // STEP 9: AKSES PAKET UJIAN SEEDER & TOKEN
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 9] Akses Paket Ujian & Token Demo...\n";
    $demoExam = $examModel->where('code', 'EXAM-MAT12-UAS')->first();
    reportStep($demoExam !== null, "Paket Ujian Demo Ditemukan", "Kode: EXAM-MAT12-UAS | Judul: {$demoExam['title']}");
    $demoExamId = (int)$demoExam['id'];

    $tokenModel = new ExamTokenModel();
    $demoToken = $tokenModel->where('exam_id', $demoExamId)->where('is_active', 1)->first();
    $tokenCode = $demoToken['token'] ?? $demoToken['token_code'] ?? '';
    reportStep(!empty($tokenCode) && $tokenCode === 'UAS2026', "Token Ujian Aktif Terverifikasi", "Token: {$tokenCode}");

    // -------------------------------------------------------------------------
    // STEP 10: JALANKAN SATU SIKLUS EXAM FLOW LENGKAP
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 10] Menjalankan Siklus Lengkap CBT Exam Siswa...\n";
    $sessionService = new ExamSessionService();
    $answerService = new ExamAnswerService();
    $proctorService = new AiProctoringService();

    // 10.1 Siswa Memulai Sesi Ujian (Start Session)
    $startRes = $sessionService->startOrResumeSession($demoExamId, $studentId, $tokenCode, '127.0.0.1', 'SimulatorClient');
    reportStep($startRes['success'] === true && !empty($startRes['session_token']), "10.1 Mulai Sesi Ujian CBT", "Session Token: " . substr($startRes['session_token'] ?? '', 0, 16) . "...");
    $sessionToken = $startRes['session_token'];

    // Dapatkan ID Sesi
    $sessionRow = (new ExamSessionModel())->findByToken($sessionToken);
    $examSessionId = (int)$sessionRow['id'];

    // 10.2 Ambil Soal di Ruang Ujian
    $roomData = $sessionService->getExamRoomData($sessionToken, $studentId);
    $questions = $roomData['questions'] ?? [];
    reportStep(count($questions) === 6, "10.2 Load Butir Soal ke Ruang Ujian", count($questions) . " butir soal termuat (5 PG + 1 Esai)");

    // 10.3 Inisialisasi Sesi Dual Camera Proctoring
    $procInit = $proctorService->startOrGetProctoringSession($examSessionId, $studentId);
    reportStep($procInit['success'] === true, "10.3 Inisialisasi Dual Camera AI Proctoring", "Proctoring Session ID: {$procInit['session']['id']}");
    $proctoringSessionId = (int)$procInit['session']['id'];

    // 10.4 Simpan Jawaban Siswa (Autosave PG & Esai)
    $mcQuestion = null;
    $essayQuestion = null;
    foreach ($questions as $q) {
        $type = $q['question_type'] ?? $q['type'] ?? '';
        if ($type === 'multiple_choice' && $mcQuestion === null) {
            $mcQuestion = $q;
        }
        if ($type === 'essay' && $essayQuestion === null) {
            $essayQuestion = $q;
        }
    }

    $savedCount = 0;
    if ($mcQuestion) {
        $firstOpt = reset($mcQuestion['options']);
        $qId = (int)($mcQuestion['question_id'] ?? $mcQuestion['id']);
        $save1 = $answerService->saveAnswer($sessionToken, $studentId, $qId, (int)$firstOpt['id'], null, 0);
        if ($save1['success']) $savedCount++;
    }
    if ($essayQuestion) {
        $essayText = 'Turunan kedua digunakan untuk menganalisis kecekungan kurva dan titik belok fungsi.';
        $qId = (int)($essayQuestion['question_id'] ?? $essayQuestion['id']);
        $save2 = $answerService->saveAnswer($sessionToken, $studentId, $qId, null, $essayText, 0);
        if ($save2['success']) $savedCount++;
    }
    reportStep($savedCount === 2, "10.4 Auto-Save Jawaban Pilihan Ganda & Esai via AJAX", "2 jawaban berhasil tersimpan secara real-time");

    // 10.5 Kirim Telemetri AI Real-Time
    $telemRes = $proctorService->logTelemetry($proctoringSessionId, [
        'head_pose_yaw'          => 2.0,
        'head_pose_pitch'        => -1.0,
        'gaze_direction'         => 'center',
        'detected_face_count'    => 1,
        'mouth_movement_detected'=> 0,
        'phone_detected'         => 0,
        'audio_noise_level'      => 0.12
    ]);
    reportStep($telemRes['success'] === true, "10.5 Pengiriman Telemetri AI Real-Time", "Risk Score: " . ($telemRes['risk_score'] ?? 0));

    // 10.6 Submit Selesai Ujian
    $submitRes = $sessionService->submitExam($sessionToken, $studentId);
    reportStep($submitRes['success'] === true, "10.6 Submit Ujian Akhir oleh Siswa", "Status Sesi: submitted");

    // 10.7 Validasi Immutability Pasca Submit
    $afterSubmitSave = $answerService->saveAnswer($sessionToken, $studentId, (int)$mcQuestion['id'], (int)$firstOpt['id'], null, 0);
    reportStep($afterSubmitSave['success'] === false, "10.7 Validasi Penguncian Sesi (Anti-Tampering Pasca Submit)");

    // -------------------------------------------------------------------------
    // STEP 11: CEK INTEGRITAS KETERHUBUNGAN RELASI DATA SEEDER
    // -------------------------------------------------------------------------
    echo "\n[TAHAP 11] Verifikasi Integritas & Keterhubungan Relasi Data Seeder...\n";
    
    // 11.1 Subjek & Guru
    $subTeacher = $db->query("SELECT COUNT(*) FROM teacher_subjects ts JOIN subjects s ON ts.subject_id = s.id JOIN users u ON ts.teacher_id = u.id")->fetchColumn();
    reportStep($subTeacher > 0, "Relasi Guru -> Mata Pelajaran Terhubung");

    // 11.2 Kelas & Siswa
    $classStudent = $db->query("SELECT COUNT(*) FROM class_students cs JOIN classes c ON cs.class_id = c.id JOIN users u ON cs.student_id = u.id")->fetchColumn();
    reportStep($classStudent > 0, "Relasi Siswa -> Kelas XII MIPA 1 Terhubung");

    // 11.3 Bank Soal & Butir Soal & Opsi
    $bankRow = $db->query("SELECT id FROM question_banks WHERE code = 'BNK-MAT12-UAS'")->fetch();
    $bankBankId = (int)($bankRow['id'] ?? 1);
    $bankQuestionOpts = $db->query("
        SELECT COUNT(DISTINCT q.id) as q_count, COUNT(qo.id) as opt_count 
        FROM questions q 
        LEFT JOIN question_options qo ON qo.question_id = q.id 
        WHERE q.question_bank_id = {$bankBankId}
    ")->fetch();
    reportStep((int)$bankQuestionOpts['q_count'] === 6 && (int)$bankQuestionOpts['opt_count'] >= 20, "Relasi Bank Soal -> 6 Soal & 20 Opsi Terhubung");

    // 11.4 Ujian, Aturan, Jadwal, Token, & Peserta
    $examRel = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM exam_rules WHERE exam_id = {$demoExamId}) as rule_cnt,
            (SELECT COUNT(*) FROM exam_schedules WHERE exam_id = {$demoExamId}) as sched_cnt,
            (SELECT COUNT(*) FROM exam_tokens WHERE exam_id = {$demoExamId}) as tok_cnt,
            (SELECT COUNT(*) FROM exam_participants WHERE exam_id = {$demoExamId}) as part_cnt
    ")->fetch();
    reportStep(
        (int)$examRel['rule_cnt'] >= 1 && (int)$examRel['sched_cnt'] >= 1 && (int)$examRel['tok_cnt'] >= 1 && (int)$examRel['part_cnt'] >= 1,
        "Relasi Ujian -> Rules, Schedules, Tokens, & Participants Terhubung Utuh"
    );

    echo "\n================================================================================\n";
    echo "                              RINGKASAN SIMULASI                                \n";
    echo "================================================================================\n";
    echo "Total Tahap Pengujian : " . ($passCount + $failCount) . "\n";
    echo "Berhasil (PASS)       : {$passCount}\n";
    echo "Gagal (FAIL)          : {$failCount}\n";
    echo "Tingkat Keberhasilan  : " . round(($passCount / max(1, $passCount + $failCount)) * 100, 2) . "%\n";

    if ($failCount > 0) {
        echo "\nSTATUS: TERDAPAT KEGAGALAN DALAM SIMULASI FRESH INSTALLATION!\n";
        exit(1);
    } else {
        echo "\nSTATUS: SELURUH 11 ITEM SIMULASI FRESH INSTALLATION & E2E FLOWS SUKSES 100%!\n";
        echo "================================================================================\n\n";
    }

} catch (\Throwable $e) {
    echo "\n✗ FATAL EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
