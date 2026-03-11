<?php

namespace App\Services;

use App\Repositories\SearchRepository;

class SearchService
{
    public function __construct(
        private readonly SearchRepository $searchRepository,
    ) {
    }

    public function search(string $query, ?string $category = null): array
    {
        return $this->searchRepository->search($query, $category);
    }

    public function suggest(string $query)
    {
        return $this->searchRepository->suggest($query);
    }
}

