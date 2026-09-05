<?php

namespace Database\Seeders;

use App\Models\DailyVerse;
use Illuminate\Database\Seeder;

class DailyVerseSeeder extends Seeder
{
    public function run(): void
    {
        $messages = require database_path('data/kid_listo_365.php');

        foreach ($messages as $message) {
            DailyVerse::query()->updateOrCreate(
                ['day_of_year' => (int) $message['day_of_year']],
                [
                    'reference' => $message['reference'],
                    'verse_text' => $message['verse_text'],
                    'verse_text_en' => $message['verse_text_en'] ?? null,
                    'kid_listo_message' => $message['kid_listo_message'] ?? null,
                    'kid_listo_message_en' => $message['kid_listo_message_en'] ?? null,
                    'translation' => $message['translation'] ?? 'Tagalog',
                    'book' => $message['book'],
                    'chapter' => $message['chapter'],
                    'verse_start' => $message['verse_start'],
                    'verse_end' => $message['verse_end'] ?? $message['verse_start'],
                ],
            );
        }
    }
}
