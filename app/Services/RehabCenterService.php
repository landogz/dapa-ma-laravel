<?php

namespace App\Services;

use App\Models\RehabCenter;
use App\Repositories\RehabCenterRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RehabCenterService
{
    public function __construct(
        private readonly RehabCenterRepository $rehabCenterRepository,
        private readonly UserNotificationService $userNotificationService,
    ) {
    }

    public function list(int $perPage = 20, ?string $region = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->rehabCenterRepository->paginate($perPage, $region, $search);
    }

    public function find(int $id): RehabCenter
    {
        return $this->rehabCenterRepository->findOrFail($id);
    }

    public function create(array $data): RehabCenter
    {
        $center = $this->rehabCenterRepository->create($data);
        $this->userNotificationService->notifyNewRehabCenter($center);

        return $center;
    }

    public function update(RehabCenter $center, array $data): RehabCenter
    {
        return $this->rehabCenterRepository->update($center, $data);
    }

    public function delete(RehabCenter $center): void
    {
        $this->rehabCenterRepository->delete($center);
    }

    public function archive(RehabCenter $center): RehabCenter
    {
        return $this->rehabCenterRepository->update($center, ['is_active' => false]);
    }

    public function restore(RehabCenter $center): RehabCenter
    {
        return $this->rehabCenterRepository->update($center, ['is_active' => true]);
    }
}
