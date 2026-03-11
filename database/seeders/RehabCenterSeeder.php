<?php

namespace Database\Seeders;

use App\Models\RehabCenter;
use Illuminate\Database\Seeder;

class RehabCenterSeeder extends Seeder
{
    public function run(): void
    {
        $centers = [
            [
                'name' => 'National Treatment and Rehabilitation Center',
                'region' => 'NCR',
                'province' => 'Metro Manila',
                'address' => 'Quezon City, Metro Manila',
                'contact' => '+63 2 1234 5678',
                'website' => 'https://example-ntc.gov.ph',
                'is_active' => true,
            ],
            [
                'name' => 'Visayas Regional Rehabilitation Center',
                'region' => 'Region VII',
                'province' => 'Cebu',
                'address' => 'Cebu City, Cebu',
                'contact' => '+63 32 234 5678',
                'website' => 'https://example-visayas-center.gov.ph',
                'is_active' => true,
            ],
            [
                'name' => 'Mindanao Recovery Facility',
                'region' => 'Region XI',
                'province' => 'Davao del Sur',
                'address' => 'Davao City, Davao del Sur',
                'contact' => '+63 82 345 6789',
                'website' => null,
                'is_active' => true,
            ],
        ];

        foreach ($centers as $data) {
            RehabCenter::query()->updateOrCreate(
                [
                    'name' => $data['name'],
                    'region' => $data['region'],
                ],
                $data,
            );
        }
    }
}

