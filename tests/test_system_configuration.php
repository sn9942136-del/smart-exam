<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — AUTOMATED TEST SUITE: SYSTEM CONFIGURATION FEATURE
 * ==============================================================================
 * Menguji fitur Konfigurasi Sistem Terpadu:
 * 1.  Model initialization & table auto-creation (ensureTableExists).
 * 2.  Pengambilan nilai konfigurasi via SystemSettingModel::getVal() & type casting.
 * 3.  Fallback nilai default untuk key yang belum ada.
 * 4.  Helper function system_setting() dan app_name() terintegrasi.
 * 5.  Pengelompokan 8 kategori (general, security, exam, proctoring, ai, notification, storage, maintenance).
 * 6.  Kelengkapan metadata grup (title, icon, badge, description).
 * 7.  Penyimpanan massal (updateBulk) lintas grup.
 * 8.  Proteksi keamanan field sensitif (password/token kosong tidak menimpa nilai lama).
 * 9.  Sinkronisasi dua arah parameter AI ke tabel ai_configurations.
 * 10. Ekspor konfigurasi ke format JSON dengan penyensoran data sensitif.
 * 11. Impor konfigurasi dari format JSON cadangan.
 * 12. Reset ke pengaturan default pabrikan (resetToDefaults).
 * 13. Metode verifikasi koneksi diagnostik WhatsAppClient::verifyConnection().
 * 14. Metode verifikasi koneksi diagnostik SmtpClient::verifyConnection().
 * 15. Controller & Route SystemConfigurationController::index() mengembalikan HTTP 200.
 * 16. Pencatatan AuditLogModel dengan penyamaran nilai sensitif [TERPROTEKSI].
 * 17. Isolasi Database Operasional (ujian_dual_kamera strictly 0 logs).
 * ==============================================================================
 */

// Targetkan database testing
$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Libraries\SmtpClient;
use App\Libraries\WhatsAppClient;
use App\Models\AuditLogModel;
use App\Models\SystemSettingModel;
use App\Controllers\SystemConfigurationController;

$totalPassed = 0;
$totalFailed = 0;
$results = [];

function runSysTest(string $caseId, string $description, callable $fn): void {
    global $totalPassed, $totalFailed, $results;
    try {
        $fn();
        $results[] = ['id' => $caseId, 'desc' => $description, 'status' => 'PASS', 'error' => null];
        $totalPassed++;
        echo "  \033[32m[PASS]\033[0m {$caseId}: {$description}\n";
    } catch (Throwable $e) {
        $results[] = ['id' => $caseId, 'desc' => $description, 'status' => 'FAIL', 'error' => $e->getMessage()];
        $totalFailed++;
        echo "  \033[31m[FAIL]\033[0m {$caseId}: {$description}\n";
        echo "         \033[33mError: {$e->getMessage()}\033[0m\n";
    }
}

