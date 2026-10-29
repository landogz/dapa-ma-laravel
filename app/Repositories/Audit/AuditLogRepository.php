<?php

namespace App\Repositories\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuditLogRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AuditLog>
     */
    public function export(array $filters = [], int $limit = 5000): Collection
    {
        return $this->filteredQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     today: int,
     *     this_week: int,
     *     unique_actors: int,
     *     mutations: int,
     *     categories: array<string, int>,
     *     actions: array<string, int>
     * }
     */
    public function summary(array $filters = []): array
    {
        $base = $this->filteredQuery($filters, ignoreTime: true);
        $scoped = $this->filteredQuery($filters);

        $todayStart = Carbon::now()->startOfDay();
        $weekStart = Carbon::now()->startOfWeek();

        $categories = (clone $scoped)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($total) => (int) $total)
            ->all();

        $actions = (clone $scoped)
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn ($total) => (int) $total)
            ->all();

        return [
            'today' => (clone $base)
                ->where('created_at', '>=', $todayStart)
                ->count(),
            'this_week' => (clone $base)
                ->where('created_at', '>=', $weekStart)
                ->count(),
            'unique_actors' => (int) (clone $scoped)
                ->whereNotNull('actor_id')
                ->selectRaw('COUNT(DISTINCT actor_id) as aggregate')
                ->value('aggregate'),
            'mutations' => (clone $scoped)->count(),
            'categories' => $categories,
            'actions' => $actions,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters, bool $ignoreTime = false): Builder
    {
        $query = AuditLog::query();

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '' && $action !== 'all') {
            if ($action === 'security') {
                $query->whereIn('action', [
                    'login',
                    'login_failed',
                    'logout',
                    'password_changed',
                    'force_password',
                    'suspended',
                    'activated',
                    'role_changed',
                ]);
            } elseif ($action === 'create') {
                $query->where('action', 'created');
            } elseif ($action === 'update') {
                $query->whereIn('action', ['updated', 'published', 'scheduled', 'submitted', 'archived', 'rejected', 'sent']);
            } elseif ($action === 'delete') {
                $query->where('action', 'deleted');
            } else {
                $query->where('action', $action);
            }
        }

        $actorId = $filters['actor_id'] ?? null;
        if ($actorId !== null && $actorId !== '') {
            $query->where('actor_id', (int) $actorId);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function (Builder $inner) use ($q): void {
                $inner->where('description', 'like', "%{$q}%")
                    ->orWhere('actor_name', 'like', "%{$q}%")
                    ->orWhere('actor_email', 'like', "%{$q}%")
                    ->orWhere('subject_label', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('action', 'like', "%{$q}%");
            });
        }

        if (! $ignoreTime) {
            $from = $filters['from'] ?? null;
            if (is_string($from) && $from !== '') {
                $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
            }

            $to = $filters['to'] ?? null;
            if (is_string($to) && $to !== '') {
                $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
            }
        }

        return $query;
    }
}
