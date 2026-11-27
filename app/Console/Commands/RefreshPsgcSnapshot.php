<?php

namespace App\Console\Commands;

use App\Repositories\Locations\PsgcRepository;
use Illuminate\Console\Command;
use Throwable;

class RefreshPsgcSnapshot extends Command
{
    protected $signature = 'psgc:refresh';

    protected $description = 'Download PSA PSGC regions/provinces/municipalities into storage/app/psgc (all-or-nothing).';

    public function __construct(
        private readonly PsgcRepository $psgcRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $regions = $this->psgcRepository->downloadLevel('regions');
            $provinces = $this->psgcRepository->downloadLevel('provinces');
            $municipalities = $this->psgcRepository->downloadLevel('municipalities');

            $this->psgcRepository->writeStorageSnapshots([
                'regions' => $regions,
                'provinces' => $provinces,
                'municipalities' => $municipalities,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('[DAPE-MA] PSGC refresh skipped: '.$exception->getMessage());

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '[DAPE-MA] PSGC snapshot refreshed (%d regions, %d provinces, %d municipalities).',
            count($regions),
            count($provinces),
            count($municipalities),
        ));

        return self::SUCCESS;
    }
}
