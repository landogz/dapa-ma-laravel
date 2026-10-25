<?php

namespace App\Services\IconPicker;

use App\Repositories\IconPicker\IconPickerRepository;
use Nevadskiy\Nova\IconPicker\SvgIcon;
use RuntimeException;

class IconPickerService
{
    public function __construct(
        private readonly IconPickerRepository $repository,
    ) {
    }

    /**
     * @return list<array{name: string, uri: string}>
     */
    public function iconsets(): array
    {
        return array_map(
            static fn ($iconset) => $iconset->jsonSerialize(),
            $this->repository->iconsets(),
        );
    }

    /**
     * @return list<array{name: string, contents: string}>
     */
    public function icons(string $uri): array
    {
        try {
            $icons = $this->repository->icons($uri);
        } catch (RuntimeException) {
            abort(404, "Iconset [{$uri}] is missing.");
        }

        $payload = array_map(
            static fn (SvgIcon $icon) => $icon->jsonSerialize(),
            $icons,
        );

        usort($payload, static fn (array $left, array $right): int => strcasecmp(
            (string) ($left['name'] ?? ''),
            (string) ($right['name'] ?? ''),
        ));

        return array_values($payload);
    }
}
