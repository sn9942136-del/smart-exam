<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OtpTokenModel;

/**
 * OTP Service
 * Business logic layer for OTP generation, expiration rules, and single-use verification.
 */
class OtpService
{
    private OtpTokenModel $otpModel;

    public function __construct()
    {
        $this->otpModel = new OtpTokenModel();
    }

    /**
     * Generate kode OTP 6-digit baru untuk pengguna (Masa berlaku 5 menit)
     */
    public function generate(int|string $userId, string $type = 'reset_password', int $expiresMinutes = 5): array
    {
        return $this->otpModel->generateOtp($userId, $type, $expiresMinutes);
    }

    /**
     * Validasi kode OTP
     */
    public function validate(int|string $userId, string $otp, string $type = 'reset_password'): bool
    {
        $cleanOtp = trim($otp);
        if (strlen($cleanOtp) !== 6 || !ctype_digit($cleanOtp)) {
            return false;
        }

        return $this->otpModel->verifyOtp($userId, $cleanOtp, $type);
    }

    /**
     * Dapatkan OTP aktif untuk keperluan simulasi di view
     */
    public function getActiveOtp(int|string $userId, string $type = 'reset_password'): ?string
    {
        return $this->otpModel->getLatestActiveOtp($userId, $type);
    }

    /**
     * Kirim notifikasi kode OTP ke saluran Email dan WhatsApp secara terstruktur
     *
     * @param array|int|string $userOrId Data pengguna atau ID
     * @param string $otp Kode 6 digit OTP
     * @param string $email Alamat email tujuan
     * @param string|null $phone Nomor telepon WhatsApp tujuan
     * @param array $channels Saluran pengiriman ('email', 'whatsapp')
     * @param string|null $actionUrl Tautan verifikasi halaman
     * @return array Status pengiriman per-channel
     */
    public function sendOtp(
        array|int|string $userOrId,
        string $otp,
        string $email,
        ?string $phone = null,
        array $channels = ['email', 'whatsapp'],
        ?string $actionUrl = null
    ): array {
        $userId = is_array($userOrId) ? (int)($userOrId['id'] ?? 0) : (int)$userOrId;
        $userName = is_array($userOrId) ? ($userOrId['name'] ?? null) : null;

        $targetEmail = trim($email);
        $targetPhone = !empty($phone) ? trim($phone) : null;
        $url = !empty($actionUrl) ? $actionUrl : base_url('verify-otp');

        $queueService = new \App\Services\NotificationQueueService();
        $logModel = new \App\Models\NotificationLogModel();

        $results = [
            'email'    => null,
            'whatsapp' => null
        ];

        // 1. Saluran Email
        if (in_array('email', $channels, true) && !empty($targetEmail) && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $htmlBody = \App\Libraries\SmtpClient::buildOtpHtmlTemplate($otp, $userName, 5, $url);
            $textBody = \App\Libraries\SmtpClient::buildOtpTextTemplate($otp, $userName, 5, $url);

            $enqueueRes = $queueService->enqueue(null, 'email', $targetEmail, [
                'type'                  => 'otp_verification',
                'otp'                   => $otp,
                'title'                 => 'Kode Verifikasi OTP Smart Exam',
                'subject'               => '[Smart Exam] Kode Verifikasi OTP: ' . $otp,
                'preview'               => "Kode OTP Anda: {$otp}. Berlaku 5 menit.",
                'message'               => "Kode OTP Anda adalah {$otp}. Berlaku selama 5 menit.",
                'html_body'             => $htmlBody,
                'text_body'             => $textBody,
                'action_url'            => $url,
                'user_id'               => $userId > 0 ? $userId : null,
                'user_name'             => $userName,
                'source'                => 'auth_otp'
            ]);

            $logId = (int)($enqueueRes['log_id'] ?? 0);
            if ($logId > 0) {
                // Eksekusi pengiriman langsung via processItem agar pengguna tidak menunggu cron/daemon
                $logRow = $logModel->find($logId);
                if ($logRow) {
                    $itemResult = $queueService->processItem($logRow, 'otp_immediate_worker');
                    $results['email'] = [
                        'success'   => ($itemResult['status'] ?? '') === 'sent',
                        'status'    => $itemResult['status'] ?? 'unknown',
                        'log_id'    => $logId,
                        'recipient' => $targetEmail,
                        'message'   => $itemResult['message'] ?? 'Diproses'
                    ];
                }
            }
        }

        // 2. Saluran WhatsApp
        if (in_array('whatsapp', $channels, true) && !empty($targetPhone)) {
            $normPhone = \App\Config\Notification::normalizePhoneNumber($targetPhone);
            if (!empty($normPhone) && strlen($normPhone) >= 8) {
                $waMessage = \App\Libraries\WhatsAppClient::formatOtpMessage($otp, $userName, 5);

                $enqueueWa = $queueService->enqueue(null, 'whatsapp', $normPhone, [
                    'type'                  => 'otp_verification',
                    'otp'                   => $otp,
                    'title'                 => 'Kode Verifikasi OTP',
                    'message'               => $waMessage,
                    'formatted_message'     => $waMessage,
                    'preview'               => "Kode OTP Anda: {$otp}",
                    'action_url'            => $url,
                    'user_id'               => $userId > 0 ? $userId : null,
                    'user_name'             => $userName,
                    'source'                => 'auth_otp'
                ]);

                $waLogId = (int)($enqueueWa['log_id'] ?? 0);
                if ($waLogId > 0) {
                    $waLogRow = $logModel->find($waLogId);
                    if ($waLogRow) {
                        $waItemResult = $queueService->processItem($waLogRow, 'otp_immediate_worker');
                        $results['whatsapp'] = [
                            'success'   => ($waItemResult['status'] ?? '') === 'sent',
                            'status'    => $waItemResult['status'] ?? 'unknown',
                            'log_id'    => $waLogId,
                            'recipient' => $normPhone,
                            'message'   => $waItemResult['message'] ?? 'Diproses'
                        ];
                    }
                }
            }
        }

        return $results;
    }
}
