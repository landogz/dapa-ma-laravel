<?php

namespace Database\Seeders;

use App\Models\PosterContest;
use App\Models\PosterContestEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class PosterContestSeeder extends Seeder
{
    public function run(): void
    {
        $appUser = User::query()->where('role', 'app_user')->first()
            ?? User::query()->where('role', 'user')->first();

        $openContest = PosterContest::query()->updateOrCreate(
            ['title' => 'DDB Youth Poster-Making Contest 2026'],
            [
                'description' => 'Design an original advocacy poster promoting a drug-free lifestyle.',
                'rules' => "1. One poster entry per user.\n2. Original artwork only.\n3. Upload a clear image (JPG/PNG/WebP) or provide an image URL.\n4. Admin will review submissions before publishing winners.",
                'theme' => 'Drug-Free Youth',
                'contest_year' => 2026,
                'submission_starts_at' => now('Asia/Manila')->subDays(3)->toDateString(),
                'submission_ends_at' => now('Asia/Manila')->addDays(45)->toDateString(),
                'status' => 'open',
                'cover_image_url' => null,
                'is_active' => true,
            ],
        );

        PosterContest::query()->updateOrCreate(
            ['title' => 'IEC Poster Challenge 2025'],
            [
                'description' => 'Completed poster contest featuring community advocacy designs.',
                'rules' => 'Submissions are closed. Winners have been announced.',
                'theme' => 'Prevention IEC',
                'contest_year' => 2025,
                'submission_starts_at' => now('Asia/Manila')->subYear()->toDateString(),
                'submission_ends_at' => now('Asia/Manila')->subMonths(8)->toDateString(),
                'status' => 'completed',
                'cover_image_url' => null,
                'is_active' => true,
            ],
        );

        if ($appUser) {
            PosterContestEntry::query()->updateOrCreate(
                [
                    'poster_contest_id' => $openContest->id,
                    'user_id' => $appUser->id,
                ],
                [
                    'title' => 'Choose Life Poster',
                    'creator_name' => $appUser->name,
                    'description' => 'Sample pending poster submission for admin review.',
                    'poster_image_url' => 'https://picsum.photos/seed/dape-poster/800/1100',
                    'region' => 'NCR',
                    'status' => 'pending',
                ],
            );
        }
    }
}
