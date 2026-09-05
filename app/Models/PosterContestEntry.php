<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosterContestEntry extends Model
{
    protected $fillable = [
        'poster_contest_id',
        'user_id',
        'title',
        'creator_name',
        'description',
        'poster_image_url',
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
        return $this->belongsTo(PosterContest::class, 'poster_contest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getPosterImageUrlAttribute(?string $value): ?string
    {
        return PosterContest::normalizePublicUrl($value);
    }
}
