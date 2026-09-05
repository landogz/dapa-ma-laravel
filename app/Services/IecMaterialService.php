<?php

namespace App\Services;

use App\Models\IecMaterial;
use App\Repositories\IecMaterialRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IecMaterialService
{
    public function __construct(
        private readonly IecMaterialRepository $iecMaterialRepository,
    ) {
    }

    public function list(
        int $perPage = 24,
        ?string $search = null,
        ?string $topic = null,
        ?string $mediaType = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return $this->iecMaterialRepository->paginate(
            $perPage,
            $search,
            $topic,
            $mediaType,
            $activeOnly,
        );
    }

    public function find(int $id): IecMaterial
    {
        return $this->iecMaterialRepository->findOrFail($id);
    }

    public function create(array $data): IecMaterial
    {
        return $this->iecMaterialRepository->create($data);
    }

    public function update(IecMaterial $material, array $data): IecMaterial
    {
        return $this->iecMaterialRepository->update($material, $data);
    }

    public function delete(IecMaterial $material): void
    {
        $this->iecMaterialRepository->delete($material);
    }
}
