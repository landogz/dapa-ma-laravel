<?php

namespace App\Support\Journal;

use App\Models\AppOption;
use App\Models\DiaryEntry;
use App\Models\User;
use App\Support\AppOptionGroup;
use Illuminate\Support\Facades\Schema;

class CustomFeelings
{
    /**
     * @var list<string>
     */
    public const LEGACY_PRESETS = [
        'Grounded',
        'Restless',
        'Grateful',
        'Anxious',
        'Peaceful',
        'Triggered',
        'Hopeful',
        'Lonely',
        'Motivated',
        'Overwhelmed',
    ];

    /**
     * @return list<string>
     */
    public static function presetKeys(): array
    {
        $keys = self::LEGACY_PRESETS;

        if (Schema::hasTable('app_options')) {
            $keys = array_merge(
                $keys,
                AppOption::query()
                    ->where('group', AppOptionGroup::JOURNAL_FEELING)
                    ->pluck('key')
                    ->all(),
            );
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<mixed>  $feelings
     * @return list<string>
     */
    public static function customFrom(array $feelings): array
    {
        $presets = [];
        foreach (self::presetKeys() as $key) {
            $presets[mb_strtolower($key)] = true;
        }

        $custom = [];
        foreach ($feelings as $feeling) {
            $value = trim((string) $feeling);
            if ($value === '' || isset($presets[mb_strtolower($value)])) {
                continue;
            }
            $custom[] = $value;
        }

        return array_values(array_unique($custom));
    }

    /**
     * @param  list<mixed>|null  $feelings
     */
    public static function remember(User $user, ?array $feelings): void
    {
        $incoming = self::customFrom($feelings ?? []);
        if ($incoming === []) {
            return;
        }

        $merged = array_values(array_unique([
            ...self::stored($user),
            ...$incoming,
        ]));

        $user->forceFill(['journal_custom_feelings' => $merged])->save();
    }

    /**
     * @return list<string>
     */
    public static function forUser(User $user): array
    {
        $fromEntries = [];
        $rows = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('feelings')
            ->pluck('feelings');

        foreach ($rows as $feelings) {
            if (! is_array($feelings)) {
                continue;
            }
            foreach (self::customFrom($feelings) as $feeling) {
                $fromEntries[] = $feeling;
            }
        }

        return array_values(array_unique([
            ...self::stored($user),
            ...$fromEntries,
        ]));
    }

    /**
     * @return list<string>
     */
    private static function stored(User $user): array
    {
        $saved = $user->journal_custom_feelings;
        if (! is_array($saved)) {
            return [];
        }

        return self::customFrom($saved);
    }
}
