<?php

namespace App\Repositories\IconPicker;

use App\Support\IconPicker\Catalog;
use Nevadskiy\Nova\IconPicker\SvgIcon;
use Nevadskiy\Nova\IconPicker\SvgIconset;
use RuntimeException;

class IconPickerRepository
{
    /**
     * @return list<SvgIconset>
     */
    public function iconsets(): array
    {
        return Catalog::iconsets();
    }

    public function getByUri(string $uri): SvgIconset
    {
        foreach ($this->iconsets() as $iconset) {
            if ($iconset->uri === $uri) {
                return $iconset;
            }
        }

        throw new RuntimeException("Iconset [{$uri}] is missing.");
    }

    /**
     * @return list<SvgIcon>
     */
    public function icons(string $uri): array
    {
        $path = $this->getByUri($uri)->path;

        if (! is_dir($path)) {
            return [];
        }

        return $this->getByUri($uri)->icons();
    }
}
