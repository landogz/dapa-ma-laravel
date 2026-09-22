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
        'sky',
        'feelings',
        'impact',
        'gratitude',
        'body_html',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'feelings'   => 'array',
            'body_html'  => 'encrypted',
            'gratitude'  => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
