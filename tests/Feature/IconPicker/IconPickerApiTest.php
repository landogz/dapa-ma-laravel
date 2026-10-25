<?php

namespace Tests\Feature\IconPicker;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IconPickerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_iconsets_and_svg_icons(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/icon-picker/iconsets')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.0.uri', 'outline')
            ->assertJsonPath('data.1.uri', 'solid')
            ->assertJsonPath('data.2.uri', 'app');

        $response = $this->getJson('/api/v1/admin/icon-picker/iconsets/outline');
        $response->assertOk()->assertJsonPath('status', true);

        $icons = $response->json('data');
        $this->assertIsArray($icons);
        $this->assertGreaterThanOrEqual(300, count($icons));
        $this->assertArrayHasKey('name', $icons[0]);
        $this->assertArrayHasKey('contents', $icons[0]);
        $this->assertStringContainsString('<svg', $icons[0]['contents']);
        $this->assertTrue(collect($icons)->contains(fn (array $icon) => $icon['name'] === 'academic-cap'));

        $appIcons = $this->getJson('/api/v1/admin/icon-picker/iconsets/app')->json('data');
        $this->assertTrue(collect($appIcons)->contains(fn (array $icon) => $icon['name'] === 'school_outlined'));
    }

    public function test_unknown_iconset_is_not_found(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/icon-picker/iconsets/missing')
            ->assertNotFound();
    }
}
