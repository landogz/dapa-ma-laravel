<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDailySummary extends Model
{
    protected $fillable = [
        'summary_date',
        'platform',
        'total_events',
        'unique_users',
        'unique_sessions',
        'app_opens',
        'screen_views',
        'post_views',
        'engagements',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'payload' => 'array',
        ];
    }
}
