<?php

namespace App\Support\AppTranslation;

use App\Models\AppTranslation;
use App\Support\AppOptionGroup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Visual
{
    /**
     * Translation keys that currently render an illustration on Care cards.
     *
     * @var list<string>
     */
    public const CARD_KEYS = [
        'careSpeedJournal',
        'careSpeedToolkit',
        'careSpeedSupport',
        'careCalmCorner',
        'careKidListo',
        'bidaLearnTitle',
        'bidaHopeTitle',
        'bidaCareTitle',
    ];

    /**
     * Kid Listo mascot shown on the welcome screen, Care card, and home thought banner.
     *
     * @var list<string>
     */
    public const LOGO_KEYS = [
        'kidListoLogo',
    ];

    /**
     * Settings and legal hub screens that show a circular hero image.
     *
     * @var list<string>
     */
    public const PAGE_KEYS = [
        'settingsTitle',
        'accessibility',
        'aboutApp',
        'privacyPolicy',
        'privacyNotice',
        'termsOfUse',
        'careTitle',
        'hopeTitle',
        'homeWhatsNew',
        'homeContinueLearning',
        'homeDapeProgress',
        'splashAppName',
        'myProfileTitle',
    ];

    /**
     * Bottom menu labels on the mobile shell.
     *
     * @var list<string>
     */
    public const MENU_KEYS = [
        'navHome',
        'navHope',
        'navFaq',
        'navCare',
        'navAccount',
    ];

    /**
     * @return list<string>
     */
    public static function visualKeys(): array
    {
        return [...self::MENU_KEYS, ...self::CARD_KEYS, ...self::LOGO_KEYS, ...self::PAGE_KEYS];
    }

    public static function usesVisual(string $key): bool
    {
        return in_array($key, self::visualKeys(), true);
    }

    public static function visualKind(string $key): ?string
    {
        if (in_array($key, self::MENU_KEYS, true)) {
            return 'menu';
        }

        if (in_array($key, self::CARD_KEYS, true)) {
            return 'card';
        }

        if (in_array($key, self::LOGO_KEYS, true)) {
            return 'logo';
        }

        if (in_array($key, self::PAGE_KEYS, true)) {
            return 'page';
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string, fa: string}>
     */
    public static function iconChoices(): array
    {
        $extra = [
            ['value' => 'handshake_outlined', 'label' => 'Handshake'],
            ['value' => 'spa_outlined', 'label' => 'Calm'],
            ['value' => 'auto_awesome_outlined', 'label' => 'Sparkle'],
            ['value' => 'menu_book_rounded', 'label' => 'Journal book'],
            ['value' => 'volunteer_activism_rounded', 'label' => 'Care heart'],
            ['value' => 'home_rounded', 'label' => 'Home (filled)'],
            ['value' => 'groups_rounded', 'label' => 'Community (filled)'],
            ['value' => 'help_outline_rounded', 'label' => 'Help'],
            ['value' => 'help_rounded', 'label' => 'Help (filled)'],
            ['value' => 'person_rounded', 'label' => 'Profile (filled)'],
        ];

        $merged = [];
        foreach ([...AppOptionGroup::iconChoices(), ...$extra] as $choice) {
            $choice['fa'] = AppOptionGroup::iconFaClass($choice['value']);
            $merged[$choice['value']] = $choice;
        }

        return array_values($merged);
    }

    public static function imageUrl(?AppTranslation $translation): ?string
    {
        $path = $translation?->image_path;
        if (! filled($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatAdmin(AppTranslation $translation): array
    {
        return [
            'id' => $translation->id,
            'key' => $translation->key,
            'group' => $translation->group,
            'description' => $translation->description,
            'value_en' => $translation->value_en,
            'value_tl' => $translation->value_tl,
            'is_active' => (bool) $translation->is_active,
            'icon' => $translation->icon,
            'image_url' => self::imageUrl($translation),
            'uses_card_image' => self::usesVisual((string) $translation->key),
            'visual_kind' => self::visualKind((string) $translation->key),
            'updated_at' => $translation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{image_url: ?string, icon: ?string}|null
     */
    public static function publicAsset(AppTranslation $translation): ?array
    {
        $imageUrl = self::imageUrl($translation);
        $icon = filled($translation->icon) ? (string) $translation->icon : null;

        if ($imageUrl === null && $icon === null) {
            return null;
        }

        return [
            'image_url' => $imageUrl,
            'icon' => $icon,
        ];
    }
}
