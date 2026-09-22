<?php

namespace App\Repositories;

use App\Models\LegalPage;
use Illuminate\Support\Collection;

class LegalPageRepository
{
    public function all(): Collection
    {
        return LegalPage::query()
            ->orderBy('slug')
            ->get();
    }

    public function activeAll(): Collection
    {
        return LegalPage::query()
            ->where('is_active', true)
            ->orderBy('slug')
            ->get();
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
            ->first();
    }

    public function update(LegalPage $page, array $data): LegalPage
    {
        $page->update($data);

        return $page->fresh();
    }
}
