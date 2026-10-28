<?php

namespace App\Support\AppOptions;

use App\Support\AppOptionGroup;

final class JournalFeelingCatalog
{
    public const TONE_LIGHT = 'light';

    public const TONE_DARK = 'dark';

    /**
     * Unique feeling chips from the color-mood chart (light = positive, dark = negative).
     *
     * @return list<array<string, mixed>>
     */
    public static function seedRows(): array
    {
        $rows = [];
        $sort = 1;

        foreach (self::definitions() as $item) {
            $rows[] = self::row($sort, $item);
            $sort++;
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     key: string,
     *     label_tl: string,
     *     tone: string,
     *     colors: list<array{en: string, tl: string, hex: string}>
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::item('Love', 'Pag-ibig', self::TONE_LIGHT, [self::colorRed()]),
            self::item('Anger', 'Galit', self::TONE_DARK, [self::colorRed()]),
            self::item('Happy', 'Masaya', self::TONE_LIGHT, [self::colorPink()]),
            self::item('Optimistic', 'Optimista', self::TONE_LIGHT, [self::colorPink()]),
            self::item('Imaginative', 'Malikhain', self::TONE_LIGHT, [
                self::colorPink(),
                self::colorWhite(),
                self::colorViolet(),
            ]),
            self::item('Calm/Peaceful', 'Kalmado/Payapa', self::TONE_LIGHT, [self::colorBlue()]),
            self::item('Aloofness', 'Distansya', self::TONE_DARK, [self::colorBlue()]),
            self::item('Serenity', 'Katahimikan', self::TONE_LIGHT, [self::colorBlue()]),
            self::item('Sad', 'Malungkot', self::TONE_DARK, [self::colorBlue()]),
            self::item('Reflective', 'Mapagmuni', self::TONE_LIGHT, [self::colorViolet()]),
            self::item('Neutral', 'Neutral', self::TONE_LIGHT, [self::colorGray()]),
            self::item('Balance', 'Balanse', self::TONE_LIGHT, [self::colorGray()]),
            self::item('Overwhelmed', 'Nalulula', self::TONE_DARK, [self::colorGray()]),
            self::item('Unconfident', 'Walang kumpiyansa', self::TONE_DARK, [self::colorGray()]),
            self::item('Lack of Energy', 'Kakulangan sa enerhiya', self::TONE_DARK, [self::colorGray()]),
            self::item('Afraid', 'Takot', self::TONE_DARK, [self::colorPurple()]),
            self::item('Growth', 'Paglago', self::TONE_LIGHT, [self::colorGreen()]),
            self::item('Hope', 'Pag-asa', self::TONE_LIGHT, [self::colorGreen()]),
            self::item('Healing', 'Paghilom', self::TONE_LIGHT, [self::colorGreen()]),
            self::item('Boredom', 'Inip', self::TONE_DARK, [self::colorGreen()]),
            self::item('Sickness', 'Sakit', self::TONE_DARK, [self::colorGreen()]),
            self::item('Confidence', 'Kumpiyansa', self::TONE_LIGHT, [self::colorOrange()]),
            self::item('Anxious', 'Balisa', self::TONE_DARK, [self::colorOrange()]),
            self::item('Frustration', 'Frustrasyon', self::TONE_DARK, [self::colorOrange()]),
            self::item('Happiness', 'Kaligayahan', self::TONE_LIGHT, [self::colorYellow()]),
            self::item('Optimism', 'Optimismo', self::TONE_LIGHT, [self::colorYellow()]),
            self::item('Cheerful', 'Masayahin', self::TONE_LIGHT, [self::colorYellow()]),
            self::item('Power', 'Kapangyarihan', self::TONE_LIGHT, [self::colorBlack()]),
            self::item('Mourning', 'Pagluluksa', self::TONE_DARK, [self::colorBlack()]),
            self::item('Heaviness', 'Bigat', self::TONE_DARK, [self::colorBlack()]),
        ];
    }

    /**
     * @param  array{
     *     key: string,
     *     label_tl: string,
     *     tone: string,
     *     colors: list<array{en: string, tl: string, hex: string}>
     * }  $item
     * @return array<string, mixed>
     */
    private static function row(int $sortOrder, array $item): array
    {
        $isLight = $item['tone'] === self::TONE_LIGHT;
        $colorEn = implode(', ', array_column($item['colors'], 'en'));
        $colorTl = implode(', ', array_column($item['colors'], 'tl'));
        $accent = $item['colors'][0]['hex'] ?? null;

        $subtitleEn = $isLight
            ? "Positive mood (light) · {$colorEn}"
            : "Negative mood (dark) · {$colorEn}";
        $subtitleTl = $isLight
            ? "Positibong damdamin (liwanag) · {$colorTl}"
            : "Negatibong damdamin (dilim) · {$colorTl}";

        return [
            'group' => AppOptionGroup::JOURNAL_FEELING,
            'key' => $item['key'],
            'label_en' => $item['key'],
            'label_tl' => $item['label_tl'],
            'subtitle_en' => $subtitleEn,
            'subtitle_tl' => $subtitleTl,
            'icon' => $isLight ? 'auto_awesome_outlined' : 'cloud_outlined',
            'accent_color' => $accent,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];
    }

    /**
     * @param  list<array{en: string, tl: string, hex: string}>  $colors
     * @return array{key: string, label_tl: string, tone: string, colors: list<array{en: string, tl: string, hex: string}>}
     */
    private static function item(string $key, string $labelTl, string $tone, array $colors): array
    {
        return [
            'key' => $key,
            'label_tl' => $labelTl,
            'tone' => $tone,
            'colors' => $colors,
        ];
    }

    /**
     * @return array{en: string, tl: string, hex: string}
     */
    private static function color(string $en, string $tl, string $hex): array
    {
        return ['en' => $en, 'tl' => $tl, 'hex' => $hex];
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorRed(): array
    {
        return self::color('Red', 'Pula', '#CE2028');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorPink(): array
    {
        return self::color('Pink', 'Rosas', '#EC4899');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorBlue(): array
    {
        return self::color('Blue', 'Asul', '#055498');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorWhite(): array
    {
        return self::color('White', 'Puti', '#FFFFFF');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorViolet(): array
    {
        return self::color('Violet', 'Biolet', '#7C3AED');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorGray(): array
    {
        return self::color('Gray', 'Abo', '#64748B');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorPurple(): array
    {
        return self::color('Purple', 'Lila', '#581C87');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorGreen(): array
    {
        return self::color('Green', 'Berde', '#16A34A');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorOrange(): array
    {
        return self::color('Orange', 'Kahel', '#EA580C');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorYellow(): array
    {
        return self::color('Yellow', 'Dilaw', '#FBD116');
    }

    /** @return array{en: string, tl: string, hex: string} */
    private static function colorBlack(): array
    {
        return self::color('Black', 'Itim', '#0F172A');
    }
}
