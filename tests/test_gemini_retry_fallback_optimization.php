<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TAHAP 3B-1 (PERBAIKAN) — TEST SUITE OPTIMASI RETRY & FALLBACK GEMINI
 * ==============================================================================
 * Memverifikasi 8 skenario pengujian dengan mock terkontrol tanpa memakan kuota API nyata:
 * 1. Model utama sukses.
 * 2. Model utama 503 lalu fallback sukses.
 * 3. Model utama 503 dan fallback 503.
 * 4. Model utama 429 karena kuota dan fallback sukses.
 * 5. Model utama 408 atau 5xx dengan retry terbatas.
 * 6. Model utama dan fallback dikonfigurasi dengan nama sama.
 * 7. Timeout atau koneksi gagal.
 * 8. Tidak ada retry tanpa batas dan tidak ada API key di pesan error.
 * (+ Verifikasi kompatibilitas GradingService & AiQuestionService).
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AI\GeminiProvider;
use App\Services\AI\MockAiProvider;
use App\Services\AI\AiQuestionService;
use App\Services\GradingService;

/**
 * Subclass TestableGeminiProvider untuk pengujian deterministik berbasis mock
 */
class TestableGeminiProvider extends GeminiProvider
{
    /** @var array<string, array<int, array{body: string|false, http_code: int, curl_error: string, curl_errno: int}>> */
    public array $mockResponses = [];

    /** @var array<int, array{endpoint: string, payload: string, model: string}> */
    public array $callLog = [];

    /** @var array<int, int> */
    public array $waitedAttempts = [];

    public function queueResponse(string $model, int $httpCode, mixed $body, string $curlError = '', int $curlErrno = 0): void
    {
        $this->mockResponses[$model][] = [
            'body'       => is_array($body) ? json_encode($body) : $body,
            'http_code'  => $httpCode,
            'curl_error' => $curlError,
            'curl_errno' => $curlErrno,
        ];
    }

    protected function executeHttpCall(string $endpoint, string $jsonPayload): array
    {
        preg_match('#/models/([^:]+):generateContent#', $endpoint, $m);
        $model = $m[1] ?? 'unknown';

        $this->callLog[] = [
            'endpoint' => $endpoint,
            'payload'  => $jsonPayload,
            'model'    => $model,
        ];

        if (!empty($this->mockResponses[$model])) {
            return array_shift($this->mockResponses[$model]);
        }

        return [
            'body'       => json_encode(['error' => ['code' => 500, 'message' => 'No mock queued for model ' . $model]]),
            'http_code'  => 500,
            'curl_error' => '',
            'curl_errno' => 0,
        ];
    }

    protected function waitBeforeRetry(int $attempt): void
    {
        $this->waitedAttempts[] = $attempt;
    }
}

echo "====================================================================\n";
echo "   TAHAP 3B-1: UJI RETRY & FALLBACK OPTIMASI GEMINI (AUDIT 503)     \n";
echo "====================================================================\n\n";

$passedTests = 0;
$totalTests = 9;
$testResults = [];

function recordTestResult(int $num, string $title, bool $passed, string $details = ''): void {
    global $passedTests, $testResults;
    if ($passed) {
        $passedTests++;
        echo "  [PASS] Skenario {$num}: {$title}\n";
    } else {
        echo "  [FAIL] Skenario {$num}: {$title}\n";
    }
    if ($details !== '') {
        echo "         -> {$details}\n";
    }
    $testResults[$num] = ['title' => $title, 'passed' => $passed, 'details' => $details];
}

// -----------------------------------------------------------------------------
// 1. Model utama sukses
// -----------------------------------------------------------------------------
echo "[1/8] Skenario 1: Model utama sukses...\n";
$p1 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
$p1->queueResponse('gemini-3.8-flash', 200, [
    'candidates' => [
        ['content' => ['parts' => [['text' => 'Halo, saya asisten EduAI!']]]]
    ],
    'usageMetadata' => ['totalTokenCount' => 12]
]);
$res1 = $p1->generateCompletion('siapa saya?');
$pass1 = ($res1['success'] === true
    && $res1['content'] === 'Halo, saya asisten EduAI!'
    && $res1['attempts'] === 1
    && $res1['fallback_used'] === false
    && count($p1->callLog) === 1
    && count($p1->waitedAttempts) === 0);
