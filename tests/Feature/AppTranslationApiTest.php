<?php

namespace Tests\Feature;

use App\Models\AppTranslation;
use Database\Seeders\AppTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_bundle_includes_mobile_labels_and_excludes_bible(): void
    {
        $this->seed(AppTranslationSeeder::class);

        $response = $this->getJson('/api/v1/translations?locale=en');

        $response->assertOk()
            ->assertJsonPath('status', true);

        $strings = $response->json('data.strings');
        $this->assertIsArray($strings);
        $this->assertArrayHasKey('careTitle', $strings);
        $this->assertArrayHasKey('hopeTitle', $strings);
        $this->assertArrayHasKey('journalSkyTitle', $strings);
        $this->assertArrayHasKey('navHome', $strings);
        $this->assertArrayNotHasKey('bibleTitle', $strings);
        $this->assertArrayNotHasKey('searchBibleBooks', $strings);
    }

    public function test_seeder_removes_bible_group(): void
    {
        AppTranslation::query()->create([
            'key' => 'bibleTitle',
            'group' => 'bible',
            'value_en' => 'Bible',
            'value_tl' => 'Bibliya',
            'is_active' => true,
        ]);

        $this->seed(AppTranslationSeeder::class);

        $this->assertFalse(
            AppTranslation::query()->where('key', 'bibleTitle')->exists()
        );
        $this->assertFalse(
            AppTranslation::query()->where('group', 'bible')->exists()
        );
    }

    public function test_admin_translations_page_uses_all_labels(): void
    {
        $this->get('/admin/app-translations')
            ->assertOk()
            ->assertSee('All labels')
            ->assertSee('Change every English and Tagalog label');
    }
}
