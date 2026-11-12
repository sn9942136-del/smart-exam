<?php

declare(strict_types=1);

/**
 * Diagnostic Test: Google Gemini Provider & API Connection
 * Location: tests/ (Moved from public/ for security hardening)
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AI\GeminiProvider;

$gemini = new GeminiProvider();

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // Prevent unauthorized web access if accessed via web server
    if (env('APP_ENV') === 'production' || !env('APP_DEBUG')) {
        http_response_code(403);
        echo "403 Forbidden: Diagnostic tool disabled in production.";
        exit;
    }
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Test Gemini API</title>";
    echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'>";
    echo "</head><body class='bg-light py-5'><div class='container' style='max-width: 800px;'>";
    echo "<div class='card shadow-sm border-0 rounded-4 p-4 p-md-5 bg-white'>";
    echo "<h1 class='h3 fw-bold mb-4'>🔬 Diagnostic Test: Google Gemini Provider</h1>";
    echo "<div class='mb-3'>";
    echo "<p class='mb-1'><strong>Status API Key:</strong> " . ($gemini->isAvailable() ? "<span class='badge bg-success'>✅ Terkonfigurasi di .env</span>" : "<span class='badge bg-danger'>❌ Tidak Ditemukan</span>") . "</p>";
    echo "<p class='mb-1'><strong>Model Utama:</strong> <code>" . htmlspecialchars($gemini->getModel()) . "</code></p>";
    echo "<p class='mb-1'><strong>Model Fallback:</strong> <code>" . htmlspecialchars($gemini->getFallbackModel() ?? 'None') . "</code></p>";
    echo "</div><hr class='my-4'>";
} else {
    echo "========================================================\n";
    echo "   DIAGNOSTIC TEST: GOOGLE GEMINI PROVIDER & API KEY    \n";
    echo "========================================================\n";
    echo "Status API Key : " . ($gemini->isAvailable() ? "Terkonfigurasi di .env (Active)" : "Tidak Ditemukan") . "\n";
    echo "Model Utama    : " . $gemini->getModel() . "\n";
    echo "Model Fallback : " . ($gemini->getFallbackModel() ?? 'None') . "\n";
    echo "--------------------------------------------------------\n";
}

if (!$gemini->isAvailable()) {
    $msg = "❌ GEMINI_API_KEY tidak ditemukan pada file .env. Pastikan konfigurasi telah diset.";
    if (!$isCli) {
        echo "<div class='alert alert-danger'>{$msg}</div></div></div></body></html>";
    } else {
        echo "{$msg}\n";
    }
    exit(1);
}

$prompt = "Sapa guru dan berikan 1 contoh soal pilihan ganda tentang Pemrograman Web PHP beserta opsi A, B, C, D dan kunci jawaban.";
$options = [
    'system_instruction' => 'Anda adalah asisten cerdas pembuat soal ujian. Balas HANYA dalam format JSON valid dengan field: greeting, question, options, answer, explanation.',
    'temperature' => 0.4,
    'max_tokens' => 600
];

if (!$isCli) {
    echo "<h5 class='fw-bold mb-3'>Mengirim Permintaan ke Gemini API...</h5>";
} else {
    echo "Mengirim Permintaan ke Gemini API...\n";
}

$startTime = microtime(true);
$result = $gemini->generateCompletion($prompt, $options);
$duration = round((microtime(true) - $startTime) * 1000, 2);

if ($result['success']) {
    if (!$isCli) {
        echo "<div class='alert alert-success d-flex align-items-center justify-content-between mb-3'>";
        echo "<span>✅ <strong>Koneksi Berhasil!</strong> Respons diterima dalam {$duration} ms.</span>";
        echo "<span class='badge bg-dark'>Model: " . htmlspecialchars($result['model'] ?? $gemini->getModel()) . "</span>";
        echo "</div>";

        if (!empty($result['fallback_used'])) {
            echo "<div class='alert alert-warning'>⚠️ Perhatian: Model utama gagal, respons diperoleh menggunakan Fallback Model: <code>" . htmlspecialchars($result['actual_model'] ?? '') . "</code></div>";
        }

        echo "<h6 class='fw-bold text-secondary mb-2'>Konten JSON Terstruktur:</h6>";
        echo "<pre class='bg-dark text-white p-3 rounded-3 fs-xs overflow-auto' style='max-height: 400px;'>";
        echo htmlspecialchars($result['content']);
        echo "</pre>";

        if (!empty($result['usage'])) {
            echo "<div class='small text-muted mt-2'>Usage Tokens: Prompt: " . ($result['usage']['promptTokenCount'] ?? 0) . " | Candidates: " . ($result['usage']['candidatesTokenCount'] ?? 0) . " | Total: " . ($result['usage']['totalTokenCount'] ?? 0) . "</div>";
        }
        echo "</div></div></body></html>";
    } else {
        echo "✅ [SUCCESS] Koneksi Berhasil! ({$duration} ms)\n";
        echo "Model Digunakan: " . ($result['model'] ?? $gemini->getModel()) . "\n";
        if (!empty($result['fallback_used'])) {
            echo "⚠️ [FALLBACK] Model utama dialihkan ke fallback: " . ($result['actual_model'] ?? '') . "\n";
        }
        echo "\nKonten Respons:\n" . $result['content'] . "\n";
    }
} else {
    if (!$isCli) {
        echo "<div class='alert alert-danger mb-3'>";
        echo "<h6 class='fw-bold mb-1'>❌ Gagal Menghubungi Gemini API ({$duration} ms)</h6>";
        echo "<p class='mb-0 fs-sm'>" . htmlspecialchars($result['error'] ?? 'Terjadi kesalahan tidak diketahui.') . "</p>";
        echo "</div>";

        if (!empty($result['raw'])) {
            echo "<details><summary class='text-muted small'>Detail Respons Server</summary>";
            echo "<pre class='bg-light p-3 rounded-3 mt-2 fs-3xs'>" . htmlspecialchars(json_encode($result['raw'], JSON_PRETTY_PRINT)) . "</pre>";
            echo "</details>";
        }
        echo "</div></div></body></html>";
    } else {
        echo "❌ [FAILED] Gagal Menghubungi Gemini API ({$duration} ms)\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
}
