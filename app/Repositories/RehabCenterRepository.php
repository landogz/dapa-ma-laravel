<?php

namespace App\Repositories;

use App\Models\RehabCenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RehabCenterRepository
{
    public function paginate(int $perPage = 20, ?string $region = null, ?string $search = null): LengthAwarePaginator
    {
        return RehabCenter::query()
            ->where('is_active', true)
            ->when($region, fn ($q) => $q->where('region', $region))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(int $id): RehabCenter
    {
        return RehabCenter::findOrFail($id);
    }

    public function create(array $data): RehabCenter
    {
        return RehabCenter::create($data);
    }

    public function update(RehabCenter $center, array $data): RehabCenter
    {
        $center->update($data);

        return $center->fresh();
    }

    public function delete(RehabCenter $center): void
    {
        $center->delete();
    }
}
