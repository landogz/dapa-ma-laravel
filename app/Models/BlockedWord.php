<?php

namespace App\Models;

use App\Models\Concerns\HasContentWorkflow;
use Illuminate\Database\Eloquent\Model;

class BlockedWord extends Model
{
    use HasContentWorkflow;

    protected $fillable = [
        'word',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'word' => $this->word,
            'is_active' => (bool) $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
