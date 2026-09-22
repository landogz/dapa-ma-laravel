<?php

namespace App\Services;

use App\Models\CareSupportResource;
use App\Repositories\CareSupportResourceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CareSupportResourceService
{
    public function __construct(
        private readonly CareSupportResourceRepository $repository,
    ) {
    }

    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $category);
    }

    public function find(int $id): CareSupportResource
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): CareSupportResource
    {
        return $this->repository->create($this->normalize($data));
    }

    public function update(CareSupportResource $resource, array $data): CareSupportResource
    {
        return $this->repository->update($resource, $this->normalize($data, $resource));
    }

    public function delete(CareSupportResource $resource): void
    {
        $this->repository->delete($resource);
    }

    public function publicByCategory(string $category, ?string $locale = null): array
    {
        return $this->repository
            ->activeByCategory($category)
            ->map(fn (CareSupportResource $r) => $r->toPublicArray($locale ?? 'en'))
            ->values()
            ->all();
    }

    private function normalize(array $data, ?CareSupportResource $existing = null): array
    {
        $category = $data['category'] ?? $existing?->category ?? 'hotline';

        $sortOrder = array_key_exists('sort_order', $data)
            ? (int) $data['sort_order']
            : ($existing?->sort_order ?? $this->repository->nextSortOrder($category));

        return [
            'category'       => $category,
            'title_en'       => trim((string) ($data['title_en'] ?? $existing?->title_en ?? '')),
            'title_tl'       => trim((string) ($data['title_tl'] ?? $existing?->title_tl ?? '')),
            'role_en'        => $this->nullableString($data['role_en'] ?? $existing?->role_en),
            'role_tl'        => $this->nullableString($data['role_tl'] ?? $existing?->role_tl),
            'description_en' => $this->nullableString($data['description_en'] ?? $existing?->description_en),
            'description_tl' => $this->nullableString($data['description_tl'] ?? $existing?->description_tl),
            'meta_en'        => $this->nullableString($data['meta_en'] ?? $existing?->meta_en),
            'meta_tl'        => $this->nullableString($data['meta_tl'] ?? $existing?->meta_tl),
            'phone'          => $this->nullableString($data['phone'] ?? $existing?->phone),
            'web_url'        => $this->nullableString($data['web_url'] ?? $existing?->web_url),
            'logo_url'       => $this->nullableString($data['logo_url'] ?? $existing?->logo_url),
            'logo_initial'   => $this->nullableString($data['logo_initial'] ?? $existing?->logo_initial),
            'icon_key'       => $this->nullableString($data['icon_key'] ?? $existing?->icon_key),
            'body_en'        => $this->normalizeBody($data['body_en'] ?? $existing?->body_en),
            'body_tl'        => $this->normalizeBody($data['body_tl'] ?? $existing?->body_tl),
            'is_emergency'   => array_key_exists('is_emergency', $data)
                ? (bool) $data['is_emergency']
                : ($existing?->is_emergency ?? false),
            'sort_order'     => max(0, $sortOrder),
            'is_active'      => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($existing?->is_active ?? true),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeBody(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

            return $lines === [] ? null : $lines;
        }

        if (is_array($value)) {
            $lines = array_values(array_filter(array_map(
                fn ($l) => trim((string) $l),
                $value,
            ), fn ($l) => $l !== ''));

            return $lines === [] ? null : $lines;
        }

        return null;
    }
}
