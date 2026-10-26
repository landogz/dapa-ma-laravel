<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostFeaturedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_a_post_as_featured(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::query()->create([
            'name' => 'News',
            'slug' => 'news',
        ]);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/admin/posts', [
            'title' => 'Featured story',
            'body' => 'A highlighted story for the app carousel.',
            'category_id' => $category->id,
            'is_featured' => true,
        ])->assertCreated();

        $this->assertTrue((bool) $created->json('data.is_featured'));
        $this->assertDatabaseHas('posts', [
            'title' => 'Featured story',
            'is_featured' => 1,
        ]);
    }

    public function test_published_featured_posts_are_listed_first(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $category = Category::query()->create([
            'name' => 'News',
            'slug' => 'news',
        ]);

        Post::query()->create([
            'title' => 'Regular post',
            'body' => 'Not featured',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'is_featured' => false,
            'publish_date' => now()->subHour(),
        ]);

        Post::query()->create([
            'title' => 'Featured post',
            'body' => 'Should appear first',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'is_featured' => true,
            'publish_date' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/posts')->assertOk();
        $titles = collect($response->json('data.data'))->pluck('title')->all();

        $this->assertSame('Featured post', $titles[0] ?? null);
        $this->assertTrue((bool) $response->json('data.data.0.is_featured'));
    }

    public function test_featured_filter_returns_only_featured_posts(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $category = Category::query()->create([
            'name' => 'News',
            'slug' => 'news',
        ]);

        Post::query()->create([
            'title' => 'Regular post',
            'body' => 'Not featured',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'is_featured' => false,
            'publish_date' => now(),
        ]);

        Post::query()->create([
            'title' => 'Carousel post',
            'body' => 'Featured',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'published',
            'is_featured' => true,
            'publish_date' => now(),
        ]);

        $response = $this->getJson('/api/v1/posts?featured=1')->assertOk();
        $titles = collect($response->json('data.data'))->pluck('title')->all();

        $this->assertSame(['Carousel post'], $titles);
    }
}
