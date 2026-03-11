<?php

namespace App\Repositories;

use App\Models\Post;
use App\Models\RehabCenter;
use Illuminate\Support\Collection;

class SearchRepository
{
    public function search(string $query, ?string $category = null, int $limit = 10): array
    {
        $postsQuery = Post::query()
            ->where('status', 'published')
            ->where(function ($q) use ($query): void {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('body', 'like', "%{$query}%");
            })
            ->when($category !== null && $category !== '' && $category !== 'all', function ($q) use ($category): void {
                $q->whereHas('category', function ($categoryQuery) use ($category): void {
                    $categoryQuery->where('slug', $category);
                });
            })
            ->latest()
            ->limit($limit);

        $posts = $postsQuery->get();

        $rehabCenters = RehabCenter::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query): void {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('province', 'like', "%{$query}%")
                    ->orWhere('address', 'like', "%{$query}%");
            })
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'posts'         => $posts,
            'rehab_centers' => $rehabCenters,
        ];
    }

    public function suggest(string $query, int $limit = 5): Collection
    {
        return Post::query()
            ->where('status', 'published')
            ->where('title', 'like', "%{$query}%")
            ->latest()
            ->limit($limit)
            ->pluck('title');
    }
}

