<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Mock AI Provider
 * Provider deterministik cerdas berbasis standar kurikulum nasional untuk pengujian otomatis,
 * demonstrasi offline, dan fallback ketika API key eksternal tidak dikonfigurasi.
 */
class MockAiProvider implements AiProviderInterface
{
    public function getProviderName(): string
    {
        return 'mock';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function generateCompletion(string $prompt, array $options = []): array
    {
        // Ekstrak parameter dari prompt atau options jika tersedia
        $subject = 'Umum';
        if (preg_match('/Mata Pelajaran:\s*([^\n]+)/i', $prompt, $m)) {
            $subject = trim($m[1]);
        }

        $topic = 'Materi Pembelajaran';
        if (preg_match('/Materi \/ Topik:\s*([^\n]+)/i', $prompt, $m)) {
            $topic = trim($m[1]);
        }

        $grade = 'Kelas 8';
        if (preg_match('/Kelas \/ Tingkat:\s*([^\n]+)/i', $prompt, $m)) {
            $grade = trim($m[1]);
        }

        $type = 'multiple_choice';
        if (preg_match('/Jenis Soal:\s*([^\n]+)/i', $prompt, $m)) {
            $t = strtolower(trim($m[1]));
            if (str_contains($t, 'essay') || str_contains($t, 'esai')) {
                $type = 'essay';
            } elseif (str_contains($t, 'true_false') || str_contains($t, 'benar')) {
                $type = 'true_false';
            }
        }

        $count = 5;
        if (preg_match('/Jumlah Soal:\s*(\d+)/i', $prompt, $m)) {
            $count = max(1, min(30, (int)$m[1]));
        }

        $difficulty = 'medium';
        if (preg_match('/Tingkat Kesulitan:\s*([^\n]+)/i', $prompt, $m)) {
            $d = strtolower(trim($m[1]));
            if (in_array($d, ['easy', 'medium', 'hard'], true)) {
                $difficulty = $d;
            }
        }

        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $qDiff = $difficulty === 'mixed' 
                ? (['easy', 'medium', 'hard'][$i % 3]) 
                : $difficulty;

            $qType = $type;
            if ($type === 'mixed') {
                $qType = ($i % 4 === 0) ? 'essay' : 'multiple_choice';
            }

            if ($qType === 'essay') {
                $questions[] = [
                    'number'      => $i,
                    'type'        => 'essay',
                    'difficulty'  => $qDiff,
                    'weight'      => 4.0,
                    'content'     => "Jelaskan secara komprehensif konsep dasar mengenai {$topic} pada konteks {$subject} ({$grade}), serta berikan contoh penerapannya dalam kehidupan sehari-hari! (Soal #{$i})",
                    'explanation' => "Pembahasan Soal #{$i}: Jawaban yang tepat harus mencakup definisi inti dari {$topic}, minimal 2 prinsip fundamental, serta penjabaran contoh kasus nyata yang relevan dengan materi {$subject}.",
                    'options'     => []
                ];
            } elseif ($qType === 'true_false') {
                $isTrue = ($i % 2 === 0);
                $questions[] = [
                    'number'      => $i,
                    'type'        => 'true_false',
                    'difficulty'  => $qDiff,
                    'weight'      => 1.0,
                    'content'     => "Pernyataan mengenai {$topic}: Setiap prinsip dasar dalam {$topic} pada bidang {$subject} selalu berlaku secara mutlak di bawah kondisi standar. (Pernyataan #{$i})",
                    'explanation' => "Pembahasan: Pernyataan tersebut bernilai " . ($isTrue ? 'BENAR' : 'SALAH') . " karena dalam konteks materi {$topic}, terdapat batasan dan asumsi tertentu yang mendasarinya.",
                    'options'     => [
                        ['label' => 'A', 'text' => 'Benar', 'is_correct' => $isTrue],
                        ['label' => 'B', 'text' => 'Salah', 'is_correct' => !$isTrue]
                    ]
                ];
            } else {
                // Multiple choice
                $correctIndex = ($i % 4); // 0: A, 1: B, 2: C, 3: D
                $labels = ['A', 'B', 'C', 'D'];
                $optionsList = [];

                $conceptTerms = [
                    'Menyatakan hubungan fungsional antara variabel terkait',
                    'Mengeliminasi faktor pengganggu untuk mendapatkan solusi optimal',
                    'Menerapkan formulasi baku sesuai kaidah standar keilmuan',
                    'Mengintegrasikan parameter masukan menjadi kesimpulan terukur'
                ];

                foreach ($labels as $idx => $lbl) {
                    $isCorrect = ($idx === $correctIndex);
                    $optText = $isCorrect 
                        ? "{$conceptTerms[$idx]} yang merupakan karakteristik utama {$topic}."
                        : "Pilihan pembeda alternatif {$lbl} terkait aspek sekunder pada {$topic}.";

                    $optionsList[] = [
                        'label'      => $lbl,
                        'text'       => $optText,
                        'is_correct' => $isCorrect
                    ];
                }

                $correctLabel = $labels[$correctIndex];
                $questions[] = [
                    'number'      => $i,
                    'type'        => 'multiple_choice',
                    'difficulty'  => $qDiff,
                    'weight'      => 2.0,
                    'content'     => "Dalam mempelajari materi {$topic} pada mata pelajaran {$subject} ({$grade}), manakah pernyataan berikut yang paling tepat menggambarkan karakteristik fundamentalnya? (Butir Soal #{$i})",
                    'explanation' => "Pembahasan Soal #{$i}: Jawaban yang benar adalah pilihan ({$correctLabel}). Konsep {$topic} berfokus pada {$conceptTerms[$correctIndex]} sesuai dengan silabus kurikulum {$subject}.",
                    'options'     => $optionsList
                ];
            }
        }

        $mockPayload = [
            'subject'     => $subject,
            'grade_level' => $grade,
            'topic'       => $topic,
            'total_count' => count($questions),
            'questions'   => $questions
        ];

        return [
            'success' => true,
            'content' => json_encode($mockPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'raw'     => $mockPayload,
            'usage'   => ['prompt_tokens' => 250, 'completion_tokens' => 1200, 'total_tokens' => 1450]
        ];
    }
}
