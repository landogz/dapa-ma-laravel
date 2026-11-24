<?php

namespace App\Repositories\Geocode;

use App\Support\Geocode\PhilippineCoordinateLookup;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeocodeRepository
{
    private const SEARCH_TTL_SECONDS = 86400;

    private const REVERSE_TTL_SECONDS = 86400;

    public function search(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        $cacheKey = 'geocode:search:'.md5($normalized);

        return Cache::remember($cacheKey, self::SEARCH_TTL_SECONDS, function () use ($query) {
            $response = $this->client()->get('/search', [
                'format' => 'json',
                'q' => $query,
                'countrycodes' => (string) config('services.nominatim.countrycodes', 'ph'),
                'addressdetails' => 1,
                'limit' => 8,
            ]);

            $this->assertSuccessful($response, 'Address search is temporarily unavailable.');

            return collect($response->json() ?? [])
                ->map(fn (array $item) => $this->mapPlace($item))
                ->filter()
                ->values()
                ->all();
        });
    }

    public function reverse(float $latitude, float $longitude): ?array
    {
        $roundedLat = round($latitude, 5);
        $roundedLng = round($longitude, 5);
        $cacheKey = "geocode:reverse:v2:{$roundedLat}:{$roundedLng}";

        return Cache::remember($cacheKey, self::REVERSE_TTL_SECONDS, function () use ($latitude, $longitude) {
            try {
                $response = $this->client()->get('/reverse', [
                    'format' => 'json',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'addressdetails' => 1,
                ]);

                if ($response->successful()) {
                    $payload = $response->json();
                    if (is_array($payload) && ! ($payload['error'] ?? null)) {
                        $place = $this->mapPlace($payload, $latitude, $longitude);
                        if ($place !== null && $this->hasUsablePlace($place)) {
                            return $place;
                        }
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Nominatim reverse failed; using offline PH lookup.', [
                    'lat' => $latitude,
                    'lng' => $longitude,
                    'error' => $exception->getMessage(),
                ]);
            }

            return PhilippineCoordinateLookup::reverse($latitude, $longitude);
        });
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.nominatim.base_url'), '/');
        $userAgent = (string) config('services.nominatim.user_agent');

        return Http::baseUrl($baseUrl)
            // Keep short so offline PH reverse can answer when Nominatim/DNS is down.
            ->timeout(3)
            ->connectTimeout(2)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => $userAgent,
                'Accept-Language' => 'en',
            ]);
    }

    private function assertSuccessful($response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException($message, previous: $exception);
        }
    }

    private function mapPlace(array $item, ?float $fallbackLat = null, ?float $fallbackLng = null): ?array
    {
        $label = trim((string) ($item['display_name'] ?? ''));
        $lat = isset($item['lat']) ? (float) $item['lat'] : $fallbackLat;
        $lng = isset($item['lon']) ? (float) $item['lon'] : $fallbackLng;

        if ($label === '' || ! is_numeric($lat) || ! is_numeric($lng) || ! is_finite((float) $lat) || ! is_finite((float) $lng)) {
            return null;
        }

        $address = is_array($item['address'] ?? null) ? $item['address'] : [];

        $region = trim((string) (
            $address['state']
            ?? $address['region']
            ?? $address['state_district']
            ?? ''
        ));
        $province = trim((string) (
            $address['province']
            ?? $address['county']
            ?? $address['state_district']
            ?? ''
        ));
        $city = trim((string) (
            $address['city']
            ?? $address['town']
            ?? $address['municipality']
            ?? $address['city_district']
            ?? $address['village']
            ?? $address['suburb']
            ?? $address['quarter']
            ?? $address['neighbourhood']
            ?? ''
        ));

        // NCR and some PH results omit province; keep city for analytics without duplicating into province.
        if ($province === '' && $city !== '' && ! str_contains(mb_strtolower($region), 'capital')) {
            $province = $city;
        }

        // Last resort: pull trailing PH place parts from display_name.
        if ($region === '' && $province === '' && $city === '') {
            $parsed = $this->parsePhilippineDisplayName($label);
            $region = $parsed['region'];
            $province = $parsed['province'];
            $city = $parsed['city'];
        }

        return [
            'label' => $label,
            'address' => $label,
            'latitude' => $lat,
            'longitude' => $lng,
            'region' => $region,
            'province' => $province,
            'city' => $city,
        ];
    }

    /**
     * @param  array{region?:string,province?:string,city?:string}  $place
     */
    private function hasUsablePlace(array $place): bool
    {
        return filled($place['region'] ?? null)
            || filled($place['province'] ?? null)
            || filled($place['city'] ?? null);
    }

    /**
     * @return array{region:string,province:string,city:string}
     */
    private function parsePhilippineDisplayName(string $label): array
    {
        $parts = array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            explode(',', $label),
        )));

        // Drop trailing country / postal codes.
        $parts = array_values(array_filter($parts, static function (string $part): bool {
            if (strcasecmp($part, 'Philippines') === 0) {
                return false;
            }

            return ! preg_match('/^\d{3,5}$/', $part);
        }));

        $count = count($parts);
        if ($count === 0) {
            return ['region' => '', 'province' => '', 'city' => ''];
        }

        $region = $parts[$count - 1] ?? '';
        $province = $parts[$count - 2] ?? $region;
        $city = $parts[$count - 3] ?? $province;

        return [
            'region' => $region,
            'province' => $province,
            'city' => $city,
        ];
    }
}
