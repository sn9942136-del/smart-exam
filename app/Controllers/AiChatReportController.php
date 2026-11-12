<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Services\AiChatReportService;
use Throwable;

/**
 * ==============================================================================
 * AI CHAT REPORT CONTROLLER — SMART EXAM
 * ==============================================================================
 * Mengelola dashboard Laporan Chatbot AI khusus Administrator (READ-ONLY).
 * Memantau aktivitas percakapan, analitik statistik, riwayat chat, dan ekspor data.
 * ==============================================================================
 */
class AiChatReportController extends BaseController
{
    protected AiChatReportService $reportService;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->reportService = new AiChatReportService();

        // Verifikasi Otorisasi: Khusus Super Admin & Admin (Defense-in-depth)
        $role = user_role();
        if (!in_array($role, ['super_admin', 'admin'], true)) {
            // Redirect / abort untuk non-admin
            if ($this->request && str_contains($this->request->header('Accept') ?? '', 'application/json')) {
                (new Response())->setStatusCode(403)->setJson(['success' => false, 'error' => 'Akses ditolak: Hanya Administrator yang berwenang.'])->send();
                exit;
            }
        }
    }

    /**
     * Tampilkan Halaman Utama Dashboard Laporan Chatbot AI
     */
    public function index(): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            $this->setFlash('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat Laporan Chatbot.');
            return $this->redirect(base_url('unauthorized'));
        }

        $period = (string)$this->request->get('period', '7_days');
        $startDate = (string)$this->request->get('start_date', '');
        $endDate = (string)$this->request->get('end_date', '');
        $role = (string)$this->request->get('role', 'all');
        $status = (string)$this->request->get('status', 'all');
        $search = (string)$this->request->get('search', '');
        $page = (int)$this->request->get('page', 1);
        $sortBy = (string)$this->request->get('sort_by', 'updated_at');
        $order = (string)$this->request->get('order', 'DESC');

        $filters = [
            'period'     => $period,
            'start_date' => $startDate ?: null,
            'end_date'   => $endDate ?: null,
            'role'       => $role,
            'status'     => $status,
            'search'     => $search,
            'sort_by'    => $sortBy,
            'order'      => $order,
        ];

        try {
            // 1. Data Statistik Ringkasan Utama
            $statistics = $this->reportService->getStatistics($filters);

            // 2. Data Grafik Aktivitas
            $chartData = $this->reportService->getChartData($period, $startDate ?: null, $endDate ?: null);

            // 3. Data Riwayat Percakapan (Tabel berpaginasi)
            $sessions = $this->reportService->getSessions($filters, $page, 15);
        } catch (Throwable $e) {
            \App\Libraries\Logger::exception($e);
            $this->setFlash('error', 'Gagal memuat data laporan chatbot: ' . $e->getMessage());
            $statistics = [
                'total_sessions'       => 0,
                'total_user_messages'  => 0,
                'total_bot_messages'   => 0,
                'total_messages'       => 0,
                'total_users'          => 0,
                'today_sessions'       => 0,
                'today_messages'       => 0,
                'month_sessions'       => 0,
                'month_messages'       => 0,
                'error_sessions'       => 0,
                'error_messages'       => 0,
                'role_context_usage'   => [],
                'actual_role_usage'    => [],
            ];
            $chartData = [
                'labels' => [], 'sessions' => [], 'user_messages' => [],
                'bot_messages' => [], 'total_messages' => [], 'role_breakdown' => [],
                'user_vs_bot' => ['user' => 0, 'assistant' => 0]
            ];
            $sessions = ['data' => [], 'total_rows' => 0, 'current_page' => 1, 'per_page' => 15, 'total_pages' => 1];
        }

        return $this->view('admin/ai_chat_reports/index', [
            'pageTitle'  => 'Laporan Chatbot AI - Smart Exam',
            'user'       => auth_user(),
            'statistics' => $statistics,
            'chartData'  => $chartData,
            'sessions'   => $sessions,
            'filters'    => $filters,
        ], 'layouts/admin');
    }

    /**
     * AJAX Endpoint: Dapatkan Data Grafik Aktivitas Berdasarkan Filter Periode
     */
    public function chartData(): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            return $this->json(['success' => false, 'error' => 'Akses ditolak.'], 403);
        }

        $period = (string)$this->request->get('period', '7_days');
        $startDate = (string)$this->request->get('start_date', '');
        $endDate = (string)$this->request->get('end_date', '');

        try {
            $data = $this->reportService->getChartData($period, $startDate ?: null, $endDate ?: null);
            return $this->json(['success' => true, 'chart' => $data]);
        } catch (Throwable $e) {
            \App\Libraries\Logger::exception($e);
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX Endpoint: Dapatkan Detail Percakapan untuk Ditampilkan di Modal Chat Dialog
     */
    public function detailAjax(int $id): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            return $this->json(['success' => false, 'error' => 'Akses ditolak.'], 403);
        }

        try {
            $session = $this->reportService->getSessionDetail($id);
            if (!$session) {
                return $this->json(['success' => false, 'error' => 'Sesi percakapan tidak ditemukan.'], 404);
            }

            return $this->json([
                'success' => true,
                'session' => $session
            ]);
        } catch (Throwable $e) {
            \App\Libraries\Logger::exception($e);
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Halaman Detail Percakapan Standalone (Full-Page View)
     */
    public function detail(int $id): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            $this->setFlash('error', 'Akses ditolak.');
            return $this->redirect(base_url('unauthorized'));
        }

        try {
            $session = $this->reportService->getSessionDetail($id);
            if (!$session) {
                $this->setFlash('error', 'Sesi percakapan #' . $id . ' tidak ditemukan.');
                return $this->redirect(base_url('admin/ai-chat-reports'));
            }

            return $this->view('admin/ai_chat_reports/detail', [
                'pageTitle' => 'Detail Percakapan #' . $id . ' - Smart Exam',
                'user'      => auth_user(),
                'session'   => $session
            ], 'layouts/admin');
        } catch (Throwable $e) {
            \App\Libraries\Logger::exception($e);
            $this->setFlash('error', 'Terjadi kesalahan saat memuat detail percakapan: ' . $e->getMessage());
            return $this->redirect(base_url('admin/ai-chat-reports'));
        }
    }

    /**
     * Ekspor Laporan Riwayat Percakapan Chatbot AI ke Excel (.xlsx)
     */
    public function exportExcel(): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            $this->setFlash('error', 'Akses ditolak.');
            return $this->redirect(base_url('unauthorized'));
        }

        $filters = [
            'start_date' => $this->request->get('start_date') ?: null,
            'end_date'   => $this->request->get('end_date') ?: null,
            'role'       => (string)$this->request->get('role', 'all'),
            'status'     => (string)$this->request->get('status', 'all'),
            'search'     => (string)$this->request->get('search', ''),
            'sort_by'    => (string)$this->request->get('sort_by', 'updated_at'),
            'order'      => (string)$this->request->get('order', 'DESC'),
        ];

        try {
            $content = $this->reportService->exportExcel($filters);
            $filename = 'Laporan_Chatbot_AI_' . date('Ymd_His') . '.xlsx';

            $response = new Response();
            $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->setContent($content);

            return $response;
        } catch (Throwable $e) {
            \App\Libraries\Logger::exception($e);
            $this->setFlash('error', 'Gagal mengekspor laporan ke Excel: ' . $e->getMessage());
            return $this->redirect(base_url('admin/ai-chat-reports'));
        }
    }

    /**
     * Ekspor Laporan Riwayat Percakapan Chatbot AI ke PDF (.pdf)
     */
    public function exportPdf(): Response
    {
        if (!has_role(['super_admin', 'admin'])) {
            $this->setFlash('error', 'Akses ditolak.');
            return $this->redirect(base_url('unauthorized'));
        }

        $filters = [
            'start_date' => $this->request->get('start_date') ?: null,
            'end_date'   => $this->request->get('end_date') ?: null,
            'role'       => (string)$this->request->get('role', 'all'),
            'status'     => (string)$this->request->get('status', 'all'),
            'search'     => (string)$this->request->get('search', ''),
            'sort_by'    => (string)$this->request->get('sort_by', 'updated_at'),
            'order'      => (string)$this->request->get('order', 'DESC'),
        ];

        try {
            $pdfContent = $this->reportService->exportPdf($filters);
            $filename = 'Laporan_Chatbot_AI_' . date('Ymd_His') . '.pdf';

            $response = new Response();
            $response->setHeader('Content-Type', 'application/pdf');
            $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->setContent($pdfContent);

            return $response;
        } catch (Throwable $e) {
            \App\Libraries\Logger::exception($e);
            $this->setFlash('error', 'Gagal mengekspor laporan ke PDF: ' . $e->getMessage());
            return $this->redirect(base_url('admin/ai-chat-reports'));
        }
    }
}

