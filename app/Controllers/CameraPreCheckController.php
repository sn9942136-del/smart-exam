<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamRuleModel;
use App\Models\ExamScheduleModel;

/**
 * Camera Pre-Check Controller
 * Mengelola antarmuka diagnostik & kalibrasi Dual Camera (UC-07, UC-09, UC-14)
 * sebelum peserta memasuki ruang ujian online.
 */
class CameraPreCheckController extends BaseController
{
    private ExamModel $examModel;
    private ExamRuleModel $ruleModel;
    private ExamScheduleModel $scheduleModel;
    private ExamParticipantModel $participantModel;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->examModel = new ExamModel();
        $this->ruleModel = new ExamRuleModel();
        $this->scheduleModel = new ExamScheduleModel();
        $this->participantModel = new ExamParticipantModel();
    }

    /**
     * Halaman Kalibrasi & Diagnostik Dual Camera (/student/exam/pre-check atau /siswa/exam/pre-check/{id})
     */
    public function index(string|int|null $id = null): Response
    {
        $studentId = (int) user_id();
        $exam = null;
        $rules = null;
        $schedule = null;
        $studentClass = $this->participantModel->getStudentClass($studentId);

        if ($id !== null && (int)$id > 0) {
            $examId = (int)$id;
            $validation = $this->participantModel->validateStudentAccess($examId, $studentId);
            $exam = $validation['exam'] ?? $this->examModel->getDetailsWithFullRelations($examId);
            $schedule = $validation['schedule'] ?? null;
            $rules = $exam['rules'] ?? $this->ruleModel->getByExamId($examId);
        }

        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Katalog Ujian', 'url' => base_url('siswa/exams')],
            ['title' => 'Cek & Kalibrasi Dual Kamera']
        ];

        $data = [
            'pageTitle'    => 'Kalibrasi Dual Kamera AI',
            'user'         => auth_user(),
            'studentClass' => $studentClass,
            'exam'         => $exam,
            'rules'        => $rules,
            'schedule'     => $schedule,
            'breadcrumbs'  => $breadcrumbs,
            'savedConfig'  => Session::get('exam_camera_config', [])
        ];

        return $this->view('siswa/exam/pre_check', $data, 'layouts/peserta');
    }

    /**
     * Endpoint Simpan Preferensi Perangkat Kamera ke Sesi Siswa
     */
    public function saveConfig(): Response
    {
        $primaryDeviceId = (string) $this->request->input('primary_device_id', '');
        $secondaryDeviceId = (string) $this->request->input('secondary_device_id', '');

        if (empty($primaryDeviceId)) {
            return $this->json([
                'success' => false,
                'error'   => 'Kamera utama (Front Camera) wajib dipilih.'
            ], 422);
        }

        $config = [
            'primary_device_id'   => $primaryDeviceId,
            'secondary_device_id' => $secondaryDeviceId,
            'configured_at'       => date('Y-m-d H:i:s')
        ];

        Session::set('exam_camera_config', $config);

        return $this->json([
            'success' => true,
            'message' => 'Konfigurasi perangkat kamera berhasil disimpan.',
            'config'  => $config
        ]);
    }
}
