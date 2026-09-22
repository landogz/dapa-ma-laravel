<?php

namespace Database\Seeders;

use App\Services\AppTranslationService;
use Illuminate\Database\Seeder;

class AppTranslationSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/app_translations.json');
        $count = app(AppTranslationService::class)->seedFromJson($path);

        $this->command?->info("Seeded {$count} app translation keys from mobile AppStrings.");
    }
}
