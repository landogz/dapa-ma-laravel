<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareSupportResource extends Model
{
    public const CATEGORIES = [
        'hotline',
        'counseling',
        'crisis_resource',
        'crisis_emergency',
    ];

    protected $fillable = [
        'category',
        'title_en',
        'title_tl',
        'role_en',
        'role_tl',
        'description_en',
        'description_tl',
        'meta_en',
        'meta_tl',
        'phone',
        'web_url',
        'logo_url',
        'logo_initial',
        'icon_key',
        'body_en',
        'body_tl',
        'is_emergency',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'body_en'      => 'array',
            'body_tl'      => 'array',
            'is_emergency' => 'boolean',
            'sort_order'   => 'integer',
            'is_active'    => 'boolean',
        ];
    }

    public function toPublicArray(string $locale = 'en'): array
    {
        $isTl = in_array($locale, ['tl', 'fil'], true);

        return [
            'id'           => $this->id,
            'category'     => $this->category,
            'title'        => $isTl ? $this->title_tl : $this->title_en,
            'role'         => $isTl ? $this->role_tl : $this->role_en,
            'description'  => $isTl ? $this->description_tl : $this->description_en,
            'meta'         => $isTl ? ($this->meta_tl ?: $this->meta_en) : ($this->meta_en ?: $this->meta_tl),
            'phone'        => $this->phone,
            'web_url'      => $this->web_url,
            'logo_url'     => $this->logo_url,
            'logo_initial' => $this->logo_initial,
            'icon_key'     => $this->icon_key,
            'body'         => $isTl
                ? ($this->body_tl ?: $this->body_en ?: [])
                : ($this->body_en ?: $this->body_tl ?: []),
            'is_emergency' => $this->is_emergency,
            'sort_order'   => $this->sort_order,
        ];
    }

    public function toAdminArray(): array
    {
        return [
            'id'             => $this->id,
            'category'       => $this->category,
            'title_en'       => $this->title_en,
            'title_tl'       => $this->title_tl,
            'role_en'        => $this->role_en,
            'role_tl'        => $this->role_tl,
            'description_en' => $this->description_en,
            'description_tl' => $this->description_tl,
            'meta_en'        => $this->meta_en,
            'meta_tl'        => $this->meta_tl,
            'phone'          => $this->phone,
            'web_url'        => $this->web_url,
            'logo_url'       => $this->logo_url,
            'logo_initial'   => $this->logo_initial,
            'icon_key'       => $this->icon_key,
            'body_en'        => $this->body_en,
            'body_tl'        => $this->body_tl,
            'is_emergency'   => $this->is_emergency,
            'sort_order'     => $this->sort_order,
            'is_active'      => $this->is_active,
            'created_at'     => optional($this->created_at)?->toIso8601String(),
            'updated_at'     => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
