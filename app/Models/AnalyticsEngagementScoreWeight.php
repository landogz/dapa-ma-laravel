<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEngagementScoreWeight extends Model
{
    protected $fillable = [
        'event_key',
        'label',
        'points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
