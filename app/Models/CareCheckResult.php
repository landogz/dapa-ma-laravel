<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareCheckResult extends Model
{
    public const TYPES = ['stress', 'anxiety', 'sleep'];

    protected $fillable = [
        'user_id',
        'check_type',
        'raw_score',
        'max_score',
        'percent',
        'band',
        'display_tier',
        'sleep_hours',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'integer',
            'max_score' => 'integer',
            'percent' => 'integer',
            'sleep_hours' => 'float',
            'answers' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
