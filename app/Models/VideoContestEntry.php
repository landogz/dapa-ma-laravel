<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoContestEntry extends Model
{
    protected $fillable = [
        'video_contest_id',
        'user_id',
        'title',
        'creator_name',
        'description',
        'video_url',
        'thumbnail_url',
        'region',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(VideoContest::class, 'video_contest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getVideoUrlAttribute(?string $value): ?string
    {
        return VideoContest::normalizePublicUrl($value);
    }

    public function getThumbnailUrlAttribute(?string $value): ?string
    {
        return VideoContest::normalizePublicUrl($value);
    }
}
