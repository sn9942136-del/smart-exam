<?php

declare(strict_types=1);

namespace App\Models;

/**
 * AI Violation Model
 */
class AiViolationModel extends BaseModel
{
    protected string $table = 'ai_violations';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'proctoring_session_id',
        'violation_type_id',
        'camera_device_id',
        'confidence_score',
        'evidence_snapshot_url',
        'evidence_video_clip_url',
        'metadata',
        'is_flagged_for_review',
        'proctor_action', // 'pending', 'approved', 'dismissed', 'penalized'
        'created_at',
        'updated_at'
    ];
}
