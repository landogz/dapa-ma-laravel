<?php

namespace App\Services;

use App\Models\HopeDirectoryOrganization;
use App\Models\User;
use App\Repositories\HopeDirectoryRepository;
use App\Services\ContentWorkflow\ContentWorkflowService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HopeDirectoryService
{
    public function __construct(
        private readonly HopeDirectoryRepository $repository,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    public function list(
        int $perPage = 50,
        ?string $search = null,
        ?string $category = null,
        bool $activeOnly = true,
        ?User $actor = null,
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $category, $activeOnly, $actor);
    }

    public function create(array $data, User $actor): HopeDirectoryOrganization
    {
        $payload = $this->normalize($data);
        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $payload['logo_path'] = $data['logo']->store('hope-directory', 'public');
        }

        $payload = $this->contentWorkflow->prepareCreatePayload($actor, $payload);
        $created = $this->repository->create($payload);
        $this->contentWorkflow->notifyPublishedCreate($created, $actor, 'hope-directory');

        return $created;
    }

    public function update(HopeDirectoryOrganization $org, array $data, User $actor): HopeDirectoryOrganization
    {
        $payload = $this->normalize($data, partial: true);

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $this->deleteLogo($org->logo_path);
            $payload['logo_path'] = $data['logo']->store('hope-directory', 'public');
        } elseif (!empty($data['remove_logo'])) {
            $this->deleteLogo($org->logo_path);
            $payload['logo_path'] = null;
        }

        $payload = $this->contentWorkflow->prepareUpdatePayload($org, $actor, $payload);

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
