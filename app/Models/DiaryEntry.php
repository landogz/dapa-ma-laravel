<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryEntry extends Model
{
    protected $fillable = [
        'user_id',
        'entry_date',
        'title',
        'body_html',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'body_html'  => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
