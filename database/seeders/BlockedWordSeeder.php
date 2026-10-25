<?php

namespace Database\Seeders;

use App\Models\BlockedWord;
use App\Services\BlockedWord\BlockedWordService;
use Illuminate\Database\Seeder;

class BlockedWordSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(BlockedWordService::class);
        $words = require database_path('seeders/BlockedWord/words.php');

        foreach ($words as $raw) {
            $normalized = $service->normalize(['word' => $raw, 'is_active' => true]);
            if ($normalized['word'] === '' || mb_strlen($normalized['word']) < 2) {
                continue;
            }

            BlockedWord::query()->updateOrCreate(
                ['word' => $normalized['word']],
                ['is_active' => true],
            );
        }
    }
}
