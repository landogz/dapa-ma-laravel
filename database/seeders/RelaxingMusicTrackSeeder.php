<?php

namespace Database\Seeders;

use App\Models\RelaxingMusicTrack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class RelaxingMusicTrackSeeder extends Seeder
{
    public function run(): void
    {
        $sourceDir = database_path('seeders/data/relaxing-music');
        if (! is_dir($sourceDir)) {
            $sourceDir = base_path('../dape-ma-flutter/dape_ma_mobile/assets/care/calm/music');
        }
        if (! is_dir($sourceDir)) {
            $this->command?->warn('Relaxing music seed assets not found; skipping RelaxingMusicTrackSeeder.');

            return;
        }

        $tracks = [
            ['file' => 'tree_of_life.m4a', 'title' => 'Tree of Life', 'sort' => 1],
            ['file' => 'reverse_polarity.m4a', 'title' => 'Reverse Polarity', 'sort' => 2],
            ['file' => 'glass_castle.m4a', 'title' => 'Glass Castle', 'sort' => 3],
            ['file' => 'serenity.m4a', 'title' => 'Serenity', 'sort' => 4],
            ['file' => 'aurora_borealis.m4a', 'title' => 'Aurora Borealis', 'sort' => 5],
        ];

        $seeded = 0;

        foreach ($tracks as $track) {
            $source = $sourceDir.DIRECTORY_SEPARATOR.$track['file'];
            if (! is_file($source)) {
                $this->command?->warn("Missing music file: {$track['file']}");
                continue;
            }

            $relative = 'relaxing-music/'.$track['file'];
            Storage::disk('public')->put($relative, File::get($source));

            RelaxingMusicTrack::query()->updateOrCreate(
                ['title' => $track['title']],
                [
                    'title_tl' => $track['title'],
                    'audio_path' => $relative,
                    'is_active' => true,
                    'sort_order' => $track['sort'],
                    'workflow_status' => 'published',
                    'pending_revision' => null,
                    'revision_status' => null,
                    'review_notes' => null,
                ],
            );
            $seeded++;
        }

        $this->command?->info("Seeded {$seeded} relaxing music tracks.");
    }
}
