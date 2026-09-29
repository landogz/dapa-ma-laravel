<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostLike extends Model
{
    public const REACTION_LIKE = 'like';

    public const REACTION_LOVE = 'love';

    public const REACTION_HAHA = 'haha';

    public const REACTION_SAD = 'sad';

    public const REACTION_ANGRY = 'angry';

    public const REACTION_TYPES = [
        self::REACTION_LIKE,
        self::REACTION_LOVE,
        self::REACTION_HAHA,
        self::REACTION_SAD,
        self::REACTION_ANGRY,
    ];

    protected $fillable = [
        'user_id',
        'post_id',
        'reaction',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public static function emptyReactionCounts(): array
    {
        return array_fill_keys(self::REACTION_TYPES, 0);
    }
}
