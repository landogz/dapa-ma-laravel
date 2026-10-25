<?php

namespace App\Services;

use App\Models\AppOption;
use App\Repositories\AppOptionRepository;
use App\Support\AppOptionGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppOptionService
{
    public function __construct(
        private readonly AppOptionRepository $repository,
    ) {
    }

    public function list(
        int $perPage = 100,
        ?string $search = null,
        ?string $group = null,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $group);
    }

    public function find(int $id): AppOption
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): AppOption
    {
        return $this->repository->create($this->normalize($data));
    }

    public function update(AppOption $option, array $data): AppOption
    {
        return $this->repository->update($option, $this->normalize($data, $option));
    }

    public function delete(AppOption $option): void
    {
        $this->repository->delete($option);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicCatalog(string $locale = 'en', ?string $group = null): array
    {
        $rows = $this->storedActive($group);
        $byGroup = $rows->groupBy('group');

        $keys = $group
            ? (AppOptionGroup::isValid($group) ? [$group] : [])
            : AppOptionGroup::keys();

        $catalog = [];

        foreach ($keys as $key) {
            $meta = AppOptionGroup::meta()[$key];
            $options = $byGroup->get($key, collect())
                ->map(fn (AppOption $option) => $option->toPublicArray($locale))
                ->values()
                ->all();

            if ($options === [] && ! $this->groupHasRows($key)) {
                $options = $this->fallbackOptions($key, $locale);
            }

            $catalog[] = [
                'key' => $key,
                'title' => $meta['title'],
                'subtitle' => $meta['subtitle'],
                'selection' => $meta['selection'],
                'surface' => $meta['surface'],
                'options' => $options,
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminMeta(): array
    {
        $counts = $this->repository->groupCounts();

        return [
            'groups' => collect(AppOptionGroup::meta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    ...$meta,
                    'count' => $counts[$key] ?? 0,
                ])
                ->values()
                ->all(),
            'icons' => AppOptionGroup::iconChoices(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?AppOption $existing = null): array
    {
        $group = $data['group'] ?? $existing?->group ?? AppOptionGroup::PRONOUNS;

        if (! AppOptionGroup::isValid($group)) {
            throw ValidationException::withMessages([
                'group' => ['Unknown option catalog.'],
            ]);
        }

        $key = trim((string) ($data['key'] ?? $existing?->key ?? ''));
        if ($key === '') {
            $key = Str::slug((string) ($data['label_en'] ?? ''), '_');
        }

        if ($key === '') {
            throw ValidationException::withMessages([
                'key' => ['A key is required.'],
            ]);
        }

        if ($this->repository->keyExists($group, $key, $existing?->id)) {
            throw ValidationException::withMessages([
                'key' => ['That key is already used in this catalog.'],
            ]);
        }

        $sortOrder = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== ''
            ? (int) $data['sort_order']
            : ($existing?->sort_order ?? $this->repository->nextSortOrder($group));

        $accent = $data['accent_color'] ?? $existing?->accent_color;
        if (is_string($accent)) {
            $accent = strtoupper(trim($accent));
            if ($accent !== '' && ! str_starts_with($accent, '#')) {
                $accent = '#'.$accent;
            }
            $accent = $accent === '' || $accent === '#' ? null : $accent;
        }

        return [
            'group' => $group,
            'key' => $key,
            'label_en' => trim((string) ($data['label_en'] ?? $existing?->label_en ?? '')),
            'label_tl' => trim((string) ($data['label_tl'] ?? $existing?->label_tl ?? '')),
            'subtitle_en' => $this->nullableString($data['subtitle_en'] ?? $existing?->subtitle_en),
            'subtitle_tl' => $this->nullableString($data['subtitle_tl'] ?? $existing?->subtitle_tl),
            'icon' => $this->nullableString($data['icon'] ?? $existing?->icon),
            'accent_color' => $accent,
            'sort_order' => max(0, $sortOrder),
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($existing?->is_active ?? true),
        ];
    }

    /**
     * @return Collection<int, AppOption>
     */
    private function storedActive(?string $group): Collection
    {
        if (! Schema::hasTable('app_options')) {
            return collect();
        }

        try {
            return $this->repository->active($group);
        } catch (\Throwable) {
            return collect();
        }
    }

    private function groupHasRows(string $group): bool
    {
        if (! Schema::hasTable('app_options')) {
            return false;
        }

        try {
            return $this->repository->hasAny($group);
        } catch (\Throwable) {
            return false;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackOptions(string $group, string $locale): array
    {
        $isTl = in_array($locale, ['tl', 'fil'], true);

        return collect(AppOptionGroup::seedRows())
            ->where('group', $group)
            ->map(function (array $row) use ($isTl) {
                return [
                    'key' => $row['key'],
                    'label' => $isTl ? $row['label_tl'] : $row['label_en'],
                    'label_en' => $row['label_en'],
                    'label_tl' => $row['label_tl'],
                    'subtitle' => $isTl ? ($row['subtitle_tl'] ?? '') : ($row['subtitle_en'] ?? ''),
                    'subtitle_en' => $row['subtitle_en'],
                    'subtitle_tl' => $row['subtitle_tl'],
                    'icon' => $row['icon'],
                    'accent' => $row['accent_color'],
                    'sort_order' => $row['sort_order'],
                ];
            })
            ->values()
            ->all();
    }
}
