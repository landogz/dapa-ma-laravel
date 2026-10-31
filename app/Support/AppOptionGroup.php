<?php

namespace App\Support;

final class AppOptionGroup
{
    public const PRONOUNS = 'pronouns';

    public const PERSONA = 'persona';

    public const JOURNAL_SKY = 'journal_sky';

    public const JOURNAL_FEELING = 'journal_feeling';

    public const JOURNAL_IMPACT = 'journal_impact';

    public const CARE_TOOLKIT = 'care_toolkit';

    public const CARE_SUPPORT = 'care_support';

    public const SELECTION_SINGLE = 'single';

    public const SELECTION_MULTIPLE = 'multiple';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::PRONOUNS,
            self::PERSONA,
            self::JOURNAL_SKY,
            self::JOURNAL_FEELING,
            self::JOURNAL_IMPACT,
            self::CARE_TOOLKIT,
            self::CARE_SUPPORT,
        ];
    }

    /**
     * @return array<string, array{
     *     title: string,
     *     subtitle: string,
     *     selection: string,
     *     surface: string
     * }>
     */
    public static function meta(): array
    {
        return [
            self::PRONOUNS => [
                'title' => 'Pronouns',
                'subtitle' => 'Registration chips under “What are your pronouns?”',
                'selection' => self::SELECTION_SINGLE,
                'surface' => 'Registration',
            ],
            self::PERSONA => [
                'title' => 'Which best describes you',
                'subtitle' => 'Registration — multiple selections',
                'selection' => self::SELECTION_MULTIPLE,
                'surface' => 'Registration',
            ],
            self::JOURNAL_SKY => [
                'title' => 'What’s your sky today',
                'subtitle' => 'Journal check-in color moods (light = positive, dark = negative)',
                'selection' => self::SELECTION_SINGLE,
                'surface' => 'Journal',
            ],
            self::JOURNAL_FEELING => [
                'title' => 'What are you feeling',
                'subtitle' => 'Journal feeling chips (users can still add their own)',
                'selection' => self::SELECTION_MULTIPLE,
                'surface' => 'Journal',
            ],
            self::JOURNAL_IMPACT => [
                'title' => 'What shaped your day',
                'subtitle' => 'Journal impact areas such as Connection & Circle',
                'selection' => self::SELECTION_SINGLE,
                'surface' => 'Journal',
            ],
            self::CARE_TOOLKIT => [
                'title' => 'Care toolkits',
                'subtitle' => 'Self-care Toolkit checks — set Active to show on mobile, Inactive to hide',
                'selection' => self::SELECTION_SINGLE,
                'surface' => 'Care',
            ],
            self::CARE_SUPPORT => [
                'title' => 'Care Support',
                'subtitle' => 'Get Support filters — 24/7 Hotlines, Counseling, Crisis emergency, Crisis resources',
                'selection' => self::SELECTION_SINGLE,
                'surface' => 'Care',
            ],
        ];
    }

    public static function isValid(string $group): bool
    {
        return in_array($group, self::keys(), true);
    }

    public static function selectionMode(string $group): string
    {
        return self::meta()[$group]['selection'] ?? self::SELECTION_SINGLE;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function seedRows(): array
    {
        return [
            // Pronouns
            self::row(self::PRONOUNS, 'she_her', 'She/Her', 'She/Her', 1),
            self::row(self::PRONOUNS, 'he_him', 'He/Him', 'He/Him', 2),
            self::row(self::PRONOUNS, 'they_them', 'They/Them', 'They/Them', 3),
            self::row(self::PRONOUNS, 'ze_hir', 'Ze/Hir', 'Ze/Hir', 4),
            self::row(self::PRONOUNS, 'prefer_not', 'Prefer not to say', 'Ayaw sabihin', 5),
            self::row(self::PRONOUNS, 'others', 'Others', 'Iba pa', 6),

            // Persona
            self::row(self::PERSONA, 'student', 'Student', 'Estudyante', 1, 'school_outlined'),
            self::row(self::PERSONA, 'parent', 'Parent', 'Magulang', 2, 'family_restroom_outlined'),
            self::row(self::PERSONA, 'teacher', 'Teacher', 'Guro', 3, 'menu_book_outlined'),
            self::row(self::PERSONA, 'youth_leader', 'Youth Leader', 'Youth Leader', 4, 'star_outline_rounded'),
            self::row(self::PERSONA, 'health_worker', 'Health Worker', 'Health Worker', 5, 'favorite_outline'),
            self::row(self::PERSONA, 'concerned_citizen', 'Concerned Citizen', 'Concerned Citizen', 6, 'flag_outlined'),

            // Sky — color moods (light = positive, dark = negative)
            self::skyRow(1, 'sky_red', 'Red', 'Pula', '#CE2028', 'Love', 'Anger', 'Pag-ibig', 'Galit'),
            self::skyRow(2, 'sky_pink', 'Pink', 'Rosas', '#EC4899', 'Happy, Optimistic, Imaginative', null, 'Masaya, Optimista, Malikhain', null),
            self::skyRow(3, 'sky_blue', 'Blue', 'Asul', '#055498', 'Calm/Peaceful, Serenity', 'Aloofness, Sad', 'Kalmado/Payapa, Katahimikan', 'Distansya, Malungkot'),
            self::skyRow(4, 'sky_white', 'White', 'Puti', '#FFFFFF', 'Imaginative', null, 'Malikhain', null),
            self::skyRow(5, 'sky_violet', 'Violet', 'Biolet', '#7C3AED', 'Reflective, Imaginative', null, 'Mapagmuni, Malikhain', null),
            self::skyRow(6, 'sky_gray', 'Gray', 'Abo', '#64748B', 'Neutral, Balance', 'Overwhelmed, Unconfident, Lack of Energy', 'Neutral, Balanse', 'Nalulula, Walang kumpiyansa, Kakulangan sa enerhiya'),
            self::skyRow(7, 'sky_purple', 'Purple', 'Lila', '#581C87', null, 'Afraid', null, 'Takot'),
            self::skyRow(8, 'sky_green', 'Green', 'Berde', '#16A34A', 'Growth, Hope, Healing', 'Boredom, Sickness', 'Paglago, Pag-asa, Paghilom', 'Inip, Sakit'),
            self::skyRow(9, 'sky_orange', 'Orange', 'Kahel', '#EA580C', 'Confidence', 'Anxious, Frustration', 'Kumpiyansa', 'Balisa, Frustrasyon'),
            self::skyRow(10, 'sky_yellow', 'Yellow', 'Dilaw', '#FBD116', 'Happiness, Optimism, Cheerful', null, 'Kaligayahan, Optimismo, Masayahin', null),
            self::skyRow(11, 'sky_black', 'Black', 'Itim', '#0F172A', 'Power', 'Mourning, Heaviness', 'Kapangyarihan', 'Pagluluksa, Bigat'),

            // Feelings — keys match stored diary values
            self::row(self::JOURNAL_FEELING, 'Grounded', 'Grounded', 'Nakatuntong', 1),
            self::row(self::JOURNAL_FEELING, 'Restless', 'Restless', 'Hindi mapakali', 2),
            self::row(self::JOURNAL_FEELING, 'Grateful', 'Grateful', 'Nagpapasalamat', 3),
            self::row(self::JOURNAL_FEELING, 'Anxious', 'Anxious', 'Balisa', 4),
            self::row(self::JOURNAL_FEELING, 'Peaceful', 'Peaceful', 'Payapa', 5),
            self::row(self::JOURNAL_FEELING, 'Triggered', 'Triggered', 'Na-trigger', 6),
            self::row(self::JOURNAL_FEELING, 'Hopeful', 'Hopeful', 'Umaasa', 7),
            self::row(self::JOURNAL_FEELING, 'Lonely', 'Lonely', 'Nangungulila', 8),
            self::row(self::JOURNAL_FEELING, 'Motivated', 'Motivated', 'Motibado', 9),
            self::row(self::JOURNAL_FEELING, 'Overwhelmed', 'Overwhelmed', 'Nalulula', 10),

            // Impact / shaped your day
            self::row(self::JOURNAL_IMPACT, 'connection_circle', 'Connection & Circle', 'Koneksyon at bilog', 1, 'groups_outlined'),
            self::row(self::JOURNAL_IMPACT, 'cravings_urges', 'Cravings & Urges', 'Cravings at urges', 2, 'waves_outlined'),
            self::row(self::JOURNAL_IMPACT, 'routine_sleep', 'Routine & Sleep', 'Routine at tulog', 3, 'bedtime_outlined'),
            self::row(self::JOURNAL_IMPACT, 'environment_triggers', 'Environment & Triggers', 'Kapaligiran at triggers', 4, 'place_outlined'),
            self::row(self::JOURNAL_IMPACT, 'self_care_reflection', 'Self-Care & Reflection', 'Self-care at refleksiyon', 5, 'self_improvement_outlined'),

            // Care Self-care Toolkit checks (keys must match toolkit_type / check_type)
            self::row(
                self::CARE_TOOLKIT,
                'stress',
                'Stress Check',
                'Stress Check',
                1,
                'bolt_outlined',
                'Understand your stress level',
                'Unawain ang antas ng stress mo',
            ),
            self::row(
                self::CARE_TOOLKIT,
                'anxiety',
                'Anxiety Check',
                'Anxiety Check',
                2,
                'favorite_outline',
                'Check in on anxious feelings',
                'Suriin ang pakiramdam ng pagkabalisa',
            ),
            self::row(
                self::CARE_TOOLKIT,
                'sleep',
                'Sleep Quality',
                'Kalidad ng Tulog',
                3,
                'bedtime_outlined',
                'See how well you are resting',
                'Tingnan kung gaano kahusay ang iyong pahinga',
            ),

            // Care Get Support categories (keys must match care_support_resources.category)
            self::row(
                self::CARE_SUPPORT,
                'hotline',
                '24/7 Hotlines',
                '24/7 Hotlines',
                1,
                'phone_outlined',
                'Crisis and support hotlines',
                'Mga hotline para sa krisis at suporta',
            ),
            self::row(
                self::CARE_SUPPORT,
                'counseling',
                'Counseling',
                'Counseling',
                2,
                'chat_outlined',
                'Counseling and talk support',
                'Counseling at usapang tulong',
            ),
            self::row(
                self::CARE_SUPPORT,
                'crisis_emergency',
                'Crisis emergency',
                'Crisis emergency',
                3,
                'medical_services_outlined',
                'Emergency crisis banner',
                'Banner para sa emergency crisis',
            ),
            self::row(
                self::CARE_SUPPORT,
                'crisis_resource',
                'Crisis resources',
                'Crisis resources',
                4,
                'life_ring_outlined',
                'Crisis information resources',
                'Mga resource para sa crisis',
            ),
        ];
    }

    /**
     * Font Awesome class used in admin icon pickers (Nova-style grid).
     */
    public static function iconFaClass(string $value): string
    {
        return match ($value) {
            'school_outlined' => 'fas fa-graduation-cap',
            'family_restroom_outlined' => 'fas fa-people-roof',
            'menu_book_outlined' => 'fas fa-book-open',
            'star_outline_rounded' => 'far fa-star',
            'favorite_outline' => 'far fa-heart',
            'flag_outlined' => 'fas fa-flag',
            'work_outline' => 'fas fa-briefcase',
            'volunteer_activism_outlined' => 'fas fa-hand-holding-heart',
            'volunteer_activism_rounded' => 'fas fa-hand-holding-heart',
            'handshake_outlined', 'handshake_rounded' => 'fas fa-handshake',
            'spa_outlined' => 'fas fa-spa',
            'auto_awesome_outlined' => 'fas fa-wand-magic-sparkles',
            'menu_book_rounded' => 'fas fa-book',
            'medical_services_outlined' => 'fas fa-kit-medical',
            'palette_outlined' => 'fas fa-palette',
            'wb_sunny_outlined' => 'fas fa-sun',
            'cloud_queue_outlined' => 'fas fa-cloud-sun',
            'cloud_outlined' => 'fas fa-cloud',
            'thunderstorm_outlined' => 'fas fa-cloud-bolt',
            'water_drop_outlined' => 'fas fa-droplet',
            'air' => 'fas fa-wind',
            'groups_outlined' => 'fas fa-users',
            'groups_rounded' => 'fas fa-user-group',
            'waves_outlined' => 'fas fa-water',
            'bedtime_outlined' => 'fas fa-moon',
            'place_outlined' => 'fas fa-location-dot',
            'self_improvement_outlined' => 'fas fa-person-praying',
            'home_outlined', 'home_rounded' => 'fas fa-house',
            'help_outline_rounded', 'help_rounded' => 'fas fa-circle-question',
            'person_rounded' => 'fas fa-user',
            'person_outline' => 'far fa-user',
            'fitness_center_outlined' => 'fas fa-dumbbell',
            'restaurant_outlined' => 'fas fa-utensils',
            'label_outline' => 'fas fa-tag',
            'bolt_outlined' => 'fas fa-bolt',
            'psychology_outlined' => 'fas fa-brain',
            'phone_outlined' => 'fas fa-phone',
            'chat_outlined' => 'fas fa-comments',
            'life_ring_outlined' => 'fas fa-life-ring',
            default => 'fas fa-icons',
        };
    }

    /**
     * @return list<array{value: string, label: string, fa: string}>
     */
    public static function iconChoices(): array
    {
        $items = [
            ['value' => 'school_outlined', 'label' => 'School'],
            ['value' => 'family_restroom_outlined', 'label' => 'Family'],
            ['value' => 'menu_book_outlined', 'label' => 'Book'],
            ['value' => 'star_outline_rounded', 'label' => 'Star'],
            ['value' => 'favorite_outline', 'label' => 'Heart'],
            ['value' => 'flag_outlined', 'label' => 'Flag'],
            ['value' => 'work_outline', 'label' => 'Work'],
            ['value' => 'volunteer_activism_outlined', 'label' => 'Volunteer'],
            ['value' => 'volunteer_activism_rounded', 'label' => 'Care heart'],
            ['value' => 'handshake_outlined', 'label' => 'Handshake'],
            ['value' => 'spa_outlined', 'label' => 'Calm'],
            ['value' => 'auto_awesome_outlined', 'label' => 'Sparkle'],
            ['value' => 'menu_book_rounded', 'label' => 'Journal book'],
            ['value' => 'medical_services_outlined', 'label' => 'Medical'],
            ['value' => 'palette_outlined', 'label' => 'Color'],
            ['value' => 'wb_sunny_outlined', 'label' => 'Sun'],
            ['value' => 'cloud_queue_outlined', 'label' => 'Light cloud'],
            ['value' => 'cloud_outlined', 'label' => 'Cloud'],
            ['value' => 'thunderstorm_outlined', 'label' => 'Storm'],
            ['value' => 'water_drop_outlined', 'label' => 'Water'],
            ['value' => 'air', 'label' => 'Air'],
            ['value' => 'groups_outlined', 'label' => 'Groups'],
            ['value' => 'waves_outlined', 'label' => 'Waves'],
            ['value' => 'bedtime_outlined', 'label' => 'Sleep'],
            ['value' => 'place_outlined', 'label' => 'Place'],
            ['value' => 'self_improvement_outlined', 'label' => 'Self-care'],
            ['value' => 'home_outlined', 'label' => 'Home'],
            ['value' => 'fitness_center_outlined', 'label' => 'Fitness'],
            ['value' => 'restaurant_outlined', 'label' => 'Food'],
            ['value' => 'label_outline', 'label' => 'Label'],
            ['value' => 'bolt_outlined', 'label' => 'Bolt'],
            ['value' => 'psychology_outlined', 'label' => 'Mind'],
            ['value' => 'phone_outlined', 'label' => 'Phone'],
            ['value' => 'chat_outlined', 'label' => 'Chat'],
            ['value' => 'life_ring_outlined', 'label' => 'Life ring'],
        ];

        return array_map(static function (array $item): array {
            $item['fa'] = self::iconFaClass($item['value']);

            return $item;
        }, $items);
    }

    /**
     * @return array<string, mixed>
     */
    private static function skyRow(
        int $sortOrder,
        string $key,
        string $labelEn,
        string $labelTl,
        string $accentColor,
        ?string $lightEn,
        ?string $darkEn,
        ?string $lightTl,
        ?string $darkTl,
    ): array {
        $en = [];
        $tl = [];
        if ($lightEn) {
            $en[] = "Light: {$lightEn}";
            $tl[] = "Liwanag: {$lightTl}";
        }
        if ($darkEn) {
            $en[] = "Dark: {$darkEn}";
            $tl[] = "Dilim: {$darkTl}";
        }

        return self::row(
            self::JOURNAL_SKY,
            $key,
            $labelEn,
            $labelTl,
            $sortOrder,
            'palette_outlined',
            implode(' · ', $en),
            implode(' · ', $tl),
            $accentColor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        string $group,
        string $key,
        string $labelEn,
        string $labelTl,
        int $sortOrder,
        ?string $icon = null,
        ?string $subtitleEn = null,
        ?string $subtitleTl = null,
        ?string $accentColor = null,
    ): array {
        return [
            'group' => $group,
            'key' => $key,
            'label_en' => $labelEn,
            'label_tl' => $labelTl,
            'subtitle_en' => $subtitleEn,
            'subtitle_tl' => $subtitleTl,
            'icon' => $icon,
            'accent_color' => $accentColor,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];
    }
}
