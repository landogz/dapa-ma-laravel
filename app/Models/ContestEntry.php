<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestEntry extends Model
{
    protected $fillable = [
        'contest_id',
        'user_id',
        'title',
        'creator_name',
        'entry_type',
        'description',
        'lyrics',
        'media_url',
        'cover_image_url',
        'poster_image_url',
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
        return $this->belongsTo(Contest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getMediaUrlAttribute(?string $value): ?string
    {
        return Contest::normalizePublicUrl($value);
    }

    public function getCoverImageUrlAttribute(?string $value): ?string
    {
        return Contest::normalizePublicUrl($value);
    }

    public function getPosterImageUrlAttribute(?string $value): ?string
    {
        return Contest::normalizePublicUrl($value);
    }

    public function getVideoUrlAttribute(?string $value): ?string
    {
        return Contest::normalizePublicUrl($value);
    }

    public function getThumbnailUrlAttribute(?string $value): ?string
    {
        return Contest::normalizePublicUrl($value);
    }
}
