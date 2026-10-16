<?php

namespace App\Services;

use App\Support\ShareCard;
use Illuminate\Support\Facades\File;

class ShareOgImageService
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const VERSION = 'v2';

    public function pathFor(string $type, ShareCard $card): string
    {
        $dest = $this->cachePath($type, $card->uuid !== '' ? $card->uuid : (string) $card->id, $card->imageSrc);
        if (! is_file($dest)) {
            $this->renderToFile($card->imageSrc, $dest);
        }

        return $dest;
    }

    public function renderToFile(string $sourceSrc, string $destPath): void
    {
        File::ensureDirectoryExists(dirname($destPath));

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($canvas === false) {
            throw new \RuntimeException('Unable to create Open Graph canvas.');
        }

        $this->fillBrandGradient($canvas);

        $source = $this->loadSource($sourceSrc);
        if ($source instanceof \GdImage) {
            $this->paintContained($canvas, $source);
            imagedestroy($source);
        }

        imagejpeg($canvas, $destPath, 86);
        imagedestroy($canvas);
    }

    private function cachePath(string $type, string $key, string $sourceSrc): string
    {
        $stamp = '0';
        $local = $this->localPath($sourceSrc);
        if ($local && is_file($local)) {
            $stamp = (string) filemtime($local);
        }

        return storage_path('app/og/'.self::VERSION.'-'.$type.'-'.$id.'-'.$stamp.'.jpg');
    }

    private function fillBrandGradient(\GdImage $canvas): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / (self::HEIGHT - 1);
            $color = imagecolorallocate(
                $canvas,
                (int) round(5 + (18 - 5) * $t),
                (int) round(84 + (58 - 84) * $t),
                (int) round(152 + (96 - 152) * $t),
            );
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }
    }

    private function paintContained(\GdImage $canvas, \GdImage $source): void
    {
        $sw = imagesx($source);
        $sh = imagesy($source);
        if ($sw < 1 || $sh < 1) {
            return;
        }

        $padX = 56;
        $padY = 48;
        $innerW = self::WIDTH - ($padX * 2);
        $innerH = self::HEIGHT - ($padY * 2);
        $scale = min($innerW / $sw, $innerH / $sh);
        $dw = max(1, (int) round($sw * $scale));
        $dh = max(1, (int) round($sh * $scale));
        $dx = (int) round((self::WIDTH - $dw) / 2);
        $dy = (int) round((self::HEIGHT - $dh) / 2);

        imagecopyresampled($canvas, $source, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    }

    private function loadSource(string $src): ?\GdImage
    {
        $bytes = $this->readBytes($src);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        return $image instanceof \GdImage ? $image : null;
    }

    private function readBytes(string $src): ?string
    {
        $local = $this->localPath($src);
        if ($local && is_file($local)) {
            $bytes = file_get_contents($local);

            return $bytes === false ? null : $bytes;
        }

        if (! str_starts_with($src, 'https://') && ! str_starts_with($src, 'http://')) {
            return null;
        }

        $context = stream_context_create([
            'http' => ['timeout' => 8, 'follow_location' => 1],
            'ssl' => ['verify_peer' => true],
        ]);
        $bytes = @file_get_contents($src, false, $context);

        return $bytes === false ? null : $bytes;
    }

    private function localPath(string $src): ?string
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = str_starts_with($src, '/') ? $src : '/'.$src;
        }

        $path = rawurldecode($path);
        if (str_starts_with($path, '/storage/')) {
            return storage_path('app/public/'.ltrim(substr($path, 9), '/'));
        }

        if (str_starts_with($path, '/')) {
            return public_path(ltrim($path, '/'));
        }

        return null;
    }
}
