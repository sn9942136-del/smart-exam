<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\App;
use App\Libraries\Database;
use App\Libraries\Response;

/**
 * Home Controller
 * Handles the main landing page and system health check.
 */
class HomeController extends BaseController
{
    /**
     * Halaman Landing Page Utama
     */
    public function index(): Response
    {
        $data = [
            'pageTitle'   => 'Smart Exam - Platform Ujian Digital Terpadu',
            'appName'     => App::$appName,
            'appEnv'      => App::$appEnv,
            'dbConnected' => Database::isConnected(),
            'features'    => [
                [
                    'icon'        => 'bi-camera-video',
                    'badge'       => 'Primary Camera',
                    'title'       => 'AI Facial & Gaze Tracking',
                    'description' => 'Kamera depan mendeteksi pergerakan kepala, arah pandangan mata (gaze), serta kehadiran wajah peserta secara real-time.'
                ],
                [
                    'icon'        => 'bi-phone',
                    'badge'       => 'Secondary Camera',
                    'title'       => 'Side & Desk Monitoring',
                    'description' => 'Kamera sekunder (smartphone/webcam samping) mengawasi lingkungan meja, posisi tangan, dan perangkat sekunder di sekitar peserta.'
                ],
                [
                    'icon'        => 'bi-shield-check',
                    'badge'       => 'Anti-Fraud Engine',
                    'title'       => 'Multi-Person & Tab Lock',
                    'description' => 'Deteksi instan jika terdapat orang lain di ruangan, upaya pergantian tab, window blur, atau intervensi suara.'
                ],
                [
                    'icon'        => 'bi-graph-up-arrow',
                    'badge'       => 'Live Proctoring',
                    'title'       => 'Dashboard Pengawas Real-Time',
                    'description' => 'Guru dan Pengawas dapat memantau feed dual-kamera seluruh peserta secara bersamaan dengan penandaan skor integritas otomatis.'
                ]
            ],
            'roles'       => [
                [
                    'role'  => 'Super Admin / Admin',
                    'desc'  => 'Manajemen pengguna, pengaturan master data, konfigurasi model AI, dan audit sistem menyeluruh.',
                    'color' => 'danger'
                ],
                [
                    'role'  => 'Guru / Pengawas',
                    'desc'  => 'Pembuatan bank soal, penjadwalan ujian, pemantauan proctoring live, dan validasi rekaman kecurangan.',
                    'color' => 'primary'
                ],
                [
                    'role'  => 'Peserta Didik',
                    'desc'  => 'Ujian online dengan kalibrasi dual-kamera mudah, antarmuka pengerjaan responsif, dan feedback real-time.',
                    'color' => 'success'
                ]
            ]
        ];

        return $this->view('home/index', $data);
    }

    /**
     * Health Check Endpoint (JSON)
     */
    public function health(): Response
    {
        return $this->json([
            'status'      => 'ok',
            'application' => App::$appName,
            'environment' => App::$appEnv,
            'php_version' => PHP_VERSION,
            'database'    => Database::isConnected() ? 'connected' : 'disconnected',
            'timestamp'   => date('Y-m-d H:i:s'),
            'timezone'    => App::$timezone
        ]);
    }
}
