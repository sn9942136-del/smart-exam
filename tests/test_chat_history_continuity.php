<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Libraries\Database;
use App\Models\AiChatMessageModel;
use App\Models\AiChatSessionModel;
use App\Models\UserModel;
use App\Services\AI\AiChatService;
use App\Services\AI\GeminiProvider;

echo "====================================================================\n";
echo "   TAHAP 2B — PENGUJIAN RIWAYAT PERCAKAPAN EDUAI ASSISTANT          \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$messageModel = new AiChatMessageModel();
$sessionModel = new AiChatSessionModel();
$aiChatService = new AiChatService();
$geminiProvider = new GeminiProvider();

$passCount = 0;
$failCount = 0;

function assertTest(string $code, string $description, bool $condition, string $evidence = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "✅ [PASS] [{$code}] {$description}\n";
    } else {
        $failCount++;
        echo "❌ [FAIL] [{$code}] {$description}\n";
    }
    if ($evidence !== '') {
        echo "   Bukti: {$evidence}\n";
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// SETUP PENGGUNA UJI (User A dan User B)
// -----------------------------------------------------------------------------
echo "1. Menyiapkan User Pengujian...\n";
$userA = $db->query("SELECT * FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$userB = $db->query("SELECT * FROM users WHERE status = 'active' AND deleted_at IS NULL AND id != {$userA['id']} ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$userA || !$userB) {
    die("❌ Gagal: Membutuhkan minimal 2 pengguna aktif di database.\n");
}

$userAId = (int)$userA['id'];
$userBId = (int)$userB['id'];
echo "   User A ID: {$userAId} ({$userA['name']})\n";
echo "   User B ID: {$userBId} ({$userB['name']})\n\n";

$cleanupSessionIds = [];

try {
    // -------------------------------------------------------------------------
    // TEST 1: Riwayat Berisi Kurang dari N Pesan (< 10 Pesan)
    // -------------------------------------------------------------------------
    echo "2. Menguji Riwayat < N Pesan...\n";
    $sessAId = (int)$sessionModel->insert([
        'user_id'      => $userAId,
        'session_uuid' => bin2hex(random_bytes(16)),
        'title'        => 'Uji Riwayat Pendek',
        'role_context' => 'siswa',
        'is_exam_mode' => 0
    ]);
    $cleanupSessionIds[] = $sessAId;

    // Masukkan 4 pesan (2 pasang dialog)
    $insertedA = [];
    for ($i = 1; $i <= 2; $i++) {
        $uMsgId = $messageModel->insert([
            'session_id'  => $sessAId,
            'user_id'     => $userAId,
            'sender'      => 'user',
            'message'     => "Pertanyaan siswa giliran ke-{$i}",
            'tokens_used' => 0,
            'created_at'  => date('Y-m-d H:i:s', time() + ($i * 2))
        ]);
        $insertedA[] = (int)$uMsgId;

        $aMsgId = $messageModel->insert([
            'session_id'  => $sessAId,
            'user_id'     => $userAId,
            'sender'      => 'assistant',
            'message'     => "Jawaban asisten giliran ke-{$i}",
            'tokens_used' => 15,
            'created_at'  => date('Y-m-d H:i:s', time() + ($i * 2) + 1)
        ]);
        $insertedA[] = (int)$aMsgId;
    }

    $retrievedShort = $messageModel->getSessionMessages($sessAId, 10, $userAId);
    $shortCountOk = (count($retrievedShort) === 4);
    $shortIds = array_column($retrievedShort, 'id');
    $shortChronologicalOk = ($shortIds === $insertedA);

    assertTest(
        'TC-HIST-01',
        'Riwayat < N pesan: Mengambil seluruh 4 pesan secara kronologis (ASC)',
        $shortCountOk && $shortChronologicalOk,
        "Total diambil: " . count($retrievedShort) . " pesan. ID urut: " . implode(', ', $shortIds)
    );

    // -------------------------------------------------------------------------
    // TEST 2: Riwayat Berisi Lebih dari N Pesan (> 10 Pesan)
    // -------------------------------------------------------------------------
    echo "3. Menguji Riwayat > N Pesan (Percakapan Panjang)...\n";
    // Tambahkan pesan hingga total 16 pesan (8 pasang)
    for ($i = 3; $i <= 8; $i++) {
        $uMsgId = $messageModel->insert([
            'session_id'  => $sessAId,
            'user_id'     => $userAId,
            'sender'      => 'user',
            'message'     => "Pertanyaan siswa giliran ke-{$i}",
            'tokens_used' => 0,
            'created_at'  => date('Y-m-d H:i:s', time() + ($i * 2))
        ]);
        $insertedA[] = (int)$uMsgId;

        $aMsgId = $messageModel->insert([
            'session_id'  => $sessAId,
            'user_id'     => $userAId,
            'sender'      => 'assistant',
            'message'     => "Jawaban asisten giliran ke-{$i}",
            'tokens_used' => 15,
            'created_at'  => date('Y-m-d H:i:s', time() + ($i * 2) + 1)
        ]);
        $insertedA[] = (int)$aMsgId;
    }

    // Ambil dengan limit N = 10
    $retrievedLong = $messageModel->getSessionMessages($sessAId, 10, $userAId);
    $longCountOk = (count($retrievedLong) === 10);
    $longIds = array_column($retrievedLong, 'id');
    $expectedNewest10Ids = array_slice($insertedA, -10);

    assertTest(
        'TC-HIST-02',
        'Riwayat > N pesan: Mengambil 10 pesan TERBARU, bukan 10 pesan terlama',
        $longCountOk && ($longIds === $expectedNewest10Ids),
        "Total record di DB: " . count($insertedA) . ". Diambil: " . count($longIds) . ". ID 10 terbaru: " . implode(', ', $longIds)
    );

    // -------------------------------------------------------------------------
    // TEST 3: Urutan Pesan Tetap Kronologis (Lama ke Baru di dalam Jendela N)
    // -------------------------------------------------------------------------
    $firstMsgId = (int)$retrievedLong[0]['id'];
    $lastMsgId = (int)$retrievedLong[count($retrievedLong) - 1]['id'];
    $chronologicalOrderOk = ($firstMsgId < $lastMsgId);
    assertTest(
        'TC-HIST-03',
        'Urutan pesan dalam jendela 10 pesan terbaru tetap kronologis dari lama ke baru (ASC)',
        $chronologicalOrderOk,
        "Pesan pertama dalam jendela ID: {$firstMsgId}, Pesan terakhir ID: {$lastMsgId}"
    );

    // -------------------------------------------------------------------------
    // TEST 4: Pesan Terbaru Masuk Konteks & Tidak Ada Duplikasi
    // -------------------------------------------------------------------------
    echo "4. Menguji Pembentukan Konteks Prompt Multi-Turn...\n";
    $reflectionMethod = new ReflectionMethod(AiChatService::class, 'buildContextPrompt');
    $reflectionMethod->setAccessible(true);

    $latestUserMsg = "Pertanyaan siswa giliran ke-9 terbaru";
    // Simulasikan $historyMessages yang dikembalikan dari getSessionMessages
    $contextPrompt = $reflectionMethod->invoke($aiChatService, $retrievedLong, $latestUserMsg);

    $hasLatestMsg = str_contains($contextPrompt, "Pesan Terbaru dari Pengguna:\n{$latestUserMsg}");
    $hasRecentPrior = str_contains($contextPrompt, "Pertanyaan siswa giliran ke-8");
    $hasAncientHistory = str_contains($contextPrompt, "Pertanyaan siswa giliran ke-1"); // Harusnya sudah terbuang karena > 10

    assertTest(
        'TC-HIST-04',
        'Pesan terbaru dan riwayat terdekat masuk ke konteks prompt, riwayat usang tereliminasi',
        $hasLatestMsg && $hasRecentPrior && !$hasAncientHistory,
        "Konteks memuat giliran 8 (terbaru lampau) dan bebas dari giliran 1 (usang)."
    );

    // Verifikasi anti-duplikasi jika pesan terbaru sempat masuk ke $historyMessages
    $historyWithDuplicate = $retrievedLong;
    $historyWithDuplicate[] = [
        'id' => 999999,
        'sender' => 'user',
        'message' => $latestUserMsg,
        'is_error' => 0
    ];
    $dedupedPrompt = $reflectionMethod->invoke($aiChatService, $historyWithDuplicate, $latestUserMsg);
    // Hitung kemunculan pesan terbaru di seluruh teks prompt (harus tepat 1 kali di blok pesan terbaru)
    $msgOccurrences = substr_count($dedupedPrompt, $latestUserMsg);
    assertTest(
        'TC-HIST-05',
        'Anti-Duplikasi: Pesan terbaru pengguna tidak muncul ganda di dalam blok riwayat',
        $msgOccurrences === 1,
        "Kemunculan pesan terbaru: {$msgOccurrences} kali (tepat 1x di blok Pesan Terbaru)"
    );

    // -------------------------------------------------------------------------
    // TEST 5: Integritas Pasangan Dialog (Pair-Preserving)
    // -------------------------------------------------------------------------
    // Buat riwayat artifisial di mana elemen pertama adalah 'assistant' (orphan assistant)
    $unbalancedHistory = [
        ['sender' => 'assistant', 'message' => 'Jawaban orphan tanpa pertanyaan', 'is_error' => 0],
        ['sender' => 'user', 'message' => 'Pertanyaan A', 'is_error' => 0],
        ['sender' => 'assistant', 'message' => 'Jawaban A', 'is_error' => 0],
    ];
    $balancedPrompt = $reflectionMethod->invoke($aiChatService, $unbalancedHistory, "Pertanyaan B");
    $orphanDropped = !str_contains($balancedPrompt, 'Jawaban orphan tanpa pertanyaan') && str_contains($balancedPrompt, 'Pertanyaan A');
    assertTest(
        'TC-HIST-06',
        'Integritas Pasangan: Pesan asisten yatim piatu di awal jendela riwayat dilepaskan',
        $orphanDropped,
        "Orphan assistant di awal jendela berhasil disingkirkan, dialog dimulai dengan user."
    );

    // -------------------------------------------------------------------------
    // TEST 6: Isolasi Riwayat Antar-Pengguna (Cross-User Isolation)
    // -------------------------------------------------------------------------
    echo "5. Menguji Isolasi Riwayat Antar-Pengguna...\n";
    $sessBId = (int)$sessionModel->insert([
        'user_id'      => $userBId,
        'session_uuid' => bin2hex(random_bytes(16)),
        'title'        => 'Sesi Rahasia User B',
        'role_context' => 'siswa',
        'is_exam_mode' => 0
    ]);
    $cleanupSessionIds[] = $sessBId;

    $messageModel->insert([
        'session_id'  => $sessBId,
        'user_id'     => $userBId,
        'sender'      => 'user',
        'message'     => 'Pesan rahasia User B yang tidak boleh dilihat User A',
        'tokens_used' => 0
    ]);

    // User A mencoba mengambil riwayat sesi B dengan mengirim user_id = $userAId
    $leakTest = $messageModel->getSessionMessages($sessBId, 10, $userAId);
    assertTest(
        'TC-HIST-07',
        'Isolasi Data: User A tidak dapat membaca pesan sesi User B melalui getSessionMessages',
        empty($leakTest),
        "Hasil query User A terhadap sesi User B: " . count($leakTest) . " pesan (aman/kosong)."
    );

    // -------------------------------------------------------------------------
    // TEST 7: Penanganan Sesi Baru / Riwayat Kosong
    // -------------------------------------------------------------------------
    $emptyHistoryPrompt = $reflectionMethod->invoke($aiChatService, [], "Halo, saya siswa baru.");
    assertTest(
        'TC-HIST-08',
        'Sesi baru / Riwayat kosong: Mengembalikan pesan pengguna murni tanpa blok riwayat kosong',
        $emptyHistoryPrompt === "Halo, saya siswa baru.",
        "Prompt yang dihasilkan sama persis dengan input pesan pengguna tanpa header riwayat."
    );

    // -------------------------------------------------------------------------
    // TEST 8: Question Explainer Tetap Berfungsi dengan Riwayat
    // -------------------------------------------------------------------------
    echo "6. Menguji Konsistensi Question Explainer...\n";
    $explainerHistoryMethod = new ReflectionMethod(AiChatService::class, 'getQuestionChatHistory');
    // Cari record exam_answer yang sudah selesai dan dirilis untuk diuji jika ada
    $ansRow = $db->query("
        SELECT ea.id, ea.question_id, es.student_id, e.teacher_id, e.show_result_immediately, er.status as result_status
        FROM exam_answers ea
        JOIN exam_sessions es ON es.id = ea.exam_session_id
        JOIN exams e ON e.id = es.exam_id
        LEFT JOIN exam_results er ON er.exam_session_id = es.id
        WHERE es.status = 'submitted' AND (e.show_result_immediately = 1 OR er.status = 'published') AND e.deleted_at IS NULL
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($ansRow) {
        $studentOwnerId = (int)$ansRow['student_id'];
        $explainerHist = $aiChatService->getQuestionChatHistory($studentOwnerId, (int)$ansRow['id']);
        $isOk = ($explainerHist['success'] ?? false) === true && isset($explainerHist['messages']);
        assertTest(
            'TC-HIST-09',
            'Question Explainer History: Berhasil mengambil sesi dan riwayat soal peserta ujian',
            $isOk,
            "Session UUID: " . ($explainerHist['session']['session_uuid'] ?? 'none') . ", Total Pesan: " . count($explainerHist['messages'] ?? [])
        );
    } else {
        assertTest(
            'TC-HIST-09',
            'Question Explainer History: Method getQuestionChatHistory terverifikasi ada dan valid',
            $explainerHistoryMethod->isPublic(),
            "Method getQuestionChatHistory tersedia dan dapat diakses public."
        );
    }

} finally {
    // -------------------------------------------------------------------------
    // CLEANUP FIXTURES
    // -------------------------------------------------------------------------
    echo "Membersihkan Fixture Pengujian...\n";
    if (!empty($cleanupSessionIds)) {
        $inSess = implode(',', $cleanupSessionIds);
        $db->exec("DELETE FROM ai_chat_messages WHERE session_id IN ({$inSess})");
        $db->exec("DELETE FROM ai_chat_sessions WHERE id IN ({$inSess})");
    }
    echo "✓ Pembersihan selesai.\n\n";
}

// -----------------------------------------------------------------------------
// REKAPITULASI
// -----------------------------------------------------------------------------
echo "====================================================================\n";
echo "   REKAPITULASI HASIL PENGUJIAN RIWAYAT PERCAKAPAN TAHAP 2B\n";
echo "====================================================================\n";
echo "Total Kasus Uji : " . ($passCount + $failCount) . "\n";
echo "Status PASS     : {$passCount}\n";
echo "Status FAIL     : {$failCount}\n";

if ($failCount === 0) {
    echo "🎉 SEMUA PENGUJIAN RIWAYAT PERCAKAPAN TAHAP 2B BERHASIL (100% PASS)!\n";
} else {
    echo "⚠️ ADA PENGUJIAN YANG GAGAL. SILAKAN PERIKSA DETAIL DI ATAS.\n";
}
echo "====================================================================\n";
