<?php

namespace App\Services;

use App\Models\AppOption;
use App\Models\RehabCenter;
use App\Repositories\RehabCenterRepository;
use App\Support\AppOptionGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RehabCenterService
{
    public function __construct(
        private readonly RehabCenterRepository $rehabCenterRepository,
        private readonly UserNotificationService $userNotificationService,
    ) {
    }

    public function list(
        int $perPage = 20,
        ?string $region = null,
        ?string $search = null,
        bool $activeOnly = true,
        ?string $category = null,
    ): LengthAwarePaginator {
        return $this->rehabCenterRepository
            ->paginate($perPage, $region, $search, $activeOnly, $category)
            ->through(fn (RehabCenter $center) => $this->format($center));
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

    /**
     * @return array<string, mixed>
     */
    public function format(RehabCenter $center): array
    {
        $option = $this->categoryOptions()->get((string) ($center->category ?? ''));

        return [
            ...$center->toArray(),
            'category_label' => $option?->label_en,
            'category_label_tl' => $option?->label_tl,
            'category_color' => $option?->accent_color,
            'category_icon' => $option?->icon,
        ];
    }

    /** @var Collection<string, AppOption>|null */
    private ?Collection $categoryOptionsCache = null;

    /**
     * @return Collection<string, AppOption>
     */
    private function categoryOptions(): Collection
    {
        if ($this->categoryOptionsCache instanceof Collection) {
            return $this->categoryOptionsCache;
        }

        $this->categoryOptionsCache = AppOption::query()
            ->where('group', AppOptionGroup::REHAB_CENTER)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');

        return $this->categoryOptionsCache;
    }
}
