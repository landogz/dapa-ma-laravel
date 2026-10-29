<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UserRepository
{
    private const STAFF_ROLES = [
        'super_admin',
        'publisher',
        'editor',
        'analytics_viewer',
    ];

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{
     *     staff: int,
     *     app: int,
     *     never_logged_in: int,
     *     must_change_password: int,
     *     suspended: int,
     *     roles: array<string, int>
     * }
     */
    public function summary(array $filters = []): array
    {
        // Audience tab counts stay global so staff/app chips stay accurate.
        $global = User::query();
        $audienceScoped = $this->audienceQuery($filters['audience'] ?? null);
        $roleScoped = $this->applyRoleFilter(clone $audienceScoped, $filters['role'] ?? null);

        return [
            'staff' => (clone $global)->whereIn('role', self::STAFF_ROLES)->count(),
            'app' => (clone $global)->where('role', 'app_user')->count(),
            'never_logged_in' => (clone $roleScoped)->whereNull('last_login_at')->count(),
            'must_change_password' => (clone $roleScoped)->where('must_change_password', true)->count(),
            'suspended' => (clone $roleScoped)->where('is_active', false)->count(),
            'roles' => $this->roleCounts($audienceScoped),
        ];
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function countActiveSuperAdmins(): int
    {
        return User::query()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->count();
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = $this->audienceQuery($filters['audience'] ?? null);
        $this->applyRoleFilter($query, $filters['role'] ?? null);
        $this->applyStatusFilter($query, $filters['status'] ?? null);
        $this->applySearchFilter($query, $filters['q'] ?? null);

        return $query;
    }

    private function audienceQuery(?string $audience): Builder
    {
        $query = User::query();

        if ($audience === 'app') {
            return $query->where('role', 'app_user');
        }

        if ($audience === 'staff') {
            return $query->whereIn('role', self::STAFF_ROLES);
        }

        return $query;
    }

    private function applyRoleFilter(Builder $query, ?string $role): Builder
    {
        if ($role !== null && $role !== '') {
            $query->where('role', $role);
        }

        return $query;
    }

    private function applyStatusFilter(Builder $query, ?string $status): Builder
    {
        $idleBefore = Carbon::now()->subDays(30);

        return match ($status) {
            'active' => $query->where('is_active', true),
            'suspended' => $query->where('is_active', false),
            'never' => $query->whereNull('last_login_at'),
            'password_due' => $query->where('must_change_password', true),
            'idle' => $query
                ->where('is_active', true)
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '<', $idleBefore)
                ->where('must_change_password', false),
            default => $query,
        };
    }

    private function applySearchFilter(Builder $query, ?string $search): Builder
    {
        $q = trim((string) $search);

        if ($q === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($q): void {
            $inner->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        });
    }

    /**
     * @return array<string, int>
     */
    private function roleCounts(Builder $base): array
    {
        $counts = [];

        foreach ([...self::STAFF_ROLES, 'app_user'] as $role) {
            $counts[$role] = (clone $base)->where('role', $role)->count();
        }

        return $counts;
    }
}
