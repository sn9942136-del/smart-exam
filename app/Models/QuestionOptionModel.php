<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Question Option Model
 * Mengelola opsi pilihan jawaban untuk butir soal (Pilihan Ganda, Benar/Salah).
 */
class QuestionOptionModel extends BaseModel
{
    protected string $table = 'question_options';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'question_id',
        'option_key', // 'A', 'B', 'C', 'D', 'E', 'Benar', 'Salah'
        'content',
        'option_image',
        'is_correct', // 1 or 0
        'score_value',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan semua opsi pilihan jawaban untuk suatu butir soal
     */
    public function getByQuestionId(int|string $questionId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE question_id = :qid ORDER BY id ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':qid', (int)$questionId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Hapus seluruh opsi untuk suatu butir soal
     */
    public function deleteByQuestionId(int|string $questionId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE question_id = :qid";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':qid', (int)$questionId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Simpan kumpulan opsi secara masal
     */
    public function saveBulkOptions(int $questionId, array $options): bool
    {
        // Hapus opsi lama terlebih dahulu
        $this->deleteByQuestionId($questionId);

        if (empty($options)) {
            return true;
        }

        $sql = "INSERT INTO {$this->table} 
                (question_id, option_key, content, option_image, is_correct, score_value, created_at, updated_at) 
                VALUES (:question_id, :option_key, :content, :option_image, :is_correct, :score_value, NOW(), NOW())";
        
        $stmt = $this->db()->prepare($sql);

        foreach ($options as $opt) {
            $stmt->bindValue(':question_id', $questionId, \PDO::PARAM_INT);
            $stmt->bindValue(':option_key', (string)($opt['option_key'] ?? 'A'));
            $stmt->bindValue(':content', (string)($opt['content'] ?? ''));
            $stmt->bindValue(':option_image', !empty($opt['option_image']) ? (string)$opt['option_image'] : null, \PDO::PARAM_STR);
            $stmt->bindValue(':is_correct', !empty($opt['is_correct']) ? 1 : 0, \PDO::PARAM_INT);
            $stmt->bindValue(':score_value', (float)($opt['score_value'] ?? 0.0), \PDO::PARAM_STR);
            $stmt->execute();
        }

        return true;
    }

    /**
     * Cek apakah opsi jawaban tertentu memang milik butir soal yang bersangkutan
     */
    public function isOptionBelongsToQuestion(int $questionId, int $optionId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE id = :opt_id AND question_id = :qid LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':opt_id', $optionId, \PDO::PARAM_INT);
        $stmt->bindValue(':qid', $questionId, \PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }
}
