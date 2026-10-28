<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    public const LINK_POST = 'post';

    public const LINK_HOPE_EVENT = 'hope_event';

    protected $fillable = [
        'title',
        'body',
        'topic',
        'link_type',
        'link_id',
        'post_id',
        'sent_by',
        'sent_at',
        'recipient_count',
        'inbox_count',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'link_id' => 'integer',
        'recipient_count' => 'integer',
        'inbox_count' => 'integer',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function hopeEvent(): BelongsTo
    {
        return $this->belongsTo(HopeEvent::class, 'link_id');
    }

    public function resolvedLinkType(): ?string
    {
        if (filled($this->link_type) && filled($this->link_id)) {
            return (string) $this->link_type;
        }

        if (filled($this->post_id)) {
            return self::LINK_POST;
        }

        return null;
    }

    public function resolvedLinkId(): ?int
    {
        if (filled($this->link_type) && filled($this->link_id)) {
            return (int) $this->link_id;
        }

        if (filled($this->post_id)) {
            return (int) $this->post_id;
        }

        return null;
    }
}
