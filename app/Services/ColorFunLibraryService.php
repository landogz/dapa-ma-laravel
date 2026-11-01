<?php

namespace App\Services;

use App\Models\ColorFunWorksheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ColorFunLibraryService
{
    /**
     * @return Collection<int, ColorFunWorksheet>
     */
    public function list(?string $search = null, ?string $category = null, bool $activeOnly = false): Collection
    {
        $query = ColorFunWorksheet::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($category && $category !== ColorFunWorksheet::FILTER_ALL) {
            $query->where('category', $category);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }

        return $query->get();
    }

    /**
     * @return Collection<int, ColorFunWorksheet>
     */
    public function publicList(?string $category = null): Collection
    {
        return $this->list(null, $category, true);
    }

    public function find(int $id): ColorFunWorksheet
    {
        return ColorFunWorksheet::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ColorFunWorksheet
    {
        $payload = $this->normalize($data);

        if (! (($data['image'] ?? null) instanceof UploadedFile)) {
            throw new \InvalidArgumentException('Worksheet image is required.');
        }

        $payload['image_path'] = $data['image']->store('color-fun/worksheets', 'public');

        if (! array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = (int) ColorFunWorksheet::query()->max('sort_order') + 1;
        }

        return ColorFunWorksheet::query()->create($payload)->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<ColorFunWorksheet>
     */
    public function createMany(array $data): array
    {
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            $data['images'] ?? (($data['image'] ?? null) instanceof UploadedFile ? [$data['image']] : []),
            static fn ($file) => $file instanceof UploadedFile,
        ));

        if ($files === []) {
            throw new \InvalidArgumentException('At least one worksheet image is required.');
        }

        $nextSort = array_key_exists('sort_order', $data) && $data['sort_order'] !== null
            ? (int) $data['sort_order']
            : (int) ColorFunWorksheet::query()->max('sort_order') + 1;

        $created = [];

        foreach ($files as $index => $file) {
            $title = null;
            if (count($files) === 1 && filled($data['title'] ?? null)) {
                $title = (string) $data['title'];
            } else {
                $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
                $title = filled($original) ? $original : 'Worksheet';
            }

            $created[] = $this->create([
                'title' => $title,
                'category' => $data['category'] ?? 'nature',
                'image' => $file,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $nextSort + $index,
            ]);
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ColorFunWorksheet $worksheet, array $data): ColorFunWorksheet
    {
        $payload = $this->normalize($data, partial: true);

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $this->deleteFile($worksheet->image_path);
            $payload['image_path'] = $data['image']->store('color-fun/worksheets', 'public');
        }

        $worksheet->update($payload);

        return $worksheet->fresh();
    }

    public function delete(ColorFunWorksheet $worksheet): void
    {
        $this->deleteFile($worksheet->image_path);
        $worksheet->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        $payload = [];

        foreach (['title', 'category', 'is_active', 'sort_order'] as $key) {
            if (! array_key_exists($key, $data)) {
                if ($partial) {
                    continue;
                }
                if ($key === 'is_active') {
                    $payload[$key] = true;
                    continue;
                }
                if ($key === 'sort_order') {
                    continue;
                }
            }

            if (! array_key_exists($key, $data) && $partial) {
                continue;
            }

            $value = $data[$key] ?? null;

            if ($key === 'title') {
                $payload[$key] = filled($value) ? trim((string) $value) : 'Worksheet';
            } elseif ($key === 'category') {
                $category = strtolower(trim((string) $value));
                $allowed = ColorFunWorksheet::worksheetCategories();
                $payload[$key] = in_array($category, $allowed, true)
                    ? $category
                    : ($allowed[0] ?? 'nature');
            } elseif ($key === 'is_active') {
                $payload[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            } elseif ($key === 'sort_order' && $value !== null && $value !== '') {
                $payload[$key] = (int) $value;
            }
        }

        return $payload;
    }

    private function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete(ltrim($path, '/'));
    }
}
