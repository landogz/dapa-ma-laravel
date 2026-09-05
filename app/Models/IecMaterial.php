<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class IecMaterial extends Model
{
    protected $fillable = [
        'title',
        'description',
        'topic',
        'media_type',
        'media_url',
        'thumbnail_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getMediaUrlAttribute(?string $value): ?string
    {
        return self::normalizePublicUrl($value);
    }

    public function getThumbnailUrlAttribute(?string $value): ?string
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
