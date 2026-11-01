<?php

namespace App\Services\Analytics;

use App\Repositories\Analytics\CareToolsRepository;
use App\Support\Analytics\AnalyticsDateRange;

class CareToolsService
{
    public function __construct(
        private readonly CareToolsRepository $careToolsRepository,
    ) {
    }

    /**
     * @return array{totals: array<string, int>, features: array<string, int>}
     */
    public function dashboard(AnalyticsDateRange $range): array
    {
        return $this->careToolsRepository->dashboard($range);
    }
}