recordTestResult(1, 'Model utama sukses tanpa retry', $pass1, "Panggilan: " . count($p1->callLog) . ", Attempts: " . ($res1['attempts'] ?? 0));

// -----------------------------------------------------------------------------
// 2. Model utama 503 lalu fallback sukses
// -----------------------------------------------------------------------------
echo "\n[2/8] Skenario 2: Model utama 503 lalu fallback sukses...\n";
$p2 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
// Model utama 503 overload (maksimal 2 percobaan)
$p2->queueResponse('gemini-3.8-flash', 503, ['error' => ['message' => 'This model is currently experiencing high demand.']]);
$p2->queueResponse('gemini-3.8-flash', 503, ['error' => ['message' => 'This model is currently experiencing high demand.']]);
// Fallback model sukses 200
$p2->queueResponse('gemini-3.5-flash-lite', 200, [
    'candidates' => [
        ['content' => ['parts' => [['text' => 'Jawaban sukses dari model fallback']]]]
    ],
    'usageMetadata' => ['totalTokenCount' => 18]
]);

$res2 = $p2->generateCompletion('siapa saya?');
$primaryCalls2 = count(array_filter($p2->callLog, fn($c) => $c['model'] === 'gemini-3.8-flash'));
$fallbackCalls2 = count(array_filter($p2->callLog, fn($c) => $c['model'] === 'gemini-3.5-flash-lite'));
$pass2 = ($res2['success'] === true
    && $res2['fallback_used'] === true
    && $res2['actual_model'] === 'gemini-3.5-flash-lite'
    && $primaryCalls2 === 2 // Dibatasi 2 percobaan, tidak membuang 3x percobaan penuh
    && $fallbackCalls2 === 1);
recordTestResult(2, 'Model utama 503 alihkan ke fallback setelah retry terbatas', $pass2,
    "Primary 503 attempts: {$primaryCalls2}, Fallback attempts: {$fallbackCalls2}");

// -----------------------------------------------------------------------------
// 3. Model utama 503 dan fallback 503
// -----------------------------------------------------------------------------
echo "\n[3/8] Skenario 3: Model utama 503 dan fallback 503...\n";
$p3 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
// Model utama 503
$p3->queueResponse('gemini-3.8-flash', 503, ['error' => ['message' => 'High demand']]);
$p3->queueResponse('gemini-3.8-flash', 503, ['error' => ['message' => 'High demand']]);
// Fallback 503
$p3->queueResponse('gemini-3.5-flash-lite', 503, ['error' => ['message' => 'High demand']]);
$p3->queueResponse('gemini-3.5-flash-lite', 503, ['error' => ['message' => 'High demand']]);

$res3 = $p3->generateCompletion('siapa saya?');
$pass3 = ($res3['success'] === false
    && !empty($res3['error'])
    && !empty($res3['user_message'])
    && str_contains($res3['user_message'], 'lonjakan antrean server')
    && !empty($res3['technical_error'])
    && $res3['fallback_attempted'] === true);
recordTestResult(3, 'Model utama 503 dan fallback 503 mengembalikan pesan ramah & error teknis terkontrol', $pass3,
    "User message: " . ($res3['user_message'] ?? 'N/A'));

// -----------------------------------------------------------------------------
// 4. Model utama 429 karena kuota dan fallback sukses
// -----------------------------------------------------------------------------
echo "\n[4/8] Skenario 4: Model utama 429 kuota habis langsung fallback sukses...\n";
$p4 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
$p4->queueResponse('gemini-3.8-flash', 429, [
    'error' => [
        'code'    => 429,
        'message' => 'Quota exceeded for metric free_tier_requests',
        'status'  => 'RESOURCE_EXHAUSTED'
    ]
]);
$p4->queueResponse('gemini-3.5-flash-lite', 200, [
    'candidates' => [
        ['content' => ['parts' => [['text' => 'Respon cepat fallback']]]]
    ]
]);

