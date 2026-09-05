<?php

namespace Database\Seeders;

use App\Models\Training;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $trainings = [
            [
                'title' => 'Barangay Anti-Drug Abuse Council (BADAC) Orientation',
                'description' => 'Capacity-building session for BADAC members on drug prevention programs, case referral pathways, and community advocacy.',
                'category' => 'Capacity Building',
                'region' => 'NCR',
                'venue' => 'DDB Conference Hall, Quezon City',
                'start_date' => now()->addDays(14)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'schedule_notes' => '8:00 AM – 4:00 PM',
                'organizer' => 'Dangerous Drugs Board',
                'contact' => '+63 2 8929 1753',
                'registration_url' => 'https://ddb.gov.ph',
                'slots' => 50,
                'is_active' => true,
            ],
            [
                'title' => 'Youth Against Drugs Leadership Training',
                'description' => 'Interactive workshop for youth leaders covering peer education, life skills, and anti-drug messaging.',
                'category' => 'Youth Development',
                'region' => 'Region VII',
                'venue' => 'Cebu City Sports Complex Conference Room',
                'start_date' => now()->addDays(28)->toDateString(),
                'end_date' => now()->addDays(29)->toDateString(),
                'schedule_notes' => 'Day 1–2 · 9:00 AM – 5:00 PM',
                'organizer' => 'DDB Region VII',
                'contact' => '+63 32 255 1234',
                'registration_url' => null,
                'slots' => 80,
                'is_active' => true,
            ],
            [
                'title' => 'Community-Based Drug Rehabilitation Facilitators Training',
                'description' => 'Training for LGU facilitators on community-based rehabilitation approaches and aftercare support.',
                'category' => 'Community-Based',
                'region' => 'Region XI',
                'venue' => 'Davao City Training Center',
                'start_date' => now()->addDays(45)->toDateString(),
                'end_date' => now()->addDays(47)->toDateString(),
                'schedule_notes' => '3-day live-in training',
                'organizer' => 'DDB Region XI',
                'contact' => '+63 82 221 5678',
                'registration_url' => 'https://ddb.gov.ph',
                'slots' => 40,
                'is_active' => true,
            ],
            [
                'title' => 'Preventive Drug Education for Teachers',
                'description' => 'Seminar for educators on integrating preventive drug education into school programs.',
                'category' => 'Preventive Education',
                'region' => 'Region III',
                'venue' => 'Clark Freeport Zone, Pampanga',
                'start_date' => now()->addDays(21)->toDateString(),
                'end_date' => now()->addDays(21)->toDateString(),
                'schedule_notes' => '1:00 PM – 5:00 PM',
                'organizer' => 'Dangerous Drugs Board',
                'contact' => '+63 45 599 0000',
                'registration_url' => null,
                'slots' => 100,
                'is_active' => true,
            ],
            [
                'title' => 'Anti-Drug Advocacy Communications Workshop',
                'description' => 'Hands-on workshop on creating effective anti-drug advocacy materials for communities and social media.',
                'category' => 'Anti-Drug Advocacy',
                'region' => 'NCR',
                'venue' => 'Online (Zoom)',
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(10)->toDateString(),
                'schedule_notes' => '10:00 AM – 12:00 NN',
                'organizer' => 'Dangerous Drugs Board – IEC Unit',
                'contact' => 'iec@ddb.gov.ph',
                'registration_url' => 'https://ddb.gov.ph',
                'slots' => 200,
                'is_active' => true,
            ],
        ];

        foreach ($trainings as $data) {
            Training::query()->updateOrCreate(
                [
                    'title' => $data['title'],
                    'region' => $data['region'],
                ],
                $data,
            );
        }
    }
}
