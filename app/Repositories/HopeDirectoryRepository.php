<?php

namespace App\Repositories;

use App\Models\HopeDirectoryOrganization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HopeDirectoryRepository
{
    public function paginate(
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        $query = HopeDirectoryOrganization::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        return $query->paginate($perPage);
    }

    public function create(array $data): HopeDirectoryOrganization
    {
        return HopeDirectoryOrganization::query()->create($data);
    }

    public function update(HopeDirectoryOrganization $org, array $data): HopeDirectoryOrganization
    {
        $org->fill($data);
        $org->save();

        return $org->fresh();
    }

    public function delete(HopeDirectoryOrganization $org): void
    {
        $org->delete();
    }
}
