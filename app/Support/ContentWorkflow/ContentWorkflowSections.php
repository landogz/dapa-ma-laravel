<?php

namespace App\Support\ContentWorkflow;

final class ContentWorkflowSections
{
    /**
     * @return array<string, array{label:string, path:string, query:string}>
     */
    public static function all(): array
    {
        return [
            'contests' => ['label' => 'Contest', 'path' => '/admin/contests', 'query' => 'item'],
            'iec-materials' => ['label' => 'IEC material', 'path' => '/admin/iec-materials', 'query' => 'item'],
            'app-translations' => ['label' => 'App translation', 'path' => '/admin/app-translations', 'query' => 'item'],
            'care-toolkit' => ['label' => 'Care toolkit question', 'path' => '/admin/care-toolkit', 'query' => 'item'],
            'care-support' => ['label' => 'Care support resource', 'path' => '/admin/care-support', 'query' => 'item'],
            'hope-directory' => ['label' => 'Hope directory org', 'path' => '/admin/hope-directory', 'query' => 'item'],
            'hope-events' => ['label' => 'Hope event', 'path' => '/admin/hope-events', 'query' => 'item'],
            'settings' => ['label' => 'Settings content', 'path' => '/admin/settings', 'query' => 'item'],
            'lookups' => ['label' => 'Lookup option', 'path' => '/admin/lookups', 'query' => 'item'],
            'word-blocking' => ['label' => 'Blocked word', 'path' => '/admin/word-blocking', 'query' => 'item'],
            'notifications' => ['label' => 'Notification campaign', 'path' => '/admin/notifications', 'query' => 'item'],
            'posts' => ['label' => 'Post', 'path' => '/admin/posts', 'query' => 'post'],
            'kid-listo' => ['label' => 'Kid Listo quote', 'path' => '/admin/settings', 'query' => 'quote'],
            'legal-pages' => ['label' => 'Legal page', 'path' => '/admin/settings', 'query' => 'legal'],
        ];
    }

    public static function get(string $section): ?array
    {
        return self::all()[$section] ?? null;
    }

    public static function adminUrl(string $section, int|string $id): string
    {
        $meta = self::get($section);
        if (! $meta) {
            return '/admin/dashboard';
        }

        return $meta['path'].'?'.$meta['query'].'='.urlencode((string) $id);
    }
}
