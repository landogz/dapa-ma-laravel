<?php

namespace App\Services;

use App\Models\AppTranslation;
use App\Repositories\AppTranslationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AppTranslationService
{
    public function __construct(
        private readonly AppTranslationRepository $appTranslationRepository,
    ) {
    }

    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $group = null,
        ?bool $activeOnly = null,
    ): LengthAwarePaginator {
        return $this->appTranslationRepository->paginate($perPage, $search, $group, $activeOnly);
    }

    public function find(int $id): AppTranslation
    {
        return $this->appTranslationRepository->findOrFail($id);
    }

    public function create(array $data): AppTranslation
    {
        return $this->appTranslationRepository->create($this->normalize($data));
    }

    public function update(AppTranslation $translation, array $data): AppTranslation
    {
        $payload = $this->normalize($data, $translation);

        // Key is immutable after create.
        unset($payload['key']);

        return $this->appTranslationRepository->update($translation, $payload);
    }

    public function delete(AppTranslation $translation): void
    {
        $this->appTranslationRepository->delete($translation);
    }

    public function groups(): Collection
    {
        return $this->appTranslationRepository->groups();
    }

    /**
     * Mobile-ready locale bundle.
     *
     * @return array{locale: string, version: string|null, strings: array<string, string>}
     */
    public function publicBundle(string $locale = 'en'): array
    {
        $locale = in_array($locale, ['tl', 'fil'], true) ? 'tl' : 'en';
        $rows = $this->appTranslationRepository->activeBundle();

        $strings = [];
        foreach ($rows as $row) {
            $strings[$row->key] = $locale === 'tl' ? $row->value_tl : $row->value_en;
        }

        return [
            'locale' => $locale,
            'version' => $this->appTranslationRepository->latestUpdatedAt(),
            'strings' => $strings,
        ];
    }

    public function seedFromJson(string $path): int
    {
        if (! is_file($path)) {
            return 0;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return 0;
        }

        $rows = [];
        foreach ($decoded as $item) {
            if (empty($item['key']) || ! isset($item['en'], $item['tl'])) {
                continue;
            }

            $rows[] = [
                'key' => (string) $item['key'],
                'group' => $item['group'] ?? null,
                'description' => $item['description'] ?? null,
                'value_en' => (string) $item['en'],
                'value_tl' => (string) $item['tl'],
                'is_active' => true,
            ];
        }

        return $this->appTranslationRepository->upsertMany($rows);
    }

    private function normalize(array $data, ?AppTranslation $existing = null): array
    {
        $payload = [
            'group' => filled($data['group'] ?? null) ? trim((string) $data['group']) : null,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'value_en' => trim((string) ($data['value_en'] ?? '')),
            'value_tl' => trim((string) ($data['value_tl'] ?? '')),
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($existing?->is_active ?? true),
        ];

        if (! $existing) {
            $payload['key'] = trim((string) ($data['key'] ?? ''));
        }

        return $payload;
    }
}
