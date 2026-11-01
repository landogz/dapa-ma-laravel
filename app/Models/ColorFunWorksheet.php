<?php

namespace App\Models;

use App\Support\AppOptionGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ColorFunWorksheet extends Model
{
    public const FILTER_ALL = 'all';

    public const CATEGORIES = ['nature', 'people', 'messages'];

    /**
     * Active Color Fun lookup keys, including the All filter chip.
     *
     * @return list<string>
     */
    public static function lookupKeys(): array
    {
        $fallback = [self::FILTER_ALL, ...self::CATEGORIES];

        if (! Schema::hasTable('app_options')) {
            return $fallback;
        }

        try {
            $keys = self::queryLookupKeys();

            return $keys !== [] ? $keys : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * Worksheet assignment keys (Lookups minus All).
     *
     * @return list<string>
     */
    public static function worksheetCategories(): array
    {
        $keys = array_values(array_filter(
            self::lookupKeys(),
            static fn (string $key) => $key !== self::FILTER_ALL,
        ));

        return $keys !== [] ? $keys : self::CATEGORIES;
    }

    /**
     * @return list<string>
     */
    private static function queryLookupKeys(): array
    {
        $query = AppOption::query()
            ->where('group', AppOptionGroup::COLOR_FUN)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        return $query->pluck('key')
            ->map(static fn ($key) => strtolower(trim((string) $key)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected $fillable = [
        'title',
        'category',
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
            'title' => $this->title,
            'category' => $this->category,
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
            'title' => $this->title,
            'category' => $this->category,
            'image_url' => $this->image_url,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
