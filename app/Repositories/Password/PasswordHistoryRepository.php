<?php

namespace App\Repositories\Password;

use App\Models\Password\PasswordHistory;
use App\Models\User;
use App\Support\Password\PasswordPolicy;
use Illuminate\Support\Collection;

class PasswordHistoryRepository
{
    /**
     * @return Collection<int, string>
     */
    public function recentHashes(User $user, ?int $limit = null): Collection
    {
        $take = $limit ?? PasswordPolicy::historyCount();

        if ($take < 1) {
            return collect();
        }

        return PasswordHistory::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->limit($take)
            ->pluck('password');
    }

    public function record(User $user, string $hashedPassword): PasswordHistory
    {
        return PasswordHistory::query()->create([
            'user_id' => $user->getKey(),
            'password' => $hashedPassword,
            'created_at' => now(),
        ]);
    }

    public function prune(User $user): void
    {
        $keep = PasswordPolicy::historyCount();

        $query = PasswordHistory::query()->where('user_id', $user->getKey());

        if ($keep < 1) {
            $query->delete();

            return;
        }

        $keepIds = (clone $query)
            ->latest('id')
            ->limit($keep)
            ->pluck('id');

        $query->whereNotIn('id', $keepIds)->delete();
    }
}
