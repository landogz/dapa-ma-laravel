<?php

namespace App\Support\ContentWorkflow;

/**
 * Sections that should push an in-app inbox alert to mobile app users when published.
 */
final class ContentWorkflowMobileNotify
{
    /**
     * @return list<string>
     */
    public static function sections(): array
    {
        return [
            'hope-events',
            'contests',
            'iec-materials',
            'care-support',
            'hope-directory',
            'care-toolkit',
        ];
    }

    public static function shouldNotify(string $section): bool
    {
        return in_array($section, self::sections(), true);
    }

    /**
     * @return array{type:string, link_type:string, title:string, body:string}
     */
    public static function copy(string $section, string $itemTitle): array
    {
        return match ($section) {
            'hope-events' => [
                'type' => 'new_hope_event',
                'link_type' => 'hope_event',
                'title' => 'New Hope event',
                'body' => sprintf('"%s" is now available.', $itemTitle),
            ],
            'contests' => [
                'type' => 'new_contest',
                'link_type' => 'contest',
                'title' => 'New contest',
                'body' => sprintf('"%s" is now open.', $itemTitle),
            ],
            'iec-materials' => [
                'type' => 'new_iec_material',
                'link_type' => 'iec_material',
                'title' => 'New IEC material',
                'body' => sprintf('"%s" is now available.', $itemTitle),
            ],
            'care-support' => [
                'type' => 'new_care_support',
                'link_type' => 'care_support',
                'title' => 'Care Support update',
                'body' => sprintf('"%s" was added to Care Support.', $itemTitle),
            ],
            'hope-directory' => [
                'type' => 'new_hope_directory',
                'link_type' => 'hope_directory',
                'title' => 'Hope Directory update',
                'body' => sprintf('"%s" was added to the Hope Directory.', $itemTitle),
            ],
            'care-toolkit' => [
                'type' => 'new_care_toolkit',
                'link_type' => 'care_toolkit',
                'title' => 'Self-care Toolkit update',
                'body' => sprintf('"%s" is now in the Self-care Toolkit.', $itemTitle),
            ],
            default => [
                'type' => 'content_published',
                'link_type' => $section,
                'title' => 'New update',
                'body' => sprintf('"%s" is now available.', $itemTitle),
            ],
        };
    }
}
