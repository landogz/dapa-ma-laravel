<?php

namespace App\Repositories\CareCheck;

use App\Models\CareCheckResult;
use App\Models\User;
use App\Support\Analytics\AnalyticsDateRange;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CareCheckRepository
{
    public function paginateForUser(User $user, int $perPage, ?string $checkType = null): LengthAwarePaginator
    {
        return CareCheckResult::query()
            ->where('user_id', $user->id)
            ->when($checkType, fn ($q) => $q->where('check_type', $checkType))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function latestForUser(User $user, string $checkType): ?CareCheckResult
    {
        return CareCheckResult::query()
            ->where('user_id', $user->id)
            ->where('check_type', $checkType)
            ->orderByDesc('created_at')
            ->first();
    }

    public function create(User $user, array $data): CareCheckResult
    {
        return CareCheckResult::query()->create([
            'user_id' => $user->id,
            ...$data,
        ]);
    }

    public function paginateAdmin(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->adminQuery($filters)
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CareCheckResult>
     */
    public function listForExport(array $filters, int $limit = 10000): Collection
    {
        return $this->adminQuery($filters)
            ->limit(max(1, min(20000, $limit)))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function adminQuery(array $filters)
    {
        return CareCheckResult::query()
            ->with('user:id,name,email')
            ->when($filters['check_type'] ?? null, fn ($q, $type) => $q->where('check_type', $type))
            ->when($filters['display_tier'] ?? null, fn ($q, $tier) => $q->where('display_tier', $tier))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }

    public function findAdmin(int $id): CareCheckResult
    {
        return CareCheckResult::query()->with('user:id,name,email')->findOrFail($id);
    }

    public function summary(AnalyticsDateRange $range): array
    {
        $columns = ['check_type', 'display_tier', 'band', 'percent', 'user_id', 'created_at'];

        $rows = CareCheckResult::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->get($columns);

        $previousRows = CareCheckResult::query()
            ->whereBetween('created_at', [$range->previousFrom, $range->previousTo])
            ->get(['display_tier', 'user_id']);

        $current = $this->summarizeWindow($rows, $range);

        return [
            ...$current,
            'updated_at' => now()->toIso8601String(),
            'range' => $range->toArray(),
            'period_label' => 'previous period',
            'previous' => [
                'total' => $previousRows->count(),
                'unique_users' => $previousRows->pluck('user_id')->unique()->count(),
                'by_display_tier' => [
                    'low' => $previousRows->where('display_tier', 'low')->count(),
                    'high' => $previousRows->where('display_tier', 'high')->count(),
                ],
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CareCheckResult>  $rows
     * @return array<string, mixed>
     */
    private function summarizeWindow($rows, AnalyticsDateRange $range): array
    {
        $byType = [];
        foreach (CareCheckResult::TYPES as $type) {
            $subset = $rows->where('check_type', $type);
            $byType[$type] = [
                'count' => $subset->count(),
                'unique_users' => $subset->pluck('user_id')->unique()->count(),
                'avg_percent' => $subset->avg('percent') !== null
                    ? round((float) $subset->avg('percent'), 1)
                    : 0,
            ];
        }

        $chartFrom = $this->chartWindowStart($range);
        $chartTo = $range->to->copy()->endOfDay();
        $dailyMap = [];
        $cursor = $chartFrom->copy()->startOfDay();
        while ($cursor->lte($chartTo)) {
            $day = $cursor->toDateString();
            $dailyMap[$day] = [
                'date' => $day,
                'total' => 0,
                'stress' => 0,
                'anxiety' => 0,
                'sleep' => 0,
            ];
            $cursor->addDay();
        }

        foreach ($rows as $row) {
            $day = optional($row->created_at)?->toDateString();
            if ($day && isset($dailyMap[$day])) {
                $dailyMap[$day]['total']++;
                if (isset($dailyMap[$day][$row->check_type])) {
                    $dailyMap[$day][$row->check_type]++;
                }
            }
        }

        $tierCounts = $rows->groupBy('display_tier')->map->count();
        $bandCounts = $rows->groupBy(fn ($r) => $r->check_type.':'.$r->band)->map->count();

        return [
            'days' => $range->daySpan(),
            'total' => $rows->count(),
            'unique_users' => $rows->pluck('user_id')->unique()->count(),
            'avg_percent' => $rows->avg('percent') !== null
                ? round((float) $rows->avg('percent'), 1)
                : 0,
            'by_type' => $byType,
            'by_display_tier' => [
                'low' => (int) ($tierCounts['low'] ?? 0),
                'high' => (int) ($tierCounts['high'] ?? 0),
            ],
            'by_band' => $bandCounts,
            'daily' => array_values($dailyMap),
            'daily_truncated' => $range->daySpan() > 90,
        ];
    }

    private function chartWindowStart(AnalyticsDateRange $range): CarbonInterface
    {
        if ($range->daySpan() <= 90) {
            return $range->from->copy()->startOfDay();
        }

        $start = $range->to->copy()->subDays(89)->startOfDay();

        return $start->lt($range->from) ? $range->from->copy()->startOfDay() : $start;
    }

    public function typeCounts(): array
    {
        $counts = CareCheckResult::query()
            ->select('check_type', DB::raw('COUNT(*) as total'))
            ->groupBy('check_type')
            ->pluck('total', 'check_type')
            ->all();

        return [
            '' => CareCheckResult::query()->count(),
            'stress' => (int) ($counts['stress'] ?? 0),
            'anxiety' => (int) ($counts['anxiety'] ?? 0),
            'sleep' => (int) ($counts['sleep'] ?? 0),
        ];
    }

    public function tierCounts(): array
    {
        $counts = CareCheckResult::query()
            ->select('display_tier', DB::raw('COUNT(*) as total'))
            ->groupBy('display_tier')
            ->pluck('total', 'display_tier')
            ->all();

        return [
            '' => CareCheckResult::query()->count(),
            'low' => (int) ($counts['low'] ?? 0),
            'high' => (int) ($counts['high'] ?? 0),
        ];
    }
}
