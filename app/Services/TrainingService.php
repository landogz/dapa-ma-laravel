<?php

namespace App\Services;

use App\Models\Training;
use App\Repositories\TrainingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TrainingService
{
    public function __construct(
        private readonly TrainingRepository $trainingRepository,
    ) {
    }

    public function list(
        int $perPage = 20,
        ?string $region = null,
        ?string $search = null,
        ?string $category = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return $this->trainingRepository->paginate(
            $perPage,
            $region,
            $search,
            $category,
            $activeOnly,
        );
    }

    public function find(int $id): Training
    {
        return $this->trainingRepository->findOrFail($id);
    }

    public function create(array $data): Training
    {
        return $this->trainingRepository->create($data);
    }

    public function update(Training $training, array $data): Training
    {
        return $this->trainingRepository->update($training, $data);
    }

    public function delete(Training $training): void
    {
        $this->trainingRepository->delete($training);
    }
}
