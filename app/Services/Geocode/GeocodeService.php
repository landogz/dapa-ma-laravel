<?php

namespace App\Services\Geocode;

use App\Repositories\Geocode\GeocodeRepository;

class GeocodeService
{
    public function __construct(
        private readonly GeocodeRepository $geocodeRepository,
    ) {
    }

    public function search(string $query): array
    {
        return $this->geocodeRepository->search($query);
    }

    public function reverse(float $latitude, float $longitude): ?array
    {
        return $this->geocodeRepository->reverse($latitude, $longitude);
    }
}
