<?php

namespace App\Repositories\CareCheck;

use App\Models\CareCheckResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return CareCheckResult::query()
            ->with('user:id,name,email')
            ->when($filters['check_type'] ?? null, fn ($q, $type) => $q->where('check_type', $type))
            ->when($filters['display_tier'] ?? null, fn ($q, $tier) => $q->where('display_tier', $tier))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findAdmin(int $id): CareCheckResult
    {
        return CareCheckResult::query()->with('user:id,name,email')->findOrFail($id);
    }

    public function summary(int $days): array
    {
        $days = max(1, $days);
        $now = Carbon::now();
        $from = $now->copy()->subDays($days - 1)->startOfDay();
        $previousFrom = $from->copy()->subDays($days);
        $previousTo = $from->copy()->subSecond();

        $columns = ['check_type', 'display_tier', 'band', 'percent', 'user_id', 'created_at'];

        $rows = CareCheckResult::query()
            ->where('created_at', '>=', $from)
            ->get($columns);

        $previousRows = CareCheckResult::query()
            ->whereBetween('created_at', [$previousFrom, $previousTo])
            ->get(['display_tier', 'user_id']);

        $current = $this->summarizeWindow($rows, $days, $from);

        return [
            ...$current,
            'updated_at' => $now->toIso8601String(),
            'period_label' => 'previous '.$days.' days',
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
    private function summarizeWindow($rows, int $days, Carbon $from): array
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

        $dailyMap = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $dailyMap[$day] = [
                'date' => $day,
                'total' => 0,
                'stress' => 0,
                'anxiety' => 0,
                'sleep' => 0,
            ];
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
            'days' => $days,
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
        ];
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
