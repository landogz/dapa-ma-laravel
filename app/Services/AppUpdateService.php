<?php

namespace App\Services;

use App\Repositories\AppUpdateRepository;

class AppUpdateService
{
    public function __construct(
        private readonly AppUpdateRepository $repository,
    ) {
    }

    /**
     * @return array{
     *     available: bool,
     *     version: string,
     *     build: int,
     *     mandatory: bool,
     *     apk_url: string,
     *     message: string
     * }
     */
    public function current(): array
    {
        $manifest = $this->repository->readManifest() ?? [];
        $version = trim((string) ($manifest['version'] ?? config('dape.mobile.version', '1.0.0')));
        $build = (int) ($manifest['build'] ?? config('dape.mobile.build', 1));
        $message = trim((string) ($manifest['message'] ?? ''));
        if ($message === '') {
            $message = 'There is a new update with the app. Please update.';
        }

        return [
            'available' => $this->repository->apkExists() && $version !== '' && $build > 0,
            'version' => $version !== '' ? $version : '1.0.0',
            'build' => $build > 0 ? $build : 1,
            'mandatory' => (bool) ($manifest['mandatory'] ?? false),
            'apk_url' => rtrim(request()->getSchemeAndHttpHost(), '/').'/download/dape-ma.apk',
            'message' => $message,
        ];
    }
}
