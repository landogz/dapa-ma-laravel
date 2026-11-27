<?php

namespace App\Repositories\Locations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PsgcRepository
{
    private const PAGE_SIZE = 100;

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function regions(): array
    {
        return $this->remember('psgc:regions', fn () => $this->fetchLevel('regions'));
    }

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function provinces(int $reg): array
    {
        return $this->remember(
            "psgc:provinces:{$reg}",
            fn () => $this->fetchLevel('provinces', ['reg' => $reg]),
        );
    }

    /**
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    public function municipalities(int $prv): array
    {
        return $this->remember(
            "psgc:municipalities:{$prv}",
            fn () => $this->fetchLevel('municipalities', ['prv' => $prv]),
        );
    }

    /**
     * @param  callable(): list<array{code:string,name:string,reg:int|null,prv:int|null}>  $loader
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    private function remember(string $cacheKey, callable $loader): array
    {
        $ttl = max(60, (int) config('services.psgc.cache_ttl', 86400));

        return Cache::remember($cacheKey, $ttl, $loader);
    }

    /**
     * @param  array<string, int|string>  $filters
     * @return list<array{code:string,name:string,reg:int|null,prv:int|null}>
     */
    private function fetchLevel(string $level, array $filters = []): array
    {
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

    private function path(string $level): string
    {
        $version = trim((string) config('services.psgc.version', 'Q2_2024'));

        return '/'.$version.'/'.$level;
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.psgc.base_url', 'https://classification.psa.gov.ph/psgc'), '/');

        return Http::baseUrl($baseUrl)
            ->timeout(12)
            ->connectTimeout(5)
            ->acceptJson();
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
