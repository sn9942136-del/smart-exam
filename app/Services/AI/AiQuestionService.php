<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Libraries\Database;
use App\Models\AiQuestionGenerationModel;
use App\Models\AuditLogModel;
use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SubjectModel;
use Throwable;

/**
 * AI Question Service
 * Mengorkestrasi pembuatan draf soal otomatis berbasis AI (UC-21c),
 * validasi skema respon JSON, peninjauan draf oleh guru, dan impor atomik ke Bank Soal.
 */
class AiQuestionService
{
    protected AiQuestionGenerationModel $generationModel;
    protected QuestionBankModel $bankModel;
    protected QuestionModel $questionModel;
    protected QuestionOptionModel $optionModel;
    protected SubjectModel $subjectModel;

    public function __construct()
    {
        $this->generationModel = new AiQuestionGenerationModel();
        $this->bankModel = new QuestionBankModel();
        $this->questionModel = new QuestionModel();
        $this->optionModel = new QuestionOptionModel();
        $this->subjectModel = new SubjectModel();
    }

    /**
     * Memilih AI Provider aktif berdasarkan preferensi atau konfigurasi sistem
     */
    public function resolveProvider(?string $preferredProvider = null): AiProviderInterface
    {
        $providerName = strtolower(trim((string)($preferredProvider ?? $_ENV['AI_PROVIDER'] ?? 'gemini')));

        if ($providerName === 'mock') {
            return new MockAiProvider();
        }

        if ($providerName === 'openai') {
            return new OpenAiProvider();
        }

        // Default & Primary: Google Gemini Provider
        return new GeminiProvider();
    }

    /**
     * Bangun prompt komprehensif dan panggil model AI untuk menghasilkan draf soal (UC-21c)
     *
     * @param array $params Input guru (bank_id, subject, grade, topic, question_type, count, difficulty, language, instructions)
     * @param int $userId ID guru yang meminta pembuatan soal
     * @return array{success: bool, generation_id?: int, questions?: array, summary?: array, error?: string}
     */
    public function generateQuestions(array $params, int $userId): array
    {
        $bankId = (int)($params['question_bank_id'] ?? 0);
        $bank = $this->bankModel->find($bankId);

        if (!$bank || !empty($bank['deleted_at'])) {
            return ['success' => false, 'error' => 'Bank Soal tujuan tidak ditemukan.'];
        }

        $subjectName = trim((string)($params['subject_name'] ?? ''));
        if (empty($subjectName) && !empty($bank['subject_id'])) {
            $subj = $this->subjectModel->find((int)$bank['subject_id']);
            $subjectName = $subj['name'] ?? 'Umum';
        }

        $topic = trim((string)($params['topic'] ?? ''));
        if (empty($topic)) {
            return ['success' => false, 'error' => 'Topik / Materi pembelajaran wajib diisi.'];
        }

        $gradeLevel = trim((string)($params['grade_level'] ?? 'Kelas 10'));
        $questionType = (string)($params['question_type'] ?? 'multiple_choice');
        $questionCount = max(1, min(30, (int)($params['question_count'] ?? 5)));
        $difficulty = (string)($params['difficulty'] ?? 'medium');
        $language = (string)($params['language'] ?? 'id');
        $customPrompt = trim((string)($params['custom_instructions'] ?? ''));

        // 1. Susun Prompt Instruksi Kurikulum
        $constructedPrompt = $this->buildPrompt(
            $subjectName,
            $gradeLevel,
            $topic,
            $questionType,
            $questionCount,
            $difficulty,
            $language,
            $customPrompt
        );

        // 2. Pilih Provider AI & Eksekusi
        $provider = $this->resolveProvider($params['provider'] ?? null);
        $completion = $provider->generateCompletion($constructedPrompt, [
            'temperature'        => 0.7,
            'max_tokens'         => 8192,
            'response_mime_type' => 'application/json',
            'system_instruction' => "Anda adalah Asisten Pakar Pembuat Soal Ujian Kurikulum Indonesia. Anda WAJIB membalas HANYA dalam format JSON terstruktur yang valid tanpa teks pembuka atau penutup markdown."
        ]);

        if (!$completion['success'] || empty($completion['content'])) {
            // Catat log kegagalan jika ada
            $this->generationModel->insert([
                'question_bank_id' => $bankId,
                'user_id'          => $userId,
                'prompt'           => $constructedPrompt,
                'subject_topic'    => $topic,
                'grade_level'      => $gradeLevel,
                'question_type'    => $questionType,
                'language'         => $language,
                'difficulty_level' => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium',
                'question_count'   => $questionCount,
                'accepted_count'   => 0,
                'raw_response'     => null,
                'error_message'    => $completion['error'] ?? 'Gagal mendapatkan respon dari AI',
                'status'           => 'failed'
            ]);

            return [
                'success' => false,
                'error'   => 'AI Generator gagal: ' . ($completion['error'] ?? 'Respon kosong dari provider.')
            ];
        }

        // 3. Validasi Skema JSON Respon
        $validation = $this->validateGeneratedQuestions($completion['content']);
        if (!$validation['valid']) {
            $this->generationModel->insert([
                'question_bank_id' => $bankId,
                'user_id'          => $userId,
                'prompt'           => $constructedPrompt,
                'subject_topic'    => $topic,
                'grade_level'      => $gradeLevel,
                'question_type'    => $questionType,
                'language'         => $language,
                'difficulty_level' => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium',
                'question_count'   => $questionCount,
                'accepted_count'   => 0,
                'raw_response'     => $completion['content'],
                'error_message'    => 'Validasi JSON gagal: ' . $validation['error'],
                'status'           => 'failed'
            ]);

            return [
                'success' => false,
                'error'   => 'Struktur data respon AI tidak valid: ' . $validation['error']
            ];
        }

        $questions = $validation['questions'];

        // 4. Simpan Draf ke ai_question_generations (Human-in-the-loop pending review)
        $generationId = (int)$this->generationModel->insert([
            'question_bank_id' => $bankId,
            'user_id'          => $userId,
            'prompt'           => $constructedPrompt,
            'subject_topic'    => $topic,
            'grade_level'      => $gradeLevel,
            'question_type'    => $questionType,
            'language'         => $language,
            'difficulty_level' => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium',
            'question_count'   => count($questions),
            'accepted_count'   => 0,
            'raw_response'     => json_encode($questions, JSON_UNESCAPED_UNICODE),
            'error_message'    => null,
            'status'           => 'pending'
        ]);

        return [
            'success'       => true,
            'generation_id' => $generationId,
            'provider'      => $provider->getProviderName(),
            'questions'     => $questions,
            'summary'       => [
                'bank_id'        => $bankId,
                'bank_title'     => $bank['title'] ?? '',
                'subject_name'   => $subjectName,
                'grade_level'    => $gradeLevel,
                'topic'          => $topic,
                'total_generated'=> count($questions)
            ]
        ];
    }

