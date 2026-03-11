<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->with(['sender', 'post'])
            ->latest('sent_at')
            ->paginate($perPage);
    }

    public function create(array $data): Notification
    {
        return Notification::create($data);
    }
}
