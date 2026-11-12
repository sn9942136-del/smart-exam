<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\PasswordService;

/**
 * Password Controller
 * Handles forgot password request, 6-digit OTP verification, and new password submission.
 */
class PasswordController extends BaseController
{
    private PasswordService $passwordService;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->passwordService = new PasswordService();
    }

    /**
     * Tampilkan formulir Permohonan Lupa Kata Sandi
     */
    public function showForgotPassword(): Response
    {
        if (is_logged_in()) {
            return $this->redirect(base_url());
        }

        return $this->view('auth/forgot_password', [
            'pageTitle' => 'Lupa Kata Sandi - Smart Exam'
        ], 'layouts/auth');
    }

    /**
     * Proses kirim OTP untuk reset kata sandi
     */
    public function sendOtp(): Response
    {
        $login = (string) $this->request->input('login', '');
        $phone = (string) $this->request->input('phone', '');
        $rawChannels = $this->request->input('channels', ['email', 'whatsapp']);
        $channels = is_array($rawChannels) ? $rawChannels : ['email', 'whatsapp'];

        Session::set('_old_input_login', $login);
        Session::set('_old_input_phone', $phone);
        Session::remove('_old_input_otp');

        $errors = $this->validate(['login' => $login], ['login' => 'required']);
        if (!empty($errors)) {
            Session::setFlash('danger', 'Silakan masukkan email atau username Anda.');
            return $this->back();
        }

        try {
            $result = $this->passwordService->requestReset($login, $phone, $channels);
        } catch (\Throwable $e) {
            \App\Libraries\Logger::exception($e);
            Session::setFlash('danger', 'Terjadi kendala pada koneksi sistem: ' . $e->getMessage() . '. Silakan pastikan layanan database aktif dan coba lagi.');
            return $this->back();
        }

        if (!$result['success']) {
            Session::setFlash('danger', $result['error']);
            return $this->back();
        }

        $destinations = [];
        if (!empty($result['sent_email'])) {
            $destinations[] = 'Email (' . PasswordService::maskEmail($result['sent_email']) . ')';
        }
        if (!empty($result['sent_phone'])) {
            $destinations[] = 'WhatsApp (' . PasswordService::maskPhone($result['sent_phone']) . ')';
        }

        $destText = !empty($destinations) 
            ? ' ke ' . implode(' dan ', $destinations) 
            : ' untuk akun Anda';

        $notifDetails = $result['notifications'] ?? [];
        $emailSent = !empty($notifDetails['email']['success']);
        $waSent = !empty($notifDetails['whatsapp']['success']);

        if ($emailSent && !$waSent && in_array('whatsapp', $channels, true)) {
            Session::setFlash('info', 'Kode verifikasi OTP 6-digit berhasil dikirimkan ke Email Anda. (Untuk WhatsApp: pastikan nomor pengirim di dashboard Fonnte sudah berstatus connect).');
        } else {
            Session::setFlash('info', 'Kode verifikasi OTP 6-digit berhasil dikirimkan' . $destText . '. Silakan periksa pesan Anda.');
        }

        return $this->redirect(base_url('verify-otp'));
    }

    /**
     * Tampilkan formulir Verifikasi OTP
     */
    public function showVerifyOtp(): Response
    {
        $userId = Session::get('reset_user_id');
        $email = Session::get('reset_email');
        $phone = Session::get('reset_phone');
        $otpPreview = Session::get('reset_otp_preview');

        if (empty($userId) || empty($email)) {
            Session::setFlash('warning', 'Sesi verifikasi tidak ditemukan. Silakan masukkan email Anda terlebih dahulu.');
            return $this->redirect(base_url('forgot-password'));
        }

        return $this->view('auth/verify_otp', [
            'pageTitle'   => 'Verifikasi Kode OTP',
            'email'       => $email,
            'phone'       => $phone,
            'maskedEmail' => PasswordService::maskEmail($email),
            'maskedPhone' => PasswordService::maskPhone($phone),
            'otpPreview'  => '' // Dikosongkan agar pengguna mengetik manual dari email/whatsapp
        ], 'layouts/auth');
    }

    /**
     * Proses verifikasi kode OTP yang dimasukkan
     */
    public function verifyOtp(): Response
    {
        $userId = Session::get('reset_user_id');
        if (empty($userId)) {
            Session::setFlash('warning', 'Sesi verifikasi telah kadaluarsa.');
            return $this->redirect(base_url('forgot-password'));
        }

        $otp = (string) $this->request->input('otp', '');
        if (empty($otp)) {
            Session::setFlash('danger', 'Silakan masukkan 6 digit kode OTP.');
            return $this->back();
        }

        try {
            $result = $this->passwordService->verifyOtp($userId, $otp);
        } catch (\Throwable $e) {
            \App\Libraries\Logger::exception($e);
            Session::setFlash('danger', 'Gagal memverifikasi OTP: ' . $e->getMessage());
            return $this->back();
        }

        if (!$result['success']) {
            Session::setFlash('danger', $result['error']);
            return $this->back();
        }

        Session::setFlash('success', 'Kode OTP terverifikasi dengan benar! Silakan masukkan kata sandi baru Anda.');
        return $this->redirect(base_url('reset-password'));
    }

    /**
     * Tampilkan formulir Reset Kata Sandi Baru
     */
    public function showResetPassword(): Response
    {
        $email = Session::get('reset_email');
        $token = Session::get('reset_verified_token');

        if (empty($email) || empty($token)) {
            Session::setFlash('warning', 'Akses reset password tidak valid atau telah selesai.');
            return $this->redirect(base_url('forgot-password'));
        }

        return $this->view('auth/reset_password', [
            'pageTitle' => 'Atur Ulang Kata Sandi Baru',
            'email'     => $email,
            'token'     => $token
        ], 'layouts/auth');
    }

    /**
     * Proses simpan kata sandi baru
     */
    public function resetPassword(): Response
    {
        $email = Session::get('reset_email');
        $token = (string) $this->request->input('token', Session::get('reset_verified_token'));
        $newPassword = (string) $this->request->input('new_password', '');
        $confirmPassword = (string) $this->request->input('confirm_password', '');

        if (empty($email) || empty($token)) {
            Session::setFlash('danger', 'Sesi pengaturan ulang kata sandi tidak valid.');
            return $this->redirect(base_url('forgot-password'));
        }

        try {
            $result = $this->passwordService->resetPassword($email, $token, $newPassword, $confirmPassword);
        } catch (\Throwable $e) {
            \App\Libraries\Logger::exception($e);
            Session::setFlash('danger', 'Gagal mereset kata sandi: ' . $e->getMessage());
            return $this->back();
        }

        if (!$result['success']) {
            Session::setFlash('danger', $result['error']);
            return $this->back();
        }

        Session::setFlash('success', $result['message']);
        return $this->redirect(base_url('login'));
    }
}
