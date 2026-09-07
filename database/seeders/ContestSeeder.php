<?php

namespace Database\Seeders;

use App\Models\Contest;
use Illuminate\Database\Seeder;

class ContestSeeder extends Seeder
{
    public function run(): void
    {
        if (Contest::query()->exists()) {
            return;
        }

        $year = (int) now('Asia/Manila')->format('Y');
        $startsAt = now('Asia/Manila')->subDays(3)->toDateString();
        $endsAt = now('Asia/Manila')->addDays(45)->toDateString();

        Contest::query()->create([
            'category' => 'song',
            'title' => 'DDB Youth Song Writing Contest '.$year,
            'description' => 'Submit an original song or advocacy playlist promoting a drug-free lifestyle.',
            'rules' => "1. One entry per user.\n2. Must be original or properly curated.\n3. Include a YouTube or Spotify link, or lyrics.\n4. Admin will review all submissions before publishing winners.",
            'theme' => 'Drug-Free Youth',
            'allowed_entry_types' => 'both',
            'contest_year' => $year,
            'submission_starts_at' => $startsAt,
            'submission_ends_at' => $endsAt,
            'status' => 'open',
            'cover_image_url' => null,
            'is_active' => true,
        ]);

        Contest::query()->create([
            'category' => 'poster',
            'title' => 'DDB Youth Poster-Making Contest '.$year,
            'description' => 'Design an original advocacy poster promoting a drug-free lifestyle.',
            'rules' => "1. One poster entry per user.\n2. Original artwork only.\n3. Upload a clear image (JPG/PNG/WebP) or provide an image URL.\n4. Admin will review submissions before publishing winners.",
            'theme' => 'Drug-Free Youth',
            'allowed_entry_types' => null,
            'contest_year' => $year,
            'submission_starts_at' => $startsAt,
            'submission_ends_at' => $endsAt,
            'status' => 'open',
            'cover_image_url' => null,
            'is_active' => true,
        ]);

        Contest::query()->create([
            'category' => 'video',
            'title' => 'DDB Youth Video-Making Contest '.$year,
            'description' => 'Create an original advocacy video promoting a drug-free lifestyle.',
            'rules' => "1. One video entry per user.\n2. Original video only.\n3. Submit a public YouTube (or video) link.\n4. Keep videos family-friendly and under 5 minutes when possible.\n5. Admin will review submissions before publishing winners.",
            'theme' => 'Drug-Free Youth',
            'allowed_entry_types' => null,
            'contest_year' => $year,
            'submission_starts_at' => $startsAt,
            'submission_ends_at' => $endsAt,
            'status' => 'open',
            'cover_image_url' => null,
            'is_active' => true,
        ]);
    }
}