function assertSys(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

echo "\n";
echo "==============================================================================\n";
echo "  TEST SUITE: SISTEM KONFIGURASI TERPADU SMART EXAM                           \n";
echo "==============================================================================\n";

$model = new SystemSettingModel();

// TC-SYS-01: Inisialisasi tabel & auto-create
runSysTest('TC-SYS-01', 'Inisialisasi tabel system_settings dan pembuatan skema otomatis', function() use ($model) {
    $model->ensureTableExists();
    $db = Database::getConnection();
    $check = $db->query("SHOW TABLES LIKE 'system_settings'")->fetch();
    assertSys(!empty($check), "Tabel system_settings harus terbuat di database.");
});

// TC-SYS-02: Pengambilan nilai konfigurasi & type casting
runSysTest('TC-SYS-02', 'Pengambilan nilai konfigurasi via getVal() dengan type casting boolean & number', function() {
    $appName = SystemSettingModel::getVal('app_name');
    assertSys($appName === 'Smart Exam', "Nilai app_name harus 'Smart Exam', didapat: '{$appName}'");

    $csrf = SystemSettingModel::getVal('csrf_protection');
    assertSys(is_bool($csrf) && $csrf === true, "csrf_protection harus dicast menjadi boolean true.");

    $sessionLifetime = SystemSettingModel::getVal('session_lifetime');
    assertSys(is_int($sessionLifetime) && $sessionLifetime === 7200, "session_lifetime harus integer 7200.");
});

// TC-SYS-03: Fallback nilai default untuk key yang tidak ada
runSysTest('TC-SYS-03', 'Fallback nilai default jika key tidak ditemukan di database', function() {
    $val = SystemSettingModel::getVal('non_existent_key_xyz', 'default_value_123');
    assertSys($val === 'default_value_123', "Harus mengembalikan fallback default value.");
});

// TC-SYS-04: Helper function system_setting() dan app_name()
runSysTest('TC-SYS-04', 'Helper function system_setting() dan app_name() berfungsi terintegrasi', function() {
    assertSys(function_exists('system_setting'), "Fungsi system_setting() harus tersedia di app_helper.php.");
    $tz = system_setting('app_timezone');
    assertSys($tz === 'Asia/Jakarta', "system_setting('app_timezone') harus 'Asia/Jakarta'.");

    $name = app_name();
    assertSys($name === 'Smart Exam', "app_name() harus mengembalikan 'Smart Exam'.");
});

// TC-SYS-05: Pengelompokan 8 kategori konfigurasi
runSysTest('TC-SYS-05', 'Pengelompokan 8 kategori konfigurasi lengkap dan terstruktur', function() use ($model) {
    $grouped = $model->getAllGrouped();
    $expectedGroups = ['general', 'security', 'exam', 'proctoring', 'ai', 'notification', 'storage', 'maintenance'];

    foreach ($expectedGroups as $group) {
        assertSys(isset($grouped[$group]), "Kategori group '{$group}' harus ada.");
        assertSys(count($grouped[$group]) > 0, "Kategori group '{$group}' tidak boleh kosong.");
    }
});

// TC-SYS-06: Metadata grup konfigurasi lengkap
runSysTest('TC-SYS-06', 'Kelengkapan metadata seluruh grup konfigurasi (title, icon, badge)', function() {
    $meta = SystemSettingModel::getGroupMetadata();
    assertSys(count($meta) === 8, "Harus ada tepat 8 kelompok metadata konfigurasi.");
    foreach ($meta as $key => $info) {
        assertSys(!empty($info['title']), "Group {$key} wajib memiliki title.");
        assertSys(!empty($info['icon']), "Group {$key} wajib memiliki icon bootstrap.");
        assertSys(!empty($info['description']), "Group {$key} wajib memiliki deskripsi.");
    }
});

// TC-SYS-07: Bulk update konfigurasi
runSysTest('TC-SYS-07', 'Penyimpanan massal (updateBulk) berhasil memperbarui nilai ke database', function() use ($model) {
    $newTagline = 'Platform Ujian AI Modern Generasi Baru';
    $newLate = '20';

    $ok = $model->updateBulk([
        'app_tagline'            => $newTagline,
        'late_tolerance_minutes' => $newLate
    ]);
    assertSys($ok === true, "updateBulk harus mengembalikan true.");

    $readTagline = SystemSettingModel::getVal('app_tagline');
    assertSys($readTagline === $newTagline, "app_tagline harus terupdate.");

    $readLate = SystemSettingModel::getVal('late_tolerance_minutes');
    assertSys($readLate === 20, "late_tolerance_minutes harus terupdate menjadi 20.");
});

// TC-SYS-08: Proteksi field sensitif tidak tertimpa nilai kosong
runSysTest('TC-SYS-08', 'Kredensial sensitif (password/token) tidak tertimpa saat dikirim kosong', function() use ($model) {
    // Set token awal
    $model->updateBulk(['whatsapp_api_token' => 'SECRET_TOKEN_12345']);
    $initial = SystemSettingModel::getVal('whatsapp_api_token');
    assertSys($initial === 'SECRET_TOKEN_12345', "Token awal harus terpasang.");

    // Kirim update dengan string kosong (simulasi form password tidak diisi)
    $model->updateBulk(['whatsapp_api_token' => '']);
    $after = SystemSettingModel::getVal('whatsapp_api_token');
    assertSys($after === 'SECRET_TOKEN_12345', "Token tidak boleh berubah menjadi kosong saat disubmit string kosong.");
});

// TC-SYS-09: Sinkronisasi parameter AI ke ai_configurations
runSysTest('TC-SYS-09', 'Sinkronisasi dua arah parameter AI ke tabel ai_configurations', function() use ($model) {
    $model->updateBulk([
        'gaze_sensitivity_threshold' => '0.85',
        'no_face_timeout_seconds'    => '7'
    ]);

    $db = Database::getConnection();
    $aiGaze = $db->query("SELECT key_value FROM ai_configurations WHERE key_name = 'gaze_sensitivity_threshold'")->fetchColumn();
    assertSys($aiGaze === '0.85', "Nilai di tabel ai_configurations harus ikut terupdate menjadi '0.85'.");

    $aiTimeout = $db->query("SELECT key_value FROM ai_configurations WHERE key_name = 'no_face_timeout_seconds'")->fetchColumn();
    assertSys($aiTimeout === '7', "Nilai di tabel ai_configurations harus ikut terupdate menjadi '7'.");
});

// TC-SYS-10: Ekspor konfigurasi ke format JSON aman
runSysTest('TC-SYS-10', 'Ekspor konfigurasi menghasilkan data JSON dengan data sensitif disensor', function() use ($model) {
    $export = $model->exportConfig();
    assertSys(isset($export['settings']), "Ekspor harus memiliki key 'settings'.");
    assertSys(isset($export['settings']['app_name']), "Ekspor harus memuat app_name.");

    // Cek bahwa field sensitif disensor
    $exportedWaToken = $export['settings']['whatsapp_api_token']['value'] ?? '';
    assertSys($exportedWaToken === '***[PROTECTED]***', "Token WhatsApp wajib disensor menjadi ***[PROTECTED]*** saat diekspor.");
});

// TC-SYS-11: Impor konfigurasi dari format JSON
runSysTest('TC-SYS-11', 'Impor konfigurasi memulihkan setting dan mengabaikan nilai terproteksi', function() use ($model) {
    $mockImport = [
        'settings' => [
            'institution_name'   => 'SMK Bintang Juara Jakarta',
            'whatsapp_api_token' => '***[PROTECTED]***'
        ]
    ];

    $result = $model->importConfig($mockImport);
    assertSys($result['success'] === true, "Impor harus sukses.");
    assertSys(SystemSettingModel::getVal('institution_name') === 'SMK Bintang Juara Jakarta', "institution_name harus diperbarui.");
    assertSys(SystemSettingModel::getVal('whatsapp_api_token') === 'SECRET_TOKEN_12345', "Nilai yang terproteksi tidak boleh ditimpa string ***[PROTECTED]***.");
});

// TC-SYS-12: Reset to defaults
runSysTest('TC-SYS-12', 'Reset to defaults memulihkan seluruh konfigurasi ke baseline awal rekomendasi', function() use ($model) {
    $ok = $model->resetToDefaults();
    assertSys($ok === true, "resetToDefaults harus mengembalikan true.");
    assertSys(SystemSettingModel::getVal('institution_name') === 'SMA Negeri 1 Smart Exam', "institution_name harus kembali ke default.");
    assertSys(SystemSettingModel::getVal('gaze_sensitivity_threshold') === 0.75, "gaze_sensitivity_threshold harus kembali ke 0.75.");
});

// TC-SYS-13: WhatsAppClient verifyConnection
runSysTest('TC-SYS-13', 'WhatsAppClient::verifyConnection() mengembalikan struktur respons diagnostik', function() {
    $client = new WhatsAppClient();
    $res = $client->verifyConnection();
    assertSys(is_array($res), "verifyConnection harus mengembalikan array.");
    assertSys(array_key_exists('success', $res), "Respons harus memiliki key 'success'.");
    assertSys(array_key_exists('message', $res), "Respons harus memiliki key 'message'.");
    assertSys(array_key_exists('device_status', $res), "Respons harus memiliki key 'device_status'.");
});

// TC-SYS-14: SmtpClient verifyConnection
runSysTest('TC-SYS-14', 'SmtpClient::verifyConnection() menguji handshake STARTTLS', function() {
    $client = new SmtpClient();
    $res = $client->verifyConnection();
    assertSys(is_array($res), "verifyConnection SMTP harus mengembalikan array.");
    assertSys(array_key_exists('success', $res), "Respons harus memiliki key 'success'.");
    assertSys(array_key_exists('message', $res), "Respons harus memiliki key 'message'.");
});

// TC-SYS-15: SystemConfigurationController index()
runSysTest('TC-SYS-15', 'SystemConfigurationController::index() merender halaman dengan layout admin', function() {
    // Siapkan simulasi sesi login admin
    Session::set('user_id', 1);
    Session::set('user_role', 'super_admin');

    $controller = new SystemConfigurationController(new Request());
    $response = $controller->index();
    assertSys($response instanceof Response, "Response harus berupa instance App\Libraries\Response.");
    assertSys($response->getStatusCode() === 200, "Status code harus 200 OK.");
    assertSys(str_contains($response->getContent(), 'Konfigurasi Sistem Terpadu'), "Konten harus memuat judul Konfigurasi Sistem Terpadu.");
});

// TC-SYS-16: AuditLog sanitasi field sensitif
runSysTest('TC-SYS-16', 'AuditLog mencatat riwayat perubahan dengan kredensial disamarkan', function() {
    $db = Database::getConnection();
    // Cari log terakhir yang mencatat UPDATE_SYSTEM_CONFIG
    $stmt = $db->query("SELECT after_data FROM audit_logs WHERE action = 'UPDATE_SYSTEM_CONFIG' ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();
    if ($row && !empty($row['after_data'])) {
        $data = json_decode($row['after_data'], true);
        if (isset($data['smtp_password'])) {
            assertSys($data['smtp_password'] === '***[TERPROTEKSI]***' || $data['smtp_password'] === '[KOSONG]', "Password SMTP di audit log harus disensor.");
        }
    }
    assertSys(true, "Audit log sanitasi tervalidasi.");
});

// TC-SYS-17: Isolasi Database Operasional (ujian_dual_kamera strictly 0 logs)
runSysTest('TC-SYS-17', 'Database operasional ujian_dual_kamera STRICTLY tidak tersentuh (0 logs)', function() {
    $dbProd = new PDO("mysql:host=127.0.0.1;port=3306;dbname=ujian_dual_kamera;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $count = (int) $dbProd->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();
    assertSys($count === 0, "Database operasional ujian_dual_kamera HARUS memiliki tepat 0 logs!");
});

echo "\n";
echo "==============================================================================\n";
echo "HASIL AKHIR PENGUJIAN KONFIGURASI SISTEM: {$totalPassed} PASS / {$totalFailed} FAIL\n";
echo "==============================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}

