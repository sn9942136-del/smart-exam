<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Config\App;
use Throwable;

/**
 * Google Gemini Provider
 *
 * Integrasi resmi Google Gemini API.
 *
 * Spesifikasi Arsitektur:
 * - Model Utama: GEMINI_MODEL (default: gemini-3.8-flash)
 * - Model Cadangan (Fallback): GEMINI_FALLBACK_MODEL (default: gemini-2.5-flash)
 * - Autentikasi: Header `x-goog-api-key` (API Key tidak pernah diletakkan di URL/query string)
 * - Automatic Retry: Exponential backoff dengan jitter untuk HTTP 408, 429, 500, 502, 503, 504 dan cURL network errors.
 * - Tanpa fallback diam-diam ke Mock: Error dilaporkan secara transparan.
 */
class GeminiProvider implements AiProviderInterface
{
    private ?string $apiKey;
    private string $model;
    private ?string $fallbackModel;
    private string $apiBaseUrl;
    private int $maxRetries;

    /**
     * Constructor
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?string $fallbackModel = null,
        ?string $apiBaseUrl = null,
        int $maxRetries = 3
    ) {
        $this->apiKey = $apiKey
            ?? (function_exists('system_setting') ? (system_setting('gemini_api_key') ?: null) : null)
            ?? $_ENV['GEMINI_API_KEY']
            ?? $_ENV['AI_API_KEY']
            ?? (class_exists(App::class) ? App::env('GEMINI_API_KEY') : null)
            ?? (class_exists(App::class) ? App::env('AI_API_KEY') : null)
            ?? getenv('GEMINI_API_KEY')
            ?? getenv('AI_API_KEY')
            ?: null;

        $this->model = $model
            ?? (function_exists('system_setting') ? (system_setting('gemini_model') ?: null) : null)
            ?? $_ENV['GEMINI_MODEL']
            ?? (class_exists(App::class) ? App::env('GEMINI_MODEL', 'gemini-3.5-flash-lite') : 'gemini-3.5-flash-lite');

        $this->fallbackModel = $fallbackModel
            ?? (function_exists('system_setting') ? (system_setting('gemini_fallback_model') ?: null) : null)
            ?? $_ENV['GEMINI_FALLBACK_MODEL']
            ?? (class_exists(App::class) ? App::env('GEMINI_FALLBACK_MODEL', 'gemini-flash-lite-latest,gemini-2.5-flash') : 'gemini-flash-lite-latest,gemini-2.5-flash');

        $this->apiBaseUrl = rtrim(
            $apiBaseUrl
                ?? (class_exists(App::class) ? App::env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta') : 'https://generativelanguage.googleapis.com/v1beta'),
            '/'
        );

        $this->maxRetries = max(1, $maxRetries);
    }

    /**
     * Nama provider
     */
    public function getProviderName(): string
    {
        return 'gemini';
    }

    /**
     * Mengecek apakah API Key tersedia
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Mendapatkan model aktif saat ini
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Mendapatkan model fallback yang dikonfigurasi
     */
    public function getFallbackModel(): ?string
    {
        return $this->fallbackModel;
    }

    /**
     * Memeriksa apakah status HTTP termasuk kriteria retry (408, 429, 500, 502, 503, 504)
     */
    public function isRetryableHttpCode(int $httpCode): bool
    {
        return ($httpCode === 408 || $httpCode === 429 || $httpCode >= 500);
    }

