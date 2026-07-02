<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVerse extends Model
{
    protected $fillable = [
        'day_of_year',
        'reference',
        'verse_text',
        'translation',
        'book',
        'chapter',
        'verse_start',
        'verse_end',
    ];

    protected function casts(): array
    {
        return [
            'day_of_year' => 'integer',
            'chapter'     => 'integer',
            'verse_start' => 'integer',
            'verse_end'   => 'integer',
        ];
    }
}
