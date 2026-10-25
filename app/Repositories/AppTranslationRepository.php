<?php

namespace App\Repositories;

use App\Models\AppTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AppTranslationRepository
{
    /**
     * @param  list<string>|null  $keys
     */
    public function paginate(
        int $perPage = 50,
        ?string $search = null,
        ?string $group = null,
        ?bool $activeOnly = null,
        ?array $keys = null,
    ): LengthAwarePaginator {
        return AppTranslation::query()
            ->where(function ($query): void {
                $query->whereNull('group')->orWhere('group', '!=', 'bible');
            })
            ->when($activeOnly === true, fn ($q) => $q->where('is_active', true))
            ->when($group, fn ($q) => $q->where('group', $group))
            ->when($keys, fn ($q) => $q->whereIn('key', $keys))
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($q) use ($search): void {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('group', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('value_en', 'like', "%{$search}%")
                        ->orWhere('value_tl', 'like', "%{$search}%");
                });
            })
            ->orderBy('group')
            ->orderBy('key')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): AppTranslation
    {
        return AppTranslation::query()->findOrFail($id);
    }

    public function create(array $data): AppTranslation
    {
        return AppTranslation::create($data);
    }

    public function update(AppTranslation $translation, array $data): AppTranslation
    {
        $translation->update($data);

        return $translation->fresh();
    }

    public function delete(AppTranslation $translation): void
    {
        $translation->delete();
    }

    public function groups(): Collection
    {
        return AppTranslation::query()
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->where('group', '!=', 'bible')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');
    }

    public function groupCounts(): Collection
    {
        return AppTranslation::query()
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->where('group', '!=', 'bible')
            ->selectRaw('`group`, count(*) as total')
            ->groupBy('group')
            ->orderBy('group')
            ->pluck('total', 'group');
    }

    public function deleteBibleCatalog(): int
    {
        return AppTranslation::query()
            ->where(function ($query): void {
                $query->where('group', 'bible')
                    ->orWhereIn('key', [
                        'bibleTitle',
                        'searchBibleBooks',
                        'oldTestament',
                        'newTestament',
                        'noBibleBooksFound',
                        'bibleLoadFailed',
                        'bibleLanguageNote',
                        'chaptersLabel',
                    ]);
            })
            ->delete();
    }

    public function syncMany(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $existing = AppTranslation::query()->where('key', $row['key'])->first();

            if (! $existing) {
                AppTranslation::create($row);
                $count++;
                continue;
            }

            $update = [
                'group' => $row['group'] ?? $existing->group,
            ];

            if (! filled($existing->value_en) || $this->looksBroken($existing->value_en)) {
                $update['value_en'] = $row['value_en'];
            }

            if (! filled($existing->value_tl) || $this->looksBroken($existing->value_tl)) {
                $update['value_tl'] = $row['value_tl'];
            }

            if ($update !== ['group' => $existing->group]) {
                $existing->update($update);
            }

            $count++;
        }

        return $count;
    }

    public function activeBundle(?string $locale = null): Collection
    {
        return AppTranslation::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('group')->orWhere('group', '!=', 'bible');
            })
            ->whereNotIn('key', [
                'bibleTitle',
                'searchBibleBooks',
                'oldTestament',
                'newTestament',
                'noBibleBooksFound',
                'bibleLoadFailed',
                'bibleLanguageNote',
                'chaptersLabel',
            ])
            ->orderBy('key')
            ->get(['key', 'value_en', 'value_tl', 'icon', 'image_path', 'updated_at']);
    }

    public function latestUpdatedAt(): ?string
    {
        $value = AppTranslation::query()->where('is_active', true)->max('updated_at');

        return $value ? (string) $value : null;
    }

    private function looksBroken(?string $value): bool
    {
        $text = trim((string) $value);

        return $text === ''
            || str_contains($text, 's scripture')
            || str_contains($text, 's note')
            || str_contains($text, '${')
            || str_contains($text, ' == 1 ?');
    }

    public function upsertMany(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            AppTranslation::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'group' => $row['group'] ?? null,
                    'description' => $row['description'] ?? null,
                    'value_en' => $row['value_en'],
                    'value_tl' => $row['value_tl'],
                    'is_active' => $row['is_active'] ?? true,
                ],
            );
            $count++;
        }

        return $count;
    }
}
