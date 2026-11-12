<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamModel;
use App\Models\ExamParticipantModel;
use App\Models\ExamSessionModel;

/**
 * Exam Timer Service
 * Mengelola otoritas waktu server (Server Clock Authority), kalkulasi deadline presisi,
 * sinkronisasi sisa waktu ujian, dan validasi sesi aktif terpusat.
 */
class ExamTimerService
{
    private ExamSessionModel $sessionModel;
    private ExamModel $examModel;
    private ExamParticipantModel $participantModel;

    public function __construct()
    {
        $this->sessionModel = new ExamSessionModel();
        $this->examModel = new ExamModel();
        $this->participantModel = new ExamParticipantModel();
    }

    /**
     * Hitung deadline Unix Timestamp absolut untuk sesi ujian.
     * Deadline ditentukan oleh waktu mulai sesi + durasi ujian (dalam detik),
     * serta dibatasi oleh jadwal kelas (schedule end_time) jika ada.
     */
    public function calculateDeadline(array $session, array $exam, ?array $schedule = null): int
    {
        $sessionStartTime = strtotime($session['start_time']);
        $durationMinutes = (int)($exam['duration_minutes'] ?? 60);
        $durationSeconds = $durationMinutes * 60;
        $maxEndTime = $sessionStartTime + $durationSeconds;

        if ($schedule && !empty($schedule['end_time'])) {
            $scheduleEndTime = strtotime($schedule['end_time']);
            if ($scheduleEndTime > 0 && $scheduleEndTime < $maxEndTime) {
                $maxEndTime = $scheduleEndTime;
            }
        }

        return $maxEndTime;
    }

    /**
     * Hitung sisa detik waktu ujian berdasarkan jam server real-time.
     */
    public function getRemainingSeconds(array $session, array $exam, ?array $schedule = null): int
    {
        $deadline = $this->calculateDeadline($session, $exam, $schedule);
        $now = time();
        return max(0, $deadline - $now);
    }

    /**
     * Validasi Sesi Ujian Aktif Terpusat (Server-Side Authority)
     *
     * Memeriksa 7 kondisi minimal:
     * 1. Session exists di database
     * 2. Session milik student yang sedang login
     * 3. Status session masih 'ongoing' (bukan submitted/terminated/paused)
     * 4. Ujian exists dan masih valid (tidak dihapus / tidak nonaktif)
     * 5. Current server time belum melewati deadline
     * 6. Session belum diterminasi / diskualifikasi
     * 7. Schedule ujian masih valid jika diberlakukan
     *
     * @param string $sessionToken Token sesi unik ujian
     * @param int $studentId ID siswa yang sedang diautentikasi
     * @return array{
     *     valid: bool,
     *     code: string,
     *     error: ?string,
     *     session?: array,
     *     exam?: array,
     *     schedule?: ?array,
     *     deadline_timestamp?: int,
     *     remaining_seconds?: int,
     *     is_expired?: bool,
     *     server_time?: string
     * }
     */
    public function validateActiveExamSession(string $sessionToken, int $studentId): array
    {
        $now = time();
        $serverTimeFormatted = date('Y-m-d H:i:s', $now);

        // 1. Validasi keberadaan sesi
        $session = $this->sessionModel->findByToken($sessionToken);
        if (!$session) {
            return [
                'valid'       => false,
                'code'        => 'SESSION_NOT_FOUND',
                'error'       => 'Sesi ujian tidak ditemukan.',
                'server_time' => $serverTimeFormatted
            ];
        }

        // 2. Validasi kepemilikan sesi (Student Authorization)
        if ((int)$session['student_id'] !== $studentId) {
            return [
                'valid'       => false,
                'code'        => 'UNAUTHORIZED',
                'error'       => 'Akses ditolak: Anda tidak memiliki izin untuk sesi ujian peserta lain.',
                'server_time' => $serverTimeFormatted
            ];
        }

        // 3. Validasi status sesi (Ongoing / Submitted / Terminated)
        if ($session['status'] === 'submitted') {
            return [
                'valid'              => false,
                'code'               => 'ALREADY_SUBMITTED',
                'error'              => 'Sesi ujian telah selesai dan dikumpulkan.',
                'session'            => $session,
                'remaining_seconds'  => 0,
                'is_expired'         => true,
                'server_time'        => $serverTimeFormatted
            ];
        }

        if ($session['status'] === 'terminated') {
            return [
                'valid'              => false,
                'code'               => 'TERMINATED',
                'error'              => 'Sesi ujian telah dihentikan/didiskualifikasi oleh pengawas ruangan.',
                'session'            => $session,
                'remaining_seconds'  => 0,
                'is_expired'         => true,
                'server_time'        => $serverTimeFormatted
            ];
        }

        if ($session['status'] !== 'ongoing') {
            return [
                'valid'              => false,
                'code'               => 'NOT_ONGOING',
                'error'              => 'Status sesi ujian tidak valid atau sedang dijeda (' . ($session['status'] ?? 'unknown') . ').',
                'session'            => $session,
                'remaining_seconds'  => 0,
                'is_expired'         => true,
                'server_time'        => $serverTimeFormatted
            ];
        }

        // 4. Validasi keberadaan data ujian
        $examId = (int)$session['exam_id'];
        $exam = $this->examModel->find($examId);
        if (!$exam || !empty($exam['deleted_at'])) {
            return [
                'valid'       => false,
                'code'        => 'EXAM_NOT_FOUND',
                'error'       => 'Data ujian tidak ditemukan atau telah dihapus dari sistem.',
                'session'     => $session,
                'server_time' => $serverTimeFormatted
            ];
        }

        if (in_array($exam['status'], ['draft', 'archived'], true)) {
            return [
                'valid'       => false,
                'code'        => 'EXAM_INACTIVE',
                'error'       => 'Ujian saat ini sedang tidak aktif atau diarsipkan oleh guru pengampu.',
                'session'     => $session,
                'exam'        => $exam,
                'server_time' => $serverTimeFormatted
            ];
        }

        // 5, 6, 7. Validasi Deadline & Jadwal Kelas
        $accessValidation = $this->participantModel->validateStudentAccess($examId, $studentId);
        $schedule = $accessValidation['schedule'] ?? null;

        $deadlineTimestamp = $this->calculateDeadline($session, $exam, $schedule);
        $remainingSeconds = max(0, $deadlineTimestamp - $now);
        $isExpired = ($now >= $deadlineTimestamp || $remainingSeconds <= 0);

        if ($isExpired) {
            // Perbarui sisa detik sesi ke 0 di database jika belum
            if ((int)($session['remaining_seconds'] ?? 1) > 0) {
                $this->sessionModel->updateRemainingSeconds((int)$session['id'], 0);
            }

            return [
                'valid'              => false,
                'code'               => 'EXPIRED',
                'error'              => 'Waktu pengerjaan ujian telah berakhir (Batas Waktu Server Terlampaui).',
                'session'            => $session,
                'exam'               => $exam,
                'schedule'           => $schedule,
                'deadline_timestamp' => $deadlineTimestamp,
                'remaining_seconds'  => 0,
                'is_expired'         => true,
                'server_time'        => $serverTimeFormatted
            ];
        }

        return [
            'valid'              => true,
            'code'               => 'OK',
            'error'              => null,
            'session'            => $session,
            'exam'               => $exam,
            'schedule'           => $schedule,
            'deadline_timestamp' => $deadlineTimestamp,
            'remaining_seconds'  => $remainingSeconds,
            'is_expired'         => false,
            'server_time'        => $serverTimeFormatted
        ];
    }

