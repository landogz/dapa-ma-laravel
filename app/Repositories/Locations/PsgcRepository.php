<?php

namespace App\Repositories\Locations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PsgcRepository
{
    private const PAGE_SIZE = 100;

    private const LEVELS = ['regions', 'provinces', 'municipalities'];

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function regions(): array
    {
        return $this->fromSnapshot('regions');
    }

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function provinces(int $reg): array
    {
        return $this->fromSnapshot('provinces', ['reg' => $reg]);
    }

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function municipalities(int $prv): array
    {
        return $this->fromSnapshot('municipalities', ['prv' => $prv]);
    }

    /**
     * Download one PSGC level from PSA (refresh command only — never on request path).
     *
     * @param  array<string, int|string>  $filters
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function downloadLevel(string $level, array $filters = []): array
    {
        if (! in_array($level, self::LEVELS, true)) {
            throw new RuntimeException('Unknown PSGC level: '.$level);
        }

        $token = trim((string) config('services.psgc.token'));
        if ($token === '') {
            throw new RuntimeException('PSGC API token is not configured.');
        }

        $items = [];
        $page = 1;

        do {
            $response = $this->client()->get($this->path($level), array_merge($filters, [
                'token' => $token,
                'page' => $page,
                'page_size' => self::PAGE_SIZE,
            ]));

            $this->assertSuccessful($response);

            $payload = $response->json();
            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
            foreach ($results as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $mapped = $this->mapRow($row);
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }

            $hasNext = filled($payload['next'] ?? null);
            $page++;
        } while ($hasNext && $page <= 50);

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * Write refreshed snapshots to storage/app/psgc only when all three levels are non-empty.
     *
     * @param  array{regions:list,provinces:list,municipalities:list}  $levels
     */
    public function writeStorageSnapshots(array $levels): void
    {
        foreach (self::LEVELS as $level) {
            $items = $levels[$level] ?? null;
            if (! is_array($items) || $items === []) {
                throw new RuntimeException('Refusing to write empty PSGC snapshot for '.$level.'.');
            }
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('psgc');

        foreach (self::LEVELS as $level) {
            $disk->put(
                'psgc/'.$level.'.json',
                json_encode($levels[$level], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
        }
    }

    /**
     * @param  array<string, int|string>  $filters
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    private function fromSnapshot(string $level, array $filters = []): array
    {
        $path = $this->resolveSnapshotPath($level);
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('PSGC snapshot is invalid for '.$level.'.');
        }

        $regFilter = isset($filters['reg']) ? (int) $filters['reg'] : null;
        $prvFilter = isset($filters['prv']) ? (int) $filters['prv'] : null;

        $items = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped = $this->mapSnapshotRow($row);
            if ($mapped === null) {
                continue;
            }
            if ($regFilter !== null && (int) ($mapped['reg'] ?? 0) !== $regFilter) {
                continue;
            }
            if ($prvFilter !== null && (int) ($mapped['prv'] ?? 0) !== $prvFilter) {
                continue;
            }
            $items[] = $mapped;
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $items;
    }

    private function resolveSnapshotPath(string $level): string
    {
        $refreshed = storage_path('app/psgc/'.$level.'.json');
        if (is_file($refreshed)) {
            return $refreshed;
        }

        // shortcut: shipped Q2_2024 extract until psgc:refresh can reach PSA
        $shipped = database_path('seeders/data/psgc/'.$level.'.json');
        if (is_file($shipped)) {
            return $shipped;
        }

        throw new RuntimeException('PSGC snapshot is missing for '.$level.'.');
    }

    private function path(string $level): string
    {
        $version = trim((string) config('services.psgc.version', 'Q2_2024'));

        return '/'.$version.'/'.$level;
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.psgc.base_url', 'https://classification.psa.gov.ph/psgc'), '/');

        return Http::baseUrl($baseUrl)
            ->timeout(8)
            ->connectTimeout(3)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'DAPE-MA/1.0',
            ]);
    }

    private function assertSuccessful($response): void
    {
        if ($response->successful()) {
            return;
        }

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('PSGC directory is temporarily unavailable.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code:string,name:string,reg:int|null,prv:int|null}|null
     */
    private function mapRow(array $row): ?array
    {
        $name = trim((string) ($row['area_name'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));
        if ($name === '' || $code === '') {
            return null;
        }

        return [
            'code' => $code,
            'name' => $name,
            'reg' => $this->nullableInt($row['reg'] ?? null),
            'prv' => $this->nullableInt($row['prv'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code:string,name:string,reg:int|null,prv:int|null}|null
     */
    private function mapSnapshotRow(array $row): ?array
    {
        $name = trim((string) ($row['name'] ?? $row['area_name'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));
        if ($name === '' || $code === '') {
            return null;
        }

        return [
            'code' => $code,
            'name' => $name,
            'reg' => $this->nullableInt($row['reg'] ?? null),
            'prv' => $this->nullableInt($row['prv'] ?? null),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
