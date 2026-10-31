<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsAlertRule extends Model
{
    protected $fillable = [
        'key',
        'name',
        'metric_key',
        'comparison',
        'lookback_days',
        'threshold_percent',
        'min_volume',
        'direction',
        'severity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_percent' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnalyticsAnomalyAlert::class, 'rule_id');
    }
}
