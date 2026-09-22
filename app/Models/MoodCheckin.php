<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'mood',
        'source',
        'score',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'note'  => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
