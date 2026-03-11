<?php

namespace Database\Seeders;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookmarkSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('role', 'app_user')->first();

        if (! $user) {
            return;
        }

        $posts = Post::query()->inRandomOrder()->take(6)->get();

        foreach ($posts as $post) {
            Bookmark::query()->firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);
        }
    }
}

