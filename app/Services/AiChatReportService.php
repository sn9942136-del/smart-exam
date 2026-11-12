<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Database;
use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * ==============================================================================
 * AI CHAT REPORT SERVICE — SMART EXAM
 * ==============================================================================
 * Layanan pelaporan, audit, dan analitik aktivitas Chatbot AI (READ-ONLY).
 * Mengolah statistik percakapan riil, riwayat pesan, distribusi role, dan ekspor.
 * Menjamin 0 modifikasi terhadap riwayat chat dan engine AI yang sedang berjalan.
 * ==============================================================================
 */
class AiChatReportService
{
    protected ?PDO $db = null;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    protected function getDb(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getConnection();
        }
        return $this->db;
    }

    /**
     * Dapatkan ringkasan statistik komprehensif chatbot AI dari database aktual
     */
    public function getStatistics(?array $filters = null): array
    {
        $db = $this->getDb();

        // 1. Total Sesi / Percakapan
        $totalSessions = (int)$db->query("SELECT COUNT(*) FROM ai_chat_sessions")->fetchColumn();

        // 2. Pesan Pengguna & Pesan Chatbot
        $msgStats = $db->query("
            SELECT 
                SUM(CASE WHEN sender = 'user' THEN 1 ELSE 0 END) AS total_user_msg,
                SUM(CASE WHEN sender = 'assistant' THEN 1 ELSE 0 END) AS total_bot_msg,
                COUNT(*) AS total_msg,
                SUM(CASE WHEN is_error = 1 THEN 1 ELSE 0 END) AS total_err_msg
            FROM ai_chat_messages
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalUserMsg = (int)($msgStats['total_user_msg'] ?? 0);
        $totalBotMsg = (int)($msgStats['total_bot_msg'] ?? 0);
        $totalMsg = (int)($msgStats['total_msg'] ?? 0);
        $totalErrMsg = (int)($msgStats['total_err_msg'] ?? 0);

        // 3. Total Pengguna Unik yang Pernah Chatting
        $totalUsers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM ai_chat_sessions")->fetchColumn();

        // 4. Aktivitas Hari Ini (Sesi & Pesan)
        $todayStats = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM ai_chat_sessions WHERE DATE(created_at) = CURDATE()) AS today_sessions,
                (SELECT COUNT(*) FROM ai_chat_messages WHERE DATE(created_at) = CURDATE()) AS today_messages
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $todaySessions = (int)($todayStats['today_sessions'] ?? 0);
        $todayMessages = (int)($todayStats['today_messages'] ?? 0);

        // 5. Aktivitas Bulan Ini (Sesi & Pesan)
        $monthStats = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM ai_chat_sessions WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())) AS month_sessions,
                (SELECT COUNT(*) FROM ai_chat_messages WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())) AS month_messages
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $monthSessions = (int)($monthStats['month_sessions'] ?? 0);
        $monthMessages = (int)($monthStats['month_messages'] ?? 0);

        // 6. Jumlah Percakapan dengan Catatan Error
        $errorSessions = (int)$db->query("
            SELECT COUNT(DISTINCT session_id) 
            FROM ai_chat_messages 
            WHERE is_error = 1
        ")->fetchColumn();

        // 7. Penggunaan Berdasarkan Role Konteks Sesi
        $roleContextRows = $db->query("
            SELECT 
                role_context,
                COUNT(id) AS session_count
            FROM ai_chat_sessions
            GROUP BY role_context
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $roleUsage = [
            'admin'              => 0,
            'guru'               => 0,
            'siswa'              => 0,
            'question_explainer' => 0,
            'lainnya'            => 0
        ];
        foreach ($roleContextRows as $r) {
            $ctx = strtolower((string)($r['role_context'] ?? ''));
            $cnt = (int)$r['session_count'];
            if (isset($roleUsage[$ctx])) {
                $roleUsage[$ctx] = $cnt;
            } else {
                $roleUsage['lainnya'] += $cnt;
            }
        }

        // 8. Penggunaan Berdasarkan Role Akun Pengguna Terdaftar
        $userRoleRows = $db->query("
            SELECT 
                COALESCE(r.name, 'unassigned') AS role_name,
                COUNT(DISTINCT s.id) AS session_count
            FROM ai_chat_sessions s
            LEFT JOIN user_roles ur ON s.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            GROUP BY role_name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $actualRoleUsage = [
            'super_admin'   => 0,
            'admin'         => 0,
            'guru'          => 0,
            'peserta_didik' => 0,
            'lainnya'       => 0
        ];
        foreach ($userRoleRows as $ur) {
            $rName = strtolower((string)$ur['role_name']);
            $cnt = (int)$ur['session_count'];
            if (isset($actualRoleUsage[$rName])) {
                $actualRoleUsage[$rName] = $cnt;
            } else {
                $actualRoleUsage['lainnya'] += $cnt;
            }
        }

        return [
            'total_sessions'       => $totalSessions,
            'total_user_messages'  => $totalUserMsg,
            'total_bot_messages'   => $totalBotMsg,
            'total_messages'       => $totalMsg,
            'total_users'          => $totalUsers,
            'today_sessions'       => $todaySessions,
            'today_messages'       => $todayMessages,
            'month_sessions'       => $monthSessions,
            'month_messages'       => $monthMessages,
            'error_sessions'       => $errorSessions,
            'error_messages'       => $totalErrMsg,
            'role_context_usage'   => $roleUsage,
            'actual_role_usage'    => $actualRoleUsage,
        ];
    }

    /**
     * Hitung rentang tanggal awal dan akhir berdasarkan preset periode
     */
    public function resolveDateRange(string $period = '7_days', ?string $startDate = null, ?string $endDate = null): array
    {
        $today = date('Y-m-d');

        switch ($period) {
            case 'today':
                return [$today, $today];
            case '7_days':
                return [date('Y-m-d', strtotime('-6 days')), $today];
            case '30_days':
                return [date('Y-m-d', strtotime('-29 days')), $today];
            case 'this_month':
                return [date('Y-m-01'), date('Y-m-t')];
            case 'custom':
                $start = (!empty($startDate) && strtotime($startDate)) ? date('Y-m-d', strtotime($startDate)) : date('Y-m-d', strtotime('-6 days'));
                $end = (!empty($endDate) && strtotime($endDate)) ? date('Y-m-d', strtotime($endDate)) : $today;
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                return [$start, $end];
            default:
                return [date('Y-m-d', strtotime('-6 days')), $today];
        }
    }

    /**
     * Ambil data analitik grafik aktivitas berdasarkan periode filter
     */
    public function getChartData(string $period = '7_days', ?string $startDate = null, ?string $endDate = null): array
    {
        $db = $this->getDb();
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);

        // 1. Data Timeline Harian (Sesi Percakapan & Jumlah Pesan per Hari)
        $dateMap = [];
        $current = strtotime($start);
        $endTs = strtotime($end);

        // Batasi rentang timeline maksimal 90 hari agar grafik tetap ringan & responsif
        $diffDays = (int)(($endTs - $current) / 86400);
        if ($diffDays > 90) {
            $current = strtotime('-89 days', $endTs);
            $start = date('Y-m-d', $current);
        }

        while ($current <= $endTs) {
            $d = date('Y-m-d', $current);
            $dateMap[$d] = [
                'date'          => $d,
                'label'         => date('d M', $current),
                'sessions'      => 0,
                'user_messages' => 0,
                'bot_messages'  => 0,
                'total_messages'=> 0,
            ];
            $current = strtotime('+1 day', $current);
        }

        // Query sesi harian dalam rentang
        $stmtSessions = $db->prepare("
            SELECT DATE(created_at) AS dt, COUNT(*) AS cnt
            FROM ai_chat_sessions
            WHERE DATE(created_at) BETWEEN :start AND :end
            GROUP BY DATE(created_at)
        ");
        $stmtSessions->execute([':start' => $start, ':end' => $end]);
        foreach ($stmtSessions->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dt = $row['dt'];
            if (isset($dateMap[$dt])) {
                $dateMap[$dt]['sessions'] = (int)$row['cnt'];
            }
        }

        // Query pesan harian dalam rentang
        $stmtMessages = $db->prepare("
            SELECT 
                DATE(created_at) AS dt,
                SUM(CASE WHEN sender = 'user' THEN 1 ELSE 0 END) AS user_cnt,
                SUM(CASE WHEN sender = 'assistant' THEN 1 ELSE 0 END) AS bot_cnt,
                COUNT(*) AS total_cnt
            FROM ai_chat_messages
            WHERE DATE(created_at) BETWEEN :start AND :end
            GROUP BY DATE(created_at)
        ");
        $stmtMessages->execute([':start' => $start, ':end' => $end]);
        foreach ($stmtMessages->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dt = $row['dt'];
            if (isset($dateMap[$dt])) {
                $dateMap[$dt]['user_messages'] = (int)($row['user_cnt'] ?? 0);
                $dateMap[$dt]['bot_messages'] = (int)($row['bot_cnt'] ?? 0);
                $dateMap[$dt]['total_messages'] = (int)($row['total_cnt'] ?? 0);
            }
        }

        // 2. Distribusi Penggunaan Berdasarkan Role dalam Periode
        $stmtRoles = $db->prepare("
            SELECT 
                COALESCE(r.name, s.role_context, 'lainnya') AS role_name,
                COUNT(DISTINCT s.id) AS session_count
            FROM ai_chat_sessions s
            LEFT JOIN user_roles ur ON s.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE DATE(s.created_at) BETWEEN :start AND :end
            GROUP BY role_name
        ");
        $stmtRoles->execute([':start' => $start, ':end' => $end]);
        $roleBreakdown = [];
        foreach ($stmtRoles->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roleBreakdown[$r['role_name']] = (int)$r['session_count'];
        }

        // 3. Perbandingan Total Pesan User vs Respons Bot dalam Periode
        $stmtRatio = $db->prepare("
            SELECT 
                SUM(CASE WHEN sender = 'user' THEN 1 ELSE 0 END) AS user_count,
                SUM(CASE WHEN sender = 'assistant' THEN 1 ELSE 0 END) AS bot_count
            FROM ai_chat_messages
            WHERE DATE(created_at) BETWEEN :start AND :end
        ");
        $stmtRatio->execute([':start' => $start, ':end' => $end]);
        $ratioRow = $stmtRatio->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'period'        => $period,
            'start_date'    => $start,
            'end_date'      => $end,
            'labels'        => array_column(array_values($dateMap), 'label'),
            'sessions'      => array_column(array_values($dateMap), 'sessions'),
            'user_messages' => array_column(array_values($dateMap), 'user_messages'),
            'bot_messages'  => array_column(array_values($dateMap), 'bot_messages'),
            'total_messages'=> array_column(array_values($dateMap), 'total_messages'),
            'role_breakdown'=> $roleBreakdown,
            'user_vs_bot'   => [
                'user'      => (int)($ratioRow['user_count'] ?? 0),
                'assistant' => (int)($ratioRow['bot_count'] ?? 0)
            ]
        ];
    }

    /**
     * Dapatkan daftar riwayat percakapan dengan filter, pencarian, dan pagination
     */
    public function getSessions(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $db = $this->getDb();

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        // Filter Pencarian (Nama, Email, Username, atau Judul Percakapan)
        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(u.name LIKE :s1 OR u.email LIKE :s2 OR u.username LIKE :s3 OR s.title LIKE :s4)";
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        // Filter Rentang Tanggal
        if (!empty($filters['start_date'])) {
            $where[] = "DATE(s.created_at) >= :start_date";
            $params[':start_date'] = date('Y-m-d', strtotime((string)$filters['start_date']));
        }
        if (!empty($filters['end_date'])) {
            $where[] = "DATE(s.created_at) <= :end_date";
            $params[':end_date'] = date('Y-m-d', strtotime((string)$filters['end_date']));
        }

        // Filter Role Pengguna / Role Konteks
        if (!empty($filters['role']) && $filters['role'] !== 'all') {
            $roleFilter = trim((string)$filters['role']);
            $where[] = "(r.name = :role_filter OR s.role_context = :role_ctx)";
            $params[':role_filter'] = $roleFilter;
            $params[':role_ctx'] = $roleFilter;
        }

        // Filter Status Sesi
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statusFilter = trim((string)$filters['status']);
            if ($statusFilter === 'error') {
                $where[] = "EXISTS (SELECT 1 FROM ai_chat_messages err_m WHERE err_m.session_id = s.id AND err_m.is_error = 1)";
            } elseif ($statusFilter === 'exam_mode') {
                $where[] = "s.is_exam_mode = 1";
            } elseif ($statusFilter === 'active') {
                $where[] = "EXISTS (SELECT 1 FROM ai_chat_messages act_m WHERE act_m.session_id = s.id)";
            } elseif ($statusFilter === 'empty') {
                $where[] = "NOT EXISTS (SELECT 1 FROM ai_chat_messages emp_m WHERE emp_m.session_id = s.id)";
            }
        }

        $whereSql = implode(" AND ", $where);

        // Hitung total baris yang cocok
        $countSql = "
            SELECT COUNT(DISTINCT s.id)
            FROM ai_chat_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE {$whereSql}
        ";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalRows = (int)$countStmt->fetchColumn();

        // Pengurutan Waktu
        $sortBy = (isset($filters['sort_by']) && in_array($filters['sort_by'], ['created_at', 'updated_at', 'id'], true))
            ? 's.' . $filters['sort_by']
            : 's.updated_at';
        $order = (isset($filters['order']) && strtoupper((string)$filters['order']) === 'ASC') ? 'ASC' : 'DESC';

        // Query Data Riwayat Sesi Percakapan
        $dataSql = "
            SELECT 
                s.id,
                s.session_uuid,
                s.title,
                s.role_context,
                s.is_exam_mode,
                s.created_at,
                s.updated_at,
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email,
                u.nip_nisn,
                u.avatar,
                COALESCE(MIN(r.name), s.role_context) AS user_role,
                COUNT(m.id) AS total_messages,
                SUM(CASE WHEN m.is_error = 1 THEN 1 ELSE 0 END) AS error_count,
                SUM(CASE WHEN m.sender = 'user' THEN 1 ELSE 0 END) AS user_message_count,
                SUM(CASE WHEN m.sender = 'assistant' THEN 1 ELSE 0 END) AS bot_message_count,
                MAX(m.created_at) AS last_message_at
            FROM ai_chat_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            LEFT JOIN ai_chat_messages m ON s.id = m.session_id
            WHERE {$whereSql}
            GROUP BY s.id
            ORDER BY {$sortBy} {$order}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($dataSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Format data baris untuk keperluan UI
        $formatted = [];
        $indexNumber = $offset + 1;
        foreach ($rows as $row) {
            $errCount = (int)($row['error_count'] ?? 0);
            $msgCount = (int)($row['total_messages'] ?? 0);
            $isExam = (bool)($row['is_exam_mode'] ?? false);

            if ($errCount > 0) {
                $statusBadge = ['label' => 'Ada Kendala', 'class' => 'bg-danger-subtle text-danger border-danger-subtle', 'icon' => 'bi-exclamation-triangle'];
            } elseif ($isExam) {
                $statusBadge = ['label' => 'Mode Ujian', 'class' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle', 'icon' => 'bi-shield-shaded'];
            } elseif ($msgCount > 0) {
                $statusBadge = ['label' => 'Aktif / Selesai', 'class' => 'bg-success-subtle text-success border-success-subtle', 'icon' => 'bi-check-circle'];
            } else {
                $statusBadge = ['label' => 'Sesi Baru', 'class' => 'bg-secondary-subtle text-secondary border-secondary-subtle', 'icon' => 'bi-dash-circle'];
            }

            $formatted[] = array_merge($row, [
                'index_no'     => $indexNumber++,
                'status_badge' => $statusBadge,
                'role_label'   => self::formatRoleLabel((string)($row['user_role'] ?? ''))
            ]);
        }

        $totalPages = (int)ceil($totalRows / $perPage);

        return [
            'data'         => $formatted,
            'total_rows'   => $totalRows,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total_pages'  => max(1, $totalPages),
            'has_prev'     => $page > 1,
            'has_next'     => $page < $totalPages
        ];
    }

    /**
     * Dapatkan detail lengkap satu sesi percakapan termasuk pesan-pesan kronologis (READ-ONLY)
     */
    public function getSessionDetail(int $sessionId): ?array
    {
        $db = $this->getDb();

        // 1. Ambil data sesi percakapan & identitas user
        $sqlSession = "
            SELECT 
                s.id,
                s.session_uuid,
                s.title,
                s.role_context,
                s.is_exam_mode,
                s.exam_session_id,
                s.created_at,
                s.updated_at,
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email,
                u.username,
                u.nip_nisn,
                u.avatar,
                u.status AS user_status,
                COALESCE(MIN(r.name), s.role_context) AS user_role,
                (SELECT COUNT(*) FROM ai_chat_messages m WHERE m.session_id = s.id) AS total_messages,
                (SELECT COUNT(*) FROM ai_chat_messages m WHERE m.session_id = s.id AND m.is_error = 1) AS error_count
            FROM ai_chat_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE s.id = :id
            GROUP BY s.id
            LIMIT 1
        ";
        $stmtSession = $db->prepare($sqlSession);
        $stmtSession->execute([':id' => $sessionId]);
        $session = $stmtSession->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return null;
        }

        // 2. Ambil seluruh pesan dalam sesi secara kronologis (ASC berdasarkan ID)
        $sqlMessages = "
            SELECT 
                id,
                session_id,
                user_id,
                sender,
                message,
                tokens_used,
                model_used,
                is_error,
                created_at,
                updated_at
            FROM ai_chat_messages
            WHERE session_id = :session_id
            ORDER BY id ASC
        ";
        $stmtMessages = $db->prepare($sqlMessages);
        $stmtMessages->execute([':session_id' => $sessionId]);
        $messages = $stmtMessages->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Sensor otomatis jika ada teks sangat sensitif (kredensial / token) tanpa mengubah database
        $scrubbedMessages = [];
        foreach ($messages as $msg) {
            $msg['message'] = self::sanitizeSensitiveText((string)$msg['message']);
            $scrubbedMessages[] = $msg;
        }

        $session['role_label'] = self::formatRoleLabel((string)($session['user_role'] ?? ''));
        $session['messages'] = $scrubbedMessages;

        return $session;
    }

    /**
     * Format label badge role agar mudah dipahami
     */
    public static function formatRoleLabel(string $role): string
    {
        return match (strtolower(trim($role))) {
            'super_admin'        => 'Super Admin',
            'admin'              => 'Administrator',
            'guru'               => 'Guru / Pengawas',
            'peserta_didik', 'siswa' => 'Peserta Didik',
            'question_explainer' => 'Penjelas Soal (AI)',
            default              => ucfirst($role ?: 'Pengguna')
        };
    }

    /**
     * Sanitasi teks sensitif agar token / kunci rahasia tidak bocor pada laporan admin
     */
    public static function sanitizeSensitiveText(string $text): string
    {
        // Sensor API Key format Google Gemini / Bearer
        $text = preg_replace('/AIza[0-9A-Za-z-_]{35}/', '***[TERPROTEKSI]***', $text) ?: $text;
        $text = preg_replace('/AQ\.[0-9A-Za-z-_]{40,}/', '***[TERPROTEKSI]***', $text) ?: $text;
        // Sensor format Authorization Bearer
        $text = preg_replace('/(Bearer\s+)[A-Za-z0-9_\-\.]{15,}/i', '$1***[TERPROTEKSI]***', $text) ?: $text;
        return $text;
    }

    /**
     * Ekspor Laporan Riwayat Percakapan ke Format Excel (.xlsx) Menggunakan PhpSpreadsheet
     */
    public function exportExcel(array $filters = []): string
    {
        // Ambil data tanpa batasan pagination kecil (maks 1000 baris untuk keamanan memori)
        $reportData = $this->getSessions($filters, 1, 1000);
        $rows = $reportData['data'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Chatbot AI');

        // Header Dokumen
        $sheet->setCellValue('A1', 'SMART EXAM — LAPORAN AKTIVITAS CHATBOT AI');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $periodeText = 'Periode: ' . ($filters['start_date'] ?? 'Semua') . ' s/d ' . ($filters['end_date'] ?? 'Semua');
        $sheet->setCellValue('A2', $periodeText . ' | Dicetak pada: ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Tabel
        $headers = [
            'A4' => 'No',
            'B4' => 'ID Sesi',
            'C4' => 'Nama Pengguna',
            'D4' => 'Email',
            'E4' => 'Role',
            'F4' => 'Judul Percakapan',
            'G4' => 'Total Pesan',
            'H4' => 'Status',
            'I4' => 'Waktu Percakapan'
        ];

        foreach ($headers as $col => $title) {
            $sheet->setCellValue($col, $title);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]
        ];
        $sheet->getStyle('A4:I4')->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(26);

        // Isi Data Baris
        $rowIdx = 5;
        foreach ($rows as $item) {
            $statusText = $item['status_badge']['label'] ?? 'Normal';
            $sheet->setCellValue('A' . $rowIdx, $item['index_no']);
            $sheet->setCellValue('B' . $rowIdx, '#' . $item['id']);
            $sheet->setCellValue('C' . $rowIdx, $item['user_name'] ?? '-');
            $sheet->setCellValue('D' . $rowIdx, $item['user_email'] ?? '-');
            $sheet->setCellValue('E' . $rowIdx, $item['role_label']);
            $sheet->setCellValue('F' . $rowIdx, $item['title'] ?? 'Percakapan AI');
            $sheet->setCellValue('G' . $rowIdx, (int)$item['total_messages']);
            $sheet->setCellValue('H' . $rowIdx, $statusText);
            $sheet->setCellValue('I' . $rowIdx, date('d/m/Y H:i', strtotime($item['updated_at'])));

            // Penataan Alignment
            $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('I' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Border baris
            $sheet->getStyle('A' . $rowIdx . ':I' . $rowIdx)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

            $rowIdx++;
        }

        if (empty($rows)) {
            $sheet->setCellValue('A5', 'Tidak ada data percakapan yang sesuai dengan filter.');
            $sheet->mergeCells('A5:I5');
            $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A5')->getFont()->setItalic(true);
        }

        // Auto size kolom
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string)ob_get_clean();
    }

    /**
     * Ekspor Laporan Riwayat Percakapan ke Format PDF Menggunakan Dompdf
     */
    public function exportPdf(array $filters = []): string
    {
        $reportData = $this->getSessions($filters, 1, 500);
        $rows = $reportData['data'];
        $stats = $this->getStatistics($filters);

        $html = $this->renderPdfTemplate($rows, $stats, $filters);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string)$dompdf->output();
    }

    /**
     * Render Template HTML untuk Dompdf Laporan Chatbot
     */
    protected function renderPdfTemplate(array $rows, array $stats, array $filters): string
    {
        $startDate = $filters['start_date'] ?? 'Semua';
        $endDate = $filters['end_date'] ?? 'Semua';
        $printedAt = date('d/m/Y H:i:s');

        $rowsHtml = '';
        foreach ($rows as $item) {
            $statusBadge = $item['status_badge']['label'] ?? 'Normal';
            $badgeColor = match ($statusBadge) {
                'Ada Kendala'    => '#dc2626',
                'Mode Ujian'     => '#d97706',
                'Aktif / Selesai'=> '#16a34a',
                default          => '#64748b'
            };

            $timeFormatted = date('d/m/Y H:i', strtotime($item['updated_at']));
            $uName = htmlspecialchars((string)($item['user_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $uEmail = htmlspecialchars((string)($item['user_email'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $uRole = htmlspecialchars((string)$item['role_label'], ENT_QUOTES, 'UTF-8');
            $uTitle = htmlspecialchars((string)($item['title'] ?? '-'), ENT_QUOTES, 'UTF-8');

            $rowsHtml .= "
                <tr>
                    <td style='text-align: center;'>{$item['index_no']}</td>
                    <td style='text-align: center; font-family: monospace;'>#{$item['id']}</td>
                    <td><strong>{$uName}</strong><br><small style='color: #64748b;'>{$uEmail}</small></td>
                    <td>{$uRole}</td>
                    <td>{$uTitle}</td>
                    <td style='text-align: center; font-weight: bold;'>{$item['total_messages']}</td>
                    <td style='text-align: center;'><span style='background-color: {$badgeColor}; color: #ffffff; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold;'>{$statusBadge}</span></td>
                    <td style='text-align: center;'>{$timeFormatted}</td>
                </tr>
            ";
        }

        if (empty($rows)) {
            $rowsHtml = "<tr><td colspan='8' style='text-align: center; padding: 15px; color: #64748b; font-style: italic;'>Tidak ada data percakapan pada rentang filter ini.</td></tr>";
        }

        return "
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <title>Laporan Aktivitas Chatbot AI - Smart Exam</title>
            <style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 10px; }
                .header-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
                .title { font-size: 18px; font-weight: bold; color: #0f172a; margin: 0; }
                .subtitle { font-size: 10px; color: #64748b; margin-top: 3px; }
                .stats-container { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                .stat-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; text-align: center; }
                .stat-value { font-size: 16px; font-weight: bold; color: #2563eb; }
                .stat-label { font-size: 9px; color: #64748b; margin-top: 2px; }
                table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                table.data-table th { background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 9px; padding: 6px 8px; text-align: left; }
                table.data-table td { font-size: 9px; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
                table.data-table tr:nth-child(even) { background-color: #f8fafc; }
                .footer { margin-top: 20px; font-size: 8px; color: #94a3b8; text-align: right; }
            </style>
        </head>
        <body>
            <table class='header-table'>
                <tr>
                    <td>
                        <div class='title'>SMART EXAM — LAPORAN AKTIVITAS CHATBOT AI</div>
                        <div class='subtitle'>Sistem Ujian Online Berbasis AI dan Dual Camera &bull; Audit Operasional Asisten AI</div>
                    </td>
                    <td style='text-align: right;'>
                        <div class='subtitle'>Periode: <strong>{$startDate}</strong> s/d <strong>{$endDate}</strong></div>
                        <div class='subtitle'>Dicetak: {$printedAt}</div>
                    </td>
                </tr>
            </table>

            <table class='stats-container'>
                <tr>
                    <td style='width: 20%; padding: 3px;'><div class='stat-box'><div class='stat-value'>{$stats['total_sessions']}</div><div class='stat-label'>Total Sesi Percakapan</div></div></td>
                    <td style='width: 20%; padding: 3px;'><div class='stat-box'><div class='stat-value'>{$stats['total_messages']}</div><div class='stat-label'>Total Pesan</div></div></td>
                    <td style='width: 20%; padding: 3px;'><div class='stat-box'><div class='stat-value'>{$stats['total_users']}</div><div class='stat-label'>Pengguna Unik</div></div></td>
                    <td style='width: 20%; padding: 3px;'><div class='stat-box'><div class='stat-value'>{$stats['today_messages']}</div><div class='stat-label'>Pesan Hari Ini</div></div></td>
                    <td style='width: 20%; padding: 3px;'><div class='stat-box'><div class='stat-value'>{$stats['error_sessions']}</div><div class='stat-label'>Sesi Ada Kendala</div></div></td>
                </tr>
            </table>

            <table class='data-table'>
                <thead>
                    <tr>
                        <th style='width: 3%; text-align: center;'>No</th>
                        <th style='width: 6%; text-align: center;'>ID</th>
                        <th style='width: 22%;'>Pengguna</th>
                        <th style='width: 13%;'>Role</th>
                        <th style='width: 28%;'>Judul Sesi</th>
                        <th style='width: 8%; text-align: center;'>Pesan</th>
                        <th style='width: 10%; text-align: center;'>Status</th>
                        <th style='width: 10%; text-align: center;'>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>

            <div class='footer'>
                Laporan Otomatis Smart Exam &bull; Dokumen Rahasia Khusus Administrator
            </div>
        </body>
        </html>
        ";
    }
}

