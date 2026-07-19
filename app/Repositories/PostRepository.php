<?php

namespace App\Repositories;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class PostRepository
{
    public function paginateAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return Post::query()
            ->with(['category', 'author'])
            ->withCount(['likes', 'comments', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate($perPage);
    }

    public function findAdminDetail(int $id): Post
    {
        return Post::query()
            ->with([
                'category',
                'author',
                'likes' => fn ($query) => $query
                    ->with('user:id,name,email')
                    ->latest(),
                'comments' => fn ($query) => $query
                    ->with('user:id,name,email')
                    ->orderBy('created_at'),
                'reviews' => fn ($query) => $query
                    ->with('user:id,name,email')
                    ->latest(),
            ])
            ->withCount(['likes', 'comments', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->findOrFail($id);
    }

    public function paginatePublished(int $perPage = 20, ?string $categorySlug = null): LengthAwarePaginator
    {
        $query = Post::query()
            ->with(['category', 'author'])
            ->withCount(['likes', 'comments', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->where('status', 'published')
            ->where('publish_date', '<=', Carbon::now())
            ->latest('publish_date');

        if ($categorySlug !== null && $categorySlug !== '') {
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        return $query->paginate($perPage);
    }

    public function selectionList(): Collection
    {
        return Post::query()
            ->select(['id', 'title', 'status', 'publish_date'])
            ->where('status', 'published')
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->get();
    }

    public function findOrFail(int $id): Post
    {
        return Post::with(['category', 'author'])
            ->withCount(['likes', 'comments', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->findOrFail($id);
    }

    public function create(array $data): Post
    {
        return Post::create($data);
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);

        return $post->fresh(['category', 'author']);
    }

    public function countDueForPublish(): int
    {
        return Post::query()
            ->where('status', 'scheduled')
            ->where('publish_date', '<=', Carbon::now())
            ->count();
    }

    public function publishDue(): int
    {
        return Post::query()
            ->where('status', 'scheduled')
            ->where('publish_date', '<=', Carbon::now())
            ->update(['status' => 'published']);
    }
}
