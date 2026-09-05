<?php

namespace App\Services;

use App\Models\IecMaterial;
use App\Repositories\IecMaterialRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IecMaterialService
{
    public function __construct(
        private readonly IecMaterialRepository $iecMaterialRepository,
    ) {
    }

    public function list(
        int $perPage = 24,
        ?string $search = null,
        ?string $topic = null,
        ?string $mediaType = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return $this->iecMaterialRepository->paginate(
            $perPage,
            $search,
            $topic,
            $mediaType,
            $activeOnly,
        );
    }

    public function find(int $id): IecMaterial
    {
        return $this->iecMaterialRepository->findOrFail($id);
    }

    public function create(array $data): IecMaterial
    {
        $payload = $this->normalizePayload($data);

        return $this->iecMaterialRepository->create($payload);
    }

    public function update(IecMaterial $material, array $data): IecMaterial
    {
        $payload = $this->normalizePayload($data, $material);

        return $this->iecMaterialRepository->update($material, $payload);
    }

    public function delete(IecMaterial $material): void
    {
        $this->deleteStoredMedia($material->getRawOriginal('media_url') ?? $material->media_url);
        $this->deleteStoredMedia($material->getRawOriginal('thumbnail_url') ?? $material->thumbnail_url);
        $this->iecMaterialRepository->delete($material);
    }

    private function normalizePayload(array $data, ?IecMaterial $existing = null): array
    {
        $payload = collect($data)->except(['media_file', 'thumbnail_file'])->all();

        if (($data['media_file'] ?? null) instanceof UploadedFile) {
            if ($existing) {
                $this->deleteStoredMedia($existing->getRawOriginal('media_url') ?? $existing->media_url);
            }
            $payload['media_url'] = $this->storeMediaFile($data['media_file']);

            $extension = strtolower($data['media_file']->getClientOriginalExtension() ?: '');
            if (($payload['media_type'] ?? null) === null || in_array($payload['media_type'] ?? '', ['gif', 'image'], true)) {
                $payload['media_type'] = $extension === 'gif' ? 'gif' : ($payload['media_type'] ?? 'image');
            }
        }

        if (($data['thumbnail_file'] ?? null) instanceof UploadedFile) {
            if ($existing) {
                $this->deleteStoredMedia($existing->getRawOriginal('thumbnail_url') ?? $existing->thumbnail_url);
            }
            $payload['thumbnail_url'] = $this->storeMediaFile($data['thumbnail_file'], 'iec-materials/thumbnails');
        }

        if (array_key_exists('media_url', $payload) && blank($payload['media_url'])) {
            unset($payload['media_url']);
        }

        if (array_key_exists('thumbnail_url', $payload) && blank($payload['thumbnail_url'])) {
            unset($payload['thumbnail_url']);
        }

        return $payload;
    }

    private function storeMediaFile(UploadedFile $mediaFile, string $directory = 'iec-materials/media'): string
    {
        $path = $mediaFile->store($directory, 'public');

        return Storage::disk('public')->url($path);
    }

    private function deleteStoredMedia(?string $mediaUrl): void
    {
        if (! $mediaUrl) {
            return;
        }

        $storagePrefix = Storage::disk('public')->url('/');

        if (! str_starts_with($mediaUrl, $storagePrefix)) {
            return;
        }

        $relativePath = ltrim(substr($mediaUrl, strlen($storagePrefix)), '/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
