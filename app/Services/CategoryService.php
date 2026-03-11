<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function listForSelection(): Collection
    {
        return $this->categoryRepository->allForSelection();
    }
}
