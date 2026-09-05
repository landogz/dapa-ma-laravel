<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongContestEntry extends Model
{
    protected $fillable = [
        'song_contest_id',
        'user_id',
        'title',
        'artist_name',
        'entry_type',
        'description',
        'lyrics',
        'media_url',
        'cover_image_url',
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
        return $this->belongsTo(SongContest::class, 'song_contest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
