<?php

namespace App\Support\Analytics;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

final class AnalyticsExportDate
{
    public static function dateTime(mixed $value): string
    {
        $carbon = self::parse($value);
        if ($carbon === null) {
            return self::fallback($value);
        }

        return $carbon->timezone(self::timezone())->format('M j, Y g:i A');
    }

    public static function date(mixed $value): string
    {
        $carbon = self::parse($value);
        if ($carbon === null) {
            return self::fallback($value);
        }

        return $carbon->timezone(self::timezone())->format('M j, Y');
    }

    public static function auto(mixed $value): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return self::date($value);
        }

        return self::dateTime($value);
    }

    public static function hour(mixed $value): string
    {
        $hour = max(0, min(23, (int) $value));
        $suffix = $hour >= 12 ? 'pm' : 'am';
        $twelve = $hour % 12 === 0 ? 12 : $hour % 12;

        return $twelve.$suffix;
    }

    public static function cell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return self::dateTime($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $trimmed)) {
            return self::dateTime($trimmed);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return self::date($trimmed);
        }

        return $value;
    }

    private static function parse(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '—') {
            return null;
        }

        try {
            return Carbon::parse($trimmed);
        } catch (Throwable) {
            return null;
        }
    }

    private static function fallback(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value === '—') {
            return '—';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Manila');
    }
}
