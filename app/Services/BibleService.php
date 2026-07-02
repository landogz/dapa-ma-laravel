<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BibleService
{
    private const HELLOAO_BASE = 'https://bible.helloao.org/api';

    /** @var array<string, string> */
    private const BOOK_CODES = [
        'genesis'       => 'GEN',
        'exodus'        => 'EXO',
        'psalms'        => 'PSA',
        'proverbs'      => 'PRO',
        'isaiah'        => 'ISA',
        'matthew'       => 'MAT',
        'mark'          => 'MRK',
        'luke'          => 'LUK',
        'john'          => 'JHN',
        'acts'          => 'ACT',
        'romans'        => 'ROM',
        '1 corinthians' => '1CO',
        'galatians'     => 'GAL',
        'ephesians'     => 'EPH',
        'philippians'   => 'PHP',
        'james'         => 'JAS',
        '1 peter'       => '1PE',
        '1 john'        => '1JN',
        'revelation'    => 'REV',
    ];

    /** @var list<string> */
    private const SUPPORTED_BOOK_IDS = [
        'genesis',
        'exodus',
        'psalms',
        'proverbs',
        'isaiah',
        'matthew',
        'mark',
        'luke',
        'john',
        'acts',
        'romans',
        '1 corinthians',
        'galatians',
        'ephesians',
        'philippians',
        'james',
        '1 peter',
        '1 john',
        'revelation',
    ];

    public function books(?string $locale = null): array
    {
        $translation = $this->resolveTranslation($locale);
        $cacheKey = "bible:books:{$translation}";

        return Cache::remember($cacheKey, now()->addWeek(), function () use ($translation, $locale) {
            $response = Http::timeout(15)->get(self::HELLOAO_BASE . "/{$translation}/books.json");

            if (! $response->successful()) {
                return $this->fallbackBooks($locale);
            }

            $payload = $response->json();
            $remoteBooks = collect($payload['books'] ?? [])
                ->keyBy(static fn (array $book) => Str::upper((string) ($book['id'] ?? '')));

            $books = [];

            foreach (self::SUPPORTED_BOOK_IDS as $bookId) {
                $code = self::BOOK_CODES[$bookId] ?? null;

                if (! $code) {
                    continue;
                }

                $remote = $remoteBooks->get($code);

                if (! is_array($remote)) {
                    continue;
                }

                $books[] = [
                    'id'        => $bookId,
                    'name'      => (string) ($remote['name'] ?? Str::title($bookId)),
                    'testament' => Str::upper((string) ($remote['testament'] ?? 'NT')) === 'OT' ? 'OT' : 'NT',
                    'chapters'  => (int) ($remote['numberOfChapters'] ?? 1),
                ];
            }

            return $books ?: $this->fallbackBooks($locale);
        });
    }

    public function passage(
        string $book,
        int $chapter,
        ?int $verseStart = null,
        ?int $verseEnd = null,
        ?string $locale = null,
    ): array {
        $bookId = $this->resolveBookId($book);
        $bookCode = self::BOOK_CODES[$bookId];
        $translation = $this->resolveTranslation($locale);
        $cacheKey = "bible:{$translation}:{$bookCode}:{$chapter}:{$verseStart}-{$verseEnd}";

        return Cache::remember($cacheKey, now()->addDay(), function () use (
            $translation,
            $bookCode,
            $bookId,
            $chapter,
            $verseStart,
            $verseEnd,
            $locale,
        ) {
            $response = Http::timeout(15)->get(
                self::HELLOAO_BASE . "/{$translation}/{$bookCode}/{$chapter}.json",
            );

            if (! $response->successful()) {
                abort(502, 'Unable to load Bible passage at this time.');
            }

            $payload = $response->json();
            $chapterData = $payload['chapter'] ?? [];
            $content = is_array($chapterData['content'] ?? null) ? $chapterData['content'] : [];

            $verses = [];

            foreach ($content as $block) {
                if (! is_array($block) || ($block['type'] ?? '') !== 'verse') {
                    continue;
                }

                $verseNumber = (int) ($block['number'] ?? 0);

                if ($verseNumber < 1) {
                    continue;
                }

                if ($verseStart !== null && $verseNumber < $verseStart) {
                    continue;
                }

                if ($verseEnd !== null && $verseNumber > $verseEnd) {
                    continue;
                }

                $verses[] = [
                    'verse' => $verseNumber,
                    'text'  => $this->verseTextFromContent($block['content'] ?? []),
                ];
            }

            $bookName = (string) ($payload['book']['name'] ?? Str::title(str_replace('-', ' ', $bookId)));
            $reference = "{$bookName} {$chapter}";

            if ($verseStart !== null) {
                $reference .= ":{$verseStart}";
                if ($verseEnd !== null && $verseEnd !== $verseStart) {
                    $reference .= "-{$verseEnd}";
                }
            } elseif (is_string($payload['thisChapterReference'] ?? null)) {
                $reference = (string) $payload['thisChapterReference'];
            }

            return [
                'reference'   => $reference,
                'translation' => $this->translationLabel($translation, $locale),
                'locale'      => $this->normalizeLocale($locale),
                'verses'      => $verses,
                'text'        => collect($verses)->pluck('text')->implode(' '),
            ];
        });
    }

    private function resolveBookId(string $book): string
    {
        $needle = Str::lower(trim($book));

        if (isset(self::BOOK_CODES[$needle])) {
            return $needle;
        }

        foreach (self::BOOK_CODES as $slug => $code) {
            if ($code === Str::upper($needle)) {
                return $slug;
            }
        }

        abort(422, 'Unknown Bible book.');
    }

    private function resolveTranslation(?string $locale): string
    {
        return match ($this->normalizeLocale($locale)) {
            'tl'    => 'tgl_ulb',
            default => 'BSB',
        };
    }

    private function normalizeLocale(?string $locale): string
    {
        $value = Str::lower(trim((string) $locale));

        return in_array($value, ['tl', 'fil', 'tagalog', 'filipino'], true) ? 'tl' : 'en';
    }

    private function translationLabel(string $translationId, ?string $locale): string
    {
        return match ($this->normalizeLocale($locale)) {
            'tl'    => 'Tagalog (Banal na Bibliya)',
            default => 'English (Berean Standard Bible)',
        };
    }

  /**
     * @param  array<int, mixed>  $content
     */
    private function verseTextFromContent(array $content): string
    {
        $parts = [];

        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;
                continue;
            }

            if (! is_array($part)) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $parts[] = $part['text'];
                continue;
            }

            if (isset($part['content']) && is_array($part['content'])) {
                $parts[] = $this->verseTextFromContent($part['content']);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '');
    }

    /**
     * @return list<array{id: string, name: string, testament: string, chapters: int}>
     */
    private function fallbackBooks(?string $locale): array
    {
        $isTagalog = $this->normalizeLocale($locale) === 'tl';

        $names = $isTagalog
            ? [
                'genesis' => 'Genesis',
                'exodus' => 'Exodo',
                'psalms' => 'Mga Awit',
                'proverbs' => 'Mga Kawikaan',
                'isaiah' => 'Isaias',
                'matthew' => 'Mateo',
                'mark' => 'Marcos',
                'luke' => 'Lucas',
                'john' => 'Juan',
                'acts' => 'Mga Gawa',
                'romans' => 'Roma',
                '1 corinthians' => '1 Corinto',
                'galatians' => 'Galacia',
                'ephesians' => 'Efeso',
                'philippians' => 'Filipos',
                'james' => 'Santiago',
                '1 peter' => '1 Pedro',
                '1 john' => '1 Juan',
                'revelation' => 'Pahayag',
            ]
            : [
                'genesis' => 'Genesis',
                'exodus' => 'Exodus',
                'psalms' => 'Psalms',
                'proverbs' => 'Proverbs',
                'isaiah' => 'Isaiah',
                'matthew' => 'Matthew',
                'mark' => 'Mark',
                'luke' => 'Luke',
                'john' => 'John',
                'acts' => 'Acts',
                'romans' => 'Romans',
                '1 corinthians' => '1 Corinthians',
                'galatians' => 'Galatians',
                'ephesians' => 'Ephesians',
                'philippians' => 'Philippians',
                'james' => 'James',
                '1 peter' => '1 Peter',
                '1 john' => '1 John',
                'revelation' => 'Revelation',
            ];

        $chapters = [
            'genesis' => 50, 'exodus' => 40, 'psalms' => 150, 'proverbs' => 31, 'isaiah' => 66,
            'matthew' => 28, 'mark' => 16, 'luke' => 24, 'john' => 21, 'acts' => 28,
            'romans' => 16, '1 corinthians' => 16, 'galatians' => 6, 'ephesians' => 6,
            'philippians' => 4, 'james' => 5, '1 peter' => 5, '1 john' => 5, 'revelation' => 22,
        ];

        $testaments = [
            'genesis' => 'OT', 'exodus' => 'OT', 'psalms' => 'OT', 'proverbs' => 'OT', 'isaiah' => 'OT',
        ];

        return array_map(static fn (string $id) => [
            'id'        => $id,
            'name'      => $names[$id],
            'testament' => $testaments[$id] ?? 'NT',
            'chapters'  => $chapters[$id],
        ], self::SUPPORTED_BOOK_IDS);
    }
}
