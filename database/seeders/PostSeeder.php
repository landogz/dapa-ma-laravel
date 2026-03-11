<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $editor = User::query()->where('role', 'editor')->first();

        if (! $editor) {
            return;
        }

        $categories = Category::all();

        if ($categories->isEmpty()) {
            return;
        }

        $statuses = ['draft', 'pending_review', 'scheduled', 'published'];

        foreach (range(1, 12) as $i) {
            $title = "Sample Post {$i} for DAPE-MA";

            Post::query()->updateOrCreate(
                ['title' => $title],
                [
                    'body' => "This is sample content body for {$title}. It demonstrates the DAPE-MA editorial workflow.",
                    'category_id' => $categories->random()->id,
                    'media_url' => null,
                    'youtube_url' => $i % 3 === 0 ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null,
                    'status' => Arr::random($statuses),
                    'review_notes' => $i % 4 === 0 ? 'Editorial review notes for demonstration.' : null,
                    'publish_date' => now()->subDays(random_int(0, 10))->addDays(random_int(0, 10)),
                    'author_id' => $editor->id,
                ],
            );
        }
    }
}

