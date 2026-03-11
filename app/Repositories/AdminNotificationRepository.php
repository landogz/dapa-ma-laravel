<?php

namespace App\Repositories;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminNotificationRepository
{
    public function paginateForUser(User $user, int $perPage = 10, bool $unreadOnly = false): LengthAwarePaginator
    {
        $query = AdminNotification::query()
            ->where('user_id', $user->id)
            ->latest();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage);
    }

    public function countUnreadForUser(User $user): int
    {
        return AdminNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function createForUser(User $user, array $data): AdminNotification
    {
        return AdminNotification::create(array_merge(
            ['user_id' => $user->id],
            $data,
        ));
    }

    public function markAsRead(AdminNotification $notification): void
    {
        if ($notification->read_at) {
            return;
        }

        $notification->forceFill(['read_at' => now()])->save();
    }

    public function markAllAsRead(User $user): int
    {
        return AdminNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}

