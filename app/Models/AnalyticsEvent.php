<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'event_type',
        'post_id',
        'resource_type',
        'resource_id',
        'user_id',
        'session_id',
        'platform',
        'occurred_at',
        'screen_name',
        'page_path',
        'app_version',
        'device_category',
        'os_name',
        'browser',
        'duration_ms',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Prefer occurred_at when present for analytics windows. */
    public function scopeInWindow($query, $from, $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->where(function ($inner) use ($from, $to) {
                $inner->whereNotNull('occurred_at')
                    ->where('occurred_at', '>=', $from)
                    ->where('occurred_at', '<=', $to);
            })->orWhere(function ($inner) use ($from, $to) {
                $inner->whereNull('occurred_at')
                    ->where('created_at', '>=', $from)
                    ->where('created_at', '<=', $to);
            });
        });
    }
}
