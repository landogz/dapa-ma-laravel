<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KidListoQuote extends Model
{
    protected $fillable = [
        'message_en',
        'message_tl',
        'attribution',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function toPublicArray(string $locale = 'en'): array
    {
        $locale = in_array($locale, ['tl', 'fil'], true) ? 'tl' : 'en';
        $isTl = $locale === 'tl';

        return [
            'id' => $this->id,
            'brand' => 'Kid Listo Says',
            'brand_tagline' => $isTl
                ? 'Araw-araw na motivational quote para sa drug-free youth'
                : 'A daily motivational quote for a drug-free youth',
            'message' => $isTl ? $this->message_tl : $this->message_en,
            'attribution' => $this->attribution,
            'locale' => $locale,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