    /**
     * Membangun prompt berbasis standar kurikulum untuk AI
     */
    protected function buildPrompt(
        string $subject,
        string $grade,
        string $topic,
        string $type,
        int $count,
        string $difficulty,
        string $language,
        string $customInstructions
    ): string {
        $langLabel = match ($language) {
            'en'    => 'Bahasa Inggris (English)',
            'ar'    => 'Bahasa Arab (Arabic)',
            default => 'Bahasa Indonesia'
        };

        $typeLabel = match ($type) {
            'essay'      => 'Esai / Uraian (Essay)',
            'true_false' => 'Benar / Salah (True / False)',
            'mixed'      => 'Campuran Pilihan Ganda dan Esai',
            default      => 'Pilihan Ganda (Multiple Choice 4 Opsi: A, B, C, D)'
        };

        $prompt = "Buatkan {$count} butir soal ujian berkualitas tinggi berdasarkan parameter kurikulum berikut:\n";
        $prompt .= "- Mata Pelajaran: {$subject}\n";
        $prompt .= "- Kelas / Tingkat: {$grade}\n";
        $prompt .= "- Materi / Topik: {$topic}\n";
        $prompt .= "- Jenis Soal: {$typeLabel}\n";
        $prompt .= "- Jumlah Soal: {$count}\n";
        $prompt .= "- Tingkat Kesulitan: {$difficulty}\n";
        $prompt .= "- Bahasa Pengantar: {$langLabel}\n";

        if (!empty($customInstructions)) {
            $prompt .= "- Instruksi Khusus Tambahan: {$customInstructions}\n";
        }

        $prompt .= "\nKetentuan Output Respon AI:\n";
        $prompt .= "1. Format respon WAJIB berupa JSON murni dengan objek root 'questions' berisi array butir soal.\n";
        $prompt .= "2. Setiap elemen dalam array 'questions' harus memiliki struktur:\n";
        $prompt .= "   - 'number': integer urutan (1, 2, ...)\n";
        $prompt .= "   - 'type': string ('multiple_choice' / 'essay' / 'true_false')\n";
        $prompt .= "   - 'difficulty': string ('easy' / 'medium' / 'hard')\n";
        $prompt .= "   - 'weight': float bobot nilai (misal: 1.0 atau 2.0 untuk PG, 4.0 untuk Esai)\n";
        $prompt .= "   - 'content': string teks pertanyaan lengkap, jelas, dan kontekstual\n";
        $prompt .= "   - 'explanation': string pembahasan/kunci penjelasan mengapa jawaban tersebut benar\n";
        $prompt .= "   - 'options': array objek pilihan (hanya untuk multiple_choice dan true_false). Setiap opsi berisi:\n";
        $prompt .= "     * 'label': 'A', 'B', 'C', 'D'\n";
        $prompt .= "     * 'text': string teks pilihan jawaban\n";
        $prompt .= "     * 'is_correct': boolean (true untuk 1 jawaban benar, false untuk lainnya)\n";
        $prompt .= "3. Pastikan tepat 1 opsi bernilai is_correct = true untuk soal pilihan ganda.\n";
        $prompt .= "4. Hindari membuat soal yang ambigu atau duplikasi.\n";

        return $prompt;
    }

