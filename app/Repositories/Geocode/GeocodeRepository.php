<?php

namespace App\Repositories\Geocode;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
        $cacheKey = "geocode:reverse:{$roundedLat}:{$roundedLng}";

        return Cache::remember($cacheKey, self::REVERSE_TTL_SECONDS, function () use ($latitude, $longitude) {
            $response = $this->client()->get('/reverse', [
                'format' => 'json',
                'lat' => $latitude,
                'lon' => $longitude,
                'addressdetails' => 1,
            ]);

            $this->assertSuccessful($response, 'Reverse geocoding is temporarily unavailable.');

            $payload = $response->json();
            if (! is_array($payload) || ($payload['error'] ?? null)) {
                return null;
            }

            return $this->mapPlace($payload, $latitude, $longitude);
        });
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.nominatim.base_url'), '/');
        $userAgent = (string) config('services.nominatim.user_agent');

        return Http::baseUrl($baseUrl)
            ->timeout(8)
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

        $region = trim((string) ($address['state'] ?? $address['region'] ?? ''));
        $province = trim((string) ($address['province'] ?? $address['county'] ?? ''));
        $city = trim((string) (
            $address['city']
            ?? $address['town']
            ?? $address['municipality']
            ?? $address['city_district']
            ?? $address['village']
            ?? $address['suburb']
            ?? ''
        ));

        // NCR and some PH results omit province; keep city for analytics without duplicating into province.
        if ($province === '' && $city !== '' && ! str_contains(mb_strtolower($region), 'capital')) {
            $province = $city;
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
}
