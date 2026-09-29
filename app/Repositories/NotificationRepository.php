<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository
{
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = Notification::query()
            ->with(['sender:id,name,email', 'post:id,title'])
            ->latest('sent_at')
            ->latest('id');

        $topic = isset($filters['topic']) ? trim((string) $filters['topic']) : '';
        if ($topic !== '') {
            $query->where('topic', $topic);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('body', 'like', $like);
            });
        }

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): Notification
    {
        return Notification::query()
            ->with(['sender:id,name,email', 'post:id,title'])
            ->findOrFail($id);
    }

    public function create(array $data): Notification
    {
        return Notification::create($data);
    }

    public function update(Notification $notification, array $data): Notification
    {
        $notification->fill($data);
        $notification->save();

        return $notification->fresh(['sender', 'post']);
    }

    public function delete(Notification $notification): void
    {
        $notification->delete();
    }
}
