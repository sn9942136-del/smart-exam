<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AI\GeminiProvider;

$gemini = new GeminiProvider();
$prompt = "Buatkan 3 butir soal ujian matematika kelas 8 topik SPLDV bentuk pilihan ganda. Ketentuan Output Respon AI: Format respon WAJIB berupa JSON murni dengan objek root 'questions' berisi array butir soal (number, type, difficulty, weight, content, explanation, options [{label, text, is_correct}]).";

$res = $gemini->generateCompletion($prompt, [
    'max_tokens' => 8000,
    'temperature' => 0.7
]);

echo "Success: " . ($res['success'] ? 'true' : 'false') . "\n";
echo "Model Used: " . ($res['model'] ?? '') . "\n";
echo "Fallback Used: " . (!empty($res['fallback_used']) ? 'true' : 'false') . "\n";
echo "Usage Metadata:\n";
print_r($res['usage'] ?? []);
if (isset($res['raw']['candidates'][0])) {
    echo "Finish Reason: " . ($res['raw']['candidates'][0]['finishReason'] ?? 'NONE') . "\n";
}
echo "Content Length: " . strlen($res['content'] ?? '') . "\n";
echo "Content Preview:\n" . substr($res['content'] ?? '', 0, 400) . "\n...\n";
echo "Content Ending:\n" . substr($res['content'] ?? '', -200) . "\n";
