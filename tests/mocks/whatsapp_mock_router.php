<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * SMART EXAM — MOCK WHATSAPP HTTP ROUTER (STANDALONE LOCAL SANDBOX)
 * ==============================================================================
 * Server mock mandiri untuk dijalankan via PHP Built-in Server:
 *   php -S 127.0.0.1:8088 tests/mocks/whatsapp_mock_router.php
 *
 * FITUR:
 * - Hanya menerima koneksi lokal (127.0.0.1).
 * - Skenario pengujian deterministik berbasis nomor telepon dummy.
 * - Deduplikasi & Idempotency guard berbasis client_reference_id.
 * - Tidak mengakses internet dan tidak meneruskan pesan ke nomor nyata.
 * ==============================================================================
 */

// Pastikan hanya melayani request dari localhost
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
if ($remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'error' => 'Akses ditolak: Hanya loopback 127.0.0.1 yang diizinkan.']);
    exit;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Header standar
header('Content-Type: application/json; charset=utf-8');

// Penyimpanan cache referensi unik untuk uji idempotensi
$storageDir = __DIR__ . '/../../storage/cache';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$cacheFile = $storageDir . '/mock_wa_processed.json';

function getProcessedReferences(string $file): array {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function saveProcessedReference(string $file, string $refId, array $data): void {
    $all = getProcessedReferences($file);
    $all[$refId] = array_merge($data, ['timestamp' => date('Y-m-d H:i:s')]);
    file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT));
}

// -----------------------------------------------------------------------------
// ENDPOINT: GET /api/health
// -----------------------------------------------------------------------------
if ($requestUri === '/api/health') {
    echo json_encode([
        'status'    => 'ok',
        'service'   => 'Smart Exam Mock WhatsApp Gateway',
        'host'      => '127.0.0.1:8088',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// ENDPOINT: POST /api/reset (Untuk membersihkan rekaman test)
// -----------------------------------------------------------------------------
if ($requestUri === '/api/reset' && $method === 'POST') {
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
    echo json_encode(['status' => true, 'message' => 'Cache referensi idempotensi berhasil dibersihkan.']);
    exit;
}

// -----------------------------------------------------------------------------
// ENDPOINT: POST /api/send (Dispatch Mock Utama)
// -----------------------------------------------------------------------------
if ($requestUri === '/api/send') {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => false, 'error' => 'Method Not Allowed. Gunakan POST.']);
        exit;
    }

    // Ambil payload JSON atau POST
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $target = trim((string)($payload['target'] ?? $payload['phone'] ?? $payload['recipient'] ?? ''));
    $message = trim((string)($payload['message'] ?? ''));
    $clientRefId = trim((string)(
        $payload['client_reference_id'] ?? 
        $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? 
        $payload['idempotency_key'] ?? 
        ''
    ));

    // Validasi field wajib
    if (empty($target) || empty($message)) {
        http_response_code(400);
        echo json_encode([
            'status'     => false,
            'error'      => 'Parameter target dan message wajib diisi.',
            'error_code' => 'MISSING_REQUIRED_FIELDS'
        ]);
        exit;
    }

    // 1. UJI IDEMPOTENSI: Cek apakah client_reference_id sudah pernah diproses
    if ($clientRefId !== '') {
        $processed = getProcessedReferences($cacheFile);
        if (isset($processed[$clientRefId])) {
            // DUPLICATE PREVENTED! Kembalikan response sukses awal tanpa kirim ulang
            http_response_code(200);
            echo json_encode([
                'status'              => true,
                'message_id'          => $processed[$clientRefId]['message_id'] ?? ('MOCK-WA-' . substr(md5($clientRefId), 0, 10)),
                'code'                => 'ALREADY_PROCESSED',
                'duplicate_prevented' => true,
                'client_reference_id' => $clientRefId,
                'note'                => 'Pesan duplikat dicegah oleh idempotency guard gateway.'
            ]);
            exit;
        }
    }

    // 2. SKENARIO DETERMINISTIK BERDASARKAN NOMOR DUMMY
    $cleanPhone = preg_replace('/[^0-9]/', '', $target);
    if (str_starts_with($cleanPhone, '62')) {
        $cleanPhone = '0' . substr($cleanPhone, 2);
    }

    // Skenario A: Timeout (Target berakhiran '99900000' atau sama persis '081299900000')
    if (str_ends_with($cleanPhone, '99900000') || $cleanPhone === '081299900000') {
        // Tahan selama 20 detik untuk menguji cURL timeout (batas timeout worker adalah 15 detik)
        sleep(20);
        echo json_encode(['status' => true, 'message_id' => 'MOCK-WA-TIMEOUT-RECOVERED']);
        exit;
    }

    // Skenario B: Respons Rusak / Bukan JSON (Target '081250200000')
    if ($cleanPhone === '081250200000') {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(200);
        echo "<html><body>502 Bad Gateway from Nginx Proxy</body></html>";
        exit;
    }

    // Skenario C: Permanent Error - HTTP 400 (Target '081240000000' atau nomor terlalu pendek)
    if ($cleanPhone === '081240000000' || strlen($cleanPhone) < 8) {
        http_response_code(400);
        echo json_encode([
            'status'     => false,
            'error'      => 'Nomor telepon tujuan tidak valid atau tidak terdaftar di WhatsApp.',
            'error_code' => 'INVALID_PHONE_NUMBER',
            'target'     => $target
        ]);
        exit;
    }

    // Skenario D: Rate Limit / Throttling - HTTP 429 (Target '081242900000')
    if ($cleanPhone === '081242900000') {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode([
            'status'      => false,
            'error'       => 'Rate limit exceeded: Batas kuota per detik terlampaui.',
            'error_code'  => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => 60
        ]);
        exit;
    }

    // Skenario E: Server Error Sementara - HTTP 500 (Target '081250000000')
    if ($cleanPhone === '081250000000') {
        http_response_code(500);
        echo json_encode([
            'status'     => false,
            'error'      => 'Internal server error on WhatsApp upstream cluster.',
            'error_code' => 'UPSTREAM_INTERNAL_ERROR'
        ]);
        exit;
    }

    // Skenario F: Service Unavailable - HTTP 503 (Target '081250300000')
    if ($cleanPhone === '081250300000') {
        http_response_code(503);
        echo json_encode([
            'status'     => false,
            'error'      => 'Gateway sedang dalam pemeliharaan berkala.',
            'error_code' => 'SERVICE_MAINTENANCE'
        ]);
        exit;
    }

    // Skenario G: SUKSES NORMAL (HTTP 200 OK)
    $generatedMsgId = 'MOCK-WA-' . strtoupper(bin2hex(random_bytes(6)));
    $responseData = [
        'status'              => true,
        'message_id'          => $generatedMsgId,
        'code'                => 'DELIVERED',
        'recipient'           => $target,
        'client_reference_id' => $clientRefId ?: null,
        'latency_ms'          => rand(10, 25)
    ];

    if ($clientRefId !== '') {
        saveProcessedReference($cacheFile, $clientRefId, $responseData);
    }

    http_response_code(200);
    echo json_encode($responseData);
    exit;
}

// Route tidak ditemukan
http_response_code(404);
echo json_encode(['status' => false, 'error' => "Endpoint tidak ditemukan: {$requestUri}"]);
exit;
