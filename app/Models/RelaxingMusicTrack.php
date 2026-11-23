<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RelaxingMusicTrack extends Model
{
    protected $fillable = [
        'title',
        'title_tl',
        'audio_path',
        'duration_seconds',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'duration_seconds' => 'integer',
    ];

    protected $appends = [
        'audio_url',
    ];

    public function getAudioUrlAttribute(): ?string
    {
        if (! $this->audio_path) {
            return null;
        }

        return Storage::disk('public')->url(ltrim($this->audio_path, '/'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'title_tl' => $this->title_tl,
            'audio_path' => $this->audio_path,
            'audio_url' => $this->audio_url,
            'duration_seconds' => $this->duration_seconds,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'title_tl' => $this->title_tl ?: $this->title,
            'audio_url' => $this->audio_url,
            'duration_seconds' => $this->duration_seconds,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
