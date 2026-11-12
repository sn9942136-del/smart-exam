<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionBankModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;

/**
 * Question Service
 * Menangani logika bisnis butir soal, validasi komprehensif, upload gambar, dan opsi jawaban dinamis.
 */
class QuestionService
{
    protected QuestionModel $questionModel;
    protected QuestionOptionModel $optionModel;
    protected QuestionBankModel $bankModel;

    public function __construct()
    {
        $this->questionModel = new QuestionModel();
        $this->optionModel = new QuestionOptionModel();
        $this->bankModel = new QuestionBankModel();
    }

    /**
     * Buat butir soal baru beserta opsi jawaban dalam satu database transaction
     */
    public function createQuestion(int $bankId, array $data, ?array $file = null): array
    {
        // 1. Validasi input
        $validation = $this->validateQuestionData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'question_id' => null
            ];
        }

        // 2. Handle upload gambar jika ada
        $imagePath = null;
        if ($file !== null && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadRes = $this->handleImageUpload($file, $bankId);
            if (!$uploadRes['success']) {
                return [
                    'success' => false,
                    'errors' => ['question_image' => $uploadRes['error']],
                    'question_id' => null
                ];
            }
            $imagePath = $uploadRes['file_path'];
        }

        $db = \App\Libraries\Database::getConnection();
        $db->beginTransaction();

        try {
            $nextOrder = $this->questionModel->getNextOrderNumber($bankId);
            $type = (string)($data['type'] ?? 'multiple_choice');
            $weight = (float)($data['weight'] ?? 1.0);
            $difficulty = in_array($data['difficulty'] ?? '', ['easy', 'medium', 'hard'], true) 
                ? (string)$data['difficulty'] 
                : 'medium';

            $questionId = $this->questionModel->insert([
                'question_bank_id' => $bankId,
                'type' => $type,
                'content' => sanitize_html(trim((string)$data['content'])),
                'question_image' => $imagePath,
                'question_audio' => null,
                'explanation' => !empty($data['explanation']) ? sanitize_html(trim((string)$data['explanation'])) : null,
                'weight' => $weight,
                'difficulty' => $difficulty,
                'order_number' => (int)($data['order_number'] ?? $nextOrder),
            ]);

            // Simpan opsi jawaban sesuai tipe
            $this->saveOptionsForType((int)$questionId, $type, $data);

            // Perbarui jumlah total butir soal pada bank soal
            $this->bankModel->recalculateTotalQuestions($bankId);

            $db->commit();

            return [
                'success' => true,
                'errors' => [],
                'question_id' => (int)$questionId
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            // Bersihkan file yang terlanjur diupload jika gagal
            if ($imagePath && file_exists(APP_ROOT . '/public/' . $imagePath)) {
                @unlink(APP_ROOT . '/public/' . $imagePath);
            }
            return [
                'success' => false,
                'errors' => ['general' => 'Gagal menyimpan soal: ' . $e->getMessage()],
                'question_id' => null
            ];
        }
    }

    /**
     * Perbarui butir soal yang ada
     */
    public function updateQuestion(int $questionId, array $data, ?array $file = null): array
    {
        $existing = $this->questionModel->find($questionId);
        if (!$existing || !empty($existing['deleted_at'])) {
            return [
                'success' => false,
                'errors' => ['general' => 'Butir soal tidak ditemukan.'],
                'question_id' => null
            ];
        }

        $bankId = (int)$existing['question_bank_id'];

        // 1. Validasi input
        $validation = $this->validateQuestionData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'question_id' => $questionId
            ];
        }

        // 2. Handle upload gambar baru atau hapus gambar lama
        $imagePath = $existing['question_image'];
        if (!empty($data['remove_image']) && $data['remove_image'] === '1') {
            if ($imagePath && file_exists(APP_ROOT . '/public/' . $imagePath)) {
                @unlink(APP_ROOT . '/public/' . $imagePath);
            }
            $imagePath = null;
        }

        if ($file !== null && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadRes = $this->handleImageUpload($file, $bankId);
            if (!$uploadRes['success']) {
                return [
                    'success' => false,
                    'errors' => ['question_image' => $uploadRes['error']],
                    'question_id' => $questionId
                ];
            }
            // Hapus gambar lama jika diganti
            if ($imagePath && file_exists(APP_ROOT . '/public/' . $imagePath)) {
                @unlink(APP_ROOT . '/public/' . $imagePath);
            }
            $imagePath = $uploadRes['file_path'];
        }

        $db = \App\Libraries\Database::getConnection();
        $db->beginTransaction();

        try {
            $type = (string)($data['type'] ?? $existing['type']);
            $weight = (float)($data['weight'] ?? $existing['weight']);
            $difficulty = in_array($data['difficulty'] ?? '', ['easy', 'medium', 'hard'], true) 
                ? (string)$data['difficulty'] 
                : $existing['difficulty'];

            $this->questionModel->update($questionId, [
                'type' => $type,
                'content' => sanitize_html(trim((string)$data['content'])),
                'question_image' => $imagePath,
                'explanation' => !empty($data['explanation']) ? sanitize_html(trim((string)$data['explanation'])) : null,
                'weight' => $weight,
                'difficulty' => $difficulty,
                'order_number' => (int)($data['order_number'] ?? $existing['order_number']),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Simpan / refresh opsi jawaban
            $this->saveOptionsForType($questionId, $type, $data);

            // Recalculate
            $this->bankModel->recalculateTotalQuestions($bankId);

            $db->commit();

            return [
                'success' => true,
                'errors' => [],
                'question_id' => $questionId
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            return [
                'success' => false,
                'errors' => ['general' => 'Gagal memperbarui soal: ' . $e->getMessage()],
                'question_id' => $questionId
            ];
        }
    }

    /**
     * Hapus butir soal dan berkas gambar terkait
     */
    public function deleteQuestion(int $questionId): bool
    {
        $existing = $this->questionModel->find($questionId);
        if (!$existing) {
            return false;
        }

        $bankId = (int)$existing['question_bank_id'];

        // Hapus file gambar jika ada
        if (!empty($existing['question_image'])) {
            $fullPath = APP_ROOT . '/public/' . $existing['question_image'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        // Hapus opsi dan soal
        $this->optionModel->deleteByQuestionId($questionId);
        $res = $this->questionModel->delete($questionId);

        // Update count
        $this->bankModel->recalculateTotalQuestions($bankId);

        return (bool)$res;
    }

    /**
     * Validasi data input soal sesuai spesifikasi
     */
    public function validateQuestionData(array $data): array
    {
        $errors = [];

        // 1. Soal wajib diisi
        $content = trim((string)($data['content'] ?? ''));
        if ($content === '') {
            $errors['content'] = 'Teks pertanyaan butir soal wajib diisi.';
        }

        // 2. Nilai / Bobot soal harus valid (> 0)
        $weight = isset($data['weight']) ? (float)$data['weight'] : 0.0;
        if ($weight <= 0.0) {
            $errors['weight'] = 'Bobot nilai soal harus berupa angka positif (lebih dari 0).';
        }

        // 3. Tingkat kesulitan
        $difficulty = (string)($data['difficulty'] ?? '');
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $errors['difficulty'] = 'Pilih tingkat kesulitan yang valid (Mudah, Sedang, Sukar).';
        }

        // 4. Jenis soal & opsi
        $type = (string)($data['type'] ?? 'multiple_choice');
        if (!in_array($type, ['multiple_choice', 'essay', 'true_false'], true)) {
            $errors['type'] = 'Jenis soal tidak valid.';
        }

        if ($type === 'multiple_choice') {
            $options = $data['options'] ?? [];
            if (!is_array($options) || count($options) < 2) {
                $errors['options'] = 'Pilihan ganda minimal harus memiliki 2 pilihan jawaban (misal A dan B).';
            } else {
                $filledCount = 0;
                foreach ($options as $key => $optContent) {
                    if (trim((string)$optContent) !== '') {
                        $filledCount++;
                    }
                }
                if ($filledCount < 2) {
                    $errors['options'] = 'Teks pilihan jawaban minimal 2 opsi tidak boleh kosong.';
                }
            }

            $correctKey = $data['correct_option'] ?? null;
            if ($correctKey === null || trim((string)$correctKey) === '') {
                $errors['correct_option'] = 'Kunci jawaban benar wajib dipilih untuk soal pilihan ganda.';
            }
        } elseif ($type === 'true_false') {
            $correctKey = $data['correct_tf'] ?? null;
            if ($correctKey !== 'Benar' && $correctKey !== 'Salah') {
                $errors['correct_tf'] = 'Kunci jawaban (Benar atau Salah) wajib dipilih.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Simpan opsi jawaban sesuai tipe soal
     */
    protected function saveOptionsForType(int $questionId, string $type, array $data): void
    {
        if ($type === 'multiple_choice') {
            $rawOptions = $data['options'] ?? [];
            $correctKey = (string)($data['correct_option'] ?? 'A');
            $weight = (float)($data['weight'] ?? 1.0);

            $optionsToSave = [];
            foreach ($rawOptions as $key => $content) {
                $contentTrimmed = sanitize_html(trim((string)$content));
                if ($contentTrimmed === '') {
                    continue;
                }
                $isCorrect = (strtoupper((string)$key) === strtoupper($correctKey)) ? 1 : 0;
                $optionsToSave[] = [
                    'option_key' => strtoupper((string)$key),
                    'content' => $contentTrimmed,
                    'option_image' => null,
                    'is_correct' => $isCorrect,
                    'score_value' => $isCorrect ? $weight : 0.0
                ];
            }
            $this->optionModel->saveBulkOptions($questionId, $optionsToSave);

        } elseif ($type === 'true_false') {
            $correctTf = (string)($data['correct_tf'] ?? 'Benar');
            $weight = (float)($data['weight'] ?? 1.0);

            $optionsToSave = [
                [
                    'option_key' => 'A',
                    'content' => 'Benar',
                    'option_image' => null,
                    'is_correct' => ($correctTf === 'Benar') ? 1 : 0,
                    'score_value' => ($correctTf === 'Benar') ? $weight : 0.0
                ],
                [
                    'option_key' => 'B',
                    'content' => 'Salah',
                    'option_image' => null,
                    'is_correct' => ($correctTf === 'Salah') ? 1 : 0,
                    'score_value' => ($correctTf === 'Salah') ? $weight : 0.0
                ]
            ];
            $this->optionModel->saveBulkOptions($questionId, $optionsToSave);

        } elseif ($type === 'essay') {
            // Soal esai tidak memerlukan baris opsi jawaban di question_options
            $this->optionModel->deleteByQuestionId($questionId);
        }
    }

    /**
     * Unggah dan verifikasi keamanan berkas gambar soal
     */
    protected function handleImageUpload(array $file, int $bankId): array
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxSizeBytes = 3 * 1024 * 1024; // 3MB

        $origName = (string)($file['name'] ?? 'image.jpg');
        $tmpName = (string)($file['tmp_name'] ?? '');
        $fileSize = (int)($file['size'] ?? 0);

        if ($fileSize > $maxSizeBytes) {
            return [
                'success' => false,
                'error' => 'Ukuran gambar maksimal adalah 3 MB.'
            ];
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return [
                'success' => false,
                'error' => 'Format gambar tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.'
            ];
        }

        // Verifikasi MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes, true)) {
            return [
                'success' => false,
                'error' => 'Tipe berkas tidak valid sebagai gambar.'
            ];
        }

        // Buat folder upload jika belum ada
        $uploadDir = APP_ROOT . '/public/uploads/questions';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $uniqueName = sprintf('q_%d_%s_%s.%s', $bankId, date('YmdHis'), bin2hex(random_bytes(4)), $ext);
        $targetDestination = $uploadDir . '/' . $uniqueName;

        $moved = is_uploaded_file($tmpName) 
            ? move_uploaded_file($tmpName, $targetDestination) 
            : copy($tmpName, $targetDestination);

        if (!$moved) {
            return [
                'success' => false,
                'error' => 'Gagal memindahkan berkas gambar ke direktori penyimpanan.'
            ];
        }

        return [
            'success' => true,
            'file_path' => 'uploads/questions/' . $uniqueName
        ];
    }
}