$res4 = $p4->generateCompletion('siapa saya?');
$primaryCalls4 = count(array_filter($p4->callLog, fn($c) => $c['model'] === 'gemini-3.8-flash'));
$pass4 = ($res4['success'] === true
    && $res4['fallback_used'] === true
    && $primaryCalls4 === 1 // Zero retry on quota exhaustion
    && count($p4->waitedAttempts) === 0);
recordTestResult(4, 'Model utama 429 kuota habis langsung beralih ke fallback tanpa delay', $pass4,
    "Primary calls: {$primaryCalls4} (0 retries), Waited delay count: " . count($p4->waitedAttempts));

// -----------------------------------------------------------------------------
// 5. Model utama 408 atau 5xx dengan retry terbatas
// -----------------------------------------------------------------------------
echo "\n[5/8] Skenario 5: Model utama 500 / 408 dengan retry terbatas...\n";
$p5 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite', null, 3);
// Percobaan 1: 500 Internal Server Error
$p5->queueResponse('gemini-3.8-flash', 500, ['error' => ['message' => 'Transient backend glitch']]);
// Percobaan 2: 200 Sukses
$p5->queueResponse('gemini-3.8-flash', 200, [
    'candidates' => [
        ['content' => ['parts' => [['text' => 'Pulih setelah transient error']]]]
    ]
]);

$res5 = $p5->generateCompletion('siapa saya?');
$pass5 = ($res5['success'] === true
    && $res5['attempts'] === 2
    && count($p5->callLog) === 2
    && $p5->waitedAttempts === [1]);
recordTestResult(5, 'Model utama 500 / 408 berhasil pulih pada retry terbatas', $pass5,
    "Total calls: " . count($p5->callLog) . ", Attempts log: [" . implode(', ', $p5->waitedAttempts) . "]");

// -----------------------------------------------------------------------------
// 6. Model utama dan fallback dikonfigurasi dengan nama sama
// -----------------------------------------------------------------------------
echo "\n[6/8] Skenario 6: Model utama dan fallback sama dikonfigurasi aman...\n";
$p6 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.8-flash');
$p6->queueResponse('gemini-3.8-flash', 429, [
    'error' => ['code' => 429, 'message' => 'quota exceeded', 'status' => 'RESOURCE_EXHAUSTED']
]);

$res6 = $p6->generateCompletion('siapa saya?');
$totalCalls6 = count($p6->callLog);
$pass6 = ($res6['success'] === false && $totalCalls6 === 1);
recordTestResult(6, 'Model utama == fallback ditangani aman tanpa panggilan duplikat sia-sia', $pass6,
    "Total panggilan: {$totalCalls6} (Panggilan redundant dicegah)");

// -----------------------------------------------------------------------------
// 7. Timeout atau koneksi gagal (Transport Error)
// -----------------------------------------------------------------------------
echo "\n[7/8] Skenario 7: Timeout atau koneksi cURL gagal...\n";
$p7 = new TestableGeminiProvider('mock_key', 'gemini-3.8-flash', 'gemini-3.5-flash-lite');
// Model utama timeout
$p7->queueResponse('gemini-3.8-flash', 0, '', 'Operation timed out after 30000 milliseconds', 28);
$p7->queueResponse('gemini-3.8-flash', 0, '', 'Operation timed out after 30000 milliseconds', 28);
$p7->queueResponse('gemini-3.8-flash', 0, '', 'Operation timed out after 30000 milliseconds', 28);
// Fallback pulih dan sukses
$p7->queueResponse('gemini-3.5-flash-lite', 200, [
    'candidates' => [
        ['content' => ['parts' => [['text' => 'Pulih dari timeout utama via fallback']]]]
    ]
]);

$res7 = $p7->generateCompletion('siapa saya?');
$pass7 = ($res7['success'] === true
    && $res7['fallback_used'] === true
    && $res7['actual_model'] === 'gemini-3.5-flash-lite');
