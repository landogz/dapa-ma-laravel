<?php

namespace App\Repositories;

use App\Models\KidListoQuote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KidListoQuoteRepository
{
    public function paginate(int $perPage = 50, ?string $search = null): LengthAwarePaginator
    {
        return KidListoQuote::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('message_en', 'like', "%{$search}%")
                        ->orWhere('message_tl', 'like', "%{$search}%")
                        ->orWhere('attribution', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): KidListoQuote
    {
        return KidListoQuote::query()->findOrFail($id);
    }

    public function create(array $data): KidListoQuote
    {
        return KidListoQuote::query()->create($data);
    }

    public function update(KidListoQuote $quote, array $data): KidListoQuote
    {
        $quote->update($data);

        return $quote->fresh();
    }

    public function delete(KidListoQuote $quote): void
    {
        $quote->delete();
    }

    public function randomActive(): ?KidListoQuote
    {
        return KidListoQuote::query()
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
    }
}
