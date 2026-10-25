<?php

namespace App\Support\IconPicker;

use Nevadskiy\Nova\IconPicker\SvgIconset;

/**
 * SVG iconsets for the admin picker, using nevadskiy/nova-icon-picker.
 *
 * @see https://github.com/xalaida/nova-icon-picker
 */
final class Catalog
{
    public const APP_URI = 'app';

    public const OUTLINE_URI = 'outline';

    public const SOLID_URI = 'solid';

    /**
     * @return list<SvgIconset>
     */
    public static function iconsets(): array
    {
        return [
            new SvgIconset(
                name: 'Outline',
                uri: self::OUTLINE_URI,
                path: resource_path('svg/heroicons/outline'),
            ),
            new SvgIconset(
                name: 'Solid',
                uri: self::SOLID_URI,
                path: resource_path('svg/heroicons/solid'),
                prefix: 'solid/',
            ),
            new SvgIconset(
                name: 'App',
                uri: self::APP_URI,
                path: resource_path('svg/app-icons'),
            ),
        ];
    }
}
