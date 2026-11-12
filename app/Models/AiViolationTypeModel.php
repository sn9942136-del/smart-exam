<?php

declare(strict_types=1);

namespace App\Models;

/**
 * AI Violation Type Model
 */
class AiViolationTypeModel extends BaseModel
{
    protected string $table = 'ai_violation_types';
    protected string $primaryKey = 'id';

    protected array $allowedFields = [
        'code',
        'name',
        'severity',
        'penalty_score',
        'description',
        'created_at',
        'updated_at'
    ];
}
