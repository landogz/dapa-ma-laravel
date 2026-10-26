<?php

namespace Tests\Feature;

use App\Models\AppOption;
use App\Services\AppOptionService;
use Database\Seeders\AppOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppOptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_includes_persona_multiple_and_journal_impact(): void
    {
        $this->seed(AppOptionSeeder::class);

        $response = $this->getJson('/api/v1/app-options');

        $response->assertOk()
            ->assertJsonPath('status', true);

        $groups = collect($response->json('data'));
        $this->assertTrue($groups->pluck('key')->contains('persona'));
        $this->assertTrue($groups->pluck('key')->contains('journal_impact'));

        $persona = $groups->firstWhere('key', 'persona');
        $this->assertSame('multiple', $persona['selection'] ?? null);
        $this->assertNotEmpty($persona['options'] ?? []);

        $impact = $groups->firstWhere('key', 'journal_impact');
        $keys = collect($impact['options'] ?? [])->pluck('key');
        $this->assertTrue($keys->contains('connection_circle'));
        $this->assertTrue($keys->contains('cravings_urges'));
    }

    public function test_public_sky_option_includes_accent(): void
    {
        $this->seed(AppOptionSeeder::class);

        $response = $this->getJson('/api/v1/app-options');
        $sky = collect($response->json('data'))->firstWhere('key', 'journal_sky');
        $red = collect($sky['options'] ?? [])->firstWhere('key', 'sky_red');

        $this->assertSame('#CE2028', $red['accent'] ?? null);
        $this->assertSame('Red', $red['label_en'] ?? null);
    }

    public function test_admin_lookups_page_is_labeled_lookups(): void
    {
        $this->get('/admin/lookups')
            ->assertOk()
            ->assertSee('Lookups')
            ->assertDontSee('Admin Settings');
    }

    public function test_legacy_admin_settings_url_redirects_to_lookups(): void
    {
        $this->get('/admin/app-options')
            ->assertRedirect('/admin/lookups');
    }

    public function test_accent_color_is_normalized_to_hex(): void
    {
        $this->seed(AppOptionSeeder::class);

        $option = AppOption::query()
            ->where('group', 'journal_sky')
            ->where('key', 'sky_red')
            ->firstOrFail();

        $updated = app(AppOptionService::class)->update($option, [
            'label_en' => $option->label_en,
            'label_tl' => $option->label_tl,
            'accent_color' => 'ce2028',
        ]);

        $this->assertSame('#CE2028', $updated->accent_color);
    }
}
