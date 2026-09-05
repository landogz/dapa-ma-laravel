<?php

namespace Database\Seeders;

use App\Models\IecMaterial;
use Illuminate\Database\Seeder;

class IecMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'title' => 'Say No to Drugs — Animated Spot',
                'description' => 'Short animated reminder for youth: choose health, friendship, and a drug-free future.',
                'topic' => 'Youth',
                'media_type' => 'gif',
                'media_url' => 'https://media.giphy.com/media/3o7aCTPPm4OHfRLSH6/giphy.gif',
                'thumbnail_url' => 'https://media.giphy.com/media/3o7aCTPPm4OHfRLSH6/giphy.gif',
                'sort_order' => 1,
            ],
            [
                'title' => 'Family Support Matters',
                'description' => 'Animated IEC on listening, encouragement, and early help within the family.',
                'topic' => 'Family',
                'media_type' => 'gif',
                'media_url' => 'https://media.giphy.com/media/l0MYt5jPR6QX5pnqM/giphy.gif',
                'thumbnail_url' => 'https://media.giphy.com/media/l0MYt5jPR6QX5pnqM/giphy.gif',
                'sort_order' => 2,
            ],
            [
                'title' => 'Prevention Starts With Awareness',
                'description' => 'Looping awareness graphic for community IEC caravans and school talks.',
                'topic' => 'Prevention',
                'media_type' => 'gif',
                'media_url' => 'https://media.giphy.com/media/xT9IgG50Fb7Mi0prBC/giphy.gif',
                'thumbnail_url' => 'https://media.giphy.com/media/xT9IgG50Fb7Mi0prBC/giphy.gif',
                'sort_order' => 3,
            ],
            [
                'title' => 'Recovery Is Possible',
                'description' => 'Encouraging animated message for persons who use drugs and their support network.',
                'topic' => 'Recovery',
                'media_type' => 'gif',
                'media_url' => 'https://media.giphy.com/media/26BRv0ThflsHCqDrG/giphy.gif',
                'thumbnail_url' => 'https://media.giphy.com/media/26BRv0ThflsHCqDrG/giphy.gif',
                'sort_order' => 4,
            ],
            [
                'title' => 'DDB Advocacy Video Clip',
                'description' => 'Sample YouTube advocacy clip for IEC screenings and community orientations.',
                'topic' => 'Awareness',
                'media_type' => 'youtube',
                'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'sort_order' => 5,
            ],
            [
                'title' => 'Be a Drug-Free Champion',
                'description' => 'Bright animated call-to-action for peer educators and campus advocates.',
                'topic' => 'Youth',
                'media_type' => 'gif',
                'media_url' => 'https://media.giphy.com/media/3orieUeweejRtUxqJE/giphy.gif',
                'thumbnail_url' => 'https://media.giphy.com/media/3orieUeweejRtUxqJE/giphy.gif',
                'sort_order' => 6,
            ],
        ];

        foreach ($items as $item) {
            IecMaterial::updateOrCreate(
                ['title' => $item['title']],
                array_merge($item, ['is_active' => true]),
            );
        }
    }
}
