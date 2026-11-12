<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Models\AiConfigurationModel;
use App\Models\AuditLogModel;

/**
 * AI Configuration Controller
 * Manages system AI thresholds, proctoring sensitivities, and model parameters.
 */
class AiConfigurationController extends BaseController
{
    private AiConfigurationModel $aiConfigModel;

    public function __construct()
    {
        parent::__construct();
        $this->aiConfigModel = new AiConfigurationModel();
    }

    /**
     * Tampilan Konfigurasi Parameter Sensitivitas AI (Redirect langsung ke tab Proctoring Konfigurasi Sistem)
     */
    public function index(): Response
    {
        return $this->redirect(base_url('admin/system-configuration?tab=proctoring'));
    }

    /**
     * Proses update konfigurasi AI
     */
    public function update(): Response
    {
        $post = $this->request->all();
        $settings = $post['configs'] ?? [];

        if (empty($settings) || !is_array($settings)) {
            $this->setFlash('danger', 'Tidak ada data konfigurasi yang dikirim.');
            return $this->redirect(base_url('admin/system-configuration?tab=proctoring'));
        }

        $this->aiConfigModel->updateBulk($settings);

        // Sinkronisasi ke SystemSettingModel agar kedua tabel selalu identik
        $settingModel = new \App\Models\SystemSettingModel();
        $settingModel->updateBulk($settings);

        AuditLogModel::record('UPDATE_AI_CONFIG', 'system', 'ai_configurations', null, $settings);

        $this->setFlash('success', 'Seluruh parameter sensitivitas AI berhasil diperbarui dan disinkronisasi ke engine proctoring.');
        return $this->redirect(base_url('admin/system-configuration?tab=proctoring'));
    }

    /**
     * Reset seluruh parameter ke baseline default
     */
    public function resetDefault(): Response
    {
        $this->aiConfigModel->resetToDefaults();

        $defaults = [
            'face_detection_model'         => 'MediaPipe Face Mesh v2',
            'gaze_sensitivity_threshold'   => '0.75',
            'no_face_timeout_seconds'      => '5',
            'dual_cam_sync_interval_ms'    => '1000',
            'ai_question_generation_model' => 'gpt-4o-mini',
            'ai_essay_grading_tolerance'   => '0.85'
        ];
        $settingModel = new \App\Models\SystemSettingModel();
        $settingModel->updateBulk($defaults);

        AuditLogModel::record('RESET_AI_CONFIG_DEFAULTS', 'system', 'ai_configurations');

        $this->setFlash('success', 'Parameter konfigurasi AI berhasil direset ke pengaturan default sistem.');
        return $this->redirect(base_url('admin/system-configuration?tab=proctoring'));
    }
}