    /**
     * Memeriksa apakah respons error Google mengindikasikan kuota habis (bukan rate-limit sementara)
     */
    public function isQuotaExhausted(int $httpCode, ?array $decodedError): bool
    {
        if ($httpCode !== 429 || empty($decodedError)) {
            return false;
        }

        $status = (string)($decodedError['error']['status'] ?? '');
        $message = strtolower((string)($decodedError['error']['message'] ?? ''));

        // Status resmi RESOURCE_EXHAUSTED dari Google RPC
        if ($status === 'RESOURCE_EXHAUSTED') {
            return true;
        }

        // Pola pesan kuota harian habis / limit tercapai
        if (str_contains($message, 'quota exceeded') ||
            str_contains($message, 'exceeded your current quota') ||
            str_contains($message, 'free_tier_requests') ||
            str_contains($message, 'billing details') ||
            str_contains($message, 'rate_limit_exceeded')) {
            return true;
        }

        // Deteksi dari metadata details jika tersedia
        if (!empty($decodedError['error']['details']) && is_array($decodedError['error']['details'])) {
            foreach ($decodedError['error']['details'] as $detail) {
                $reason = (string)($detail['reason'] ?? '');
                if ($reason === 'RATE_LIMIT_EXCEEDED' || $reason === 'RESOURCE_EXHAUSTED') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Menghapus referensi API key dari pesan error untuk keamanan log/respons
     */
    public function sanitizeErrorMessage(string $message): string
    {
        if (!empty($this->apiKey) && strlen($this->apiKey) > 5) {
            $message = str_replace($this->apiKey, '[REDACTED_API_KEY]', $message);
        }
        $message = (string) preg_replace('/(key=|api_key=|x-goog-api-key:\s*)([A-Za-z0-9_.-]{10,})/i', '$1[REDACTED_API_KEY]', $message);
        return $message;
    }

    /**
     * Generate completion menggunakan Gemini API
     */
    public function generateCompletion(string $prompt, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error'   => 'Google Gemini API Key tidak dikonfigurasi. Pastikan GEMINI_API_KEY tersedia di file .env.'
            ];
        }

        $systemPrompt = $options['system_instruction']
            ?? 'You are an expert Indonesian educational curriculum assessment AI. You must respond ONLY with valid JSON matching the requested structure.';

        $temperature = (float) ($options['temperature'] ?? 0.7);
        $maxOutputTokens = (int) ($options['max_tokens'] ?? 8192);

        $responseMimeType = (string)($options['response_mime_type'] ?? ($options['json_mode'] ?? false ? 'application/json' : 'text/plain'));

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'text' => "System Instructions:\n" . $systemPrompt . "\n\nUser Request:\n" . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'      => $temperature,
                'maxOutputTokens'  => $maxOutputTokens,
                'responseMimeType' => $responseMimeType
            ]
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            return [
                'success' => false,
                'error'   => 'Gagal mengenkripsi request JSON untuk Gemini API.'
            ];
        }

        // 1. Coba request dengan model utama
        $response = $this->executeRequestWithRetry($this->model, $jsonPayload);

        // 2. Jika model utama gagal (404, 429 kuota habis/rate limit, 5xx, atau transport error), alihkan ke fallback
        if (!$response['success'] && !empty($this->fallbackModel)) {
            $shouldTryFallback = ($response['http_code'] === 404 
                || $response['http_code'] >= 500 
                || $response['http_code'] === 429 
                || $response['http_code'] === 0
                || !empty($response['is_quota_exhausted']));

            if ($shouldTryFallback) {
                $candidates = array_map('trim', explode(',', $this->fallbackModel));
                $lastFallbackResponse = null;
                $attemptedFallback = false;

                foreach ($candidates as $fallbackCandidate) {
                    if (empty($fallbackCandidate) || $fallbackCandidate === $this->model) {
                        continue;
                    }
                    $attemptedFallback = true;
                    // Untuk model fallback, batasi maksimal 2 percobaan agar tidak menimbulkan rantai delay berulang
                    $fallbackResponse = $this->executeRequestWithRetry($fallbackCandidate, $jsonPayload, 2);
                    if ($fallbackResponse['success']) {
                        $fallbackResponse['fallback_used'] = true;
                        $fallbackResponse['primary_model'] = $this->model;
                        $fallbackResponse['actual_model'] = $fallbackCandidate;
                        return $fallbackResponse;
                    }
                    $lastFallbackResponse = $fallbackResponse;
                }

                if ($attemptedFallback && $lastFallbackResponse !== null) {
                    $fallbackHttpCode = $lastFallbackResponse['http_code'] ?? 500;
                    $primaryHttpCode = $response['http_code'] ?? 500;
                    $rawError = strtolower((string)($lastFallbackResponse['error'] ?? $response['error'] ?? ''));

                    if ($fallbackHttpCode === 503 || str_contains($rawError, 'high demand') || str_contains($rawError, 'unavailable')) {
                        $userFriendlyMessage = 'Layanan Google Gemini AI sedang mengalami lonjakan antrean server (HTTP 503 High Demand). Gangguan berasal dari server penyedia AI, mohon coba kembali beberapa saat lagi.';
                    } elseif (!empty($response['is_quota_exhausted']) || $fallbackHttpCode === 429) {
                        $userFriendlyMessage = 'Layanan asisten AI sedang mencapai batas kuota penggunaan harian API. Mohon coba kembali beberapa saat lagi.';
                    } elseif ($fallbackHttpCode === 408 || $fallbackHttpCode === 0) {
                        $userFriendlyMessage = 'Koneksi ke server Google AI mengalami waktu tunggu habis (timeout). Silakan periksa jaringan dan coba lagi.';
                    } else {
                        $userFriendlyMessage = 'Layanan asisten AI sedang mengalami kendala teknis sementara. Silakan coba beberapa saat lagi.';
                    }

                    $safeTechnicalSummary = $this->sanitizeErrorMessage(
                        "Model utama ({$this->model}) [HTTP {$primaryHttpCode}] dan model cadangan (" . ($lastFallbackResponse['model'] ?? 'fallback') . ") [HTTP {$fallbackHttpCode}] gagal merespons: " . ($lastFallbackResponse['error'] ?? $response['error'] ?? 'Layanan AI sedang tidak tersedia.')
                    );

                    return [
                        'success'            => false,
                        'error'              => $userFriendlyMessage . ' [Detail: ' . $safeTechnicalSummary . ']',
                        'user_message'       => $userFriendlyMessage,
                        'technical_error'    => $safeTechnicalSummary,
                        'http_code'          => $fallbackHttpCode,
                        'primary_error'      => $response['error'] ?? null,
                        'fallback_error'     => $lastFallbackResponse['error'] ?? null,
                        'fallback_attempted' => true,
                        'model'              => $this->model,
                        'actual_model'       => $lastFallbackResponse['model'] ?? null,
                        'attempts'           => ($response['attempts'] ?? 1) + ($lastFallbackResponse['attempts'] ?? 1)
                    ];
                }
            }
        }

