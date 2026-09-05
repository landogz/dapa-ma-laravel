<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'region',
        'venue',
        'start_date',
        'end_date',
        'schedule_notes',
        'organizer',
        'contact',
        'registration_url',
        'slots',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'slots' => 'integer',
    ];
}
