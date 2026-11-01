<?php

namespace Database\Seeders;

use App\Models\Sticker;
use App\Models\StickerPack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StickerLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $sourceDir = base_path('../dape-ma-flutter/dape_ma_mobile/assets/stickers/kid-listo');
        if (! is_dir($sourceDir)) {
            $sourceDir = base_path('storage/app/seed-stickers/kid-listo');
        }

        $pack = StickerPack::query()->updateOrCreate(
            ['slug' => 'kid-listo'],
            [
                'name' => 'Kid Listo',
                'description' => 'Official Kid Listo sticker pack for comment reactions.',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        if (! is_dir($sourceDir)) {
            $this->command?->warn('Kid Listo sticker assets not found; pack created without images.');

            return;
        }

        $files = collect(File::files($sourceDir))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp'], true))
            ->sortBy(fn ($file) => (int) pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->values();

        foreach ($files as $index => $file) {
            $destRelative = 'stickers/items/kid-listo-'.Str::slug(pathinfo($file->getFilename(), PATHINFO_FILENAME)).'.'.$file->getExtension();
            Storage::disk('public')->put($destRelative, File::get($file->getPathname()));

            Sticker::query()->updateOrCreate(
                [
                    'sticker_pack_id' => $pack->id,
                    'name' => 'Kid Listo '.($index + 1),
                ],
                [
                    'image_path' => $destRelative,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        $first = $pack->stickers()->orderBy('sort_order')->orderBy('id')->first();
        if ($first && ! $pack->cover_path) {
            $extension = pathinfo($first->image_path, PATHINFO_EXTENSION) ?: 'png';
            $coverRelative = 'stickers/packs/kid-listo-cover.'.$extension;
            Storage::disk('public')->put($coverRelative, Storage::disk('public')->get($first->image_path));
            $pack->update(['cover_path' => $coverRelative]);
        }

        $this->command?->info('Seeded Kid Listo sticker pack with '.$files->count().' stickers.');
    }
}
