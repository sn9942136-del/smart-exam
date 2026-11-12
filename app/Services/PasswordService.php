<?php

declare(strict_types=1);

namespace App\Services;

use App\Filters\RateLimitFilter;
use App\Libraries\Session;
use App\Models\AuditLogModel;
use App\Models\OtpTokenModel;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Models\UserSessionModel;

/**
 * Password Service
 * Business logic layer for Forgot Password, OTP verification, and Password Reset.
 */
class PasswordService
{
    private UserModel $userModel;
    private OtpService $otpService;
    private PasswordResetModel $resetModel;
    private UserSessionModel $sessionModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->otpService = new OtpService();
        $this->resetModel = new PasswordResetModel();
        $this->sessionModel = new UserSessionModel();
    }

    /**
     * Permintaan reset kata sandi (generate OTP & kirim notifikasi multi-channel)
     *
     * @param string $emailOrUsername Email, username, NIP/NISN, atau nomor telepon
     * @param string|null $inputPhone Nomor telepon tujuan kustom (opsional)
     * @param array $channels Saluran pengiriman ('email', 'whatsapp')
     * @return array
     */
    public function requestReset(
        string $emailOrUsername,
        ?string $inputPhone = null,
        array $channels = ['email', 'whatsapp']
    ): array {
        $input = trim($emailOrUsername);
        if (empty($input)) {
            return [
                'success' => false,
                'error'   => 'Silakan masukkan email atau username Anda.'
            ];
        }

        $user = $this->userModel->findByCredentials($input);
        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Akun dengan email atau username tersebut tidak ditemukan.'
            ];
        }

        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'error'   => 'Akun Anda sedang tidak aktif. Silakan hubungi Administrator.'
            ];
        }

        // 1. Tentukan email tujuan terdaftar (hanya dari profil akun di database)
        $targetEmail = trim((string)($user['email'] ?? ''));

        // 2. Tentukan nomor WhatsApp terdaftar pada profil akun di database
        $registeredPhone = trim((string)($user['phone'] ?? ''));
        $normRegisteredPhone = !empty($registeredPhone) ? \App\Config\Notification::normalizePhoneNumber($registeredPhone) : '';

        // 3. Verifikasi Konfirmasi Nomor Telepon (Anti-Pengalihan Nomor / Anti-Account Hijacking)
        $inputPhoneClean = !empty($inputPhone) ? trim($inputPhone) : null;
        if (!empty($inputPhoneClean)) {
            $normInputPhone = \App\Config\Notification::normalizePhoneNumber($inputPhoneClean);

            // Jika profil akun belum memiliki nomor terdaftar, nomor input tidak boleh menjadi pengganti sepihak
            if (empty($normRegisteredPhone)) {
                return [
                    'success' => false,
                    'error'   => 'Akun Anda belum memiliki nomor WhatsApp yang terdaftar pada profil. Silakan gunakan saluran Email atau hubungi Administrator.'
                ];
            }

            // Jika nomor input tidak cocok dengan nomor terdaftar pada profil akun, TOLAK!
            if ($normInputPhone !== $normRegisteredPhone) {
                return [
                    'success' => false,
                    'error'   => 'Nomor WhatsApp konfirmasi tidak sesuai dengan nomor yang terdaftar pada akun Anda.'
                ];
            }
        }

        // 4. Evaluasi saluran pengiriman yang valid untuk akun ini
        $activeChannels = [];
        if (in_array('email', $channels, true) && !empty($targetEmail) && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            $activeChannels[] = 'email';
        }

        $targetPhone = null;
        if (in_array('whatsapp', $channels, true)) {
            if (!empty($normRegisteredPhone) && strlen($normRegisteredPhone) >= 8) {
                $activeChannels[] = 'whatsapp';
                $targetPhone = $normRegisteredPhone;
            } elseif (count($channels) === 1 && $channels[0] === 'whatsapp') {
                return [
                    'success' => false,
                    'error'   => 'Akun Anda belum memiliki nomor WhatsApp terdaftar. Silakan pilih saluran Email.'
                ];
            }
        }

        if (empty($activeChannels)) {
            return [
                'success' => false,
                'error'   => 'Tidak ada saluran pengiriman notifikasi yang valid untuk akun ini. Silakan hubungi Administrator.'
            ];
        }

        // Generate 6-digit OTP
        $otpData = $this->otpService->generate($user['id'], 'reset_password');
        $otp = $otpData['otp'];

        // Simpan context reset di session
        Session::set('reset_user_id', (int)$user['id']);
        Session::set('reset_email', $targetEmail);
        Session::set('reset_phone', $targetPhone);
        Session::set('reset_otp_preview', $otp); // Untuk simulator tampilan demo jika aktif

        AuditLogModel::record('REQUEST_OTP', 'auth', (string)$user['id'], null, [
            'email_masked' => self::maskEmail($targetEmail),
            'phone_masked' => !empty($targetPhone) ? self::maskPhone($targetPhone) : null,
            'type'         => 'reset_password',
            'channels'     => $activeChannels
        ]);

        // Hubungkan & kirim notifikasi OTP ke Email dan WhatsApp terdaftar
        $notifResults = [];
        try {
            $notifResults = $this->otpService->sendOtp(
                $user,
                $otp,
                $targetEmail,
                $targetPhone,
                $activeChannels,
                base_url('verify-otp')
            );
        } catch (\Throwable $e) {
            // Error pengiriman eksternal tidak boleh merusak alur reset password
            \App\Libraries\Logger::exception($e);
        }

        return [
            'success'       => true,
            'user_id'       => (int)$user['id'],
            'email'         => $targetEmail,
            'phone'         => $targetPhone,
            'otp'           => $otp,
            'expires_at'    => $otpData['expires_at'],
            'channels'      => $activeChannels,
            'notifications' => $notifResults,
            'sent_email'    => in_array('email', $activeChannels, true) ? $targetEmail : null,
            'sent_phone'    => in_array('whatsapp', $activeChannels, true) ? $targetPhone : null
        ];
    }

    /**
     * Verifikasi kode OTP dan terbitkan token reset password
     */
    public function verifyOtp(int|string $userId, string $otp): array
    {
        $isValid = $this->otpService->validate($userId, $otp, 'reset_password');
        if (!$isValid) {
            return [
                'success' => false,
                'error'   => 'Kode OTP yang Anda masukkan salah atau telah kadaluarsa.'
            ];
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Pengguna tidak valid.'
            ];
        }

        // Buat token reset password berdurasi 30 menit
        $resetToken = $this->resetModel->createToken($user['email'], 30);
        Session::set('reset_verified_token', $resetToken);

        // Bersihkan rate limit counter verify-otp setelah berhasil
        RateLimitFilter::clearLimit('/verify-otp', null, 'guest');

        return [
            'success' => true,
            'token'   => $resetToken,
            'email'   => $user['email']
        ];
    }

    /**
     * Eksekusi penggantian kata sandi baru
     */
    public function resetPassword(string $email, string $token, string $newPassword, string $confirmPassword): array
    {
        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'error'   => 'Kata sandi baru minimal harus memiliki 8 karakter.'
            ];
        }

        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'error'   => 'Konfirmasi kata sandi tidak cocok dengan kata sandi baru.'
            ];
        }

        // Validasi token reset
        $validTokenRecord = $this->resetModel->findValid($email, $token);
        if (!$validTokenRecord) {
            return [
                'success' => false,
                'error'   => 'Sesi token reset password telah kadaluarsa. Silakan ulangi proses lupa password.'
            ];
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Pengguna tidak ditemukan.'
            ];
        }

        // Hash kata sandi baru dengan BCRYPT yang aman
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->userModel->updatePassword($user['id'], $newHash);

        // Hapus token reset yang sudah terpakai
        $this->resetModel->deleteByEmail($email);

        // Force logout seluruh sesi aktif sebelumnya demi keamanan
        $this->sessionModel->deactivateAllForUser($user['id']);

        // Bersihkan session reset
        Session::remove('reset_user_id');
        Session::remove('reset_email');
        Session::remove('reset_phone');
        Session::remove('reset_otp_preview');
        Session::remove('reset_verified_token');

        // Bersihkan rate limit counter forgot-password dan reset-password
        RateLimitFilter::clearLimit('/reset-password', null, 'guest');
        RateLimitFilter::clearLimit('/forgot-password', null, 'guest');

        AuditLogModel::record('RESET_PASSWORD_SUCCESS', 'auth', (string)$user['id'], null, [
            'email' => $email
        ]);

        return [
            'success' => true,
            'message' => 'Kata sandi Anda berhasil diperbarui. Silakan login menggunakan kata sandi baru.'
        ];
    }

    /**
     * Samarkan alamat email untuk privasi (contoh: user@gmail.com -> us***er@gmail.com)
     */
    public static function maskEmail(?string $email): string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', trim($email), 2);
        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } elseif ($len <= 4) {
            $maskedName = substr($name, 0, 1) . '**' . substr($name, -1);
        } else {
            $maskedName = substr($name, 0, 2) . '***' . substr($name, -2);
        }
        return $maskedName . '@' . $domain;
    }

    /**
     * Samarkan nomor telepon WhatsApp untuk privasi (contoh: 62881022834828 -> 6288****4828)
     */
    public static function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        $clean = preg_replace('/[^0-9]/', '', (string)$phone);
        $len = strlen($clean);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        $prefix = substr($clean, 0, 4);
        $suffix = substr($clean, -4);
        return $prefix . '****' . $suffix;
    }
}
