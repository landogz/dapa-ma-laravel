<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            DefaultAccountsSeeder::class,
            RehabCenterSeeder::class,
            PostSeeder::class,
            BookmarkSeeder::class,
            NotificationSeeder::class,
            ReviewSeeder::class,
            AnalyticsEventSeeder::class,
        ]);
    }
}
