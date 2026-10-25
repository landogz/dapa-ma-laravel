<?php

namespace App\Repositories\BlockedWord;

use App\Models\BlockedWord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BlockedWordRepository
{
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return BlockedWord::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('word', 'like', '%'.$search.'%');
            })
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, function ($query) use ($filters) {
                $query->where('is_active', (bool) $filters['is_active']);
            })
            ->orderBy('word')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): BlockedWord
    {
        return BlockedWord::query()->findOrFail($id);
    }

    public function create(array $data): BlockedWord
    {
        return BlockedWord::query()->create($data);
    }

    public function update(BlockedWord $word, array $data): BlockedWord
    {
        $word->update($data);

        return $word->fresh();
    }

    public function delete(BlockedWord $word): void
    {
        $word->delete();
    }

    /**
     * @return Collection<int, string>
     */
    public function activeWords(): Collection
    {
        return BlockedWord::query()
            ->where('is_active', true)
            ->orderBy('word')
            ->pluck('word');
    }

    public function catalogVersion(): string
    {
        $latest = BlockedWord::query()->max('updated_at');

        return $latest ? (string) $latest : now()->toIso8601String();
    }

    public function statusCounts(): array
    {
        return [
            '' => BlockedWord::query()->count(),
            '1' => BlockedWord::query()->where('is_active', true)->count(),
            '0' => BlockedWord::query()->where('is_active', false)->count(),
        ];
    }
}
