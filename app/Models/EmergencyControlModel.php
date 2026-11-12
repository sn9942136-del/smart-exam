<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Emergency Control Model (UC-22a)
 * Mencatat tindakan darurat pengawas seperti penghentian ujian, perpanjangan waktu massal, atau penangguhan sesi.
 */
class EmergencyControlModel extends BaseModel
{
    protected string $table = 'emergency_controls';
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false; // Only created_at in schema

    protected array $allowedFields = [
        'exam_id',
        'triggered_by',
        'event_type',
        'action_taken',
        'reason',
        'affected_students_count',
        'created_at'
    ];
}
