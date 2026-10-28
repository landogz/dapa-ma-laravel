<?php

namespace App\Support\Phone;

class PhilippinePhoneNumber
{
    public static function isValid(string $value, bool $allowShortCodes = false): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if ($allowShortCodes && self::isShortCode($trimmed)) {
            return true;
        }

        $digits = self::digits($trimmed);

        return self::isMobile($digits) || self::isLandline($digits);
    }

    public static function isListValid(string $value, bool $allowShortCodes = false): bool
    {
        $parts = preg_split('/\s*\|\s*/', $value) ?: [];
        if ($parts === []) {
            return false;
        }

        foreach ($parts as $part) {
            if (trim((string) $part) === '' || ! self::isValid((string) $part, $allowShortCodes)) {
                return false;
            }
        }

        return true;
    }

    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function isShortCode(string $value): bool
    {
        $trimmed = trim($value);

        if (preg_match('/^#\d{3,6}$/', $trimmed) === 1) {
            return true;
        }

        $digits = self::digits($trimmed);

        return preg_match('/^\d{3,4}$/', $digits) === 1;
    }

    private static function isMobile(string $digits): bool
    {
        return preg_match('/^(?:0?9\d{9}|639\d{9})$/', $digits) === 1;
    }

    private static function isLandline(string $digits): bool
    {
        return preg_match('/^(?:632\d{8}|63[3-8]\d{7,8}|02\d{8}|0[3-8]\d{7,8}|\d{7,8})$/', $digits) === 1;
    }
}
