<?php

namespace App\Services;

use App\Models\KidListoQuote;
use App\Repositories\KidListoQuoteRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KidListoQuoteService
{
    public function __construct(
        private readonly KidListoQuoteRepository $kidListoQuoteRepository,
    ) {
    }

    public function list(int $perPage = 50, ?string $search = null): LengthAwarePaginator
    {
        return $this->kidListoQuoteRepository->paginate($perPage, $search);
    }

    public function find(int $id): KidListoQuote
    {
        return $this->kidListoQuoteRepository->findOrFail($id);
    }

    public function create(array $data): KidListoQuote
    {
        return $this->kidListoQuoteRepository->create($this->normalize($data));
    }

    public function update(KidListoQuote $quote, array $data): KidListoQuote
    {
        return $this->kidListoQuoteRepository->update($quote, $this->normalize($data));
    }

    public function delete(KidListoQuote $quote): void
    {
        $this->kidListoQuoteRepository->delete($quote);
    }

    public function randomPublic(?string $locale = null): ?array
    {
        $quote = $this->kidListoQuoteRepository->randomActive();

        return $quote ? $quote->toPublicArray($locale ?? 'en') : null;
    }

    private function normalize(array $data): array
    {
        return [
            'message_en' => trim((string) ($data['message_en'] ?? '')),
            'message_tl' => trim((string) ($data['message_tl'] ?? '')),
            'attribution' => filled($data['attribution'] ?? null)
                ? trim((string) $data['attribution'])
                : null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : true,
        ];
    }
}
