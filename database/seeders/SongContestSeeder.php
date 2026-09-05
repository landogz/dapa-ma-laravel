<?php

namespace Database\Seeders;

use App\Models\SongContest;
use App\Models\SongContestEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class SongContestSeeder extends Seeder
{
    public function run(): void
    {
        $appUser = User::query()->where('role', 'app_user')->first()
            ?? User::query()->where('role', 'user')->first();

        $openContest = SongContest::query()->updateOrCreate(
            ['title' => 'DDB Youth Song Writing Contest 2025'],
            [
                'description' => 'Submit an original song or advocacy playlist promoting a drug-free lifestyle.',
                'rules' => "1. One entry per user.\n2. Must be original or properly curated.\n3. Include a YouTube or Spotify link.\n4. Admin will review all submissions before publishing winners.",
                'theme' => 'Drug-Free Youth',
                'allowed_entry_types' => 'both',
                'contest_year' => 2025,
                'submission_starts_at' => now()->subDays(7)->toDateString(),
                'submission_ends_at' => now()->addDays(30)->toDateString(),
                'status' => 'open',
                'cover_image_url' => null,
                'is_active' => true,
            ],
        );

        SongContest::query()->updateOrCreate(
            ['title' => 'Playlist Advocacy Challenge 2024'],
            [
                'description' => 'Completed playlist contest featuring community-curated recovery tracks.',
                'rules' => 'Submissions are closed. Winners have been announced.',
                'theme' => 'Recovery Playlists',
                'allowed_entry_types' => 'playlist',
                'contest_year' => 2024,
                'submission_starts_at' => now()->subYear()->toDateString(),
                'submission_ends_at' => now()->subMonths(10)->toDateString(),
                'status' => 'completed',
                'cover_image_url' => null,
                'is_active' => true,
            ],
        );

        if ($appUser) {
            SongContestEntry::query()->updateOrCreate(
                [
                    'song_contest_id' => $openContest->id,
                    'user_id' => $appUser->id,
                ],
                [
                    'title' => 'Choose Life',
                    'artist_name' => $appUser->name,
                    'entry_type' => 'song',
                    'description' => 'Sample pending submission for admin review.',
                    'lyrics' => "Chorus\nChoose life, choose light, choose me",
                    'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'region' => 'NCR',
                    'status' => 'pending',
                ],
            );
        }
    }
}
