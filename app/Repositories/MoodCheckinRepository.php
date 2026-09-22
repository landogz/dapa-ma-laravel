<?php

namespace App\Repositories;

use App\Models\MoodCheckin;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class MoodCheckinRepository
{
    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return MoodCheckin::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function latestTodayForUser(User $user): ?MoodCheckin
    {
        return MoodCheckin::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function create(User $user, array $data): MoodCheckin
    {
        return MoodCheckin::query()->create([
            'user_id' => $user->id,
            'mood'    => $data['mood'],
            'source'  => $data['source'] ?? 'care_hub',
            'score'   => $data['score'] ?? null,
            'note'    => $data['note'] ?? null,
        ]);
    }
}
