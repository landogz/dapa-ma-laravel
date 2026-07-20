<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserNotificationRepository
{
    public function paginateForUser(User $user, int $perPage = 20, bool $unreadOnly = false): LengthAwarePaginator
    {
        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage);
    }

    public function countUnreadForUser(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function createForUser(User $user, array $data): UserNotification
    {
        return UserNotification::create(array_merge(
            ['user_id' => $user->id],
            $data,
        ));
    }

    /**
     * @param  Collection<int, User>|array<int, User>  $users
     */
    public function createForUsers(iterable $users, array $data): int
    {
        $now = now();
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                'user_id' => $user->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'data' => isset($data['data']) ? json_encode($data['data']) : null,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            UserNotification::query()->insert($chunk);
        }

        return count($rows);
    }

    public function markAsRead(UserNotification $notification): void
    {
        if ($notification->read_at) {
            return;
        }

        $notification->forceFill(['read_at' => now()])->save();
    }

    public function markAllAsRead(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
