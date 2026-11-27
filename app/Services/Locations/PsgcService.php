<?php

namespace App\Services\Locations;

use App\Repositories\Locations\PsgcRepository;

class PsgcService
{
    public function __construct(
        private readonly PsgcRepository $psgcRepository,
    ) {
    }

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function regions(): array
    {
        return $this->psgcRepository->regions();
    }

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function provinces(int $reg): array
    {
        return $this->psgcRepository->provinces($reg);
    }

    /**
     * Cities/municipalities for a province code.
     * NCR cities and HUCs often have no municipality children — fall back to the province name.
     *
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function cities(int $prv, ?string $provinceName = null): array
    {
        $cities = $this->psgcRepository->municipalities($prv);
        if ($cities !== []) {
            return $cities;
        }

        $fallback = trim((string) $provinceName);
        if ($fallback === '') {
            return [];
        }

        return [[
            'code' => (string) $prv,
            'name' => $fallback,
            'reg' => null,
            'prv' => $prv,
        ]];
    }
}
