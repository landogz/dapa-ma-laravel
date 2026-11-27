<?php

namespace App\Repositories;

class AppUpdateRepository
{
    public function readManifest(): ?array
    {
        $path = public_path('download/dape-ma-version.json');
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function apkExists(): bool
    {
        return is_file(public_path('download/dape-ma.apk'));
    }

    /**
     * @param  array{version:string,build:int,mandatory?:bool,message?:string}  $manifest
     */
    public function writeManifest(array $manifest): void
    {
        $path = public_path('download/dape-ma-version.json');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode([
                'version' => (string) $manifest['version'],
                'build' => (int) $manifest['build'],
                'mandatory' => (bool) ($manifest['mandatory'] ?? false),
                'message' => (string) ($manifest['message'] ?? 'There is a new update with the app. Please update.'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
