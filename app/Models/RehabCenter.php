<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RehabCenter extends Model
{
    protected $fillable = [
        'name',
        'region',
        'province',
        'address',
        'contact',
        'website',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'target_id')
            ->where('target_type', self::class);
    }
}
