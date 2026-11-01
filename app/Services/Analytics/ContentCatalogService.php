<?php

namespace App\Services\Analytics;

use App\Repositories\Analytics\ContentCatalogRepository;
use App\Support\Analytics\AnalyticsDateRange;

class ContentCatalogService
{
    public function __construct(
        private readonly ContentCatalogRepository $contentCatalogRepository,
    ) {
    }

    /**
     * @return array{totals: array<string, int>, groups: list<array<string, mixed>>}
     */
    public function dashboard(AnalyticsDateRange $range): array
    {
        return $this->contentCatalogRepository->dashboard($range);
    }
}
