<?php

namespace App\Services;

use App\Models\HopeDirectoryOrganization;
use App\Repositories\HopeDirectoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HopeDirectoryService
{
    public function __construct(
        private readonly HopeDirectoryRepository $repository,
    ) {
    }

    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
        bool $activeOnly = true,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $category, $activeOnly);
    }

    public function create(array $data): HopeDirectoryOrganization
    {
        $payload = $this->normalize($data);
        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $payload['logo_path'] = $data['logo']->store('hope-directory', 'public');
        }

        return $this->repository->create($payload);
    }

    public function update(HopeDirectoryOrganization $org, array $data): HopeDirectoryOrganization
    {
        $payload = $this->normalize($data, partial: true);

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $this->deleteLogo($org->logo_path);
            $payload['logo_path'] = $data['logo']->store('hope-directory', 'public');
        } elseif (!empty($data['remove_logo'])) {
            $this->deleteLogo($org->logo_path);
            $payload['logo_path'] = null;
        }

        return $this->repository->update($org, $payload);
    }

    public function delete(HopeDirectoryOrganization $org): void
    {
        $this->deleteLogo($org->logo_path);
        $this->repository->delete($org);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        $fields = ['name', 'description', 'category', 'address', 'phone', 'email', 'sort_order', 'is_active'];
        $payload = [];

        foreach ($fields as $field) {
            if (!$partial || array_key_exists($field, $data)) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }
        }

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('sort_order', $payload)) {
            $payload['sort_order'] = (int) $payload['sort_order'];
        }

        return $payload;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
