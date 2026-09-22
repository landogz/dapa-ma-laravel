<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HopeDirectoryOrganization extends Model
{
    public const CATEGORIES = ['government', 'ngo', 'school'];

    protected $fillable = [
        'name',
        'description',
        'category',
        'address',
        'phone',
        'email',
        'logo_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function logoUrl(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function toPublicArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'category'    => $this->category,
            'address'     => $this->address,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'logo_url'    => $this->logoUrl(),
            'sort_order'  => $this->sort_order,
        ];
    }

    public function toAdminArray(): array
    {
        return [
            ...$this->toPublicArray(),
            'logo_path'  => $this->logo_path,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
