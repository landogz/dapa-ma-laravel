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

    public function forToday(): ?array
    {
        $dayOfYear = Carbon::today()->dayOfYear;
        $verse = $this->dailyVerseRepository->findByDayOfYear($dayOfYear)
            ?? $this->dailyVerseRepository->findFallback($dayOfYear);

        return $verse ? $this->formatVerse($verse) : null;
    }

    public function formatVerse(DailyVerse $verse): array
    {
        return [
            'reference'   => $verse->reference,
            'verse_text'  => $verse->verse_text,
            'translation' => $verse->translation,
            'book'        => $verse->book,
            'chapter'     => $verse->chapter,
            'verse_start' => $verse->verse_start,
            'verse_end'   => $verse->verse_end,
            'day_of_year' => $verse->day_of_year,
        ];
    }
}
