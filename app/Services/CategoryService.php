<?php

namespace App\Services;

use App\Models\AppOption;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Support\AppOptionGroup;
use App\Support\ContentWorkflow\ContentWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Categories available for the Posts picker.
     * Synced from Lookups → Post categories (active + published).
     *
     * @return Collection<int, Category>
     */
    public function listForSelection(?int $includeCategoryId = null): Collection
    {
        $this->syncActiveLookupsToCategories();

        $activeKeys = $this->activeLookupKeys();

        if ($activeKeys === []) {
            return $this->categoryRepository->allForSelection();
        }

        return Category::query()
            ->select(['id', 'name', 'slug'])
            ->where(function ($query) use ($activeKeys, $includeCategoryId): void {
                $query->whereIn('slug', $activeKeys);
                if ($includeCategoryId) {
                    $query->orWhere('id', $includeCategoryId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function syncFromLookup(AppOption $option): ?Category
    {
        if ($option->group !== AppOptionGroup::POST_CATEGORY) {
            return null;
        }

        $key = trim((string) $option->key);
        if ($key === '') {
            return null;
        }

        return Category::query()->updateOrCreate(
            ['slug' => $key],
            ['name' => trim((string) $option->label_en) ?: $key],
        );
    }

    public function syncActiveLookupsToCategories(): void
    {
        if (! Schema::hasTable('app_options') || ! Schema::hasTable('categories')) {
            return;
        }

        foreach ($this->activeLookupOptions() as $option) {
            $this->syncFromLookup($option);
        }
    }

    /**
     * @return list<string>
     */
    private function activeLookupKeys(): array
    {
        return $this->activeLookupOptions()
            ->pluck('key')
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, AppOption>
     */
    private function activeLookupOptions(): Collection
    {
        if (! Schema::hasTable('app_options')) {
            return collect();
        }

        try {
            $query = AppOption::query()
                ->where('group', AppOptionGroup::POST_CATEGORY)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id');

            if (Schema::hasColumn('app_options', 'workflow_status')) {
                $query->where('workflow_status', ContentWorkflowStatus::PUBLISHED);
            }

            return $query->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
