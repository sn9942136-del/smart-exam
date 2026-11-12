<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\App;
use App\Config\Notification;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\SmtpClient;
use App\Libraries\WhatsAppClient;
use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;
use App\Models\SemesterModel;
use App\Models\SystemSettingModel;
use Throwable;

/**
 * System Configuration Controller
 * Mengelola seluruh preferensi dan konfigurasi terpadu Smart Exam:
 * Identitas Umum, Keamanan Sesi, CBT Exam Rules, AI Proctoring, LLM Provider,
 * Notifikasi Multi-Kanal (SMTP & WhatsApp), Penyimpanan Media, serta Diagnostik Server.
 */
class SystemConfigurationController extends BaseController
{
    private SystemSettingModel $settingModel;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->settingModel = new SystemSettingModel();
    }

    /**
     * Tampilan Utama Halaman Konfigurasi Sistem
     */
    public function index(): Response
    {
        $configsGrouped = $this->settingModel->getAllGrouped();
        $groupMetadata = SystemSettingModel::getGroupMetadata();

        // Ambil Data Master untuk dropdown
        $academicYearModel = new AcademicYearModel();
        $academicYears = $academicYearModel->findAll([], 'name ASC');

        $semesterModel = new SemesterModel();
        $semesters = $semesterModel->findAll([], 'name ASC');

        // Kumpulkan Diagnostik Lingkungan Server
        $diagnostics = $this->collectServerDiagnostics();

        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Konfigurasi Sistem']
        ];

        $data = [
            'pageTitle'      => 'Konfigurasi Sistem Terpadu',
            'breadcrumbs'    => $breadcrumbs,
            'configsGrouped' => $configsGrouped,
            'groupMetadata'  => $groupMetadata,
            'academicYears'  => $academicYears,
            'semesters'      => $semesters,
            'diagnostics'    => $diagnostics,
            'activeTab'      => $this->request->get('tab', 'general')
        ];

        return $this->view('admin/system_config/index', $data, 'layouts/admin');
    }

    /**
     * Memproses Penyimpanan Massal Konfigurasi Sistem
     */
    public function update(): Response
    {
        $post = $this->request->all();
        $settings = $post['configs'] ?? [];

        if (empty($settings) || !is_array($settings)) {
            $this->setFlash('danger', 'Tidak ada data konfigurasi yang dikirim.');
            return $this->redirect(base_url('admin/system-configuration'));
        }

        // Validasi input khusus
        $errors = [];
        if (!empty($settings['institution_email']) && !filter_var($settings['institution_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email institusi tidak valid.';
        }
        if (!empty($settings['smtp_from_email']) && !filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email pengirim SMTP tidak valid.';
        }
        if (isset($settings['session_lifetime']) && (int)$settings['session_lifetime'] < 300) {
            $errors[] = 'Masa berlaku sesi minimal 300 detik (5 menit).';
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            return $this->redirect(base_url('admin/system-configuration?tab=' . ($post['current_tab'] ?? 'general')));
        }

        // Lakukan pembaharuan bulk
        $success = $this->settingModel->updateBulk($settings);

        if (!$success) {
            $this->setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan konfigurasi.');
            return $this->redirect(base_url('admin/system-configuration'));
        }

        // Sanitasi log audit dari password dan token sensitif
        $auditPayload = $settings;
        $sensitiveKeys = ['smtp_password', 'whatsapp_api_token', 'gemini_api_key'];
        foreach ($sensitiveKeys as $sKey) {
            if (isset($auditPayload[$sKey])) {
                $auditPayload[$sKey] = !empty($auditPayload[$sKey]) ? '***[TERPROTEKSI]***' : '[KOSONG]';
            }
        }

        AuditLogModel::record(
            'UPDATE_SYSTEM_CONFIG',
            'system',
            'system_settings',
            null,
            $auditPayload
        );

        $this->setFlash('success', 'Seluruh preferensi konfigurasi sistem berhasil diperbarui dan disinkronisasikan ke seluruh modul aplikasi.');
        return $this->redirect(base_url('admin/system-configuration?tab=' . ($post['current_tab'] ?? 'general')));
    }

    /**
     * Reset Seluruh Parameter Konfigurasi ke Nilai Standar Pabrikan
     */
    public function resetDefaults(): Response
    {
        $this->settingModel->resetToDefaults();

        AuditLogModel::record(
            'RESET_SYSTEM_CONFIG_DEFAULTS',
            'system',
            'system_settings'
        );

        $this->setFlash('success', 'Seluruh konfigurasi sistem berhasil dipulihkan ke pengaturan default rekomendasi.');
        return $this->redirect(base_url('admin/system-configuration'));
    }

    /**
     * AJAX Endpoint: Uji Koneksi SMTP Gmail Live
     */
    public function testSmtp(): Response
    {
        try {
            $smtpClient = new SmtpClient();
            $result = $smtpClient->verifyConnection();

            return $this->json([
                'success' => (bool)($result['success'] ?? false),
                'message' => $result['message'] ?? 'Tidak ada pesan respon.',
                'banner'  => $result['banner'] ?? null
            ]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Pengecualian saat menguji SMTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX Endpoint: Uji Koneksi WhatsApp Gateway (Fonnte API / Local Mock)
     */
    public function testWhatsApp(): Response
    {
        try {
            $waClient = new WhatsAppClient();
            $result = $waClient->verifyConnection();

            return $this->json([
                'success'       => (bool)($result['success'] ?? false),
                'message'       => $result['message'] ?? 'Tidak ada pesan respon.',
                'device_status' => $result['device_status'] ?? 'unknown',
                'details'       => $result['details'] ?? null
            ]);
        } catch (Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Pengecualian saat menguji WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ekspor Cadangan Konfigurasi Sistem ke File JSON
     */
    public function export(): Response
    {
        $exportData = $this->settingModel->exportConfig();
        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $filename = 'smart_exam_config_' . date('Ymd_His') . '.json';

        $response = new Response();
        $response->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $response->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $response->setContent($json);
        return $response;
    }

    /**
     * Impor Cadangan Konfigurasi Sistem dari File JSON yang Diunggah
     */
    public function import(): Response
    {
        if (empty($_FILES['config_file']['tmp_name']) || !is_uploaded_file($_FILES['config_file']['tmp_name'])) {
            $this->setFlash('danger', 'Harap pilih berkas JSON konfigurasi yang valid untuk diimpor.');
            return $this->redirect(base_url('admin/system-configuration'));
        }

        $rawJson = file_get_contents($_FILES['config_file']['tmp_name']);
        $decoded = json_decode((string)$rawJson, true);

        if (!is_array($decoded) || empty($decoded['settings'])) {
            $this->setFlash('danger', 'Format berkas JSON konfigurasi tidak sesuai atau rusak.');
            return $this->redirect(base_url('admin/system-configuration'));
        }

        $result = $this->settingModel->importConfig($decoded);

        if (!($result['success'] ?? false)) {
            $this->setFlash('danger', $result['message'] ?? 'Gagal mengimpor konfigurasi.');
            return $this->redirect(base_url('admin/system-configuration'));
        }

        AuditLogModel::record(
            'IMPORT_SYSTEM_CONFIG',
            'system',
            'system_settings',
            null,
            ['count' => $result['count'] ?? 0]
        );

        $this->setFlash('success', $result['message'] ?? 'Konfigurasi berhasil dipulihkan.');
        return $this->redirect(base_url('admin/system-configuration'));
    }

    /**
     * Kumpulkan metrik informasi server & dependensi sistem
     */
    private function collectServerDiagnostics(): array
    {
        $appRoot = dirname(__DIR__, 2);

        // Periksa izin tulis direktori penyimpanan
        $storagePaths = [
            'Logs'       => $appRoot . '/storage/logs',
            'Uploads'    => $appRoot . '/storage/uploads',
            'Recordings' => $appRoot . '/storage/recordings'
        ];

        $storageStatus = [];
        foreach ($storagePaths as $name => $path) {
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);
            $storageStatus[$name] = [
                'path'     => str_replace($appRoot, '', $path),
                'exists'   => $exists,
                'writable' => $writable
            ];
        }

        // Cek ekstensi PHP esensial
        $requiredExtensions = [
            'pdo_mysql' => 'Koneksi Basis Data MySQL',
            'curl'      => 'Koneksi API AI & WhatsApp Gateway',
            'openssl'   => 'Kriptografi & Token CSPRNG',
            'mbstring'  => 'Multibyte String Processing',
            'gd'        => 'Kompresi Gambar & Watermarking',
            'fileinfo'  => 'Validasi MIME Berkas Unggahan',
            'zip'       => 'Ekspor Laporan Spreadsheet'
        ];

        $extensionsStatus = [];
        foreach ($requiredExtensions as $ext => $desc) {
            $extensionsStatus[$ext] = [
                'desc'   => $desc,
                'loaded' => extension_loaded($ext)
            ];
        }

        // Hitung ruang penyimpanan hard disk
        $diskFree = @disk_free_space($appRoot);
        $diskTotal = @disk_total_space($appRoot);
        $diskFreeFormatted = $diskFree ? number_format($diskFree / (1024 * 1024 * 1024), 2) . ' GB' : 'N/A';
        $diskTotalFormatted = $diskTotal ? number_format($diskTotal / (1024 * 1024 * 1024), 2) . ' GB' : 'N/A';
        $diskUsagePercent = ($diskTotal && $diskFree) ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;

        return [
            'php_version'       => PHP_VERSION,
            'os'                => PHP_OS_FAMILY . ' (' . php_uname('s') . ' ' . php_uname('r') . ')',
            'server_software'   => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP ' . PHP_VERSION . ' Dev Server',
            'database_name'     => \App\Config\Database::$database,
            'database_host'     => \App\Config\Database::$host . ':' . \App\Config\Database::$port,
            'memory_usage'      => number_format(memory_get_usage(true) / (1024 * 1024), 2) . ' MB',
            'memory_limit'      => ini_get('memory_limit') ?: 'N/A',
            'max_execution_time'=> ini_get('max_execution_time') . 's',
            'upload_max_filesize'=> ini_get('upload_max_filesize'),
            'post_max_size'     => ini_get('post_max_size'),
            'disk_free'         => $diskFreeFormatted,
            'disk_total'        => $diskTotalFormatted,
            'disk_usage_percent'=> $diskUsagePercent,
            'storage_status'    => $storageStatus,
            'extensions_status' => $extensionsStatus,
            'smtp_summary'      => Notification::getSmtpConfigSummary(),
            'whatsapp_summary'  => Notification::getWhatsAppConfigSummary()
        ];
    }
}

