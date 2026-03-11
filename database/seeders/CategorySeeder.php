<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed default post categories.
     */
    public function run(): void
    {
        $categories = [
            'Drug Effects',
            'Rehabilitation',
            'Prevention',
            'News',
            'Legal',
        ];

        foreach ($categories as $categoryName) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName],
            );
        }
    }
}
