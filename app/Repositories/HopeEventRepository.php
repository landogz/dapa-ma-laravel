<?php

namespace App\Repositories;

use App\Models\HopeEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HopeEventRepository
{
    public function paginate(
        int $perPage = 50,
        ?string $search = null,
        ?string $audience = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        $query = HopeEvent::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($audience, fn ($q) => $q->where('audience', $audience))
            ->when($search, function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('venue', 'like', $like)
                        ->orWhere('online_label', 'like', $like)
                        ->orWhere('about_text', 'like', $like);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('start_date');

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): HopeEvent
    {
        return HopeEvent::query()->findOrFail($id);
    }

    public function create(array $data): HopeEvent
    {
        return HopeEvent::query()->create($data);
    }

    public function update(HopeEvent $event, array $data): HopeEvent
    {
        $event->fill($data);
        $event->save();

        return $event->fresh();
    }

    public function delete(HopeEvent $event): void
    {
        $event->delete();
    }
}
