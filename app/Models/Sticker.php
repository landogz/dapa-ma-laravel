<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Sticker extends Model
{
    protected $fillable = [
        'sticker_pack_id',
        'name',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(StickerPack::class, 'sticker_pack_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url(ltrim($this->image_path, '/'));
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'sticker_pack_id' => $this->sticker_pack_id,
            'name' => $this->name,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
