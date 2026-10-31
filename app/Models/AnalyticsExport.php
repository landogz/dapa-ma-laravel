<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsExport extends Model
{
    protected $fillable = [
        'requested_by',
        'report_type',
        'format',
        'status',
        'filters',
        'columns',
        'disk_path',
        'download_name',
        'error_message',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'ready_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
