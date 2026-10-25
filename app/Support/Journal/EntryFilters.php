<?php

namespace App\Support\Journal;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EntryFilters
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $from = self::date($filters['from'] ?? null);
        $to = self::date($filters['to'] ?? null);
        $period = trim((string) ($filters['period'] ?? 'all'));

        if ($from !== null || $to !== null) {
            if ($from !== null) {
                $query->whereDate('entry_date', '>=', $from->toDateString());
            }
            if ($to !== null) {
                $query->whereDate('entry_date', '<=', $to->toDateString());
            }
        } elseif ($period === 'today') {
            $query->whereDate('entry_date', Carbon::today()->toDateString());
        } elseif ($period === 'week') {
            $query->whereBetween('entry_date', [
                Carbon::now()->startOfWeek()->toDateString(),
                Carbon::now()->endOfWeek()->toDateString(),
            ]);
        } elseif ($period === 'month') {
            $query->whereBetween('entry_date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ]);
        }

        $sky = trim((string) ($filters['sky'] ?? ''));
        if ($sky !== '') {
            $query->where('sky', $sky);
        }

        $feeling = trim((string) ($filters['feeling'] ?? ''));
        if ($feeling !== '') {
            $query->where(function (Builder $builder) use ($feeling): void {
                $builder
                    ->whereJsonContains('feelings', $feeling)
                    ->orWhere('feelings', 'like', '%'.$feeling.'%');
            });
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('body_html', 'like', $like)
                    ->orWhere('gratitude', 'like', $like)
                    ->orWhere('sky', 'like', $like)
                    ->orWhere('impact', 'like', $like)
                    ->orWhere('feelings', 'like', $like);
            });
        }

        return $query;
    }

    private static function date(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
