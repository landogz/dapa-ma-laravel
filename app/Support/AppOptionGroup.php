<?php

namespace App\Support;

final class AppOptionGroup
{
    public const PRONOUNS = 'pronouns';

    public const PERSONA = 'persona';

    public const JOURNAL_SKY = 'journal_sky';

    public const JOURNAL_FEELING = 'journal_feeling';

    public const JOURNAL_IMPACT = 'journal_impact';

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
                'title' => 'How’s your sky today',
                'subtitle' => 'Journal check-in weather options',
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

            // Sky
            self::row(
                self::JOURNAL_SKY,
                'clear_skies',
                'Clear Skies',
                'Maaliwalas',
                1,
                'wb_sunny_outlined',
                'Steady and bright today.',
                'Matatag at maliwanag ngayon.',
                '#FBD116',
            ),
            self::row(
                self::JOURNAL_SKY,
                'passing_mist',
                'Passing Mist',
                'Pasadong ulap',
                2,
                'cloud_queue_outlined',
                'A little unclear, but moving.',
                'Medyo malabo, pero gumagalaw.',
                '#94A3B8',
            ),
            self::row(
                self::JOURNAL_SKY,
                'overcast',
                'Overcast',
                'Maulap',
                3,
                'cloud_outlined',
                'Heavy, but you are still here.',
                'Mabigat, pero nandito ka pa.',
                '#64748B',
            ),
            self::row(
                self::JOURNAL_SKY,
                'stormy',
                'Stormy',
                'Mabagyo',
                4,
                'thunderstorm_outlined',
                'Rough weather — be gentle with yourself.',
                'Masungit ang panahon — maging mahinahon sa sarili.',
                '#055498',
            ),

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
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function iconChoices(): array
    {
        return [
            ['value' => 'school_outlined', 'label' => 'School'],
            ['value' => 'family_restroom_outlined', 'label' => 'Family'],
            ['value' => 'menu_book_outlined', 'label' => 'Book'],
            ['value' => 'star_outline_rounded', 'label' => 'Star'],
            ['value' => 'favorite_outline', 'label' => 'Heart'],
            ['value' => 'flag_outlined', 'label' => 'Flag'],
            ['value' => 'work_outline', 'label' => 'Work'],
            ['value' => 'volunteer_activism_outlined', 'label' => 'Volunteer'],
            ['value' => 'medical_services_outlined', 'label' => 'Medical'],
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
        ];
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
