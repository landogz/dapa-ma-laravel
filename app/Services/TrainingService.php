<?php

namespace App\Services;

use App\Models\Training;
use App\Repositories\TrainingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TrainingService
{
    public function __construct(
        private readonly TrainingRepository $trainingRepository,
        private readonly UserNotificationService $userNotificationService,
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
        $training = $this->trainingRepository->create($data);
        $this->userNotificationService->notifyNewTraining($training);

        return $training;
    }

    public function update(Training $training, array $data): Training
    {
        return $this->trainingRepository->update($training, $data);
    }

    public function delete(Training $training): void
    {
        $this->trainingRepository->delete($training);
    }

    public function archive(Training $training): Training
    {
        return $this->trainingRepository->update($training, ['is_active' => false]);
    }

    public function restore(Training $training): Training
    {
        return $this->trainingRepository->update($training, ['is_active' => true]);
    }
}