    /**
     * Sinkronisasi sisa waktu pengerjaan sesi ujian (Server Time Authority)
     *
     * @return array{success: bool, remaining_seconds: int, is_expired: bool, server_time: string, session_status?: string, start_time?: string, error?: string}
     */
    public function syncTimer(string $sessionToken, int $studentId): array
    {
        $validation = $this->validateActiveExamSession($sessionToken, $studentId);

        if (!$validation['valid']) {
            $code = $validation['code'];

            if ($code === 'ALREADY_SUBMITTED' || $code === 'TERMINATED') {
                return [
                    'success'           => true,
                    'remaining_seconds' => 0,
                    'is_expired'        => true,
                    'server_time'       => date('Y-m-d H:i:s'),
                    'session_status'    => $validation['session']['status'] ?? strtolower($code)
                ];
            }

            if ($code === 'EXPIRED') {
                return [
                    'success'           => true,
                    'remaining_seconds' => 0,
                    'is_expired'        => true,
                    'server_time'       => date('Y-m-d H:i:s'),
                    'session_status'    => 'ongoing'
                ];
            }

            return [
                'success'           => false,
                'remaining_seconds' => 0,
                'is_expired'        => true,
                'server_time'       => date('Y-m-d H:i:s'),
                'error'             => $validation['error'] ?? 'Sesi ujian tidak valid.'
            ];
        }

        $session = $validation['session'];
        $remainingSeconds = $validation['remaining_seconds'];

        // Perbarui sisa detik ke database
        $this->sessionModel->updateRemainingSeconds((int)$session['id'], $remainingSeconds);

        return [
            'success'           => true,
            'remaining_seconds' => $remainingSeconds,
            'is_expired'        => false,
            'server_time'       => date('Y-m-d H:i:s'),
            'start_time'        => $session['start_time']
        ];
    }

    /**
     * Cek apakah waktu sesi ujian telah habis berdasarkan kalkulasi jam server
     */
    public function isExpired(string $sessionToken, ?int $studentId = null): bool
    {
        $session = $this->sessionModel->findByToken($sessionToken);
        if (!$session) {
            return true;
        }

        if ($session['status'] !== 'ongoing') {
            return true;
        }

        $stId = $studentId ?? (int)$session['student_id'];
        $validation = $this->validateActiveExamSession($sessionToken, $stId);
        return !$validation['valid'] && ($validation['code'] === 'EXPIRED' || ($validation['is_expired'] ?? false));
    }
}
