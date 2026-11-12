<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Exam Rule Model
 * Mengelola aturan pengawasan AI Proctoring, deteksi kecurangan, dan batasan browser.
 */
class ExamRuleModel extends BaseModel
{
    protected string $table = 'exam_rules';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'exam_id',
        'allow_tab_switch',
        'max_tab_switches',
        'lock_browser',
        'detect_multiple_faces',
        'detect_look_away',
        'detect_audio_noise',
        'detect_secondary_device',
        'created_at',
        'updated_at'
    ];

    /**
     * Dapatkan aturan ujian berdasarkan exam_id
     */
    public function getByExamId(int $examId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE exam_id = :exam_id LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Simpan atau perbarui aturan ujian
     */
    public function saveRules(int $examId, array $rules): bool
    {
        $existing = $this->getByExamId($examId);

        $data = [
            'allow_tab_switch'        => !empty($rules['allow_tab_switch']) ? 1 : 0,
            'max_tab_switches'        => isset($rules['max_tab_switches']) ? max(0, (int)$rules['max_tab_switches']) : 3,
            'lock_browser'            => !empty($rules['lock_browser']) ? 1 : 0,
            'detect_multiple_faces'   => !empty($rules['detect_multiple_faces']) ? 1 : 0,
            'detect_look_away'        => !empty($rules['detect_look_away']) ? 1 : 0,
            'detect_audio_noise'      => !empty($rules['detect_audio_noise']) ? 1 : 0,
            'detect_secondary_device' => !empty($rules['detect_secondary_device']) ? 1 : 0,
        ];

        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return (bool)$this->update((int)$existing['id'], $data);
        } else {
            $data['exam_id'] = $examId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            return (bool)$this->insert($data);
        }
    }
}
