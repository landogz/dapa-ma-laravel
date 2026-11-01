<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StickerPack extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'cover_url',
        'stickers_count',
    ];

    public function stickers(): HasMany
    {
        return $this->hasMany(Sticker::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_path) {
            $first = $this->relationLoaded('stickers')
                ? $this->stickers->firstWhere('is_active', true) ?? $this->stickers->first()
                : $this->stickers()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();

            return $first?->image_url;
        }

        return Storage::disk('public')->url(ltrim($this->cover_path, '/'));
    }

    public function getStickersCountAttribute(): int
    {
        if (array_key_exists('stickers_count', $this->attributes)) {
            return (int) $this->attributes['stickers_count'];
        }

        if ($this->relationLoaded('stickers')) {
            return $this->stickers->count();
        }

        return (int) $this->stickers()->count();
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_path' => $this->cover_path,
            'cover_url' => $this->cover_url,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'stickers_count' => $this->stickers_count,
            'stickers' => $this->relationLoaded('stickers')
                ? $this->stickers->map(fn (Sticker $sticker) => $sticker->toAdminArray())->values()->all()
                : [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    public function toPublicArray(): array
    {
        $stickers = $this->relationLoaded('stickers')
            ? $this->stickers->where('is_active', true)->values()
            : $this->stickers()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_url' => $this->cover_url,
            'sort_order' => (int) $this->sort_order,
            'stickers' => $stickers->map(fn (Sticker $sticker) => $sticker->toPublicArray())->values()->all(),
        ];
    }
}
