<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;

/**
 * Dashboard Controller
 * Handles role-specific dashboard views and statistics for Admin, Guru, and Peserta Didik.
 */
class DashboardController extends BaseController
{
    /**
     * Dashboard Administrator & Super Admin
     */
    public function admin(): Response
    {
        $breadcrumbs = [
            ['title' => 'Admin', 'url' => base_url('admin/dashboard')],
            ['title' => 'Pusat Kontrol Utama']
        ];

        $stats = [
            'total_users'        => 42,
            'total_exams'        => 12,
            'active_proctorings' => 8,
            'violations_today'   => 3,
            'server_cpu'         => '18%',
            'server_memory'      => '2.4 GB / 8 GB',
            'storage_snapshots'  => '4.8 GB / 50 GB'
        ];

        $recentAuditRows = [
            [
                '1',
                '<div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-1.5 fs-xs fw-bold" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;">AD</div>
                    <div><span class="fw-semibold text-dark d-block">Admin Sekolah</span><small class="text-muted">admin@example.com</small></div>
                 </div>',
                '<span class="badge bg-primary-subtle text-primary">Login Berhasil</span>',
                '<span class="font-monospace text-muted">127.0.0.1</span>',
                '<span class="text-muted">Baru saja</span>',
                '<div class="text-end"><span class="badge bg-success"><i class="bi bi-check2"></i> Terverifikasi</span></div>'
            ],
            [
                '2',
                '<div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-info-subtle text-info p-1.5 fs-xs fw-bold" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;">BG</div>
                    <div><span class="fw-semibold text-dark">Budi Guru, M.Pd</span><small class="text-muted">guru@example.com</small></div>
                 </div>',
                '<span class="badge bg-info-subtle text-info">Sinkronisasi Bank Soal</span>',
                '<span class="font-monospace text-muted">192.168.1.104</span>',
                '<span class="text-muted">15 menit lalu</span>',
                '<div class="text-end"><span class="badge bg-success"><i class="bi bi-check2"></i> Sukses</span></div>'
            ],
            [
                '3',
                '<div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning p-1.5 fs-xs fw-bold" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;">AS</div>
                    <div><span class="fw-semibold text-dark">Ahmad Siswa</span><small class="text-muted">siswa@example.com</small></div>
                 </div>',
                '<span class="badge bg-warning-subtle text-warning">Peringatan AI: Arah Pandang</span>',
                '<span class="font-monospace text-muted">192.168.1.115</span>',
                '<span class="text-muted">28 menit lalu</span>',
                '<div class="text-end"><span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> Logged</span></div>'
            ],
            [
                '4',
                '<div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-success-subtle text-success p-1.5 fs-xs fw-bold" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;">SA</div>
                    <div><span class="fw-semibold text-dark">Super Administrator</span><small class="text-muted">superadmin@example.com</small></div>
                 </div>',
                '<span class="badge bg-secondary-subtle text-secondary">Update Konfigurasi AI</span>',
                '<span class="font-monospace text-muted">127.0.0.1</span>',
                '<span class="text-muted">1 jam lalu</span>',
                '<div class="text-end"><span class="badge bg-success"><i class="bi bi-check2"></i> Tersimpan</span></div>'
            ]
        ];

        $data = [
            'pageTitle'       => 'Dashboard Administrator',
            'user'            => auth_user(),
            'breadcrumbs'     => $breadcrumbs,
            'stats'           => $stats,
            'recentAuditRows' => $recentAuditRows
        ];

        return $this->view('dashboard/admin', $data, 'layouts/admin');
    }

