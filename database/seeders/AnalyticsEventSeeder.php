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

        AnalyticsEvent::query()->delete();

        $user = User::query()->where('role', 'app_user')->first();
        $platforms = ['android', 'ios', 'web'];
        $eventTypes = ['post_view', 'post_view', 'post_view', 'bookmark', 'search', 'share'];

        foreach ($posts as $post) {
            foreach (range(1, 5) as $index) {
                AnalyticsEvent::query()->create([
                    'event_type' => $eventTypes[array_rand($eventTypes)],
                    'post_id'    => $post->id,
                    'user_id'    => $user?->id,
                    'session_id' => Str::uuid()->toString(),
                    'platform'   => $platforms[array_rand($platforms)],
                    'created_at' => now()->subDays(random_int(0, 6))->subHours(random_int(0, 23)),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