    /**
     * Memvalidasi dan membersihkan data respon JSON dari AI
     */
    public function validateGeneratedQuestions(mixed $rawInput): array
    {
        $data = null;
        if (is_string($rawInput)) {
            $clean = trim($rawInput);
            // 1. Bersihkan markdown code blocks jika ada (```json ... ```)
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean, $matches)) {
                $clean = trim($matches[1]);
            }
            $data = json_decode($clean, true);

            // 2. Jika gagal, coba ekstrak substring antara { ... } atau [ ... ] terluar
            if (!is_array($data)) {
                $firstBrace = strpos($clean, '{');
                $lastBrace = strrpos($clean, '}');
                if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                    $jsonSub = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
                    $data = json_decode($jsonSub, true);
                }
            }
        } elseif (is_array($rawInput)) {
            $data = $rawInput;
        }

        if (!is_array($data)) {
            return ['valid' => false, 'error' => 'Respon bukan format JSON yang valid.', 'questions' => []];
        }

        $rawList = $data['questions'] ?? (isset($data[0]) ? $data : []);
        if (!is_array($rawList) || empty($rawList)) {
            return ['valid' => false, 'error' => 'Array "questions" tidak ditemukan atau kosong.', 'questions' => []];
        }

        $sanitizedQuestions = [];
        foreach ($rawList as $idx => $q) {
            $num = $idx + 1;
            $content = sanitize_html(trim((string)($q['content'] ?? $q['question'] ?? '')));
            if (empty($content)) {
                return ['valid' => false, 'error' => "Soal #{$num} tidak memiliki teks pertanyaan ('content').", 'questions' => []];
            }

            $type = (string)($q['type'] ?? 'multiple_choice');
            if (!in_array($type, ['multiple_choice', 'single_choice', 'essay', 'true_false'], true)) {
                $type = 'multiple_choice';
            }

            $difficulty = strtolower((string)($q['difficulty'] ?? 'medium'));
            if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = 'medium';
            }

            $weight = (float)($q['weight'] ?? ($type === 'essay' ? 4.0 : 2.0));
            $explanation = sanitize_html(trim((string)($q['explanation'] ?? $q['pembahasan'] ?? '')));

            $options = [];
            if ($type !== 'essay') {
                $rawOptions = $q['options'] ?? $q['pilihan'] ?? [];
                if (!is_array($rawOptions) || count($rawOptions) < 2) {
                    return ['valid' => false, 'error' => "Soal pilihan #{$num} harus memiliki minimal 2 opsi jawaban.", 'questions' => []];
                }

                $hasCorrect = false;
                $defaultLabels = ['A', 'B', 'C', 'D', 'E'];

                foreach ($rawOptions as $optIdx => $opt) {
                    $optLabel = $opt['label'] ?? ($defaultLabels[$optIdx] ?? chr(65 + $optIdx));
                    $optText = sanitize_html(trim((string)($opt['text'] ?? $opt['option'] ?? $opt['pilihan'] ?? '')));
                    $isCorrect = !empty($opt['is_correct']) || (!empty($q['correct_answer']) && strtoupper((string)$q['correct_answer']) === strtoupper($optLabel));

                    if ($isCorrect) {
                        $hasCorrect = true;
                    }

                    $options[] = [
                        'label'      => strtoupper((string)$optLabel),
                        'text'       => $optText,
                        'is_correct' => $isCorrect ? 1 : 0
                    ];
                }

                // Jika tidak ada kunci benar yang terdeteksi, jadikan opsi A sebagai default
                if (!$hasCorrect && !empty($options)) {
                    $options[0]['is_correct'] = 1;
                }
            }

            $sanitizedQuestions[] = [
                'number'      => $num,
                'type'        => $type,
                'difficulty'  => $difficulty,
                'weight'      => $weight > 0 ? $weight : 1.0,
                'content'     => $content,
                'explanation' => $explanation,
                'options'     => $options
            ];
        }

        return [
            'valid'     => true,
            'questions' => $sanitizedQuestions
        ];
    }

    /**
     * Dapatkan draf generasi soal AI yang siap direview oleh guru
     */
    public function getDraftForReview(int $generationId, int $userId): ?array
    {
        $batch = $this->generationModel->getDetailWithBank($generationId, $userId);
        if (!$batch) {
            return null;
        }

        $questions = [];
        if (!empty($batch['raw_response'])) {
            $parsed = json_decode($batch['raw_response'], true);
            if (is_array($parsed)) {
                $questions = $parsed;
            }
        }

        $batch['questions'] = $questions;
        return $batch;
    }

    /**
     * Mengimpor butir soal terpilih yang telah disetujui guru ke Bank Soal (UC-21c Human Approval)
     *
     * @param int $generationId ID batch ai_question_generations
     * @param array $approvedQuestions Array butir soal yang telah diperiksa/diedit oleh guru
     * @param int $teacherId ID guru yang menyetujui
     * @return array{success: bool, imported_count?: int, bank_id?: int, error?: string}
     */
    public function importApprovedQuestions(int $generationId, array $approvedQuestions, int $teacherId): array
    {
        $batch = $this->generationModel->find($generationId);
        if (!$batch) {
            return ['success' => false, 'error' => 'Draf generasi soal tidak ditemukan.'];
        }

        $bankId = (int)$batch['question_bank_id'];
        $bank = $this->bankModel->find($bankId);
        if (!$bank || !empty($bank['deleted_at'])) {
            return ['success' => false, 'error' => 'Bank Soal tujuan tidak ditemukan.'];
        }

        if (empty($approvedQuestions)) {
            return ['success' => false, 'error' => 'Pilih minimal 1 butir soal untuk disimpan ke Bank Soal.'];
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $importedCount = 0;
            $nextOrder = $this->questionModel->getNextOrderNumber($bankId);

            foreach ($approvedQuestions as $q) {
                $content = sanitize_html(trim((string)($q['content'] ?? '')));
                if (empty($content)) {
                    continue;
                }

                $type = (string)($q['type'] ?? 'multiple_choice');
                $difficulty = in_array($q['difficulty'] ?? '', ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium';
                $weight = (float)($q['weight'] ?? ($type === 'essay' ? 4.0 : 2.0));
                $explanation = !empty($q['explanation']) ? sanitize_html(trim((string)$q['explanation'])) : null;

                // 1. Simpan butir soal
                $questionId = (int)$this->questionModel->insert([
                    'question_bank_id' => $bankId,
                    'type'             => $type,
                    'content'          => $content,
                    'question_image'   => null,
                    'question_audio'   => null,
                    'explanation'      => $explanation,
                    'weight'           => $weight,
                    'difficulty'       => $difficulty,
                    'order_number'     => $nextOrder++,
                    'created_at'       => date('Y-m-d H:i:s'),
                    'updated_at'       => date('Y-m-d H:i:s')
                ]);

                // 2. Simpan opsi jawaban (jika bukan esai)
                if ($type !== 'essay' && !empty($q['options']) && is_array($q['options'])) {
                    $defaultLabels = ['A', 'B', 'C', 'D', 'E'];
                    foreach ($q['options'] as $optIdx => $opt) {
                        $optText = sanitize_html(trim((string)($opt['text'] ?? '')));
                        if ($optText === '') continue;

                        $optKey = (string)($opt['label'] ?? ($defaultLabels[$optIdx] ?? chr(65 + $optIdx)));
                        $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
                        $this->optionModel->insert([
                            'question_id'  => $questionId,
                            'option_key'   => strtoupper($optKey),
                            'content'      => $optText,
                            'option_image' => null,
                            'is_correct'   => $isCorrect,
                            'score_value'  => $isCorrect ? $weight : 0.0,
                            'created_at'   => date('Y-m-d H:i:s'),
                            'updated_at'   => date('Y-m-d H:i:s')
                        ]);
                    }
                }


                $importedCount++;
            }

            // 3. Rekalkulasi total butir soal pada bank soal
            $this->bankModel->recalculateTotalQuestions($bankId);

            // 4. Update status ai_question_generations
            $this->generationModel->update($generationId, [
                'status'         => 'success',
                'accepted_count' => $importedCount,
                'updated_at'     => date('Y-m-d H:i:s')
            ]);

            // 5. Catat Audit Log
            AuditLogModel::record(
                'AI_IMPORT_QUESTIONS',
                'question_banks',
                (string)$bankId,
                null,
                [
                    'generation_id'   => $generationId,
                    'imported_count'  => $importedCount,
                    'teacher_id'      => $teacherId,
                    'bank_title'      => $bank['title'] ?? ''
                ]
            );

            $db->commit();

            return [
                'success'        => true,
                'imported_count' => $importedCount,
                'bank_id'        => $bankId,
                'message'        => "Berhasil menyimpan {$importedCount} butir soal AI ke Bank Soal '{$bank['title']}'."
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            return [
                'success' => false,
                'error'   => 'Gagal menyimpan butir soal ke database: ' . $e->getMessage()
            ];
        }
    }
}
