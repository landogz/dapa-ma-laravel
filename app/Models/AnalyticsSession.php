<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'platform',
        'device_category',
        'app_version',
        'started_at',
        'last_activity_at',
        'ended_at',
        'duration_seconds',
        'entry_screen',
        'exit_screen',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
