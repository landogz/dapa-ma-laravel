<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed default post categories.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Drug Effects', 'slug' => 'drug-effects'],
            ['name' => 'Rehabilitation', 'slug' => 'rehabilitation'],
            ['name' => 'Prevention', 'slug' => 'prevention'],
            ['name' => 'Information, Education, and Communication (IEC)', 'slug' => 'iec'],
            ['name' => 'News', 'slug' => 'news'],
            ['name' => 'Legal', 'slug' => 'legal'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']],
            );
        }
    }
}
