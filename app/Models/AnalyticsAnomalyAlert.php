<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsAnomalyAlert extends Model
{
    protected $fillable = [
        'rule_id',
        'title',
        'description',
        'metric_key',
        'previous_value',
        'current_value',
        'percent_change',
        'severity',
        'status',
        'fingerprint',
        'detected_at',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'previous_value' => 'float',
            'current_value' => 'float',
            'percent_change' => 'float',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AnalyticsAlertRule::class, 'rule_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
