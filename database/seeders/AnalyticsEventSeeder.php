<?php

namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AnalyticsEventSeeder extends Seeder
{
    public function run(): void
    {
        $posts = Post::query()->where('status', 'published')->get();

        if ($posts->isEmpty()) {
            return;
        }

        $user = User::query()->where('role', 'app_user')->first();

        foreach ($posts as $post) {
            foreach (range(1, 5) as $i) {
                AnalyticsEvent::query()->create([
                    'event_type' => 'view',
                    'post_id' => $post->id,
                    'user_id' => $user?->id,
                    'session_id' => Str::uuid()->toString(),
                ]);
            }
        }
    }
}

