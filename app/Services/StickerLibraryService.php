<?php

namespace App\Services;

use App\Models\Sticker;
use App\Models\StickerPack;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StickerLibraryService
{
    /**
     * @return Collection<int, StickerPack>
     */
    public function listPacks(?string $search = null, bool $activeOnly = false): Collection
    {
        $query = StickerPack::query()
            ->with(['stickers' => function ($q) use ($activeOnly): void {
                if ($activeOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('sort_order')->orderBy('id');
            }])
            ->withCount('stickers')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        return $query->get();
    }

    /**
     * @return Collection<int, StickerPack>
     */
    public function publicPacks(): Collection
    {
        return $this->listPacks(null, true);
    }

    public function findPack(int $id): StickerPack
    {
        return StickerPack::query()
            ->with(['stickers' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->withCount('stickers')
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPack(array $data): StickerPack
    {
        $payload = $this->normalizePack($data);
        if (empty($payload['slug'])) {
            $payload['slug'] = $this->uniqueSlug($payload['name']);
        }

        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $payload['cover_path'] = $data['cover']->store('stickers/packs', 'public');
        }

        $pack = StickerPack::query()->create($payload);

        return $this->findPack($pack->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePack(StickerPack $pack, array $data): StickerPack
    {
        $payload = $this->normalizePack($data, partial: true);

        if (array_key_exists('name', $payload) && empty($payload['slug'] ?? $pack->slug)) {
            $payload['slug'] = $this->uniqueSlug($payload['name'], $pack->id);
        }

        if (($data['cover'] ?? null) instanceof UploadedFile) {
            $this->deleteFile($pack->cover_path);
            $payload['cover_path'] = $data['cover']->store('stickers/packs', 'public');
        } elseif (! empty($data['remove_cover'])) {
            $this->deleteFile($pack->cover_path);
            $payload['cover_path'] = null;
        }

        $pack->update($payload);

        return $this->findPack($pack->id);
    }

    public function deletePack(StickerPack $pack): void
    {
        DB::transaction(function () use ($pack): void {
            $pack->load('stickers');
            foreach ($pack->stickers as $sticker) {
                $this->deleteFile($sticker->image_path);
            }
            $this->deleteFile($pack->cover_path);
            $pack->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSticker(array $data): Sticker
    {
        $payload = $this->normalizeSticker($data);

        if (! (($data['image'] ?? null) instanceof UploadedFile)) {
            throw new \InvalidArgumentException('Sticker image is required.');
        }

        $payload['image_path'] = $data['image']->store('stickers/items', 'public');

        if (! array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = (int) Sticker::query()
                ->where('sticker_pack_id', $payload['sticker_pack_id'])
                ->max('sort_order') + 1;
        }

        $sticker = Sticker::query()->create($payload);

        return $sticker->fresh();
    }

    /**
     * Create one or many stickers from uploaded image files.
     *
     * @param  array<string, mixed>  $data
     * @return list<Sticker>
     */
    public function createStickers(array $data): array
    {
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            $data['images'] ?? (($data['image'] ?? null) instanceof UploadedFile ? [$data['image']] : []),
            static fn ($file) => $file instanceof UploadedFile,
        ));

        if ($files === []) {
            throw new \InvalidArgumentException('At least one sticker image is required.');
        }

        $packId = (int) ($data['sticker_pack_id'] ?? 0);
        $nextSort = array_key_exists('sort_order', $data) && $data['sort_order'] !== null
            ? (int) $data['sort_order']
            : (int) Sticker::query()->where('sticker_pack_id', $packId)->max('sort_order') + 1;

        $created = [];

        foreach ($files as $index => $file) {
            $name = null;
            if (count($files) === 1 && filled($data['name'] ?? null)) {
                $name = (string) $data['name'];
            } else {
                $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
                $name = filled($original) ? $original : null;
            }

            $created[] = $this->createSticker([
                'sticker_pack_id' => $packId,
                'name' => $name,
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
    public function updateSticker(Sticker $sticker, array $data): Sticker
    {
        $payload = $this->normalizeSticker($data, partial: true);

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $this->deleteFile($sticker->image_path);
            $payload['image_path'] = $data['image']->store('stickers/items', 'public');
        }

        $sticker->update($payload);

        return $sticker->fresh();
    }

    public function deleteSticker(Sticker $sticker): void
    {
        $this->deleteFile($sticker->image_path);
        $sticker->delete();
    }

    /**
     * @param  list<array{id:int, sort_order:int}>  $items
     */
    public function reorderPacks(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                StickerPack::query()
                    ->whereKey((int) $item['id'])
                    ->update(['sort_order' => (int) $item['sort_order']]);
            }
        });
    }

    /**
     * @param  list<array{id:int, sort_order:int}>  $items
     */
    public function reorderStickers(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                Sticker::query()
                    ->whereKey((int) $item['id'])
                    ->update(['sort_order' => (int) $item['sort_order']]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePack(array $data, bool $partial = false): array
    {
        $fields = ['name', 'slug', 'description', 'is_active', 'sort_order'];
        $payload = [];

        foreach ($fields as $field) {
            if (! $partial || array_key_exists($field, $data)) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
        }

        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = (int) $payload['sort_order'];
        }

        if (array_key_exists('description', $payload) && $payload['description'] === '') {
            $payload['description'] = null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeSticker(array $data, bool $partial = false): array
    {
        $fields = ['sticker_pack_id', 'name', 'is_active', 'sort_order'];
        $payload = [];

        foreach ($fields as $field) {
            if (! $partial || array_key_exists($field, $data)) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
        }

        if (array_key_exists('sticker_pack_id', $payload)) {
            $payload['sticker_pack_id'] = (int) $payload['sticker_pack_id'];
        }

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = (int) $payload['sort_order'];
        }

        if (array_key_exists('name', $payload) && $payload['name'] === '') {
            $payload['name'] = null;
        }

        return $payload;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'pack';
        $slug = $base;
        $i = 2;

        while (
            StickerPack::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
