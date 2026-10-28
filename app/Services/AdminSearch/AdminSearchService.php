<?php

namespace App\Services\AdminSearch;

use App\Repositories\AdminSearch\AdminSearchRepository;

class AdminSearchService
{
    private const ROLE_SECTIONS = [
        'super_admin' => [
            'posts', 'rehab-centers', 'trainings', 'contests', 'iec-materials',
            'app-translations', 'notifications', 'analytics', 'users', 'diary',
            'care-toolkit', 'care-checks', 'care-support', 'hope-directory',
            'hope-events', 'settings', 'lookups', 'word-blocking',
        ],
        'editor' => ['posts'],
        'publisher' => [
            'posts', 'rehab-centers', 'trainings', 'contests', 'iec-materials',
            'app-translations', 'notifications', 'care-toolkit', 'care-checks',
            'care-support', 'hope-directory', 'hope-events', 'settings',
            'lookups', 'word-blocking',
        ],
        'analytics_viewer' => ['analytics'],
    ];

    public function __construct(
        private readonly AdminSearchRepository $adminSearchRepository,
    ) {
    }

    public function search(string $query, ?string $role): array
    {
        $sections = self::ROLE_SECTIONS[$role] ?? [];
        $results = [];

        $map = [
            'posts'            => fn () => $this->adminSearchRepository->posts($query),
            'users'            => fn () => $this->adminSearchRepository->users($query),
            'contests'         => fn () => $this->adminSearchRepository->contests($query),
            'rehab-centers'    => fn () => $this->adminSearchRepository->rehabCenters($query),
            'trainings'        => fn () => $this->adminSearchRepository->trainings($query),
            'iec-materials'    => fn () => $this->adminSearchRepository->iecMaterials($query),
            'app-translations' => fn () => $this->adminSearchRepository->translations($query),
            'lookups'          => fn () => $this->adminSearchRepository->lookups($query),
            'hope-directory'   => fn () => $this->adminSearchRepository->hopeDirectory($query),
            'hope-events'      => fn () => $this->adminSearchRepository->hopeEvents($query),
            'care-support'     => fn () => $this->adminSearchRepository->careSupport($query),
            'word-blocking'    => fn () => $this->adminSearchRepository->blockedWords($query),
            'notifications'    => fn () => $this->adminSearchRepository->notifications($query),
            'settings'         => fn () => $this->adminSearchRepository->quotes($query),
        ];

        foreach ($map as $section => $resolver) {
            if (! in_array($section, $sections, true)) {
                continue;
            }

            $chunk = $resolver();

            if ($chunk->isNotEmpty()) {
                $results = array_merge($results, $chunk->all());
            }
        }

        return $results;
    }
}
