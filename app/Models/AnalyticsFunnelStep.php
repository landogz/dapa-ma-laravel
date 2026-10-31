<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsFunnelStep extends Model
{
    protected $fillable = [
        'funnel_id',
        'step_order',
        'name',
        'event_type',
        'resource_type',
    ];

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(AnalyticsFunnelDefinition::class, 'funnel_id');
    }
}
