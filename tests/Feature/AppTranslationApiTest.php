<?php

namespace Tests\Feature;

use App\Models\AppTranslation;
use App\Services\AppTranslationService;
use Database\Seeders\AppTranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_translations_page_is_grouped(): void
    {
        $this->get('/admin/app-translations')
            ->assertOk()
            ->assertSee('Edit by group')
            ->assertSee('Choose a group, then edit its English and Tagalog labels.')
            ->assertDontSee('All labels');
    }

    public function test_group_counts_exclude_bible(): void
    {
        $this->seed(AppTranslationSeeder::class);

        $counts = app(AppTranslationService::class)->groupCounts();

        $this->assertFalse($counts->has('bible'));
        $this->assertGreaterThan(0, (int) $counts->get('navigation'));
        $this->assertGreaterThan(0, (int) $counts->get('care'));
    }

    public function test_public_bundle_includes_uploaded_image_and_icon(): void
    {
        Storage::fake('public');

        $translation = AppTranslation::query()->create([
            'key' => 'careSpeedJournal',
            'group' => 'care',
            'value_en' => 'My Journal',
            'value_tl' => 'Aking Journal',
            'is_active' => true,
        ]);

        app(AppTranslationService::class)->update($translation, [
            'value_en' => 'My Journal',
            'value_tl' => 'Aking Journal',
            'is_active' => true,
            'icon' => 'menu_book_rounded',
            'image' => UploadedFile::fake()->image('journal.png', 80, 80),
        ]);

        $response = $this->getJson('/api/v1/translations?locale=en')->assertOk();
        $asset = $response->json('data.assets.careSpeedJournal');

        $this->assertIsArray($asset);
        $this->assertSame('menu_book_rounded', $asset['icon']);
        $this->assertNotEmpty($asset['image_url']);
        $this->assertStringContainsString('app-translations', $asset['image_url']);
    }

    public function test_bottom_menu_keys_are_marked_as_visuals(): void
    {
        $this->assertContains('navHome', \App\Support\AppTranslation\Visual::MENU_KEYS);
        $this->assertContains('navCare', \App\Support\AppTranslation\Visual::visualKeys());
        $this->assertSame('menu', \App\Support\AppTranslation\Visual::visualKind('navFaq'));
    }

    public function test_kid_listo_logo_is_a_visual_logo(): void
    {
        $this->assertContains('kidListoLogo', \App\Support\AppTranslation\Visual::LOGO_KEYS);
        $this->assertContains('kidListoLogo', \App\Support\AppTranslation\Visual::visualKeys());
        $this->assertSame('logo', \App\Support\AppTranslation\Visual::visualKind('kidListoLogo'));
    }

    public function test_settings_title_is_a_page_visual(): void
    {
        $this->assertContains('settingsTitle', \App\Support\AppTranslation\Visual::PAGE_KEYS);
        $this->assertSame('page', \App\Support\AppTranslation\Visual::visualKind('settingsTitle'));
        $this->assertSame('page', \App\Support\AppTranslation\Visual::visualKind('hopeTitle'));
        $this->assertSame('card', \App\Support\AppTranslation\Visual::visualKind('bidaLearnTitle'));
    }

    public function test_seeder_includes_kid_listo_logo(): void
    {
        $this->seed(AppTranslationSeeder::class);

        $row = AppTranslation::query()->where('key', 'kidListoLogo')->first();

        $this->assertNotNull($row);
        $this->assertSame('kid_listo', $row->group);
        $this->assertSame('Kid Listo', $row->value_en);
    }

    public function test_bottom_menu_keys_are_in_the_navigation_group(): void
    {
        $this->seed(AppTranslationSeeder::class);

        foreach (['navHome', 'navHope', 'navFaq', 'navCare', 'navAccount'] as $key) {
            $this->assertSame(
                'navigation',
                AppTranslation::query()->where('key', $key)->value('group'),
                $key,
            );
        }
    }
}
