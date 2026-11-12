<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;

/**
 * Base Controller
 * Central controller providing view rendering, JSON responses, redirects, and session helpers.
 */
abstract class BaseController
{
    protected Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
    }

    /**
     * Render view dengan layout master
     *
     * @param string $viewPath Lokasi view relatif terhadap app/Views/ (contoh: 'home/index')
     * @param array $data Data variabel yang akan di-extract ke view
     * @param string|null $layout Layout master yang digunakan (default: 'layouts/main', null jika tanpa layout)
     */
    protected function view(string $viewPath, array $data = [], ?string $layout = 'layouts/main'): Response
    {
        $viewFile = __DIR__ . '/../Views/' . trim($viewPath, '/') . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file [{$viewPath}.php] tidak ditemukan di app/Views/");
        }

        // Extract variabel untuk view
        extract($data, EXTR_SKIP);

        // Tangkap output view
        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        // Jika menggunakan layout, render konten ke dalam layout
        if ($layout !== null) {
            $layoutFile = __DIR__ . '/../Views/' . trim($layout, '/') . '.php';
            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout file [{$layout}.php] tidak ditemukan di app/Views/");
            }

            // $content dan variabel $data tersedia di dalam layout
            ob_start();
            include $layoutFile;
            $finalOutput = ob_get_clean();
        } else {
            $finalOutput = $content;
        }

        $response = new Response();
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->setContent($finalOutput);
        return $response;
    }

    /**
     * Alias method untuk view()
     */
    protected function renderView(string $viewPath, array $data = [], ?string $layout = 'layouts/main'): Response
    {
        return $this->view($viewPath, $data, $layout);
    }

    /**
     * Mengembalikan response JSON terstandarisasi
     */
    protected function json(mixed $data, int $statusCode = 200): Response
    {
        $response = new Response();
        return $response->json($data, $statusCode);
    }

    /**
     * Redirect ke URL tertentu
     */
    protected function redirect(string $url, int $statusCode = 302): Response
    {
        if (!preg_match('#^https?://#i', $url)) {
            $url = base_url($url);
        }

        $response = new Response();
        return $response->redirect($url, $statusCode);
    }

    /**
     * Redirect kembali ke halaman sebelumnya
     */
    protected function back(int $statusCode = 302): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? base_url();
        return $this->redirect($referer, $statusCode);
    }

    /**
     * Set Flash Message ke session
     */
    protected function setFlash(string $type, string $message): void
    {
        Session::setFlash($type, $message);
    }

    /**
     * Validasi input request sederhana
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $val = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && (empty($val) && $val !== '0')) {
                    $errors[$field][] = "Kolom {$field} wajib diisi.";
                }
                if ($rule === 'email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "Format email pada {$field} tidak valid.";
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (!empty($val) && strlen((string)$val) < $min) {
                        $errors[$field][] = "Panjang {$field} minimal {$min} karakter.";
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (!empty($val) && strlen((string)$val) > $max) {
                        $errors[$field][] = "Panjang {$field} maksimal {$max} karakter.";
                    }
                }
            }
        }

        return $errors;
    }
}
