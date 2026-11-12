<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Services\ReportService;
use Throwable;

/**
 * Report Controller
 * Menangani pengunduhan berkas laporan hasil ujian format PDF dan Excel (UC-25).
 */
class ReportController extends BaseController
{
    protected ReportService $reportService;

    public function __construct()
    {
        parent::__construct();
        $this->reportService = new ReportService();
    }

    /**
     * Unduh Laporan Rekapitulasi Nilai Ujian format PDF
     */
    public function exportPdf(int $id): Response
    {
        $exam = (new \App\Models\ExamModel())->find($id);
        if (!$exam) {
            $this->setFlash('error', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url('/guru/grading'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            $this->setFlash('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengekspor laporan ujian ini.');
            return $this->redirect(base_url('/guru/grading'));
        }

        try {
            $pdfContent = $this->reportService->exportExamReportPdf($id);

            $response = new Response();
            $response->setHeader('Content-Type', 'application/pdf');
            $response->setHeader('Content-Disposition', 'attachment; filename="Rekap_Nilai_Ujian_' . $id . '_' . date('Ymd_His') . '.pdf"');
            $response->setContent($pdfContent);

            return $response;
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal mengekspor PDF: ' . $e->getMessage());
            return $this->redirect(base_url("/guru/grading/exam/{$id}"));
        }
    }

    /**
     * Unduh Laporan Rekapitulasi Nilai Ujian format Excel (.xlsx)
     */
    public function exportExcel(int $id): Response
    {
        $exam = (new \App\Models\ExamModel())->find($id);
        if (!$exam) {
            $this->setFlash('error', 'Ujian tidak ditemukan.');
            return $this->redirect(base_url('/guru/grading'));
        }

        if (user_role() === 'guru' && (int)$exam['teacher_id'] !== (int)user_id()) {
            $this->setFlash('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengekspor laporan ujian ini.');
            return $this->redirect(base_url('/guru/grading'));
        }

        try {
            $excelContent = $this->reportService->exportExamReportExcel($id);

            $response = new Response();
            $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->setHeader('Content-Disposition', 'attachment; filename="Rekap_Nilai_Ujian_' . $id . '_' . date('Ymd_His') . '.xlsx"');
            $response->setContent($excelContent);

            return $response;
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal mengekspor Excel: ' . $e->getMessage());
            return $this->redirect(base_url("/guru/grading/exam/{$id}"));
        }
    }

    /**
     * Unduh Rapor Siswa Individual format PDF
     */
    public function exportStudentPdf(int $id): Response
    {
        $session = (new \App\Models\ExamSessionModel())->find($id);
        if (!$session) {
            $this->setFlash('error', 'Sesi ujian tidak ditemukan.');
            return $this->redirect(base_url('/guru/grading'));
        }

        if (user_role() === 'guru') {
            $exam = (new \App\Models\ExamModel())->find((int)$session['exam_id']);
            if (!$exam || (int)$exam['teacher_id'] !== (int)user_id()) {
                $this->setFlash('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk mengekspor rapor siswa ujian ini.');
                return $this->redirect(base_url('/guru/grading'));
            }
        }

        try {
            $pdfContent = $this->reportService->exportStudentPdf($id);

            $response = new Response();
            $response->setHeader('Content-Type', 'application/pdf');
            $response->setHeader('Content-Disposition', 'attachment; filename="Rapor_Ujian_Siswa_' . $id . '_' . date('Ymd_His') . '.pdf"');
            $response->setContent($pdfContent);

            return $response;
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal mengekspor Rapor Siswa: ' . $e->getMessage());
            return $this->redirect(base_url("/guru/grading/session/{$id}"));
        }
    }
}