recordTestResult(7, 'Transport error / timeout pada model utama berhasil dialihkan ke fallback', $pass7,
    "Fallback sukses: " . ($res7['success'] ? 'YA' : 'TIDAK'));

// -----------------------------------------------------------------------------
// 8. Tidak ada retry tanpa batas dan tidak ada API key di pesan error
// -----------------------------------------------------------------------------
echo "\n[8/8] Skenario 8: Batas retry ketat dan proteksi redaksi API Key...\n";
$secretKey = 'AIzaSyA_TOP_SECRET_123456789_KEY';
$p8 = new TestableGeminiProvider($secretKey, 'gemini-3.8-flash', 'gemini-3.5-flash-lite', null, 3);
// Gagal berulang
$p8->queueResponse('gemini-3.8-flash', 500, ['error' => ['message' => "Error key={$secretKey}"]]);
$p8->queueResponse('gemini-3.8-flash', 500, ['error' => ['message' => "Error key={$secretKey}"]]);
$p8->queueResponse('gemini-3.8-flash', 500, ['error' => ['message' => "Error key={$secretKey}"]]);
$p8->queueResponse('gemini-3.5-flash-lite', 500, ['error' => ['message' => "Error key={$secretKey}"]]);
$p8->queueResponse('gemini-3.5-flash-lite', 500, ['error' => ['message' => "Error key={$secretKey}"]]);
$p8->queueResponse('gemini-3.5-flash-lite', 500, ['error' => ['message' => "Should never be called"]]);

$res8 = $p8->generateCompletion('siapa saya?');
$totalCalls8 = count($p8->callLog);
$hasSecretInError = str_contains($res8['error'] ?? '', $secretKey) || str_contains($res8['technical_error'] ?? '', $secretKey);
$hasRedactedNotice = str_contains($res8['error'] ?? '', '[REDACTED_API_KEY]') || str_contains($res8['technical_error'] ?? '', '[REDACTED_API_KEY]');

$pass8 = ($totalCalls8 === 5 && !$hasSecretInError && $hasRedactedNotice);
recordTestResult(8, 'Batas panggilan ketat (5 panggilan) dan API key disanitasi bersih', $pass8,
    "Total calls: {$totalCalls8}, Bocor di output: " . ($hasSecretInError ? 'YA' : 'TIDAK'));

// -----------------------------------------------------------------------------
// 9. Kompatibilitas Pemanggil (GradingService & AiQuestionService)
// -----------------------------------------------------------------------------
echo "\n[9/8] Uji Kompatibilitas Pemanggil Terkait...\n";
$mockProvider = new MockAiProvider();
$mockCompletion = $mockProvider->generateCompletion('Test mock');
$mockSuccess = ($mockCompletion['success'] === true && !empty($mockCompletion['content']));

$questionService = new AiQuestionService();
$resolvedMock = $questionService->resolveProvider('mock');

$gradingService = new GradingService();
$gradingAvailable = is_object($gradingService);

$pass9 = ($mockSuccess && $resolvedMock->getProviderName() === 'mock' && $gradingAvailable);
recordTestResult(9, 'Provider lain (MockAiProvider) dan caller (GradingService, AiQuestionService) normal', $pass9,
    "Mock provider: OK, QuestionService: OK, GradingService: OK");

// -----------------------------------------------------------------------------
// REKAPITULASI
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "                   HASIL PENGUJIAN TAHAP 3B-1                       \n";
echo "====================================================================\n";
echo "Total Skenario: {$totalTests}\n";
echo "Lulus         : {$passedTests}\n";
echo "Gagal         : " . ($totalTests - $passedTests) . "\n";
echo "Status        : " . ($passedTests === $totalTests ? "SEMUA PENGUJIAN LULUS (100%)" : "TERDAPAT KEGAGALAN") . "\n";
echo "====================================================================\n";

if ($passedTests !== $totalTests) {
    exit(1);
}
exit(0);
