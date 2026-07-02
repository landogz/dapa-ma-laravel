<?php

namespace App\Repositories;

use App\Models\DailyVerse;

class DailyVerseRepository
{
    public function findByDayOfYear(int $dayOfYear): ?DailyVerse
    {
        return DailyVerse::query()
            ->where('day_of_year', $dayOfYear)
            ->first();
    }

    public function findFallback(int $dayOfYear): ?DailyVerse
    {
        $total = DailyVerse::query()->count();

        if ($total === 0) {
            return null;
        }

        $index = (($dayOfYear - 1) % $total) + 1;

        return DailyVerse::query()
            ->orderBy('day_of_year')
            ->skip($index - 1)
            ->first();
    }
}
