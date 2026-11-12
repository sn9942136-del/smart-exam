<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Response;
use App\Libraries\Session;
use App\Services\AuthService;

/**
 * Auth Controller
 * Handles user login view, submission processing, and logout.
 */
class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->authService = new AuthService();
    }

    /**
     * Tampilkan halaman formulir login
     */
    public function showLogin(): Response
    {
        // Cek auto-login via remember me cookie jika ada
        if (!is_logged_in()) {
            $this->authService->checkRememberToken();
        }

        // Jika sudah login, redirect langsung ke Dashboard masing-masing role
        if (is_logged_in()) {
            $role = user_role() ?? 'peserta_didik';
            return $this->redirect($this->authService->getDashboardUrl($role));
        }

        return $this->view('auth/login', [
            'pageTitle' => 'Masuk ke Sistem Smart Exam'
        ], 'layouts/auth');
    }

    /**
     * Proses submit formulir login
     */
    public function login(): Response
    {
        $login = (string) $this->request->input('login', '');
        $password = (string) $this->request->input('password', '');
        $remember = filter_var($this->request->input('remember', false), FILTER_VALIDATE_BOOLEAN);

        // Server-side validation
        $errors = $this->validate([
            'login'    => $login,
            'password' => $password
        ], [
            'login'    => 'required',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                return $this->json([
                    'status' => 'error',
                    'errors' => $errors
                ], 422);
            }

            Session::setFlash('danger', 'Mohon lengkapi email/username dan kata sandi Anda.');
            Session::set('_old_input_login', $login);
            return $this->back();
        }

        // Jalankan autentikasi melalui AuthService
        $ip = $this->request->getIp();
        $userAgent = $this->request->getUserAgent();
        $result = $this->authService->attempt($login, $password, $remember, $ip, $userAgent);

        if (!$result['success']) {
            if ($this->request->isAjax()) {
                return $this->json([
                    'status'  => 'error',
                    'message' => $result['error']
                ], 401);
            }

            Session::setFlash('danger', $result['error']);
            Session::set('_old_input_login', $login);
            return $this->back();
        }

        // Login Berhasil
        if ($this->request->isAjax()) {
            return $this->json([
                'status'   => 'success',
                'message'  => 'Login berhasil! Mengalihkan ke dashboard...',
                'redirect' => $result['redirect']
            ]);
        }

        Session::setFlash('success', 'Selamat datang kembali, ' . user_name() . '!');
        return $this->redirect($result['redirect']);
    }

    /**
     * Proses logout pengguna
     */
    public function logout(): Response
    {
        $this->authService->logout();
        return $this->redirect(base_url('login'));
    }
}
