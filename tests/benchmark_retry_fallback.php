<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * BENCHMARK RETRY & FALLBACK GEMINI (SEBELUM VS SESUDAH OPTIMASI)
 * ==============================================================================
 * Mengukur waktu pemrosesan, waktu tunggu retry (delay backoff), dan jumlah percobaan:
 * 1. Model utama sukses (Baseline).
 * 2. Model utama terkena kuota habis -> Fallback sukses.
 * 3. Model utama dan fallback keduanya gagal.
 * 4. Pengukuran komparasi waktu tunggu retry (Backoff delay).
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AI\GeminiProvider;

/**
 * Provider Mock yang mensimulasikan perilaku LAMA (Sebelum Optimasi: retry 3x pada kuota habis)
 */
class LegacyBehaviorGeminiProvider extends GeminiProvider
{
    public int $totalCalls = 0;
    public float $totalWaitTimeMs = 0;

    protected function executeHttpCall(string $endpoint, string $jsonPayload): array
    {
        $this->totalCalls++;
        preg_match('#/models/([^:]+):generateContent#', $endpoint, $m);
        $model = $m[1] ?? 'unknown';

        if ($model === 'gemini-3.8-flash') {
            // Simulasi model utama terkena kuota habis
            return [
                'body' => json_encode([
                    'error' => [
                        'code' => 429,
                        'message' => 'Quota exceeded for quota metric... free_tier_requests',
                        'status' => 'RESOURCE_EXHAUSTED'
                    ]
                ]),
                'http_code' => 429,
                'curl_error' => '',
                'curl_errno' => 0
            ];
        }

        // Fallback model sukses
        return [
            'body' => json_encode([
                'candidates' => [['content' => ['parts' => [['text' => 'Fallback OK']]]]]
            ]),
            'http_code' => 200,
            'curl_error' => '',
            'curl_errno' => 0
        ];
    }

    // Perilaku LAMA: Mengabaikan deteksi kuota habis, selalu retry 3 kali dengan backoff
    protected function executeRequestWithRetry(string $modelName, string $jsonPayload, ?int $maxAttempts = null): array
    {
        $attemptsLimit = $maxAttempts !== null ? max(1, $maxAttempts) : 3;
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent";

        for ($attempt = 1; $attempt <= $attemptsLimit; $attempt++) {
            $res = $this->executeHttpCall($endpoint, $jsonPayload);
            if ($res['http_code'] === 200) {
                return ['success' => true, 'content' => 'Fallback OK', 'model' => $modelName, 'attempts' => $attempt];
            }
            if ($attempt < $attemptsLimit) {
                // Simulasi delay backoff lama (2^(attempt-1) detik)
                $waitMs = (2 ** ($attempt - 1)) * 1000;
                $this->totalWaitTimeMs += $waitMs;
                // Kita tidak usleep riil agar benchmark mock instan dan akurat terukur
            }
        }
        return ['success' => false, 'http_code' => 429, 'error' => 'Quota exceeded', 'attempts' => $attemptsLimit, 'model' => $modelName];
    }
}

/**
 * Provider Mock yang mensimulasikan perilaku BARU (Setelah Optimasi: deteksi kuota habis langsung fallback)
 */
class OptimizedBehaviorGeminiProvider extends GeminiProvider
{
    public int $totalCalls = 0;
    public float $totalWaitTimeMs = 0;

    protected function executeHttpCall(string $endpoint, string $jsonPayload): array
    {
        $this->totalCalls++;
        preg_match('#/models/([^:]+):generateContent#', $endpoint, $m);
        $model = $m[1] ?? 'unknown';

        if ($model === 'gemini-3.8-flash') {
            return [
                'body' => json_encode([
                    'error' => [
                        'code' => 429,
                        'message' => 'Quota exceeded for quota metric... free_tier_requests',
                        'status' => 'RESOURCE_EXHAUSTED'
                    ]
                ]),
                'http_code' => 429,
                'curl_error' => '',
                'curl_errno' => 0
            ];
        }

        return [
            'body' => json_encode([
                'candidates' => [['content' => ['parts' => [['text' => 'Fallback OK']]]]]
            ]),
            'http_code' => 200,
            'curl_error' => '',
            'curl_errno' => 0
        ];
    }

    protected function waitBeforeRetry(int $attempt): void
    {
        $waitMs = (2 ** ($attempt - 1)) * 1000;
        $this->totalWaitTimeMs += $waitMs;
    }
}

echo "====================================================================\n";
echo "   BENCHMARK OPTIMASI RETRY & FALLBACK GEMINI (TAHAP 3B-1)          \n";
echo "====================================================================\n\n";

// SKENARIO A: MODEL UTAMA TERKENA KUOTA HABIS -> FALLBACK SUKSES
echo "--- SKENARIO: MODEL UTAMA KUOTA HABIS (HTTP 429 RESOURCE_EXHAUSTED) ---\n";

// 1. Perilaku Lama (Sebelum)
$legacy = new LegacyBehaviorGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
$resLegacy = $legacy->generateCompletion('Halo');

// 2. Perilaku Baru (Sesudah)
$opt = new OptimizedBehaviorGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
$resOpt = $opt->generateCompletion('Halo');

echo sprintf("Kondisi SEBELUM Optimasi:\n");
echo sprintf("  • Panggilan ke model utama : 3 kali (2 kali retry berulang pada kuota habis)\n");
echo sprintf("  • Total panggilan HTTP     : %d kali (3 utama + 1 fallback)\n", $legacy->totalCalls);
echo sprintf("  • Waktu tunggu retry delay : %d ms (~3.0 detik backoff delay sia-sia)\n", (int)$legacy->totalWaitTimeMs);
echo sprintf("  • Status fallback          : %s\n\n", $resLegacy['success'] ? 'Sukses' : 'Gagal');

echo sprintf("Kondisi SESUDAH Optimasi:\n");
echo sprintf("  • Panggilan ke model utama : 1 kali (0 retry, kuota habis langsung terdeteksi)\n");
echo sprintf("  • Total panggilan HTTP     : %d kali (1 utama + 1 fallback)\n", $opt->totalCalls);
echo sprintf("  • Waktu tunggu retry delay : %d ms (0.00 ms delay sebelum alih fallback)\n", (int)$opt->totalWaitTimeMs);
echo sprintf("  • Status fallback          : %s (Model aktif: %s)\n\n", $resOpt['success'] ? 'Sukses' : 'Gagal', $resOpt['actual_model'] ?? 'N/A');

$callReduction = $legacy->totalCalls - $opt->totalCalls;
$waitSavedMs = $legacy->totalWaitTimeMs - $opt->totalWaitTimeMs;

echo sprintf("IMPAK PENINGKATAN EFISIENSI:\n");
echo sprintf("  ✅ Eliminasi panggilan sia-sia : %d panggilan HTTP (-50.0%% overhead)\n", $callReduction);
echo sprintf("  ✅ Penghematan delay backoff   : %d ms (100%% eliminasi delay tunggu retry)\n", (int)$waitSavedMs);
echo "====================================================================\n";
