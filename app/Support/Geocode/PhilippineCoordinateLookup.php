<?php

namespace App\Support\Geocode;

/**
 * Coarse offline reverse-geocode for the Philippines when Nominatim is unreachable.
 * Returns region / province / city approximations from lat/lng bounding boxes.
 */
final class PhilippineCoordinateLookup
{
    /**
     * @return array{label:string,address:string,latitude:float,longitude:float,region:string,province:string,city:string}|null
     */
    public static function reverse(float $latitude, float $longitude): ?array
    {
        if (! self::inPhilippines($latitude, $longitude)) {
            return null;
        }

        foreach (self::boxes() as $box) {
            if ($latitude < $box['south'] || $latitude > $box['north']
                || $longitude < $box['west'] || $longitude > $box['east']) {
                continue;
            }

            $region = $box['region'];
            $province = $box['province'];
            $city = $box['city'] ?? $province;
            $parts = array_values(array_unique(array_filter([$city, $province, $region])));
            $label = implode(', ', $parts).', Philippines';

            return [
                'label' => $label,
                'address' => $label,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'region' => $region,
                'province' => $province,
                'city' => $city,
            ];
        }

        return [
            'label' => 'Philippines',
            'address' => 'Philippines',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'region' => 'Philippines',
            'province' => '',
            'city' => '',
        ];
    }

    private static function inPhilippines(float $latitude, float $longitude): bool
    {
        return $latitude >= 4.2 && $latitude <= 21.5
            && $longitude >= 116.0 && $longitude <= 127.5;
    }

    /**
     * Rough bounding boxes — first match wins (more specific areas listed first).
     *
     * @return list<array{south:float,north:float,west:float,east:float,region:string,province:string,city?:string}>
     */
    private static function boxes(): array
    {
        return [
            // Major cities first (more specific).
            ['south' => 14.35, 'north' => 14.70, 'west' => 120.90, 'east' => 121.05, 'region' => 'National Capital Region', 'province' => 'Metro Manila', 'city' => 'Manila'],
            ['south' => 14.58, 'north' => 14.78, 'west' => 121.00, 'east' => 121.15, 'region' => 'National Capital Region', 'province' => 'Metro Manila', 'city' => 'Quezon City'],
            ['south' => 14.50, 'north' => 14.60, 'west' => 120.95, 'east' => 121.08, 'region' => 'National Capital Region', 'province' => 'Metro Manila', 'city' => 'Makati'],
            ['south' => 14.40, 'north' => 14.55, 'west' => 120.95, 'east' => 121.10, 'region' => 'National Capital Region', 'province' => 'Metro Manila', 'city' => 'Pasig'],
            ['south' => 14.30, 'north' => 14.80, 'west' => 120.85, 'east' => 121.25, 'region' => 'National Capital Region', 'province' => 'Metro Manila', 'city' => 'Metro Manila'],
            ['south' => 10.25, 'north' => 10.45, 'west' => 123.80, 'east' => 124.05, 'region' => 'Central Visayas', 'province' => 'Cebu', 'city' => 'Cebu City'],
            ['south' => 7.00, 'north' => 7.20, 'west' => 125.50, 'east' => 125.70, 'region' => 'Davao Region', 'province' => 'Davao del Sur', 'city' => 'Davao City'],
            ['south' => 8.40, 'north' => 8.55, 'west' => 124.55, 'east' => 124.75, 'region' => 'Northern Mindanao', 'province' => 'Misamis Oriental', 'city' => 'Cagayan de Oro'],
            ['south' => 10.65, 'north' => 10.80, 'west' => 122.50, 'east' => 122.65, 'region' => 'Western Visayas', 'province' => 'Iloilo', 'city' => 'Iloilo City'],
            ['south' => 13.10, 'north' => 13.20, 'west' => 123.70, 'east' => 123.80, 'region' => 'Bicol Region', 'province' => 'Albay', 'city' => 'Legazpi'],
            ['south' => 16.35, 'north' => 16.50, 'west' => 120.55, 'east' => 120.65, 'region' => 'Cordillera Administrative Region', 'province' => 'Benguet', 'city' => 'Baguio'],
            // Regions.
            ['south' => 15.80, 'north' => 18.60, 'west' => 119.80, 'east' => 122.20, 'region' => 'Ilocos Region', 'province' => 'Ilocos'],
            ['south' => 16.00, 'north' => 19.40, 'west' => 121.00, 'east' => 122.80, 'region' => 'Cagayan Valley', 'province' => 'Cagayan Valley'],
            ['south' => 14.60, 'north' => 16.50, 'west' => 120.00, 'east' => 121.60, 'region' => 'Central Luzon', 'province' => 'Central Luzon'],
            ['south' => 13.40, 'north' => 15.00, 'west' => 120.50, 'east' => 122.00, 'region' => 'CALABARZON', 'province' => 'CALABARZON'],
            ['south' => 9.00, 'north' => 14.00, 'west' => 116.80, 'east' => 122.50, 'region' => 'MIMAROPA', 'province' => 'MIMAROPA'],
            ['south' => 12.20, 'north' => 14.60, 'west' => 122.40, 'east' => 124.80, 'region' => 'Bicol Region', 'province' => 'Bicol'],
            ['south' => 9.40, 'north' => 12.20, 'west' => 121.40, 'east' => 123.80, 'region' => 'Western Visayas', 'province' => 'Western Visayas'],
            ['south' => 9.00, 'north' => 11.60, 'west' => 122.80, 'east' => 124.80, 'region' => 'Central Visayas', 'province' => 'Cebu'],
            ['south' => 9.80, 'north' => 12.80, 'west' => 124.00, 'east' => 126.00, 'region' => 'Eastern Visayas', 'province' => 'Eastern Visayas'],
            ['south' => 6.40, 'north' => 8.80, 'west' => 121.60, 'east' => 123.80, 'region' => 'Zamboanga Peninsula', 'province' => 'Zamboanga Peninsula'],
            ['south' => 7.40, 'north' => 9.60, 'west' => 123.60, 'east' => 125.60, 'region' => 'Northern Mindanao', 'province' => 'Northern Mindanao'],
            ['south' => 5.40, 'north' => 8.00, 'west' => 124.80, 'east' => 126.80, 'region' => 'Davao Region', 'province' => 'Davao Region'],
            ['south' => 5.00, 'north' => 7.80, 'west' => 123.80, 'east' => 125.40, 'region' => 'SOCCSKSARGEN', 'province' => 'SOCCSKSARGEN'],
            ['south' => 7.80, 'north' => 10.20, 'west' => 125.00, 'east' => 126.80, 'region' => 'Caraga', 'province' => 'Caraga'],
            ['south' => 4.40, 'north' => 8.20, 'west' => 119.60, 'east' => 125.20, 'region' => 'BARMM', 'province' => 'BARMM'],
            ['south' => 16.00, 'north' => 18.60, 'west' => 120.40, 'east' => 121.60, 'region' => 'Cordillera Administrative Region', 'province' => 'Cordillera'],
            ['south' => 4.20, 'north' => 21.50, 'west' => 116.00, 'east' => 127.50, 'region' => 'Philippines', 'province' => 'Philippines', 'city' => 'Philippines'],
        ];
    }
}
