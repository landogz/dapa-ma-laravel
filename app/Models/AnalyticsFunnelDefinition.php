<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsFunnelDefinition extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'max_window_hours',
        'is_sequential',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_sequential' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AnalyticsFunnelStep::class, 'funnel_id')->orderBy('step_order');
    }
}
