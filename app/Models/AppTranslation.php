<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTranslation extends Model
{
    protected $fillable = [
        'key',
        'group',
        'description',
        'icon',
        'image_path',
        'value_en',
        'value_tl',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
