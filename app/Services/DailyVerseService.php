<?php

namespace App\Services;

use App\Models\DailyVerse;
use App\Repositories\DailyVerseRepository;
use Illuminate\Support\Carbon;

class DailyVerseService
{
    public function __construct(
        private readonly DailyVerseRepository $dailyVerseRepository,
    ) {
    }

    public function forToday(?string $locale = null): ?array
    {
        $dayOfYear = Carbon::now('Asia/Manila')->dayOfYear;
        $verse = $this->dailyVerseRepository->findByDayOfYear($dayOfYear)
            ?? $this->dailyVerseRepository->findFallback($dayOfYear);

        return $verse ? $this->formatVerse($verse, $locale) : null;
    }

    public function formatVerse(DailyVerse $verse, ?string $locale = null): array
    {
        $isEnglish = strtolower((string) $locale) === 'en';

        $verseText = $isEnglish && filled($verse->verse_text_en)
            ? $verse->verse_text_en
            : $verse->verse_text;

        $kidMessage = $isEnglish && filled($verse->kid_listo_message_en)
            ? $verse->kid_listo_message_en
            : ($verse->kid_listo_message ?: $verse->verse_text);

        return [
            'brand' => 'Kid Listo Says',
            'brand_tagline' => $isEnglish
                ? 'A daily Bible message for a drug-free youth'
                : 'Araw-araw na mensahe ng Bibliya para sa drug-free youth',
            'reference' => $verse->reference,
            'verse_text' => $verseText,
            'kid_listo_message' => $kidMessage,
            'translation' => $isEnglish ? 'English' : ($verse->translation ?: 'Tagalog'),
            'book' => $verse->book,
            'chapter' => $verse->chapter,
            'verse_start' => $verse->verse_start,
            'verse_end' => $verse->verse_end,
            'day_of_year' => $verse->day_of_year,
            'total_days' => 365,
        ];
    }
}
