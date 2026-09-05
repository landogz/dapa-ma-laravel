<?php

namespace App\Repositories;

use App\Models\IecMaterial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IecMaterialRepository
{
    public function paginate(
        int $perPage = 24,
        ?string $search = null,
        ?string $topic = null,
        ?string $mediaType = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return IecMaterial::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($topic, fn ($q) => $q->where('topic', $topic))
            ->when($mediaType, fn ($q) => $q->where('media_type', $mediaType))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('topic', 'like', "%{$search}%");
            }))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): IecMaterial
    {
        return IecMaterial::findOrFail($id);
    }

    public function create(array $data): IecMaterial
    {
        return IecMaterial::create($data);
    }

    public function update(IecMaterial $material, array $data): IecMaterial
    {
        $material->update($data);

        return $material->fresh();
    }

    public function delete(IecMaterial $material): void
    {
        $material->delete();
    }
}
