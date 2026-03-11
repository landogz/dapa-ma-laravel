<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\RehabCenter;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('role', 'app_user')->first();

        if (! $user) {
            return;
        }

        $posts = Post::query()->where('status', 'published')->inRandomOrder()->take(3)->get();
        $centers = RehabCenter::all();

        foreach ($posts as $post) {
            Review::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'target_id' => $post->id,
                    'target_type' => Post::class,
                ],
                [
                    'rating' => random_int(3, 5),
                    'comment' => 'Sample review for seeded post content.',
                ],
            );
        }

        foreach ($centers as $center) {
            Review::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'target_id' => $center->id,
                    'target_type' => RehabCenter::class,
                ],
                [
                    'rating' => random_int(3, 5),
                    'comment' => 'Sample review for seeded rehabilitation center.',
                ],
            );
        }
    }
}

