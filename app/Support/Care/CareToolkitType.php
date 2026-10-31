<?php

namespace App\Support\Care;

use App\Models\AppOption;
use App\Models\CareToolkitQuestion;
use App\Support\AppOptionGroup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Care check toolkit keys (stress / anxiety / sleep).
 * Catalog lives in Lookups (`app_options.group = care_toolkit`); inactive rows are hidden from the app.
 */
final class CareToolkitType
{
    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return CareToolkitQuestion::TYPES;
    }

    /**
     * Keys that may be used for questions / results (seeded keys + active catalog).
     *
     * @return list<string>
     */
    public static function allowedKeys(): array
    {
        $keys = self::defaults();

        if (! Schema::hasTable('app_options')) {
            return $keys;
        }

        try {
            $fromCatalog = AppOption::query()
                ->where('group', AppOptionGroup::CARE_TOOLKIT)
                ->pluck('key')
                ->filter()
                ->values()
                ->all();

            return array_values(array_unique([...$keys, ...$fromCatalog]));
        } catch (\Throwable) {
            return $keys;
        }
    }

    /**
     * Active + published toolkit keys for the mobile Self-care Toolkit screen.
     *
     * @return list<string>
     */
    public static function activeKeys(): array
    {
        if (! Schema::hasTable('app_options')) {
            return self::defaults();
        }

        try {
            $active = AppOption::query()
                ->where('group', AppOptionGroup::CARE_TOOLKIT)
                ->where('is_active', true)
                ->published()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('key')
                ->filter()
                ->values()
                ->all();

            if ($active === []) {
                // No catalog rows yet — keep legacy three checks.
                $hasAny = AppOption::query()
                    ->where('group', AppOptionGroup::CARE_TOOLKIT)
                    ->exists();

                return $hasAny ? [] : self::defaults();
            }

            return $active;
        } catch (\Throwable) {
            return self::defaults();
        }
    }

    public static function isActive(string $key): bool
    {
        return in_array($key, self::activeKeys(), true);
    }

    public static function validationRule(): In
    {
        return Rule::in(self::allowedKeys());
    }

    public static function activeValidationRule(): In
    {
        $keys = self::activeKeys();
        if ($keys === []) {
            // No active toolkits — still accept defaults so validation messages stay useful;
            // the controller should reject empty catalogs separately when needed.
            $keys = self::defaults();
        }

        return Rule::in($keys);
    }
}
