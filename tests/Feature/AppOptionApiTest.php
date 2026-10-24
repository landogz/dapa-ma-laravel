<?php

namespace Tests\Feature;

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
}
