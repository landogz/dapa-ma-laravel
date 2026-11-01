<?php

namespace Database\Seeders;

use App\Models\ColorFunWorksheet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ColorFunWorksheetSeeder extends Seeder
{
    public function run(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->command?->warn('GD not available; Color Fun sample worksheets skipped.');

            return;
        }

        $samples = [
            ['title' => 'Love Birds', 'category' => 'nature', 'shape' => 'heart'],
            ['title' => 'Garden', 'category' => 'nature', 'shape' => 'flower'],
            ['title' => 'Cute Ducks', 'category' => 'nature', 'shape' => 'waves'],
            ['title' => 'Heart', 'category' => 'messages', 'shape' => 'heart-fill'],
            ['title' => 'Friends', 'category' => 'people', 'shape' => 'people'],
            ['title' => 'Be Kind', 'category' => 'messages', 'shape' => 'hands'],
        ];

        foreach ($samples as $index => $sample) {
            $relative = 'color-fun/worksheets/sample-'.($index + 1).'-'.$sample['shape'].'.png';
            Storage::disk('public')->put($relative, $this->pngBytes($sample['shape']));

            ColorFunWorksheet::query()->updateOrCreate(
                ['title' => $sample['title'], 'category' => $sample['category']],
                [
                    'image_path' => $relative,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        $this->command?->info('Seeded '.count($samples).' Color Fun worksheets.');
    }

    private function pngBytes(string $shape): string
    {
        $size = 640;
        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $blue = imagecolorallocate($img, 5, 84, 152);
        $light = imagecolorallocate($img, 173, 216, 240);
        imagefilledrectangle($img, 0, 0, $size, $size, $white);
        imagesetthickness($img, 8);

        match ($shape) {
            'flower' => $this->drawFlower($img, $blue, $light, $size),
            'waves' => $this->drawWaves($img, $blue, $size),
            'heart-fill' => $this->drawHeart($img, $blue, $size, true),
            'people' => $this->drawPeople($img, $blue, $size),
            'hands' => $this->drawHands($img, $blue, $size),
            default => $this->drawHeart($img, $blue, $size, false),
        };

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function drawHeart($img, int $color, int $size, bool $filled): void
    {
        $cx = (int) ($size / 2);
        $cy = (int) ($size / 2) - 20;
        $points = [];
        for ($t = 0; $t < 360; $t += 2) {
            $rad = deg2rad($t);
            $x = 16 * pow(sin($rad), 3);
            $y = 13 * cos($rad) - 5 * cos(2 * $rad) - 2 * cos(3 * $rad) - cos(4 * $rad);
            $points[] = (int) ($cx + $x * 12);
            $points[] = (int) ($cy - $y * 12);
        }
        if ($filled) {
            imagefilledpolygon($img, $points, $color);
        } else {
            imagepolygon($img, $points, $color);
        }
    }

    private function drawFlower($img, int $color, int $light, int $size): void
    {
        $cx = (int) ($size / 2);
        $cy = (int) ($size / 2);
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad($i * 60);
            $px = (int) ($cx + cos($angle) * 110);
            $py = (int) ($cy + sin($angle) * 110);
            imageellipse($img, $px, $py, 140, 140, $color);
            imageellipse($img, $px, $py, 120, 120, $light);
        }
        imageellipse($img, $cx, $cy, 90, 90, $color);
        imagefilledellipse($img, $cx, $cy, 40, 40, $light);
    }

    private function drawWaves($img, int $color, int $size): void
    {
        for ($row = 0; $row < 4; $row++) {
            $y = 160 + ($row * 90);
            $prevX = 40;
            $prevY = $y;
            for ($x = 40; $x <= $size - 40; $x += 20) {
                $ny = (int) ($y + sin(($x / 40) + $row) * 28);
                imageline($img, $prevX, $prevY, $x, $ny, $color);
                $prevX = $x;
                $prevY = $ny;
            }
        }
    }

    private function drawPeople($img, int $color, int $size): void
    {
        foreach ([190, 320, 450] as $cx) {
            imageellipse($img, $cx, 210, 90, 90, $color);
            imageline($img, $cx, 255, $cx, 390, $color);
            imageline($img, $cx, 300, $cx - 70, 360, $color);
            imageline($img, $cx, 300, $cx + 70, 360, $color);
            imageline($img, $cx, 390, $cx - 55, 500, $color);
            imageline($img, $cx, 390, $cx + 55, 500, $color);
        }
        unset($size);
    }

    private function drawHands($img, int $color, int $size): void
    {
        imageellipse($img, (int) ($size / 2), (int) ($size / 2), 280, 220, $color);
        for ($i = -2; $i <= 2; $i++) {
            $x = (int) (($size / 2) + ($i * 48));
            imageline($img, $x, (int) ($size / 2) - 90, $x, (int) ($size / 2) - 180, $color);
            imageellipse($img, $x, (int) ($size / 2) - 190, 34, 34, $color);
        }
    }
}