        return $response;
    }

    /**
     * Eksekusi pemanggilan HTTP transport (dapat di-override untuk keperluan mock pengujian)
     *
     * @param string $endpoint URL endpoint Gemini
     * @param string $jsonPayload Request body JSON
     * @return array{body: string|false, http_code: int, curl_error: string, curl_errno: int}
     */
    protected function executeHttpCall(string $endpoint, string $jsonPayload): array
    {
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return [
                'body'       => false,
                'http_code'  => 0,
                'curl_error' => 'Gagal menginisialisasi cURL handler.',
                'curl_errno' => -1
            ];
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        return [
            'body'       => $responseBody,
            'http_code'  => $httpCode,
            'curl_error' => $curlError,
            'curl_errno' => $curlErrno
        ];
    }

    /**
     * Eksekusi request HTTP cURL dengan Automatic Retry & Exponential Backoff
     *
     * @param string $modelName Model yang dipanggil
     * @param string $jsonPayload JSON request body
     * @param int|null $maxAttempts Batas percobaan khusus untuk panggilan ini
     * @return array Hasil eksekusi
     */
    protected function executeRequestWithRetry(string $modelName, string $jsonPayload, ?int $maxAttempts = null): array
    {
        $endpoint = "{$this->apiBaseUrl}/models/{$modelName}:generateContent";
        $lastError = null;
        $lastHttpCode = 0;
        $lastRawResponse = null;
        $attemptsLimit = $maxAttempts !== null ? max(1, $maxAttempts) : $this->maxRetries;

        for ($attempt = 1; $attempt <= $attemptsLimit; $attempt++) {
            $httpResult = $this->executeHttpCall($endpoint, $jsonPayload);

            $responseBody = $httpResult['body'];
            $httpCode = $httpResult['http_code'];
            $curlError = $httpResult['curl_error'];
            $curlErrno = $httpResult['curl_errno'];

            // Cek cURL Network / Transport Error
            if ($curlError || $curlErrno !== 0) {
                $lastError = $this->sanitizeErrorMessage("Gagal menghubungi Gemini API (Transport error): {$curlError}");
                $lastHttpCode = $httpCode;

                if ($attempt < $attemptsLimit) {
                    $this->waitBeforeRetry($attempt);
                    continue;
                }

                return [
                    'success'   => false,
                    'error'     => $lastError,
                    'http_code' => $lastHttpCode,
                    'attempts'  => $attempt,
                    'model'     => $modelName
                ];
            }

            $lastRawResponse = $responseBody;
            $lastHttpCode = $httpCode;

            // Cek Response Kosong
            if ($responseBody === false || $responseBody === '') {
                $lastError = "Gemini API mengembalikan respons kosong (HTTP {$httpCode}).";
                if ($attempt < $attemptsLimit) {
                    $this->waitBeforeRetry($attempt);
                    continue;
                }
                return [
                    'success'   => false,
                    'error'     => $lastError,
                    'http_code' => $lastHttpCode,
                    'attempts'  => $attempt,
                    'model'     => $modelName
                ];
            }

            // HTTP 200: Sukses
            if ($httpCode === 200) {
                $result = json_decode((string) $responseBody, true);
                if (!is_array($result)) {
                    return [
                        'success'   => false,
                        'error'     => 'Format respons Gemini bukan JSON yang valid.',
                        'raw'       => $responseBody,
                        'http_code' => 200,
                        'attempts'  => $attempt,
                        'model'     => $modelName
                    ];
                }

                $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($content === '') {
                    return [
                        'success'   => false,
                        'error'     => 'Gemini merespons sukses tetapi bagian konten kosong.',
                        'raw'       => $result,
                        'http_code' => 200,
                        'attempts'  => $attempt,
                        'model'     => $modelName
                    ];
                }

                return [
                    'success'       => true,
                    'content'       => $content,
                    'raw'           => $result,
                    'usage'         => $result['usageMetadata'] ?? [],
                    'model'         => $modelName,
                    'provider'      => $this->getProviderName(),
                    'attempts'      => $attempt,
                    'fallback_used' => false
                ];
            }

            // HTTP Error: Decode detail error dari JSON Gemini
            $decodedError = json_decode((string) $responseBody, true);
            $rawErrorMessage = $decodedError['error']['message'] ?? "HTTP Status {$httpCode}";
            $lastError = $this->sanitizeErrorMessage("Gemini Error ({$httpCode}): {$rawErrorMessage}");

            // CEK 1: Apakah error adalah kuota habis (RESOURCE_EXHAUSTED / Quota Exceeded)?
            // Jika kuota habis, JANGAN lakukan retry berulang pada model yang sama karena pasti akan gagal.
            // Segera return pada attempt pertama agar sistem langsung mengaktifkan fallback tanpa delay.
            if ($this->isQuotaExhausted($httpCode, $decodedError)) {
                return [
                    'success'            => false,
                    'error'              => $lastError,
                    'http_code'          => $httpCode,
                    'raw'                => $decodedError,
                    'attempts'           => $attempt,
                    'model'              => $modelName,
                    'is_quota_exhausted' => true
                ];
            }

            // CEK 2: Error yang tidak masuk kriteria retry (400, 401, 403, 404)
            $shouldRetry = ($httpCode === 408 || $httpCode === 429 || $httpCode >= 500);
            if (!$shouldRetry) {
                return [
                    'success'   => false,
                    'error'     => $lastError,
                    'http_code' => $httpCode,
                    'raw'       => $decodedError,
                    'attempts'  => $attempt,
                    'model'     => $modelName
                ];
            }

            // Khusus HTTP 503 (Overloaded / High Demand):
            // Batasi percobaan pada model yang sama maksimal 2 kali percobaan (1 kali retry cepat)
            // agar tidak membuang waktu tunggu jika lonjakan permintaan menetap, sehingga sistem dapat segera mencoba model cadangan.
            if ($httpCode === 503 && $attempt >= 2) {
                return [
                    'success'   => false,
                    'error'     => "{$lastError} (Setelah {$attempt} kali percobaan)",
                    'http_code' => $lastHttpCode,
                    'raw'       => $decodedError,
                    'attempts'  => $attempt,
                    'model'     => $modelName
                ];
            }

            // Lakukan retry untuk temporary rate limit / server error jika kuota percobaan masih ada
            if ($attempt < $attemptsLimit) {
                $this->waitBeforeRetry($attempt);
                continue;
            }

            return [
                'success'   => false,
                'error'     => "{$lastError} (Setelah {$attempt} kali percobaan)",
                'http_code' => $lastHttpCode,
                'raw'       => $decodedError,
                'attempts'  => $attempt,
                'model'     => $modelName
            ];
        }

        return [
            'success'   => false,
            'error'     => $lastError ?? 'Gemini API gagal memberikan respons.',
            'http_code' => $lastHttpCode,
            'raw'       => $lastRawResponse,
            'attempts'  => $attemptsLimit,
            'model'     => $modelName
        ];
    }

    /**
     * Menunggu sebelum retry dengan exponential backoff dan random jitter
     *
     * @param int $attempt Nomor percobaan saat ini
     */
    protected function waitBeforeRetry(int $attempt): void
    {
        $baseDelaySeconds = 2 ** ($attempt - 1); // 1s, 2s, 4s...
        $jitterMicroseconds = random_int(0, 500) * 1000; // 0 - 500ms
        $totalMicroseconds = ($baseDelaySeconds * 1_000_000) + $jitterMicroseconds;

        usleep($totalMicroseconds);
    }
}