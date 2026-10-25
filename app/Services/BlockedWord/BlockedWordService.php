<?php

namespace App\Services\BlockedWord;

use App\Models\BlockedWord;
use App\Repositories\BlockedWord\BlockedWordRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class BlockedWordService
{
    private const CACHE_KEY = 'blocked_words.active';

    public const MASK = '****';

    public function __construct(
        private readonly BlockedWordRepository $blockedWordRepository,
    ) {
    }

    public function listAdmin(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->blockedWordRepository->paginate($perPage, $filters);
    }

    public function find(int $id): BlockedWord
    {
        return $this->blockedWordRepository->findOrFail($id);
    }

    public function create(array $data): BlockedWord
    {
        $word = $this->blockedWordRepository->create($this->normalize($data));
        $this->forgetCache();

        return $word;
    }

    public function update(BlockedWord $word, array $data): BlockedWord
    {
        $updated = $this->blockedWordRepository->update($word, $this->normalize($data));
        $this->forgetCache();

        return $updated;
    }

    public function delete(BlockedWord $word): void
    {
        $this->blockedWordRepository->delete($word);
        $this->forgetCache();
    }

    public function statusCounts(): array
    {
        return $this->blockedWordRepository->statusCounts();
    }

    /**
     * @return array{version: string, words: array<int, string>}
     */
    public function publicCatalog(): array
    {
        return [
            'version' => $this->blockedWordRepository->catalogVersion(),
            'words' => $this->activeWords(),
        ];
    }

    public function mask(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $words = $this->activeWords();
        if ($words === []) {
            return $text;
        }

        usort($words, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        $escaped = array_map(static fn (string $word): string => preg_quote($word, '/'), $words);
        $pattern = '/(?<![\p{L}\p{N}_])(?:'.implode('|', $escaped).')(?![\p{L}\p{N}_])/iu';

        return preg_replace($pattern, self::MASK, $text) ?? $text;
    }

    /**
     * @return array<int, string>
     */
    public function activeWords(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            return $this->blockedWordRepository->activeWords()
                ->map(fn ($word) => mb_strtolower(trim((string) $word)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{word: string, is_active: bool}
     */
    public function normalize(array $data): array
    {
        return [
            'word' => mb_strtolower(trim((string) ($data['word'] ?? ''))),
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : true,
        ];
    }

    private function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
