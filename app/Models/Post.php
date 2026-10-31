<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'title',
        'body',
        'category_id',
        'media_url',
        'youtube_url',
        'comments_enabled',
        'is_featured',
        'status',
        'review_notes',
        'pending_revision',
        'revision_status',
        'publish_date',
        'author_id',
    ];

    protected $casts = [
        'comments_enabled' => 'boolean',
        'is_featured' => 'boolean',
        'publish_date' => 'datetime',
        'pending_revision' => 'array',
    ];

    public function hasPendingRevision(): bool
    {
        return filled($this->revision_status) && is_array($this->pending_revision) && $this->pending_revision !== [];
    }

    public function isRevisionAwaitingPublisher(): bool
    {
        return $this->status === 'published' && $this->revision_status === 'pending_review';
    }

    /**
     * @return array<string, mixed>
     */
    public function liveContentSnapshot(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'category_id' => $this->category_id,
            'media_url' => $this->media_url,
            'youtube_url' => $this->youtube_url,
            'comments_enabled' => (bool) $this->comments_enabled,
            'is_featured' => (bool) $this->is_featured,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'target_id')
            ->where('target_type', self::class);
    }
}
