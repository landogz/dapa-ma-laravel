<?php

namespace App\Repositories;

use App\Models\LegalPage;
use Illuminate\Support\Collection;

class LegalPageRepository
{
    private const DISPLAY_ORDER = [
        'terms_of_use' => 1,
        'privacy_policy' => 2,
        'privacy_notice' => 3,
    ];

    public function all(): Collection
    {
        return $this->sorted(LegalPage::query()->get());
    }

    public function activeAll(): Collection
    {
        return $this->sorted(
            LegalPage::query()->where('is_active', true)->published()->get(),
        );
    }

    private function sorted(Collection $pages): Collection
    {
        return $pages
            ->sortBy(fn (LegalPage $page) => self::DISPLAY_ORDER[$page->slug] ?? 99)
            ->values();
    }

    public function findBySlug(string $slug): ?LegalPage
    {
        return LegalPage::query()
            ->where('slug', $slug)
            ->first();
    }

    public function findActiveBySlug(string $slug): ?LegalPage
    {
        return LegalPage::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->published()
            ->first();
    }

    public function update(LegalPage $page, array $data): LegalPage
    {
        $page->update($data);

        return $page->fresh();
    }
}
