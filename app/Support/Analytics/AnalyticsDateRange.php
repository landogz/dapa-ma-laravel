<?php

namespace App\Support\Analytics;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Resolve analytics reporting windows in the application timezone.
 */
final class AnalyticsDateRange
{
    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly CarbonInterface $previousFrom,
        public readonly CarbonInterface $previousTo,
        public readonly string $key,
        public readonly string $label,
    ) {
    }

    /**
     * @param  array{range?: string, from?: string, to?: string}  $input
     */
    public static function fromRequest(array $input): self
    {
        $tz = config('app.timezone', 'UTC');
        $now = now($tz);
        $range = strtolower(trim((string) ($input['range'] ?? '7')));

        if ($range === 'custom' || (! empty($input['from']) && ! empty($input['to']))) {
            $from = Carbon::parse((string) $input['from'], $tz)->startOfDay();
            $to = Carbon::parse((string) $input['to'], $tz)->endOfDay();
            if ($from->gt($to)) {
                throw new InvalidArgumentException('Start date must be before end date.');
            }
            if ($from->diffInDays($to) > 732) {
                throw new InvalidArgumentException('Custom range cannot exceed 24 months.');
            }
            $seconds = max(1, $from->diffInSeconds($to));
            $previousTo = $from->copy();
            $previousFrom = $previousTo->copy()->subSeconds($seconds);

            return new self($from, $to, $previousFrom, $previousTo, 'custom', 'Custom range');
        }

        return match ($range) {
            'today', '1' => self::preset(
                $now->copy()->startOfDay(),
                $now->copy(),
                'today',
                'Today',
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ),
            'yesterday' => self::preset(
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
                'yesterday',
                'Yesterday',
                $now->copy()->subDays(2)->startOfDay(),
                $now->copy()->subDays(2)->endOfDay(),
            ),
            '7', '7d', 'week', 'last_7_days' => self::rolling($now, 7, '7', 'Last 7 days'),
            '14', 'last_14_days' => self::rolling($now, 14, '14', 'Last 14 days'),
            '30', '30d', 'last_30_days' => self::rolling($now, 30, '30', 'Last 30 days'),
            '60', 'last_60_days' => self::rolling($now, 60, '60', 'Last 60 days'),
            '90', 'last_90_days' => self::rolling($now, 90, '90', 'Last 90 days'),
            'this_week' => self::preset(
                $now->copy()->startOfWeek(),
                $now->copy(),
                'this_week',
                'This week',
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ),
            'last_week' => self::preset(
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
                'last_week',
                'Last week',
                $now->copy()->subWeeks(2)->startOfWeek(),
                $now->copy()->subWeeks(2)->endOfWeek(),
            ),
            'this_month' => self::preset(
                $now->copy()->startOfMonth(),
                $now->copy(),
                'this_month',
                'This month',
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ),
            'last_month' => self::preset(
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'last_month',
                'Last month',
                $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                $now->copy()->subMonthsNoOverflow(2)->endOfMonth(),
            ),
            'this_quarter' => self::preset(
                $now->copy()->startOfQuarter(),
                $now->copy(),
                'this_quarter',
                'This quarter',
                $now->copy()->subQuarter()->startOfQuarter(),
                $now->copy()->subQuarter()->endOfQuarter(),
            ),
            'last_quarter' => self::preset(
                $now->copy()->subQuarter()->startOfQuarter(),
                $now->copy()->subQuarter()->endOfQuarter(),
                'last_quarter',
                'Last quarter',
                $now->copy()->subQuarters(2)->startOfQuarter(),
                $now->copy()->subQuarters(2)->endOfQuarter(),
            ),
            'this_year' => self::preset(
                $now->copy()->startOfYear(),
                $now->copy(),
                'this_year',
                'This year',
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ),
            'last_year' => self::preset(
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
                'last_year',
                'Last year',
                $now->copy()->subYears(2)->startOfYear(),
                $now->copy()->subYears(2)->endOfYear(),
            ),
            'all', 'all_time' => self::preset(
                Carbon::create(2020, 1, 1, 0, 0, 0, $tz),
                $now->copy(),
                'all_time',
                'All time',
                Carbon::create(2019, 1, 1, 0, 0, 0, $tz),
                Carbon::create(2019, 12, 31, 23, 59, 59, $tz),
            ),
            default => self::rolling($now, 7, '7', 'Last 7 days'),
        };
    }

    private static function rolling(CarbonInterface $now, int $days, string $key, string $label): self
    {
        $to = $now->copy();
        $from = $now->copy()->subDays($days - 1)->startOfDay();
        $previousTo = $from->copy();
        $previousFrom = $previousTo->copy()->subDays($days);

        return new self($from, $to, $previousFrom, $previousTo, $key, $label);
    }

    private static function preset(
        CarbonInterface $from,
        CarbonInterface $to,
        string $key,
        string $label,
        CarbonInterface $previousFrom,
        CarbonInterface $previousTo,
    ): self {
        return new self($from, $to, $previousFrom, $previousTo, $key, $label);
    }

    public function daySpan(): int
    {
        return max(1, (int) $this->from->diffInDays($this->to) + 1);
    }

    /**
     * @return array{key: string, label: string, from: string, to: string, previous_from: string, previous_to: string, days: int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'previous_from' => $this->previousFrom->toIso8601String(),
            'previous_to' => $this->previousTo->toIso8601String(),
            'days' => $this->daySpan(),
        ];
    }
}
