<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VideoContest;
use App\Models\VideoContestEntry;
use Illuminate\Database\Seeder;

class VideoContestSeeder extends Seeder
{
    public function run(): void
    {
        $appUser = User::query()->where('role', 'app_user')->first()
            ?? User::query()->where('role', 'user')->first();

        $openContest = VideoContest::query()->updateOrCreate(
            ['title' => 'DDB Youth Video-Making Contest 2026'],
            [
                'description' => 'Create an original advocacy video promoting a drug-free lifestyle.',
                'rules' => "1. One video entry per user.\n2. Original video only.\n3. Submit a public YouTube (or video) link.\n4. Keep videos family-friendly and under 5 minutes when possible.\n5. Admin will review submissions before publishing winners.",
                'theme' => 'Drug-Free Youth',
                'contest_year' => 2026,
                'submission_starts_at' => now('Asia/Manila')->subDays(3)->toDateString(),
                'submission_ends_at' => now('Asia/Manila')->addDays(45)->toDateString(),
                'status' => 'open',
                'cover_image_url' => null,
                'is_active' => true,
            ],
        );

        VideoContest::query()->updateOrCreate(
            ['title' => 'IEC Video Challenge 2025'],
            [
                'description' => 'Completed video contest featuring community advocacy films.',
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
            VideoContestEntry::query()->updateOrCreate(
                [
                    'video_contest_id' => $openContest->id,
                    'user_id' => $appUser->id,
                ],
                [
                    'title' => 'Choose Life Video',
                    'creator_name' => $appUser->name,
                    'description' => 'Sample pending video submission for admin review.',
                    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'thumbnail_url' => null,
                    'region' => 'NCR',
                    'status' => 'pending',
                ],
            );
        }
    }
}
