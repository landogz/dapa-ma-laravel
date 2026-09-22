<?php

namespace App\Support;

class InterestValues
{
    public const PRESET = [
        'knowledge',
        'community',
        'wellness',
        'stories',
        'support',
        'events',
        'opportunities',
        'advocacy',
    ];

    public static function isValid(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (in_array($value, self::PRESET, true)) {
            return true;
        }

        // Custom "Other" interest: other:User typed label
        if (preg_match('/^other:\S.*$/u', $value) === 1) {
            return mb_strlen($value) <= 80;
        }

        return false;
    }

    public static function normalize(array $interests): array
    {
        $normalized = [];

        foreach ($interests as $interest) {
            $value = trim((string) $interest);
            if ($value === '' || ! self::isValid($value)) {
                continue;
            }

            if (str_starts_with($value, 'other:')) {
                $label = trim(mb_substr($value, 6));
                if ($label === '') {
                    continue;
                }
                $value = 'other:'.$label;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}