    /**
     * Dashboard Guru / Pengawas Ujian
     */
    public function guru(): Response
    {
        $breadcrumbs = [
            ['title' => 'Guru', 'url' => base_url('guru/dashboard')],
            ['title' => 'Panel Guru & Pengawas']
        ];

        $stats = [
            'question_banks' => 8,
            'total_items'    => 184,
            'upcoming_exams' => 4,
            'graded_essays'  => 48,
            'pending_essays' => 6,
            'active_rooms'   => 2
        ];

        $examScheduleRows = [
            [
                '1',
                '<div class="fw-semibold text-dark">Penilaian Akhir Semester - Matematika Wajib</div><small class="text-muted">Kode: EXAM-MTK-XII</small>',
                '<span class="badge bg-light text-dark border">XII MIPA 1</span>',
                '<span class="text-muted">18 Sep 2026, 08:00 WIB</span>',
                '<span class="badge bg-secondary-subtle text-secondary">90 Menit</span>',
                '<span class="badge bg-primary-subtle text-primary">34 Siswa</span>',
                '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Siap Dimulai</span>',
                '<div class="text-end">
                    <button class="btn btn-xs btn-outline-primary rounded-pill px-2.5" onclick="App.toast(\'Membuka live stream pengawasan WebRTC...\', \'info\', \'Proctoring Room\')"><i class="bi bi-broadcast me-1"></i> Monitor</button>
                 </div>'
            ],
            [
                '2',
                '<div class="fw-semibold text-dark">Kuis Harian - Fisika Kuantum</div><small class="text-muted">Kode: EXAM-FIS-XII</small>',
                '<span class="badge bg-light text-dark border">XII MIPA 2</span>',
                '<span class="text-muted">19 Sep 2026, 10:30 WIB</span>',
                '<span class="badge bg-secondary-subtle text-secondary">45 Menit</span>',
                '<span class="badge bg-primary-subtle text-primary">32 Siswa</span>',
                '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i> Menunggu Token</span>',
                '<div class="text-end">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="App.toast(\'Edit pengaturan jadwal ujian.\', \'info\')"><i class="bi bi-pencil me-1"></i> Kelola</button>
                 </div>'
            ],
            [
                '3',
                '<div class="fw-semibold text-dark">Ujian Praktik Analisis Kimia Organik</div><small class="text-muted">Kode: EXAM-KIM-XII</small>',
                '<span class="badge bg-light text-dark border">XII MIPA 1</span>',
                '<span class="text-muted">22 Sep 2026, 13:00 WIB</span>',
                '<span class="badge bg-secondary-subtle text-secondary">60 Menit</span>',
                '<span class="badge bg-primary-subtle text-primary">34 Siswa</span>',
                '<span class="badge bg-secondary"><i class="bi bi-calendar-event me-1"></i> Terjadwal</span>',
                '<div class="text-end">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="App.toast(\'Pratinjau soal ujian.\', \'info\')"><i class="bi bi-eye me-1"></i> Soal</button>
                 </div>'
            ]
        ];

        $data = [
            'pageTitle'        => 'Dashboard Guru & Pengawas',
            'user'             => auth_user(),
            'breadcrumbs'      => $breadcrumbs,
            'stats'            => $stats,
            'examScheduleRows' => $examScheduleRows
        ];

        return $this->view('dashboard/guru', $data, 'layouts/guru');
    }

    /**
     * Dashboard Peserta Didik
     */
    public function siswa(): Response
    {
        $breadcrumbs = [
            ['title' => 'Siswa', 'url' => base_url('siswa/dashboard')],
            ['title' => 'Ruang Ujian & Kalibrasi']
        ];

        $examReadiness = [
            'primary_cam'   => 'Siap (Laptop Webcam)',
            'secondary_cam' => 'Standby (Scan QR Smartphone)',
            'network'       => 'Stabil (Latensi 24ms)',
            'browser_lock'  => 'Didukung (Full-Screen & Tab Guard)'
        ];

        $registeredExams = [
            [
                '1',
                '<div class="fw-semibold text-dark">Penilaian Akhir Semester - Matematika Wajib</div><small class="text-muted">Materi: Kalkulus, Vektor, & Dimensi Tiga</small>',
                '<span class="text-dark">Budi Guru, M.Pd</span>',
                '<span class="text-muted">Besok, 08:00 WIB</span>',
                '<span class="badge bg-secondary-subtle text-secondary">90 Menit</span>',
                '<span class="badge bg-primary-subtle text-primary">Dual Cam AI Wajib</span>',
                '<div class="text-end">
                    <button class="btn btn-xs btn-primary-gradient rounded-pill px-3" onclick="App.openCameraDiagnostic()"><i class="bi bi-play-circle me-1"></i> Kalibrasi & Masuk</button>
                 </div>'
            ],
            [
                '2',
                '<div class="fw-semibold text-dark">Kuis Harian - Fisika Kuantum</div><small class="text-muted">Materi: Radiasi Benda Hitam</small>',
                '<span class="text-dark">Siti Rahma, S.Pd</span>',
                '<span class="text-muted">19 Sep 2026, 10:30 WIB</span>',
                '<span class="badge bg-secondary-subtle text-secondary">45 Menit</span>',
                '<span class="badge bg-success-subtle text-success">Kamera Tunggal</span>',
                '<div class="text-end">
                    <button class="btn btn-xs btn-outline-secondary rounded-pill px-3" disabled><i class="bi bi-lock me-1"></i> Belum Dibuka</button>
                 </div>'
            ]
        ];

        $data = [
            'pageTitle'       => 'Ruang Ujian Peserta Didik',
            'user'            => auth_user(),
            'breadcrumbs'     => $breadcrumbs,
            'examReadiness'   => $examReadiness,
            'registeredExams' => $registeredExams
        ];

        return $this->view('dashboard/siswa', $data, 'layouts/peserta');
    }
}
