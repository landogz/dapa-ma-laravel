<?php

namespace App\Services;

use App\Models\LegalPage;
use App\Repositories\LegalPageRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LegalPageService
{
    public function __construct(
        private readonly LegalPageRepository $legalPageRepository,
    ) {
    }

    public function listForAdmin(): Collection
    {
        return $this->legalPageRepository->all();
    }

    public function findForAdmin(string $slug): LegalPage
    {
        $page = $this->legalPageRepository->findBySlug($slug);

        if (! $page) {
            throw ValidationException::withMessages([
                'slug' => ['Legal page not found.'],
            ]);
        }

        return $page;
    }

    public function update(string $slug, array $data): LegalPage
    {
        $page = $this->findForAdmin($slug);

        return $this->legalPageRepository->update($page, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicIndex(string $locale = 'en'): array
    {
        return $this->legalPageRepository
            ->activeAll()
            ->map(fn (LegalPage $page) => $page->toPublicArray($locale))
            ->values()
            ->all();
    }

    public function publicShow(string $slug, string $locale = 'en'): array
    {
        $page = $this->legalPageRepository->findActiveBySlug($slug);

        if (! $page) {
            throw ValidationException::withMessages([
                'slug' => ['Legal page not found.'],
            ]);
        }

        return $page->toPublicArray($locale);
    }
}
