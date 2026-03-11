<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $sender = User::query()->whereIn('role', ['publisher', 'super_admin'])->inRandomOrder()->first();

        if (! $sender) {
            return;
        }

        $posts = Post::query()->where('status', 'published')->inRandomOrder()->take(3)->get();

        foreach ($posts as $post) {
            Notification::query()->firstOrCreate(
                [
                    'title' => "New post: {$post->title}",
                    'post_id' => $post->id,
                ],
                [
                    'body' => 'Sample push notification seeded for DAPE-MA.',
                    'topic' => 'all-users',
                    'sent_by' => $sender->id,
                    'sent_at' => now()->subMinutes(random_int(5, 120)),
                ],
            );
        }
    }
}

