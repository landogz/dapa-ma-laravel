<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Contest extends Model
{
    protected $fillable = [
        'category',
        'title',
        'description',
        'rules',
        'theme',
        'allowed_entry_types',
        'contest_year',
        'submission_starts_at',
        'submission_ends_at',
        'status',
        'cover_image_url',
        'is_active',
    ];

    protected $casts = [
        'contest_year' => 'integer',
        'submission_starts_at' => 'date',
        'submission_ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'is_open_for_submission',
        'entries_count',
        'pending_count',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(ContestEntry::class);
    }

    public function getIsOpenForSubmissionAttribute(): bool
    {
        if (! $this->is_active || $this->status !== 'open') {
            return false;
        }

        // Calendar-day window in PH local time (admin date pickers are local dates).
        $today = now('Asia/Manila')->toDateString();

        if ($this->submission_starts_at && $today < $this->submission_starts_at->toDateString()) {
            return false;
        }

        if ($this->submission_ends_at && $today > $this->submission_ends_at->toDateString()) {
            return false;
        }

        return true;
    }

    public function getEntriesCountAttribute(): int
    {
        if (array_key_exists('entries_count', $this->attributes)) {
            return (int) $this->attributes['entries_count'];
        }

        return $this->entries()->count();
    }

    public function getPendingCountAttribute(): int
    {
        if (array_key_exists('pending_count', $this->attributes)) {
            return (int) $this->attributes['pending_count'];
        }

        return $this->entries()->where('status', 'pending')->count();
    }

    public function allowsEntryType(string $type): bool
    {
        if ($this->category !== 'song') {
            return false;
        }

        if ($this->allowed_entry_types === 'both') {
            return in_array($type, ['song', 'playlist'], true);
        }

        return $this->allowed_entry_types === $type;
    }

    public function getCoverImageUrlAttribute(?string $value): ?string
    {
        return self::normalizePublicUrl($value);
    }

    public static function normalizePublicUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url(ltrim($value, '/'));
    }
}
