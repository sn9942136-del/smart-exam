<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Exam Event Model (UC-22, UC-22c)
 * Mencatat deret waktu peristiwa sesi ujian (reset sesi, perubahan timer, peringatan, diskualifikasi).
 */
class ExamEventModel extends BaseModel
{
    protected string $table = 'exam_events';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Only created_at in schema

    protected array $allowedFields = [
        'exam_session_id',
        'event_name',
        'payload',
        'created_at'
    ];

    /**
     * Catat peristiwa baru pada sesi ujian
     */
    public static function log(int $examSessionId, string $eventName, array $payload = []): int|string|bool
    {
        $model = new self();
        return $model->insert([
            'exam_session_id' => $examSessionId,
            'event_name'      => $eventName,
            'payload'         => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at'      => date('Y-m-d H:i:s')
        ]);
    }
}
