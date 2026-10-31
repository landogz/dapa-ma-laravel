<?php

namespace App\Services;

use App\Models\AppTranslation;
use App\Models\User;
use App\Repositories\AppTranslationRepository;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Support\AppTranslation\Visual;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AppTranslationService
{
    public function __construct(
        private readonly AppTranslationRepository $appTranslationRepository,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    /**
     * @param  list<string>|null  $keys
     */
    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $group = null,
        ?bool $activeOnly = null,
        ?array $keys = null,
        ?User $actor = null,
    ): LengthAwarePaginator {
        return $this->appTranslationRepository->paginate($perPage, $search, $group, $activeOnly, $keys, $actor);
    }

    public function find(int $id): AppTranslation
    {
        return $this->appTranslationRepository->findOrFail($id);
    }

    public function create(array $data, User $actor): AppTranslation
    {
        $payload = $this->contentWorkflow->prepareCreatePayload($actor, $this->normalize($data));

        return $this->appTranslationRepository->create($payload);
    }

    public function update(AppTranslation $translation, array $data, User $actor): AppTranslation
    {
        $payload = $this->normalize($data, $translation);

        // Key is immutable after create.
        unset($payload['key']);

        $payload = $this->contentWorkflow->prepareUpdatePayload($translation, $actor, $payload);

        return $this->appTranslationRepository->update($translation, $payload);
    }

    public function delete(AppTranslation $translation): void
    {
        $this->deleteStoredImage($translation->image_path);
        $this->appTranslationRepository->delete($translation);
    }

    public function groups(): Collection
    {
        return $this->appTranslationRepository->groups();
    }

    public function groupCounts(): Collection
    {
        return $this->appTranslationRepository->groupCounts();
    }

    /**
     * Mobile-ready locale bundle.
     *
     * @return array{locale: string, version: string|null, strings: array<string, string>, assets: array<string, array{image_url: ?string, icon: ?string}>}
     */
    public function publicBundle(string $locale = 'en'): array
    {
        $locale = in_array($locale, ['tl', 'fil'], true) ? 'tl' : 'en';
        $rows = $this->appTranslationRepository->activeBundle();

        $strings = [];
        $assets = [];
        foreach ($rows as $row) {
            $strings[$row->key] = $locale === 'tl' ? $row->value_tl : $row->value_en;
            $asset = Visual::publicAsset($row);
            if ($asset !== null) {
                $assets[$row->key] = $asset;
            }
        }

        return [
            'locale' => $locale,
            'version' => $this->appTranslationRepository->latestUpdatedAt(),
            'strings' => $strings,
            'assets' => $assets,
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

        $this->appTranslationRepository->deleteBibleCatalog();

        $rows = [];
        foreach ($decoded as $item) {
            if (empty($item['key']) || ! isset($item['en'], $item['tl'])) {
                continue;
            }

            $group = $item['group'] ?? null;
            if ($group === 'bible') {
                continue;
            }

            $rows[] = [
                'key' => (string) $item['key'],
                'group' => $group,
                'description' => $item['description'] ?? null,
                'value_en' => (string) $item['en'],
                'value_tl' => (string) $item['tl'],
                'is_active' => true,
            ];
        }

        return $this->appTranslationRepository->syncMany($rows);
    }

    private function normalize(array $data, ?AppTranslation $existing = null): array
    {
        $payload = [
            'group' => $this->normalizeGroup($data['group'] ?? null),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'value_en' => trim((string) ($data['value_en'] ?? '')),
            'value_tl' => trim((string) ($data['value_tl'] ?? '')),
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($existing?->is_active ?? true),
        ];

        if (array_key_exists('icon', $data)) {
            $icon = trim((string) ($data['icon'] ?? ''));
            $payload['icon'] = $icon === '' ? null : $icon;
        }

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $this->deleteStoredImage($existing?->image_path);
            $payload['image_path'] = $data['image']->store('app-translations', 'public');
        } elseif (! empty($data['remove_image'])) {
            $this->deleteStoredImage($existing?->image_path);
            $payload['image_path'] = null;
        }

        if (! $existing) {
            $payload['key'] = trim((string) ($data['key'] ?? ''));
        }

        return $payload;
    }

    private function normalizeGroup(mixed $group): ?string
    {
        $value = filled($group) ? trim((string) $group) : null;

        if ($value === 'bible') {
            return 'kid_listo';
        }

        return $value;
    }

    private function deleteStoredImage(?string $path): void
    {
        if (! filled($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
